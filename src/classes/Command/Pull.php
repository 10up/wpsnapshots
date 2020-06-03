<?php
/**
 * Pull command
 *
 * @package wpsnapshots
 */

namespace WPSnapshots\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use WPSnapshots\RepositoryManager;
use WPSnapshots\WordPressBridge;
use WPSnapshots\Config;
use WPSnapshots\Utils;
use WPSnapshots\SearchReplace;
use WPSnapshots\Snapshot;
use WPSnapshots\Meta;
use WPSnapshots\Log;
use Requests;

/**
 * The pull command grabs a snapshot and pulls it down overwriting your wp-content
 * folder and current DB.
 */
class Pull extends Command {

	/**
	 * Setup up command
	 */
	protected function configure() {
		$this->setName( 'pull' );
		$this->setDescription( 'Pull a snapshot into a WordPress instance.' );
		$this->addArgument( 'snapshot_id', InputArgument::REQUIRED, 'Snapshot ID to pull.' );
		$this->addOption( 'confirm', null, InputOption::VALUE_NONE, 'Confirm pull operation.' );
		$this->addOption( 'repository', null, InputOption::VALUE_REQUIRED, 'Repository to use. Defaults to first repository saved in config.' );
		$this->addOption( 'confirm_wp_download', null, InputOption::VALUE_NONE, 'Confirm WordPress download.' );
		$this->addOption( 'confirm_config_create', null, InputOption::VALUE_NONE, 'Confirm wp-config.php create.' );
		$this->addOption( 'confirm_wp_version_change', null, InputOption::VALUE_NONE, 'Confirm changing WP version to match snapshot.' );
		$this->addOption( 'confirm_ms_constant_update', null, InputOption::VALUE_NONE, 'Confirm updating constants in wp-config.php for multisite.' );
		$this->addOption( 'include_files', null, InputOption::VALUE_NONE, 'Pull files within snapshot.' );
		$this->addOption( 'include_db', null, InputOption::VALUE_NONE, 'Pull database within snapshot.' );

		$this->addOption( 'config_db_host', null, InputOption::VALUE_REQUIRED, 'Config database host.' );
		$this->addOption( 'config_db_name', null, InputOption::VALUE_REQUIRED, 'Config database name.' );
		$this->addOption( 'config_db_user', null, InputOption::VALUE_REQUIRED, 'Config database user.' );
		$this->addOption( 'config_db_password', null, InputOption::VALUE_REQUIRED, 'Config database password.' );

		$this->addOption( 'path', null, InputOption::VALUE_REQUIRED, 'Path to WordPress files.' );

		$this->addOption( 'skip_table_search_replace', [], InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Skip search and replacing specific tables. Leave out table prefix.' );

		$this->addOption( 'db_host', null, InputOption::VALUE_REQUIRED, 'Database host.' );
		$this->addOption( 'db_name', null, InputOption::VALUE_REQUIRED, 'Database name.' );
		$this->addOption( 'db_user', null, InputOption::VALUE_REQUIRED, 'Database user.' );
		$this->addOption( 'db_password', null, InputOption::VALUE_REQUIRED, 'Database password.' );

		/**
		 * Site Mapping JSON should look like this:
		 *
		 * [
		 *  {
		 *      "home_url" : "http://homeurl1",
		 *      "site_url" : "http://siteurl1"
		 *      "blog_id"  : 1
		 *  }
		 *  ...
		 * ]
		 *
		 * If blog_id isn't used, order will be respected as compared to the snapshot meta.
		 */
		$this->addOption( 'site_mapping', null, InputOption::VALUE_REQUIRED, 'JSON or path to site mapping file.' );
		$this->addOption( 'main_domain', null, InputOption::VALUE_REQUIRED, 'Main domain for multisite snapshots' );
	}

	/**
	 * Executes the command
	 *
	 * @param  InputInterface  $input Console input
	 * @param  OutputInterface $output Console output
	 */
	protected function execute( InputInterface $input, OutputInterface $output ) {
		Log::instance()->setOutput( $output );

		$repository = RepositoryManager::instance()->setup( $input->getOption( 'repository' ) );

		if ( ! $repository ) {
			Log::instance()->write( 'Could not setup repository.', 0, 'error' );
			return 1;
		}

		$id = $input->getArgument( 'snapshot_id' );

		$helper = $this->getHelper( 'question' );

		$remote_meta     = Meta::getRemote( $id, $repository->getName() );
		$local_meta      = Meta::getLocal( $id, $repository->getName() );
		$snapshot_source = null;
		$meta            = null;

		if ( empty( $remote_meta ) && empty( $local_meta ) ) {
			Log::instance()->write( 'Snapshot does not exist.', 0, 'error' );

			return 1;
		}

		if ( ! empty( $remote_meta ) && ! empty( $local_meta ) ) {
			$overwrite_local = $helper->ask( $input, $output, new ConfirmationQuestion( 'This snapshot exists locally. Do you want to overwrite it with the remote copy? (y/N) ', false ) );
		}

		if ( empty( $local_meta ) && ! empty( $remote_meta ) ) {
			$meta            = $remote_meta;
			$snapshot_source = 'remote';
		} elseif ( ! empty( $local_meta ) && empty( $remote_meta ) ) {
			$meta            = $local_meta;
			$snapshot_source = 'local';
		} elseif ( ! empty( $local_meta ) && ! empty( $remote_meta ) ) {
			if ( $overwrite_local ) {
				$meta            = $remote_meta;
				$snapshot_source = 'remote';
			} else {
				$meta            = $local_meta;
				$snapshot_source = 'local';
			}
		}

		if ( empty( $meta ) || ( empty( $meta['contains_files'] ) && empty( $meta['contains_db'] ) ) ) {
			Log::instance()->write( 'Snapshot is not valid.', 0, 'error' );

			return 1;
		}

		$path = $input->getOption( 'path' );

		if ( empty( $path ) ) {
			$path = getcwd();
		}

		$path = Utils\normalize_path( $path );

		if ( $meta['contains_files'] && $meta['contains_db'] ) {
			if ( $meta['contains_files'] ) {
				if ( empty( $input->getOption( 'include_files' ) ) ) {
					$pull_files = $helper->ask( $input, $output, new ConfirmationQuestion( 'Do you want to pull files? (Y/n) ', true ) );
				} else {
					$pull_files = true;
				}
			} else {
				$pull_files = false;
			}

			if ( $meta['contains_db'] ) {
				if ( empty( $input->getOption( 'include_db' ) ) ) {
					$pull_db = $helper->ask( $input, $output, new ConfirmationQuestion( 'Do you want to pull the database? (Y/n) ', true ) );
				} else {
					$pull_db = true;
				}
			} else {
				$pull_db = false;
			}
		} else {
			$pull_db    = $meta['contains_db'];
			$pull_files = $meta['contains_files'];
		}

		if ( 'remote' === $snapshot_source ) {
			$snapshot = Snapshot::getRemote( $id, $repository->getName(), ! $pull_files, ! $pull_db );
		} else {
			$snapshot = Snapshot::getLocal( $id, $repository->getName(), ! $pull_files, ! $pull_db );
		}

		if ( ! is_a( $snapshot, '\WPSnapshots\Snapshot' ) ) {
			return 1;
		}

		$snapshot_path = Utils\get_snapshot_directory() . $id . '/';

		$verbose = $input->getOption( 'verbose' );

		$verbose_pipe = ( ! $verbose ) ? '> /dev/null' : '';

		if ( ! Utils\is_wp_present( $path ) ) {
			Log::instance()->write( 'This is not a WordPress install. WordPress needs to be present in order to pull a snapshot.', 0, 'error' );

			if ( empty( $input->getOption( 'confirm_wp_download' ) ) ) {
				$download_wp = $helper->ask( $input, $output, new ConfirmationQuestion( 'Do you want to download WordPress? (y/N) ', false ) );

				if ( ! $download_wp ) {
					return 1;
				}
			}

			/**
			 * Download WordPress core files
			 */

			Log::instance()->write( 'Getting WordPress download URL...', 1 );

			$download_url = Utils\get_download_url();

			$headers = [ 'Accept' => 'application/json' ];
			$options = [
				'timeout'  => 600,
				'filename' => $snapshot_path . 'wp.tar.gz',
			];

			Log::instance()->write( 'Downloading WordPress...', 1 );

			$request = Requests::get( $download_url, $headers, $options );

			Log::instance()->write( 'Extracting WordPress...', 1 );

			exec( 'rm -rf ' . Utils\escape_shell_path( $path ) . 'wordpress && tar -C ' . Utils\escape_shell_path( $path ) . ' -xf ' . Utils\escape_shell_path( $snapshot_path ) . 'wp.tar.gz ' . $verbose_pipe );

			Log::instance()->write( 'Moving WordPress files...', 1 );

			exec( 'mv ' . Utils\escape_shell_path( $path ) . 'wordpress/* .' );

			Log::instance()->write( 'Removing temporary WordPress files...', 1 );

			exec( 'rm -rf ' . Utils\escape_shell_path( $path ) . 'wordpress' );

			Log::instance()->write( 'WordPress downloaded.' );
		}

		if ( ! Utils\locate_wp_config( $path ) ) {
			Log::instance()->write( 'No wp-config.php file present. wp-config.php needs to be setup in order to pull a snapshot.', 0, 'error' );

			if ( empty( $input->getOption( 'confirm_config_create' ) ) ) {
				$create_config = $helper->ask( $input, $output, new ConfirmationQuestion( 'Do you want to create a wp-config.php file? (y/N) ', false ) );

				if ( ! $create_config ) {
					return 1;
				}
			}

			$config_constants = [
				'FS_METHOD' => 'direct',
			];

			if ( ! empty( $snapshot->meta['subdomain_install'] ) ) {
				$config_constants['ADMIN_COOKIE_PATH'] = '/';
				$config_constants['COOKIE_DOMAIN']     = '';
				$config_constants['COOKIEPATH']        = '';
				$config_constants['SITECOOKIEPATH']    = '';
			}

			if ( ! empty( $input->getOption( 'config_db_host' ) ) ) {
				$config_constants['DB_HOST'] = $input->getOption( 'config_db_host' );
			} else {
				$db_host_question = new Question( 'What is your database host? ' );
				$db_host_question->setValidator( '\WPSnapshots\Utils\not_empty_validator' );

				$config_constants['DB_HOST'] = $helper->ask( $input, $output, $db_host_question );
			}

			if ( ! empty( $input->getOption( 'config_db_name' ) ) ) {
				$config_constants['DB_NAME'] = $input->getOption( 'config_db_name' );
			} else {
				$db_name_question = new Question( 'What is your database name? ' );
				$db_name_question->setValidator( '\WPSnapshots\Utils\not_empty_validator' );

				$config_constants['DB_NAME'] = $helper->ask( $input, $output, $db_name_question );
			}

			if ( ! empty( $input->getOption( 'config_db_user' ) ) ) {
				$config_constants['DB_USER'] = $input->getOption( 'config_db_user' );
			} else {
				$db_user_question = new Question( 'What is your database user? ' );
				$db_user_question->setValidator( '\WPSnapshots\Utils\not_empty_validator' );

				$config_constants['DB_USER'] = $helper->ask( $input, $output, $db_user_question );
			}

			if ( ! empty( $input->getOption( 'config_db_password' ) ) ) {
				$config_constants['DB_PASSWORD'] = $input->getOption( 'config_db_password' );
			} else {
				$db_password_question = new Question( 'What is your database password? ' );
				$db_password_question->setValidator( '\WPSnapshots\Utils\not_empty_validator' );

				$config_constants['DB_PASSWORD'] = $helper->ask( $input, $output, $db_password_question );
			}

			Log::instance()->write( 'Creating wp-config.php file...', 1 );

			Utils\create_config_file( $path . 'wp-config.php', $path . 'wp-config-sample.php', $config_constants );

			Log::instance()->write( 'wp-config.php created.' );
		}

		$extra_config_constants = [
			'WP_CACHE' => false,
		];

		$db_host     = $input->getOption( 'db_host' );
		$db_name     = $input->getOption( 'db_name' );
		$db_user     = $input->getOption( 'db_user' );
		$db_password = $input->getOption( 'db_password' );

		if ( ! empty( $db_host ) ) {
			$extra_config_constants['DB_HOST'] = $db_host;
		} if ( ! empty( $db_name ) ) {
			$extra_config_constants['DB_NAME'] = $db_name;
		} if ( ! empty( $db_user ) ) {
			$extra_config_constants['DB_USER'] = $db_user;
		} if ( ! empty( $db_password ) ) {
			$extra_config_constants['DB_PASSWORD'] = $db_password;
		}

		/**
		 * Make sure we don't redirect if no tables exist
		 */
		define( 'WP_INSTALLING', true );

		Log::instance()->write( 'Bootstrapping WordPress...', 1 );

		if ( ! WordPressBridge::instance()->load( $path, $extra_config_constants ) ) {
			Log::instance()->write( 'Could not connect to WordPress database.', 0, 'error' );

			return 1;
		}

		/**
		 * We make the user double confirm since this could destroy a website
		 */
		$confirm = $input->getOption( 'confirm' );

		if ( empty( $confirm ) ) {

			$confirm = $helper->ask( $input, $output, new ConfirmationQuestion( 'Are you sure you want to do this? This is a potentially destructive operation. You should run a back up first. (y/N) ', false ) );

			if ( ! $confirm ) {
				return 1;
			}
		}

		if ( $pull_db ) {
			$pre_update_site_url = site_url();
			$pre_update_home_url = home_url();

			$use_https = false;

			if ( ! empty( $pre_update_site_url ) ) {
				$pre_update_site_url_parsed = parse_url( $pre_update_site_url );

				if ( 'https' === $pre_update_site_url_parsed['scheme'] ) {
					$use_https = true;
				}
			}

			Log::instance()->write( 'Pulling database...' );

			Log::instance()->write( 'Decompressing database backup file...' );

			exec( 'cd ' . Utils\escape_shell_path( $snapshot_path ) . ' && gzip -d -k -f data.sql.gz ' . $verbose_pipe );

			/**
			 * Import tables
			 */

			$args = array(
				'host'     => DB_HOST,
				'user'     => DB_USER,
				'pass'     => DB_PASSWORD,
				'database' => DB_NAME,
				'execute'  => 'SET GLOBAL max_allowed_packet=51200000;',
			);

			Log::instance()->write( 'Attempting to set max_allowed_packet...', 1 );

			$command_result = Utils\run_mysql_command( 'mysql --no-defaults --no-auto-rehash', $args, '', false );

			if ( 0 !== $command_result ) {
				Log::instance()->write( 'Could not set MySQL max_allowed_packet. If MySQL import fails, try running WP Snapshots using root DB user.', 0, 'warning' );
			}

			Log::instance()->write( 'Updating database. This may take awhile depending on the size of the database (' . Utils\format_bytes( filesize( $snapshot_path . 'data.sql' ) ) . ')...' );
			$query = 'SET autocommit = 0; SET unique_checks = 0; SET foreign_key_checks = 0; SOURCE %s; COMMIT;';

			$args = array(
				'host'     => DB_HOST,
				'user'     => DB_USER,
				'pass'     => DB_PASSWORD,
				'database' => DB_NAME,
				'execute'  => sprintf( $query, $snapshot_path . 'data.sql' ),
			);

			if ( ! isset( $assoc_args['default-character-set'] ) && defined( 'DB_CHARSET' ) && constant( 'DB_CHARSET' ) ) {
				$args['default-character-set'] = constant( 'DB_CHARSET' );
			}

			Utils\run_mysql_command( 'mysql --no-defaults --no-auto-rehash', $args );

			/**
			 * Customize DB for current install
			 */

			Log::instance()->write( 'Getting MySQL tables...', 1 );

			$all_tables = Utils\get_tables( false );

			global $wpdb;

			$main_blog_id = ( defined( 'BLOG_ID_CURRENT_SITE' ) ) ? BLOG_ID_CURRENT_SITE : null;

			$current_table_prefix = $wpdb->get_blog_prefix( $main_blog_id );

			/**
			 * First update table prefixes
			 */
			if ( ! empty( $snapshot->meta['table_prefix'] ) && ! empty( $current_table_prefix ) && $snapshot->meta['table_prefix'] !== $current_table_prefix ) {
				Log::instance()->write( 'Renaming WordPress tables...' );

				foreach ( $all_tables as $table ) {
					if ( 0 === strpos( $table, $snapshot->meta['table_prefix'] ) ) {
						/**
						 * Update this table to use the current config prefix
						 */
						$new_table = $current_table_prefix . str_replace( $snapshot->meta['table_prefix'], '', $table );
						$wpdb->query( sprintf( 'RENAME TABLE `%s` TO `%s`', esc_sql( $table ), esc_sql( $new_table ) ) );
					}
				}
			}

			global $wp_version;

			if ( ! empty( $snapshot->meta['wp_version'] ) && $snapshot->meta['wp_version'] !== $wp_version ) {
				$confirm_wp_version_change = $input->getOption( 'confirm_wp_version_change' );

				$change_wp_version = true;

				if ( empty( $confirm_wp_version_change ) ) {
					$change_wp_version = $helper->ask( $input, $output, new ConfirmationQuestion( 'This snapshot is running WordPress version ' . $snapshot->meta['wp_version'] . ', and you are running ' . $wp_version . '. Do you want to change your WordPress version to ' . $snapshot->meta['wp_version'] . '? (Y/n) ', true ) );
				}

				if ( ! empty( $change_wp_version ) ) {
					// Delete old WordPress
					exec( 'rm -rf ' . Utils\escape_shell_path( $path ) . 'wp-includes ' . Utils\escape_shell_path( $path ) . 'wp-admin' );
					exec( 'cd ' . Utils\escape_shell_path( $path ) . ' && rm index.php && rm xmlrpc.php && rm license.txt && rm readme.html' );
					exec( 'cd ' . Utils\escape_shell_path( $path ) . ' && find . -maxdepth 1 ! -path . -type f -name "wp-*.php" ! -iname "wp-config.php" -delete' );
					exec( 'rm -rf ' . Utils\escape_shell_path( $path ) . 'wordpress' );

					Log::instance()->write( 'Getting WordPress download URL...', 1 );

					$download_url = Utils\get_download_url( $snapshot->meta['wp_version'] );

					$headers = [ 'Accept' => 'application/json' ];
					$options = [
						'timeout'  => 600,
						'filename' => $snapshot_path . 'wp.tar.gz',
					];

					Log::instance()->write( 'Downloading WordPress ' . $snapshot->meta['wp_version'] . '...', 1 );

					$request = Requests::get( $download_url, $headers, $options );

					Log::instance()->write( 'Extracting WordPress...', 1 );

					exec( 'tar -C ' . Utils\escape_shell_path( $path ) . ' -xf ' . Utils\escape_shell_path( $snapshot_path ) . 'wp.tar.gz ' . $verbose_pipe );

					Log::instance()->write( 'Moving WordPress files...', 1 );

					exec( 'rm -rf ' . Utils\escape_shell_path( $path ) . 'wordpress/wp-content && mv ' . Utils\escape_shell_path( $path ) . 'wordpress/* .' );

					Log::instance()->write( 'Removing temporary WordPress files...', 1 );

					exec( 'rm -rf ' . Utils\escape_shell_path( $path ) . 'wordpress' );

					Log::instance()->write( 'WordPress version changed.' );
				}
			}

			/**
			 * Get tables again since it could have changed
			 */
			$wp_tables = Utils\get_tables();

			/**
			 * Handle url replacements
			 */
			if ( ! empty( $snapshot->meta['sites'] ) ) {
				Log::instance()->write( 'Preparing to replace URLs...' );

				$site_mapping     = [];
				$site_mapping_raw = $input->getOption( 'site_mapping' );

				if ( ! empty( $site_mapping_raw ) ) {
					$site_mapping_raw = json_decode( $site_mapping_raw, true );

					foreach ( $site_mapping_raw as $site ) {
						if ( ! empty( $site['blog_id'] ) ) {
							$site_mapping[ (int) $site['blog_id'] ] = $site;
						} else {
							$site_mapping[] = $site;
						}
					}

					if ( empty( $snapshot->meta['multisite'] ) ) {
						$site_mapping = array_values( $site_mapping );
					}
				}

				$url_validator = function( $answer ) {
					if ( '' === trim( $answer ) || false !== strpos( $answer, ' ' ) || ! preg_match( '#https?:#i', $answer ) ) {
						throw new \RuntimeException(
							'URL is not valid. Should be prefixed with http(s) and contain no spaces.'
						);
					}

					return $answer;
				};

				$skip_table_search_replace = $input->getOption( 'skip_table_search_replace' );

				if ( empty( $skip_table_search_replace ) ) {
					$skip_table_search_replace = [
						'terms',
						'term_relationships',
						'term_taxonomy',
					];
				}

				if ( ! empty( $snapshot->meta['multisite'] ) ) {
					$used_home_urls = [];
					$used_site_urls = [];

					$skip_table_search_replace = array_merge( [ 'site', 'blogs' ], $skip_table_search_replace );

					// Make WP realize we are in multisite now
					if ( ! defined( 'MULTISITE' ) ) {
						define( 'MULTISITE', true );
					}

					if ( empty( $snapshot->meta['subdomain_install'] ) ) {
						Log::instance()->write( 'Multisite installation (path based install) detected.' );
					} else {
						Log::instance()->write( 'Multisite installation (subdomain based install) detected.' );
					}

					$i = 0;

					/**
					 * First handle multisite intricacies. We need to set domains and paths for each blog. We'll copy
					 * whatever the current path is. However, the user needs to set the domain for every blog. For
					 * path based installed we just use the first domain.
					 */

					$main_domain = $input->getOption( 'main_domain' );

					$snapshot_main_domain = ( ! empty( $snapshot->meta['domain_current_site'] ) ) ? $snapshot->meta['domain_current_site'] : '';

					if ( ! empty( $snapshot_main_domain ) ) {
						$main_domain_question = new Question( 'Main domain (defaults to main domain in the snapshot: ' . $snapshot_main_domain . '): ', $snapshot_main_domain );
					} else {
						$example_site = 'mysite.test';

						if ( ! empty( $snapshot->meta['sites'][0]['home_url'] ) ) {
							$example_site = parse_url( $snapshot->meta['sites'][0]['home_url'], PHP_URL_HOST );
						}

						$main_domain_question = new Question( 'Main domain (' . $example_site . ' for example): ' );
					}

					$main_domain_question->setValidator(
						function( $answer ) {
							if ( '' === trim( $answer ) || false !== strpos( $answer, ' ' ) || preg_match( '#https?:#i', $answer ) ) {
								throw new \RuntimeException(
									'Domain not valid. The domain should be in the form of `google.com`, no https:// needed'
								);
							}

								return $answer;
						}
					);

					if ( empty( $main_domain ) ) {
						$main_domain = $helper->ask( $input, $output, $main_domain_question );
					}

					foreach ( $snapshot->meta['sites'] as $site ) {

						Log::instance()->write( 'Replacing URLs for blog ' . $site['blog_id'] . '.' );

						if ( ! empty( $site_mapping[ (int) $site['blog_id'] ] ) ) {
							$new_home_url = $site_mapping[ (int) $site['blog_id'] ]['home_url'];
							$new_site_url = $site_mapping[ (int) $site['blog_id'] ]['site_url'];
						} else {
							$home_question = new Question( 'Home URL (defaults home URL in snapshot: ' . $site['home_url'] . '): ', $site['home_url'] );
							$home_question->setValidator( $url_validator );

							$new_home_url = $helper->ask( $input, $output, $home_question );

							while ( in_array( $new_home_url, $used_home_urls, true ) ) {
								Log::instance()->write( 'Sorry, that home URL is already taken by another site.', 0, 'error' );

								$home_question = new Question( 'Home URL (defaults to home URL in snapshot: ' . $site['home_url'] . '): ', $site['home_url'] );
								$home_question->setValidator( $url_validator );

								$new_home_url = $helper->ask( $input, $output, $home_question );
							}

							$site_question = new Question( 'Site URL (defaults to site URL in snapshot: ' . $site['site_url'] . '): ', $site['site_url'] );
							$site_question->setValidator( $url_validator );

							$new_site_url = $helper->ask( $input, $output, $site_question );

							while ( in_array( $new_site_url, $used_site_urls, true ) ) {
								Log::instance()->write( 'Sorry, that site URL is already taken by another site.', 0, 'error' );

								$site_question = new Question( 'Site URL (defaults to site URL in snapshot: ' . $site['site_url'] . '): ', $site['site_url'] );
								$site_question->setValidator( $url_validator );

								$new_site_url = $helper->ask( $input, $output, $site_question );
							}
						}

						if ( empty( $first_home_url ) ) {
							$first_home_url = $new_home_url;
						}

						$used_home_urls[] = $new_home_url;
						$used_site_urls[] = $new_site_url;

						Log::instance()->write( 'Updating blogs table...', 1 );

						$blog_path = trailingslashit( parse_url( $new_home_url, PHP_URL_PATH ) );
						if ( empty( $blog_path ) ) {
							$blog_path = '/';
						}

						$blog_url = parse_url( $new_home_url, PHP_URL_HOST );

						if ( ! empty( parse_url( $new_home_url, PHP_URL_PORT ) ) ) {
							$blog_url .= ':' . parse_url( $new_home_url, PHP_URL_PORT );
						}

						/**
						 * Update multisite stuff for each blog
						 */
						$wpdb->query( $wpdb->prepare( 'UPDATE ' . $current_table_prefix . 'blogs SET path=%s, domain=%s WHERE blog_id=%d', $blog_path, $blog_url, (int) $site['blog_id'] ) );

						/**
						 * Update all tables except wp_site and wp_blog since we handle that separately
						 */
						$tables_to_update = [];

						foreach ( $wp_tables as $table ) {
							if ( 1 === (int) $site['blog_id'] ) {
								if ( preg_match( '#^' . $current_table_prefix . '#', $table ) && ! preg_match( '#^' . $current_table_prefix . '[0-9]+_#', $table ) ) {
									if ( ! in_array( str_replace( $current_table_prefix, '', $table ), $skip_table_search_replace ) ) {
										$tables_to_update[] = $table;
									}
								}
							} else {
								if ( preg_match( '#^' . $current_table_prefix . $site['blog_id'] . '_#', $table ) ) {
									$raw_table = str_replace( $current_table_prefix . $site['blog_id'] . '_', '', $table );

									if ( ! in_array( $raw_table, $skip_table_search_replace ) ) {
										$tables_to_update[] = $table;
									}
								}
							}
						}

						if ( ! empty( $tables_to_update ) ) {
							Log::instance()->write( 'Running replacement... This may take awhile depending on the size of the database.' );
							Log::instance()->write( 'Search and replacing tables: ' . implode( ', ', $tables_to_update ), 1 );

							new SearchReplace( $site['home_url'], $new_home_url, $tables_to_update );

							if ( $site['home_url'] !== $site['site_url'] ) {
								new SearchReplace( $site['site_url'], $new_site_url, $tables_to_update );
							}
						}

						$i++;
					}

					Log::instance()->write( 'Updating site table...', 1 );

					/**
					 * Update site domain with main domain
					 */
					$wpdb->query( $wpdb->prepare( 'UPDATE ' . $current_table_prefix . 'site SET domain=%s', $main_domain ) );

					Log::instance()->write( 'URLs replaced.' );

					if (
						! defined( 'BLOG_ID_CURRENT_SITE' )
						|| ( ! empty( $snapshot->meta['blog_id_current_site'] ) && BLOG_ID_CURRENT_SITE !== (int) $snapshot->meta['blog_id_current_site'] )
						|| ! defined( 'SITE_ID_CURRENT_SITE' )
						|| ( ! empty( $snapshot->meta['site_id_current_site'] ) && SITE_ID_CURRENT_SITE !== (int) $snapshot->meta['site_id_current_site'] )
						|| ! defined( 'PATH_CURRENT_SITE' )
						|| ( ! empty( $snapshot->meta['path_current_site'] ) && PATH_CURRENT_SITE !== $snapshot->meta['path_current_site'] )
						|| ! defined( 'MULTISITE' )
						|| ! MULTISITE
						|| ! defined( 'DOMAIN_CURRENT_SITE' )
						|| DOMAIN_CURRENT_SITE !== $main_domain
						|| ! defined( 'SUBDOMAIN_INSTALL' )
						|| SUBDOMAIN_INSTALL !== $snapshot->meta['subdomain_install']
					) {
						$update_ms_constants = $input->getOption( 'confirm_ms_constant_update' );

						if ( ! $update_ms_constants ) {
							$update_ms_constants = $helper->ask( $input, $output, new ConfirmationQuestion( 'Constants need to be updated in your wp-config.php file. Want WP Snapshots to do this automatically? (Y/n) ', true ) );
						}

						if ( ! $update_ms_constants ) {

							Log::instance()->write( 'The following code should be in your wp-config.php file:', 0, 'warning' );
							Log::instance()->write(
								"define('WP_ALLOW_MULTISITE', true);
	define('MULTISITE', true);
	define('SUBDOMAIN_INSTALL', " . ( ( ! empty( $snapshot->meta['subdomain_install'] ) ) ? 'true' : 'false' ) . ");
	define('DOMAIN_CURRENT_SITE', '" . $main_domain . "');
	define('PATH_CURRENT_SITE', '" . ( ( ! empty( $snapshot->meta['path_current_site'] ) ) ? $snapshot->meta['path_current_site'] : '/' ) . "');
	define('SITE_ID_CURRENT_SITE', " . ( ( ! empty( $snapshot->meta['site_id_current_site'] ) ) ? $snapshot->meta['site_id_current_site'] : '1' ) . ");
	define('BLOG_ID_CURRENT_SITE', " . ( ( ! empty( $snapshot->meta['blog_id_current_site'] ) ) ? $snapshot->meta['blog_id_current_site'] : '1' ) . ');',
								0,
								'success'
							);
						} else {
							Utils\write_constants_to_wp_config(
								[
									'WP_ALLOW_MULTISITE'   => true,
									'MULTISITE'            => true,
									'SUBDOMAIN_INSTALL'    => ( ! empty( $snapshot->meta['subdomain_install'] ) ) ? true : false,
									'DOMAIN_CURRENT_SITE'  => $main_domain,
									'PATH_CURRENT_SITE'    => ( ! empty( $snapshot->meta['path_current_site'] ) ) ? $snapshot->meta['path_current_site'] : '/',
									'SITE_ID_CURRENT_SITE' => ( ! empty( $snapshot->meta['site_id_current_site'] ) ) ? $snapshot->meta['site_id_current_site'] : 1,
									'BLOG_ID_CURRENT_SITE' => ( ! empty( $snapshot->meta['blog_id_current_site'] ) ) ? $snapshot->meta['blog_id_current_site'] : 1,
								],
								$path . 'wp-config.php'
							);

							Log::instance()->write( 'Multisite constants added to wp-config.php.' );
						}
					}
				} else {
					if ( ! empty( $site_mapping ) ) {
						$new_home_url = $site_mapping[0]['home_url'];
						$new_site_url = $site_mapping[0]['site_url'];
					} else {
						$home_question = new Question( 'Home URL (defaults to home URL in snapshot: ' . $snapshot->meta['sites'][0]['home_url'] . '): ', $snapshot->meta['sites'][0]['home_url'] );
						$home_question->setValidator( $url_validator );

						$new_home_url = $helper->ask( $input, $output, $home_question );

						$site_question = new Question( 'Site URL (defaults to site URL in snapshot: ' . $snapshot->meta['sites'][0]['site_url'] . '): ', $snapshot->meta['sites'][0]['site_url'] );
						$site_question->setValidator( $url_validator );

						$new_site_url = $helper->ask( $input, $output, $site_question );
					}

					$first_home_url = $new_home_url;

					Log::instance()->write( 'Running replacement... This may take awhile depending on the size of the database.' );

					$tables_to_update = [];

					foreach ( $wp_tables as $table ) {
						$raw_table = str_replace( $current_table_prefix, '', $table );

						if ( ! in_array( $raw_table, $skip_table_search_replace ) ) {
							$tables_to_update[] = $table;
						}
					}

					Log::instance()->write( 'Search and replacing tables: ' . implode( ', ', $tables_to_update ), 1 );

					new SearchReplace( $snapshot->meta['sites'][0]['home_url'], $new_home_url, $tables_to_update );

					if ( $snapshot->meta['sites'][0]['home_url'] !== $snapshot->meta['sites'][0]['site_url'] ) {
						new SearchReplace( $snapshot->meta['sites'][0]['site_url'], $new_site_url, $tables_to_update );
					}

					Log::instance()->write( 'URLs replaced.' );
				}
			}

			/**
			 * Create wpsnapshots user
			 */
			Log::instance()->write( 'Cleaning wpsnapshots user...', 1 );

			$user = get_user_by( 'login', 'wpsnapshots' );

			$user_args = [
				'user_login' => 'wpsnapshots',
				'user_pass'  => 'password',
				'user_email' => 'wpsnapshots@wpsnapshots.test',
				'role'       => 'administrator',
			];

			if ( ! empty( $user ) ) {
				$user_args['ID']        = $user->ID;
				$user_args['user_pass'] = wp_hash_password( 'password' );
			}

			$user_id = wp_insert_user( $user_args );

			if ( ! empty( $snapshot->meta['multisite'] ) ) {
				$site_admins_rows = $wpdb->get_results( 'SELECT * FROM ' . Utils\esc_sql_name( $current_table_prefix . 'sitemeta' ) . ' WHERE meta_key="site_admins"', ARRAY_A );

				if ( ! empty( $site_admins_rows ) ) {
					foreach ( $site_admins_rows as $site_admin_row ) {
						$admins = unserialize( $site_admin_row['meta_value'] );

						$admins[] = 'wpsnapshots';

						$wpdb->update(
							$current_table_prefix . 'sitemeta',
							[
								'meta_value' => serialize( array_unique( $admins ) ),
							],
							[
								'meta_id' => $site_admin_row['meta_id'],
							]
						);
					}
				}
			}
		}

		if ( $pull_files ) {
			Log::instance()->write( 'Pulling files...' );

			Log::instance()->write( 'Replacing wp-content/...' );

			Log::instance()->write( 'wp-content path set to ' . WP_CONTENT_DIR, 1 );

			Log::instance()->write( 'Removing old wp-content/...', 1 );
			exec( 'rm -rf ' . Utils\escape_shell_path( WP_CONTENT_DIR ) . '/..?* ' . Utils\escape_shell_path( WP_CONTENT_DIR ) . '/.[!.]* ' . Utils\escape_shell_path( WP_CONTENT_DIR ) . '/*' );

			Log::instance()->write( 'Extracting snapshot wp-content/...', 1 );

			exec( 'mkdir -p ' . Utils\escape_shell_path( WP_CONTENT_DIR ) );

			exec( 'tar -C ' . Utils\escape_shell_path( WP_CONTENT_DIR ) . ' -xf ' . Utils\escape_shell_path( $snapshot_path ) . 'files.tar.gz ' . $verbose_pipe );
		}

		/**
		 * Cleaning up decompressed files
		 */
		Log::instance()->write( 'Cleaning up temporary files...', 1 );

		@unlink( $snapshot_path . 'data.sql' );
		@unlink( $snapshot_path . 'wp.tar.gz' );

		Log::instance()->write( 'Pull finished.', 0, 'success' );

		if ( $pull_db ) {
			Log::instance()->write( 'Visit in your browser: ' . $first_home_url, 0, 'success' );

			if ( 'localhost' !== parse_url( $first_home_url, PHP_URL_HOST ) ) {
				Log::instance()->write( 'Make sure the following entry is in your hosts file: "127.0.0.1 ' . parse_url( $first_home_url, PHP_URL_HOST ) . '"', 0, 'success' );
			}

			Log::instance()->write( 'Admin login: username - "wpsnapshots", password - "password"', 0, 'success' );
		}
	}

}
