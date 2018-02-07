<?php

namespace WPSnapshots;

use \Aws\S3\S3Client;
use \Aws\Exception\AwsException;
use WPSnapshots\Utils;

class S3 {
	private $client;
	private $repository;
	private $region;

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
            'signature' => 'v4',
            'region' => $config['region']
		] );

		$this->repository = $config['repository'];
		$this->region = $config['region'];
	}

	/**
	 * Upload a snapshot to S3 given a path to files.tar.gz and data.sql.gz
	 *
	 * @param  string $id         Snapshot ID
	 * @param  string $project    Project slug
	 * @param  string $db_path    Path to data.sql.gz
	 * @param  string $files_path Path to files.tar.gz
	 * @return bool|error
	 */
	public function putSnapshot( $id, $project, $db_path, $files_path ) {
		try {
			$db_result = $this->client->putObject( [
				'Bucket'     => self::getBucketName( $this->repository ),
				'Key'        => $project . '/' . $id . '/data.sql.gz',
				'SourceFile' => realpath( $db_path ),
			] );

			$files_result = $this->client->putObject( [
				'Bucket'     => self::getBucketName( $this->repository ),
				'Key'        => $project . '/' . $id . '/files.tar.gz',
				'SourceFile' => realpath( $files_path ),
			] );

			/**
			 * Wait for files first since that will probably take longer
			 */
			$this->client->waitUntil( 'ObjectExists', [
				'Bucket' => self::getBucketName( $this->repository ),
				'Key'    => $project . '/' . $id . '/files.tar.gz',
			] );

			$this->client->waitUntil( 'ObjectExists', [
				'Bucket' => self::getBucketName( $this->repository ),
				'Key'    => $project . '/' . $id . '/data.sql.gz',
			] );
		} catch ( \Exception $e ) {
			$error = [
				'message'        => $e->getMessage(),
				'aws_request_id' => $e->getAwsRequestId(),
				'aws_error_type' => $e->getAwsErrorType(),
				'aws_error_code' => $e->getAwsErrorCode(),
			];

			return new Error( 0, $error );
		}

		return true;
	}

	/**
	 * Download a snapshot given an id. Must specify where to download files/data
	 *
	 * @param  string $id         Snapshot id
	 * @param  string $project    Project slug
	 * @param  string $db_path    Where to download data.sql.gz
	 * @param  string $files_path Where to download files.tar.gz
	 * @return array|error
	 */
	public function downloadSnapshot( $id, $project, $db_path, $files_path ) {
		try {
			$db_download = $this->client->getObject( [
			    'Bucket' => self::getBucketName( $this->repository ),
			    'Key'    => $project . '/' . $id . '/data.sql.gz',
			    'SaveAs' => $db_path,
			] );

			$files_download = $this->client->getObject( [
			    'Bucket' => self::getBucketName( $this->repository ),
			    'Key'    => $project . '/' . $id . '/files.tar.gz',
			    'SaveAs' => $files_path,
			] );
		} catch ( \Exception $e ) {
			$error = [
				'message'        => $e->getMessage(),
				'aws_request_id' => $e->getAwsRequestId(),
				'aws_error_type' => $e->getAwsErrorType(),
				'aws_error_code' => $e->getAwsErrorCode(),
			];

			return new Error( 0, $error );
		}

		return true;
	}

	/**
	 * Delete a snapshot given an id
	 *
	 * @param  string $id Snapshot id
	 * @param  string $project
	 * @return bool|error
	 */
	public function deleteSnapshot( $id, $project ) {
		try {
			$result = $this->client->deleteObjects( [
				'Bucket' => self::getBucketName( $this->repository ),
				'Objects' => [
					[
						'Key' => $project . '/' . $id . '/files.tar.gz',
					],
					[
						'Key' => $project . '/' . $id . '/data.sql',
					],
					[
						'Key' => $project . '/' . $id . '/data.sql.gz',
					],
				],
			] );
		} catch ( \Exception $e ) {
			$error = [
				'message'        => $e->getMessage(),
				'aws_request_id' => $e->getAwsRequestId(),
				'aws_error_type' => $e->getAwsErrorType(),
				'aws_error_code' => $e->getAwsErrorCode(),
			];

			return new Error( 0, $error );
		}

		return true;
	}

	public static function getBucketName( $repository ) {
		return 'wpsnapshots-' . $repository;
	}

	/**
	 * Test S3 connection by attempting to list S3 objects.
	 *
	 * @param  array $creds
	 * @return bool|Error
	 */
	public static function test( $config ) {
		$client = S3Client::factory( [
			'credentials' => [
				'key'    => $config['access_key_id'],
				'secret' => $config['secret_access_key'],
			],
		] );

		$bucket_name = self::getBucketName( $config['repository'] );

		try {
			$objects = $client->listObjects( [ 'Bucket' => $bucket_name ] );
		} catch ( \Exception $e ) {
			$error = [
				'message'        => $e->getMessage(),
				'aws_request_id' => $e->getAwsRequestId(),
				'aws_error_type' => $e->getAwsErrorType(),
				'aws_error_code' => $e->getAwsErrorCode(),
			];

			return new Error( 0, $error );
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
			$error = [
				'message'        => $e->getMessage(),
				'aws_request_id' => $e->getAwsRequestId(),
				'aws_error_type' => $e->getAwsErrorType(),
				'aws_error_code' => $e->getAwsErrorCode(),
			];

			return new Error( 0, $error );
		}

		$bucket_name = self::getBucketName( $this->repository );

		foreach ( $result['Buckets'] as $bucket ) {
			if ( $bucket_name === $bucket['Name'] ) {
				$bucket_exists = true;
			}
		}

		if ( $bucket_exists ) {
			return new Error( 1, 'Bucket already exists' );
		}

		try {
			$result = $this->client->createBucket( [ 'Bucket' => self::getBucketName( $this->repository ), 'LocationConstraint' => $this->region ] );
		} catch ( \Exception $e ) {
			$error = [
				'message'        => $e->getMessage(),
				'aws_request_id' => $e->getAwsRequestId(),
				'aws_error_type' => $e->getAwsErrorType(),
				'aws_error_code' => $e->getAwsErrorCode(),
			];

			return new Error( 2, $error );
		}

		return true;
	}
}
