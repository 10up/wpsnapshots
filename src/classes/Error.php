<?php

namespace WPProjects;

/**
 * A simple error wrapping class
 */
class Error {

	/**
	 * Construct error
	 * @param int    $code    Error code
	 * @param string $message Error message
	 */
	public function __construct( $code, $message = '' ) {
		$this->code = $code;
		$this->message = $message;
	}
}
