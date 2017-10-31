<?php

namespace WPSnapshots;

/**
 * A simple error wrapping class
 */
class Error {

	/**
	 * Construct error
	 *
	 * @param int    $code    Error code
	 * @param string|array $message Error message
	 */
	public function __construct( $code, $message = '' ) {
		$this->code = $code;
		$this->message = $message;
	}
}
