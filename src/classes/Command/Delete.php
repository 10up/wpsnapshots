<?php
/**
 * Delete command
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
use WPSnapshots\Connection;
use WPSnapshots\Utils;
use WPSnapshots\S3;
use WPSnapshots\Log;


/**
 * This command deletes a snapshot from the repo given an ID.
 */
class Delete extends Command {

	/**
	 * Setup up command
	 */
	protected function configure() {
		$this->setName( 'delete' );
		$this->setDescription( 'Delete a snapshot from the repository.' );
		$this->addArgument( 'snapshot-id', InputArgument::REQUIRED, 'Snapshot ID to delete.' );
	}

	/**
	 * Execute command
	 *
	 * @param  InputInterface  $input
	 * @param  OutputInterface $output
	 */
	protected function execute( InputInterface $input, OutputInterface $output ) {
		Log::instance()->setOutput( $output );

		$connection = Connection::instance()->connect();

		if ( Utils\is_error( $connection ) ) {
			Log::instance()->write( 'Could not connect to repository.', 0, 'error' );
			return;
		}

		$id = $input->getArgument( 'snapshot-id' );

		$snapshot = Connection::instance()->db->getSnapshot( $id );

		if ( Utils\is_error( $snapshot ) ) {
			Log::instance()->write( 'Could not get snapshot from database.', 0, 'error' );

			if ( is_array( $snapshot->data ) && ! empty( $snapshot->data['aws_error_code'] ) ) {
				if ( 'AccessDeniedException' === $snapshot->data['aws_error_code'] ) {
					Log::instance()->write( 'Access denied. You might not have access to this project.', 0, 'error' );
				}

				Log::instance()->write( 'Error Message: ' . $snapshot->data['message'], 1, 'error' );
				Log::instance()->write( 'AWS Request ID: ' . $snapshot->data['aws_request_id'], 1, 'error' );
				Log::instance()->write( 'AWS Error Type: ' . $snapshot->data['aws_error_type'], 1, 'error' );
				Log::instance()->write( 'AWS Error Code: ' . $snapshot->data['aws_error_code'], 1, 'error' );
			}

			return;
		}

		$files_result = Connection::instance()->s3->deleteSnapshot( $id, $snapshot['project'] );

		if ( Utils\is_error( $files_result ) ) {
			Log::instance()->write( 'Could not delete snapshot.', 0, 'error' );

			if ( is_array( $files_result->data ) ) {
				Log::instance()->write( 'S3 delete error:' );
				Log::instance()->write( 'Error Message: ' . $files_result->data['message'], 1, 'error' );
				Log::instance()->write( 'AWS Request ID: ' . $files_result->data['aws_request_id'], 1, 'error' );
				Log::instance()->write( 'AWS Error Type: ' . $files_result->data['aws_error_type'], 1, 'error' );
				Log::instance()->write( 'AWS Error Code: ' . $files_result->data['aws_error_code'], 1, 'error' );
			}

			return;
		}

		$db_result = Connection::instance()->db->deleteSnapshot( $id );

		if ( Utils\is_error( $db_result ) ) {
			Log::instance()->write( 'Could not delete snapshot.', 0, 'error' );

			if ( is_array( $db_result->data ) ) {
				Log::instance()->write( 'DynamoDB delete error:</error>' );
				Log::instance()->write( 'Error Message: ' . $db_result->data['message'], 1, 'error' );
				Log::instance()->write( 'AWS Request ID: ' . $db_result->data['aws_request_id'], 1, 'error' );
				Log::instance()->write( 'AWS Error Type: ' . $db_result->data['aws_error_type'], 1, 'error' );
				Log::instance()->write( 'AWS Error Code: ' . $db_result->data['aws_error_code'], 1, 'error' );
			}

			return;
		}

		Log::instance()->write( 'Snapshot deleted.', 0, 'success' );
	}

}
