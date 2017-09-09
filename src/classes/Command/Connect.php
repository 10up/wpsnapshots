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
	}

	/**
	 * Execute command
	 *
	 * @param  InputInterface  $input
	 * @param  OutputInterface $output
	 */
	protected function execute( InputInterface $input, OutputInterface $output ) {
		$profile = '10up';

		$current_connection_config = ConnectionManager::instance()->getConfig( $profile );

		if ( ! Utils\is_error( $current_connection_config ) ) {
			$output->writeln( 'Connection config profile already exist. Proceeding will overwrite it.' );
		}

		if ( Utils\is_error( $current_connection_config ) ) {
			$current_connection_config = [];
		}

		/**
		 * Loop until we get S3 credentials that work
		 */
		while ( true ) {
			$helper = $this->getHelper( 'question' );

			$access_key_id = $helper->ask( $input, $output, new Question( 'AWS Access Key ID: ' ) );

			$secret_access_key = $helper->ask( $input, $output, new Question( 'AWS Secret Access Key: ' ) );

			$profile = '10up';

			$config = [
				$profile => [
					'access_key_id'     => $access_key_id,
					'secret_access_key' => $secret_access_key,
					'region'            => 'us-west-1',
				],
			];

			$test = S3::test( $config[ $profile ] );

			if ( ! Utils\is_error( $test ) ) {
				$output->writeln( '<info>Repository connection verified and saved!</info>' );
				ConnectionManager::instance()->writeConfig( array_merge( $current_connection_config, $config ) );
				break;
			} else {
				$output->writeln( '<comment>Repository connection did not work. Try again?</comment>' );
			}
		}
	}

}
