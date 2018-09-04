<?php

namespace WPSnapshots;

class Config {
	const VERSION = '2';

	/**
	 * Repository name.
	 *
	 * @var string
	 */
	protected $repository;

	/**
	 * Config
	 *
	 * @var array
	 */
	protected $config;

	/**
	 * Constructor
	 *
	 * @param string $repository The repository name to retrieve the config for.
	 */
	public function __construct( $repository = '' ) {
		$this->repository = $repository;

		$this->config = array_reduce( self::get_repositories(), function( $config, $repo ) use ( $repository ) {
			if ( ! is_array( $config ) && $repository === $repo['repository'] ) {
				$config = $repo;
			}
			return $config;
		}, new Error( 0, 'No configuration exists.' ) );
	}

	/**
	 * Write repository config.
	 *
	 * @param  array $config The repository config to write.
	 * @return void
	 */
	public function write( $config ) {
		$config_file = self::get_config_file();

		$repositories = $config_file['repositories'];

		// Remove matching repository config.
		if ( ! Utils\is_error( $this->config ) ) {
			$repositories = array_filter( $repositories, function( $repository ) use ( $config ) {
				return $repository['repository'] !== $config['repository'];
			} );
		}

		// Add new config to repositories array.
		array_push( $repositories, $config );

		// Overwrite config.
		$config_file['repositories'] = $repositories;
		self::write_config_file( $config_file );
	}

	/**
	 * Get repository config.
	 *
	 * @return array Repository config.
	 */
	public function get() {
		return $this->config;
	}

	/**
	 * Get current wp snapshots config if it exists
	 *
	 * @return array|Error
	 */
	protected static function get_config_file() {
		if ( ! file_exists( $_SERVER['HOME'] . '/.wpsnapshots.json' ) ) {
			return new Error( 0, 'No json file exists.' );
		}

		$snapshots_config_file = json_decode( file_get_contents( $_SERVER['HOME'] . '/.wpsnapshots.json' ), true );

		if ( ! self::check_version( $snapshots_config_file ) ) {
			return self::upgrade( $snapshots_config_file );
		}

		return $snapshots_config_file;
	}


	/**
	 * Write wpsnapshots config to ~/.wpsnapshots.json
	 *
	 * @param  array $config_file
	 */
	protected static function write_config_file( $config_file ) {
		file_put_contents( $_SERVER['HOME'] . '/.wpsnapshots.json', json_encode( $config_file ) );
	}

	/**
	 * Get repository configs.
	 *
	 * @return array An array of repository configurations.
	 */
	protected static function get_repositories() {
		$config = self::get_config_file();

		if ( ! isset( $config['repositories'] ) ) {
			return [];
		}

		return $config['repositories'];
	}

	/**
	 * Check version.
	 *
	 * @param  array $file_config The wpsnapshots config.
	 * @return bool               True if we're on a current version, false otherwise.
	 */
	protected static function check_version( $file_config ) {
		if ( ! isset( $file_config['version'] ) ) {
			return false;
		}

		if ( version_compare( $file_config['version'], self::VERSION ) >= 0 ) {
			return true;
		}

		return false;
	}

	/**
	 * Upgrade.
	 *
	 * @param  array $file_config The wpsnapshots config.
	 * @return array              An upgrade wpsnapshots config.
	 */
	protected static function upgrade( $file_config ) {
		// Upgrade from v1 to v2.
		if ( ! isset( $file_config['version'] ) ) {
			$version2 = [
				'version' => '2',
			];

			// If we're upgrading from v1 to v2, push v1's full configuration onto the repositories array.
			$version2['repositories'] = $file_config ? [ $file_config ] : [];

			// Write the upgraded config to disk.
			file_put_contents( $_SERVER['HOME'] . '/.wpsnapshots.json', json_encode( $version2 ) );

			return self::get_config_file();
		}
	}
}
