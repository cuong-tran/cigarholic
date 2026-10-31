<?php
/**
 * Sample catalog for development and staging.
 *
 * @package Cigarholic
 */

namespace Cigarholic\Core\Catalog;

use Cigarholic\Core\Feature;
use RuntimeException;
use WC_Product;
use WC_Product_Attribute;
use WC_Product_Variable;
use WC_Product_Variation;

/**
 * `wp cigarholic seed` (run by scripts/runtime-up.sh): seven sample products across every category, one of
 * them variable (finish variations with their own SKU, price and stock), each with realistic specifications,
 * attributes, occasions, shipping class, compliance fields and a generated placeholder image with alt text.
 *
 * Sample data is unmistakable: every SKU starts with `SAMPLE-` and every title contains "Sample"
 * (`wp cigarholic catalog check` fails on production while any exists). Idempotent: products are found by
 * SKU and updated in place; images are uploaded once. WooCommerce CRUD only. Refuses to run on production.
 *
 * Feature IDs: FND-02 (primary), CAT-01, CAT-03.
 */
final class Seed implements Feature {

	public const IMAGES_OPTION = 'cigarholic_seed_images';

	/**
	 * Shipping classes: slug => name (D-05).
	 */
	public const SHIPPING_CLASSES = array(
		'humidor'           => 'Humidor',
		'oversized-humidor' => 'Oversized humidor',
		'accessory'         => 'Accessory',
	);

	/**
	 * Feature ID.
	 *
	 * @return string
	 */
	public static function id(): string {
		return 'FND-02';
	}

	/**
	 * Hooks: the `wp cigarholic seed` command (WP-CLI defers it until the namespace exists).
	 *
	 * @return void
	 */
	public function register(): void {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'cigarholic seed', array( $this, 'command' ) );
		}
	}

	/**
	 * Creates or updates the sample catalog (SAMPLE- SKUs, "Sample" in every title).
	 *
	 * Idempotent: products are matched by SKU and updated in place, never duplicated. Ensures the catalog
	 * data model first. Refuses to run on production.
	 *
	 * ## EXAMPLES
	 *
	 *     wp cigarholic seed
	 *
	 * @param array<int, string>    $args       Positional arguments (none).
	 * @param array<string, string> $assoc_args Options (none).
	 * @return void
	 */
	public function command( array $args, array $assoc_args ): void {
		unset( $args, $assoc_args );
		try {
			$summary = self::run();
		} catch ( RuntimeException $e ) {
			\WP_CLI::error( $e->getMessage() );
		}
		foreach ( $summary['log'] as $line ) {
			\WP_CLI::log( $line );
		}
		\WP_CLI::success( sprintf( 'Sample catalog: %d products (%d created, %d updated).', count( $summary['ids'] ), $summary['created'], $summary['updated'] ) );
	}

	/**
	 * Seeds the sample catalog.
	 *
	 * @return array{created: int, updated: int, ids: list<int>, log: list<string>}
	 * @throws RuntimeException On production.
	 */
	public static function run(): array {
		if ( 'production' === wp_get_environment_type() ) {
			throw new RuntimeException( 'Refusing to seed sample data on production.' );
		}

		$summary = array(
			'created' => 0,
			'updated' => 0,
			'ids'     => array(),
			'log'     => array(),
		);

		DataModel::ensure();
		$summary['log'][] = 'data model ensured';

		foreach ( self::SHIPPING_CLASSES as $slug => $name ) {
			DataModel::ensure_term( 'product_shipping_class', $slug, $name, $summary['log'] );
		}

		foreach ( self::products() as $definition ) {
			$id = self::upsert( $definition, $summary );
			if ( $id > 0 ) {
				$summary['ids'][] = $id;
			}
		}

		return $summary;
	}

	/**
	 * Creates or updates one product (and its variations) from a definition.
	 *
	 * @param array<string, mixed>                                                 $d       Product definition.
	 * @param array{created: int, updated: int, ids: list<int>, log: list<string>} $summary Summary, updated.
	 * @return int Product ID, 0 on failure.
	 */
	private static function upsert( array $d, array &$summary ): int {
		$sku         = (string) $d['sku'];
		$type        = (string) $d['type'];
		$existing_id = wc_get_product_id_by_sku( $sku );
		$product     = wc_get_product_object( $type, $existing_id );
		$created     = 0 === $existing_id;

		$weight_unit    = (string) get_option( 'woocommerce_weight_unit', 'lbs' );
		$dimension_unit = (string) get_option( 'woocommerce_dimension_unit', 'in' );
		$dimensions     = (array) $d['dimensions_in'];

		$product->set_name( (string) $d['name'] );
		$product->set_slug( (string) $d['slug'] );
		$product->set_status( 'publish' );
		$product->set_catalog_visibility( 'visible' );
		$product->set_sku( $sku );
		$product->set_description( (string) $d['description'] );
		$product->set_short_description( (string) $d['short_description'] );
		$product->set_reviews_allowed( true );
		$product->set_weight( (string) wc_get_weight( (float) $d['weight_lb'], $weight_unit, 'lbs' ) );
		$product->set_length( (string) wc_get_dimension( (float) $dimensions[0], $dimension_unit, 'in' ) );
		$product->set_width( (string) wc_get_dimension( (float) $dimensions[1], $dimension_unit, 'in' ) );
		$product->set_height( (string) wc_get_dimension( (float) $dimensions[2], $dimension_unit, 'in' ) );
		$product->set_shipping_class_id( DataModel::term_id( 'product_shipping_class', (string) $d['shipping_class'] ) );
		$product->set_category_ids( array_filter( array( DataModel::term_id( 'product_cat', (string) $d['category'] ) ) ) );
		$product->set_attributes( self::attributes( (array) $d['attributes'], isset( $d['variation_attribute'] ) ? (string) $d['variation_attribute'] : '' ) );

		if ( 'simple' === $type ) {
			$product->set_regular_price( (string) $d['price'] );
			$product->set_manage_stock( true );
			$product->set_stock_quantity( (int) $d['stock'] );
			$product->set_stock_status( 'instock' );
		}
		if ( $product instanceof WC_Product_Variable && isset( $d['variation_attribute'], $d['variations'] ) ) {
			$taxonomy = wc_attribute_taxonomy_name( (string) $d['variation_attribute'] );
			$first    = (array) current( (array) $d['variations'] );
			$product->set_default_attributes( array( $taxonomy => (string) $first['term'] ) );
		}

		foreach ( (array) $d['meta'] as $key => $value ) {
			$value = DataModel::sanitize_value( (string) $key, (string) $value );
			if ( '' === $value ) {
				$product->delete_meta_data( (string) $key );
			} else {
				$product->update_meta_data( (string) $key, $value );
			}
		}

		$image = (array) $d['image'];
		$product->set_image_id( self::image_id( (string) $d['slug'], (string) $d['name'], (array) $image['color'], (string) $image['alt'] ) );

		$id = $product->save();
		if ( $id <= 0 ) {
			$summary['log'][] = sprintf( 'failed: %s', $sku );
			return 0;
		}

		$occasions = array_filter( array_map( static fn( string $slug ): int => DataModel::term_id( DataModel::TAXONOMY, $slug ), (array) $d['occasions'] ) );
		wp_set_object_terms( $id, array_values( $occasions ), DataModel::TAXONOMY );

		if ( $product instanceof WC_Product_Variable && isset( $d['variation_attribute'], $d['variations'] ) ) {
			self::upsert_variations( $product, (string) $d['variation_attribute'], (array) $d['variations'], $summary );
			WC_Product_Variable::sync( $id );
		}

		if ( $created ) {
			++$summary['created'];
		} else {
			++$summary['updated'];
		}
		$summary['log'][] = sprintf( '%s %s (#%d)', $created ? 'created' : 'updated', $sku, $id );

		return $id;
	}

	/**
	 * Creates or updates the variations of a variable product, matched by SKU.
	 *
	 * @param WC_Product_Variable                                                  $product   Parent product (saved).
	 * @param string                                                               $attribute Attribute slug (without pa_).
	 * @param array<int, array<string, mixed>>                                     $rows      Variation definitions.
	 * @param array{created: int, updated: int, ids: list<int>, log: list<string>} $summary   Summary, updated.
	 * @return void
	 */
	private static function upsert_variations( WC_Product_Variable $product, string $attribute, array $rows, array &$summary ): void {
		$taxonomy = wc_attribute_taxonomy_name( $attribute );
		foreach ( array_values( $rows ) as $position => $row ) {
			$sku         = (string) $row['sku'];
			$existing_id = wc_get_product_id_by_sku( $sku );
			$variation   = $existing_id > 0 ? wc_get_product( $existing_id ) : null;
			if ( ! $variation instanceof WC_Product_Variation || $variation->get_parent_id() !== $product->get_id() ) {
				$variation = new WC_Product_Variation();
			}
			$variation->set_parent_id( $product->get_id() );
			$variation->set_sku( $sku );
			$variation->set_status( 'publish' );
			$variation->set_menu_order( $position );
			$variation->set_attributes( array( $taxonomy => (string) $row['term'] ) );
			$variation->set_regular_price( (string) $row['price'] );
			$variation->set_manage_stock( true );
			$variation->set_stock_quantity( (int) $row['stock'] );
			$variation->set_stock_status( 'instock' );
			$variation->save();
			$summary['log'][] = sprintf( '  variation %s (#%d)', $sku, $variation->get_id() );
		}
	}

	/**
	 * Builds the WC_Product_Attribute list from slug => term slugs.
	 *
	 * @param array<string, list<string>> $attributes Attribute slug => term slugs.
	 * @param string                      $variation  Attribute slug used for variations, or ''.
	 * @return list<WC_Product_Attribute>
	 */
	private static function attributes( array $attributes, string $variation ): array {
		$list     = array();
		$position = 0;
		foreach ( $attributes as $slug => $term_slugs ) {
			$taxonomy_id = wc_attribute_taxonomy_id_by_name( $slug );
			if ( 0 === $taxonomy_id ) {
				continue;
			}
			$taxonomy = wc_attribute_taxonomy_name( $slug );
			$term_ids = array();
			foreach ( $term_slugs as $term_slug ) {
				$term_id = DataModel::term_id( $taxonomy, $term_slug );
				if ( $term_id > 0 ) {
					$term_ids[] = $term_id;
				}
			}
			if ( array() === $term_ids ) {
				continue;
			}
			$attribute = new WC_Product_Attribute();
			$attribute->set_id( $taxonomy_id );
			$attribute->set_name( $taxonomy );
			$attribute->set_options( $term_ids );
			$attribute->set_position( $position );
			$attribute->set_visible( true );
			$attribute->set_variation( $slug === $variation );
			$list[] = $attribute;
			++$position;
		}
		return $list;
	}

	/**
	 * Attachment ID of the product's placeholder image, generated with GD and uploaded once.
	 *
	 * @param string          $key   Image key (product slug).
	 * @param string          $title Product name.
	 * @param array<int, int> $rgb   Background colour.
	 * @param string          $alt   Alt text.
	 * @return int 0 when GD or the upload directory is unavailable.
	 */
	private static function image_id( string $key, string $title, array $rgb, string $alt ): int {
		$map = get_option( self::IMAGES_OPTION, array() );
		$map = is_array( $map ) ? $map : array();
		if ( isset( $map[ $key ] ) && 'attachment' === get_post_type( (int) $map[ $key ] ) ) {
			$id = (int) $map[ $key ];
			if ( $alt !== get_post_meta( $id, '_wp_attachment_image_alt', true ) ) {
				update_post_meta( $id, '_wp_attachment_image_alt', $alt );
			}
			return $id;
		}

		$png = self::png( $title, $rgb );
		if ( '' === $png ) {
			return 0;
		}
		$upload = wp_upload_bits( $key . '.png', null, $png );
		if ( ! empty( $upload['error'] ) || empty( $upload['file'] ) ) {
			return 0;
		}
		if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}
		if ( ! function_exists( 'wp_read_image_metadata' ) ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
		}
		$id = wp_insert_attachment(
			array(
				'post_mime_type' => 'image/png',
				'post_title'     => $title,
				'post_content'   => '',
				'post_status'    => 'inherit',
			),
			(string) $upload['file']
		);
		if ( is_wp_error( $id ) || 0 >= $id ) {
			return 0;
		}
		wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, (string) $upload['file'] ) );
		update_post_meta( $id, '_wp_attachment_image_alt', $alt );

		$map[ $key ] = $id;
		update_option( self::IMAGES_OPTION, $map, false );

		return $id;
	}

	/**
	 * A 600 x 600 PNG: coloured background, a lighter "lid" band, "SAMPLE" and the product name.
	 *
	 * @param string          $title Product name.
	 * @param array<int, int> $rgb   Background colour.
	 * @return string PNG bytes, '' without GD.
	 */
	private static function png( string $title, array $rgb ): string {
		if ( ! function_exists( 'imagecreatetruecolor' ) ) {
			return '';
		}
		$image = imagecreatetruecolor( 600, 600 );
		if ( false === $image ) {
			return '';
		}
		$r          = (int) ( $rgb[0] ?? 90 );
		$g          = (int) ( $rgb[1] ?? 60 );
		$b          = (int) ( $rgb[2] ?? 40 );
		$background = (int) imagecolorallocate( $image, $r, $g, $b );
		$band       = (int) imagecolorallocate( $image, min( 255, $r + 35 ), min( 255, $g + 30 ), min( 255, $b + 25 ) );
		$white      = (int) imagecolorallocate( $image, 255, 255, 255 );
		imagefill( $image, 0, 0, $background );
		imagefilledrectangle( $image, 70, 200, 530, 400, $band );
		imagerectangle( $image, 70, 200, 530, 400, $white );

		$label = 'SAMPLE';
		imagestring( $image, 5, (int) ( ( 600 - strlen( $label ) * imagefontwidth( 5 ) ) / 2 ), 60, $label, $white );

		$y = 440;
		foreach ( explode( "\n", wordwrap( $title, 40, "\n", true ) ) as $line ) {
			imagestring( $image, 4, (int) ( ( 600 - strlen( $line ) * imagefontwidth( 4 ) ) / 2 ), $y, $line, $white );
			$y += imagefontheight( 4 ) + 4;
		}

		ob_start();
		imagepng( $image, null, 6 );

		return (string) ob_get_clean();
	}

	/**
	 * The sample products. Specifications are inches and pounds; prices USD.
	 *
	 * @return list<array<string, mixed>>
	 */
	public static function products(): array {
		$cedar = 'Lining and dividers: Cedrela odorata, Brazil';

		return array(
			array(
				'sku'                 => 'SAMPLE-DESK-050-WAL-VAR',
				'type'                => 'variable',
				'name'                => 'Sample Walnut Desktop Humidor 50',
				'slug'                => 'sample-walnut-desktop-humidor-50',
				'short_description'   => 'Sample product. A 50-count desktop humidor in American black walnut with a Spanish cedar interior, glass-top hygrometer and humidifier jar. Handcrafted in Vietnam.',
				'description'         => '<p>Sample product for development and staging; not for sale.</p><p>Solid walnut carcass with mitred corners, a Spanish cedar lining and one movable divider. The lid closes on a cedar lip for a tight seal. Includes an analog hygrometer and a humidifier jar.</p><p>What is in the box: humidor, divider, humidifier jar, hygrometer, care and seasoning card.</p>',
				'category'            => 'desktop-humidors',
				'occasions'           => array( 'fathers-day', 'anniversary' ),
				'shipping_class'      => 'humidor',
				'weight_lb'           => 6.5,
				'dimensions_in'       => array( 12.5, 9.25, 4.75 ),
				'attributes'          => array(
					'capacity-range'  => array( '25-50' ),
					'exterior-wood'   => array( 'walnut' ),
					'interior-lining' => array( 'spanish-cedar' ),
					'humidification'  => array( 'humidifier-jar' ),
					'hygrometer'      => array( 'analog' ),
					'finish'          => array( 'matte-lacquer', 'gloss-lacquer' ),
					'lock'            => array( 'none' ),
					'seal'            => array( 'spanish-cedar-lip' ),
				),
				'variation_attribute' => 'finish',
				'variations'          => array(
					array(
						'sku'   => 'SAMPLE-DESK-050-WAL-MATTE',
						'term'  => 'matte-lacquer',
						'price' => '289',
						'stock' => 12,
					),
					array(
						'sku'   => 'SAMPLE-DESK-050-WAL-GLOSS',
						'term'  => 'gloss-lacquer',
						'price' => '309',
						'stock' => 8,
					),
				),
				'meta'                => array(
					'_ch_capacity'            => '50',
					'_ch_max_cigar_length_in' => '7.5',
					'_ch_max_ring_gauge'      => '60',
					'_ch_interior_length_in'  => '11',
					'_ch_interior_width_in'   => '8',
					'_ch_interior_height_in'  => '3.5',
					'_ch_wood_species'        => "Exterior: Juglans nigra, United States\n" . $cedar,
					'_ch_harvest_country'     => 'United States; Brazil',
					'_ch_composite_wood'      => 'no',
					'_ch_hts_code'            => '4420.90.6500',
					'_ch_prop65'              => 'no',
				),
				'image'               => array(
					'color' => array( 94, 62, 40 ),
					'alt'   => 'Sample walnut desktop humidor, placeholder image',
				),
			),
			array(
				'sku'               => 'SAMPLE-DESK-100-MAH-GLOSS',
				'type'              => 'simple',
				'name'              => 'Sample Mahogany Desktop Humidor 100',
				'slug'              => 'sample-mahogany-desktop-humidor-100',
				'short_description' => 'Sample product. A 100-count desktop humidor in gloss mahogany with a digital hygrometer, humidifier bar and key lock. Handcrafted in Vietnam.',
				'description'       => '<p>Sample product for development and staging; not for sale.</p><p>Mahogany veneer over a solid rubberwood carcass, Spanish cedar lining with two dividers and a lift-out tray. Digital front-mounted hygrometer, humidifier bar and a key lock.</p><p>What is in the box: humidor, tray, two dividers, humidifier bar, digital hygrometer, two keys, care and seasoning card.</p>',
				'category'          => 'desktop-humidors',
				'occasions'         => array( 'corporate', 'retirement' ),
				'shipping_class'    => 'humidor',
				'price'             => '389',
				'stock'             => 6,
				'weight_lb'         => 8.8,
				'dimensions_in'     => array( 15, 10.5, 6.5 ),
				'attributes'        => array(
					'capacity-range'  => array( '50-100' ),
					'exterior-wood'   => array( 'mahogany' ),
					'interior-lining' => array( 'spanish-cedar' ),
					'humidification'  => array( 'humidifier-bar' ),
					'hygrometer'      => array( 'digital' ),
					'finish'          => array( 'gloss-lacquer' ),
					'lock'            => array( 'key-lock' ),
					'seal'            => array( 'spanish-cedar-lip' ),
				),
				'meta'              => array(
					'_ch_capacity'            => '100',
					'_ch_max_cigar_length_in' => '8',
					'_ch_max_ring_gauge'      => '64',
					'_ch_interior_length_in'  => '13.5',
					'_ch_interior_width_in'   => '9',
					'_ch_interior_height_in'  => '5',
					'_ch_wood_species'        => "Exterior veneer: Swietenia macrophylla, Peru\nCarcass: Hevea brasiliensis, Vietnam\n" . $cedar,
					'_ch_harvest_country'     => 'Peru; Vietnam; Brazil',
					'_ch_composite_wood'      => 'no',
					'_ch_hts_code'            => '4420.90.6500',
					'_ch_prop65'              => 'no',
				),
				'image'             => array(
					'color' => array( 120, 52, 38 ),
					'alt'   => 'Sample mahogany desktop humidor, placeholder image',
				),
			),
			array(
				'sku'               => 'SAMPLE-CAB-300-OAK-MATTE',
				'type'              => 'simple',
				'name'              => 'Sample Oak Cabinet Humidor 300',
				'slug'              => 'sample-oak-cabinet-humidor-300',
				'short_description' => 'Sample product. A 300-count cabinet humidor in white oak with electronic humidification, a digital hygrometer and four Spanish cedar trays. Handcrafted in Vietnam.',
				'description'       => '<p>Sample product for development and staging; not for sale.</p><p>Free-standing cabinet in solid white oak with a glass door, gasket seal, electronic humidifier and four lift-out Spanish cedar trays. Ships oversized on a pallet.</p><p>What is in the box: cabinet, four trays, electronic humidifier with power adapter, digital hygrometer, two keys, care and seasoning card.</p>',
				'category'          => 'cabinet-humidors',
				'occasions'         => array( 'corporate' ),
				'shipping_class'    => 'oversized-humidor',
				'price'             => '1490',
				'stock'             => 2,
				'weight_lb'         => 48,
				'dimensions_in'     => array( 24, 18, 40 ),
				'attributes'        => array(
					'capacity-range'  => array( '200-plus' ),
					'exterior-wood'   => array( 'oak' ),
					'interior-lining' => array( 'spanish-cedar' ),
					'humidification'  => array( 'electronic' ),
					'hygrometer'      => array( 'digital' ),
					'finish'          => array( 'matte-lacquer' ),
					'lock'            => array( 'key-lock' ),
					'seal'            => array( 'gasket' ),
				),
				'meta'              => array(
					'_ch_capacity'            => '300',
					'_ch_max_cigar_length_in' => '9',
					'_ch_max_ring_gauge'      => '70',
					'_ch_interior_length_in'  => '21',
					'_ch_interior_width_in'   => '15',
					'_ch_interior_height_in'  => '30',
					'_ch_wood_species'        => "Exterior: Quercus alba, United States\nBack panel: plywood, Populus spp., Vietnam\n" . $cedar,
					'_ch_harvest_country'     => 'United States; Vietnam; Brazil',
					'_ch_composite_wood'      => 'yes',
					'_ch_hts_code'            => '4420.90.6500',
					'_ch_prop65'              => 'yes',
				),
				'image'             => array(
					'color' => array( 176, 138, 86 ),
					'alt'   => 'Sample oak cabinet humidor, placeholder image',
				),
			),
			array(
				'sku'               => 'SAMPLE-TRAV-010-ACA-OIL',
				'type'              => 'simple',
				'name'              => 'Sample Acacia Travel Humidor 10',
				'slug'              => 'sample-acacia-travel-humidor-10',
				'short_description' => 'Sample product. A 10-count travel humidor in oiled acacia with a magnetic closure and a pocket for a humidity pack. Handcrafted in Vietnam.',
				'description'       => '<p>Sample product for development and staging; not for sale.</p><p>Compact travel case in Vietnamese acacia with a Spanish cedar insert, magnetic closure and a sleeve for a two-way humidity pack (not included).</p><p>What is in the box: travel humidor, care card.</p>',
				'category'          => 'travel-humidors',
				'occasions'         => array( 'groomsmen', 'holiday' ),
				'shipping_class'    => 'humidor',
				'price'             => '89',
				'stock'             => 25,
				'weight_lb'         => 1.4,
				'dimensions_in'     => array( 9, 5.5, 2.5 ),
				'attributes'        => array(
					'capacity-range'  => array( 'up-to-25' ),
					'exterior-wood'   => array( 'acacia' ),
					'interior-lining' => array( 'spanish-cedar' ),
					'humidification'  => array( 'boveda-packs' ),
					'hygrometer'      => array( 'none' ),
					'finish'          => array( 'oil' ),
					'lock'            => array( 'none' ),
					'seal'            => array( 'magnetic' ),
				),
				'meta'              => array(
					'_ch_capacity'            => '10',
					'_ch_max_cigar_length_in' => '7.5',
					'_ch_max_ring_gauge'      => '56',
					'_ch_interior_length_in'  => '8',
					'_ch_interior_width_in'   => '4.5',
					'_ch_interior_height_in'  => '1.75',
					'_ch_wood_species'        => "Exterior: Acacia mangium, Vietnam\n" . $cedar,
					'_ch_harvest_country'     => 'Vietnam; Brazil',
					'_ch_composite_wood'      => 'no',
					'_ch_hts_code'            => '4420.90.6500',
					'_ch_prop65'              => 'no',
				),
				'image'             => array(
					'color' => array( 150, 100, 60 ),
					'alt'   => 'Sample acacia travel humidor, placeholder image',
				),
			),
			array(
				'sku'               => 'SAMPLE-BOX-025-CHE-OIL',
				'type'              => 'simple',
				'name'              => 'Sample Cherry Cigar Box 25',
				'slug'              => 'sample-cherry-cigar-box-25',
				'short_description' => 'Sample product. A 25-count cigar box in oiled cherry with a felt-lined lid and a Spanish cedar base. Handcrafted in Vietnam.',
				'description'       => '<p>Sample product for development and staging; not for sale.</p><p>A presentation box rather than a humidor: cherry exterior, Spanish cedar base, felt-lined lid, no humidification. For short-term storage and gifting.</p><p>What is in the box: cigar box, care card.</p>',
				'category'          => 'cigar-boxes',
				'occasions'         => array( 'fathers-day', 'groomsmen' ),
				'shipping_class'    => 'humidor',
				'price'             => '119',
				'stock'             => 15,
				'weight_lb'         => 2.6,
				'dimensions_in'     => array( 10.5, 7.5, 3.25 ),
				'attributes'        => array(
					'capacity-range'  => array( 'up-to-25' ),
					'exterior-wood'   => array( 'cherry' ),
					'interior-lining' => array( 'spanish-cedar' ),
					'humidification'  => array( 'none' ),
					'hygrometer'      => array( 'none' ),
					'finish'          => array( 'oil' ),
					'lock'            => array( 'none' ),
					'seal'            => array( 'felt' ),
				),
				'meta'              => array(
					'_ch_capacity'            => '25',
					'_ch_max_cigar_length_in' => '7',
					'_ch_max_ring_gauge'      => '54',
					'_ch_interior_length_in'  => '9.5',
					'_ch_interior_width_in'   => '6.5',
					'_ch_interior_height_in'  => '2.5',
					'_ch_wood_species'        => "Exterior: Prunus serotina, United States\nBase: Cedrela odorata, Brazil",
					'_ch_harvest_country'     => 'United States; Brazil',
					'_ch_composite_wood'      => 'no',
					'_ch_hts_code'            => '4420.90.4500',
					'_ch_prop65'              => 'no',
				),
				'image'             => array(
					'color' => array( 140, 70, 50 ),
					'alt'   => 'Sample cherry cigar box, placeholder image',
				),
			),
			array(
				'sku'               => 'SAMPLE-ACC-000-NONE-NONE',
				'type'              => 'simple',
				'name'              => 'Sample Digital Hygrometer',
				'slug'              => 'sample-digital-hygrometer',
				'short_description' => 'Sample product. A round digital hygrometer and thermometer with a magnetic mount, calibrated to within two percent.',
				'description'       => '<p>Sample product for development and staging; not for sale.</p><p>Front-mounted digital hygrometer with a 2.5 inch face, magnetic back plate and a replaceable battery. Fits every Cigarholic humidor.</p><p>What is in the box: hygrometer, magnetic plate, battery, instructions.</p>',
				'category'          => 'humidor-accessories',
				'occasions'         => array(),
				'shipping_class'    => 'accessory',
				'price'             => '24',
				'stock'             => 40,
				'weight_lb'         => 0.2,
				'dimensions_in'     => array( 2.5, 2.5, 0.6 ),
				'attributes'        => array(
					'hygrometer' => array( 'digital' ),
				),
				'meta'              => array(
					'_ch_composite_wood' => 'no',
					'_ch_hts_code'       => '9025.80.1000',
					'_ch_prop65'         => 'no',
				),
				'image'             => array(
					'color' => array( 70, 70, 80 ),
					'alt'   => 'Sample digital hygrometer, placeholder image',
				),
			),
			array(
				'sku'               => 'SAMPLE-GIFT-010-WAL-MATTE',
				'type'              => 'simple',
				'name'              => 'Sample Groomsmen Gift Set',
				'slug'              => 'sample-groomsmen-gift-set',
				'short_description' => 'Sample product. A walnut 10-count travel humidor with a stainless cutter in a gift box. Handcrafted in Vietnam.',
				'description'       => '<p>Sample product for development and staging; not for sale.</p><p>A matte walnut travel humidor with Spanish cedar insert and magnetic closure, paired with a double-blade stainless cutter, in a kraft gift box.</p><p>What is in the box: travel humidor, cutter, gift box, care card.</p>',
				'category'          => 'gift-sets',
				'occasions'         => array( 'groomsmen', 'corporate' ),
				'shipping_class'    => 'humidor',
				'price'             => '149',
				'stock'             => 10,
				'weight_lb'         => 2.2,
				'dimensions_in'     => array( 10, 6, 3 ),
				'attributes'        => array(
					'capacity-range'  => array( 'up-to-25' ),
					'exterior-wood'   => array( 'walnut' ),
					'interior-lining' => array( 'spanish-cedar' ),
					'humidification'  => array( 'boveda-packs' ),
					'hygrometer'      => array( 'none' ),
					'finish'          => array( 'matte-lacquer' ),
					'lock'            => array( 'none' ),
					'seal'            => array( 'magnetic' ),
				),
				'meta'              => array(
					'_ch_capacity'            => '10',
					'_ch_max_cigar_length_in' => '7.5',
					'_ch_max_ring_gauge'      => '56',
					'_ch_interior_length_in'  => '8.5',
					'_ch_interior_width_in'   => '4.5',
					'_ch_interior_height_in'  => '1.75',
					'_ch_wood_species'        => "Humidor exterior: Juglans nigra, United States\n" . $cedar,
					'_ch_harvest_country'     => 'United States; Brazil',
					'_ch_composite_wood'      => 'no',
					'_ch_hts_code'            => '4420.90.6500',
					'_ch_prop65'              => 'no',
				),
				'image'             => array(
					'color' => array( 60, 90, 70 ),
					'alt'   => 'Sample groomsmen gift set, placeholder image',
				),
			),
		);
	}
}
