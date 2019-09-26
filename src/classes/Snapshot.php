<?php
/**
 * Handle snapshot actions
 *
 * @package wpsnapshots
 */

namespace WPSnapshots;

use Symfony\Component\Console\Output\OutputInterface;
use \Exception;
use WPSnapshots\Utils;
use WPSnapshots\Log;

/**
 * Create, download, save, push, and pull snapshots
 */
class Snapshot {
	/**
	 * Snapshot id
	 *
	 * @var string
	 */
	public $id;

	/**
	 * Repository name
	 *
	 * @var  string
	 */
	public $repository_name;

	/**
	 * Snapshot meta data
	 *
	 * @var array
	 */
	public $meta = [];

	/**
	 * Does snapshot exist on remote or not
	 *
	 * @var boolean
	 */
	public $remote = false;

	/**
	 * Snapshot constructor. Snapshot must already exist locally in $path.
	 *
	 * @param string $id Snapshot id
	 * @param string $repository_name Name of rpo
	 * @param array  $meta Snapshot meta data
	 * @param bool   $remote Does snapshot exist remotely or not
	 * @throws Exception Throw exception if files don't exist.
	 */
	public function __construct( $id, $repository_name, $meta, $remote = false ) {
		$this->id              = $id;
		$this->repository_name = $repository_name;
		$this->meta            = $meta;
		$this->remote          = $remote;

		if ( ! file_exists( Utils\get_snapshot_directory() . $id . '/data.sql.gz' ) || ! file_exists( Utils\get_snapshot_directory() . $id . '/files.tar.gz' ) ) {
			throw new Exception( 'Snapshot data or files do not exist locally.' );
		}
	}

	/**
	 * Given an ID, create a WP Snapshots object
	 *
	 * @param  string $id Snapshot ID
	 * @return Snapshot
	 */
	public static function get( $id ) {
		if ( file_exists( Utils\get_snapshot_directory() . $id . '/meta.json' ) ) {
			$meta_file_contents = file_get_contents( Utils\get_snapshot_directory() . $id . '/meta.json' );
			$meta               = json_decode( $meta_file_contents, true );
		} else {
			$meta = [];
		}

		/**
		 * Backwards compant - need to fill in repo before we started saving repo in meta.
		 */
		if ( empty( $meta['repository'] ) ) {
			Log::instance()->write( 'Legacy snapshot found without repository. Assuming default repository.', 1, 'warning' );

			$meta['repository'] = RepositoryManager::instance()->getDefault();
		}

		return new self( $id, $meta['repository'], $meta );
	}

	/**
	 * Create a snapshot.
	 *
	 * @param array $args List of arguments
	 * @return bool|Snapshot
	 */
	public static function create( $args ) {
		$path = Utils\normalize_path( $args['path'] );

		if ( ! Utils\is_wp_present( $path ) ) {
			Log::instance()->write( 'This is not a WordPress install. You can only create a snapshot from the root of a WordPress install.', 0, 'error' );

			return;
		}

		/**
		 * Define snapshot ID
		 */
		$id = Utils\generate_snapshot_id();

		$create_dir = Utils\create_snapshot_directory( $id );

		if ( ! $create_dir ) {
			Log::instance()->write( 'Cannot create necessary snapshot directories.', 0, 'error' );

			return false;
		}

		if ( ! Utils\is_wp_present( $path ) ) {
			Log::instance()->write( 'This is not a WordPress install.', 0, 'error' );

			return false;
		}

		if ( ! Utils\locate_wp_config( $path ) ) {
			Log::instance()->write( 'No wp-config.php file present.', 0, 'error' );

			return false;
		}

		$extra_config_constants = [
			'WP_CACHE' => false,
		];

		if ( ! empty( $args['db_host'] ) ) {
			$extra_config_constants['DB_HOST'] = $args['db_host'];
		} if ( ! empty( $args['db_name'] ) ) {
			$extra_config_constants['DB_NAME'] = $args['db_name'];
		} if ( ! empty( $args['db_user'] ) ) {
			$extra_config_constants['DB_USER'] = $args['db_user'];
		} if ( ! empty( $args['db_password'] ) ) {
			$extra_config_constants['DB_PASSWORD'] = $args['db_password'];
		}

		Log::instance()->write( 'Bootstrapping WordPress...', 1 );

		if ( ! WordPressBridge::instance()->load( $path, $extra_config_constants ) ) {
			Log::instance()->write( 'Could not connect to WordPress database.', 0, 'error' );

			return false;
		}

		global $wpdb;

		$meta = [
			'author'      => [],
			'repository'  => $args['repository'],
			'description' => $args['description'],
			'project'     => $args['project'],
		];

		$author_info = RepositoryManager::instance()->getAuthorInfo();

		if ( ! empty( $author_info['name'] ) ) {
			$meta['author']['name'] = $author_info['name'];
		}

		if ( ! empty( $author_info['email'] ) ) {
			$meta['author']['email'] = $author_info['email'];
		}

		$meta['multisite']            = false;
		$meta['subdomain_install']    = false;
		$meta['domain_current_site']  = false;
		$meta['path_current_site']    = false;
		$meta['site_id_current_site'] = false;
		$meta['blog_id_current_site'] = false;
		$meta['sites']                = [];

		if ( is_multisite() ) {
			$meta['multisite'] = true;

			if ( defined( 'SUBDOMAIN_INSTALL' ) && SUBDOMAIN_INSTALL ) {
				$meta['subdomain_install'] = true;
			}

			if ( defined( 'DOMAIN_CURRENT_SITE' ) ) {
				$meta['domain_current_site'] = DOMAIN_CURRENT_SITE;
			}

			if ( defined( 'PATH_CURRENT_SITE' ) ) {
				$meta['path_current_site'] = PATH_CURRENT_SITE;
			}

			if ( defined( 'SITE_ID_CURRENT_SITE' ) ) {
				$meta['site_id_current_site'] = SITE_ID_CURRENT_SITE;
			}

			if ( defined( 'BLOG_ID_CURRENT_SITE' ) ) {
				$meta['blog_id_current_site'] = BLOG_ID_CURRENT_SITE;
			}

			$sites = get_sites( [ 'number' => 500 ] );

			foreach ( $sites as $site ) {
				$meta['sites'][] = [
					'blog_id'  => $site->blog_id,
					'domain'   => $site->domain,
					'path'     => $site->path,
					'site_url' => get_blog_option( $site->blog_id, 'siteurl' ),
					'home_url' => get_blog_option( $site->blog_id, 'home' ),
					'blogname' => get_blog_option( $site->blog_id, 'blogname' ),
				];
			}
		} else {
			$meta['sites'][] = [
				'site_url' => get_option( 'siteurl' ),
				'home_url' => get_option( 'home' ),
				'blogname' => get_option( 'blogname' ),
			];
		}

		$main_blog_id = ( defined( 'BLOG_ID_CURRENT_SITE' ) ) ? BLOG_ID_CURRENT_SITE : null;

		$meta['table_prefix'] = $wpdb->get_blog_prefix( $main_blog_id );

		global $wp_version;

		$meta['wp_version'] = ( ! empty( $wp_version ) ) ? $wp_version : '';

		/**
		 * Dump sql to .wpsnapshots/data.sql
		 */
		$command          = '/usr/bin/env mysqldump --no-defaults --single-transaction %s';
		$command_esc_args = array( DB_NAME );
		$command         .= ' --tables';

		/**
		 * We only export tables with WP prefix
		 */
		Log::instance()->write( 'Getting WordPress tables...', 1 );

		$tables = Utils\get_tables();

		foreach ( $tables as $table ) {
			// We separate the users table for scrubbing
			if ( ! $args['no_scrub'] && $GLOBALS['table_prefix'] . 'users' === $table ) {
				continue;
			}

			$command           .= ' %s';
			$command_esc_args[] = trim( $table );
		}

		$snapshot_path = Utils\get_snapshot_directory() . $id . '/';

		$mysql_args = [
			'host'        => DB_HOST,
			'pass'        => DB_PASSWORD,
			'user'        => DB_USER,
			'result-file' => $snapshot_path . 'data.sql',
		];

		if ( defined( 'DB_CHARSET' ) && constant( 'DB_CHARSET' ) ) {
			$mysql_args['default-character-set'] = constant( 'DB_CHARSET' );
		}

		$escaped_command = call_user_func_array( '\WPSnapshots\Utils\esc_cmd', array_merge( array( $command ), $command_esc_args ) );

		Log::instance()->write( 'Exporting database...' );

		Utils\run_mysql_command( $escaped_command, $mysql_args );

		if ( ! $args['no_scrub'] ) {
			$command = '/usr/bin/env mysqldump --no-defaults --single-transaction %s';

			$command_esc_args = array( DB_NAME );

			$command           .= ' --tables %s';
			$command_esc_args[] = $GLOBALS['table_prefix'] . 'users';

			$mysql_args = [
				'host'        => DB_HOST,
				'pass'        => DB_PASSWORD,
				'user'        => DB_USER,
				'result-file' => $snapshot_path . 'data-users.sql',
			];

			$escaped_command = call_user_func_array( '\WPSnapshots\Utils\esc_cmd', array_merge( array( $command ), $command_esc_args ) );

			Log::instance()->write( 'Exporting users...', 1 );

			Utils\run_mysql_command( $escaped_command, $mysql_args );

			Log::instance()->write( 'Scrubbing user database...' );

			$all_hashed_passwords = [];
			$all_emails           = [];

			Log::instance()->write( 'Getting users...', 1 );

			$user_rows = $wpdb->get_results( "SELECT user_pass, user_email FROM $wpdb->users", ARRAY_A );

			foreach ( $user_rows as $user_row ) {
				$all_hashed_passwords[] = $user_row['user_pass'];
				if ( $user_row['user_email'] ) {
					$all_emails[] = $user_row['user_email'];
				}
			}

			$sterile_password = wp_hash_password( 'password' );
			$sterile_email    = 'user%d@example.com';

			Log::instance()->write( 'Opening users export...', 1 );

			$users_handle = @fopen( $snapshot_path . 'data-users.sql', 'r' );
			$data_handle  = @fopen( $snapshot_path . 'data.sql', 'a' );

			if ( ! $users_handle || ! $data_handle ) {
				Log::instance()->write( 'Could not scrub users.', 0, 'error' );

				return false;
			}

			$buffer = '';
			$i      = 0;

			Log::instance()->write( 'Writing scrubbed user data and merging exports...', 1 );

			while ( ! feof( $users_handle ) ) {
				$chunk = fread( $users_handle, 4096 );

				foreach ( $all_hashed_passwords as $password ) {
					$chunk = str_replace( "'$password'", "'$sterile_password'", $chunk );
				}

				foreach ( $all_emails as $index => $email ) {
					$chunk = str_replace(
						"'$email'",
						sprintf( "'$sterile_email'", $index ),
						$chunk
					);
				}

				$buffer .= $chunk;

				if ( 0 === $i % 10000 ) {
					fwrite( $data_handle, $buffer );
					$buffer = '';
				}

				$i++;
			}

			if ( ! empty( $buffer ) ) {
				fwrite( $data_handle, $buffer );
				$buffer = '';
			}

			fclose( $data_handle );
			fclose( $users_handle );

			Log::instance()->write( 'Removing old SQL...', 1 );

			unlink( $snapshot_path . 'data-users.sql' );
		}

		$verbose_pipe = ( empty( Log::instance()->getVerbosity() ) ) ? '> /dev/null' : '';

		/**
		 * Create file back up of wp-content in .wpsnapshots/files.tar.gz
		 */

		Log::instance()->write( 'Saving file back up...' );

		$excludes = '';

		if ( ! empty( $args['exclude'] ) ) {
			foreach ( $args['exclude'] as $exclude ) {
				$exclude = trim( $exclude );

				if ( ! preg_match( '#^\./.*#', $exclude ) ) {
					$exclude = './' . $exclude;
				}

				Log::instance()->write( 'Excluding ' . $exclude, 1 );

				$excludes .= ' --exclude="' . $exclude . '"';
			}
		}

		Log::instance()->write( 'Compressing files...', 1 );

		$v_flag = ( ! empty( Log::instance()->getVerbosity() ) ) ? 'v' : '';

		$command = 'cd ' . escapeshellarg( WP_CONTENT_DIR ) . '/ && tar ' . $excludes . ' -zc' . $v_flag . 'f ' . Utils\escape_shell_path( $snapshot_path ) . 'files.tar.gz . ' . $verbose_pipe;

		Log::instance()->write( $command, 2 );

		exec( $command );

		Log::instance()->write( 'Compressing database backup...', 1 );

		exec( 'gzip -9 ' . Utils\escape_shell_path( $snapshot_path ) . 'data.sql ' . $verbose_pipe );

		$meta['size'] = filesize( $snapshot_path . 'data.sql.gz' ) + filesize( $snapshot_path . 'files.tar.gz' );

		/**
		 * Finally save snapshot meta to meta.json
		 */
		$meta_handle = @fopen( $snapshot_path . 'meta.json', 'x' ); // Create file and fail if it exists.

		if ( ! $meta_handle ) {
			Log::instance()->write( 'Could not create .wpsnapshots/SNAPSHOT_ID/meta.json.', 0, 'error' );

			return false;
		}

		fwrite( $meta_handle, json_encode( $meta, JSON_PRETTY_PRINT ) );

		$snapshot = new self( $id, $args['repository'], $meta );

		return $snapshot;
	}

	/**
	 * Download snapshot.
	 *
	 * @param   string $id Snapshot id
	 * @param   string $repository_name Name of repo
	 * @return  bool|Snapshot
	 */
	public static function download( $id, $repository_name ) {
		if ( Utils\is_snapshot_cached( $id ) ) {
			Log::instance()->write( 'Snapshot found in cache.' );

			return self::get( $id );
		}

		$create_dir = Utils\create_snapshot_directory( $id );

		if ( ! $create_dir ) {
			Log::instance()->write( 'Cannot create necessary snapshot directories.', 0, 'error' );

			return false;
		}

		$repository = RepositoryManager::instance()->setup( $repository_name );

		if ( ! $repository ) {
			Log::instance()->write( 'Could not setup repository.', 0, 'error' );

			return false;
		}

		Log::instance()->write( 'Getting snapshot information...' );

		$snapshot = $repository->getDB()->getSnapshot( $id );

		if ( ! $snapshot ) {
			Log::instance()->write( 'Could not get snapshot from database.', 0, 'error' );

			return false;
		}

		if ( empty( $snapshot ) || empty( $snapshot['project'] ) ) {
			Log::instance()->write( 'Missing critical snapshot data.', 0, 'error' );
			return false;
		}

		/**
		 * Backwards compant. Add repository to meta before we started saving it.
		 */
		if ( empty( $snapshot['repository'] ) ) {
			$snapshot['repository'] = $repository_name;
		}

		Log::instance()->write( 'Downloading snapshot files and database (' . Utils\format_bytes( $snapshot['size'] ) . ')...' );

		$snapshot_path = Utils\get_snapshot_directory() . $id . '/';

		$download = $repository->getS3()->downloadSnapshot( $id, $snapshot['project'], $snapshot_path . 'data.sql.gz', $snapshot_path . 'files.tar.gz' );

		if ( ! $download ) {
			Log::instance()->write( 'Failed to download snapshot.', 0, 'error' );

			return false;
		}

		/**
		 * Finally save snapshot meta to meta.json
		 */
		$meta_handle = @fopen( $snapshot_path . 'meta.json', 'x' ); // Create file and fail if it exists.

		if ( ! $meta_handle ) {
			Log::instance()->write( 'Could not create meta.json.', 0, 'error' );

			return false;
		}

		fwrite( $meta_handle, json_encode( $snapshot, JSON_PRETTY_PRINT ) );

		return new self( $id, $repository_name, $snapshot, true );
	}

	/**
	 * Push snapshot to repository
	 *
	 * @return boolean
	 */
	public function push() {
		if ( $this->remote ) {
			Log::instance()->write( 'Snapshot already pushed.', 0, 'error' );
			return false;
		}

		$repository = RepositoryManager::instance()->setup( $this->repository_name );

		if ( ! $repository ) {
			Log::instance()->write( 'Could not setup repository.', 0, 'error' );

			return false;
		}

		/**
		 * Put files to S3
		 */
		Log::instance()->write( 'Uploading files (' . Utils\format_bytes( $this->meta['size'] ) . ')...' );

		$s3_add = $repository->getS3()->putSnapshot( $this->id, $this->meta['project'], Utils\get_snapshot_directory() . $this->id . '/data.sql.gz', Utils\get_snapshot_directory() . $this->id . '/files.tar.gz' );

		if ( ! $s3_add ) {
			Log::instance()->write( 'Could not upload files to S3.', 0, 'error' );

			return false;
		}

		/**
		 * Add snapshot to DB
		 */
		Log::instance()->write( 'Adding snapshot to database...' );

		$inserted_snapshot = $repository->getDB()->insertSnapshot( $this->id, $this->meta );

		if ( ! $inserted_snapshot ) {
			Log::instance()->write( 'Could not add snapshot to database.', 0, 'error' );

			return false;
		}

		$this->remote = true;

		return true;
	}
}
