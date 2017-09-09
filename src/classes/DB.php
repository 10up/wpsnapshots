<?php

namespace WPProjects;

use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Marshaler;

class DB {
	public $client;

	/**
	 * Init dynamodb client
	 *
	 * @param array $creds
	 * @param string $profile Config profile
	 * @param [string $region  AWS region
	 */
	public function __construct( $creds, $profile, $region ) {
		$this->client = DynamoDbClient::factory( [
			'credentials' => [
				'key'    => $creds['access_key_id'],
				'secret' => $creds['secret_access_key'],
			],
			'region'      => $region,
		] );
	}

	/**
	 * Use DynamoDB scan to search tables for projects where project, id, or author information
	 * matches search text
	 *
	 * @param  string $query
	 * @return array
	 */
	public function search( $query ) {
		$marshaler = new Marshaler();

		try {
			$search_scan = $this->client->getIterator( 'Scan', [
				'TableName'  => 'wpprojects',
				'ConditionalOperator' => 'OR',
				'ScanFilter' => [
			        'project' => [
			            'AttributeValueList' => [
			                [ 'S' => $query ]
			            ],
			            'ComparisonOperator' => 'CONTAINS'
			        ],
			        'id' => [
			            'AttributeValueList' => [
			                [ 'S' => $query ]
			            ],
			            'ComparisonOperator' => 'EQ'
			        ],
			    ],
			] );
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
	 * Insert a project instance into the DB
	 *
	 * @param  array $project_instance [description]
	 * @return Error|array
	 */
	public function insertProjectInstance( $project_instance ) {
		$marshaler = new Marshaler();

		$time = time();

		$project_item = [
			'project'           => $project_instance['project'],
			'id'                => md5( $project_instance['project'] . '-' . $time ),
			'time'              => $time,
			'environment'       => $project_instance['environment'],
			'author'            => $project_instance['author'],
			'multisite'         => $project_instance['multisite'],
			'sites'             => $project_instance['sites'],
			'table_prefix'      => $project_instance['table_prefix'],
			'subdomain_install' => $project_instance['subdomain_install'],
		];

		$project_json = json_encode( $project_item );

		try {
			$result = $this->client->putItem( [
				'TableName' => 'wpprojects',
				'Item'      => $marshaler->marshalJson( $project_json )
			] );
		} catch ( \Exception $e ) {
			return new Error( 0, 'Error occurred.' );
		}

		return $project_item;
	}

	/**
	 * Delete a project instance given an id
	 *
	 * @param  string $id
	 * @return bool|Error
	 */
	public function deleteProjectInstance( $id ) {
		try {
			$result = $this->client->deleteItem( [
				'TableName' => 'wpprojects',
				'Key' => [
					'id'   => [
						'S' => $id
					],
				]
			] );
		} catch ( \Exception $e ) {
			return new Error( 0 );
		}

		return true;
	}

	/**
	 * Get a project instance given an id
	 *
	 * @param  string $id
	 * @return bool|Error
	 */
	public function getProjectInstance( $id ) {
		try {
			$result = $this->client->getItem( [
				'ConsistentRead' => true,
				'TableName'      => 'wpprojects',
				'Key'            => [
					'id' => [
						'S' => $id
					],
				]
			] );
		} catch ( \Exception $e ) {
			return new Error( 0 );
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
				'TableName' => 'wpprojects',
				'AttributeDefinitions' => [
					[
						'AttributeName' => 'id',
						'AttributeType' => 'S'
					]
				],
				'KeySchema' => [
					[
						'AttributeName' => 'id',
						'KeyType'       => 'HASH'
					]
				],
				'ProvisionedThroughput' => [
					'ReadCapacityUnits'  => 10,
					'WriteCapacityUnits' => 20
				]
			] );

			$this->client->waitUntil('TableExists', [
			    'TableName' => 'wpprojects'
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
