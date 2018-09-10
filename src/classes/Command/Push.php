<?php
/**
 * Push command
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
use WPSnapshots\Connection;
use WPSnapshots\WordPressBridge;
use WPSnapshots\Config;
use WPSnapshots\Utils;
use WPSnapshots\Snapshot;
use WPSnapshots\Log;

/**
 * The push command first runs "create" to create the snapshot, then pushes it to a remote repository.
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
	 * @param  InputInterface  $input Command input
	 * @param  OutputInterface $output Command output
	 */
	protected function execute( InputInterface $input, OutputInterface $output ) {
		Log::instance()->setOutput( $output );

		$connection = Connection::instance()->connect();

		if ( Utils\is_error( $connection ) ) {
			Log::instance()->write( 'Could not connect to repository.', 0, 'error' );
			return;
		}

		$path = $input->getOption( 'path' );

		if ( empty( $path ) ) {
			$path = getcwd();
		}

		$path = Utils\normalize_path( $path );

		$helper = $this->getHelper( 'question' );

		$verbose = $input->getOption( 'verbose' );

		$project_question = new Question( 'Project Slug (letters, numbers, _, and - only): ' );
		$project_question->setValidator( '\WPSnapshots\Utils\slug_validator' );

		$project = $helper->ask( $input, $output, $project_question );

		$description_question = new Question( 'Snapshot Description (e.g. Local environment): ' );
		$description_question->setValidator( '\WPSnapshots\Utils\not_empty_validator' );

		$description = $helper->ask( $input, $output, $description_question );

		$snapshot = Snapshot::create(
			[
				'path'            => $path,
				'db_host'         => $input->getOption( 'db_host' ),
				'db_name'         => $input->getOption( 'db_name' ),
				'db_user'         => $input->getOption( 'db_user' ),
				'db_password'     => $input->getOption( 'db_password' ),
				'project'         => $project,
				'description'     => $description,
				'no_scrub'        => $input->getOption( 'no-scrub' ),
				'exclude_uploads' => $input->getOption( 'exclude-uploads' ),
			], $output, $verbose
		);

		if ( ! is_a( $snapshot, '\WPSnapshots\Snapshot' ) ) {
			return;
		}

		/**
		 * Put files on S3
		 */
		Log::instance()->write( 'Uploading files...' );

		$s3_add = Connection::instance()->s3->putSnapshot( $snapshot->id, $snapshot->meta['project'], Utils\get_snapshot_directory() . $snapshot->id . '/data.sql.gz', Utils\get_snapshot_directory() . $snapshot->id . '/files.tar.gz' );

		if ( Utils\is_error( $s3_add ) ) {
			Log::instance()->write( 'Could not upload files to S3.', 0, 'error' );

			if ( is_array( $s3_add->data ) ) {
				if ( 'AccessDenied' === $s3_add->data['aws_error_code'] ) {
					Log::instance()->write( 'Access denied. You might not have access to this project.', 0, 'error' );
				}

				Log::instance()->write( 'Error Message: ' . $s3_add->data['message'], 1, 'error' );
				Log::instance()->write( 'AWS Request ID: ' . $s3_add->data['aws_request_id'], 1, 'error' );
				Log::instance()->write( 'AWS Error Type: ' . $s3_add->data['aws_error_type'], 1, 'error' );
				Log::instance()->write( 'AWS Error Code: ' . $s3_add->data['aws_error_code'], 1, 'error' );
			}

			return;
		}

		/**
		 * Add snapshot to DB
		 */
		Log::instance()->write( 'Adding snapshot to database...' );

		$inserted_snapshot = Connection::instance()->db->insertSnapshot( $snapshot->id, $snapshot->meta );

		if ( Utils\is_error( $inserted_snapshot ) ) {
			Log::instance()->write( 'Could not add snapshot to database.', 0, 'error' );

			if ( is_array( $inserted_snapshot->data ) ) {
				if ( 'AccessDeniedException' === $inserted_snapshot->data['aws_error_code'] ) {
					Log::instance()->write( 'Access denied. You might not have access to this project.', 0, 'error' );
				}

				Log::instance()->write( 'Error Message: ' . $inserted_snapshot->data['message'], 1, 'error' );
				Log::instance()->write( 'AWS Request ID: ' . $inserted_snapshot->data['aws_request_id'], 1, 'error' );
				Log::instance()->write( 'AWS Error Type: ' . $inserted_snapshot->data['aws_error_type'], 1, 'error' );
				Log::instance()->write( 'AWS Error Code: ' . $inserted_snapshot->data['aws_error_code'], 1, 'error' );
			}

			return;
		}

		Log::instance()->write( 'Push finished! Snapshot ID is ' . $snapshot->id, 0, 'success' );
	}

}
