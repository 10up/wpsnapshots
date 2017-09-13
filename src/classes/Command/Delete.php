<?php

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


/**
 * This command deletes an instance of a project from the repo given an ID.
 */
class Delete extends Command {

	/**
	 * Setup up command
	 */
	protected function configure() {
		$this->setName( 'delete' );
		$this->setDescription( 'Delete a snapshot from the repository.' );
		$this->addArgument( 'instance-id', InputArgument::REQUIRED, 'Snapshot ID to delete.' );
	}

	/**
	 * Execute command
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

		$id = $input->getArgument( 'instance-id' );

		$files_result = Connection::instance()->s3->deleteSnapshot( $id );

		$db_result = Connection::instance()->db->deleteSnapshot( $id );

		if ( Utils\is_error( $files_result ) || Utils\is_error( $files_result ) ) {
			$output->writeln( '<error>Could not delete snapshot</error>' );
		} else {
			$output->writeln( '<info>Snapshot deleted.</info>' );
		}

	}

}
