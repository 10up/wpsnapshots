<?php
/**
 * Create command
 *
 * @package  wpsnapshots
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
use WPSnapshots\Connection;
use WPSnapshots\WordPressBridge;
use WPSnapshots\Config;
use WPSnapshots\Utils;
use WPSnapshots\Snapshot;
use WPSnapshots\Log;

/**
 * The create command creates a snapshot in the .wpsnapshots directory but does not push it remotely.
 */
class Create extends Command {

	/**
	 * Setup up command
	 */
	protected function configure() {
		$this->setName( 'create' );
		$this->setDescription( 'Create a snapshot locally.' );
		$this->addOption( 'exclude-uploads', false, InputOption::VALUE_NONE, 'Exclude uploads from pushed snapshot.' );
		$this->addOption( 'no-scrub', false, InputOption::VALUE_NONE, "Don't scrub personal user data." );

		$this->addOption( 'path', null, InputOption::VALUE_REQUIRED, 'Path to WordPress files.' );
		$this->addOption( 'db_host', null, InputOption::VALUE_REQUIRED, 'Database host.' );
		$this->addOption( 'db_name', null, InputOption::VALUE_REQUIRED, 'Database name.' );
		$this->addOption( 'db_user', null, InputOption::VALUE_REQUIRED, 'Database user.' );
		$this->addOption( 'db_password', null, InputOption::VALUE_REQUIRED, 'Database password.' );
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
			return;
		}

		$path = $input->getOption( 'path' );

		if ( empty( $path ) ) {
			$path = getcwd();
		}

		if ( ! Utils\is_wp_present( $path ) ) {
			Log::instance()->write( 'This is not a WordPress install. You can only create a snapshot from the root of a WordPress install.', 0, 'error' );

			return;
		}

		$helper = $this->getHelper( 'question' );

		$project_question = new Question( 'Project Slug (letters, numbers, _, and - only): ' );
		$project_question->setValidator( '\WPSnapshots\Utils\slug_validator' );

		$project = $helper->ask( $input, $output, $project_question );

		$description_question = new Question( 'Snapshot Description (e.g. Local environment): ' );
		$description_question->setValidator( '\WPSnapshots\Utils\not_empty_validator' );

		$description = $helper->ask( $input, $output, $description_question );

		$snapshot = Snapshot::create(
			[
				'db_host'         => $input->getOption( 'db_host' ),
				'db_name'         => $input->getOption( 'db_name' ),
				'db_user'         => $input->getOption( 'db_user' ),
				'db_password'     => $input->getOption( 'db_password' ),
				'project'         => $project,
				'path'            => $path,
				'description'     => $description,
				'no_scrub'        => $input->getOption( 'no-scrub' ),
				'exclude_uploads' => $input->getOption( 'exclude-uploads' ),
			], $output, $input->getOption( 'verbose' )
		);

		if ( is_a( $snapshot, '\WPSnapshots\Snapshot' ) ) {
			Log::instance()->write( 'Create finished! Snapshot ID is ' . $snapshot->id, 0, 'success' );
		}
	}
}
