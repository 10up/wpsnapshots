<?php

namespace WPSnapshots;

use WPSnapshots\Utils;

class Connection {

	public $config = null;

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

	public function __construct( $repository ) {
		$this->config = new Config( $repository );
	}

	/**
	 * Connect to S3/DynamoDB
	 *
	 * @return bool|Error
	 */
	public function connect() {
		$config = $this->config->get();

		if ( Utils\is_error( $config ) ) {
			return $config;
		}

		$this->s3 = new S3( $config );

		$this->db = new DB( $config );

		return true;
	}
}
