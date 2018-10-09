<?php
/**
 * Manage connection to Amazon
 *
 * @package wpsnapshots
 */

namespace WPSnapshots;

use WPSnapshots\Utils;

/**
 * Class for handling connection to S3 and DynamoDB
 */
class Connection {

	/**
	 * Instance of S3 client
	 *
	 * @var \Aws\S3\S3Client
	 */
	public $s3;

	/**
	 * Instance of S3 client
	 *
	 * @var \Aws\DynamoDb\DynamoDbClient
	 */
	public $db;

	/**
	 * Repository config
	 *
	 * @var array
	 */
	public $config;

	/**
	 * Singleton
	 */
	private function construct() { }

	/**
	 * Connect to S3/DynamoDB
	 *
	 * @param  string $repository Optional repository to use. Defaults to first repo in config.
	 * @return bool|Error
	 */
	public function connect( $repository = null ) {
		$config = Config::instance()->get( $repository );

		if ( empty( $repository ) ) {
			$config = array_values( $config['repositories'] );
			$config = $config[0];
		}

		if ( Utils\is_error( $config ) ) {
			return $config;
		}

		$this->config = $config;

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
			$instance = new self();
		}

		return $instance;
	}
}
