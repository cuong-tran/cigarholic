<?php
/**
 * SKU scheme and validation.
 *
 * @package Cigarholic
 */

namespace Cigarholic\Core\Catalog;

use Cigarholic\Core\Feature;
use WC_Admin_Meta_Boxes;
use WC_Product;

/**
 * SKU scheme `CH-{TYPE}-{CAPACITY}-{WOOD}-{FINISH}` (ADR 0003), unique per product and variation:
 *
 * - TYPE: DESK, CAB, TRAV, BOX, ACC or GIFT (one per product category),
 * - CAPACITY: three digits (cigars; 000 for accessories),
 * - WOOD and FINISH: three to five uppercase letters (WAL, MAH, MATTE, GLOSS, OIL, ...).
 *
 * Sample data uses `SAMPLE-` in place of `CH-` (`SAMPLE-DESK-050-WAL-MATTE`) and is valid everywhere
 * but production (`wp cigarholic catalog check`). The product form validates on save and keeps the
 * previously saved SKU when the new one is invalid or taken, with an admin error notice.
 *
 * Feature IDs: INV-01 (primary), CAT-09.
 */
final class Sku implements Feature {

	public const PREFIX        = 'CH-';
	public const SAMPLE_PREFIX = 'SAMPLE-';

	/**
	 * Product category slug => SKU type segment.
	 */
	public const TYPES = array(
		'desktop-humidors'    => 'DESK',
		'cabinet-humidors'    => 'CAB',
		'travel-humidors'     => 'TRAV',
		'cigar-boxes'         => 'BOX',
		'humidor-accessories' => 'ACC',
		'gift-sets'           => 'GIFT',
	);

	private const PATTERN = '/^(?:CH|SAMPLE)-(?:DESK|CAB|TRAV|BOX|ACC|GIFT)-[0-9]{3}-[A-Z]{3,5}-[A-Z]{3,5}$/';

	/**
	 * Feature ID.
	 *
	 * @return string
	 */
	public static function id(): string {
		return 'INV-01';
	}

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'woocommerce_admin_process_product_object', array( $this, 'validate_product' ) );
		add_action( 'woocommerce_admin_process_variation_object', array( $this, 'validate_variation' ), 10, 2 );
	}

	/**
	 * Whether a SKU follows the scheme (a `SAMPLE-` SKU with the same structure counts).
	 *
	 * @param string $sku SKU.
	 * @return bool
	 */
	public static function is_valid( string $sku ): bool {
		return 1 === preg_match( self::PATTERN, $sku );
	}

	/**
	 * Whether a SKU marks sample data.
	 *
	 * @param string $sku SKU.
	 * @return bool
	 */
	public static function is_sample( string $sku ): bool {
		return str_starts_with( $sku, self::SAMPLE_PREFIX );
	}

	/**
	 * Problems with a SKU for a product: scheme and uniqueness. Empty when fine.
	 *
	 * @param string $sku        SKU (non-empty).
	 * @param int    $product_id Product or variation ID (0 for a new one).
	 * @return list<string>
	 */
	public static function problems( string $sku, int $product_id ): array {
		$problems = array();
		if ( ! self::is_valid( $sku ) ) {
			$problems[] = sprintf(
				/* translators: %s: the SKU */
				__( 'SKU "%s" does not follow the scheme CH-{TYPE}-{CAPACITY}-{WOOD}-{FINISH}, for example CH-DESK-050-WAL-MATTE (types DESK, CAB, TRAV, BOX, ACC, GIFT; capacity three digits; wood and finish three to five capital letters).', 'cigarholic' ),
				$sku
			);
		}
		if ( ! wc_product_has_unique_sku( $product_id, $sku ) ) {
			$problems[] = sprintf(
				/* translators: %s: the SKU */
				__( 'SKU "%s" is already used by another product or variation.', 'cigarholic' ),
				$sku
			);
		}
		return $problems;
	}

	/**
	 * Validates the SKU of a product saved from the product form.
	 *
	 * @param WC_Product $product Product.
	 * @return void
	 */
	public function validate_product( WC_Product $product ): void {
		$this->validate( $product );
	}

	/**
	 * Validates the SKU of a variation saved from the product form.
	 *
	 * @param WC_Product $variation Variation.
	 * @param int        $index     Loop index (unused).
	 * @return void
	 */
	public function validate_variation( WC_Product $variation, int $index ): void {
		unset( $index );
		$this->validate( $variation );
	}

	/**
	 * Adds an admin error and keeps the stored SKU when the submitted one is invalid or taken.
	 *
	 * @param WC_Product $product Product or variation, before save.
	 * @return void
	 */
	private function validate( WC_Product $product ): void {
		$sku = (string) $product->get_sku( 'edit' );
		if ( '' === $sku ) {
			return;
		}
		$problems = self::problems( $sku, $product->get_id() );
		if ( array() === $problems ) {
			return;
		}
		if ( class_exists( WC_Admin_Meta_Boxes::class ) ) {
			foreach ( $problems as $message ) {
				WC_Admin_Meta_Boxes::add_error( $message );
			}
		}
		$stored   = $product->get_id() > 0 ? wc_get_product( $product->get_id() ) : false;
		$previous = $stored instanceof WC_Product ? (string) $stored->get_sku( 'edit' ) : '';
		$product->set_sku( $previous );
	}
}
