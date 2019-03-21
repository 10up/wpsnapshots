<?php
/**
 * Manage repositories
 *
 * @package wpsnapshots
 */

namespace WPSnapshots;

use WPSnapshots\Utils;

/**
 * Class for managing repos
 */
class RepositoryManager {

	/**
	 * Configuration
	 *
	 * @var Config
	 */
	private $config;

	/**
	 * Setup repositories
	 *
	 * @var array
	 */
	private $repositories = [];

	/**
	 * Set up a repository. This does not test the connection.
	 *
	 * @param  string $repository_name Name of repo to setup.
	 * @return Repository|bool
	 */
	public function setup( $repository_name = null ) {
		if ( empty( $this->config['repositories'] ) ) {
			Log::instance()->write( 'No repositories in configuration.', 1 );

			return false;
		}

		if ( empty( $repository_name ) ) {
			$repository_name = $this->getDefault();
		}

		if ( ! empty( $this->repositories[ $repository_name ] ) ) {
			return $this->repositories[ $repository_name ];
		}

		if ( empty( $this->config['repositories'][ $repository_name ] ) ) {
			Log::instance()->write( 'Repository not in configuration.', 1 );

			return false;
		}

		$repo_config = $this->config['repositories'][ $repository_name ];

		$repository = new Repository( $repository_name, $repo_config['access_key_id'], $repo_config['secret_access_key'], $repo_config['region'] );

		$this->repositories[ $repository_name ] = $repository;

		Log::instance()->write( 'Setup repository: ' . $repository_name );

		return $repository;
	}

	/**
	 * Get default repository
	 *
	 * @return string|bool
	 */
	public function getDefault() {
		if ( empty( $this->config['repositories'] ) ) {
			return false;
		}

		$repos = $this->config['repositories'];

		if ( 1 === count( $repos ) && ! empty( $repos['local'] ) ) {
			return 'local';
		}

		if ( ! empty( $repos['local'] ) ) {
			unset( $repos['local'] );
		}

		$repos = array_values( $repos );

		return $repos[0]['repository'];
	}

	/**
	 * Setup repo manager
	 */
	private function __construct() {
		$this->config = Config::get();

		// Add local repository
		$repositories = [];

		if ( empty( $this->config ) || empty( $this->repositories ) ) {
			$repositories = $this->config['repositories'];
		}

		$repositories['local'] = [
			'repository'        => 'local',
			'access_key_id'     => '',
			'secret_access_key' => '',
			'region'            => '',
		];

		$this->config['repositories'] = $repositories;
	}

	/**
	 * Get config
	 *
	 * @return Config
	 */
	public function getConfig() {
		return $this->config;
	}

	/**
	 * Get author info
	 *
	 * @return array|bool
	 */
	public function getAuthorInfo() {
		if ( empty( $this->config ) ) {
			return false;
		}

		return [
			'name'  => $this->config['name'],
			'email' => $this->config['email'],
		];
	}

	/**
	 * Return singleton instance of class
	 *
	 * @return object
	 */
	public static function instance() {
		static $instance;

		if ( empty( $instance ) ) {
			$instance = new self();
		}

		return $instance;
	}
}
