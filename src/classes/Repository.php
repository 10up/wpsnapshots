<?php
/**
 * A single repository
 *
 * @package wpsnapshots
 */

namespace WPSnapshots;

/**
 * A repository
 */
class Repository {

	/**
	 * Instance of S3 client
	 *
	 * @var S3
	 */
	private $s3;

	/**
	 * Instance of S3 client
	 *
	 * @var DB
	 */
	private $db;

	/**
	 * Repository config
	 *
	 * @var array
	 */
	private $name;

	/**
	 * Setup a new repository
	 *
	 * @param  string $name Name of repo
	 * @param  string $access_key_id AWS access key
	 * @param  string $secret_access_key AWS secret access key
	 * @param  string $region AWS region
	 */
	public function __construct( $name, $access_key_id, $secret_access_key, $region ) {
		$this->name = $name;

		$this->s3 = new S3( $name, $access_key_id, $secret_access_key, $region );

		$this->db = new DB( $name, $access_key_id, $secret_access_key, $region );
	}

	/**
	 * Get DB client
	 *
	 * @return DB
	 */
	public function getDB() {
		return $this->db;
	}

	/**
	 * Get S3 client
	 *
	 * @return S3
	 */
	public function getS3() {
		return $this->s3;
	}

	/**
	 * Get repository name
	 *
	 * @return string
	 */
	public function getName() {
		return $this->name;
	}
}
