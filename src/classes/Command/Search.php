<?php

namespace WPProjects\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Helper\Table;
use WPProjects\ConnectionManager;
use WPProjects\WordPressConfig;
use WPProjects\ProjectConfig;
use WPProjects\Utils;

/**
 * The search command searches for projects within the repository.
 */
class Search extends Command {

	/**
	 * Setup up command
	 */
	protected function configure() {
		$this->setName( 'search' );
		$this->setDescription( 'Search for projects within a repository.' );
		$this->addArgument( 'search-text', InputArgument::REQUIRED, 'Text to search against project instances.' );
	}

	/**
	 * Executes the command
	 *
	 * @param  InputInterface  $input
	 * @param  OutputInterface $output
	 */
	protected function execute( InputInterface $input, OutputInterface $output ) {
		$connection = ConnectionManager::instance()->connect();

		if ( Utils\is_error( $connection ) ) {
			$output->writeln( '<error>Could not connect to repository.</error>' );
			return;
		}

		$instances = ConnectionManager::instance()->db->search( $input->getArgument( 'search-text' ) );

		if ( empty( $instances ) ) {
			$output->writeln( '<comment>No project instances found.</comment>' );
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
