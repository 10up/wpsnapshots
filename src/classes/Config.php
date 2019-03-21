<?php
/**
 * Handle config
 *
 * @package wpsnapshots
 */

namespace WPSnapshots;

use \ArrayAccess;
use WPSnapshots\Utils;

/**
 * Handle suite config files
 */
class Config implements ArrayAccess {

	/**
	 * Store config
	 *
	 * @var array
	 */
	protected $config;

	/**
	 * Initiate class
	 *
	 * @param  array $config Configuration array
	 */
	public function __construct( $config = [] ) {
		$this->config = array_merge(
			[
				'name'         => '',
				'email'        => '',
				'repositories' => [],
			],
			$config
		);
	}

	/**
	 * Create config from file
	 *
	 * @return Config|bool
	 */
	public static function get() {
		Log::instance()->write( 'Reading configuration from file.', 1 );

		$file_path = Utils\get_snapshot_directory() . 'config.json';

		if ( ! file_exists( $file_path ) ) {
			/**
			 * Backwards compat for old config file path
			 */
			$file_path = $_SERVER['HOME'] . '/.wpsnapshots.json';

			if ( ! file_exists( $file_path ) ) {
				Log::instance()->write( 'No config found.', 1 );

				$config = new self();
				$config->write();

				return $config;
			} else {
				rename( $file_path, Utils\get_snapshot_directory() . 'config.json' );

				$file_path = Utils\get_snapshot_directory() . 'config.json';
			}
		}

		$config = json_decode( file_get_contents( $file_path ), true );

		if ( empty( $config ) ) {
			$config = [];
		}

		return new self( $config );
	}

	/**
	 * Write config to current config file
	 */
	public function write() {
		Log::instance()->write( 'Writing config.', 1 );

		$create_dir = Utils\create_snapshot_directory();

		if ( ! $create_dir ) {
			Log::instance()->write( 'Cannot create necessary snapshot directory.', 0, 'error' );

			return false;
		}

		file_put_contents( Utils\get_snapshot_directory() . 'config.json', json_encode( $this->config, JSON_PRETTY_PRINT ) );
	}

	/**
	 * Set key in class
	 *
	 * @param  int|string $offset Array key
	 * @param  mixed      $value  Array value
	 */
	public function offsetSet( $offset, $value ) {
		if ( is_null( $offset ) ) {
			$this->config[] = $value;
		} else {
			$this->config[ $offset ] = $value;
		}
	}

	/**
	 * Check if key exists
	 *
	 * @param  int|string $offset Array key
	 * @return bool
	 */
	public function offsetExists( $offset ) {
		return isset( $this->config[ $offset ] );
	}

	/**
	 * Delete array value by key
	 *
	 * @param  int|string $offset Array key
	 */
	public function offsetUnset( $offset ) {
		unset( $this->config[ $offset ] );
	}

	/**
	 * Get config array
	 *
	 * @return array
	 */
	public function toArray() {
		return $this->config;
	}

	/**
	 * Get array value by key
	 *
	 * @param  int|string $offset Array key
	 * @return mixed
	 */
	public function offsetGet( $offset ) {
		return isset( $this->config[ $offset ] ) ? $this->config[ $offset ] : null;
	}
}
