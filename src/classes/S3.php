<?php

namespace WPProjects;

use \Aws\S3\S3Client;

class S3 {
	private $client;

	/**
	 * Setup S3 client
	 *
	 * @param array $creds
	 * @param string $profile
	 */
	public function __construct( $creds, $profile ) {
		$this->client = S3Client::factory( [
			'credentials' => [
				'key'    => $creds['access_key_id'],
				'secret' => $creds['secret_access_key'],
			],
		] );
	}

	/**
	 * Upload a project instance to S3 given a path to files.tar.gz and data.sql
	 *
	 * @param  array $project_instance Must contain 'id'
	 * @param  string $db_path         Path to data.sql
	 * @param  string $files_path      Path to files.tar.gz
	 * @return bool|error
	 */
	public function putProjectInstance( $project_instance, $db_path, $files_path ) {
		try {
			$db_result = $this->client->putObject( [
				'Bucket'     => 'wpprojects',
				'Key'        => $project_instance['id'] . '/data.sql',
				'SourceFile' => realpath( $db_path ),
				'Metadata'   => [
					'project' => $project_instance['project'],
				],
			] );

			$files_result = $this->client->putObject( [
				'Bucket'     => 'wpprojects',
				'Key'        => $project_instance['id'] . '/files.tar.gz',
				'SourceFile' => realpath( $files_path ),
				'Metadata'   => [
					'project' => $project_instance['project'],
				],
			] );

			/**
			 * Wait for files first since that will probably take longer
			 */
			$this->client->waitUntil( 'ObjectExists', [
				'Bucket' => 'wpprojects',
				'Key'    => $project_instance['id'] . '/files.tar.gz',
			] );

			$this->client->waitUntil( 'ObjectExists', [
				'Bucket' => 'wpprojects',
				'Key'    => $project_instance['id'] . '/data.sql',
			] );
		} catch ( \Exception $e ) {
			return new Error( 0 );
		}

		return true;
	}

	/**
	 * Download a project instance given an id. Must specify where to download files/data
	 *
	 * @param  string $id         Project instance id
	 * @param  string $db_path    Where to download data.sql
	 * @param  string $files_path Where to download files.tar.gz
	 * @return bool|error
	 */
	public function downloadProjectInstance( $id, $db_path, $files_path ) {
		try {
			$db_download = $this->client->getObject( [
			    'Bucket' => 'wpprojects',
			    'Key'    => $id . '/data.sql',
			    'SaveAs' => $db_path,
			] );

			$files_download = $this->client->getObject( [
			    'Bucket' => 'wpprojects',
			    'Key'    => $id . '/files.tar.gz',
			    'SaveAs' => $files_path,
			] );
		} catch ( \Exception $e ) {
			return new Error( 0 );
		}

		return true;
	}

	/**
	 * Delete a project instance given an id
	 *
	 * @param  string $id Project instance id
	 * @return bool|error
	 */
	public function deleteProjectInstance( $id ) {
		try {
			$result = $this->client->deleteObjects( [
				'Bucket' => 'wpprojects',
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

	/**
	 * Test S3 connection by attempting to list S3 buckets
	 *
	 * @param  array $creds
	 * @return bool|Error
	 */
	public static function test( $creds ) {
		$client = S3Client::factory( [
			'credentials' => [
				'key'    => $creds['access_key_id'],
				'secret' => $creds['secret_access_key'],
			]
		] );

		try {
			$client->listBuckets();

			return true;
		} catch ( \Exception $e ) {
			return new Error( 0, 'Connection could not be established' );
		}
	}

	/**
	 * Create WPProjects S3 bucket
	 *
	 * @return bool|Error
	 */
	public function createBucket() {
		try {
			$result = $this->client->createBucket( [ 'Bucket' => 'wpprojects' ] );
		} catch ( \Exception $e ) {
			if ( 'BucketAlreadyOwnedByYou' === $e->getAwsErrorCode() || 'BucketAlreadyExists' === $e->getAwsErrorCode() ) {
				return new Error( 1, 'Bucket already exists' );
			}

			return new Error( 0, 'Could not create bucket' );
		}

		return true;
	}
}
