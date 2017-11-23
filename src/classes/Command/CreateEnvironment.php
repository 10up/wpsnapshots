<?php

namespace WPSnapshots\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Question\Question;
use WPSnapshots\Config;
use WPSnapshots\Utils;
use WPSnapshots\S3;


/**
 * The create-enviromment command creates a local development environment and pulls a snapshot into it
 */
class CreateEnvironment extends Command {

	/**
	 * Setup up command
	 */
	protected function configure() {
		$this->setName( 'create-environment' );
		$this->setDescription( 'Create an environment with a snapshot.' );
		$this->addArgument( 'snapshot-id', InputArgument::REQUIRED, 'Snapshot to pull into created environment.' );
	}

	/**
	 * Execute command
	 *
	 * @param  InputInterface  $input
	 * @param  OutputInterface $output
	 */
	protected function execute( InputInterface $input, OutputInterface $output ) {
		exec( 'docker info > /dev/null 2>&1', $docker_check_output, $docker_check_return );

		if ( 0 !== $docker_check_return ) {
			$output->writeln( '<error>Docker is either not installed or not running.' );
			exit;
		}

		exec( 'docker-compose ps > /dev/null 2>&1', $docker_compose_check_output, $docker_compose_check_return );

		if ( 0 !== $docker_compose_check_return ) {
			$output->writeln( '<error>docker-compose is not installed or broken. See https://docs.docker.com/compose/install/#install-compose' );
			exit;
		}

		exec( 'git version > /dev/null 2>&1', $git_check_output, $git_check_return );

		if ( 0 !== $git_check_return ) {
			$output->writeln( '<error>Git is not installed.</error>' );
			exit;
		}
	}
}
