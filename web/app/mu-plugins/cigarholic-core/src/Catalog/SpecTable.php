<?php
/**
 * Specification table block.
 *
 * @package Cigarholic
 */

namespace Cigarholic\Core\Catalog;

use Cigarholic\Core\Feature;
use Cigarholic\Core\Plugin;
use WC_Product;
use WC_Product_Attribute;

/**
 * The `cigarholic/spec-table` dynamic block: one accessible table of the current product's specifications
 * read from the data model (CAT-03): capacity, cigar fit, exterior and interior dimensions, weight, then
 * every visible global attribute. Lengths show inches and centimetres, weights pounds and kilograms, always.
 * WooCommerce's "Additional information" tab is hidden on products where the table has content, so the
 * same data is not shown twice.
 *
 * Feature ID: CAT-04.
 */
final class SpecTable implements Feature {

	public const BLOCK = 'cigarholic/spec-table';

	/**
	 * Feature ID.
	 *
	 * @return string
	 */
	public static function id(): string {
		return 'CAT-04';
	}

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'register_block' ) );
		add_filter( 'woocommerce_product_tabs', array( $this, 'hide_additional_information_tab' ), 98 );
	}

	/**
	 * Registers the block from blocks/spec-table/block.json.
	 *
	 * @return void
	 */
	public function register_block(): void {
		register_block_type( Plugin::instance()->dir() . '/blocks/spec-table' );
	}

	/**
	 * Removes the "Additional information" tab when the spec table renders for the product.
	 *
	 * @param array<string, array<string, mixed>> $tabs Product tabs.
	 * @return array<string, array<string, mixed>>
	 */
	public function hide_additional_information_tab( array $tabs ): array {
		global $product;
		if ( isset( $tabs['additional_information'] ) && $product instanceof WC_Product && array() !== self::rows( $product ) ) {
			unset( $tabs['additional_information'] );
		}
		return $tabs;
	}

	/**
	 * Renders the table, or '' when the product has no specification data.
	 *
	 * @param WC_Product $product            Product (a variation renders its parent's table).
	 * @param string     $wrapper_attributes Attributes for the <table>, already escaped (block wrapper).
	 * @return string
	 */
	public static function render( WC_Product $product, string $wrapper_attributes = 'class="cigarholic-spec-table"' ): string {
		$product = self::resolve( $product );
		$rows    = self::rows( $product );
		if ( array() === $rows ) {
			return '';
		}

		$html  = '<table ' . $wrapper_attributes . '>';
		$html .= '<caption class="cigarholic-spec-table__caption">' . esc_html(
			sprintf(
				/* translators: %s: product name */
				__( 'Specifications of %s', 'cigarholic' ),
				$product->get_name()
			)
		) . '</caption>';
		$html .= '<tbody>';
		foreach ( $rows as $row ) {
			$html .= '<tr><th scope="row">' . esc_html( $row['label'] ) . '</th><td>' . esc_html( $row['value'] ) . '</td></tr>';
		}
		$html .= '</tbody></table>';

		return $html;
	}

	/**
	 * The rows of the table in display order.
	 *
	 * @param WC_Product $product Product.
	 * @return list<array{label: string, value: string}>
	 */
	public static function rows( WC_Product $product ): array {
		$product = self::resolve( $product );
		$rows    = array();

		$capacity = DataModel::meta_int( $product, '_ch_capacity' );
		if ( $capacity > 0 ) {
			$rows[] = array(
				'label' => __( 'Capacity', 'cigarholic' ),
				/* translators: %s: number of cigars */
				'value' => sprintf( _n( '%s cigar', '%s cigars', $capacity, 'cigarholic' ), number_format_i18n( $capacity ) ),
			);
		}

		$fit = self::fit( DataModel::meta_float( $product, '_ch_max_cigar_length_in' ), DataModel::meta_int( $product, '_ch_max_ring_gauge' ) );
		if ( '' !== $fit ) {
			$rows[] = array(
				'label' => __( 'Fits up to', 'cigarholic' ),
				'value' => $fit,
			);
		}

		$unit     = (string) get_option( 'woocommerce_dimension_unit', 'in' );
		$exterior = self::dimensions(
			self::to_inches( (string) $product->get_length( 'edit' ), $unit ),
			self::to_inches( (string) $product->get_width( 'edit' ), $unit ),
			self::to_inches( (string) $product->get_height( 'edit' ), $unit )
		);
		if ( '' !== $exterior ) {
			$rows[] = array(
				'label' => __( 'Exterior dimensions (L × W × H)', 'cigarholic' ),
				'value' => $exterior,
			);
		}

		$interior = self::dimensions(
			DataModel::meta_float( $product, '_ch_interior_length_in' ),
			DataModel::meta_float( $product, '_ch_interior_width_in' ),
			DataModel::meta_float( $product, '_ch_interior_height_in' )
		);
		if ( '' !== $interior ) {
			$rows[] = array(
				'label' => __( 'Interior dimensions (L × W × H)', 'cigarholic' ),
				'value' => $interior,
			);
		}

		$weight = (string) $product->get_weight( 'edit' );
		if ( '' !== $weight && (float) $weight > 0 ) {
			$pounds = (float) wc_get_weight( (float) $weight, 'lbs' );
			$rows[] = array(
				'label' => __( 'Weight', 'cigarholic' ),
				'value' => self::weight( $pounds ),
			);
		}

		foreach ( $product->get_attributes() as $attribute ) {
			if ( ! $attribute instanceof WC_Product_Attribute || ! $attribute->is_taxonomy() || ! $attribute->get_visible() ) {
				continue;
			}
			$names = wc_get_product_terms( $product->get_id(), $attribute->get_name(), array( 'fields' => 'names' ) );
			if ( ! is_array( $names ) || array() === $names ) {
				continue;
			}
			$rows[] = array(
				'label' => wc_attribute_label( $attribute->get_name(), $product ),
				'value' => implode( ', ', array_map( 'strval', $names ) ),
			);
		}

		return $rows;
	}

	/**
	 * "7.5 in (19.1 cm)".
	 *
	 * @param float $inches Length in inches.
	 * @return string
	 */
	public static function length( float $inches ): string {
		return sprintf(
			/* translators: 1: inches, 2: centimetres */
			__( '%1$s in (%2$s cm)', 'cigarholic' ),
			self::number( $inches, 2 ),
			self::number( $inches * 2.54, 1 )
		);
	}

	/**
	 * "12 × 9 × 4.5 in (30.5 × 22.9 × 11.4 cm)", or '' unless all three are set.
	 *
	 * @param float $length Inches.
	 * @param float $width  Inches.
	 * @param float $height Inches.
	 * @return string
	 */
	public static function dimensions( float $length, float $width, float $height ): string {
		if ( $length <= 0 || $width <= 0 || $height <= 0 ) {
			return '';
		}
		return sprintf(
			/* translators: 1: length in inches, 2: width in inches, 3: height in inches, 4: length in cm, 5: width in cm, 6: height in cm */
			__( '%1$s × %2$s × %3$s in (%4$s × %5$s × %6$s cm)', 'cigarholic' ),
			self::number( $length, 2 ),
			self::number( $width, 2 ),
			self::number( $height, 2 ),
			self::number( $length * 2.54, 1 ),
			self::number( $width * 2.54, 1 ),
			self::number( $height * 2.54, 1 )
		);
	}

	/**
	 * "6.5 lb (2.95 kg)".
	 *
	 * @param float $pounds Weight in pounds.
	 * @return string
	 */
	public static function weight( float $pounds ): string {
		return sprintf(
			/* translators: 1: pounds, 2: kilograms */
			__( '%1$s lb (%2$s kg)', 'cigarholic' ),
			self::number( $pounds, 2 ),
			self::number( $pounds * 0.45359237, 2 )
		);
	}

	/**
	 * "7.5 in (19.1 cm) long, ring gauge 60", either half alone, or ''.
	 *
	 * @param float $length_in  Max cigar length in inches.
	 * @param int   $ring_gauge Max ring gauge.
	 * @return string
	 */
	public static function fit( float $length_in, int $ring_gauge ): string {
		if ( $length_in > 0 && $ring_gauge > 0 ) {
			/* translators: 1: length with both units, 2: ring gauge */
			return sprintf( __( '%1$s long, ring gauge %2$d', 'cigarholic' ), self::length( $length_in ), $ring_gauge );
		}
		if ( $length_in > 0 ) {
			/* translators: %s: length with both units */
			return sprintf( __( '%s long', 'cigarholic' ), self::length( $length_in ) );
		}
		if ( $ring_gauge > 0 ) {
			/* translators: %d: ring gauge */
			return sprintf( __( 'Ring gauge %d', 'cigarholic' ), $ring_gauge );
		}
		return '';
	}

	/**
	 * A core dimension converted to inches from the store unit.
	 *
	 * @param string $value Stored dimension.
	 * @param string $unit  Store dimension unit.
	 * @return float
	 */
	private static function to_inches( string $value, string $unit ): float {
		if ( '' === $value || (float) $value <= 0 ) {
			return 0.0;
		}
		return (float) wc_get_dimension( (float) $value, 'in', $unit );
	}

	/**
	 * Number with up to N decimals, trailing zeros removed, localised separators.
	 *
	 * @param float $value    Value.
	 * @param int   $decimals Maximum decimals.
	 * @return string
	 */
	private static function number( float $value, int $decimals ): string {
		return wc_format_localized_decimal( wc_format_decimal( $value, $decimals, true ) );
	}

	/**
	 * The product whose data is shown: a variation shows its parent.
	 *
	 * @param WC_Product $product Product.
	 * @return WC_Product
	 */
	private static function resolve( WC_Product $product ): WC_Product {
		if ( $product->is_type( 'variation' ) && $product->get_parent_id() > 0 ) {
			$parent = wc_get_product( $product->get_parent_id() );
			if ( $parent instanceof WC_Product ) {
				return $parent;
			}
		}
		return $product;
	}
}
