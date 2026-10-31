<?php
/**
 * Tests for the SKU scheme.
 *
 * @package Cigarholic
 */

namespace Cigarholic\Tests;

use Cigarholic\Core\Catalog\Sku;
use WC_Product_Simple;
use WP_UnitTestCase;

/**
 * Feature IDs: INV-01, CAT-09.
 */
final class CatalogSkuTest extends WP_UnitTestCase {

	/**
	 * SKUs that follow CH-{TYPE}-{CAPACITY}-{WOOD}-{FINISH}, including sample ones.
	 *
	 * @return array<string, array{string}>
	 */
	public static function valid_skus(): array {
		return array(
			'desktop'   => array( 'CH-DESK-050-WAL-MATTE' ),
			'cabinet'   => array( 'CH-CAB-300-OAK-GLOSS' ),
			'travel'    => array( 'CH-TRAV-010-ACA-OIL' ),
			'box'       => array( 'CH-BOX-025-CHE-OIL' ),
			'accessory' => array( 'CH-ACC-000-NONE-NONE' ),
			'gift'      => array( 'CH-GIFT-010-WAL-MATTE' ),
			'five'      => array( 'CH-DESK-100-MAPLE-UNFIN' ),
			'sample'    => array( 'SAMPLE-DESK-050-WAL-MATTE' ),
		);
	}

	/**
	 * SKUs outside the scheme.
	 *
	 * @return array<string, array{string}>
	 */
	public static function invalid_skus(): array {
		return array(
			'empty'          => array( '' ),
			'lowercase'      => array( 'ch-desk-050-wal-matte' ),
			'two digits'     => array( 'CH-DESK-50-WAL-MATTE' ),
			'four digits'    => array( 'CH-DESK-0500-WAL-MATTE' ),
			'unknown type'   => array( 'CH-SOFA-050-WAL-MATTE' ),
			'short wood'     => array( 'CH-DESK-050-WA-MATTE' ),
			'long wood'      => array( 'CH-DESK-050-WALNUT-MATTE' ),
			'extra segment'  => array( 'CH-DESK-050-WAL-MATTE-X' ),
			'missing finish' => array( 'CH-DESK-050-WAL' ),
			'wrong prefix'   => array( 'XX-DESK-050-WAL-MATTE' ),
			'bare sample'    => array( 'SAMPLE-' ),
			'whitespace'     => array( 'CH-DESK-050-WAL-MATTE ' ),
			'digits in wood' => array( 'CH-DESK-050-W4L-MATTE' ),
		);
	}

	/**
	 * Valid SKUs pass.
	 *
	 * @dataProvider valid_skus
	 *
	 * @param string $sku SKU.
	 */
	public function test_valid( string $sku ): void {
		$this->assertTrue( Sku::is_valid( $sku ), $sku );
	}

	/**
	 * Invalid SKUs fail.
	 *
	 * @dataProvider invalid_skus
	 *
	 * @param string $sku SKU.
	 */
	public function test_invalid( string $sku ): void {
		$this->assertFalse( Sku::is_valid( $sku ), $sku );
	}

	/**
	 * Only the SAMPLE- prefix marks sample data.
	 */
	public function test_sample(): void {
		$this->assertTrue( Sku::is_sample( 'SAMPLE-DESK-050-WAL-MATTE' ) );
		$this->assertFalse( Sku::is_sample( 'CH-DESK-050-WAL-MATTE' ) );
		$this->assertFalse( Sku::is_sample( 'sample-desk' ) );
	}

	/**
	 * Every category has a SKU type.
	 */
	public function test_types_cover_categories(): void {
		$this->assertSame( array( 'DESK', 'CAB', 'TRAV', 'BOX', 'ACC', 'GIFT' ), array_values( Sku::TYPES ) );
	}

	/**
	 * The problems() helper reports the scheme and uniqueness, and nothing for a product's own SKU.
	 */
	public function test_problems(): void {
		$product = new WC_Product_Simple();
		$product->set_name( 'Scheme test' );
		$product->set_regular_price( '10' );
		$product->set_sku( 'CH-DESK-050-WAL-MATTE' );
		$id = $product->save();

		$this->assertSame( array(), Sku::problems( 'CH-DESK-050-WAL-MATTE', $id ) );
		$this->assertCount( 1, Sku::problems( 'CH-DESK-050-WAL-MATTE', 0 ), 'duplicate of another product' );
		$this->assertCount( 1, Sku::problems( 'NOT-A-SCHEME', 0 ), 'scheme only' );

		$legacy = new WC_Product_Simple();
		$legacy->set_name( 'Legacy SKU' );
		$legacy->set_regular_price( '10' );
		$legacy->set_sku( 'legacy-123' );
		$legacy->save();
		$this->assertCount( 2, Sku::problems( 'legacy-123', 0 ), 'scheme and uniqueness are reported together' );
	}

	/**
	 * The product form keeps the stored SKU when the submitted one is invalid.
	 */
	public function test_admin_save_keeps_previous_sku(): void {
		$product = new WC_Product_Simple();
		$product->set_name( 'Revert test' );
		$product->set_regular_price( '10' );
		$product->set_sku( 'CH-BOX-025-CHE-OIL' );
		$id = $product->save();

		$editing = wc_get_product( $id );
		$this->assertNotFalse( $editing );
		$editing->set_sku( 'typo' );
		( new Sku() )->validate_product( $editing );
		$this->assertSame( 'CH-BOX-025-CHE-OIL', $editing->get_sku( 'edit' ) );

		$editing->set_sku( 'CH-BOX-025-CHE-RAW' );
		( new Sku() )->validate_product( $editing );
		$this->assertSame( 'CH-BOX-025-CHE-RAW', $editing->get_sku( 'edit' ), 'a valid new SKU is kept' );
	}
}
