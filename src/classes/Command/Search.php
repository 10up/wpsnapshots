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
	 * Pretty format bytes
	 *
	 * @param  int  $size
	 * @param  inte $precision
	 * @return string
	 */
	protected function formatBytes( $size, $precision = 2 ) {
		$base = log( $size, 1000 );
		$suffixes = [ '', 'KB', 'MB', 'GB', 'TB' ];

		return round( pow( 1000, $base - floor( $base ) ), $precision ) . ' ' . $suffixes[ floor( $base ) ];
	}

	/**
	 * Executes the command
	 *
	 * @param  InputInterface  $input
	 * @param  OutputInterface $output
	 */
	protected function execute( InputInterface $input, OutputInterface $output ) {
		$connection = Connection::instance()->connect();

		$verbose = $input->getOption( 'verbose' );

		if ( Utils\is_error( $connection ) ) {
			$output->writeln( '<error>Could not connect to repository.</error>' );
			return;
		}

		$instances = Connection::instance()->db->search( $input->getArgument( 'search-text' ) );

		if ( Utils\is_error( $instances ) ) {
			$output->writeln( '<error>An error occured while searching.</error>' );

			if ( $verbose ) {
				$output->writeln( '<error>Error Message: ' . $inserted_snapshot->data['message'] . '</error>' );
				$output->writeln( '<error>AWS Request ID: ' . $inserted_snapshot->data['aws_request_id'] . '</error>' );
				$output->writeln( '<error>AWS Error Type: ' . $inserted_snapshot->data['aws_error_type'] . '</error>' );
				$output->writeln( '<error>AWS Error Code: ' . $inserted_snapshot->data['aws_error_code'] . '</error>' );
			}
		}

		if ( empty( $instances ) ) {
			$output->writeln( '<comment>No snapshots found.</comment>' );
			return;
		}

		$table = new Table( $output );
		$table->setHeaders( [ 'ID', 'Project', 'Description', 'Author', 'Size', 'Created' ] );

		$rows = [];

		foreach ( $instances as $instance ) {
			if ( empty( $instance['time'] ) ) {
				$instance['time'] = time();
			}

			$rows[ $instance['time'] ] = [
				'id' => ( ! empty( $instance['id'] ) ) ? $instance['id'] : '',
				'project' => ( ! empty( $instance['project'] ) ) ? $instance['project'] : '',
				'description' => ( ! empty( $instance['description'] ) ) ? $instance['description'] : '',
				'author' => ( ! empty( $instance['author']['name'] ) ) ? $instance['author']['name'] : '',
				'size' => ( ! empty( $instance['size'] ) ) ? $this->formatBytes( (int) $instance['size'] ) : '',
				'created' => ( ! empty( $instance['time'] ) ) ? date( 'F j, Y, g:i a', $instance['time'] ) : '',
			];
		}

		krsort( $rows );

		$table->setRows( $rows );

		$table->render();
	}

}
