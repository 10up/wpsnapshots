<?php
/**
 * Handle search and replace on MySQL tables
 *
 * @package wpsnapshots
 */

namespace WPSnapshots;

use WPSnapshots\Utils;

/**
 * This class runs a search and replace operation on a DB
 */
class SearchReplace {

	/**
	 * Max recursion level for serialized data
	 *
	 * @var int
	 */
	private $max_recursion;

	/**
	 * Constructing the class runs search and replace on the current WP DB
	 *
	 * @param string $old  String to find
	 * @param string $new  Replacement string
	 * @param array  $tables Tables to replace
	 */
	public function __construct( $old, $new, $tables ) {
		global $wpdb;

		$this->max_recursion = intval( ini_get( 'xdebug.max_nesting_level' ) );
		$this->old           = $old;
		$this->new           = $new;
		$this->updated       = 0;

		$skip_columns = [
			'user_pass',
		];

		// @todo: need to remove this hardcode
		$php_only = false;

		foreach ( $tables as $table ) {
			list( $primary_keys, $columns, $all_columns ) = $this->get_columns( $table );

			// since we'll be updating one row at a time,
			// we need a primary key to identify the row
			if ( empty( $primary_keys ) ) {
				continue;
			}

			$table_sql = $this->esc_sql_ident( $table );

			foreach ( $columns as $col ) {
				if ( in_array( $col, $skip_columns ) ) {
					continue;
				}

				if ( ! $php_only ) {
					$col_sql = $this->esc_sql_ident( $col );

					$wpdb->last_error = '';

					$serial_row = $wpdb->get_row( "SELECT * FROM $table_sql WHERE $col_sql REGEXP '^[aiO]:[1-9]' LIMIT 1" );

					// When the regex triggers an error, we should fall back to PHP
					if ( false !== strpos( $wpdb->last_error, 'ERROR 1139' ) ) {
						$serial_row = true;
					}
				}

				if ( $php_only || null !== $serial_row ) {
					$updated = $this->php_handle_col( $col, $primary_keys, $table );
				} else {
					$updated = $this->sql_handle_col( $col, $table );
				}

				$this->updated += $updated;
			}
		}
	}

	/**
	 * Get columns for a table organizing by text/primary.
	 *
	 * @param  string $table Table name
	 * @return array
	 */
	private function get_columns( $table ) {
		global $wpdb;

		$table_sql    = $this->esc_sql_ident( $table );
		$primary_keys = $text_columns = $all_columns = array();

		foreach ( $wpdb->get_results( "DESCRIBE $table_sql" ) as $col ) {
			if ( 'PRI' === $col->Key ) {
				$primary_keys[] = $col->Field;
			}

			if ( $this->is_text_col( $col->Type ) ) {
				$text_columns[] = $col->Field;
			}

			$all_columns[] = $col->Field;
		}

		return array( $primary_keys, $text_columns, $all_columns );
	}

	/**
	 * Check if column type is text
	 *
	 * @param  string $type Column type
	 * @return boolean
	 */
	private function is_text_col( $type ) {
		foreach ( array( 'text', 'varchar' ) as $token ) {
			if ( false !== strpos( $type, $token ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Escape SQL identifiers i.e. table name, column name with backticks
	 *
	 * @param  string|array $idents Identifiers
	 * @return string|array
	 */
	private function esc_sql_ident( $idents ) {
		$backtick = function( $v ) {
			// Escape any backticks in the identifier by doubling.
			return '`' . str_replace( '`', '``', $v ) . '`';
		};

		if ( is_string( $idents ) ) {
			return $backtick( $idents );
		}

		return array_map( $backtick, $idents );
	}

	/**
	 * Handle search/replace with only SQL. This won't work for serialized data. Returns
	 * number of updates made.
	 *
	 * @param  string $col Column name
	 * @param  string $table Table name
	 * @return int
	 */
	private function sql_handle_col( $col, $table ) {
		global $wpdb;

		$table_sql = $this->esc_sql_ident( $table );
		$col_sql   = $this->esc_sql_ident( $col );

		$count = $wpdb->query( $wpdb->prepare( "UPDATE $table_sql SET $col_sql = REPLACE($col_sql, %s, %s);", $this->old, $this->new ) );

		return $count;
	}

	/**
	 * Use PHP to run search and replace on a column. This is mostly used for serialized data.
	 * Returns number of updates made
	 *
	 * @param  string $col Column name
	 * @param  array  $primary_keys Array of keys
	 * @param  string $table Table name
	 * @return int
	 */
	private function php_handle_col( $col, $primary_keys, $table ) {
		global $wpdb;

		$count = 0;

		$table_sql = $this->esc_sql_ident( $table );
		$col_sql   = $this->esc_sql_ident( $col );

		$where            = " WHERE $col_sql" . $wpdb->prepare( ' LIKE %s', '%' . $wpdb->esc_like( $this->old ) . '%' );
		$primary_keys_sql = implode( ',', $this->esc_sql_ident( $primary_keys ) );

		$rows = $wpdb->get_results( "SELECT {$primary_keys_sql} FROM {$table_sql} {$where}" );

		foreach ( $rows as $keys ) {
			$where_sql = '';

			foreach ( (array) $keys as $k => $v ) {
				if ( strlen( $where_sql ) ) {
					$where_sql .= ' AND ';
				}
				$where_sql .= $this->esc_sql_ident( $k ) . ' = ' . esc_sql( $v );
			}

			$col_value = $wpdb->get_var( "SELECT {$col_sql} FROM {$table_sql} WHERE {$where_sql}" );

			if ( '' === $col_value ) {
				continue;
			}

			$value = $this->php_search_replace( $col_value, false );

			if ( $value === $col_value ) {
				continue;
			}

			$where = array();

			foreach ( (array) $keys as $k => $v ) {
				$where[ $k ] = $v;
			}

			$count += $wpdb->update( $table, array( $col => $value ), $where );
		}

		return $count;
	}

	/**
	 * Perform php search and replace on data. Returns updated data
	 *
	 * @param  string|object|array $data Data to search
	 * @param  bool                $serialised Serialized data or not
	 * @param  integer             $recursion_level Recursion level
	 * @param  array               $visited_data Array of visited data
	 * @return string|object|array
	 */
	private function php_search_replace( $data, $serialised, $recursion_level = 0, $visited_data = array() ) {
		// some unseriliased data cannot be re-serialised eg. SimpleXMLElements
		try {
			// If we've reached the maximum recursion level, short circuit
			if ( $this->max_recursion !== 0 && $recursion_level >= $this->max_recursion ) { // @codingStandardsIgnoreLine
				return $data;
			}
			if ( is_array( $data ) || is_object( $data ) ) {
				// If we've seen this exact object or array before, short circuit
				if ( in_array( $data, $visited_data, true ) ) {
					return $data; // Avoid infinite loops when there's a cycle
				}
				// Add this data to the list of
				$visited_data[] = $data;
			}

			if ( is_string( $data ) && ( $unserialized = @unserialize( $data ) ) !== false ) {
				$data = $this->php_search_replace( $unserialized, true, $recursion_level + 1 );
			} elseif ( is_array( $data ) ) {
				$keys = array_keys( $data );
				foreach ( $keys as $key ) {
					$data[ $key ] = $this->php_search_replace( $data[ $key ], false, $recursion_level + 1, $visited_data );
				}
			} elseif ( is_object( $data ) ) {
				foreach ( $data as $key => $value ) {
					$data->$key = $this->php_search_replace( $value, false, $recursion_level + 1, $visited_data );
				}
			} elseif ( is_string( $data ) ) {
				$data = str_replace( $this->old, $this->new, $data );
			}
			if ( $serialised ) {
				return serialize( $data );
			}
		} catch ( Exception $error ) {
			// Do nothing
		}

		return $data;
	}
}
