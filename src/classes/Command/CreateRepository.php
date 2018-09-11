<?php
/**
 * Create repository command
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
use WPSnapshots\Log;

/**
 * The create-repository command creates the wpsnapshots bucket in the provided
 * S3 repository and the table within DynamoDB. If the bucket or table already exists,
 * the command does nothing.
 */
class CreateRepository extends Command {

	/**
	 * Setup up command
	 */
	protected function configure() {
		$this->setName( 'create-repository' );
		$this->setDescription( 'Create new WP Snapshots repository.' );
	}

	/**
	 * Executes the command
	 *
	 * @param  InputInterface  $input Command input
	 * @param  OutputInterface $output Command output
	 */
	protected function execute( InputInterface $input, OutputInterface $output ) {
		Log::instance()->setOutput( $output );

		Connection::instance()->connect();

		$create_s3 = Connection::instance()->s3->createBucket();

		$s3_setup = true;

		if ( Utils\is_error( $create_s3 ) ) {

			if ( 0 === $create_s3->code ) {
				Log::instance()->write( 'Access denied. Could not read AWS buckets. S3 may already be setup.', 0, 'warning' );
			} elseif ( 1 === $create_s3->code ) {
				Log::instance()->write( 'S3 already setup.', 0, 'warning' );
			} else {
				if ( 'BucketAlreadyOwnedByYou' === $create_s3->data['aws_error_code'] || 'BucketAlreadyExists' === $create_s3->data['aws_error_code'] ) {
					Log::instance()->write( 'S3 already setup.', 0, 'warning' );
				} else {
					Log::instance()->write( 'Could not create S3 bucket.', 0, 'error' );
					$s3_setup = false;

					Log::instance()->write( 'Error Message: ' . $create_s3->data['message'], 1, 'error' );
					Log::instance()->write( 'AWS Request ID: ' . $create_s3->data['aws_request_id'], 1, 'error' );
					Log::instance()->write( 'AWS Error Type: ' . $create_s3->data['aws_error_type'], 1, 'error' );
					Log::instance()->write( 'AWS Error Code: ' . $create_s3->data['aws_error_code'], 1, 'error' );
				}
			}
		}

		$create_db = Connection::instance()->db->createTables();

		$db_setup = true;

		if ( Utils\is_error( $create_db ) ) {
			if ( 'ResourceInUseException' === $create_db->data['aws_error_code'] ) {
				Log::instance()->write( 'DynamoDB table already setup.', 0, 'warning' );
			} else {
				Log::instance()->write( 'Could not create DynamoDB table.', 0, 'error' );
				$db_setup = false;

				Log::instance()->write( 'Error Message: ' . $create_db->data['message'], 1, 'error' );
				Log::instance()->write( 'AWS Request ID: ' . $create_db->data['aws_request_id'], 1, 'error' );
				Log::instance()->write( 'AWS Error Type: ' . $create_db->data['aws_error_type'], 1, 'error' );
				Log::instance()->write( 'AWS Error Code: ' . $create_db->data['aws_error_code'], 1, 'error' );
			}
		}

		if ( ! $db_setup || ! $s3_setup ) {
			Log::instance()->write( 'Repository could not be created.', 0, 'error' );
			return 1;
		} else {
			Log::instance()->write( 'Repository setup!', 0, 'success' );
		}
	}

}
