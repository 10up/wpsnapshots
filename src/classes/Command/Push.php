<?php
/**
 * Push command
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
use WPSnapshots\RepositoryManager;
use WPSnapshots\WordPressBridge;
use WPSnapshots\Config;
use WPSnapshots\Utils;
use WPSnapshots\Snapshot;
use WPSnapshots\Meta;
use WPSnapshots\Log;

/**
 * The push command first runs "create" to create the snapshot, then pushes it to a remote repository.
 */
class Push extends Command {

	/**
	 * Setup up command
	 */
	protected function configure() {
		$this->setName( 'push' );
		$this->setDescription( 'Push a snapshot to a repository.' );
		$this->addArgument( 'snapshot_id', InputArgument::OPTIONAL, 'Optional snapshot ID to push. If none is provided, a new snapshot will be created from the local environment.' );
		$this->addOption( 'repository', null, InputOption::VALUE_REQUIRED, 'Repository to use. Defaults to first repository saved in config.' );
		$this->addOption( 'small', false, InputOption::VALUE_NONE, 'Trim data and files to create a small snapshot. Note that this action will modify your local.' );
		$this->addOption( 'include_files', null, InputOption::VALUE_NONE, 'Include files in snapshot.' );
		$this->addOption( 'include_db', null, InputOption::VALUE_NONE, 'Include database in snapshot.' );

		$this->addOption( 'slug', null, InputOption::VALUE_REQUIRED, 'Project slug for snapshot.' );
		$this->addOption( 'description', null, InputOption::VALUE_OPTIONAL, 'Description of snapshot.' );
		$this->addOption( 'no_scrub', false, InputOption::VALUE_NONE, "Don't scrub personal user data. This is a legacy option and equivalent to --scrub=0" );
		$this->addOption( 'scrub', false, InputOption::VALUE_REQUIRED, 'Scrubbing to do on data. 2 is the most aggressive and replaces all user information with dummy data; 1 only replaces passwords; 0 is no scrubbing. Defaults to 2.', 2 );

		$this->addOption( 'path', null, InputOption::VALUE_REQUIRED, 'Path to WordPress files.' );
		$this->addOption( 'db_host', null, InputOption::VALUE_REQUIRED, 'Database host.' );
		$this->addOption( 'db_name', null, InputOption::VALUE_REQUIRED, 'Database name.' );
		$this->addOption( 'db_user', null, InputOption::VALUE_REQUIRED, 'Database user.' );
		$this->addOption( 'db_password', null, InputOption::VALUE_REQUIRED, 'Database password.' );
		$this->addOption( 'exclude', false, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Exclude a file or directory from the snapshot.' );
		$this->addOption( 'exclude_uploads', false, InputOption::VALUE_NONE, 'Exclude uploads from pushed snapshot.' );
	}
/**
	 * Executes the command
	 *
	 * @param  InputInterface  $input Command input
	 * @param  OutputInterface $output Command output
	 */
	protected function execute( InputInterface $input, OutputInterface $output ) {
		Log::instance()->setOutput( $output );

		$repository = RepositoryManager::instance()->setup( $input->getOption( 'repository' ) );

		if ( ! $repository ) {
			Log::instance()->write( 'Could not setup repository.', 0, 'error' );
			return 1;
		}

		$snapshot_id = $input->getArgument( 'snapshot_id' );

		if ( ! empty( $snapshot_id ) ) {
			$remote_snapshot = Meta::getRemote( $snapshot_id, $repository->getName() );

			if ( ! empty( $remote_snapshot ) ) {
				Log::instance()->write( 'You can not overwrite an existing snapshot. Please create a new one.', 0, 'error' );

				return 1;
			}

			$local_snapshot = Meta::getLocal( $snapshot_id, $repository->getName() );

			if ( empty( $local_snapshot ) ) {
				Log::instance()->write( 'Snapshot not found locally.', 0, 'error' );

				return 1;
			}

			$snapshot = Snapshot::getLocal( $snapshot_id, $repository->getName() );
		} else {

			$path = $input->getOption( 'path' );

			if ( empty( $path ) ) {
				$path = getcwd();
			}

			$path = Utils\normalize_path( $path );

			$helper = $this->getHelper( 'question' );

			$verbose = $input->getOption( 'verbose' );

			$project = $input->getOption( 'slug' );

			if ( ! empty( $project ) ) {
				$project = preg_replace( '#[^a-zA-Z0-9\-_]#', '', $project );
			}

			if ( empty( $project ) ) {
				$project_question = new Question( 'Project Slug (letters, numbers, _, and - only): ' );
				$project_question->setValidator( '\WPSnapshots\Utils\slug_validator' );

				$project = $helper->ask( $input, $output, $project_question );
			}

			$description = $input->getOption( 'description' );

			if ( ! isset( $description ) ) {
				$description_question = new Question( 'Snapshot Description (e.g. Local environment): ' );
				$description_question->setValidator( '\WPSnapshots\Utils\not_empty_validator' );

				$description = $helper->ask( $input, $output, $description_question );
			}

			$exclude = $input->getOption( 'exclude' );

			if ( ! empty( $input->getOption( 'exclude_uploads' ) ) ) {
				$exclude[] = './uploads';
			}

			if ( empty( $input->getOption( 'include_files' ) ) ) {
				$files_question = new ConfirmationQuestion( 'Include files in snapshot? (Y/n) ', true );

				$include_files = $helper->ask( $input, $output, $files_question );
			} else {
				$include_files = true;
			}

			if ( empty( $input->getOption( 'include_db' ) ) ) {
				$db_question = new ConfirmationQuestion( 'Include database in snapshot? (Y/n) ', true );

				$include_db = $helper->ask( $input, $output, $db_question );
			} else {
				$include_db = true;
			}

			if ( empty( $include_files ) && empty( $include_db ) ) {
				Log::instance()->write( 'A snapshot must include either a database or a snapshot.', 0, 'error' );
				return 1;
			}

			$scrub = $input->getOption( 'scrub' );

			if ( $input->getOption( 'no_scrub' ) ) {
				$scrub = 0;
			}

			$snapshot = Snapshot::create(
				[
					'path'           => $path,
					'db_host'        => $input->getOption( 'db_host' ),
					'db_name'        => $input->getOption( 'db_name' ),
					'db_user'        => $input->getOption( 'db_user' ),
					'db_password'    => $input->getOption( 'db_password' ),
					'project'        => $project,
					'description'    => $description,
					'scrub'          => (int) $scrub,
					'small'          => $input->getOption( 'small' ),
					'exclude'        => $exclude,
					'repository'     => $repository->getName(),
					'contains_db'    => $include_db,
					'contains_files' => $include_files,
				], $output, $verbose
			);
		}

		if ( ! is_a( $snapshot, '\WPSnapshots\Snapshot' ) ) {
			return 1;
		}

		if ( $snapshot->push() ) {
			Log::instance()->write( 'Push finished!' . ( empty( $snapshot_id ) ? ' Snapshot ID is ' . $snapshot->id : '' ), 0, 'success' );
		}
	}
}
