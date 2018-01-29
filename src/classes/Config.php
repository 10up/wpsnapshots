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
		file_put_contents( $this->path() , json_encode( $config ) );
	}

	/**
	 * Get current wp snapshots config if it exists
	 *
	 * @return array|Error
	 */
	public function get() {
		if ( ! file_exists( $this->path()  ) ) {
			return new Error( 0, 'No json file exists.' );
		}

		$snapshots_config_file = json_decode( file_get_contents( $this->path()  ), true );

		return $snapshots_config_file;
	}

	/**
	 * Get path to config.
	 *
	 * @return string Path to config file.
	 */
	public function path() {
		return $_SERVER['HOME'] . '/.wpsnapshots.json';
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
