<?php
/**
 * Log messages within application.
 *
 * @package wpsnapshots
 */

namespace WPSnapshots;

use Symfony\Component\Console\Output\OutputInterface;

/**
 * This class lets us easily log to the console or not.
 */
class Log {
	/**
	 * Output to write to
	 *
	 * @var OutputInterface
	 */
	protected $output;

	/**
	 * Log to store to
	 *
	 * @var array
	 */
	protected $log = [];

	/**
	 * Output verbosity
	 *
	 * @var int
	 */
	protected $verbosity = 0;

	/**
	 * Verbosity offset
	 *
	 * @var  int
	 */
	protected $verbosity_offset = 0;

	/**
	 * Singleton
	 */
	private function __construct() { }

	/**
	 * Do we want to log to the console? If so, set the output interface.
	 *
	 * @param OutputInterface $output Output to log to.
	 */
	public function setOutput( OutputInterface $output ) {
		$this->output = $output;

		if ( $output->isDebug() ) {
			$this->verbosity = 3;
		} elseif ( $output->isVeryVerbose() ) {
			$this->verbosity = 2;
		} elseif ( $output->isVerbose() ) {
			$this->verbosity = 1;
		}
	}

	/**
	 * Verbosity offset lets us make normal output verbose if we are using this logger
	 * within another application.
	 *
	 * @param int $verbosity_offset Offset number
	 */
	public function setVerbosityOffset( $verbosity_offset ) {
		$this->verbosity_offset = (int) $verbosity_offset;
	}

	/**
	 * Write to log
	 *
	 * @param  string $message String to write
	 * @param  int    $verbosity_level Verbosity level. See https://symfony.com/doc/current/console/verbosity.html
	 * @param  string $type Either 'info', 'success', 'warning', 'error'
	 * @param  array  $data Arbitrary data to write
	 * @return array
	 */
	public function write( $message, $verbosity_level = 0, $type = 'info', $data = [] ) {
		$verbosity_level += $this->verbosity_offset;

		$entry = [
			'message'         => $message,
			'data'            => $data,
			'type'            => $type,
			'verbosity_level' => $verbosity_level,
		];

		$this->log[] = $entry;

		if ( ! empty( $this->output ) ) {
			if ( 'warning' === $type ) {
				$message = '<comment>' . $message . '</comment>';
			} elseif ( 'success' === $type ) {
				$message = '<info>' . $message . '</info>';
			} elseif ( 'error' === $type ) {
				$message = '<error>' . $message . '</error>';
			}

			$console_verbosity_level = OutputInterface::VERBOSITY_NORMAL;

			if ( 1 === $verbosity_level ) {
				$console_verbosity_level = OutputInterface::VERBOSITY_VERBOSE;
			} elseif ( 2 === $verbosity_level ) {
				$console_verbosity_level = OutputInterface::VERBOSITY_VERY_VERBOSE;
			} elseif ( 3 === $verbosity_level ) {
				$console_verbosity_level = OutputInterface::VERBOSITY_DEBUG;
			}

			$this->output->writeln( $message, $console_verbosity_level );
		}

		return $entry;
	}

	/**
	 * Get verbosity of output
	 *
	 * @return bool
	 */
	public function getVerbosity() {
		return $this->verbosity;
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
