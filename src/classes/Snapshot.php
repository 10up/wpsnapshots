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
	 * @param string         $id Snapshot id
	 * @param string         $repository_name Name of rpo
	 * @param array|Snapshot $meta Snapshot meta data
	 * @param bool           $remote Does snapshot exist remotely or not
	 * @throws Exception Throw exception if files don't exist.
	 */
	public function __construct( $id, $repository_name, $meta, $remote = false ) {
		$this->id              = $id;
		$this->repository_name = $repository_name;
		$this->remote          = $remote;

		if ( is_a( $meta, '\WPSnapshots\Meta' ) ) {
			$this->meta = $meta;
		} else {
			$this->meta = new Meta( $id, $meta );
		}
	}

	/**
	 * Get a snapshot. First try locally and then remote.
	 *
	 * @param   string $id Snapshot id
	 * @param   string $repository_name Name of repo
	 * @param   bool   $no_files If set to true, files will not be downloaded even if available. Defaults to false.
	 * @param   bool   $no_db If set to true, db will not be downloaded even if available. Defaults to false.
	 * @return  bool|Snapshot
	 */
	public static function get( $id, $repository_name, $no_files = false, $no_db = false ) {
		$local_snapshot = self::getLocal( $id, $repository_name, $no_files, $no_db );

		if ( ! empty( $local_snapshot ) ) {
			Log::instance()->write( 'Snapshot found in cache.' );

			return $local_snapshot;
		}

		return self::getRemote( $id, $repository_name, $no_files, $no_db );
	}

	/**
	 * Given an ID, create a WP Snapshots object
	 *
	 * @param   string $id Snapshot id
	 * @param   string $repository_name Name of repo
	 * @param   bool   $no_files If set to true, files will not be downloaded even if available. Defaults to false.
	 * @param   bool   $no_db If set to true, db will not be downloaded even if available. Defaults to false.
	 * @return  bool|Snapshot
	 */
	public static function getLocal( $id, $repository_name, $no_files = false, $no_db = false ) {
		if ( $no_files && $no_db ) {
			Log::instance()->write( 'Either files or database must be in snapshot.', 0, 'error' );

			return false;
		}

		$meta = Meta::getLocal( $id, $repository_name );

		if ( empty( $meta ) ) {
			return false;
		}

		if ( $no_files ) {
			$meta['contains_files'] = false;
		}

		if ( $no_db ) {
			$meta['contains_db'] = false;
		}

		return new self( $id, $repository_name, $meta );
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

		global $wpdb, $wp_version;

		$meta = new Meta(
			$id,
			[
				'author'         => [],
				'repository'     => $args['repository'],
				'description'    => $args['description'],
				'project'        => $args['project'],
				'contains_files' => $args['contains_files'],
				'contains_db'    => $args['contains_db'],
			]
		);

		$meta['wp_version'] = ( ! empty( $wp_version ) ) ? $wp_version : '';

		$author_info = RepositoryManager::instance()->getAuthorInfo();
		$author      = [];

		if ( ! empty( $author_info['name'] ) ) {
			$author['name'] = $author_info['name'];
		}

		if ( ! empty( $author_info['email'] ) ) {
			$author['email'] = $author_info['email'];
		}

		$meta['author'] = $author;

		$verbose_pipe = ( empty( Log::instance()->getVerbosity() ) ) ? '> /dev/null' : '';

		$snapshot_path = Utils\get_snapshot_directory() . $id . '/';

		if ( $args['contains_db'] ) {

			if ( ! empty( $args['small'] ) ) {
				if ( is_multisite() ) {
					$sites = get_sites();
				} else {
					Log::instance()->write( 'Trimming snapshot data and files...' );
				}

				while ( true ) {
					$prefix = $wpdb->prefix;

					if ( is_multisite() ) {
						if ( empty( $sites ) ) {
							break;
						}

						$site = array_shift( $sites );

						Log::instance()->write( 'Trimming snapshot data and files blog ' . $site->blog_id . '...' );

						switch_to_blog( $site->blog_id );

						$prefix = $wpdb->get_blog_prefix( $site->blog_id );
					}

					// Trim posts
					$post_ids = [];

					$post_types_args = [
						'public'   => false,
						'_builtin' => true,
					];

					$post_types = $wpdb->get_results( "SELECT DISTINCT post_type FROM {$prefix}posts", ARRAY_A );

					if ( ! empty( $post_types ) ) {

						Log::instance()->write( 'Trimming posts...', 1 );

						foreach ( $post_types as $post_type ) {
							$post_type = $post_type['post_type'];

							$posts = $wpdb->get_results( $wpdb->prepare( "SELECT ID FROM {$prefix}posts WHERE post_type='%s' ORDER BY ID DESC LIMIT 300", $post_type ), ARRAY_A );

							foreach ( $posts as $post ) {
								$post_ids[] = (int) $post['ID'];
							}
						}

						if ( ! empty( $post_ids ) ) {
							// Delete other posts
							$wpdb->query( "DELETE FROM {$prefix}posts WHERE ID NOT IN (" . implode( ',', $post_ids ) . ')' );

							// Delete orphan comments
							$wpdb->query( "DELETE FROM {$prefix}comments WHERE comment_post_ID NOT IN (" . implode( ',', $post_ids ) . ')' );

							// Delete orphan meta
							$wpdb->query( "DELETE FROM {$prefix}postmeta WHERE post_id NOT IN (" . implode( ',', $post_ids ) . ')' );
						}
					}

					Log::instance()->write( 'Trimming comments...', 1 );

					$comments = $wpdb->get_results( "SELECT comment_ID FROM {$prefix}comments ORDER BY comment_ID DESC LIMIT 500", ARRAY_A );

					// Delete comments
					if ( ! empty( $comments ) ) {
						$comment_ids = [];

						foreach ( $comments as $comment ) {
							$comment_ids[] = (int) $comment['ID'];
						}

						$wpdb->query( "DELETE FROM {$prefix}comments WHERE comment_ID NOT IN (" . implode( ',', $comment_ids ) . ')' );

						$wpdb->query( "DELETE FROM {$prefix}commentmeta WHERE comment_id NOT IN (" . implode( ',', $comment_ids ) . ')' );
					}

					// Terms
					Log::instance()->write( 'Trimming terms...', 1 );

					$wpdb->query( "DELETE FROM {$prefix}term_relationships WHERE object_id NOT IN (" . implode( ',', array_unique( $post_ids ) ) . ')' );

					$term_relationships = $wpdb->get_results( "SELECT * FROM {$prefix}term_relationships ORDER BY term_taxonomy_id DESC", ARRAY_A );

					if ( ! empty( $term_relationships ) ) {
						$term_taxonomy_ids = [];

						foreach ( $term_relationships as $term_relationship ) {
							$term_taxonomy_ids[] = (int) $term_relationship['term_taxonomy_id'];
						}

						$wpdb->query( "DELETE FROM {$prefix}term_taxonomy WHERE term_taxonomy_id NOT IN (" . implode( ',', array_unique( $term_taxonomy_ids ) ) . ')' );
					}

					$term_taxonomy = $wpdb->get_results( "SELECT * FROM {$prefix}term_taxonomy ORDER BY term_taxonomy_id DESC", ARRAY_A );

					if ( ! empty( $term_taxonomy ) ) {
						$term_ids = [];

						foreach ( $term_taxonomy as $term_taxonomy_row ) {
							$term_ids[] = (int) $term_taxonomy_row['term_id'];
						}

						// Delete excess terms
						$wpdb->query( "DELETE FROM {$prefix}terms WHERE term_id NOT IN (" . implode( ',', array_unique( $term_ids ) ) . ')' );

						// Delete excess term meta
						$wpdb->query( "DELETE FROM {$prefix}termmeta WHERE term_id NOT IN (" . implode( ',', array_unique( $term_ids ) ) . ')' );
					}

					if ( is_multisite() ) {
						restore_current_blog();
					} else {
						break;
					}
				}
			}

			$meta['multisite']            = false;
			$meta['subdomain_install']    = false;
			$meta['domain_current_site']  = false;
			$meta['path_current_site']    = false;
			$meta['site_id_current_site'] = false;
			$meta['blog_id_current_site'] = false;

			$meta_sites = [];

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
					$meta_sites[] = [
						'blog_id'  => $site->blog_id,
						'domain'   => $site->domain,
						'path'     => $site->path,
						'site_url' => get_blog_option( $site->blog_id, 'siteurl' ),
						'home_url' => get_blog_option( $site->blog_id, 'home' ),
						'blogname' => get_blog_option( $site->blog_id, 'blogname' ),
					];
				}
			} else {
				$meta_sites[] = [
					'site_url' => get_option( 'siteurl' ),
					'home_url' => get_option( 'home' ),
					'blogname' => get_option( 'blogname' ),
				];
			}

			$meta['sites'] = $meta_sites;

			$main_blog_id = ( defined( 'BLOG_ID_CURRENT_SITE' ) ) ? BLOG_ID_CURRENT_SITE : null;

			$meta['table_prefix'] = $wpdb->get_blog_prefix( $main_blog_id );

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
				// We separate the users/meta table for scrubbing
				if ( 0 < $args['scrub'] && $GLOBALS['table_prefix'] . 'users' === $table ) {
					continue;
				}

				if ( 2 === $args['scrub'] && $GLOBALS['table_prefix'] . 'usermeta' === $table ) {
					continue;
				}

				$command           .= ' %s';
				$command_esc_args[] = trim( $table );
			}

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

			if ( 1 === $args['scrub'] ) {

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

				Log::instance()->write( 'Scrub severity is 1.', 1 );

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

				Log::instance()->write( 'Removing old users SQL...', 1 );

				unlink( $snapshot_path . 'data-users.sql' );
			} elseif ( 2 === $args['scrub'] ) {
				Log::instance()->write( 'Scrubbing users...' );

				$dummy_users = Utils\get_dummy_users();

				Log::instance()->write( 'Duplicating users table..', 1 );

				$wpdb->query( "CREATE TABLE {$wpdb->users}_temp LIKE $wpdb->users" );
				$wpdb->query( "INSERT INTO {$wpdb->users}_temp SELECT * FROM $wpdb->users" );

				Log::instance()->write( 'Scrub each user record..', 1 );

				$offset = 0;

				$password = wp_hash_password( 'password' );

				$user_ids = [];

				while ( true ) {
					$users = $wpdb->get_results( $wpdb->prepare( "SELECT ID, user_login FROM {$wpdb->users}_temp LIMIT 1000 OFFSET %d", $offset ), ARRAY_A );

					if ( empty( $users ) ) {
						break;
					}

					if ( 1000 <= $offset ) {
						usleep( 100 );
					}

					foreach ( $users as $user ) {
						$user_id = (int) $user['ID'];

						$user_ids[] = $user_id;

						$dummy_user = $dummy_users[ $user_id % 1000 ];

						$wpdb->query( "UPDATE {$wpdb->users}_temp SET user_pass='{$password}', user_email='{$dummy_user['email']}', user_url='', user_activation_key='', display_name='{$user['user_login']}' WHERE ID='{$user['ID']}'" );
					}

					$offset += 1000;
				}

				$command = '/usr/bin/env mysqldump --no-defaults --single-transaction %s';

				$command_esc_args = array( DB_NAME );

				$command           .= ' --tables %s';
				$command_esc_args[] = $GLOBALS['table_prefix'] . 'users_temp';

				$mysql_args = [
					'host'        => DB_HOST,
					'pass'        => DB_PASSWORD,
					'user'        => DB_USER,
					'result-file' => $snapshot_path . 'data-users.sql',
				];

				$escaped_command = call_user_func_array( '\WPSnapshots\Utils\esc_cmd', array_merge( array( $command ), $command_esc_args ) );

				Log::instance()->write( 'Exporting users...', 1 );

				Utils\run_mysql_command( $escaped_command, $mysql_args );

				$users_sql = file_get_contents( $snapshot_path . 'data-users.sql' );

				Log::instance()->write( 'Duplicating user meta table..', 1 );

				$wpdb->query( "CREATE TABLE {$wpdb->usermeta}_temp LIKE $wpdb->usermeta" );
				$wpdb->query( "INSERT INTO {$wpdb->usermeta}_temp SELECT * FROM $wpdb->usermeta" );

				// Just truncate these fields
				$wpdb->query( "UPDATE {$wpdb->usermeta}_temp SET meta_value='' WHERE meta_key='description' OR meta_key='session_tokens'" );

				for ( $i = 0; $i < count( $user_ids ); $i++ ) {
					if ( 1 < $i && 0 === $i % 1000 ) {
						usleep( 100 );
					}

					$user_id = $user_ids[ $i ];

					$dummy_user = $dummy_users[ $user_id % 1000 ];

					$wpdb->query( "UPDATE {$wpdb->usermeta}_temp SET meta_value='{$dummy_user['first_name']}' WHERE meta_key='first_name' AND user_id='{$user_id}'" );
					$wpdb->query( "UPDATE {$wpdb->usermeta}_temp SET meta_value='{$dummy_user['last_name']}' WHERE meta_key='last_name' AND user_id='{$user_id}'" );
					$wpdb->query( "UPDATE {$wpdb->usermeta}_temp SET meta_value='{$dummy_user['first_name']}' WHERE meta_key='nickname' AND user_id='{$user_id}'" );
				}

				$command = '/usr/bin/env mysqldump --no-defaults --single-transaction %s';

				$command_esc_args = array( DB_NAME );

				$command           .= ' --tables %s';
				$command_esc_args[] = $GLOBALS['table_prefix'] . 'usermeta_temp';

				$mysql_args = [
					'host'        => DB_HOST,
					'pass'        => DB_PASSWORD,
					'user'        => DB_USER,
					'result-file' => $snapshot_path . 'data-usermeta.sql',
				];

				$escaped_command = call_user_func_array( '\WPSnapshots\Utils\esc_cmd', array_merge( array( $command ), $command_esc_args ) );

				Log::instance()->write( 'Exporting usermeta...', 1 );

				Utils\run_mysql_command( $escaped_command, $mysql_args );

				$usermeta_sql = file_get_contents( $snapshot_path . 'data-usermeta.sql' );

				Log::instance()->write( 'Appending scrubbed SQL to dump file...', 1 );

				file_put_contents( $snapshot_path . 'data.sql', preg_replace( '#`' . $wpdb->users . '_temp`#', $wpdb->users, $users_sql ) . preg_replace( '#`' . $wpdb->usermeta . '_temp`#', $wpdb->usermeta, $usermeta_sql ), FILE_APPEND );

				Log::instance()->write( 'Removing temporary tables...', 1 );

				$wpdb->query( "DROP TABLE {$wpdb->usermeta}_temp" );
				$wpdb->query( "DROP TABLE {$wpdb->users}_temp" );

				Log::instance()->write( 'Removing old users and usermeta SQL...', 1 );

				unlink( $snapshot_path . 'data-users.sql' );
				unlink( $snapshot_path . 'data-usermeta.sql' );
			}

			Log::instance()->write( 'Compressing database backup...', 1 );

			exec( 'gzip -9 ' . Utils\escape_shell_path( $snapshot_path ) . 'data.sql ' . $verbose_pipe );
		}

		/**
		 * Create file back up of wp-content in .wpsnapshots/files.tar.gz
		 */

		if ( $args['contains_files'] ) {
			Log::instance()->write( 'Saving files...' );

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
		}

		if ( $args['contains_db'] ) {
			$meta['db_size'] = filesize( $snapshot_path . 'data.sql.gz' );
		}

		if ( $args['contains_files'] ) {
			$meta['files_size'] = filesize( $snapshot_path . 'files.tar.gz' );
		}

		/**
		 * Finally save snapshot meta to meta.json
		 */
		$meta->saveLocal();

		$snapshot = new self( $id, $args['repository'], $meta );

		return $snapshot;
	}

	/**
	 * Download snapshot from remote DB.
	 *
	 * @param   string $id Snapshot id
	 * @param   string $repository_name Name of repo
	 * @param   bool   $no_files If set to true, files will not be downloaded even if available. Defaults to false.
	 * @param   bool   $no_db If set to true, db will not be downloaded even if available. Defaults to false.
	 * @return  bool|Snapshot
	 */
	public static function getRemote( $id, $repository_name, $no_files = false, $no_db = false ) {
		if ( $no_files && $no_db ) {
			Log::instance()->write( 'Either files or database must be downloaded.', 0, 'error' );

			return false;
		}

		$create_dir = Utils\create_snapshot_directory( $id, true );

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

		$meta = Meta::getRemote( $id, $repository_name );

		if ( empty( $meta ) ) {
			Log::instance()->write( 'Could not get snapshot from database.', 0, 'error' );

			return false;
		}

		if ( empty( $meta['project'] ) ) {
			Log::instance()->write( 'Missing critical snapshot data.', 0, 'error' );

			return false;
		}

		if ( $no_files ) {
			$meta['contains_files'] = false;
		}

		if ( $no_db ) {
			$meta['contains_db'] = false;
		}

		/**
		 * Backwards compant. Add repository to meta before we started saving it.
		 */
		if ( empty( $meta['repository'] ) ) {
			$meta['repository'] = $repository_name;
		}

		$formatted_size = '';

		if ( empty( $meta['files_size'] ) && empty( $meta['db_size'] ) ) {
			if ( $meta['contains_files'] && $meta['contains_db'] ) {
				$formatted_size = ' (' . Utils\format_bytes( $meta['size'] ) . ')';
			}
		} else {
			$size = (int) $meta['files_size'] + (int) $meta['db_size'];

			$formatted_size = ' (' . Utils\format_bytes( $size ) . ')';
		}

		$snapshot = new self( $id, $repository_name, $meta, true );

		Log::instance()->write( 'Downloading snapshot' . $formatted_size . '...' );

		$download = $repository->getS3()->downloadSnapshot( $snapshot );

		if ( ! $download ) {
			Log::instance()->write( 'Failed to download snapshot.', 0, 'error' );

			return false;
		}

		/**
		 * Finally save snapshot meta to meta.json
		 */

		$save_local = $meta->saveLocal();

		if ( ! $save_local ) {
			Log::instance()->write( 'Could not create .wpsnapshots/' . $id . '/meta.json.', 0, 'error' );

			return false;
		}

		return $snapshot;
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
		Log::instance()->write( 'Uploading files (' . Utils\format_bytes( ( (int) $this->meta['files_size'] + (int) $this->meta['db_size'] ) ) . ')...' );

		$s3_add = $repository->getS3()->putSnapshot( $this );

		if ( ! $s3_add ) {
			Log::instance()->write( 'Could not upload files to S3.', 0, 'error' );

			return false;
		}

		/**
		 * Add snapshot to DB
		 */
		Log::instance()->write( 'Adding snapshot to database...' );

		$inserted_snapshot = $repository->getDB()->insertSnapshot( $this );

		if ( ! $inserted_snapshot ) {
			Log::instance()->write( 'Could not add snapshot to database.', 0, 'error' );

			return false;
		}

		$this->remote = true;

		return true;
	}
}
