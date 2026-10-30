<?php
/**
 * Feature contract.
 *
 * @package Cigarholic
 */

namespace Cigarholic\Core;

/**
 * A feature is one unit of custom behaviour, named after its ID in docs/FEATURES.md, switched by
 * a toggle (Features) and hooked into WordPress by register().
 *
 * Feature ID: FND-05.
 */
interface Feature {

	/**
	 * Feature ID from docs/FEATURES.md, for example "CNT-04".
	 *
	 * @return string
	 */
	public static function id(): string;

	/**
	 * Hooks the feature into WordPress. Called once, on load, only when the toggle is on.
	 *
	 * @return void
	 */
	public function register(): void;
}
