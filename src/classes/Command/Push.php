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

/**
 * The push command takes the current WP DB and wp-content folder and pushes them to
 * S3/DynamoDB as a snapshot.
 */
class Push extends Command {

	/**
	 * Setup up command
	 */
	protected function configure() {
		$this->setName( 'push' );
		$this->setDescription( 'Push a snapshot to a repository.' );
		$this->addOption( 'exclude-uploads', false, InputOption::VALUE_NONE, 'Exclude uploads from pushed snapshot.' );
		$this->addOption( 'no-scrub', false, InputOption::VALUE_NONE, "Don't scrub personal user data." );

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

		$verbose = $input->getOption( 'verbose' );

		$helper = $this->getHelper( 'question' );

		$path = Utils\normalize_path( $path );

		/**
		 * Always remove temp files first that could be left over
		 */
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

		if ( ! Utils\is_wp_present( $path ) ) {
			$output->writeln( '<error>This is not a WordPress install.</error>' );
			return;
		}

		if ( ! Utils\locate_wp_config( $path ) ) {
			$output->writeln( '<error>No wp-config.php file present.</error>' );
			return;
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

		if ( $verbose ) {
			$output->writeln( 'Bootstrapping WordPress...' );
		}

		$wp = WordPressBridge::instance()->load( $path, $extra_config_constants );

		if ( Utils\is_error( $wp ) ) {
			$output->writeln( '<error>Could not connect to WordPress database.</error>' );
			return;
		}

		global $wpdb;

		$snapshot = [
			'author' => [],
		];

		$config = Config::instance()->get();

		if ( ! empty( $config['name'] ) ) {
			$snapshot['author']['name'] = $config['name'];
		}

		if ( ! empty( $config['email'] ) ) {
			$snapshot['author']['email'] = $config['email'];
		}

		$project_question = new Question( 'Project Slug (letters, numbers, _, and - only): ' );
		$project_question->setValidator( '\WPSnapshots\Utils\slug_validator' );

		$snapshot['project'] = $helper->ask( $input, $output, $project_question );

		$description_question = new Question( 'Snapshot Description (e.g. Local environment): ' );
		$description_question->setValidator( '\WPSnapshots\Utils\not_empty_validator' );

		$snapshot['description'] = $helper->ask( $input, $output, $description_question );

		$snapshot['multisite'] = false;
		$snapshot['subdomain_install'] = false;
		$snapshot['sites'] = [];

		if ( is_multisite() ) {
			$snapshot['multisite'] = true;

			if ( defined( 'SUBDOMAIN_INSTALL' ) && SUBDOMAIN_INSTALL ) {
				$snapshot['subdomain_install'] = true;
			}

				$sites = get_sites( [ 'number' => 500 ] );

			foreach ( $sites as $site ) {
				$snapshot['sites'][] = [
					'blog_id'  => $site->blog_id,
					'domain'   => $site->domain,
					'path'     => $site->path,
					'site_url' => get_site_url( $site->blog_id ),
					'home_url' => get_home_url( $site->blog_id ),
				];
			}
		} else {
			$snapshot['sites'][] = [
				'site_url' => get_site_url(),
				'home_url' => get_home_url(),
			];
		}

		$snapshot['table_prefix'] = $GLOBALS['table_prefix'];

		$no_scrub = $input->getOption( 'no-scrub' );

		/**
		 * Dump sql to .wpsnapshots/data.sql
		 */
		$command = '/usr/bin/env mysqldump --no-defaults %s';
		$command_esc_args = array( DB_NAME );
		$command .= ' --tables';

		/**
		 * We only export tables with WP prefix
		 */
		if ( $verbose ) {
			$output->writeln( 'Getting WordPress tables...' );
		}

		$tables = Utils\get_tables();

		foreach ( $tables as $table ) {
			// We separate the users table for scrubbing
			if ( ! $no_scrub && $GLOBALS['table_prefix'] . 'users' === $table ) {
				continue;
			}

			$command .= ' %s';
			$command_esc_args[] = trim( $table );
		}

		$args = [
			'host'        => DB_HOST,
			'pass'        => DB_PASSWORD,
			'user'        => DB_USER,
			'result-file' => $temp_path . 'data.sql',
		];

		if ( defined( 'DB_CHARSET' ) && constant( 'DB_CHARSET' ) ) {
			$args['default-character-set'] = constant( 'DB_CHARSET' );
		}

		$escaped_command = call_user_func_array( '\WPSnapshots\Utils\esc_cmd', array_merge( array( $command ), $command_esc_args ) );

		$output->writeln( 'Exporting database...' );

		Utils\run_mysql_command( $escaped_command, $args );

		if ( ! $no_scrub ) {
			$command = '/usr/bin/env mysqldump --no-defaults %s';

			$command_esc_args = array( DB_NAME );

			$command .= ' --tables %s';
			$command_esc_args[] = $GLOBALS['table_prefix'] . 'users';

			$args = [
				'host'        => DB_HOST,
				'pass'        => DB_PASSWORD,
				'user'        => DB_USER,
				'result-file' => $temp_path . 'data-users.sql',
			];

			$escaped_command = call_user_func_array( '\WPSnapshots\Utils\esc_cmd', array_merge( array( $command ), $command_esc_args ) );

			if ( $verbose ) {
				$output->writeln( 'Exporting users...' );
			}

			Utils\run_mysql_command( $escaped_command, $args );

			$output->writeln( 'Scrubbing user data...' );

			$all_hashed_passwords = [];

			if ( $verbose ) {
				$output->writeln( 'Getting users...' );
			}

			$passwords = $wpdb->get_results( "SELECT user_pass FROM $wpdb->users", ARRAY_A );

			foreach ( $passwords as $password_row ) {
				$all_hashed_passwords[] = $password_row['user_pass'];
			}

			$sterile_password = wp_hash_password( 'password' );

			if ( $verbose ) {
				$output->writeln( 'Opening users export...' );
			}

			$users_handle = @fopen( $temp_path . 'data-users.sql', 'r' );
			$data_handle = @fopen( $temp_path . 'data.sql', 'a' );

			if ( ! $users_handle || ! $data_handle ) {
				$output->writeln( '<error>Could not scrub users.</error>' );
				return;
			}

			$buffer = '';
			$i = 0;

			if ( $verbose ) {
				$output->writeln( 'Writing scrubbed user data and merging exports...' );
			}

			while ( ! feof( $users_handle ) ) {
				$chunk = fread( $users_handle, 4096 );

				foreach ( $all_hashed_passwords as $password ) {
					$chunk = str_replace( "'$password'", "'$sterile_password'", $chunk );
				}

				$buffer .= $chunk;

				if ( $i % 10000 === 0 ) {
					fwrite( $data_handle, $buffer );
					$buffer = '';
				}

				$i++;
			}

			if ( ! empty( $buffer ) ) {
				fwrite( $data_handle, $buffer );
				$buffer = '';
			}

			fclose( $data_handle );
			fclose( $users_handle );

			if ( $verbose ) {
				$output->writeln( 'Removing old SQL...' );
			}

			unlink( $temp_path . 'data-users.sql' );
		}

		$verbose_pipe = ( $verbose ) ? '> /dev/null' : '';

		/**
		 * Create file back up of wp-content in .wpsnapshots/files.tar.gz
		 */

		$output->writeln( 'Saving file back up...' );

		$exclude_uploads = $input->getOption( 'exclude-uploads' );

		$excludes = '';

		if ( $exclude_uploads ) {
			$excludes .= ' --exclude="./uploads"';
		}

		if ( $verbose ) {
			$output->writeln( 'Compressing files...' );
		}

		exec( 'cd ' . escapeshellarg( WP_CONTENT_DIR ) . '/ && tar ' . $excludes . ' -zcf ' . $temp_path . 'files.tar.gz . ' . $verbose_pipe );

		/**
		 * Insert snapshot into DB
		 */
		$id = time();

		if ( ! empty( $snapshot['author']['name'] ) ) {
			$id .= '-' . $snapshot['author']['name'];
		}

		$id = md5( $id );

		$output->writeln( 'Uploading files and database to repository...' );

		/**
		 * Put files on S3
		 */
		$s3_add = Connection::instance()->s3->putSnapshot( $id, $snapshot['project'], $temp_path . 'data.sql', $temp_path . 'files.tar.gz' );

		if ( Utils\is_error( $s3_add ) ) {
			$output->writeln( '<error>Could not upload files to S3.</error>' );

			if ( 'AccessDenied' === $s3_add->data['aws_error_code'] ) {
				$output->writeln( '<error>Access denied. You might not have access to this project.</error>' );
			}

			if ( $verbose ) {
				$output->writeln( '<error>Error Message: ' . $s3_add->data['message'] . '</error>' );
				$output->writeln( '<error>AWS Request ID: ' . $s3_add->data['aws_request_id'] . '</error>' );
				$output->writeln( '<error>AWS Error Type: ' . $s3_add->data['aws_error_type'] . '</error>' );
				$output->writeln( '<error>AWS Error Code: ' . $s3_add->data['aws_error_code'] . '</error>' );
			}

			exit;
		}

		/**
		 * Add snapshot to DB
		 */
		$output->writeln( 'Adding snapshot to database...' );

		$inserted_snapshot = Connection::instance()->db->insertSnapshot( $id, $snapshot, $temp_path . 'data.sql' );

		if ( Utils\is_error( $inserted_snapshot ) ) {
			if ( 'AccessDeniedException' === $inserted_snapshot->data['aws_error_code'] ) {
				$output->writeln( '<error>Access denied. You might not have access to this project.</error>' );
			}

			$output->writeln( '<error>Could not add snapshot to database.</error>' );

			if ( $verbose ) {
				$output->writeln( '<error>Error Message: ' . $inserted_snapshot->data['message'] . '</error>' );
				$output->writeln( '<error>AWS Request ID: ' . $inserted_snapshot->data['aws_request_id'] . '</error>' );
				$output->writeln( '<error>AWS Error Type: ' . $inserted_snapshot->data['aws_error_type'] . '</error>' );
				$output->writeln( '<error>AWS Error Code: ' . $inserted_snapshot->data['aws_error_code'] . '</error>' );
			}

			exit;
		}

		$output->writeln( 'Cleaning up temp files...' );

		Utils\remove_temp_folder( $path );

		$output->writeln( '<info>Push finished! Snapshot ID is ' . $id . '</info>' );
	}

}
