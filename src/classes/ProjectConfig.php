<?php

namespace WPProjects;
use WPProjects\Error;

class ProjectConfig {

	/**
	 * Singleton
	 */
	private function __construct() { }

	/**
	 * Write configuration to the wpproject.json file
	 *
	 * @param  array $config
	 */
	public function write( $config ) {
		file_put_contents( getcwd() . '/wpproject.json', json_encode( $config ) );
	}

	/**
	 * Get config from the wpproject.json file if it exists
	 *
	 * @param  array $config
	 */
	public function get() {
		if ( file_exists( getcwd() . '/wpproject.json' ) ) {
			$config = json_decode( file_get_contents( getcwd() . '/wpproject.json' ), true );
		} else {
			$config = false;
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
			$instance = new self;
		}

		return $instance;
	}
}
