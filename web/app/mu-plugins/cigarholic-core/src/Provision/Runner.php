<?php
/**
 * Provisioning runner.
 *
 * @package Cigarholic
 */

namespace Cigarholic\Core\Provision;

/**
 * Runs the steps in provision/steps in file-name order. Each step file returns a callable that
 * receives a Context and applies settings idempotently; the runner counts the changes.
 *
 * Feature ID: FND-03.
 */
final class Runner {

	/**
	 * Constructor.
	 *
	 * @param string                 $steps_dir Directory with NN-name.php step files.
	 * @param callable(string): void $output    Receives log lines.
	 */
	public function __construct( private readonly string $steps_dir, private $output ) {}

	/**
	 * Step files, sorted.
	 *
	 * @return list<string> Absolute paths.
	 */
	public function steps(): array {
		$files = glob( rtrim( $this->steps_dir, '/' ) . '/*.php' );
		if ( false === $files ) {
			return array();
		}
		sort( $files, SORT_STRING );
		return $files;
	}

	/**
	 * Runs the steps.
	 *
	 * @param bool        $dry_run Report without changing.
	 * @param string|null $only    Run only this step (file name without .php).
	 * @return int Number of changes applied (or that would be applied).
	 * @throws \RuntimeException When the steps directory is missing, a step is unknown or malformed.
	 */
	public function run( bool $dry_run = false, ?string $only = null ): int {
		if ( ! is_dir( $this->steps_dir ) ) {
			throw new \RuntimeException( sprintf( 'Provisioning directory not found: %s', esc_html( $this->steps_dir ) ) );
		}

		$steps = $this->steps();
		if ( null !== $only ) {
			$steps = array_filter(
				$steps,
				static fn( string $file ): bool => basename( $file, '.php' ) === $only
			);
			if ( array() === $steps ) {
				throw new \RuntimeException( sprintf( 'Unknown provisioning step: %s', esc_html( $only ) ) );
			}
		}

		$total = 0;
		foreach ( $steps as $file ) {
			$name    = basename( $file, '.php' );
			$context = new Context( $dry_run, $this->output );
			$step    = require $file;
			if ( ! is_callable( $step ) ) {
				throw new \RuntimeException( sprintf( 'Step %s must return a callable.', esc_html( $name ) ) );
			}
			( $this->output )( sprintf( '== %s', $name ) );
			$step( $context );
			$total += $context->changes();
		}
		return $total;
	}
}
