<?php
/**
 * CSV import and export support for the catalog data model.
 *
 * @package Cigarholic
 */

namespace Cigarholic\Core\Catalog;

use Cigarholic\Core\Feature;
use WC_Product;
use WP_Term;

/**
 * Makes the WooCommerce product importer and exporter (WooCommerce > Products > Import / Export) carry the
 * whole data model, so the manager edits the catalog in bulk from `docs/templates/products-import-template.csv`:
 *
 * - `Meta: _ch_*` columns are WooCommerce's own meta columns and need nothing extra,
 * - the `Occasions` column (comma-separated term names) is added to the mapping, the import and the export,
 * - the "GTIN, UPC, EAN, or ISBN" header the exporter writes is mapped back to the product GTIN on import.
 *
 * Feature ID: CAT-10.
 */
final class Csv implements Feature {

	public const OCCASIONS_HEADER = 'Occasions';
	public const OCCASIONS_KEY    = 'occasion';
	public const GTIN_HEADER      = 'GTIN, UPC, EAN, or ISBN';

	/**
	 * The `Meta:` columns of the template, in order.
	 */
	public const META_HEADERS = array(
		'Meta: _ch_capacity',
		'Meta: _ch_max_cigar_length_in',
		'Meta: _ch_max_ring_gauge',
		'Meta: _ch_interior_length_in',
		'Meta: _ch_interior_width_in',
		'Meta: _ch_interior_height_in',
		'Meta: _ch_wood_species',
		'Meta: _ch_harvest_country',
		'Meta: _ch_composite_wood',
		'Meta: _ch_hts_code',
		'Meta: _ch_prop65',
	);

	/**
	 * Feature ID.
	 *
	 * @return string
	 */
	public static function id(): string {
		return 'CAT-10';
	}

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'woocommerce_csv_product_import_mapping_options', array( $this, 'add_mapping_option' ) );
		add_filter( 'woocommerce_csv_product_import_mapping_default_columns', array( $this, 'add_default_columns' ) );
		add_action( 'woocommerce_product_import_inserted_product_object', array( $this, 'import_occasions' ), 10, 2 );
		add_filter( 'woocommerce_product_export_product_default_columns', array( $this, 'add_export_column' ) );
		add_filter( 'woocommerce_product_export_product_column_' . self::OCCASIONS_KEY, array( $this, 'export_occasions' ), 10, 2 );
	}

	/**
	 * Offers "Occasions" in the importer's column mapping dropdown.
	 *
	 * @param array<string, mixed> $options Mapping options.
	 * @return array<string, mixed>
	 */
	public function add_mapping_option( array $options ): array {
		$options[ self::OCCASIONS_KEY ] = __( 'Occasions', 'cigarholic' );
		return $options;
	}

	/**
	 * Maps the template headers automatically.
	 *
	 * @param array<string, string> $columns Header => field.
	 * @return array<string, string>
	 */
	public function add_default_columns( array $columns ): array {
		$columns[ self::OCCASIONS_HEADER ]          = self::OCCASIONS_KEY;
		$columns[ __( 'Occasions', 'cigarholic' ) ] = self::OCCASIONS_KEY;
		$columns[ self::GTIN_HEADER ]               = 'global_unique_id';
		return $columns;
	}

	/**
	 * Assigns the occasion terms named in the row (comma-separated; created when missing).
	 *
	 * @param WC_Product|mixed     $product Imported product.
	 * @param array<string, mixed> $data    Parsed row.
	 * @return void
	 */
	public function import_occasions( $product, array $data ): void {
		if ( ! $product instanceof WC_Product || $product->is_type( 'variation' ) || ! array_key_exists( self::OCCASIONS_KEY, $data ) ) {
			return;
		}
		$raw   = $data[ self::OCCASIONS_KEY ];
		$names = is_scalar( $raw ) ? array_filter( array_map( 'trim', explode( ',', (string) $raw ) ), static fn( string $name ): bool => '' !== $name ) : array();
		$ids   = array();
		foreach ( $names as $name ) {
			$term = get_term_by( 'name', $name, DataModel::TAXONOMY );
			if ( ! $term instanceof WP_Term ) {
				$term = get_term_by( 'slug', sanitize_title( $name ), DataModel::TAXONOMY );
			}
			if ( $term instanceof WP_Term ) {
				$ids[] = (int) $term->term_id;
				continue;
			}
			$inserted = wp_insert_term( $name, DataModel::TAXONOMY );
			if ( ! is_wp_error( $inserted ) ) {
				$ids[] = (int) $inserted['term_id'];
			}
		}
		wp_set_object_terms( $product->get_id(), $ids, DataModel::TAXONOMY );
	}

	/**
	 * Adds "Occasions" to the exporter's column list.
	 *
	 * @param array<string, string> $columns Field => header.
	 * @return array<string, string>
	 */
	public function add_export_column( array $columns ): array {
		$columns[ self::OCCASIONS_KEY ] = self::OCCASIONS_HEADER;
		return $columns;
	}

	/**
	 * Exports the product's occasion names, comma-separated.
	 *
	 * @param mixed      $value   Current value.
	 * @param WC_Product $product Product.
	 * @return string
	 */
	public function export_occasions( $value, WC_Product $product ): string {
		unset( $value );
		$id    = $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id();
		$names = wp_get_object_terms( $id, DataModel::TAXONOMY, array( 'fields' => 'names' ) );
		return is_array( $names ) ? implode( ', ', array_map( 'strval', $names ) ) : '';
	}
}
