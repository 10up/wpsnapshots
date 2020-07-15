<?php
/**
 * Utility functions
 *
 * @package  wpsnapshots
 */

namespace WPSnapshots\Utils;

use Requests;

/**
 * Test MySQL connection
 *
 * @param  string $host     DB host
 * @param  string $database DB name
 * @param  string $user     User
 * @param  string $password Password
 * @return bool
 */
function test_mysql_connection( $host, $database, $user, $password ) {
	$mysqli = mysqli_init();

	return ( ! @$mysqli->real_connect( $host, $user, $password, $database ) ) ? mysqli_connect_error() : true;
}

/**
 * Get all constants in wp-config.php
 *
 * @param  string $wp_config_path Path to wp-config.php
 * @return array
 */
function get_wp_config_constants( $wp_config_path ) {
	$wp_config_code = explode( "\n", file_get_contents( $wp_config_path ) );
	$constants      = [];

	foreach ( $wp_config_code as $line ) {
		if ( preg_match( '#define\(.*?("|\')(.*?)("|\').*?\).*?;#', $line ) ) {
			$constant_name = preg_replace( '#^.*?define\(.*?("|\')(.*?)("|\').*$#', '$2', $line );

			$constants[ $constant_name ] = trim( preg_replace( '#^.*?define\(.*?("|\').*?("|\').*?,(.*)\).*?;.*$#', '$3', $line ), ' ' );

			if ( preg_match( '#^".*"$#', $constants[ $constant_name ] ) ) {
				$constants[ $constant_name ] = preg_replace( '#^"(.*)"$#', '$1', $constants[ $constant_name ] );
			} elseif ( preg_match( "#^'.*'$#", $constants[ $constant_name ] ) ) {
				$constants[ $constant_name ] = preg_replace( "#^'(.*)'$#", '$1', $constants[ $constant_name ] );
			}

			// Appropriately cast variables
			if ( is_numeric( $constants[ $constant_name ] ) ) {
				if ( false !== strpos( $constants[ $constant_name ], '.' ) ) {
					$constants[ $constant_name ] = (double) $constants[ $constant_name ];
				} else {
					$constants[ $constant_name ] = (int) $constants[ $constant_name ];
				}
			} elseif ( 'false' === strtolower( $constants[ $constant_name ] ) ) {
				$constants[ $constant_name ] = false;
			} elseif ( 'true' === strtolower( $constants[ $constant_name ] ) ) {
				$constants[ $constant_name ] = true;
			}
		}
	}

	return $constants;
}

/**
 * Write constants to wp-config.php ensuring the same constants don't get written twice.
 *
 * @param  array  $constants       Constants array
 * @param  string $wp_config_path Path to wp-config.php
 */
function write_constants_to_wp_config( $constants, $wp_config_path ) {
	$wp_config_code     = explode( "\n", file_get_contents( $wp_config_path ) );
	$new_wp_config_code = [];

	foreach ( $wp_config_code as $line ) {
		// We'll add this back later
		if ( preg_match( '#^<\?php.*#i', $line ) ) {
			continue;
		}

		// Don't readd lines that contain constants we are defining
		if ( preg_match( '#define\(.*?("|\')(.*?)("|\').*?\).*?;#', $line ) ) {
			$constant_name = preg_replace( '#^.*?define\(.*?("|\')(.*?)("|\').*$#', '$2', $line );

			if ( ! empty( $constants[ $constant_name ] ) ) {
				continue;
			}
		}

		$new_wp_config_code[] = $line;
	}

	foreach ( $constants as $constant_name => $constant_value ) {
		if ( false === $constant_value ) {
			$constant_value = 'false';
		} elseif ( true === $constant_value ) {
			$constant_value = 'true';
		} elseif ( is_string( $constant_value ) ) {
			$constant_value = addcslashes( $constant_value, "'" );

			$constant_value = "'$constant_value'";
		}

		array_unshift( $new_wp_config_code, 'define( "' . $constant_name . '", ' . $constant_value . ' ); // Auto added.' );
	}

	array_unshift( $new_wp_config_code, '<?php' );

	file_put_contents( $wp_config_path, implode( "\n", $new_wp_config_code ) );
}

/**
 * Add trailing slash to path
 *
 * @param  string $path Path
 * @return string
 */
function trailingslash( $path ) {
	return rtrim( $path, '/' ) . '/';
}

/**
 * Normalizes paths. Note that we DO always add a trailing slash here
 *
 * /
 * ./
 * ~/
 * ./test/
 * ~/test
 * test
 *
 * @param  string $path Path to normalize
 * @return string
 */
function normalize_path( $path ) {
	$path = trim( $path );

	if ( '/' === $path ) {
		return $path;
	}

	/**
	 * Prepend ./ to non absolute paths
	 */
	if ( preg_match( '#[^\./\\\~]#i', substr( $path, 0, 1 ) ) ) {
		$path = './' . $path;
	}

	/**
	 * Make non-absolute path absolute
	 */
	if ( './' === substr( $path, 0, 2 ) ) {
		$path = rtrim( getcwd(), '/' ) . '/' . substr( $path, 2 );
	}

	/**
	 * Replace ~ with home directory
	 */
	if ( '~' === substr( $path, 0, 1 ) ) {
		$path = ltrim( $path, '~' );

		$home = rtrim( $_SERVER['HOME'], '/' );

		$path = $home . $path;
	}

	return trailingslash( $path );
}

/**
 * Escape a path that will be passed to a shell
 *
 * @param  string $path Path to escape
 * @return string
 */
function escape_shell_path( $path ) {
	return str_replace( ' ', '\ ', $path );
}

/**
 * Validator for Symfony Question
 *
 * @param  string $answer Answer to check
 * @throws \RuntimeException Exception to throw if answer isn't valid.
 * @return string
 */
function not_empty_validator( $answer ) {
	if ( '' === trim( $answer ) ) {
		throw new \RuntimeException(
			'A valid answer is required.'
		);
	}

	return $answer;
}

/**
 * Validator for slugs
 *
 * @param  string $answer Answer to validate
 * @throws \RuntimeException Exception to throw if answer isn't valid.
 * @return string
 */
function slug_validator( $answer ) {
	if ( ! preg_match( '#^[a-z0-9\-_]+$#i', $answer ) ) {
		throw new \RuntimeException(
			'A valid non-empty slug is required (letters, numbers, -, and _).'
		);
	}

	return strtolower( $answer );
}

/**
 * Create a wp-config.php with constants based on a template file
 *
 * @param  string $path Path to WP root.
 * @param  string $path_to_template Path to config template
 * @param  array  $constants Array of constants
 */
function create_config_file( $path, $path_to_template, $constants = [] ) {
	$template = file_get_contents( $path_to_template );

	$template_code = explode( "\n", $template );
	$new_file      = [];

	foreach ( $template_code as $line ) {
		if ( preg_match( '/^\s*require.+wp-settings\.php/', $line ) ) {
			continue;
		}

		foreach ( $constants as $config_constant => $config_constant_value ) {
			if ( preg_match( '#define\(.*?("|\')' . $config_constant . '("|\').*?\).*?;#', $line ) ) {
				continue 2;
			}
		}

		$new_file[] = $line;
	}

	foreach ( $constants as $config_constant => $config_constant_value ) {
		if ( ! is_bool( $config_constant_value ) && ! is_int( $config_constant_value ) ) {
			$config_constant_value = "'" . addslashes( $config_constant_value ) . "'";
		}

		$new_file[] = "define( '" . addslashes( $config_constant ) . "', $config_constant_value );";
	}

	$new_file[] = "require_once(ABSPATH . 'wp-settings.php');";

	file_put_contents( $path, implode( "\n", $new_file ) );
}

/**
 * Get download url for WP
 *
 * @param  string $version WP version
 * @param  string $locale Language locale
 * @return string|bool
 */
function get_download_url( $version = 'latest', $locale = 'en_US' ) {
	if ( 'nightly' === $version ) {
		return 'https://wordpress.org/nightly-builds/wordpress-latest.zip';
	}

	if ( 'latest' === $version ) {
		$headers = [ 'Accept' => 'application/json' ];

		try {
			$request = Requests::get( 'https://api.wordpress.org/core/version-check/1.6/?locale=' . $locale, $headers );
		} catch ( \Exception $e ) {
			return false;
		}

		if ( 200 !== (int) $request->status_code ) {
			return false;
		}

		$request_body = unserialize( $request->body );

		if ( empty( $request_body['offers'] ) || empty( $request_body['offers'][0] ) || empty( $request_body['offers'][0]['download'] ) ) {
			return false;
		}

		return str_replace( '.zip', '.tar.gz', $request_body['offers'][0]['download'] );
	}

	if ( 'en_US' === $locale ) {
		$url = 'https://wordpress.org/wordpress-' . $version . '.tar.gz';

		return $url;
	} else {
		$url = sprintf(
			'https://%s.wordpress.org/wordpress-%s-%s.tar.gz',
			substr( $locale, 0, 2 ),
			$version,
			$locale
		);

		return $url;
	}
}

/**
 * Is WordPress in the directory?
 *
 * @param  string $path Path to WordPress directory
 * @return boolean
 */
function is_wp_present( $path ) {
	return ( file_exists( trailingslash( $path ) . 'wp-settings.php' ) );
}

/**
 * Find wp-config.php
 *
 * @param string $path Path to search for wp-config.php
 * @return string
 */
function locate_wp_config( $path ) {
	$path = trailingslash( $path );

	if ( file_exists( $path . 'wp-config.php' ) ) {
		$path = $path . 'wp-config.php';
	} elseif ( file_exists( $path . '../wp-config.php' ) ) {
		$path = $path . '../wp-config.php';
	} else {
		return false;
	}

	return realpath( $path );
}

/**
 * Create snapshots cache. Providing an id creates the subdirectory as well.
 *
 * @param  string $id   Optional ID. Setting this will create the snapshot directory.
 * @param  bool   $hard Overwrite an existing snapshot
 * @return bool
 */
function create_snapshot_directory( $id = null, $hard = false ) {
	if ( ! file_exists( get_snapshot_directory() ) ) {
		$dir_result = @mkdir( get_snapshot_directory(), 0755 );

		if ( ! $dir_result ) {
			return false;
		}
	}

	if ( ! is_writable( get_snapshot_directory() ) ) {
		return false;
	}

	if ( ! empty( $id ) ) {
		if ( $hard && file_exists( get_snapshot_directory() . $id . '/' ) ) {
			array_map( 'unlink', glob( get_snapshot_directory() . $id . '/*.*' ) );
			$rm_result = rmdir( get_snapshot_directory() . $id . '/' );

			if ( ! $rm_result ) {
				return false;
			}
		}

		if ( ! file_exists( get_snapshot_directory() . $id . '/' ) ) {
			$dir_result = mkdir( get_snapshot_directory() . $id . '/', 0755 );

			if ( ! $dir_result ) {
				return false;
			}
		}

		if ( ! is_writable( get_snapshot_directory() . $id . '/' ) ) {
			return false;
		}
	}

	return true;
}

/**
 * Get path to snapshot cache directory with trailing slash. If env variable WPSNAPSHOTS_DIR is
 * set, then use that.
 *
 * @return string
 */
function get_snapshot_directory() {
	$env_dir = getenv( 'WPSNAPSHOTS_DIR' );

	return ( ! empty( $env_dir ) ) ? rtrim( $env_dir, '/' ) . '/' : rtrim( $_SERVER['HOME'], '/' ) . '/.wpsnapshots/';
}

/**
 * Generate unique snapshot ID
 *
 * @return string
 */
function generate_snapshot_id() {
	return md5( time() . '' . rand() );
}

/**
 * Check if snapshot is in cache
 *
 * @param  string $id Snapshot id
 * @return boolean
 */
function is_snapshot_cached( $id ) {
	if ( ! file_exists( get_snapshot_directory() . $id . '/data.sql.gz' ) || ! file_exists( get_snapshot_directory() . $id . '/files.tar.gz' ) ) {
		return false;
	}

	return true;
}

/**
 * Run MySQL command via proc given associative command line args
 *
 * @param  string $cmd MySQL command
 * @param  array  $assoc_args Args to pass to MySQL
 * @param  string $append String to append to command
 * @param  bool   $exit_on_error Whether to exit on error or not.
 * @return string
 */
function run_mysql_command( $cmd, $assoc_args, $append = '', $exit_on_error = true ) {
	check_proc_available( 'run_mysql_command' );

	if ( isset( $assoc_args['host'] ) ) {
		$assoc_args = array_merge( $assoc_args, mysql_host_to_cli_args( $assoc_args['host'] ) );
	}

	$pass = $assoc_args['pass'];
	unset( $assoc_args['pass'] );

	$old_pass = getenv( 'MYSQL_PWD' );
	putenv( 'MYSQL_PWD=' . $pass );

	$final_cmd = force_env_on_nix_systems( $cmd ) . assoc_args_to_str( $assoc_args ) . $append;

	$proc = proc_open( $final_cmd, [ STDIN, STDOUT, STDERR ], $pipes );

	if ( $exit_on_error && ! $proc ) {
		exit( 1 );
	}

	$r = proc_close( $proc );

	putenv( 'MYSQL_PWD=' . $old_pass );

	if ( $exit_on_error ) {
		if ( $r ) {
			exit( $r );
		}
	} else {
		return $r;
	}
}

/**
 * Returns tables
 *
 * @param  bool $wp Whether to only return WP tables
 * @return array
 */
function get_tables( $wp = true ) {
	global $wpdb;

	$tables = [];

	$results = $wpdb->get_results( 'SHOW TABLES', ARRAY_A );

	foreach ( $results as $table_info ) {
		$table_info = array_values( $table_info );
		$table      = $table_info[0];

		if ( $wp ) {
			if ( 0 === strpos( $table, $GLOBALS['table_prefix'] ) ) {
				$tables[] = $table;
			}
		} else {
			$tables[] = $table;
		}
	}

	return $tables;
}

/**
 * Translate mysql host to cli args
 *
 * @param  string $raw_host Host string
 * @return array
 */
function mysql_host_to_cli_args( $raw_host ) {
	$assoc_args = array();
	$host_parts = explode( ':', $raw_host );

	if ( count( $host_parts ) == 2 ) {
		list( $assoc_args['host'], $extra ) = $host_parts;
		$extra                              = trim( $extra );

		if ( is_numeric( $extra ) ) {
			$assoc_args['port']     = intval( $extra );
			$assoc_args['protocol'] = 'tcp';
		} elseif ( '' !== $extra ) {
			$assoc_args['socket'] = $extra;
		}
	} else {
		$assoc_args['host'] = $raw_host;
	}

	return $assoc_args;
}

/**
 * Shell escape command as an array
 *
 * @param  array $cmd Shell command
 * @return array
 */
function esc_cmd( $cmd ) {
	if ( func_num_args() < 2 ) {
		trigger_error( 'esc_cmd() requires at least two arguments.', E_USER_WARNING );
	}

	$args = func_get_args();
	$cmd  = array_shift( $args );

	return vsprintf( $cmd, array_map( 'escapeshellarg', $args ) );
}

/**
 * Make sure env path is used on *nix
 *
 * @param  string $command Command string.
 * @return string
 */
function force_env_on_nix_systems( $command ) {
	$env_prefix     = '/usr/bin/env ';
	$env_prefix_len = strlen( $env_prefix );

	if ( is_windows() ) {
		if ( 0 === strncmp( $command, $env_prefix, $env_prefix_len ) ) {
			$command = substr( $command, $env_prefix_len );
		}
	} else {
		if ( 0 !== strncmp( $command, $env_prefix, $env_prefix_len ) ) {
			$command = $env_prefix . $command;
		}
	}

	return $command;
}

/**
 * Determine if we are on windows
 *
 * @return bool
 */
function is_windows() {
	return strtoupper( substr( PHP_OS, 0, 3 ) ) === 'WIN';
}

/**
 * Convert assoc array to string to command
 *
 * @param  array $assoc_args Associative args
 * @return string
 */
function assoc_args_to_str( $assoc_args ) {
	$str = '';

	foreach ( $assoc_args as $key => $value ) {
		if ( true === $value ) {
			$str .= " --$key";
		} elseif ( is_array( $value ) ) {
			foreach ( $value as $_ => $v ) {
				$str .= assoc_args_to_str( array( $key => $v ) );
			}
		} else {
			$str .= " --$key=" . escapeshellarg( $value );
		}
	}

	return $str;
}

/**
 * Escape sql name e.g. table name
 *
 * @param  string $name Name to escape
 * @return string
 */
function esc_sql_name( $name ) {
	return preg_replace( '#["\'`]#', '', $name );
}

/**
 * Format bytes to pretty file size
 *
 * @param  int $size     Number of bytes
 * @param  int $precision Decimal precision
 * @return string
 */
function format_bytes( $size, $precision = 2 ) {
	$base     = log( $size, 1024 );
	$suffixes = [ '', 'KB', 'MB', 'GB', 'TB' ];

	return round( pow( 1024, $base - floor( $base ) ), $precision ) . ' ' . $suffixes[ floor( $base ) ];
}

/**
 * Determine if proc is available
 *
 * @return bool
 */
function check_proc_available() {
	if ( ! function_exists( 'proc_open' ) || ! function_exists( 'proc_close' ) ) {
		return false;
	}

	return true;
}

/**
 * Get array of dummy user data to use for scrubbing
 *
 * @return array
 */
function get_dummy_users() {
	static $users = [];

	if ( empty( $users ) ) {
		$file = fopen( __DIR__ . '/data/users.csv', 'r' );

		while ( false !== ( $line = fgetcsv( $file ) ) ) {

			$user = [
				'username'   => $line[0],
				'first_name' => $line[1],
				'last_name'  => $line[2],
				'email'      => $line[3],
			];

			$users[] = $user;
		}

		fclose( $file );
	}

	return $users;
}
