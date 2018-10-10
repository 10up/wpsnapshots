<?php

namespace WPSnapshots;

class RepositoryManager {
	/**
	 * Instance of S3 client
	 *
	 * @var \Aws\S3\S3Client
	 */
	private $s3;

	/**
	 * Instance of S3 client
	 *
	 * @var \Aws\DynamoDb\DynamoDbClient
	 */
	private $db;

	/**
	 * Repository slug
	 *
	 * @var string
	 */
	private $repository;

	/**
	 * Repository config
	 *
	 * @var array
	 */
	private $config = [];

	public function connect( $repository = null ) {
		$config = Config::get();

		if ( empty( $config['repositories'] ) ) {
			Log::instance()->write( 'No repositories configured.', 0, 'error' );

			return false;
		}

		if ( ! empty( $repository ) ) {
			if ( empty( $config['repositories'][ $repository ] ) ) {
				Log::instance()->write( 'Repository not configured.', 0, 'error' );

				return false;
			}

			$this->config = $config['repositories'][ $repository ];
		} else {
			$this->config     = array_values( $config['repositories'] )[0];
			$this->repository = $this->config['repository'];
		}


		$this->s3 = new S3( $config );
		$this->db = new DB( $config );
	}

	public static function create() {

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
