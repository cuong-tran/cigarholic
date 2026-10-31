<?php
/**
 * Tests for the catalog data model.
 *
 * @package Cigarholic
 */

namespace Cigarholic\Tests;

use Cigarholic\Core\Catalog\DataModel;
use WC_Product_Simple;
use WP_Term;
use WP_UnitTestCase;

/**
 * Feature IDs: CAT-03, CAT-02, CMP-11, CMP-06.
 */
final class CatalogDataModelTest extends WP_UnitTestCase {

	/**
	 * Counts of everything ensure() creates.
	 *
	 * @return array<string, int>
	 */
	private function counts(): array {
		$counts = array(
			'product_cat' => (int) wp_count_terms(
				array(
					'taxonomy'   => 'product_cat',
					'hide_empty' => false,
				)
			),
			'occasion'    => (int) wp_count_terms(
				array(
					'taxonomy'   => DataModel::TAXONOMY,
					'hide_empty' => false,
				)
			),
			'attributes'  => count( wc_get_attribute_taxonomies() ),
		);
		foreach ( array_keys( DataModel::ATTRIBUTES ) as $slug ) {
			$counts[ 'pa_' . $slug ] = (int) wp_count_terms(
				array(
					'taxonomy'   => wc_attribute_taxonomy_name( $slug ),
					'hide_empty' => false,
				)
			);
		}
		return $counts;
	}

	/**
	 * Running ensure() creates everything once; a second run changes nothing.
	 */
	public function test_ensure_is_idempotent(): void {
		DataModel::ensure();
		$before = $this->counts();

		$this->assertSame( array(), DataModel::ensure(), 'second run reports no change' );
		$this->assertSame( $before, $this->counts() );
		$this->assertSame( DataModel::fingerprint(), get_option( DataModel::OPTION ) );

		foreach ( DataModel::CATEGORIES as $slug => $name ) {
			$term = get_term_by( 'slug', $slug, 'product_cat' );
			$this->assertInstanceOf( WP_Term::class, $term, $slug );
			$this->assertSame( $name, $term->name );
			$this->assertSame( 0, $term->parent, 'categories are flat (ADR 0002)' );
		}
		foreach ( DataModel::OCCASIONS as $slug => $name ) {
			$term = get_term_by( 'slug', $slug, DataModel::TAXONOMY );
			$this->assertInstanceOf( WP_Term::class, $term, $slug );
			$this->assertSame( $name, $term->name );
		}
		foreach ( DataModel::ATTRIBUTES as $slug => $definition ) {
			$this->assertGreaterThan( 0, wc_attribute_taxonomy_id_by_name( $slug ), $slug );
			$taxonomy = wc_attribute_taxonomy_name( $slug );
			$this->assertTrue( taxonomy_exists( $taxonomy ), $taxonomy );
			$this->assertSame( $definition['label'], wc_attribute_label( $taxonomy ) );
			foreach ( $definition['terms'] as $term_slug => $term_name ) {
				$term = get_term_by( 'slug', $term_slug, $taxonomy );
				$this->assertInstanceOf( WP_Term::class, $term, $taxonomy . '/' . $term_slug );
				$this->assertSame( $term_name, $term->name );
			}
		}
	}

	/**
	 * The occasion taxonomy is public, in REST, flat, on products, at /occasions/.
	 */
	public function test_occasion_taxonomy(): void {
		$taxonomy = get_taxonomy( DataModel::TAXONOMY );
		$this->assertNotFalse( $taxonomy );
		$this->assertTrue( $taxonomy->public );
		$this->assertTrue( $taxonomy->show_in_rest );
		$this->assertFalse( $taxonomy->hierarchical );
		$this->assertContains( 'product', $taxonomy->object_type );
		$this->assertIsArray( $taxonomy->rewrite );
		$this->assertSame( 'occasions', $taxonomy->rewrite['slug'] );
		$this->assertSame( 'manage_product_terms', $taxonomy->cap->manage_terms );
	}

	/**
	 * Submitted values are normalised by type.
	 */
	public function test_sanitize_value(): void {
		$this->assertSame( '50', DataModel::sanitize_value( '_ch_capacity', ' 50 ' ) );
		$this->assertSame( '50', DataModel::sanitize_value( '_ch_capacity', '50.7' ) );
		$this->assertSame( '', DataModel::sanitize_value( '_ch_capacity', '0' ) );
		$this->assertSame( '', DataModel::sanitize_value( '_ch_capacity', 'fifty' ) );
		$this->assertSame( '7.5', DataModel::sanitize_value( '_ch_max_cigar_length_in', '7.50' ) );
		$this->assertSame( '3.125', DataModel::sanitize_value( '_ch_interior_height_in', '3.125' ) );
		$this->assertSame( '', DataModel::sanitize_value( '_ch_interior_height_in', '-2' ) );
		$this->assertSame( 'yes', DataModel::sanitize_value( '_ch_prop65', 'yes' ) );
		$this->assertSame( 'yes', DataModel::sanitize_value( '_ch_composite_wood', '1' ) );
		$this->assertSame( 'no', DataModel::sanitize_value( '_ch_composite_wood', 'no' ) );
		$this->assertSame( '', DataModel::sanitize_value( '_ch_prop65', '' ) );
		$this->assertSame( '4420.90.6500', DataModel::sanitize_value( '_ch_hts_code', '4420.90.65 00 ' ) );
		$this->assertSame( "Exterior: Juglans nigra, United States\nLining: Cedrela odorata, Brazil", DataModel::sanitize_value( '_ch_wood_species', "Exterior: Juglans nigra, United States\nLining: Cedrela odorata, Brazil\n" ) );
	}

	/**
	 * The product form saves the fields on the product object with a nonce and capability check.
	 */
	public function test_product_data_save(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$product = new WC_Product_Simple();
		$product->set_name( 'Specs save' );
		$product->set_regular_price( '10' );
		$id = $product->save();

		$_POST = array(
			DataModel::NONCE_FIELD    => wp_create_nonce( DataModel::NONCE_ACTION ),
			'_ch_capacity'            => '50',
			'_ch_max_cigar_length_in' => '7.5',
			'_ch_max_ring_gauge'      => '60',
			'_ch_interior_length_in'  => '11',
			'_ch_wood_species'        => "Exterior: Juglans nigra, United States\r\nLining: Cedrela odorata, Brazil",
			'_ch_harvest_country'     => 'United States; Brazil',
			'_ch_composite_wood'      => 'yes',
			'_ch_hts_code'            => '4420.90.6500',
			'_ch_prop65'              => 'yes',
		);

		$editing = wc_get_product( $id );
		$this->assertNotFalse( $editing );
		( new DataModel() )->save_product_data( $editing );
		$editing->save();

		$saved = wc_get_product( $id );
		$this->assertNotFalse( $saved );
		$this->assertSame( '50', $saved->get_meta( '_ch_capacity' ) );
		$this->assertSame( '7.5', $saved->get_meta( '_ch_max_cigar_length_in' ) );
		$this->assertSame( '60', $saved->get_meta( '_ch_max_ring_gauge' ) );
		$this->assertSame( '', $saved->get_meta( '_ch_interior_width_in' ), 'blank fields are not stored' );
		$this->assertStringContainsString( 'Juglans nigra', $saved->get_meta( '_ch_wood_species' ) );
		$this->assertSame( 'yes', $saved->get_meta( '_ch_composite_wood' ) );
		$this->assertSame( 'yes', $saved->get_meta( '_ch_prop65' ) );

		// Unchecked boxes are not posted: the toggle goes back off.
		unset( $_POST['_ch_prop65'], $_POST['_ch_composite_wood'] );
		( new DataModel() )->save_product_data( $saved );
		$saved->save();
		$this->assertSame( '', wc_get_product( $id )->get_meta( '_ch_prop65' ) );

		// Without a valid nonce nothing changes.
		$_POST[ DataModel::NONCE_FIELD ] = 'wrong';
		$_POST['_ch_capacity']           = '999';
		$again                           = wc_get_product( $id );
		( new DataModel() )->save_product_data( $again );
		$this->assertSame( '50', $again->get_meta( '_ch_capacity' ) );
	}

	/**
	 * The Prop 65 warning renders only when the product's toggle is on, with the standard wording.
	 */
	public function test_prop65_warning(): void {
		$product = new WC_Product_Simple();
		$product->set_name( 'Prop 65' );
		$product->set_regular_price( '10' );
		$product->save();

		$GLOBALS['product'] = $product;
		ob_start();
		( new DataModel() )->render_prop65_warning();
		$this->assertSame( '', ob_get_clean(), 'off by default (D-08)' );

		$product->update_meta_data( '_ch_prop65', 'yes' );
		$product->save();
		$GLOBALS['product'] = $product;
		ob_start();
		( new DataModel() )->render_prop65_warning();
		$html = (string) ob_get_clean();
		$this->assertStringContainsString( 'WARNING:', $html );
		$this->assertStringContainsString( 'known to the State of California to cause cancer', $html );
		$this->assertStringContainsString( 'wood dust', $html );
		$this->assertStringContainsString( 'www.P65Warnings.ca.gov', $html );
		$this->assertStringContainsString( 'class="cigarholic-prop65"', $html );
		unset( $GLOBALS['product'] );
	}
}
