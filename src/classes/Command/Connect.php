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
 * The connect command asks the user for S3 keys. When provided working S3 keys,
 * the command saves those keys to ~/.wpprojects.json. Keys are saved under a profiles
 * which enable multiple repositories to be used.
 */
class Connect extends Command {

	/**
	 * Setup up command
	 */
	protected function configure() {
		$this->setName( 'connect' );
		$this->setDescription( 'Connects WPProjects to an S3 repository.' );
		$this->addArgument( 'profile', InputArgument::REQUIRED, 'Profile to connect to.' );
		$this->addOption( 'region', null, InputOption::VALUE_REQUIRED, 'AWS region to use.' );
	}

	/**
	 * Execute command
	 *
	 * @param  InputInterface  $input
	 * @param  OutputInterface $output
	 */
	protected function execute( InputInterface $input, OutputInterface $output ) {
		$profile = $input->getArgument( 'profile' );

		$region = $input->getOption( 'region' );

		if ( empty( $region ) ) {
			$region = 'us-west-1';
		}

		$current_connection_config = ConnectionManager::instance()->getConfig();

		if ( ! Utils\is_error( $current_connection_config ) ) {
			$output->writeln( 'Connection config already exists. Proceeding will overwrite it.' );
		}

		$config = [
			'profile' => $profile,
		];

		/**
		 * Loop until we get S3 credentials that work
		 */
		while ( true ) {
			$helper = $this->getHelper( 'question' );

			$access_key_id = $helper->ask( $input, $output, new Question( 'AWS Access Key ID: ' ) );

			$secret_access_key = $helper->ask( $input, $output, new Question( 'AWS Secret Access Key: ' ) );

			$config['access_key_id'] = $access_key_id;
			$config['secret_access_key'] = $secret_access_key;
			$config['region'] = $region;

			$test = S3::test( $config );

			if ( ! Utils\is_error( $test ) ) {
				$output->writeln( '<info>Repository connection verified and saved!</info>' );
				break;
			} else {
				if ( 0 === $test->code ) {
					$output->writeln( '<comment>Repository connection did not work. Try again?</comment>' );
				} else {
					$output->writeln( '<info>Connection successful. However, no repository has been setup. Run `wpprojects create-repository`</info>' );
					break;
				}
			}
		}

		ConnectionManager::instance()->writeConfig( $config );
	}

}
