<?php

namespace WPProjects\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Question\Question;
use WPProjects\ConnectionManager;
use WPProjects\WordPressConfig;
use WPProjects\ProjectConfig;
use WPProjects\Utils;

/**
 * The push command takes the current WP DB and wp-content folder and pushes them to
 * S3.
 */
class Push extends Command {

	/**
	 * Setup up command
	 */
	protected function configure() {
		$this->setName( 'push' );
		$this->setDescription( 'Push a project instance to a repository' );
		$this->addOption( 'no-uploads', false, InputOption::VALUE_NONE, 'Exclude uploads from pushed project instance.' );
		$this->addOption( 'no-scrub', false, InputOption::VALUE_NONE, "Don't scrub personal user data." );
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
			return;
		}

		$wp = WordPressConfig::instance()->load();

		if ( Utils\is_error( $wp ) ) {
			$output->writeln( '<error>Could not connect to WordPress database.</error>' );
			return;
		}

		global $wpdb;

		/**
		 * Always remove temp files first that could be left over
		 */
		$remove_temp = Utils\remove_temp_folder();

		if ( Utils\is_error( $remove_temp ) ) {
			$output->writeln( '<error>Failed to clean up old WPProject temp files.</error>' );
			return;
		}

		$temp_path = getcwd() . '/.wpprojects';

		$dir_result = mkdir( $temp_path, 0755 );

		if ( ! $dir_result ) {
			$output->writeln( '<error>Cannot write to current directory.</error>' );
			return;
		}

		$project_config = ProjectConfig::instance()->get();

		if ( empty( $project_config ) ) {
			$project_config = [];
		}

		$helper = $this->getHelper( 'question' );

		/**
		 * Create wpproject.json if it doesn't exist
		 */

		if ( empty( $project_config ) ) {

			$not_empty_validator = function( $answer ) {
				if ( '' === trim( $answer ) ) {
					throw new \RuntimeException(
						'A valid answer is required.'
					);
				}

				return $answer;
			};

			$project_question = new Question( 'Project slug: ' );
			$project_question->setValidator( $not_empty_validator );

			$project_config['project'] = $helper->ask( $input, $output, $project_question );

			$project_config['author'] = [];

			$project_config['author']['name'] = $helper->ask( $input, $output, new Question( 'Your name: ', '' ) );

			$project_config['author']['email'] = $helper->ask( $input, $output, new Question( 'Your email: ', '' ) );

			$environment_question = new Question( 'What type of environment is this? (local, staging, production) ' );
			$environment_question->setValidator( $not_empty_validator );

			$project_config['environment'] = $helper->ask( $input, $output, $environment_question );

			ProjectConfig::instance()->write( $project_config );
		}

		$project_config['multisite'] = false;
		$project_config['subdomain_install'] = false;
		$project_config['sites'] = [];

		if ( is_multisite() ) {
			$project_config['multisite'] = true;

			if ( defined( 'SUBDOMAIN_INSTALL' ) && SUBDOMAIN_INSTALL ) {
				$project_config['subdomain_install'] = true;
 			}

 			$sites = get_sites( [ 'number' => 500, ] );

 			foreach ( $sites as $site ) {
 				$project_config['sites'][] = [
 					'blog_id'  => $site->blog_id,
 					'domain'   => $site->domain,
 					'path'     => $site->path,
 					'site_url' => get_site_url( $site->blog_id ),
 					'home_url' => get_home_url( $site->blog_id ),
 				];
 			}
		} else {
			$project_config['sites'][] = [
				'site_url' => get_site_url(),
				'home_url' => get_home_url(),
			];
		}

		$project_config['table_prefix'] = $GLOBALS['table_prefix'];

		/**
		 * Dump sql to .wpprojects/data.sql
		 */
		$command = '/usr/bin/env mysqldump --no-defaults %s';
		$command_esc_args = array( DB_NAME );
		$command .= ' --tables';

		/**
		 * We only export tables with WP prefix
		 */
		$tables = Utils\get_tables();

		foreach ( $tables as $table ) {
			$command .= ' %s';
			$command_esc_args[] = trim( $table );
		}

		$args = [
			'host'        => DB_HOST,
			'pass'        => DB_PASSWORD,
			'user'        => DB_USER,
			'result-file' => $temp_path . '/data.sql',
		];

		if ( defined( 'DB_CHARSET' ) && constant( 'DB_CHARSET' ) ) {
			$args['default-character-set'] = constant( 'DB_CHARSET' );
		}

		$escaped_command = call_user_func_array( '\WPProjects\Utils\esc_cmd', array_merge( array( $command ), $command_esc_args ) );

		$output->writeln( 'Exporting database...' );

		Utils\run_mysql_command( $escaped_command, $args );

		$no_scrub = $input->getOption( 'no-scrub' );

		if ( ! $no_scrub ) {
			$output->writeln( 'Scrubbing personal data...' );

			$all_hashed_passwords = [];

			$passwords = $wpdb->get_results( "SELECT user_pass FROM $wpdb->users", ARRAY_A );

			foreach ( $passwords as $password_row ) {
				$all_hashed_passwords[] = $password_row['user_pass'];
			}

			$sterile_password = wp_hash_password( 'password' );

			$dump_sql = file_get_contents( $temp_path . '/data.sql' );

			foreach ( $all_hashed_passwords as $password ) {
				$dump_sql = str_replace( "'$password'", "'$sterile_password'", $dump_sql );
			}

			file_put_contents( $temp_path . '/data.sql', $dump_sql );
		}

		/**
		 * Create file back up of wp-content in .wpprojects/files.tar.gz
		 */

		$output->writeln( 'Saving file back up...' );

		$no_uploads = $input->getOption( 'no-uploads' );

		$maybe_uploads = '';
		if ( $no_uploads ) {
			$maybe_uploads = ' --exclude="uploads"';
		}

		exec( 'cd ' . escapeshellarg( WP_CONTENT_DIR ) . '/ && tar -zcvf ../.wpprojects/files.tar.gz . ' . $maybe_uploads );

		$output->writeln( 'Adding project instance to database...' );

		/**
		 * Insert instance of project in DB
		 */
		$project_instance = ConnectionManager::instance()->db->insertProjectInstance( $project_config, $temp_path . '/data.sql' );

		if ( Utils\is_error( $project_instance ) ) {
			$output->writeln( '<error>Could not add project instance to database.</error>' );
			exit;
		}

		$output->writeln( 'Upload files and database to repository...' );

		/**
		 * Put files on S3
		 */
		$s3_add = ConnectionManager::instance()->s3->putProjectInstance( $project_instance, $temp_path . '/data.sql', $temp_path . '/files.tar.gz' );

		if ( Utils\is_error( $s3_add ) ) {
			$output->writeln( '<error>Could not upload files to S3.</error>' );
			exit;
		}

		$output->writeln( 'Cleaning up temp files...' );

		//Utils\remove_temp_folder();

		$output->writeln( '<info>Push finished! Project instance id is ' . $project_instance['id'] . '</info>' );
	}

}
