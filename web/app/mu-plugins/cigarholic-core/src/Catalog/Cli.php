<?php
/**
 * WP-CLI: catalog.
 *
 * @package Cigarholic
 */

namespace Cigarholic\Core\Catalog;

use WP_CLI;

/**
 * `wp cigarholic catalog` commands: the data hygiene check the brief calls `catalog:check`, and `ensure`
 * for the data model.
 *
 * Feature IDs: CAT-09, CAT-03.
 */
final class Cli {

	/**
	 * Checks catalog data hygiene and exits 1 when anything is wrong.
	 *
	 * Reports products and variations missing a SKU, with a SKU outside the CH-{TYPE}-{CAPACITY}-{WOOD}-{FINISH}
	 * scheme or a duplicated SKU, missing weight or dimensions, missing wood species or country of harvest,
	 * a featured image without alt text, humidors without cigar capacity, and category slugs that clash.
	 * On production (WP_ENVIRONMENT_TYPE) it also fails while any SAMPLE- product exists. The kickoff brief
	 * calls this command `catalog:check`; WP-CLI spells it `catalog check`.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : table, json, csv, yaml or count.
	 * ---
	 * default: table
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp cigarholic catalog check
	 *     wp cigarholic catalog check --format=json
	 *
	 * @param array<int, string>    $args       Positional arguments (none).
	 * @param array<string, string> $assoc_args Options.
	 * @return void
	 */
	public function check( array $args, array $assoc_args ): void {
		unset( $args );
		$format = $assoc_args['format'] ?? 'table';
		$result = Check::run();

		$rows = array();
		foreach ( $result['problems'] as $problem ) {
			$rows[] = array_merge( array( 'severity' => 'error' ), $problem );
		}
		foreach ( $result['warnings'] as $warning ) {
			$rows[] = array_merge( array( 'severity' => 'warning' ), $warning );
		}
		if ( array() !== $rows || 'table' !== $format ) {
			WP_CLI\Utils\format_items( $format, $rows, array( 'severity', 'code', 'product_id', 'sku', 'message' ) );
		}

		$counts = sprintf( '%d products, %d variations, %d warning(s)', $result['products'], $result['variations'], count( $result['warnings'] ) );
		if ( array() !== $result['problems'] ) {
			WP_CLI::error( sprintf( 'Catalog check failed: %d problem(s) in %s.', count( $result['problems'] ), $counts ) );
		}
		WP_CLI::success( sprintf( 'Catalog check passed: %s.', $counts ) );
	}

	/**
	 * Ensures the catalog data model: categories, occasions, global attributes and their terms.
	 *
	 * Idempotent; runs on init automatically whenever the model definition changes.
	 *
	 * ## EXAMPLES
	 *
	 *     wp cigarholic catalog ensure
	 *
	 * @return void
	 */
	public function ensure(): void {
		$changes = DataModel::ensure();
		foreach ( $changes as $change ) {
			WP_CLI::log( 'created: ' . $change );
		}
		WP_CLI::success( sprintf( 'Catalog data model ensured: %d change(s).', count( $changes ) ) );
	}
}
