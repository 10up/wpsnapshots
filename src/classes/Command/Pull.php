<?php

namespace WPProjects\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use WPProjects\ConnectionManager;
use WPProjects\WordPressConfig;
use WPProjects\ProjectConfig;
use WPProjects\Utils;
use WPProjects\SearchReplace;

/**
 * The pull command grabs a project instance and pulls it down overwriting your wp-content
 * folder and current DB.
 */
class Pull extends Command {

	/**
	 * Setup up command
	 */
	protected function configure() {
		$this->setName( 'pull' );
		$this->setDescription( 'Pull a project instance from a repository' );
		$this->addArgument( 'instance-id', InputArgument::REQUIRED, 'Project instance ID to pull.' );

		$this->addOption( 'confirm', null, InputOption::VALUE_NONE, 'Confirm pull operation.' );
	}

	/**
	 * Executes the command
	 *
	 * @param  InputInterface  $input
	 * @param  OutputInterface $output
	 */
	protected function execute( InputInterface $input, OutputInterface $output ) {
		$connection = ConnectionManager::instance()->connect();

		if ( Utils\is_error( $connection ) ) {
			$output->writeln( '<error>Could not connect to repository.</error>' );
			return;
		}

		if ( ! Utils\locate_wp_config() ) {
			$output->writeln( '<error>This is not a WordPress install.</error>' );

			/**
			 * Todo: Prompt to install WordPress. Prompt for DB creds to create wp-config.php
			 */
			return;
		}

		$wp = WordPressConfig::instance()->load();

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

		$remove_temp = Utils\remove_temp_folder();

		if ( Utils\is_error( $remove_temp ) ) {
			$output->writeln( '<error>Failed to clean up old WPProject temp files.</error>' );
			return;
		}

		$helper = $this->getHelper( 'question' );

		/**
		 * We make the user double confirm since this could destroy a website
		 */
		$confirm = $input->getOption( 'confirm' );

		if ( empty( $confirm ) ) {

			$confirm = $helper->ask( $input, $output, new ConfirmationQuestion( 'Are you sure you want to do this? This is a potentially destructive operation. (yes|no) ', false ) );

			if ( ! $confirm ) {
				return;
			}
		}

		$temp_path = getcwd() . '/.wpprojects';

		$dir_result = mkdir( $temp_path, 0755 );

		if ( ! $dir_result ) {
			$output->writeln( '<error>Cannot write to current directory.</error>' );
			return;
		}

		$id = $input->getArgument( 'instance-id' );

		$output->writeln( 'Downloading project instance files and database...' );

		$download = ConnectionManager::instance()->s3->downloadProjectInstance( $id, $temp_path . '/data.sql', $temp_path . '/files.tar.gz' );

		if ( Utils\is_error( $download ) ) {
			$output->writeln( '<error>Failed to pull project instance.</error>' );
			return;
		}

		$project_instance = ConnectionManager::instance()->db->getProjectInstance( $id );

		if ( Utils\is_error( $project_instance ) ) {
			$output->writeln( '<error>Failed to get project instance.</error>' );
			return;
		}

		$output->writeln( 'Replacing wp-content/...' );

		//exec( 'rm -rf ' . getcwd() . '/wp-content/..?* ' . getcwd() . '/wp-content/.[!.]* ' . getcwd() . '/wp-content/* && tar -C ' . getcwd() . '/wp-content' . ' -xvf ' . $temp_path . '/files.tar.gz' );

		/**
		 * Import tables
		 */

		$output->writeln( 'Updating database...' );
		$query = 'SET autocommit = 0; SET unique_checks = 0; SET foreign_key_checks = 0; SOURCE %s; COMMIT;';

		$args = array(
			'host' => DB_HOST,
			'user' => DB_USER,
			'pass' => DB_PASSWORD,
			'database' => DB_NAME,
			'execute' => sprintf( $query, $temp_path . '/data.sql' ),
		);

		if ( ! isset( $assoc_args['default-character-set'] ) && defined( 'DB_CHARSET' ) && constant( 'DB_CHARSET' ) ) {
			$args['default-character-set'] = constant( 'DB_CHARSET' );
		}

		Utils\run_mysql_command( 'mysql --no-defaults --no-auto-rehash', $args );

		/**
		 * Customize DB for current install
		 */

		$all_tables = Utils\get_tables( false );

		global $wpdb;

		/**
		 * First update table prefixes
		 */
		if ( ! empty( $project_instance['table_prefix'] ) && ! empty( $GLOBALS['table_prefix'] ) && $project_instance['table_prefix'] !== $GLOBALS['table_prefix'] ) {
			$output->writeln( 'Renaming WordPress tables...' );

			foreach ( $all_tables as $table ) {
				if ( 0 === strpos( $table, $project_instance['table_prefix'] ) ) {
					/**
					 * Update this table to use the current config prefix
					 */
					$new_table = $GLOBALS['table_prefix'] . str_replace( $project_instance['table_prefix'], '', $table );
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
		if ( ! empty( $project_instance['sites'] ) ) {
			$output->writeln( 'Replacing URLs...' );

			$url_validator = function( $answer ) {
				if ( '' === trim( $answer ) || false !== strpos( $answer, ' ' ) || ! preg_match( '#https?:#i', $answer ) ) {
					throw new \RuntimeException(
						'URL is not valid. Should be prefixed with http and contain no spaces.'
					);
				}

				return $answer;
			};

			if ( ! empty( $project_instance['multisite'] ) ) {
				if ( empty( $project_instance['subdomain_install'] ) ) {
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

				if ( empty( $project_instance['subdomain_install'] ) ) {
					$main_domain = $helper->ask( $input, $output, $main_domain_question );
				}

				foreach ( $project_instance['sites'] as $site ) {

					$output->writeln( 'Replacing URLs for blog ' . $site['blog_id'] . '. Path for blog is ' . $site['path'] . '.' );

					if ( ! empty( $project_instance['subdomain_install'] ) ) {
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

					$new_site_url = $helper->ask( $input, $output, $site_question);

					switch_to_blog( $site['blog_id'] );

					$blog_data = [
						'path'   => $site['path'],
						'domain' => $domain,
					];

					/**
					 * Update multisite stuff for each blog
					 */
					update_blog_details( $site['blog_id'], $blog_data );

					restore_current_blog();

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
						new SearchReplace( $site['home_url'], $new_home_url, $tables_to_update );

						if ( $site['home_url'] !== $site['site_url'] ) {
							new SearchReplace( $site['site_url'], $new_site_url, $tables_to_update );
						}
					}

					$i++;
				}

				/**
				 * Update site domain with main domain
				 */
				$wpdb->query( $wpdb->prepare( "UPDATE " . $GLOBALS['table_prefix'] . "site SET domain='%s'", esc_sql( $main_domain ) ) );

				if ( ! defined( 'BLOG_ID_CURRENT_SITE' ) || ! defined( 'SITE_ID_CURRENT_SITE' ) || ! defined( 'PATH_CURRENT_SITE' ) || ! defined( 'MULTISITE' ) || ! MULTISITE || ! defined( 'DOMAIN_CURRENT_SITE' ) || $main_domain !== DOMAIN_CURRENT_SITE || ! defined( 'SUBDOMAIN_INSTALL' ) || $project_instance['subdomain_install'] !== SUBDOMAIN_INSTALL ) {

					$output->writeln( '<comment>URLs replaced. Since you are running multisite, the following code should be in your wp-config.php file:</comment>' );
					$output->writeln( "define('WP_ALLOW_MULTISITE', true);
define('MULTISITE', true);
define('SUBDOMAIN_INSTALL', false);
define('DOMAIN_CURRENT_SITE', 'localhost');
define('PATH_CURRENT_SITE', '/');
define('SITE_ID_CURRENT_SITE', 1);
define('BLOG_ID_CURRENT_SITE', 1);");
				} else {
					$output->writeln( 'URLs replaced.' );
				}
			} else {
				$home_question = new Question( 'Home URL (' . $pre_update_home_url .' is recommended): ', $pre_update_home_url );
				$home_question->setValidator( $url_validator );

				$new_home_url = $helper->ask( $input, $output, $home_question );

				$site_question = new Question( 'Site URL (' . $pre_update_site_url .' is recommended): ', $pre_update_site_url );
				$site_question->setValidator( $url_validator );

				$new_site_url = $helper->ask( $input, $output, $site_question );

				new SearchReplace( $project_instance['sites'][0]['home_url'], $new_home_url );
				new SearchReplace( $project_instance['sites'][0]['site_url'], $new_site_url );
			}
		}

		Utils\remove_temp_folder();

		$output->writeln( '<info>Pull finished</info>' );
	}

}
