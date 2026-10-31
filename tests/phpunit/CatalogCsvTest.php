<?php
/**
 * Tests for the CSV import template and the importer/exporter hooks.
 *
 * @package Cigarholic
 */

namespace Cigarholic\Tests;

use Cigarholic\Core\Catalog\Csv;
use Cigarholic\Core\Catalog\DataModel;
use WC_Product;
use WC_Product_CSV_Importer;
use WC_Product_CSV_Importer_Controller;
use WC_Product_Simple;
use WP_UnitTestCase;

/**
 * Feature IDs: CAT-10, CMP-11.
 */
final class CatalogCsvTest extends WP_UnitTestCase {

	/**
	 * Path of the template the manager starts from.
	 *
	 * @return string
	 */
	private function template(): string {
		return dirname( __DIR__, 2 ) . '/docs/templates/products-import-template.csv';
	}

	/**
	 * Loads the importer classes the way WooCommerce > Products > Import does.
	 */
	public function set_up(): void {
		parent::set_up();
		// The importer creates categories only for a user who may manage terms, as in WooCommerce > Products > Import.
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		update_option( 'woocommerce_weight_unit', 'lbs' );
		update_option( 'woocommerce_dimension_unit', 'in' );
		DataModel::ensure();
		include_once WC_ABSPATH . 'includes/admin/importers/class-wc-product-csv-importer-controller.php';
		include_once WC_ABSPATH . 'includes/import/class-wc-product-csv-importer.php';
	}

	/**
	 * The importer's automatic column mapping, exactly as the mapping screen computes it.
	 *
	 * @param array<int, string> $headers Raw CSV headers.
	 * @return array<string, string> Header => field.
	 */
	private function auto_map( array $headers ): array {
		$controller = new class() extends WC_Product_CSV_Importer_Controller {
			/**
			 * Exposes the protected mapping.
			 *
			 * @param array<int, string> $headers Raw headers.
			 * @return array<string, string>
			 */
			public function map( array $headers ): array {
				return $this->auto_map_columns( $headers, false );
			}
		};
		return $controller->map( $headers );
	}

	/**
	 * Every template column maps automatically, and the import fills meta, attributes, occasions and variations.
	 */
	public function test_template_imports(): void {
		$file = $this->template();
		$this->assertFileExists( $file );

		$headers = ( new WC_Product_CSV_Importer( $file, array( 'parse' => false ) ) )->get_raw_keys();
		$mapping = $this->auto_map( $headers );

		$this->assertSame( 'sku', $mapping['SKU'] );
		$this->assertSame( 'global_unique_id', $mapping[ Csv::GTIN_HEADER ] );
		$this->assertSame( 'weight', $mapping['Weight (lbs)'] );
		$this->assertSame( 'length', $mapping['Length (in)'] );
		$this->assertSame( 'category_ids', $mapping['Categories'] );
		$this->assertSame( Csv::OCCASIONS_KEY, $mapping[ Csv::OCCASIONS_HEADER ] );
		$this->assertSame( 'shipping_class_id', $mapping['Shipping class'] );
		$this->assertSame( 'parent_id', $mapping['Parent'] );
		$this->assertSame( 'attributes:name1', $mapping['Attribute 1 name'] );
		$this->assertSame( 'attributes:value1', $mapping['Attribute 1 value(s)'] );
		$this->assertSame( 'attributes:taxonomy1', $mapping['Attribute 1 global'] );
		$this->assertSame( 'attributes:default6', $mapping['Attribute 6 default'] );
		foreach ( Csv::META_HEADERS as $header ) {
			$this->assertSame( 'meta:' . substr( $header, strlen( 'Meta: ' ) ), $mapping[ $header ], $header );
		}
		// An unrecognised header is kept verbatim (lowercased) as its own field name; real fields have no spaces.
		$unmapped = array_filter( $mapping, static fn( string $field ): bool => 1 === preg_match( '/[\s,()]/', $field ) );
		$this->assertSame( array(), $unmapped, 'every column of the template is recognised' );

		// "Update existing products" unticked, as for a first import: WooCommerce skips unknown SKUs when it is on.
		$importer = new WC_Product_CSV_Importer(
			$file,
			array(
				'mapping'         => $mapping,
				'parse'           => true,
				'update_existing' => false,
			)
		);
		$result   = $importer->import();
		$messages = array_map( static fn( $error ): string => is_wp_error( $error ) ? $error->get_error_message() : 'unknown', $result['failed'] );
		$this->assertSame( array(), $messages );
		$this->assertCount( 7, $result['imported'] );
		$this->assertCount( 2, $result['imported_variations'] );

		$simple = wc_get_product( wc_get_product_id_by_sku( 'SAMPLE-DESK-100-MAH-GLOSS' ) );
		$this->assertInstanceOf( WC_Product::class, $simple );
		$this->assertSame( 'Sample Mahogany Desktop Humidor 100', $simple->get_name() );
		$this->assertSame( '389', $simple->get_regular_price() );
		$this->assertSame( '8.8', $simple->get_weight() );
		$this->assertSame( '15', $simple->get_length() );
		$this->assertSame( '100', $simple->get_meta( '_ch_capacity' ) );
		$this->assertSame( '8', $simple->get_meta( '_ch_max_cigar_length_in' ) );
		$this->assertStringContainsString( 'Swietenia macrophylla', $simple->get_meta( '_ch_wood_species' ) );
		$this->assertStringContainsString( "\n", $simple->get_meta( '_ch_wood_species' ), 'one wood per line survives the import' );
		$this->assertSame( 'Peru; Vietnam; Brazil', $simple->get_meta( '_ch_harvest_country' ) );
		$this->assertSame( 'no', $simple->get_meta( '_ch_composite_wood' ) );
		$this->assertSame( '4420.90.6500', $simple->get_meta( '_ch_hts_code' ) );
		$this->assertSame( 'no', $simple->get_meta( '_ch_prop65' ) );
		$this->assertSame( array( 'desktop-humidors' ), wp_get_post_terms( $simple->get_id(), 'product_cat', array( 'fields' => 'slugs' ) ) );
		$this->assertSame( array( 'corporate', 'retirement' ), wp_get_post_terms( $simple->get_id(), DataModel::TAXONOMY, array( 'fields' => 'slugs' ) ) );
		$this->assertSame( 'humidor', $simple->get_shipping_class() );
		$this->assertSame( array( 'Mahogany' ), wc_get_product_terms( $simple->get_id(), 'pa_exterior-wood', array( 'fields' => 'names' ) ) );
		$this->assertSame( array( 'Key lock' ), wc_get_product_terms( $simple->get_id(), 'pa_lock', array( 'fields' => 'names' ) ) );
		$this->assertArrayHasKey( 'pa_finish', $simple->get_attributes() );

		$parent = wc_get_product( wc_get_product_id_by_sku( 'SAMPLE-DESK-050-WAL-VAR' ) );
		$this->assertInstanceOf( WC_Product::class, $parent );
		$this->assertTrue( $parent->is_type( 'variable' ) );
		$this->assertCount( 2, $parent->get_children() );
		$this->assertTrue( $parent->get_attributes()['pa_finish']->get_variation(), 'the attribute with a default becomes the variation attribute' );

		$variation = wc_get_product( wc_get_product_id_by_sku( 'SAMPLE-DESK-050-WAL-GLOSS' ) );
		$this->assertInstanceOf( WC_Product::class, $variation );
		$this->assertTrue( $variation->is_type( 'variation' ) );
		$this->assertSame( $parent->get_id(), $variation->get_parent_id() );
		$this->assertSame( array( 'pa_finish' => 'gloss-lacquer' ), $variation->get_attributes() );
		$this->assertSame( '309', $variation->get_regular_price() );
		$this->assertSame( 8, $variation->get_stock_quantity() );

		$cabinet = wc_get_product( wc_get_product_id_by_sku( 'SAMPLE-CAB-300-OAK-MATTE' ) );
		$this->assertInstanceOf( WC_Product::class, $cabinet );
		$this->assertSame( 'yes', $cabinet->get_meta( '_ch_prop65' ) );
		$this->assertSame( 'yes', $cabinet->get_meta( '_ch_composite_wood' ) );
		$this->assertSame( 'oversized-humidor', $cabinet->get_shipping_class() );
	}

	/**
	 * Occasions export as comma-separated names, and the column is offered to the exporter.
	 */
	public function test_export_occasions(): void {
		$product = new WC_Product_Simple();
		$product->set_name( 'Export test' );
		$product->set_regular_price( '10' );
		$id = $product->save();
		wp_set_object_terms( $id, array( DataModel::term_id( DataModel::TAXONOMY, 'groomsmen' ), DataModel::term_id( DataModel::TAXONOMY, 'fathers-day' ) ), DataModel::TAXONOMY );

		$csv = new Csv();
		$this->assertSame( "Father's Day, Groomsmen", $csv->export_occasions( '', wc_get_product( $id ) ) );
		$this->assertSame( Csv::OCCASIONS_HEADER, $csv->add_export_column( array() )[ Csv::OCCASIONS_KEY ] );
		$this->assertArrayHasKey( Csv::OCCASIONS_KEY, $csv->add_mapping_option( array() ) );
	}
}
