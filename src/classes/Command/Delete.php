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
use WPSnapshots\RepositoryManager;
use WPSnapshots\Utils;
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
		$this->addArgument( 'snapshot_id', InputArgument::REQUIRED, 'Snapshot ID to delete.' );
		$this->addOption( 'repository', null, InputOption::VALUE_REQUIRED, 'Repository to use. Defaults to first repository saved in config.' );
	}

	/**
	 * Execute command
	 *
	 * @param  InputInterface  $input Command input
	 * @param  OutputInterface $output Command output
	 */
	protected function execute( InputInterface $input, OutputInterface $output ) {
		Log::instance()->setOutput( $output );

		$repository = RepositoryManager::instance()->setup( $input->getOption( 'repository' ) );

		if ( ! $repository ) {
			Log::instance()->write( 'Could not setup repository.', 0, 'error' );
			return 1;
		}

		$id = $input->getArgument( 'snapshot_id' );

		$snapshot = $repository->getDB()->getSnapshot( $id );

		if ( ! $snapshot ) {
			Log::instance()->write( 'Could not get snapshot from database.', 0, 'error' );

			return 1;
		}

		$files_result = $repository->getS3()->deleteSnapshot( $id, $snapshot['project'] );

		if ( ! $files_result ) {
			Log::instance()->write( 'Could not delete snapshot.', 0, 'error' );

			return 1;
		}

		$db_result = $repository->getDB()->deleteSnapshot( $id );

		if ( ! $db_result ) {
			Log::instance()->write( 'Could not delete snapshot.', 0, 'error' );

			return 1;
		}

		Log::instance()->write( 'Snapshot deleted.', 0, 'success' );
	}

}
