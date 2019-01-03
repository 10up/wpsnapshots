<?php
/**
 * Progress wrapper functionality
 *
 * @package wpsnapshots
 */

namespace WPSnapshots;

use Symfony\Component\Console\Helper\ProgressBar as Progress;
use Symfony\Component\Console\Output;

/**
 * Display a progress bar.
 */
class ProgressBar {

	/**
	 * ID of this progress bar.
	 *
	 * @var int
	 */
	public $id = 0;

	/**
	 * Output reference.
	 *
	 * @var Output
	 */
	protected $output;

	/**
	 * Progress bar reference.
	 *
	 * @var Progress
	 */
	protected $progress;

	/**
	 * Constructor.
	 *
	 * @param int    $id     The progress bar ID.
	 * @param Output $output The output reference.
	 * @param string $format Format to display.
	 * @param int    $steps  Optional. Max steps to set.
	 * @return ProgressBar
	 */
	public function __construct(
		int $id,
		Output $output,
		string $format,
		int $steps = 0
	) {
		$this->id       = $id;
		$this->output   = $output;
		$this->progress = new Progress( $output );

		$this->progress->setFormat( $format );
		if ( $steps ) {
			$this->progress->setMaxSteps( $steps );
		}
		return $this;
	}

	/**
	 * Sets the maximum steps for the bar.
	 *
	 * @param int $steps Number of steps.
	 * @return ProgressBar
	 */
	public function setSteps( int $steps ) {
		$this->progress->setMaxSteps( $steps );
		return $this;
	}

	/**
	 * Gets the maximum steps for the bar.
	 *
	 * @return int
	 */
	public function getSteps() : int {
		return $this->progress->getMaxSteps();
	}

	/**
	 * Advances the bar by one tick.
	 *
	 * @return void
	 */
	public function tick() {
		$this->progress->advance();
	}

	/**
	 * Sets the bar to a certain step.
	 *
	 * @param int $step The step to set the bar to.
	 * @return void
	 */
	public function set( int $step ) {
		$this->progress->setProgress( $step );
	}

	/**
	 * Finishes a progress display.
	 *
	 * @param bool $addLine Whether to display an additional line.
	 * @return void
	 */
	public function finish( bool $addLine = true ) {
		$this->progress->finish();
		if ( $addLine ) {
			$this->output->writeln( '' );
		}
	}
}
