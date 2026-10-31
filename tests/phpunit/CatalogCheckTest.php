<?php
/**
 * Tests for the catalog check.
 *
 * @package Cigarholic
 */

namespace Cigarholic\Tests;

use Cigarholic\Core\Catalog\Check;
use Cigarholic\Core\Catalog\DataModel;
use WC_Product;
use WC_Product_Attribute;
use WC_Product_Simple;
use WP_UnitTestCase;

/**
 * Feature IDs: CAT-09, INV-01, CMP-11.
 */
final class CatalogCheckTest extends WP_UnitTestCase {

	/**
	 * Store units back to the ADR 0003 defaults after each test.
	 */
	public function tear_down(): void {
		update_option( 'woocommerce_weight_unit', 'lbs' );
		update_option( 'woocommerce_dimension_unit', 'in' );
		parent::tear_down();
	}

	/**
	 * Problem codes reported for one product.
	 *
	 * @param array{problems: list<array{code: string, product_id: int, sku: string, message: string}>} $result Check result.
	 * @param int                                                                                       $id     Product ID.
	 * @return list<string>
	 */
	private function codes( array $result, int $id ): array {
		$codes = array();
		foreach ( $result['problems'] as $problem ) {
			if ( $problem['product_id'] === $id ) {
				$codes[] = $problem['code'];
			}
		}
		sort( $codes );
		return $codes;
	}

	/**
	 * A complete product in a category: SKU, weight, dimensions, species, country, image with alt, capacity.
	 *
	 * @param string $sku      SKU.
	 * @param string $category Category slug.
	 * @return WC_Product
	 */
	private function complete( string $sku, string $category ): WC_Product {
		$image = self::factory()->attachment->create( array( 'post_mime_type' => 'image/png' ) );
		update_post_meta( $image, '_wp_attachment_image_alt', 'Alt text' );

		$product = new WC_Product_Simple();
		$product->set_name( 'Complete ' . $sku );
		$product->set_regular_price( '100' );
		$product->set_sku( $sku );
		$product->set_weight( '5' );
		$product->set_length( '10' );
		$product->set_width( '8' );
		$product->set_height( '4' );
		$product->set_image_id( $image );
		$product->set_category_ids( array( DataModel::term_id( 'product_cat', $category ) ) );
		$product->update_meta_data( '_ch_capacity', '25' );
		$product->update_meta_data( '_ch_wood_species', 'Exterior: Juglans nigra, United States' );
		$product->update_meta_data( '_ch_harvest_country', 'United States' );
		$product->save();
		return $product;
	}

	/**
	 * A bare humidor is missing everything; a complete one is fine.
	 */
	public function test_missing_data(): void {
		DataModel::ensure();
		$bare = new WC_Product_Simple();
		$bare->set_name( 'Bare humidor' );
		$bare->set_regular_price( '10' );
		$bare->set_category_ids( array( DataModel::term_id( 'product_cat', 'desktop-humidors' ) ) );
		$bare_id = $bare->save();

		$complete = $this->complete( 'CH-BOX-025-CHE-OIL', 'cigar-boxes' );

		$result = Check::run( 'development' );
		$this->assertSame(
			array( 'missing_capacity', 'missing_dimensions', 'missing_harvest_country', 'missing_image', 'missing_sku', 'missing_weight', 'missing_wood_species' ),
			$this->codes( $result, $bare_id )
		);
		$this->assertSame( array(), $this->codes( $result, $complete->get_id() ) );
		$this->assertSame( 2, $result['products'] );

		$no_alt = $this->complete( 'CH-BOX-025-OAK-OIL', 'cigar-boxes' );
		delete_post_meta( $no_alt->get_image_id(), '_wp_attachment_image_alt' );
		$this->assertSame( array( 'missing_image_alt' ), $this->codes( Check::run( 'development' ), $no_alt->get_id() ) );
	}

	/**
	 * Scheme, duplicates and sample data on production.
	 */
	public function test_skus(): void {
		$invalid = $this->complete( 'legacy-1', 'cigar-boxes' );
		$first   = $this->complete( 'CH-DESK-050-WAL-MATTE', 'desktop-humidors' );
		$second  = $this->complete( 'CH-DESK-050-WAL-GLOSS', 'desktop-humidors' );
		$sample  = $this->complete( 'SAMPLE-TRAV-010-ACA-OIL', 'travel-humidors' );

		// WooCommerce refuses a duplicate through the CRUD API; simulate data that slipped in another way.
		update_post_meta( $second->get_id(), '_sku', 'CH-DESK-050-WAL-MATTE' );
		wp_cache_flush();

		$result = Check::run( 'development' );
		$this->assertSame( array( 'invalid_sku' ), $this->codes( $result, $invalid->get_id() ) );
		$this->assertSame( array( 'duplicate_sku' ), $this->codes( $result, $first->get_id() ) );
		$this->assertSame( array( 'duplicate_sku' ), $this->codes( $result, $second->get_id() ) );
		$this->assertSame( array(), $this->codes( $result, $sample->get_id() ), 'sample data is fine off production' );

		$this->assertSame( array( 'sample_data' ), $this->codes( Check::run( 'production' ), $sample->get_id() ) );
	}

	/**
	 * Accessories without wood need no species; with a wood attribute they do.
	 */
	public function test_accessories(): void {
		DataModel::ensure();
		$hygrometer = $this->complete( 'CH-ACC-000-NONE-NONE', 'humidor-accessories' );
		$hygrometer->delete_meta_data( '_ch_wood_species' );
		$hygrometer->delete_meta_data( '_ch_harvest_country' );
		$hygrometer->save();

		$tray = $this->complete( 'CH-ACC-000-CED-RAW', 'humidor-accessories' );
		$tray->delete_meta_data( '_ch_wood_species' );
		$tray->delete_meta_data( '_ch_harvest_country' );
		$lining = new WC_Product_Attribute();
		$lining->set_id( wc_attribute_taxonomy_id_by_name( 'interior-lining' ) );
		$lining->set_name( 'pa_interior-lining' );
		$lining->set_options( array( DataModel::term_id( 'pa_interior-lining', 'spanish-cedar' ) ) );
		$tray->set_attributes( array( $lining ) );
		$tray->save();

		$result = Check::run( 'development' );
		$this->assertSame( array(), $this->codes( $result, $hygrometer->get_id() ) );
		$this->assertSame( array( 'missing_harvest_country', 'missing_wood_species' ), $this->codes( $result, $tray->get_id() ) );
	}

	/**
	 * Category slugs and names must be unique across the tree.
	 */
	public function test_categories(): void {
		DataModel::ensure();
		$this->assertSame( array(), Check::run( 'development' )['problems'] );

		wp_insert_term( 'Desktop Humidors', 'product_cat', array( 'parent' => DataModel::term_id( 'product_cat', 'cigar-boxes' ) ) );
		wp_insert_term( 'Boxes', 'product_cat', array( 'slug' => 'cigar-boxes-2' ) );

		$codes = array_column( Check::run( 'development' )['problems'], 'code' );
		sort( $codes );
		$this->assertSame( array( 'duplicate_category_name', 'duplicate_category_slug' ), $codes );
	}

	/**
	 * Store units other than lbs/in are warnings, not failures.
	 */
	public function test_unit_warnings(): void {
		$this->assertSame( array(), Check::run( 'development' )['warnings'] );

		update_option( 'woocommerce_weight_unit', 'kg' );
		update_option( 'woocommerce_dimension_unit', 'cm' );
		$result = Check::run( 'development' );
		$this->assertSame( array( 'weight_unit', 'dimension_unit' ), array_column( $result['warnings'], 'code' ) );
		$this->assertSame( array(), $result['problems'] );
	}
}
