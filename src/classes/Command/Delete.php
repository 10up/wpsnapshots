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
use WPProjects\Utils;
use WPProjects\S3;


/**
 * This command deletes an instance of a project from the repo given an ID.
 */
class Delete extends Command {

	/**
	 * Setup up command
	 */
	protected function configure() {
		$this->setName( 'delete' );
		$this->setDescription( 'Delete a project instance from the repository.' );
		$this->addArgument( 'instance-id', InputArgument::REQUIRED, 'Project instance ID to delete.' );
	}

	/**
	 * Execute command
	 *
	 * @param  InputInterface  $input
	 * @param  OutputInterface $output
	 */
	protected function execute( InputInterface $input, OutputInterface $output ) {
		$connection = ConnectionManager::instance()->connect( '10up' );

		if ( Utils\is_error( $connection ) ) {
			$output->writeln( '<error>Could not connect to repository.</error>' );
			return;
		}

		$id = $input->getArgument( 'instance-id' );

		$files_result = ConnectionManager::instance()->s3->deleteProjectInstance( $id );

		$db_result = ConnectionManager::instance()->db->deleteProjectInstance( $id );

		if ( Utils\is_error( $files_result ) || Utils\is_error( $files_result ) ) {
			$output->writeln( '<error>Could not delete project instance</error>' );
		} else {
			$output->writeln( '<info>Project instance deleted.</info>' );
		}

	}

}
