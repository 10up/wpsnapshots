<?php
/**
 * Search command
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
use Symfony\Component\Console\Helper\Table;
use WPSnapshots\Utils;
use WPSnapshots\RepositoryManager;
use WPSnapshots\Log;

/**
 * The search command searches for projects within the repository.
 */
class Search extends Command {

	/**
	 * Setup up command
	 */
	protected function configure() {
		$this->setName( 'search' );
		$this->setDescription( 'Search for snapshots within a repository.' );
		$this->addArgument( 'search_text', InputArgument::REQUIRED, 'Text to search against snapshots.' );
		$this->addOption( 'repository', null, InputOption::VALUE_REQUIRED, 'Repository to use. Defaults to first repository saved in config.' );
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

		$instances = $repository->getDB()->search( $input->getArgument( 'search_text' ) );

		if ( false === $instances ) {
			Log::instance()->write( 'An error occured while searching.', 0, 'success' );
		}

		if ( empty( $instances ) ) {
			Log::instance()->write( 'No snapshots found.', 0, 'warning' );
			return;
		}

		$table = new Table( $output );
		$table->setHeaders( [ 'ID', 'Project', 'Files', 'Database', 'Description', 'Author', 'Size', 'Multisite', 'Created' ] );

		$rows = [];

		foreach ( $instances as $instance ) {
			if ( empty( $instance['time'] ) ) {
				$instance['time'] = time();
			}

			// Defaults to yes for backwards compat since old snapshots dont have this meta.
			$contains_files = 'Yes';
			$contains_db    = 'Yes';

			if ( isset( $instance['contains_files'] ) ) {
				$contains_files = $instance['contains_files'] ? 'Yes' : 'No';
			}

			if ( isset( $instance['contains_db'] ) ) {
				$contains_db = $instance['contains_db'] ? 'Yes' : 'No';
			}

			$size = '-';

			if ( empty( $instance['files_size'] ) && empty( $instance['db_size'] ) ) {
				// This is for backwards compat with old snapshots
				if ( ! empty( $instance['size'] ) ) {
					$size = Utils\format_bytes( (int) $instance['size'] );
				}
			} else {
				$size = 0;

				if ( ! empty( $instance['files_size'] ) ) {
					$size += (int) $instance['files_size'];
				} if ( ! empty( $instance['db_size'] ) ) {
					$size += (int) $instance['db_size'];
				}

				$size = Utils\format_bytes( $size );
			}

			$rows[ $instance['time'] ] = [
				'id'             => ( ! empty( $instance['id'] ) ) ? $instance['id'] : '',
				'project'        => ( ! empty( $instance['project'] ) ) ? $instance['project'] : '',
				'contains_files' => $contains_files,
				'contains_db'    => $contains_db,
				'description'    => ( ! empty( $instance['description'] ) ) ? $instance['description'] : '',
				'author'         => ( ! empty( $instance['author']['name'] ) ) ? $instance['author']['name'] : '',
				'size'           => $size,
				'multisite'      => ( ! empty( $instance['multisite'] ) ) ? 'Yes' : 'No',
				'created'        => ( ! empty( $instance['time'] ) ) ? date( 'F j, Y, g:i a', $instance['time'] ) : '',
			];
		}

		ksort( $rows );

		$table->setRows( $rows );

		$table->render();
	}

}
