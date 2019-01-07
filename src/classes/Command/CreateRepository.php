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
use WPSnapshots\RepositoryManager;
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
		$this->addArgument( 'repository', InputArgument::REQUIRED, 'Repository to create.' );
	}

	/**
	 * Executes the command
	 *
	 * @param  InputInterface  $input Command input
	 * @param  OutputInterface $output Command output
	 */
	protected function execute( InputInterface $input, OutputInterface $output ) {
		Log::instance()->setOutput( $output );

		$repository = RepositoryManager::instance()->setup( $input->getArgument( 'repository' ) );

		if ( ! $repository ) {
			Log::instance()->write( 'Repository not configured. Before creating the repository, you must configure. Run `wpsnapshots configure ' . $repository . '`', 0, 'error' );
			return 1;
		}

		$create_s3 = $repository->getS3()->createBucket();

		$s3_setup = true;

		if ( true !== $create_s3 ) {
			if ( 'BucketExists' === $create_s3 || 'BucketAlreadyOwnedByYou' === $create_s3 || 'BucketAlreadyExists' === $create_s3 ) {
				Log::instance()->write( 'S3 already setup.', 0, 'warning' );
			} else {
				Log::instance()->write( 'Could not create S3 bucket.', 0, 'error' );

				$s3_setup = false;
			}
		}

		$create_db = $repository->getDB()->createTables();

		$db_setup = true;

		if ( true !== $create_db ) {
			if ( 'ResourceInUseException' === $create_db ) {
				Log::instance()->write( 'DynamoDB table already setup.', 0, 'warning' );
			} else {
				Log::instance()->write( 'Could not create DynamoDB table.', 0, 'error' );

				$db_setup = false;
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
