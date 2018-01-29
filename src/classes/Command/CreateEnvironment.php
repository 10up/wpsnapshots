<?php

namespace WPSnapshots\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\ArrayInput;
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
		$this->addOption( 'path', null, InputOption::VALUE_REQUIRED, 'Path to new environment.' );
	}

	/**
	 * Execute command
	 *
	 * @param  InputInterface  $input
	 * @param  OutputInterface $output
	 */
	protected function execute( InputInterface $input, OutputInterface $output ) {
		$command = $this->getApplication()->find('pull');

		$connection = Connection::instance()->connect();
		if ( Utils\is_error( $connection ) ) {
			$output->writeln( '<error>Could not connect to repository.</error>' );
			return;
		}

		exec( 'docker info > /dev/null 2>&1', $docker_check_output, $docker_check_return );

		if ( 0 !== $docker_check_return ) {
			$output->writeln( '<error>Docker is either not installed or not running.' );
			exit;
		}

		exec( 'docker-compose version > /dev/null 2>&1', $docker_compose_check_output, $docker_compose_check_return );

		if ( 0 !== $docker_compose_check_return ) {
			$output->writeln( '<error>docker-compose is not installed or broken. See https://docs.docker.com/compose/install/#install-compose' );
			exit;
		}

		exec( 'git version > /dev/null 2>&1', $git_check_output, $git_check_return );

		if ( 0 !== $git_check_return ) {
			$output->writeln( '<error>Git is not installed.</error>' );
			exit;
		}

		$path = $input->getOption( 'path' );

		if ( empty( $path ) ) {
			$path = getcwd();
		}

		$path = Utils\normalize_path( $path );

		if ( is_dir( $path ) && count( glob( $path . '*' ) ) > 0 ) {
			$output->writeln( '<error>Target directory contains files. You can only create an environment in a new or empty directory.</error>' );
			exit;
		}

		$output->writeln( 'Installing WP Local Docker...' );

		exec( 'git clone https://github.com/10up/wp-local-docker.git ' . $path, $clone_check_output, $clone_check_return );

		if ( 0 !== $clone_check_return ) {
			$output->writeln( '<error>An error occured while installing WP Local Docker.</error>' );
			exit;
		}

		$output->writeln( 'Starting WP Local Docker...' );

		exec( 'cd ' .$path . ' && docker-compose up -d' );

		$output->writeln( 'Copying WP Snapshots configuration to WP Local Docker' );

		$config_file = Config::instance()->path();

		copy( $config_file, $path . 'config/wpsnapshots/.wpsnapshots.json' );

		$output->writeln( 'Waiting for containers...' );
		chdir( $path );
		sleep( 10 );

		$output->writeln( 'Installing WordPress' );

		exec( './bin/setup.sh', $download_wp_output, $download_wp_return );

		if ( 0 !== $download_wp_return ) {
			$output->writeln( '<error>An error occured while attempting to download WordPress</error>' );
		}

		$output->writeln( $download_wp_output );

		exec( 'docker-compose exec --user www-data phpfpm wp core download', $download_wp_output, $download_wp_return );

		if ( 0 !== $download_wp_return ) {
			$output->writeln( '<error>An error occured while attempting to download WordPress</error>' );
		}

		$output->writeln( $download_wp_output );

		exec( 'docker-compose exec --user www-data phpfpm wp core config --dbhost=mysql --dbname=wordpress --dbuser=root --dbpass=password', $install_wp_output, $install_wp_return );

		if ( 0 !== $install_wp_return ) {
			$output->writeln( '<error>An error occured while attempting to install WordPress</error>' );
		}

		$output->writeln( $install_wp_output );

		$output->writeln( 'Pulling Snapshot' );

		$cmd = 'docker-compose exec wpsnapshots /snapshots.sh pull ' . $input->getArgument( 'snapshot-id' );

		passthru( $cmd );

	}
}
