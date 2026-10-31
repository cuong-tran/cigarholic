<?php
/**
 * Tests for the specification table block.
 *
 * @package Cigarholic
 */

namespace Cigarholic\Tests;

use Cigarholic\Core\Catalog\DataModel;
use Cigarholic\Core\Catalog\SpecTable;
use Cigarholic\Core\Plugin;
use WC_Product;
use WC_Product_Attribute;
use WC_Product_Simple;
use WP_UnitTestCase;

/**
 * Feature ID: CAT-04.
 */
final class CatalogSpecTableTest extends WP_UnitTestCase {

	/**
	 * Store units back to the ADR 0003 defaults after each test.
	 */
	public function tear_down(): void {
		update_option( 'woocommerce_weight_unit', 'lbs' );
		update_option( 'woocommerce_dimension_unit', 'in' );
		parent::tear_down();
	}

	/**
	 * A product with every specification, stored in the given store units.
	 *
	 * @param string $weight Weight in the store unit.
	 * @param string $length Length in the store unit.
	 * @param string $width  Width in the store unit.
	 * @param string $height Height in the store unit.
	 * @return WC_Product
	 */
	private function full_product( string $weight = '6.5', string $length = '12.5', string $width = '9.25', string $height = '4.75' ): WC_Product {
		DataModel::ensure();
		$product = new WC_Product_Simple();
		$product->set_name( 'Spec test humidor' );
		$product->set_description( 'Spec test description.' );
		$product->set_regular_price( '289' );
		$product->set_weight( $weight );
		$product->set_length( $length );
		$product->set_width( $width );
		$product->set_height( $height );
		$product->update_meta_data( '_ch_capacity', '50' );
		$product->update_meta_data( '_ch_max_cigar_length_in', '7.5' );
		$product->update_meta_data( '_ch_max_ring_gauge', '60' );
		$product->update_meta_data( '_ch_interior_length_in', '11' );
		$product->update_meta_data( '_ch_interior_width_in', '8' );
		$product->update_meta_data( '_ch_interior_height_in', '3.5' );
		$product->update_meta_data( '_ch_wood_species', 'Exterior: Juglans nigra, United States' );

		$finish = new WC_Product_Attribute();
		$finish->set_id( wc_attribute_taxonomy_id_by_name( 'finish' ) );
		$finish->set_name( 'pa_finish' );
		$finish->set_options( array( DataModel::term_id( 'pa_finish', 'matte-lacquer' ) ) );
		$finish->set_visible( true );
		$hidden = new WC_Product_Attribute();
		$hidden->set_id( wc_attribute_taxonomy_id_by_name( 'lock' ) );
		$hidden->set_name( 'pa_lock' );
		$hidden->set_options( array( DataModel::term_id( 'pa_lock', 'key-lock' ) ) );
		$hidden->set_visible( false );
		$product->set_attributes( array( $finish, $hidden ) );
		$product->save();

		$saved = wc_get_product( $product->get_id() );
		$this->assertInstanceOf( WC_Product::class, $saved );
		return $saved;
	}

	/**
	 * Every row shows both unit systems; hidden attributes and compliance fields never appear.
	 */
	public function test_renders_both_unit_systems(): void {
		$html = SpecTable::render( $this->full_product() );

		$this->assertStringStartsWith( '<table class="cigarholic-spec-table">', $html );
		$this->assertStringContainsString( '<caption class="cigarholic-spec-table__caption">Specifications of Spec test humidor</caption>', $html );
		$this->assertStringContainsString( '<th scope="row">Capacity</th><td>50 cigars</td>', $html );
		$this->assertStringContainsString( '<th scope="row">Fits up to</th><td>7.5 in (19.1 cm) long, ring gauge 60</td>', $html );
		$this->assertStringContainsString( '<th scope="row">Exterior dimensions (L × W × H)</th><td>12.5 × 9.25 × 4.75 in (31.8 × 23.5 × 12.1 cm)</td>', $html );
		$this->assertStringContainsString( '<th scope="row">Interior dimensions (L × W × H)</th><td>11 × 8 × 3.5 in (27.9 × 20.3 × 8.9 cm)</td>', $html );
		$this->assertStringContainsString( '<th scope="row">Weight</th><td>6.5 lb (2.95 kg)</td>', $html );
		$this->assertStringContainsString( '<th scope="row">Finish</th><td>Matte lacquer</td>', $html );
		$this->assertStringNotContainsString( 'Lock', $html, 'attributes hidden by the manager stay hidden' );
		$this->assertStringNotContainsString( 'Juglans', $html, 'compliance fields are private' );

		$labels = array_column( SpecTable::rows( $this->full_product() ), 'label' );
		$this->assertSame( array( 'Capacity', 'Fits up to', 'Exterior dimensions (L × W × H)', 'Interior dimensions (L × W × H)', 'Weight', 'Finish' ), $labels );
	}

	/**
	 * Core weight and dimensions are converted from whatever unit the store uses.
	 */
	public function test_store_units_are_converted(): void {
		update_option( 'woocommerce_weight_unit', 'kg' );
		update_option( 'woocommerce_dimension_unit', 'cm' );

		$html = SpecTable::render( $this->full_product( '2.95', '30.5', '23.5', '12.1' ) );

		$this->assertStringContainsString( '<td>6.5 lb (2.95 kg)</td>', $html );
		$this->assertStringContainsString( '<td>12.01 × 9.25 × 4.76 in (30.5 × 23.5 × 12.1 cm)</td>', $html );
	}

	/**
	 * Partial data renders only what exists; no data renders nothing.
	 */
	public function test_partial_and_empty(): void {
		$product = new WC_Product_Simple();
		$product->set_name( 'Bare' );
		$product->set_regular_price( '10' );
		$product->save();
		$this->assertSame( array(), SpecTable::rows( $product ) );
		$this->assertSame( '', SpecTable::render( $product ) );

		$product->update_meta_data( '_ch_max_ring_gauge', '54' );
		$product->set_weight( '2' );
		$product->set_length( '10' );
		$product->save();
		$rows = SpecTable::rows( $product );
		$this->assertSame( array( 'Fits up to', 'Weight' ), array_column( $rows, 'label' ), 'two of three dimensions is not a size' );
		$this->assertSame( 'Ring gauge 54', $rows[0]['value'] );
		$this->assertSame( '2 lb (0.91 kg)', $rows[1]['value'] );
	}

	/**
	 * The block renders for the current post and nothing elsewhere.
	 */
	public function test_block(): void {
		$this->assertTrue( \WP_Block_Type_Registry::get_instance()->is_registered( SpecTable::BLOCK ) );

		$product = $this->full_product();
		$this->go_to( get_permalink( $product->get_id() ) );
		$html = do_blocks( '<!-- wp:cigarholic/spec-table /-->' );
		$this->assertStringContainsString( 'wp-block-cigarholic-spec-table', $html );
		$this->assertStringContainsString( 'cigarholic-spec-table', $html );
		$this->assertStringContainsString( '6.5 lb (2.95 kg)', $html );

		$this->go_to( home_url( '/' ) );
		$this->assertSame( '', trim( do_blocks( '<!-- wp:cigarholic/spec-table /-->' ) ) );
	}

	/**
	 * WooCommerce's "Additional information" tab disappears when the table has content.
	 */
	public function test_hides_additional_information_tab(): void {
		$tabs = array(
			'description'            => array( 'title' => 'Description' ),
			'additional_information' => array( 'title' => 'Additional information' ),
		);

		$filter = Plugin::instance()->features()->get( 'spec-table' );
		$this->assertInstanceOf( SpecTable::class, $filter );

		// Through WooCommerce's own default tabs on a product page: it adds the tab, the feature removes it.
		$product = $this->full_product();
		$this->go_to( get_permalink( $product->get_id() ) );
		$GLOBALS['product'] = $product;
		remove_filter( 'woocommerce_product_tabs', array( $filter, 'hide_additional_information_tab' ), 98 );
		$this->assertArrayHasKey( 'additional_information', apply_filters( 'woocommerce_product_tabs', array() ), 'WooCommerce adds the tab for attributes and dimensions' );
		add_filter( 'woocommerce_product_tabs', array( $filter, 'hide_additional_information_tab' ), 98 );
		$filtered = apply_filters( 'woocommerce_product_tabs', array() );
		$this->assertArrayNotHasKey( 'additional_information', $filtered );
		$this->assertArrayHasKey( 'description', $filtered );
		$this->assertArrayNotHasKey( 'additional_information', $filter->hide_additional_information_tab( $tabs ) );

		// A product without specification data keeps WooCommerce's tab.
		$bare = new WC_Product_Simple();
		$bare->set_name( 'Bare' );
		$bare->save();
		$GLOBALS['product'] = $bare;
		$this->assertArrayHasKey( 'additional_information', $filter->hide_additional_information_tab( $tabs ) );
		unset( $GLOBALS['product'] );
	}
}
