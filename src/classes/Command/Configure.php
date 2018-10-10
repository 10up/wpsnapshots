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
use Symfony\Component\Console\Question\ConfirmationQuestion;
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
		$this->setDescription( 'Configure WP Snapshots with a repository.' );
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

		$config = Config::get();

		if ( ! empty( $config[ $repository ] ) ) {
			Log::instance()->write( 'Repository config already exists. Proceeding will overwrite it.' );
		}

		$repo_config = [
			'repository' => $repository,
		];

		$helper = $this->getHelper( 'question' );

		$i = 0;

		/**
		 * Loop until we get S3 credentials or the user proceeds
		 */
		while ( true ) {

			if ( 0 < $i || empty( $access_key_id ) ) {
				$access_key_id = $helper->ask( $input, $output, new Question( 'AWS Access Key ID: ' ) );
			}

			if ( 0 < $i || empty( $secret_access_key ) ) {
				$secret_access_key = $helper->ask( $input, $output, new Question( 'AWS Secret Access Key: ' ) );
			}

			if ( 0 < $i || empty( $region ) ) {
				$region = $helper->ask( $input, $output, new Question( 'AWS region (defaults to us-west-1): ', 'us-west-1' ) );
			}

			$repo_config['access_key_id']     = $access_key_id;
			$repo_config['secret_access_key'] = $secret_access_key;
			$repo_config['region']            = $region;

			$test = S3::test( $repo_config );

			if ( ! Utils\is_error( $test ) ) {
				break;
			} else {
				if ( 'NoSuchBucket' === $test->data['aws_error_code'] ) {
					Log::instance()->write( 'This repository does not exist on AWS. However, no repository has been created. Run `wpsnapshots create-repository` after configuration is complete.', 0, 'warning' );
					break;
				}

				Log::instance()->write( 'Error Message: ' . $test->data['message'], 1, 'error' );
				Log::instance()->write( 'AWS Request ID: ' . $test->data['aws_request_id'], 1, 'error' );
				Log::instance()->write( 'AWS Error Type: ' . $test->data['aws_error_type'], 1, 'error' );
				Log::instance()->write( 'AWS Error Code: ' . $test->data['aws_error_code'], 1, 'error' );

				if ( $helper->ask( $input, $output, new ConfirmationQuestion( 'Could not verify credentials. Proceed anyway? (yes or no): ', false ) ) ) {
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

		$repo_config['name'] = $name;

		if ( empty( $email ) ) {
			$email = $helper->ask( $input, $output, new Question( 'Your Email: ' ) );
		}

		if ( ! empty( $email ) ) {
			$repo_config['email'] = $email;
		}

		$repos                  = $config['repositories'];
		$repos[ $repository ]   = $repo_config;
		$config['repositories'] = $repos;

		$config->write();

		Log::instance()->write( 'WP Snapshots configuration saved.', 0, 'success' );
	}
}
