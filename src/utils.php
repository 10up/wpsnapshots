<?php

namespace WPSnapshots\Utils;

use WPSnapshots\Error;

/**
 * Check if object is of type Error
 *
 * @param  Object  $obj
 * @return boolean
 */
function is_error( $obj ) {
	return ( $obj instanceof Error );
}

/**
 * Normalizes paths. Note that we DO always add a trailing slash here
 *
 * /
 * ./
 * ~/
 * ./test/
 * ~/test
 *
 * @param  [type] $path [description]
 * @return [type]       [description]
 */
function normalize_path( $path ) {
	$path = trim( $path );

	if ( '/' === $path ) {
		return $path;
	}

	if ( '/' !== substr( $path, -1 ) ) {
		$path .= '/';
	}

	/**
	 * Replace ~ with home directory
	 */
	if ( '~' === substr( $path, 0, 1 ) ) {
		$path = ltrim( $path, '~' );

		$home = rtrim( $_SERVER['HOME'], '/' );

		$path = $home . $path;
	}

	return $path;
}

/**
 * Find wp-config.php
 *
 * @return string
 */
function locate_wp_config( $path ) {
	if ( file_exists( $path . 'wp-config.php' ) ) {
		return $path . 'wp-config.php';
	} elseif ( file_exists( $path . '../wp-config.php' ) ) {
		return $path . '../wp-config.php';
	} else {
		return false;
	}
}

/**
 * Remove .wpsnapshots temp folder. The folder stores temporary backup files
 *
 * @return Error|bool
 */
function remove_temp_folder( $path ) {
	$temp_path = $path . '.wpsnapshots';

	if ( file_exists( $temp_path ) ) {
		try {
			foreach ( glob( $temp_path . '/{,.}*', GLOB_BRACE ) as $filename ) {
			    if ( is_file( $filename ) ) {
			        unlink( $filename );
			    }
			}

			rmdir( $temp_path );
		} catch ( \Exception $e ) {
			return new Error( 0, 'Could not remove dir' );
		}
	}

	return true;
}

/**
 * Run MySQL command via proc given associative command line args
 *
 * @param  string $cmd
 * @param  array  $assoc_args
 */
function run_mysql_command( $cmd, $assoc_args, $append = '' ) {
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

	if ( ! $proc ) {
		exit( 1 );
	}

	$r = proc_close( $proc );

	putenv( 'MYSQL_PWD=' . $old_pass );

	if ( $r ) {
		exit( $r );
	}
}

/**
 * Returns tables
 *
 * @return array
 */
function get_tables( $wp = true ) {
	global $wpdb;

	$tables = [];

	$results = $wpdb->get_results( 'SHOW TABLES', ARRAY_A );

	foreach ( $results as $table_info ) {
		$table_info = array_values( $table_info );
		$table = $table_info[0];

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

function mysql_host_to_cli_args( $raw_host ) {
	$assoc_args = array();
	$host_parts = explode( ':',  $raw_host );

	if ( count( $host_parts ) == 2 ) {
		list( $assoc_args['host'], $extra ) = $host_parts;
		$extra = trim( $extra );

		if ( is_numeric( $extra ) ) {
			$assoc_args['port'] = intval( $extra );
			$assoc_args['protocol'] = 'tcp';
		} else if ( $extra !== '' ) {
			$assoc_args['socket'] = $extra;
		}
	} else {
		$assoc_args['host'] = $raw_host;
	}

	return $assoc_args;
}

function esc_cmd( $cmd ) {
	if ( func_num_args() < 2 ) {
		trigger_error( 'esc_cmd() requires at least two arguments.', E_USER_WARNING );
	}

	$args = func_get_args();
	$cmd = array_shift( $args );

	return vsprintf( $cmd, array_map( 'escapeshellarg', $args ) );
}

function force_env_on_nix_systems( $command ) {
	$env_prefix = '/usr/bin/env ';
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

function assoc_args_to_str( $assoc_args ) {
	$str = '';

	foreach ( $assoc_args as $key => $value ) {
		if ( true === $value ) {
			$str .= " --$key";
		} elseif( is_array( $value ) ) {
			foreach( $value as $_ => $v ) {
				$str .= assoc_args_to_str( array( $key => $v ) );
			}
		} else {
			$str .= " --$key=" . escapeshellarg( $value );
		}
	}

	return $str;
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
