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
 * The configure command sets up WP Snapshots with AWS info and user info.
 */
class Configure extends Command {

	/**
	 * Setup up command
	 */
	protected function configure() {
		$this->setName( 'configure' );
		$this->setDescription( 'Configure WP Snapshots with an existing repository.' );
		$this->addArgument( 'repository', InputArgument::REQUIRED, 'Repository to configure.' );
		$this->addOption( 'region', null, InputOption::VALUE_REQUIRED, 'AWS region to use.' );
	}

	/**
	 * Execute command
	 *
	 * @param  InputInterface  $input
	 * @param  OutputInterface $output
	 */
	protected function execute( InputInterface $input, OutputInterface $output ) {
		$repository = $input->getArgument( 'repository' );

		$region = $input->getOption( 'region' );

		if ( empty( $region ) ) {
			$region = 'us-west-1';
		}

		$config = Config::instance()->get();

		if ( ! Utils\is_error( $config ) ) {
			$output->writeln( 'Repository config already exists. Proceeding will overwrite it.' );
		}

		$config = [
			'repository' => $repository,
		];

		$helper = $this->getHelper( 'question' );

		/**
		 * Loop until we get S3 credentials that work
		 */
		while ( true ) {

			$access_key_id = $helper->ask( $input, $output, new Question( 'AWS Access Key ID: ' ) );

			$secret_access_key = $helper->ask( $input, $output, new Question( 'AWS Secret Access Key: ' ) );

			$config['access_key_id'] = $access_key_id;
			$config['secret_access_key'] = $secret_access_key;
			$config['region'] = $region;

			$test = S3::test( $config );

			if ( ! Utils\is_error( $test ) ) {
				break;
			} else {
				if ( 0 === $test->code ) {
					$output->writeln( '<comment>Repository connection did not work. Try again?</comment>' );
				} else {
					$output->writeln( '<comment>We successfully connected to AWS. However, no repository has been created. Run `wpsnapshots create-repository` after configuration is complete.</comment>' );
					break;
				}
			}
		}

		$name = $helper->ask( $input, $output, new Question( 'Your Name: ' ) );
		$name->setValidator( '\WPSnapshots\Utils\not_empty_validator' );

		$config['name'] = $name;

		$email = $helper->ask( $input, $output, new Question( 'Your Email: ' ) );

		if ( ! empty( $email ) ) {
			$config['email'] = $email;
		}

		Config::instance()->write( $config );

		$output->writeln( '<info>WP Snapshots configuration verified and saved.</info>' );
	}

}
