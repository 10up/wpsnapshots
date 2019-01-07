<?php
/**
 * Progress bar manager functionality.
 *
 * @package wpsnapshots
 */

namespace WPSnapshots;

use Symfony\Component\Console\Helper\ProgressBar as Progress;
use Symfony\Component\Console\Output\OutputInterface;

use Aws\AwsClient;
use Aws\Result;

/**
 * Progress bar manager class.
 */
class ProgressBarManager {

	/**
	 * Next ID to use.
	 *
	 * @var int
	 */
	private static $next_id = 0;

	/**
	 * Output reference.
	 *
	 * @var OutputInterface
	 */
	private $output;

	/**
	 * Array of currently active progress bars, keyed by ID.
	 *
	 * @var array
	 */
	private $progress_bars = [];

	/**
	 * Constructor.
	 *
	 * Configures progress with S3 definition.
	 */
	public function __construct() {
		Progress::setPlaceholderFormatterDefinition(
			'cur_bytes',
			function ( $progressBar, $output ) {
				return Utils\format_bytes( $progressBar->getProgress() );
			}
		);
		Progress::setPlaceholderFormatterDefinition(
			'max_bytes',
			function ( $progressBar, $output ) {
				return Utils\format_bytes( $progressBar->getMaxSteps() );
			}
		);
		Progress::setFormatDefinition(
			's3',
			'%cur_bytes%/%max_bytes% [%bar%] %percent:3s%%'
		);
	}

	/**
	 * Sets the output reference.
	 *
	 * @param OutputInterface $output Output reference.
	 */
	public function setOutput( OutputInterface $output ) {
		$this->output = $output;
	}

	/**
	 * Creates a progress bar.
	 *
	 * @param string  $format The format to use.
	 * @param integer $steps  Optional. The maximum steps.
	 * @return ProgressBar
	 */
	public function create(
		string $format = 'normal',
		int $steps = 0
	) : ProgressBar {
		$id = self::$next_id;
		self::$next_id++;

		if ( method_exists( $this->output, 'section' ) ) {
			$output = $this->output->section();
		} else {
			$output = $this->output;
			$this->clearAll(); // Clear out bars if there is no sections.
		}

		$progress = new ProgressBar( $id, $output, $format, $steps );

		$this->progress_bars[ $id ] = $progress;

		return $progress;
	}

	/**
	 * Clears a bar.
	 *
	 * @param int  $id      The ID of the bar.
	 * @param bool $addLine Whether to add a new line.
	 * @return void
	 */
	public function clear( int $id, bool $addLine = true ) {
		if ( isset( $this->progress_bars[ $id ] ) ) {
			$this->progress_bars[ $id ]->finish( $addLine );
			unset( $this->progress_bars[ $id ] );
		}
	}

	/**
	 * Clears a bar by the instance instead of ID.
	 *
	 * @param ProgressBar $progress The progress bar reference.
	 * @param boolean     $addLine  Whether to add a new line.
	 * @return void
	 */
	public function clearByRef(
		ProgressBar $progress,
		bool $addLine = true
	) {
		$this->clear( $progress->id );
	}

	/**
	 * Clears all progress bars.
	 *
	 * @return void
	 */
	public function clearAll() {
		$ids = array_keys( $this->progress_bars );
		foreach ( $ids as $id ) {
			$this->clear( $id, false );
		}
	}

	/**
	 * Return singleton instance of class
	 *
	 * @return ProgressBarManager
	 */
	public static function instance() : ProgressBarManager {
		static $instance;

		if ( empty( $instance ) ) {
			$instance = new self();
		}

		return $instance;
	}

	/**
	 * Wraps an AWS operation with progress bar.
	 *
	 * @see https://docs.aws.amazon.com/sdk-for-php/v3/developer-guide/guide_configuration.html#progress
	 *
	 * @param AwsClient $client The AWS client.
	 * @param string    $method The method being called.
	 * @param array     $args   The method arguments.
	 * @return Result
	 */
	public function wrapAWSOperation(
		AwsClient $client,
		string $method,
		array $args = []
	) : Result {
		$progress      = $this->create( 's3' );
		$args['@http'] = [
			'progress' => function (
				$expected_download_bytes,
				$downloaded_bytes,
				$expected_upload_bytes,
				$uploaded_bytes
			) use ( $progress ) {
				if ( ! $progress->getSteps() ) {
					if ( $expected_download_bytes ) {
						$progress->setSteps( $expected_download_bytes );
					} elseif ( $expected_upload_bytes ) {
						$progress->setSteps( $expected_upload_bytes );
					}
				}

				if ( $downloaded_bytes ) {
					$progress->set( $downloaded_bytes );
				} elseif ( $uploaded_bytes ) {
					$progress->set( $uploaded_bytes );
				}
			},
		];

		$result = call_user_func( [ $client, $method ], $args );
		$this->clearByRef( $progress );
		return $result;
	}
}
