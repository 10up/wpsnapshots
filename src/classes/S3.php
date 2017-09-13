<?php

namespace WPSnapshots;

use \Aws\S3\S3Client;

class S3 {
	private $client;
	private $repository;

	/**
	 * Setup S3 client
	 *
	 * @param array $config
	 */
	public function __construct( $config ) {
		$this->client = S3Client::factory( [
			'credentials' => [
				'key'    => $config['access_key_id'],
				'secret' => $config['secret_access_key'],
			],
		] );

		$this->repository = $config['repository'];
	}

	/**
	 * Upload a snapshot to S3 given a path to files.tar.gz and data.sql
	 *
	 * @param  array $snapshot Must contain 'id'
	 * @param  string $db_path         Path to data.sql
	 * @param  string $files_path      Path to files.tar.gz
	 * @return bool|error
	 */
	public function putSnapshot( $snapshot, $db_path, $files_path ) {
		try {
			$db_result = $this->client->putObject( [
				'Bucket'     => self::getBucketName( $this->repository ),
				'Key'        => $snapshot['id'] . '/data.sql',
				'SourceFile' => realpath( $db_path ),
				'Metadata'   => [
					'project' => $snapshot['project'],
				],
			] );

			$files_result = $this->client->putObject( [
				'Bucket'     => self::getBucketName( $this->repository ),
				'Key'        => $snapshot['id'] . '/files.tar.gz',
				'SourceFile' => realpath( $files_path ),
				'Metadata'   => [
					'project' => $snapshot['project'],
				],
			] );

			/**
			 * Wait for files first since that will probably take longer
			 */
			$this->client->waitUntil( 'ObjectExists', [
				'Bucket' => self::getBucketName( $this->repository ),
				'Key'    => $snapshot['id'] . '/files.tar.gz',
			] );

			$this->client->waitUntil( 'ObjectExists', [
				'Bucket' => self::getBucketName( $this->repository ),
				'Key'    => $snapshot['id'] . '/data.sql',
			] );
		} catch ( \Exception $e ) {
			var_dump($e);
			return new Error( 0 );
		}

		return true;
	}

	/**
	 * Download a snapshot given an id. Must specify where to download files/data
	 *
	 * @param  string $id         Snapshot id
	 * @param  string $db_path    Where to download data.sql
	 * @param  string $files_path Where to download files.tar.gz
	 * @return bool|error
	 */
	public function downloadSnapshot( $id, $db_path, $files_path ) {
		try {
			$db_download = $this->client->getObject( [
			    'Bucket' => self::getBucketName( $this->repository ),
			    'Key'    => $id . '/data.sql',
			    'SaveAs' => $db_path,
			] );

			$files_download = $this->client->getObject( [
			    'Bucket' => self::getBucketName( $this->repository ),
			    'Key'    => $id . '/files.tar.gz',
			    'SaveAs' => $files_path,
			] );
		} catch ( \Exception $e ) {
			var_dump($e);
			echo $e->getMessage();
			return new Error( 0 );
		}

		return true;
	}

	/**
	 * Delete a snapshot given an id
	 *
	 * @param  string $id Snapshot id
	 * @return bool|error
	 */
	public function deleteSnapshot( $id ) {
		try {
			$result = $this->client->deleteObjects( [
				'Bucket' => self::getBucketName( $this->repository ),
				'Objects' => [
					[
						'Key' => $id . '/files.tar.gz',
					],
					[
						'Key' => $id . '/data.sql',
					]
				],
			] );
		} catch ( \Exception $e ) {
			return new Error( 0 );
		}

		return true;
	}

	public static function getBucketName( $repository ) {
		return 'wpsnapshots-' . $repository . '-' . substr( md5( $repository ), 0, 6 );
	}

	/**
	 * Test S3 connection by attempting to list S3 buckets and write a test file.
	 *
	 * @param  array $creds
	 * @return bool|Error
	 */
	public static function test( $config ) {
		$client = S3Client::factory( [
			'credentials' => [
				'key'    => $config['access_key_id'],
				'secret' => $config['secret_access_key'],
			]
		] );

		try {
			$result = $client->listBuckets();
		} catch ( \Exception $e ) {
			return new Error( 0, 'Connection could not be established' );
		}

		$bucket_name = self::getBucketName( $config['repository'] );

		$bucket_found = false;

		foreach ( $result['Buckets'] as $bucket ) {
			if ( $bucket_name === $bucket['Name'] ) {
				$bucket_found = true;
			}
		}

		if ( ! $bucket_found ) {
			return new Error( 1, 'Bucket not found' );
		}

		return true;
	}

	/**
	 * Create WPSnapshots S3 bucket
	 *
	 * @return bool|Error
	 */
	public function createBucket() {
		$bucket_exists = false;

		try {
			$result = $this->client->listBuckets();
		} catch ( \Exception $e ) {
			return new Error( 0, 'Could not create bucket' );
		}

		$bucket_name = self::getBucketName( $this->repository );

		foreach ( $result['Buckets'] as $bucket ) {
			if ( $bucket_name === $bucket['Name'] ) {
				$bucket_exists = true;
			}
		}

		if ( ! $bucket_exists ) {
			try {
				$result = $this->client->createBucket( [ 'Bucket' => self::getBucketName( $this->repository ) ] );
			} catch ( \Exception $e ) {
				if ( 'BucketAlreadyOwnedByYou' === $e->getAwsErrorCode() || 'BucketAlreadyExists' === $e->getAwsErrorCode() ) {
					$bucket_exists = true;
				} else {
					return new Error( 0, 'Could not create bucket' );
				}
			}
		}

		if ( $bucket_exists ) {
			try {
				$test_key = time();

				$this->client->putObject( [
					'Bucket' => self::getBucketName( $this->repository ),
					'Key'    => 'test' . $test_key,
					'Body'   => 'Test write'
				] );

				$this->client->waitUntil( 'ObjectExists', [
					'Bucket' => self::getBucketName( $this->repository ),
					'Key'    => 'test' . $test_key,
				] );
			} catch( \Exception $e ) {
				return new Error( 2, 'Cant write to bucket' );
			}

			$this->client->deleteObjects( [
				'Bucket' => self::getBucketName( $this->repository ),
				'Objects' => [
					[
						'Key' => 'test' . $test_key,
					],
				],
			] );

			return new Error( 1, 'Bucket already exists' );
		}

		return true;
	}
}
