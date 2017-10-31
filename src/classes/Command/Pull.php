<?php

namespace WPSnapshots\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use WPSnapshots\Connection;
use WPSnapshots\WordPressBridge;
use WPSnapshots\Config;
use WPSnapshots\Utils;
use WPSnapshots\SearchReplace;
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
		$this->setDescription( 'Pull a snapshot from a repository.' );
		$this->addArgument( 'instance-id', InputArgument::REQUIRED, 'Snapshot ID to pull.' );
		$this->addOption( 'confirm', null, InputOption::VALUE_NONE, 'Confirm pull operation.' );

		$this->addOption( 'path', null, InputOption::VALUE_REQUIRED, 'Path to WordPress files.' );
		$this->addOption( 'db_host', null, InputOption::VALUE_REQUIRED, 'Database host.' );
		$this->addOption( 'db_name', null, InputOption::VALUE_REQUIRED, 'Database name.' );
		$this->addOption( 'db_user', null, InputOption::VALUE_REQUIRED, 'Database user.' );
		$this->addOption( 'db_password', null, InputOption::VALUE_REQUIRED, 'Database password.' );
	}

	/**
	 * Executes the command
	 *
	 * @param  InputInterface  $input
	 * @param  OutputInterface $output
	 */
	protected function execute( InputInterface $input, OutputInterface $output ) {
		$connection = Connection::instance()->connect();

		if ( Utils\is_error( $connection ) ) {
			$output->writeln( '<error>Could not connect to repository.</error>' );
			return;
		}

		$path = $input->getOption( 'path' );

		if ( empty( $path ) ) {
			$path = getcwd();
		}

		$path = Utils\normalize_path( $path );

		$remove_temp = Utils\remove_temp_folder( $path );

		if ( Utils\is_error( $remove_temp ) ) {
			$output->writeln( '<error>Failed to clean up old WP Snapshots temp files.</error>' );
			return;
		}

		$temp_path = $path . '.wpsnapshots/';

		$dir_result = mkdir( $temp_path, 0755 );

		if ( ! $dir_result ) {
			$output->writeln( '<error>Cannot write to current directory.</error>' );
			return;
		}

		$verbose = $input->getOption( 'verbose' );

		$verbose_pipe = ( $verbose ) ? '> /dev/null' : '';

		$helper = $this->getHelper( 'question' );

		if ( ! Utils\is_wp_present( $path ) ) {
			$output->writeln( '<error>This is not a WordPress install. WordPress needs to be present in order to pull an instance.</error>' );

			$download_wp = $helper->ask( $input, $output, new ConfirmationQuestion( 'Do you want to download WordPress? (yes|no) ', false ) );

			if ( ! $download_wp ) {
				return;
			}

			/**
			 * Download WordPress core files
			 */

			if ( $verbose ) {
				$output->writeln( 'Getting WordPress download URL...' );
			}

			$download_url = Utils\get_download_url();

			$headers = [ 'Accept' => 'application/json' ];
			$options = [
				'timeout' => 600,
				'filename' => $temp_path . 'wp.tar.gz',
			];

			if ( $verbose ) {
				$output->writeln( 'Downloading WordPress...' );
			}

			$request = Requests::get( $download_url, $headers, $options );

			if ( $verbose ) {
				$output->writeln( 'Extracting WordPress...' );
			}

			exec( 'tar -C ' . $path . ' -xf ' . $temp_path . 'wp.tar.gz ' . $verbose_pipe );

			if ( $verbose ) {
				$output->writeln( 'Moving WordPress files...' );
			}

			exec( 'mv ' . $path . 'wordpress/* .' );

			if ( $verbose ) {
				$output->writeln( 'Removing temporary WordPress files...' );
			}

			exec( 'rmdir ' . $path . 'wordpress' );

			$output->writeln( 'WordPress downloaded.' );
		}

		if ( ! Utils\locate_wp_config( $path ) ) {
			$output->writeln( '<error>No wp-config.php file present. wp-config.php needs to be setup in order to pull an instance.</error>' );

			$create_config = $helper->ask( $input, $output, new ConfirmationQuestion( 'Do you want to create a wp-config.php file? (yes|no) ', false ) );

			if ( ! $create_config ) {
				return;
			}

			$config_constants = [];

			$db_host_question = new Question( 'What is your database host? ' );
			$db_host_question->setValidator( '\WPSnapshots\Utils\not_empty_validator' );

			$config_constants['DB_HOST'] = $helper->ask( $input, $output, $db_host_question );

			$db_name_question = new Question( 'What is your database name? ' );
			$db_name_question->setValidator( '\WPSnapshots\Utils\not_empty_validator' );

			$config_constants['DB_NAME'] = $helper->ask( $input, $output, $db_name_question );

			$db_user_question = new Question( 'What is your database user? ' );
			$db_user_question->setValidator( '\WPSnapshots\Utils\not_empty_validator' );

			$config_constants['DB_USER'] = $helper->ask( $input, $output, $db_user_question );

			$db_password_question = new Question( 'What is your database password? ' );
			$db_password_question->setValidator( '\WPSnapshots\Utils\not_empty_validator' );

			$config_constants['DB_PASSWORD'] = $helper->ask( $input, $output, $db_password_question );

			if ( $verbose ) {
				$output->writeln( 'Creating wp-config.php file...' );
			}

			Utils\create_config_file( $path . 'wp-config.php', $path . 'wp-config-sample.php', $config_constants );

			$output->writeln( 'wp-config.php created.' );
		}

		$extra_config_constants = [];

		$db_host = $input->getOption( 'db_host' );
		$db_name = $input->getOption( 'db_name' );
		$db_user = $input->getOption( 'db_user' );
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

		if ( $verbose ) {
			$output->writeln( 'Bootstrapping WordPress...' );
		}

		$wp = WordPressBridge::instance()->load( $path, $extra_config_constants );

		if ( Utils\is_error( $wp ) ) {
			$output->writeln( '<error>Could not connect to WordPress database.</error>' );
			return;
		}

		$pre_update_site_url = site_url();
		$pre_update_home_url = home_url();

		$pre_update_site_url_parsed = parse_url( $pre_update_site_url );
		$use_https = false;

		if ( 'https' === $pre_update_site_url_parsed['scheme'] ) {
			$use_https = true;
		}

		/**
		 * We make the user double confirm since this could destroy a website
		 */
		$confirm = $input->getOption( 'confirm' );

		if ( empty( $confirm ) ) {

			$confirm = $helper->ask( $input, $output, new ConfirmationQuestion( 'Are you sure you want to do this? This is a potentially destructive operation. You should run a back up first. (yes|no) ', false ) );

			if ( ! $confirm ) {
				return;
			}
		}

		$id = $input->getArgument( 'instance-id' );

		$output->writeln( 'Downloading snapshot files and database...' );

		$download = Connection::instance()->s3->downloadSnapshot( $id, $temp_path . 'data.sql', $temp_path . 'files.tar.gz' );

		if ( Utils\is_error( $download ) ) {
			$output->writeln( '<error>Failed to pull snapshot.</error>' );
			return;
		}

		if ( $verbose ) {
			$output->writeln( 'Getting snapshot data...' );
		}

		$snapshot = Connection::instance()->db->getSnapshot( $id );

		if ( Utils\is_error( $snapshot ) ) {
			$output->writeln( '<error>Failed to get snapshot.</error>' );
			return;
		}

		$output->writeln( 'Replacing wp-content/...' );

		if ( $verbose ) {
			$output->writeln( 'Removing old wp-content/...' );
		}

		exec( 'rm -rf ' . $path . 'wp-content/..?* ' . $path . 'wp-content/.[!.]* ' . $path . 'wp-content/*' );

		if ( $verbose ) {
			$output->writeln( 'Extracting snapshot wp-content/...' );
		}

		exec( 'tar -C ' . $path . 'wp-content' . ' -xf ' . $temp_path . 'files.tar.gz ' . $verbose_pipe );

		/**
		 * Import tables
		 */

		$args = array(
			'host' => DB_HOST,
			'user' => DB_USER,
			'pass' => DB_PASSWORD,
			'database' => DB_NAME,
			'execute' => 'SET GLOBAL max_allowed_packet=51200000;',
		);

		if ( $verbose ) {
			$output->writeln( 'Attempting to set max_allowed_packet...' );
		}

		$command_result  = Utils\run_mysql_command( 'mysql --no-defaults --no-auto-rehash', $args, '', false );

		if ( 0 !== $command_result ) {
			$output->writeln( '<comment>Could not set MySQL max_allowed_packet. If MySQL import fails, try running WP Snapshots using root DB user.</comment>' );
		}

		$output->writeln( 'Updating database... This may take awhile depending on the size of the database.' );
		$query = 'SET autocommit = 0; SET unique_checks = 0; SET foreign_key_checks = 0; SOURCE %s; COMMIT;';

		$args = array(
			'host' => DB_HOST,
			'user' => DB_USER,
			'pass' => DB_PASSWORD,
			'database' => DB_NAME,
			'execute' => sprintf( $query, $temp_path . 'data.sql' ),
		);

		if ( ! isset( $assoc_args['default-character-set'] ) && defined( 'DB_CHARSET' ) && constant( 'DB_CHARSET' ) ) {
			$args['default-character-set'] = constant( 'DB_CHARSET' );
		}

		Utils\run_mysql_command( 'mysql --no-defaults --no-auto-rehash', $args );

		/**
		 * Customize DB for current install
		 */

		if ( $verbose ) {
			$output->writeln( 'Getting MySQL tables...' );
		}
		$all_tables = Utils\get_tables( false );

		global $wpdb;

		/**
		 * First update table prefixes
		 */
		if ( ! empty( $snapshot['table_prefix'] ) && ! empty( $GLOBALS['table_prefix'] ) && $snapshot['table_prefix'] !== $GLOBALS['table_prefix'] ) {
			$output->writeln( 'Renaming WordPress tables...' );

			foreach ( $all_tables as $table ) {
				if ( 0 === strpos( $table, $snapshot['table_prefix'] ) ) {
					/**
					 * Update this table to use the current config prefix
					 */
					$new_table = $GLOBALS['table_prefix'] . str_replace( $snapshot['table_prefix'], '', $table );
					$wpdb->query( $wpdb->prepare( 'RENAME TABLE `%s` TO `%s`', esc_sql( $table ), esc_sql( $new_table ) ) );
				}
			}
		}

		/**
		 * Get tables again since it could have changed
		 */
		$wp_tables = Utils\get_tables();

		/**
		 * Handle url replacements
		 */
		if ( ! empty( $snapshot['sites'] ) ) {
			$output->writeln( 'Preparing to replace URLs...' );

			$url_validator = function( $answer ) {
				if ( '' === trim( $answer ) || false !== strpos( $answer, ' ' ) || ! preg_match( '#https?:#i', $answer ) ) {
					throw new \RuntimeException(
						'URL is not valid. Should be prefixed with http and contain no spaces.'
					);
				}

				return $answer;
			};

			if ( ! empty( $snapshot['multisite'] ) ) {
				if ( empty( $snapshot['subdomain_install'] ) ) {
					$output->writeln( 'Multisite installation (path based install) detected. Paths will be maintained.' );
				} else {
					$output->writeln( 'Multisite installation (subdomain based install) detected.' );
				}

				$i = 0;

				/**
				 * First handle multisite intricacies. We need to set domains and paths for each blog. We'll copy
				 * whatever the current path is. However, the user needs to set the domain for every blog. For
				 * path based installed we just use the first domain.
				 */

				$main_domain = '';

				$current_main_domain = ( defined( 'DOMAIN_CURRENT_SITE' ) && ! empty( DOMAIN_CURRENT_SITE ) ) ? DOMAIN_CURRENT_SITE : '';

				if ( ! empty( $current_main_domain ) ) {
					$main_domain_question = new Question( 'Domain (your install\'s current site domain is ' . $current_main_domain . '): ', $current_main_domain );
				} else {
					$main_domain_question = new Question( 'Domain (localhost.dev for example): ', $current_main_domain );
				}

				$main_domain_question->setValidator( function( $answer ) {
					if ( '' === trim( $answer ) || false !== strpos( $answer, ' ' ) || preg_match( '#https?:#i', $answer ) ) {
						throw new \RuntimeException(
							'Domain not valid. The domain should be in the form of `google.com`, no https:// needed'
						);
					}

					return $answer;
				} );

				if ( empty( $snapshot['subdomain_install'] ) ) {
					$main_domain = $helper->ask( $input, $output, $main_domain_question );
				}

				foreach ( $snapshot['sites'] as $site ) {

					$output->writeln( 'Replacing URLs for blog ' . $site['blog_id'] . '. Path for blog is ' . $site['path'] . '.' );

					if ( ! empty( $snapshot['subdomain_install'] ) ) {
						$domain = $helper->ask( $input, $output, $main_domain_question );

						if ( 0 === $i ) {
							$main_domain = $domain;
						}
					} else {
						$domain = $main_domain;
					}

					$suggested_url = ( ( $use_https ) ? 'https://' : 'http://' ) . $domain . $site['path'];

					$home_question = new Question( 'Home URL (' . $suggested_url . ' might make sense): ', $suggested_url );
					$home_question->setValidator( $url_validator );

					$new_home_url = $helper->ask( $input, $output, $home_question );

					$site_question = new Question( 'Site URL (' . $suggested_url . ' might make sense): ', $suggested_url );
					$site_question->setValidator( $url_validator );

					$new_site_url = $helper->ask( $input, $output, $site_question );

					if ( $verbose ) {
						$output->writeln( 'Updating blogs table...' );
					}

					/**
					 * Update multisite stuff for each blog
					 */
					$wpdb->query( $wpdb->prepare( 'UPDATE ' . $GLOBALS['table_prefix'] . "blogs SET path='%s', domain='%s' WHERE blog_id='%d'", esc_sql( $site['path'] ), esc_sql( $domain ), (int) $site['blog_id'] ) );

					/**
					 * Update all tables except wp_site and wp_blog since we handled that above
					 */
					$blacklist_tables = [ 'site', 'blogs' ];
					$tables_to_update = [];

					foreach ( $wp_tables as $table ) {
						if ( 1 === (int) $site['blog_id'] ) {
							if ( preg_match( '#^' . $GLOBALS['table_prefix'] . '#', $table ) && ! preg_match( '#^' . $GLOBALS['table_prefix'] . '[0-9]+_#', $table ) ) {
								if ( ! in_array( str_replace( $GLOBALS['table_prefix'], '', $table ), $blacklist_tables ) ) {
									$tables_to_update[] = $table;
								}
							}
						} else {
							if ( preg_match( '#^' . $GLOBALS['table_prefix'] . $site['blog_id'] . '_#', $table ) ) {
								$raw_table = str_replace( $GLOBALS['table_prefix'] . $site['blog_id'] . '_', '', $table );

								if ( ! in_array( $raw_table, $blacklist_tables ) ) {
									$tables_to_update[] = $table;
								}
							}
						}
					}

					if ( ! empty( $tables_to_update ) ) {
						$output->writeln( 'Running replacement... This may take awhile depending on the size of the database.' );

						new SearchReplace( $site['home_url'], $new_home_url, $tables_to_update );

						if ( $site['home_url'] !== $site['site_url'] ) {
							new SearchReplace( $site['site_url'], $new_site_url, $tables_to_update );
						}
					}

					$i++;
				}

				if ( $verbose ) {
					$output->writeln( 'Updating site table...' );
				}

				/**
				 * Update site domain with main domain
				 */
				$wpdb->query( $wpdb->prepare( 'UPDATE ' . $GLOBALS['table_prefix'] . "site SET domain='%s'", esc_sql( $main_domain ) ) );

				if ( ! defined( 'BLOG_ID_CURRENT_SITE' ) || ! defined( 'SITE_ID_CURRENT_SITE' ) || ! defined( 'PATH_CURRENT_SITE' ) || ! defined( 'MULTISITE' ) || ! MULTISITE || ! defined( 'DOMAIN_CURRENT_SITE' ) || $main_domain !== DOMAIN_CURRENT_SITE || ! defined( 'SUBDOMAIN_INSTALL' ) || $snapshot['subdomain_install'] !== SUBDOMAIN_INSTALL ) {

					$output->writeln( '<comment>URLs replaced. Since you are running multisite, the following code should be in your wp-config.php file:</comment>' );
					$output->writeln( "define('WP_ALLOW_MULTISITE', true);
define('MULTISITE', true);
define('SUBDOMAIN_INSTALL', false);
define('DOMAIN_CURRENT_SITE', '" . $main_domain . "');
define('PATH_CURRENT_SITE', '/');
define('SITE_ID_CURRENT_SITE', 1);
define('BLOG_ID_CURRENT_SITE', 1);");
				} else {
					$output->writeln( 'URLs replaced.' );
				}
			} else {
				$home_question = new Question( 'Home URL (' . $pre_update_home_url . ' is recommended): ', $pre_update_home_url );
				$home_question->setValidator( $url_validator );

				$new_home_url = $helper->ask( $input, $output, $home_question );

				$site_question = new Question( 'Site URL (' . $pre_update_site_url . ' is recommended): ', $pre_update_site_url );
				$site_question->setValidator( $url_validator );

				$new_site_url = $helper->ask( $input, $output, $site_question );

				$output->writeln( 'Running replacement... This may take awhile depending on the size of the database.' );

				new SearchReplace( $snapshot['sites'][0]['home_url'], $new_home_url );
				new SearchReplace( $snapshot['sites'][0]['site_url'], $new_site_url );
			}
		}

		if ( $verbose ) {
			$output->writeln( 'Removing temp folder...' );
		}

		Utils\remove_temp_folder( $path );

		$output->writeln( '<info>Pull finished.</info>' );
	}

}
