<?php

namespace WPSnapshots\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Helper\Table;
use WPSnapshots\WordPressBridge;
use WPSnapshots\Utils;
use WPSnapshots\Connection;

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
		$this->addArgument( 'search-text', InputArgument::REQUIRED, 'Text to search against snapshots.' );
	}

	/**
	 * Executes the command
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

		$instances = Connection::instance()->db->search( $input->getArgument( 'search-text' ) );

		if ( empty( $instances ) ) {
			$output->writeln( '<comment>No snapshots found.</comment>' );
			return;
		}

		$table = new Table( $output );
		$table->setHeaders( [ 'ID', 'Project', 'Environment', 'Created' ] );

		$rows = [];

		foreach ( $instances as $instance ) {
			$rows[ $instance['time'] ] = [
				'id' => $instance['id'],
				'project' => $instance['project'],
				'environment' => $instance['environment'],
				'created' => date( 'F j, Y, g:i a', $instance['time'] ),
			];
		}

		krsort( $rows );

		$table->setRows( $rows );

		$table->render();
	}

}
