<?php

namespace WPSnapshots;

use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Marshaler;

class DB {
	public $client;
	public $repository;

	/**
	 * Init dynamodb client
	 *
	 * @param array $config
	 */
	public function __construct( $config ) {
		$this->client = DynamoDbClient::factory( [
			'credentials' => [
				'key'    => $config['access_key_id'],
				'secret' => $config['secret_access_key'],
			],
			'region'      => $config['region'],
		] );

		$this->repository = $config['repository'];
	}

	/**
	 * Use DynamoDB scan to search tables for snapshots where project, id, or author information
	 * matches search text. Searching for "*" returns all snapshots.
	 *
	 * @param  string $query
	 * @return array
	 */
	public function search( $query ) {
		$marshaler = new Marshaler();

		$args = [
			'TableName'  => 'wpsnapshots-' . $this->repository,
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
		        'id' => [
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
			return [];
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
	 * @param  string $id
	 * @param  array  $snapshot [description]
	 * @return Error|array
	 */
	public function insertSnapshot( $id, $snapshot ) {
		$marshaler = new Marshaler();

		$snapshot_item = [
			'project'           => strtolower( $snapshot['project'] ),
			'id'                => $id,
			'time'              => time(),
			'description'       => $snapshot['description'],
			'author'            => $snapshot['author'],
			'multisite'         => $snapshot['multisite'],
			'sites'             => $snapshot['sites'],
			'table_prefix'      => $snapshot['table_prefix'],
			'subdomain_install' => $snapshot['subdomain_install'],
		];

		$snapshot_json = json_encode( $snapshot_item );

		try {
			$result = $this->client->putItem( [
				'TableName' => 'wpsnapshots-' . $this->repository,
				'Item'      => $marshaler->marshalJson( $snapshot_json ),
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

		return $snapshot_item;
	}

	/**
	 * Delete a snapshot given an id
	 *
	 * @param  string $id
	 * @return bool|Error
	 */
	public function deleteSnapshot( $id ) {
		try {
			$result = $this->client->deleteItem( [
				'TableName' => 'wpsnapshots-' . $this->repository,
				'Key' => [
					'id'   => [
						'S' => $id,
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

	/**
	 * Get a snapshot given an id
	 *
	 * @param  string $id
	 * @return bool|Error
	 */
	public function getSnapshot( $id ) {
		try {
			$result = $this->client->getItem( [
				'ConsistentRead' => true,
				'TableName'      => 'wpsnapshots-' . $this->repository,
				'Key'            => [
					'id' => [
						'S' => $id,
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

		if ( empty( $result['Item'] ) ) {
			return new Error( 2, 'Item not found' );
		}

		if ( ! empty( $result['Item']['error'] ) ) {
			return new Error( 1 );
		}

		$marshaler = new Marshaler();

		return $marshaler->unmarshalItem( $result['Item'] );
	}

	/**
	 * Create default DB tables. Only need to do this once ever for repo setup.
	 *
	 * @return bool|Error
	 */
	public function createTables() {
		try {
			$this->client->createTable( [
				'TableName' => 'wpsnapshots-' . $this->repository,
				'AttributeDefinitions' => [
					[
						'AttributeName' => 'id',
						'AttributeType' => 'S',
					],
				],
				'KeySchema' => [
					[
						'AttributeName' => 'id',
						'KeyType'       => 'HASH',
					],
				],
				'ProvisionedThroughput' => [
					'ReadCapacityUnits'  => 10,
					'WriteCapacityUnits' => 20,
				],
			] );

			$this->client->waitUntil('TableExists', [
			    'TableName' => 'wpsnapshots-' . $this->repository,
			] );
		} catch ( \Exception $e ) {
			if ( 'ResourceInUseException' === $e->getAwsErrorCode() ) {
				return new Error( 1, 'Table already exists' );
			} else {
				return new Error( 0, 'Could not create table' );
			}
		}

		return true;
	}
}
