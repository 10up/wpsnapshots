<?php
/**
 * S3 wrapper functionality
 *
 * @package wpsnapshots
 */

namespace WPSnapshots;

use \Aws\S3\S3Client;
use \Aws\Exception\AwsException;
use WPSnapshots\Utils;

/**
 * Handle calls to Amazon S3
 */
class S3 {
	/**
	 * S3 client
	 *
	 * @var S3Client
	 */
	private $client;

	/**
	 * Repository name
	 *
	 * @var string
	 */
	private $repository;

	/**
	 * AWS access key id
	 *
	 * @var string
	 */
	private $access_key_id;

	/**
	 * AWS secret access key
	 *
	 * @var string
	 */
	private $secret_access_key;

	/**
	 * AWS region
	 *
	 * @var  string
	 */
	private $region;

	/**
	 * Create S3 client
	 *
	 * @param  string $repository Name of repo
	 * @param  string $access_key_id AWS access key
	 * @param  string $secret_access_key AWS secret access key
	 * @param  string $region AWS region
	 */
	public function __construct( $repository, $access_key_id, $secret_access_key, $region ) {
		$this->repository        = $repository;
		$this->access_key_id     = $access_key_id;
		$this->secret_access_key = $secret_access_key;
		$this->region            = $region;

		$this->client = S3Client::factory(
			[
				'credentials' => [
					'key'    => $access_key_id,
					'secret' => $secret_access_key,
				],
				'signature'   => 'v4',
				'region'      => $region,
				'version'     => '2006-03-01',
			]
		);
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
			$db_result = ProgressBarManager::instance()->wrapAWSOperation(
				$this->client,
				'putObject',
				[
					'Bucket'     => self::getBucketName( $this->repository ),
					'Key'        => $project . '/' . $id . '/data.sql.gz',
					'SourceFile' => realpath( $db_path ),
				]
			);

			$files_result = ProgressBarManager::instance()->wrapAWSOperation(
				$this->client,
				'putObject',
				[
					'Bucket'     => self::getBucketName( $this->repository ),
					'Key'        => $project . '/' . $id . '/files.tar.gz',
					'SourceFile' => realpath( $files_path ),
				]
			);

			/**
			 * Wait for files first since that will probably take longer
			 */
			$this->client->waitUntil(
				'ObjectExists',
				[
					'Bucket' => self::getBucketName( $this->repository ),
					'Key'    => $project . '/' . $id . '/files.tar.gz',
				]
			);

			$this->client->waitUntil(
				'ObjectExists',
				[
					'Bucket' => self::getBucketName( $this->repository ),
					'Key'    => $project . '/' . $id . '/data.sql.gz',
				]
			);
		} catch ( \Exception $e ) {
			if ( ! empty( $files_result ) && 'AccessDenied' === $files_result->data['aws_error_code'] ) {
				Log::instance()->write( 'Access denied. You might not have access to this project.', 0, 'error' );
			}

			Log::instance()->write( 'Error Message: ' . $e->getMessage(), 1, 'error' );
			Log::instance()->write( 'AWS Request ID: ' . $e->getAwsRequestId(), 1, 'error' );
			Log::instance()->write( 'AWS Error Type: ' . $e->getAwsErrorType(), 1, 'error' );
			Log::instance()->write( 'AWS Error Code: ' . $e->getAwsErrorCode(), 1, 'error' );

			return false;
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
			Log::instance()->write( 'Downloading database...' );
			$db_download = ProgressBarManager::instance()->wrapAWSOperation(
				$this->client,
				'getObject',
				[
					'Bucket' => self::getBucketName( $this->repository ),
					'Key'    => $project . '/' . $id . '/data.sql.gz',
					'SaveAs' => $db_path,
				]
			);

			Log::instance()->write( 'Downloading files...' );
			$files_download = ProgressBarManager::instance()->wrapAWSOperation(
				$this->client,
				'getObject',
				[
					'Bucket' => self::getBucketName( $this->repository ),
					'Key'    => $project . '/' . $id . '/files.tar.gz',
					'SaveAs' => $files_path,
				]
			);
		} catch ( \Exception $e ) {
			if ( 'AccessDenied' === $e->getAwsErrorCode() ) {
				Log::instance()->write( 'Access denied. You might not have access to this snapshot.', 0, 'error' );
			}

			Log::instance()->write( 'Error Message: ' . $e->getMessage(), 1, 'error' );
			Log::instance()->write( 'AWS Request ID: ' . $e->getAwsRequestId(), 1, 'error' );
			Log::instance()->write( 'AWS Error Type: ' . $e->getAwsErrorType(), 1, 'error' );
			Log::instance()->write( 'AWS Error Code: ' . $e->getAwsErrorCode(), 1, 'error' );

			return false;
		}

		return true;
	}

	/**
	 * Delete a snapshot given an id
	 *
	 * @param  string $id Snapshot id
	 * @param  string $project Project name
	 * @return bool|error
	 */
	public function deleteSnapshot( $id, $project ) {
		try {
			$result = $this->client->deleteObjects(
				[
					'Bucket' => self::getBucketName( $this->repository ),
					'Delete' => [
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
					],
				]
			);
		} catch ( \Exception $e ) {
			if ( 'AccessDenied' === $s3_add->data['aws_error_code'] ) {
				Log::instance()->write( 'Access denied. You might not have access to this snapshot.', 0, 'error' );
			}

			Log::instance()->write( 'Error Message: ' . $e->getMessage(), 1, 'error' );
			Log::instance()->write( 'AWS Request ID: ' . $e->getAwsRequestId(), 1, 'error' );
			Log::instance()->write( 'AWS Error Type: ' . $e->getAwsErrorType(), 1, 'error' );
			Log::instance()->write( 'AWS Error Code: ' . $e->getAwsErrorCode(), 1, 'error' );

			return false;
		}

		return true;
	}

	/**
	 * Get bucket name
	 *
	 * @param  string $repository Repository name
	 * @return string
	 */
	public static function getBucketName( $repository ) {
		return 'wpsnapshots-' . $repository;
	}

	/**
	 * Test S3 connection by attempting to list S3 objects.
	 *
	 * @param  array $config Config array
	 * @return bool|integer
	 */
	public static function test( $config ) {
		$client = S3Client::factory(
			[
				'credentials' => [
					'key'    => $config['access_key_id'],
					'secret' => $config['secret_access_key'],
				],
				'signature'   => 'v4',
				'region'      => $config['region'],
				'version'     => '2006-03-01',
			]
		);

		$bucket_name = self::getBucketName( $config['repository'] );

		try {
			$objects = $client->listObjects( [ 'Bucket' => $bucket_name ] );
		} catch ( \Exception $e ) {
			Log::instance()->write( 'Error Message: ' . $e->getMessage(), 1, 'error' );
			Log::instance()->write( 'AWS Request ID: ' . $e->getAwsRequestId(), 1, 'error' );
			Log::instance()->write( 'AWS Error Type: ' . $e->getAwsErrorType(), 1, 'error' );
			Log::instance()->write( 'AWS Error Code: ' . $e->getAwsErrorCode(), 1, 'error' );

			return $e->getAwsErrorCode();
		}

		return true;
	}

	/**
	 * Create WP Snapshots S3 bucket
	 *
	 * @return bool|string
	 */
	public function createBucket() {
		$bucket_exists = false;

		try {
			$result = $this->client->listBuckets();
		} catch ( \Exception $e ) {
			Log::instance()->write( 'Error Message: ' . $e->getMessage(), 1, 'error' );
			Log::instance()->write( 'AWS Request ID: ' . $e->getAwsRequestId(), 1, 'error' );
			Log::instance()->write( 'AWS Error Type: ' . $e->getAwsErrorType(), 1, 'error' );
			Log::instance()->write( 'AWS Error Code: ' . $e->getAwsErrorCode(), 1, 'error' );

			return $e->getAwsErrorCode();
		}

		$bucket_name = self::getBucketName( $this->repository );

		foreach ( $result['Buckets'] as $bucket ) {
			if ( $bucket_name === $bucket['Name'] ) {
				$bucket_exists = true;
			}
		}

		if ( $bucket_exists ) {
			return 'BucketExists';
		}

		try {
			$result = $this->client->createBucket(
				[
					'Bucket'             => self::getBucketName( $this->repository ),
					'LocationConstraint' => $this->region,
				]
			);
		} catch ( \Exception $e ) {
			Log::instance()->write( 'Error Message: ' . $e->getMessage(), 1, 'error' );
			Log::instance()->write( 'AWS Request ID: ' . $e->getAwsRequestId(), 1, 'error' );
			Log::instance()->write( 'AWS Error Type: ' . $e->getAwsErrorType(), 1, 'error' );
			Log::instance()->write( 'AWS Error Code: ' . $e->getAwsErrorCode(), 1, 'error' );

			return $e->getAwsErrorCode();
		}

		return true;
	}
}
