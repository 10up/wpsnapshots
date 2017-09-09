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
	 * Connect to S3/DynamoDB given a profle
	 *
	 * @param  string $profile
	 * @return bool|Error
	 */
	public function connect( $profile ) {
		$connection_config = $this->getConfig( $profile );

		if ( Utils\is_error( $connection_config ) ) {
			return $connection_config;
		}

		$creds = [
			'access_key_id' => $connection_config['access_key_id'],
			'secret_access_key' => $connection_config['secret_access_key'],
		];

		$this->s3 = new S3( $creds, $profile );

		$this->db = new DB( $creds, $profile, $connection_config['region'] );

		return true;
	}

	/**
	 * Write connection config to ~/.wpprojects.json
	 *
	 * @param  array $connection_config
	 */
	public function writeConfig( $connection_config ) {
		file_put_contents( $_SERVER['HOME'] . '/.wpprojects.json', json_encode( $connection_config ) );
	}

	/**
	 * Get current connection config if it exists
	 *
	 * @param  string $profile
	 * @return array|Error
	 */
	public function getConfig( $profile = null ) {
		if ( ! file_exists( $_SERVER['HOME'] . '/.wpprojects.json' ) ) {
			return new Error( 0, 'No json file exists.' );
		}

		$connection_config_file = json_decode( file_get_contents( $_SERVER['HOME'] . '/.wpprojects.json' ), true );

		if ( ! empty( $profile ) ) {
			if ( empty( $connection_config_file[ $profile ] ) ) {
				return new Error( 1, 'Profile does not exist' );
			} else {
				$connection_config_file = $connection_config_file[ $profile ];
			}
		}

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
