<?php
/**
 * Amazon Dynamo wrapper functionality
 *
 * @package wpsnapshots
 */

namespace WPSnapshots;

use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Marshaler;
use Aws\Iam\IamClient;

/**
 * Class for handling Amazon dynamodb calls
 */
class DB {
	/**
	 * Instance of DynamoDB client
	 *
	 * @var DynamoDbClient
	 */
	public $client;

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
	 * Construct DB client
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

		$this->client = DynamoDbClient::factory(
			[
				'credentials' => [
					'key'    => $access_key_id,
					'secret' => $secret_access_key,
				],
				'region'      => $region,
				'version'     => '2012-08-10',
				'csm'         => false,
			]
		);
	}

	/**
	 * Use DynamoDB scan to search tables for snapshots where project, id, or author information
	 * matches search text. Searching for "*" returns all snapshots.
	 *
	 * @param  string $query Search query string
	 * @return array
	 */
	public function search( $query ) {
		$marshaler = new Marshaler();

		$args = [
			'TableName' => 'wpsnapshots-' . $this->repository,
		];

		if ( '*' !== $query ) {
			$args['ConditionalOperator'] = 'OR';

			$args['ScanFilter'] = [
				'project' => [
					'AttributeValueList' => [
						[ 'S' => strtolower( $query ) ],
					],
					'ComparisonOperator' => 'CONTAINS',
				],
				'id'      => [
					'AttributeValueList' => [
						[ 'S' => strtolower( $query ) ],
					],
					'ComparisonOperator' => 'EQ',
				],
			];
		}

		try {
			$search_scan = $this->client->getIterator( 'Scan', $args );
		} catch ( \Exception $e ) {
			Log::instance()->write( 'Error Message: ' . $e->getMessage(), 1, 'error' );
			Log::instance()->write( 'AWS Request ID: ' . $e->getAwsRequestId(), 1, 'error' );
			Log::instance()->write( 'AWS Error Type: ' . $e->getAwsErrorType(), 1, 'error' );
			Log::instance()->write( 'AWS Error Code: ' . $e->getAwsErrorCode(), 1, 'error' );

			return false;
		}

		$instances = [];

		foreach ( $search_scan as $item ) {
			$instances[] = $marshaler->unmarshalItem( $item );
		}

		return $instances;
	}

	/**
	 * Insert a snapshot into the DB
	 *
	 * @param  Snapshot $snapshot Snapshot to insert
	 * @return array|bool
	 */
	public function insertSnapshot( Snapshot $snapshot ) {
		$marshaler = new Marshaler();

		$snapshot_item = [
			'project' => strtolower( $snapshot->meta['project'] ),
			'id'      => $snapshot->id,
			'time'    => time(),
		];

		$snapshot_item = array_merge( $snapshot_item, $snapshot->meta->toArray() );
		$snapshot_json = json_encode( $snapshot_item );

		try {
			$result = $this->client->putItem(
				[
					'TableName' => 'wpsnapshots-' . $this->repository,
					'Item'      => $marshaler->marshalJson( $snapshot_json ),
				]
			);
		} catch ( \Exception $e ) {
			if ( 'AccessDeniedException' === $e->getAwsErrorCode() ) {
				Log::instance()->write( 'Access denied. You might not have access to this project.', 0, 'error' );
			}

			Log::instance()->write( 'Error Message: ' . $e->getMessage(), 1, 'error' );
			Log::instance()->write( 'AWS Request ID: ' . $e->getAwsRequestId(), 1, 'error' );
			Log::instance()->write( 'AWS Error Type: ' . $e->getAwsErrorType(), 1, 'error' );
			Log::instance()->write( 'AWS Error Code: ' . $e->getAwsErrorCode(), 1, 'error' );

			return false;
		}

		return $snapshot_item;
	}

	/**
	 * Delete a snapshot given an id
	 *
	 * @param  string $id Snapshot ID
	 * @return bool|Error
	 */
	public function deleteSnapshot( $id ) {
		try {
			$result = $this->client->deleteItem(
				[
					'TableName' => 'wpsnapshots-' . $this->repository,
					'Key'       => [
						'id' => [
							'S' => $id,
						],
					],
				]
			);
		} catch ( \Exception $e ) {
			Log::instance()->write( 'Error Message: ' . $e->getMessage(), 1, 'error' );
			Log::instance()->write( 'AWS Request ID: ' . $e->getAwsRequestId(), 1, 'error' );
			Log::instance()->write( 'AWS Error Type: ' . $e->getAwsErrorType(), 1, 'error' );
			Log::instance()->write( 'AWS Error Code: ' . $e->getAwsErrorCode(), 1, 'error' );

			return false;
		}

		return true;
	}

	/**
	 * Get a snapshot given an id
	 *
	 * @param  string $id Snapshot ID
	 * @return bool
	 */
	public function getSnapshot( $id ) {
		try {
			$result = $this->client->getItem(
				[
					'ConsistentRead' => true,
					'TableName'      => 'wpsnapshots-' . $this->repository,
					'Key'            => [
						'id' => [
							'S' => $id,
						],
					],
				]
			);
		} catch ( \Exception $e ) {
			if ( 'AccessDeniedException' === $e->getAwsErrorCode() ) {
				Log::instance()->write( 'Access denied. You might not have access to this snapshot.', 0, 'error' );
			}

			Log::instance()->write( 'Error Message: ' . $e->getMessage(), 1, 'error' );
			Log::instance()->write( 'AWS Request ID: ' . $e->getAwsRequestId(), 1, 'error' );
			Log::instance()->write( 'AWS Error Type: ' . $e->getAwsErrorType(), 1, 'error' );
			Log::instance()->write( 'AWS Error Code: ' . $e->getAwsErrorCode(), 1, 'error' );

			return false;
		}

		if ( empty( $result['Item'] ) ) {
			return false;
		}

		if ( ! empty( $result['Item']['error'] ) ) {
			return false;
		}

		$marshaler = new Marshaler();

		return $marshaler->unmarshalItem( $result['Item'] );
	}

	/**
	 * Create default DB tables. Only need to do this once ever for repo setup.
	 *
	 * @return bool
	 */
	public function createTables() {
		try {
			$this->client->createTable(
				[
					'TableName'             => 'wpsnapshots-' . $this->repository,
					'AttributeDefinitions'  => [
						[
							'AttributeName' => 'id',
							'AttributeType' => 'S',
						],
					],
					'KeySchema'             => [
						[
							'AttributeName' => 'id',
							'KeyType'       => 'HASH',
						],
					],
					'ProvisionedThroughput' => [
						'ReadCapacityUnits'  => 10,
						'WriteCapacityUnits' => 20,
					],
				]
			);

			$this->client->waitUntil(
				'TableExists', [
					'TableName' => 'wpsnapshots-' . $this->repository,
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
