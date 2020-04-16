<?php
/**
 * Snapshot meta class
 *
 * @package wpsnapshots
 */

namespace WPSnapshots;

use \ArrayAccess;
use WPSnapshots\Log;
use WPSnapshots\Utils;

/**
 * Array style Snapshot meta wrapper with support for downloading remote meta
 */
class Meta implements ArrayAccess {

	/**
	 * Snapshot id
	 *
	 * @var string
	 */
	public $id;

	/**
	 * Snapshot meta data
	 *
	 * @var array
	 */
	protected $meta = [];

	/**
	 * Meta constructor
	 *
	 * @param  string $id Snapshot ID. Optional
	 * @param  array  $meta Snapshot meta data
	 * @return self
	 */
	public function __construct( $id, $meta = [] ) {
		$this->meta = $meta;
		$this->id   = $id;
	}

	/**
	 * Save snapshot meta locally
	 *
	 * @return false|int Number of bytes written
	 */
	public function saveLocal() {
		$meta_handle = @fopen( Utils\get_snapshot_directory() . $this->id . '/meta.json', 'x' ); // Create file and fail if it exists.

		if ( ! $meta_handle ) {
			return false;
		}

		return fwrite( $meta_handle, json_encode( $this->meta, JSON_PRETTY_PRINT ) );
	}

	/**
	 * Get meta. First try locally then try downloading
	 *
	 * @param   string $id Snapshot id
	 * @param   string $repository_name Name of repo
	 * @return  bool|Meta
	 */
	public static function get( $id, $repository_name ) {
		$cached_meta = self::getLocal( $id, $repository_name );

		// Maybe meta has already been downloaded.
		if ( ! empty( $cached_meta ) ) {
			return $cached_meta;
		}

		return self::getRemote( $id, $repository_name );
	}

	/**
	 * Download meta from remote DB
	 *
	 * @param   string $id Snapshot id
	 * @param   string $repository_name Name of repo
	 * @return  bool|Meta
	 */
	public static function getRemote( $id, $repository_name ) {
		$repository = RepositoryManager::instance()->setup( $repository_name );

		if ( ! $repository ) {
			Log::instance()->write( 'Could not setup repository.', 0, 'error' );

			return false;
		}

		$snapshot = $repository->getDB()->getSnapshot( $id );

		if ( ! $snapshot ) {
			Log::instance()->write( 'Could not download snapshot meta from database.', 0, 'error' );

			return false;
		}

		// Backwards compat since these previously were not set.
		if ( ! isset( $snapshot['contains_files'] ) ) {
			$snapshot['contains_files'] = true;
		} if ( ! isset( $snapshot['contains_db'] ) ) {
			$snapshot['contains_db'] = true;
		}

		$snapshot['repository'] = $repository_name;

		return new self( $id, $snapshot );
	}

	/**
	 * Get local snapshot meta
	 *
	 * @param  string $id Snapshot ID
	 * @param  string $repository_name Snapshot repository
	 * @return self|bool
	 */
	public static function getLocal( $id, $repository_name ) {
		if ( ! file_exists( Utils\get_snapshot_directory() . $id . '/meta.json' ) ) {
			return false;
		}

		$meta_file_contents = file_get_contents( Utils\get_snapshot_directory() . $id . '/meta.json' );
		$meta               = json_decode( $meta_file_contents, true );

		if ( null === $meta ) {
			Log::instance()->write( 'Could not decode snapshot meta.', 0, 'error' );

			return false;
		}

		if ( $repository_name !== $meta['repository'] ) {
			return false;
		}

		// Backwards compat since these previously were not set.
		if ( ! isset( $meta['contains_files'] ) && file_exists( Utils\get_snapshot_directory() . $id . '/files.tar.gz' ) ) {
			$meta['contains_files'] = true;
		} if ( ! isset( $meta['contains_db'] ) && file_exists( Utils\get_snapshot_directory() . $id . '/data.sql.gz' ) ) {
			$meta['contains_db'] = true;
		}

		if ( empty( $meta['contains_files'] ) && empty( $meta['contains_db'] ) ) {
			Log::instance()->write( 'Snapshot meta invalid.', 0, 'error' );

			return false;
		}

		return new self( $id, $meta );
	}

	/**
	 * Set key in class
	 *
	 * @param  int|string $offset Array key
	 * @param  mixed      $value  Array value
	 */
	public function offsetSet( $offset, $value ) {
		if ( is_null( $offset ) ) {
			$this->meta[] = $value;
		} else {
			$this->meta[ $offset ] = $value;
		}
	}

	/**
	 * Check if key exists
	 *
	 * @param  int|string $offset Array key
	 * @return bool
	 */
	public function offsetExists( $offset ) {
		return isset( $this->meta[ $offset ] );
	}

	/**
	 * Delete array value by key
	 *
	 * @param  int|string $offset Array key
	 */
	public function offsetUnset( $offset ) {
		unset( $this->meta[ $offset ] );
	}

	/**
	 * Get meta array
	 *
	 * @return array
	 */
	public function toArray() {
		return $this->meta;
	}

	/**
	 * Get array value by key
	 *
	 * @param  int|string $offset Array key
	 * @return mixed
	 */
	public function offsetGet( $offset ) {
		return isset( $this->meta[ $offset ] ) ? $this->meta[ $offset ] : null;
	}
}
