<?php
/**
 * Provisioning step context.
 *
 * @package Cigarholic
 */

namespace Cigarholic\Core\Provision;

/**
 * Helpers a provisioning step uses to apply settings idempotently and report what changed.
 * Every helper compares before writing, so re-running a step is a no-op.
 *
 * Feature ID: FND-03.
 */
final class Context {

	/**
	 * Number of changes applied (or that would be applied in a dry run).
	 *
	 * @var int
	 */
	private int $changes = 0;

	/**
	 * Constructor.
	 *
	 * @param bool                   $dry_run Report without changing.
	 * @param callable(string): void $output  Receives log lines.
	 */
	public function __construct( private readonly bool $dry_run, private $output ) {}

	/**
	 * Whether this is a dry run.
	 *
	 * @return bool
	 */
	public function is_dry_run(): bool {
		return $this->dry_run;
	}

	/**
	 * Changes counted so far.
	 *
	 * @return int
	 */
	public function changes(): int {
		return $this->changes;
	}

	/**
	 * Logs a line.
	 *
	 * @param string $message Message.
	 * @return void
	 */
	public function log( string $message ): void {
		( $this->output )( sprintf( '   %s', $message ) );
	}

	/**
	 * Records a change made by custom code in a step (after the step checked that it is needed).
	 *
	 * @param string $description What changed.
	 * @return void
	 */
	public function changed( string $description ): void {
		++$this->changes;
		$this->log( ( $this->dry_run ? 'would change: ' : 'changed: ' ) . $description );
	}

	/**
	 * Sets an option when its value differs.
	 *
	 * @param string $name  Option name.
	 * @param mixed  $value Wanted value.
	 * @return bool Whether a change was needed.
	 */
	public function option( string $name, mixed $value ): bool {
		$current = get_option( $name, null );
		if ( $this->same( $current, $value ) ) {
			$this->log( sprintf( 'ok: %s', $name ) );
			return false;
		}
		if ( ! $this->dry_run ) {
			update_option( $name, $value );
		}
		$this->changed( sprintf( 'option %s', $name ) );
		return true;
	}

	/**
	 * Loose comparison that treats "yes"/"no", numeric strings and arrays sensibly.
	 *
	 * @param mixed $current Current value.
	 * @param mixed $wanted  Wanted value.
	 * @return bool
	 */
	private function same( mixed $current, mixed $wanted ): bool {
		if ( is_array( $wanted ) || is_array( $current ) ) {
			return $current === $wanted;
		}
		if ( null === $current ) {
			return false;
		}
		return (string) $current === (string) $wanted;
	}
}
