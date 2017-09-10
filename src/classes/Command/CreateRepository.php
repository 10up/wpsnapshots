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
use \WPProjects\Utils;

/**
 * The create-repository command creates the wpprojects bucket in the provided
 * S3 profile and the table within DynamoDB. If the bucket or table already exists,
 * the command does nothing.
 */
class CreateRepository extends Command {

	/**
	 * Setup up command
	 */
	protected function configure() {
		$this->setName( 'create-repository' );
		$this->setDescription( 'Setup WPProjects repository on S3' );
	}

	/**
	 * Executes the command
	 *
	 * @param  InputInterface  $input
	 * @param  OutputInterface $output
	 */
	protected function execute( InputInterface $input, OutputInterface $output ) {
		ConnectionManager::instance()->connect();

		$create_s3 = ConnectionManager::instance()->s3->createBucket();

		$s3_setup = true;

		if ( Utils\is_error( $create_s3 ) ) {
			if ( 1 === $create_s3->code ) {
				$output->writeln( '<comment>S3 already setup.</comment>' );
			} elseif ( 2 === $create_s3->code ) {
				$output->writeln( '<error>Cannot write to existing WP Projects S3 bucket.</error>' );
				$s3_setup = false;
			} else {
				$output->writeln( '<error>Could not create S3 bucket.</error>' );
				$s3_setup = false;
			}
		}

		$create_db = ConnectionManager::instance()->db->createTables();

		$db_setup  = true;

		if ( Utils\is_error( $create_db ) ) {
			if ( 1 === $create_db->code ) {
				$output->writeln( '<comment>DynamoDB table already setup.</comment>' );
			} else {
				$output->writeln( '<error>Could not create DynamoDB table.</error>' );
				$db_setup = false;
			}
		}

		if ( ! $db_setup || ! $s3_setup ) {
			$output->writeln( '<error>Repository could not be created.</error>' );
		} else {
			$output->writeln( '<info>Repository setup!</info>' );
		}
	}

}
