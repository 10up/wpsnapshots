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
	 * Connection config
	 *
	 * @var array
	 */
	public $config = [];

	/**
	 * Init dynamodb client
	 *
	 * @param array $config Array of config
	 */
	public function __construct( $config ) {
		$this->config = $config;

		$this->client = DynamoDbClient::factory(
			[
				/*'credentials' => [
					'key'    => $config['access_key_id'],
					'secret' => $config['secret_access_key'],
				],*/
				'region'      => $config['region'],
				'version'     => '2012-08-10',
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
			'TableName' => 'wpsnapshots-' . $this->config['repository'],
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
			$error = [
				'message'        => $e->getMessage(),
				'aws_request_id' => $e->getAwsRequestId(),
				'aws_error_type' => $e->getAwsErrorType(),
				'aws_error_code' => $e->getAwsErrorCode(),
			];

			return new Error( 0, $error );
		}

		$instances = [];

		foreach ( $search_scan as $item ) {
			$instances[] = $marshaler->unmarshalItem( $item );
		}

		return $instances;
	}

	public function makeSnapshotPublic() {
		$client = new IamClient([
		'profile' => 'default',
		'region' => 'us-west-2',
		'version' => '2010-05-08',
		]);

		$myManagedPolicy = '{
		    "Version": "2012-10-17",
		    "Statement": [
		        {
		            "Sid": "ReadOnlyAccessToSnapshots",
		            "Effect": "Allow",
		            "Action": [
		                "dynamodb:GetItem",
		                "dynamodb:BatchGetItem",
		                "dynamodb:Query"
		            ],
		            "Resource": [
		                "arn:aws:dynamodb:' . $this->config['region'] . ':*:table/wpsnapshots-' . $this->config['repository'] . '"
		            ],
		            "Condition": {
		                "ForAllValues:StringEquals": {
		                    "dynamodb:LeadingKeys": [
		                        "snapshotid"
		                    ]
		                }
		            }
		        }
		    ]
		}';

		/*try {
		$result = $client->createPolicy(array(
		// PolicyName is required
		'PolicyName' => 'myDynamoDBPolicy',
		// PolicyDocument is required
		'PolicyDocument' => $myManagedPolicy
		));
		var_dump($result);
		} catch (AwsException $e) {
		// output error message if fails
		error_log($e->getMessage());
		}*/
	}

	/**
	 * Insert a snapshot into the DB
	 *
	 * @param  string $id Snapshot ID
	 * @param  array  $snapshot Description of snapshot
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
			'size'              => $snapshot['size'],
			'wp_version'        => $snapshot['wp_version'],
		];

		$snapshot_json = json_encode( $snapshot_item );

		try {
			$result = $this->client->putItem(
				[
					'TableName' => 'wpsnapshots-' . $this->config['repository'],
					'Item'      => $marshaler->marshalJson( $snapshot_json ),
				]
			);
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
	 * @param  string $id Snapshot ID
	 * @return bool|Error
	 */
	public function deleteSnapshot( $id ) {
		try {
			$result = $this->client->deleteItem(
				[
					'TableName' => 'wpsnapshots-' . $this->config['repository'],
					'Key'       => [
						'id' => [
							'S' => $id,
						],
					],
				]
			);
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
	 * @param  string $id Snapshot ID
	 * @return bool|Error
	 */
	public function getSnapshot( $id ) {
		try {
			$result = $this->client->getItem(
				[
					'ConsistentRead' => true,
					'TableName'      => 'wpsnapshots-' . $this->config['repository'],
					'Key'            => [
						'id' => [
							'S' => $id,
						],
					],
				]
			);
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
			$this->client->createTable(
				[
					'TableName'             => 'wpsnapshots-' . $this->config['repository'],
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
					'TableName' => 'wpsnapshots-' . $this->config['repository'],
				]
			);
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
}
