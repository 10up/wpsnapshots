<?php
/**
 * Download command
 *
 * @package  wpsnapshots
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
use WPSnapshots\RepositoryManager;
use WPSnapshots\Utils;
use WPSnapshots\Snapshot;
use WPSnapshots\Meta;
use WPSnapshots\Log;

/**
 * The downloads a snapshot to the .wpsnapshots directory but does not pull it into the current install.
 */
class Download extends Command {

	/**
	 * Setup up command
	 */
	protected function configure() {
		$this->setName( 'download' );
		$this->setDescription( 'Download a snapshot from the repository.' );
		$this->addArgument( 'snapshot_id', InputArgument::REQUIRED, 'Snapshot ID to download.' );
		$this->addOption( 'repository', null, InputOption::VALUE_REQUIRED, 'Repository to use. Defaults to first repository saved in config.' );
		$this->addOption( 'include_files', null, InputOption::VALUE_NONE, 'Include files in snapshot.' );
		$this->addOption( 'include_db', null, InputOption::VALUE_NONE, 'Include database in snapshot.' );
	}

	/**
	 * Executes the command
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

		if ( empty( $path ) ) {
			$path = getcwd();
		}

		$remote_meta = Meta::getRemote( $id, $repository->getName() );

		if ( empty( $remote_meta ) ) {
			Log::instance()->write( 'Snapshot does not exist.', 0, 'error' );

			return 1;
		}

		$helper = $this->getHelper( 'question' );

		if ( ! empty( $remote_meta['contains_files'] ) && ! empty( $remote_meta['contains_db'] ) ) {
			if ( empty( $input->getOption( 'include_files' ) ) ) {
				$files_question = new ConfirmationQuestion( 'Do you want to download snapshot files? (Y/n) ', true );

				$include_files = $helper->ask( $input, $output, $files_question );
			} else {
				$include_files = true;
			}

			if ( empty( $input->getOption( 'include_db' ) ) ) {
				$db_question = new ConfirmationQuestion( 'Do you want to download the snapshot database? (Y/n) ', true );

				$include_db = $helper->ask( $input, $output, $db_question );
			} else {
				$include_db = true;
			}
		} else {
			$include_db    = true;
			$include_files = true;
		}

		$local_meta = Meta::getLocal( $id, $repository->getName() );

		if ( ! empty( $local_meta ) && $local_meta['contains_files'] === $include_files && $local_meta['contains_db'] === $include_db ) {
			$overwrite_snapshot = $helper->ask( $input, $output, new ConfirmationQuestion( 'This snapshot exists locally. Do you want to overwrite it? (Y/n) ', true ) );

			if ( empty( $overwrite_snapshot ) ) {
				Log::instance()->write( 'No action needed.', 0, 'success' );

				return 0;
			}
		}

		$snapshot = Snapshot::getRemote( $id, $repository->getName(), ! $include_files, ! $include_db );

		if ( is_a( $snapshot, '\WPSnapshots\Snapshot' ) ) {
			Log::instance()->write( 'Download finished!', 0, 'success' );
		}
	}
}
