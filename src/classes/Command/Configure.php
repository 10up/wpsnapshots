<?php
/**
 * Configure command
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
use WPSnapshots\Config;
use WPSnapshots\Utils;
use WPSnapshots\S3;
use WPSnapshots\Log;


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
		$this->addOption( 'aws_key', null, InputOption::VALUE_REQUIRED, 'AWS Access Key ID.' );
		$this->addOption( 'aws_secret', null, InputOption::VALUE_REQUIRED, 'AWS Secret Access Key.' );
		$this->addOption( 'user_name', null, InputOption::VALUE_REQUIRED, 'Your Name.' );
		$this->addOption( 'user_email', null, InputOption::VALUE_REQUIRED, 'Your Email.' );
	}

	/**
	 * Execute command
	 *
	 * @param  InputInterface  $input Command input
	 * @param  OutputInterface $output Command output
	 */
	protected function execute( InputInterface $input, OutputInterface $output ) {
		Log::instance()->setOutput( $output );

		$repository = $input->getArgument( 'repository' );

		$region            = $input->getOption( 'region' );
		$access_key_id     = $input->getOption( 'aws_key' );
		$secret_access_key = $input->getOption( 'aws_secret' );

		if ( empty( $region ) ) {
			$region = 'us-west-1';
		}

		$config = Config::get();

		if ( ! empty( $config['repositories'][ $repository ] ) ) {
			Log::instance()->write( 'Repository config already exists. Proceeding will overwrite it.' );
		}

		$repo_config = [
			'repository' => $repository,
		];

		$helper = $this->getHelper( 'question' );

		$i = 0;

		/**
		 * Loop until we get S3 credentials that work
		 */
		while ( true ) {

			if ( 0 < $i || empty( $access_key_id ) ) {
				$access_key_id = $helper->ask( $input, $output, new Question( 'AWS Access Key ID: ' ) );
			}

			if ( 0 < $i || empty( $secret_access_key ) ) {
				$secret_access_key = $helper->ask( $input, $output, new Question( 'AWS Secret Access Key: ' ) );
			}

			$repo_config['access_key_id']     = $access_key_id;
			$repo_config['secret_access_key'] = $secret_access_key;
			$repo_config['region']            = $region;

			$test = S3::test( $repo_config );

			if ( true !== $test ) {
				break;
			} else {
				if ( 'InvalidAccessKeyId' === $test ) {
					Log::instance()->write( 'Repository connection did not work. Try again?', 0, 'warning' );
				} elseif ( 'NoSuchBucket' === $test ) {
					Log::instance()->write( 'We successfully connected to AWS. However, no repository has been created. Run `wpsnapshots create-repository` after configuration is complete.', 0, 'warning' );
					break;
				} else {
					break;
				}
			}

			$i++;
		}

		$name  = $input->getOption( 'user_name' );
		$email = $input->getOption( 'user_email' );

		if ( empty( $name ) ) {
			$name_question = new Question( 'Your Name: ' );
			$name_question->setValidator( '\WPSnapshots\Utils\not_empty_validator' );
			$name = $helper->ask( $input, $output, $name_question );
		}

		$config['name'] = $name;

		if ( empty( $email ) ) {
			$email = $helper->ask( $input, $output, new Question( 'Your Email: ' ) );
		}

		if ( ! empty( $email ) ) {
			$config['email'] = $email;
		}

		$create_dir = Utils\create_snapshot_directory();

		if ( ! $create_dir ) {
			Log::instance()->write( 'Cannot create necessary snapshot directory.', 0, 'error' );

			return 1;
		}

		$repositories = $config['repositories'];

		$repositories[ $repository ] = $repo_config;

		$config['repositories'] = $repositories;

		$config->write();

		Log::instance()->write( 'WP Snapshots configuration verified and saved.', 0, 'success' );
	}
}
