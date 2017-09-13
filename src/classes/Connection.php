<?php

namespace WPSnapshots;

use WPSnapshots\Utils;

class Connection {

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
		$config = Config::instance()->get();

		if ( Utils\is_error( $config ) ) {
			return $config;
		}

		$this->s3 = new S3( $config );

		$this->db = new DB( $config );

		return true;
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
