<?php

namespace WPSnapshots;

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
		file_put_contents( $_SERVER['HOME'] . '/.wpsnapshots.json', json_encode( $config ) );
	}

	/**
	 * Get current wp snapshots config if it exists
	 *
	 * @return array|Error
	 */
	public function get() {
		if ( ! file_exists( $_SERVER['HOME'] . '/.wpsnapshots.json' ) ) {
			return new Error( 0, 'No json file exists.' );
		}

		$snapshots_config_file = json_decode( file_get_contents( $_SERVER['HOME'] . '/.wpsnapshots.json' ), true );

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
			$instance = new self;
		}

		return $instance;
	}
}
