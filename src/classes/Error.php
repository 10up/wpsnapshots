<?php

namespace WPSnapshots;

/**
 * A simple error wrapping class
 */
class Error {

	/**
	 * Construct error
	 *
	 * @param int          $code    Error code
	 * @param string|array $data Error thing
	 */
	public function __construct( $code, $data = '' ) {
		$this->code = $code;
		$this->data = $data;
	}
}
