<?php

namespace WPSnapshots\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Input\ArrayInput;

/**
 * Setup a local environment with wp-local-docker (https://github.com/10up/wp-local-docker).
 */
class CreateEnvironment extends Command {

	/**
	 * Setup up command
	 */
	protected function configure() {
		$this->setName( 'create-environment' );
		$this->setDescription( 'Create a new wp-local-docker env and pull instance' );
		$this->addArgument( 'project-name', InputArgument::REQUIRED, 'Name of the project:' );
		$this->addArgument( 'instance-id', InputArgument::REQUIRED, 'Snapshot ID to pull.' );
		$this->addOption( 'db_host', null, InputOption::VALUE_OPTIONAL, 'Database host.' );
		$this->addOption( 'db_name', null, InputOption::VALUE_OPTIONAL, 'Database name.' );
		$this->addOption( 'db_user', null, InputOption::VALUE_OPTIONAL, 'Database user.' );
		$this->addOption( 'db_password', null, InputOption::VALUE_OPTIONAL, 'Database password.' );

	}

	/**
	 * Executes the command
	 *
	 * @param  InputInterface  $input
	 * @param  OutputInterface $output
	 */
	protected function execute( InputInterface $input, OutputInterface $output ) {
		$output->writeln( '<comment>Setting up environment!</comment>' );
		$project_name = $input->getArgument( 'project-name' );
		shell_exec( 'git clone git@github.com:10up/wp-local-docker.git '. $project_name );
		chdir( $project_name );
		shell_exec( 'docker-compose up -d' );
		$output->writeln( '<comment>Setting up WordPress!</comment>' );
		shell_exec( 'docker-compose exec -d --user www-data phpfpm wp core download' );
		shell_exec( 'docker-compose exec -d --user www-data phpfpm wp core config --dbhost=mysql --dbname=wordpress --dbuser=root --dbpass=password' );
		$pull_command = $this->getApplication()->find( 'pull' );
		$db_host = $input->getOption( 'db_host' );
		$db_name = $input->getOption( 'db_name' );
		$db_user = $input->getOption( 'db_user' );
		$db_password = $input->getOption( 'db_password' );
		$pull_args = array(
			'instance-id' => $input->getArgument( 'instance-id' ),
			'--db_host' => $db_host,
			'--db_name' => $db_name,
			'--db_user' => $db_user,
			'--db_password' => $db_password,
		);
		$pull_input = new ArrayInput( $pull_args );
		$pull_command->run( $pull_input, $output );
	}
}