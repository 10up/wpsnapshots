<?php

namespace WPProjects;

use WPProjects\Utils;

class ConnectionManager {

	/**
	 * Instance of S3 client
	 *
	 * @var \Aws\S3\S3Client
	 */
	public $s3 = null;

	/**
	 * Instance of S3 client
	 *
	 * @var \Aws\DynamoDb\DynamoDbClient
	 */
	public $db = null;

	/**
	 * Singleton
	 */
	private function construct() { }

	/**
	 * Connect to S3/DynamoDB
	 *
	 * @return bool|Error
	 */
	public function connect() {
		$config = $this->getConfig();

		if ( Utils\is_error( $config ) ) {
			return $config;
		}

		$this->s3 = new S3( $config );

		$this->db = new DB( $config );

		return true;
	}

	/**
	 * Write connection config to ~/.wpprojects.json
	 *
	 * @param  array $config
	 */
	public function writeConfig( $config ) {
		file_put_contents( $_SERVER['HOME'] . '/.wpprojects.json', json_encode( $config ) );
	}

	/**
	 * Get current connection config if it exists
	 *
	 * @return array|Error
	 */
	public function getConfig() {
		if ( ! file_exists( $_SERVER['HOME'] . '/.wpprojects.json' ) ) {
			return new Error( 0, 'No json file exists.' );
		}

		$connection_config_file = json_decode( file_get_contents( $_SERVER['HOME'] . '/.wpprojects.json' ), true );

		return $connection_config_file;
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
