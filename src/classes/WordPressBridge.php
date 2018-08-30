<?php
/**
 * Start up WP so we can use it's functions
 *
 * @package wpsnapshots
 */

namespace WPSnapshots;

/**
 * Bridge to Wordpress
 */
class WordPressBridge {

	/**
	 * Singleton
	 */
	private function __construct() { }

	/**
	 * Bootstrap WordPress just enough to get what we need.
	 *
	 * This method loads wp-config.php without wp-settings.php ensuring we get the constants we need. It
	 * also loads $wpdb so we can perform database operations.
	 *
	 * @param string $path Path to WordPress
	 * @param array  $extra_config_constants Array of extra constants
	 */
	public function load( $path, $extra_config_constants = [] ) {
		$wp_config_code = explode( "\n", file_get_contents( Utils\locate_wp_config( $path ) ) );

		$found_wp_settings = false;
		$lines_to_run      = [];

		if ( file_exists( $path . 'wp-config.php' ) ) {
			$path_replacements = [
				'__FILE__' => "'" . $path . "wp-config.php'",
				'__DIR__'  => "'" . $path . "'",
			];
		} else {
			// Must be one directory up
			$path_replacements = [
				'__FILE__' => "'" . dirname( $path ) . "/wp-config.php'",
				'__DIR__'  => "'" . dirname( $path ) . "'",
			];
		}

		foreach ( $wp_config_code as $line ) {
			if ( preg_match( '/^\s*require.+wp-settings\.php/', $line ) ) {
				continue;
			}

			/**
			 * Don't execute override constants
			 */
			foreach ( $extra_config_constants as $config_constant => $config_constant_value ) {
				if ( preg_match( '#define\(.*?("|\')' . $config_constant . '("|\').*?\).*?;#', $line ) ) {
					continue 2;
				}
			}

			/**
			 * Swap path related constants so we can run WP as a composer dependancy
			 */
			$line = str_replace( array_keys( $path_replacements ), array_values( $path_replacements ), $line );

			$lines_to_run[] = $line;
		}

		$source = implode( "\n", $lines_to_run );

		define( 'ABSPATH', $path );

		/**
		 * Set constant for instances in theme or plugin code that may prevent wpsnapshots from executing properly.
		 */
		define( 'WPSNAPSHOTS', true );

		/**
		 * Define some server variables we might need
		 */
		$_SERVER['REMOTE_ADDR'] = '1.1.1.1';

		/**
		 * Add in override constants
		 */
		foreach ( $extra_config_constants as $config_constant => $config_constant_value ) {
			define( $config_constant, $config_constant_value );
		}

		eval( preg_replace( '|^\s*\<\?php\s*|', '', $source ) );

		if ( defined( 'DOMAIN_CURRENT_SITE' ) ) {
			$url = DOMAIN_CURRENT_SITE;
			if ( defined( 'PATH_CURRENT_SITE' ) ) {
				$url .= PATH_CURRENT_SITE;
			}

			$url_parts = parse_url( $url );

			if ( ! isset( $url_parts['scheme'] ) ) {
				$url_parts = parse_url( 'http://' . $url );
			}

			if ( isset( $url_parts['host'] ) ) {
				if ( isset( $url_parts['scheme'] ) && 'https' === strtolower( $url_parts['scheme'] ) ) {
					$_SERVER['HTTPS'] = 'on';
				}

				$_SERVER['HTTP_HOST'] = $url_parts['host'];
				if ( isset( $url_parts['port'] ) ) {
					$_SERVER['HTTP_HOST'] .= ':' . $url_parts['port'];
				}

				$_SERVER['SERVER_NAME'] = $url_parts['host'];
			}

			$_SERVER['REQUEST_URI']  = $url_parts['path'] . ( isset( $url_parts['query'] ) ? '?' . $url_parts['query'] : '' );
			$_SERVER['SERVER_PORT']  = ( isset( $url_parts['port'] ) ) ? $url_parts['port'] : 80;
			$_SERVER['QUERY_STRING'] = ( isset( $url_parts['query'] ) ) ? $url_parts['query'] : '';
		}

		// Test DB connect
		$connection = Utils\test_mysql_connection( DB_HOST, DB_NAME, DB_USER, DB_PASSWORD );

		if ( true !== $connection ) {
			if ( false !== strpos( $connection, 'php_network_getaddresses' ) ) {
				Log::instance()->write( "Couldn't connect to MySQL host. Try running this command again with the --db_host=127.0.0.1 parameter.", 0, 'error' );
			} else {
				Log::instance()->write( 'Could not connect to MySQL. Is your connection info correct?', 0, 'error' );
			}

			exit;
		}

		// We can require settings after we fake $_SERVER keys
		require_once ABSPATH . 'wp-settings.php';
	}

	/**
	 * Return singleton instance of class
	 *
	 * @return object
	 */
	public static function instance() {
		static $instance;

		if ( empty( $instance ) ) {
			$instance = new self();
		}

		return $instance;
	}
}
