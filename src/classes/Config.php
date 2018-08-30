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
	 * @param  array $config
	 */
	public function write( $config ) {
		file_put_contents( Utils\get_snapshot_directory() . '/config.json', json_encode( $config ) );
	}

	/**
	 * Get current wp snapshots config if it exists
	 *
	 * @return array|Error
	 */
	public function get() {
		$file_path = Utils\get_snapshot_directory() . '/config.json';

		if ( ! file_exists( $file_path ) ) {
			/**
			 * Backwards compat for old config file path
			 */
			$file_path = $_SERVER['HOME'] . '/.wpsnapshots.json';

			if ( ! file_exists( $file_path ) ) {
				return new Error( 0, 'No json file exists.' );
			}
		}

		$snapshots_config_file = json_decode( file_get_contents( $file_path ), true );

		return $snapshots_config_file;
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
