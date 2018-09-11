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
use WPSnapshots\WordPressBridge;
use WPSnapshots\Utils;
use WPSnapshots\Connection;
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
		$this->addArgument( 'search-text', InputArgument::REQUIRED, 'Text to search against snapshots.' );
	}

	/**
	 * Pretty format bytes
	 *
	 * @param  int  $size Size in bytes
	 * @param  inte $precision Precision level
	 * @return string
	 */
	protected function formatBytes( $size, $precision = 2 ) {
		$base     = log( $size, 1000 );
		$suffixes = [ '', 'KB', 'MB', 'GB', 'TB' ];

		return round( pow( 1000, $base - floor( $base ) ), $precision ) . ' ' . $suffixes[ floor( $base ) ];
	}

	/**
	 * Executes the command
	 *
	 * @param  InputInterface  $input Command input
	 * @param  OutputInterface $output Command output
	 */
	protected function execute( InputInterface $input, OutputInterface $output ) {
		Log::instance()->setOutput( $output );

		$connection = Connection::instance()->connect();

		if ( Utils\is_error( $connection ) ) {
			Log::instance()->write( 'Could not connect to repository.', 0, 'error' );
			return 1;
		}

		$instances = Connection::instance()->db->search( $input->getArgument( 'search-text' ) );

		if ( Utils\is_error( $instances ) ) {
			Log::instance()->write( 'An error occured while searching.', 0, 'success' );

			if ( is_array( $inserted_snapshot->data ) ) {
				Log::instance()->write( 'Error Message: ' . $inserted_snapshot->data['message'], 1, 'error' );
				Log::instance()->write( 'AWS Request ID: ' . $inserted_snapshot->data['aws_request_id'], 1, 'error' );
				Log::instance()->write( 'AWS Error Type: ' . $inserted_snapshot->data['aws_error_type'], 1, 'error' );
				Log::instance()->write( 'AWS Error Code: ' . $inserted_snapshot->data['aws_error_code'], 1, 'error' );
			}
		}

		if ( empty( $instances ) ) {
			Log::instance()->write( 'No snapshots found.', 0, 'warning' );
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
				'id'          => ( ! empty( $instance['id'] ) ) ? $instance['id'] : '',
				'project'     => ( ! empty( $instance['project'] ) ) ? $instance['project'] : '',
				'description' => ( ! empty( $instance['description'] ) ) ? $instance['description'] : '',
				'author'      => ( ! empty( $instance['author']['name'] ) ) ? $instance['author']['name'] : '',
				'size'        => ( ! empty( $instance['size'] ) ) ? $this->formatBytes( (int) $instance['size'] ) : '',
				'created'     => ( ! empty( $instance['time'] ) ) ? date( 'F j, Y, g:i a', $instance['time'] ) : '',
			];
		}

		krsort( $rows );

		$table->setRows( $rows );

		$table->render();
	}

}
