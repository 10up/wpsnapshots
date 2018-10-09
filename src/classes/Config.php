<?php
/**
 * Handle global configuration files
 *
 * @package wpsnapshots
 */

namespace WPSnapshots;

use WPSnapshots\Utils;

/**
 * Config class
 */
class Config {
	/**
	 * Singleton
	 */
	private function construct() { }

	/**
	 * Write wp snapshots config to ~/.wpsnapshots.json
	 *
	 * @param array $config Config array.
	 */
	public function write( $config ) {
		file_put_contents( Utils\get_snapshot_directory() . 'config.json', json_encode( $config, JSON_PRETTY_PRINT ) );
	}

	/**
	 * Get current wp snapshots config if it exists. Optionally only get one repository config
	 *
	 * @param  string $repository Optional repository to return. Otherwise returns all repositories.
	 * @return array|Error
	 */
	public function get( $repository = null ) {
		$file_path = Utils\get_snapshot_directory() . 'config.json';

		if ( ! file_exists( $file_path ) ) {
			/**
			 * Backwards compat for old config file path
			 */
			$file_path = $_SERVER['HOME'] . '/.wpsnapshots.json';

			if ( ! file_exists( $file_path ) ) {
				return new Error( 0, 'No json file exists.' );
			} else {
				rename( $file_path, Utils\get_snapshot_directory() . 'config.json' );

				$file_path = Utils\get_snapshot_directory() . 'config.json';
			}
		}

		$config = json_decode( file_get_contents( $file_path ), true );

		// Backwards compat - move to "repositories" as base key
		if ( ! empty( $config ) && ! empty( $config['repository'] ) ) {
			$new_config = [
				'repositories' => [
					$config['repository'] => $config,
				],
			];

			$config = $new_config;

			$this->write( $config );
		}

		if ( empty( $config['repositories'] ) ) {
			return new Error( 1, 'Configuration empty.' );
		}

		if ( ! empty( $repository ) ) {
			if ( ! empty( $config['repositories'][ $repository ] ) ) {
				$config = $config['repositories'][ $repository ];
			} else {
				return new Error( 2, 'Repository not configured.' );
			}
		}

		return $config;
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
