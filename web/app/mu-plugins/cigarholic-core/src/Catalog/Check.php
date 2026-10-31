<?php
/**
 * Catalog data hygiene check.
 *
 * @package Cigarholic
 */

namespace Cigarholic\Core\Catalog;

use Cigarholic\Core\Feature;
use WC_Product;
use WP_Term;

/**
 * Reports what operations, shipping, feeds and customs paperwork need on every product and variation
 * (`wp cigarholic catalog check`, the brief's `catalog:check`): SKU present, following the scheme and
 * unique; weight and dimensions; wood species and harvest country (CMP-11); featured image alt text;
 * cigar capacity on humidors; unique category slugs (ADR 0002). On production it also fails while any
 * `SAMPLE-` product exists. Store units other than lbs/in are reported as warnings.
 *
 * Feature IDs: CAT-09 (primary), INV-01, CMP-11.
 */
final class Check implements Feature {

	/**
	 * Feature ID.
	 *
	 * @return string
	 */
	public static function id(): string {
		return 'CAT-09';
	}

	/**
	 * Hooks: the `wp cigarholic catalog` command (WP-CLI defers it until the namespace exists).
	 *
	 * @return void
	 */
	public function register(): void {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'cigarholic catalog', Cli::class );
		}
	}

	/**
	 * Runs every check.
	 *
	 * @param string|null $environment Environment type; defaults to wp_get_environment_type().
	 * @return array{problems: list<array{code: string, product_id: int, sku: string, message: string}>, warnings: list<array{code: string, product_id: int, sku: string, message: string}>, products: int, variations: int}
	 */
	public static function run( ?string $environment = null ): array {
		$environment = $environment ?? wp_get_environment_type();
		$production  = 'production' === $environment;
		$result      = array(
			'problems'   => array(),
			'warnings'   => array(),
			'products'   => 0,
			'variations' => 0,
		);
		$skus        = array();

		$ids = wc_get_products(
			array(
				'type'    => array( 'simple', 'variable', 'grouped', 'external' ),
				'status'  => array( 'publish', 'future', 'draft', 'pending', 'private' ),
				'limit'   => -1,
				'orderby' => 'ID',
				'order'   => 'ASC',
				'return'  => 'ids',
			)
		);

		foreach ( (array) $ids as $id ) {
			$product = wc_get_product( (int) $id );
			if ( ! $product instanceof WC_Product ) {
				continue;
			}
			++$result['products'];
			self::check_sku( $product, $skus, $production, $result['problems'] );

			$categories   = wp_get_post_terms( $product->get_id(), 'product_cat', array( 'fields' => 'slugs' ) );
			$categories   = is_array( $categories ) ? array_map( 'strval', $categories ) : array();
			$is_humidor   = array() !== array_intersect( $categories, DataModel::HUMIDOR_CATEGORIES );
			$is_accessory = in_array( 'humidor-accessories', $categories, true );
			$has_wood     = ! $is_accessory || self::has_wood_attribute( $product );

			$image_id = $product->get_image_id( 'edit' );
			if ( (int) $image_id <= 0 ) {
				self::add( $result['problems'], 'missing_image', $product, __( 'No featured image.', 'cigarholic' ) );
			} elseif ( '' === trim( (string) get_post_meta( (int) $image_id, '_wp_attachment_image_alt', true ) ) ) {
				self::add( $result['problems'], 'missing_image_alt', $product, __( 'Featured image has no alt text.', 'cigarholic' ) );
			}

			if ( $is_humidor && DataModel::meta_int( $product, '_ch_capacity' ) <= 0 ) {
				self::add( $result['problems'], 'missing_capacity', $product, __( 'Humidor without cigar capacity (_ch_capacity).', 'cigarholic' ) );
			}

			if ( $has_wood ) {
				if ( '' === DataModel::meta_string( $product, '_ch_wood_species' ) ) {
					self::add( $result['problems'], 'missing_wood_species', $product, __( 'Wood species missing (_ch_wood_species, Lacey Act).', 'cigarholic' ) );
				}
				if ( '' === DataModel::meta_string( $product, '_ch_harvest_country' ) ) {
					self::add( $result['problems'], 'missing_harvest_country', $product, __( 'Country of harvest missing (_ch_harvest_country).', 'cigarholic' ) );
				}
			}

			if ( $product->is_type( 'variable' ) ) {
				foreach ( $product->get_children() as $variation_id ) {
					$variation = wc_get_product( (int) $variation_id );
					if ( ! $variation instanceof WC_Product ) {
						continue;
					}
					++$result['variations'];
					self::check_sku( $variation, $skus, $production, $result['problems'] );
					self::check_shipping_data( $variation, $result['problems'] );
				}
			} elseif ( $product->is_type( 'simple' ) ) {
				self::check_shipping_data( $product, $result['problems'] );
			}
		}

		foreach ( $skus as $sku => $owners ) {
			if ( count( $owners ) < 2 ) {
				continue;
			}
			foreach ( $owners as $owner_id ) {
				$result['problems'][] = array(
					'code'       => 'duplicate_sku',
					'product_id' => $owner_id,
					'sku'        => (string) $sku,
					'message'    => sprintf(
						/* translators: %s: comma-separated product IDs */
						__( 'SKU is used by products %s.', 'cigarholic' ),
						implode( ', ', array_map( 'strval', $owners ) )
					),
				);
			}
		}

		self::check_categories( $result['problems'] );

		if ( 'lbs' !== get_option( 'woocommerce_weight_unit' ) ) {
			$result['warnings'][] = array(
				'code'       => 'weight_unit',
				'product_id' => 0,
				'sku'        => '',
				'message'    => __( 'Store weight unit is not lbs (ADR 0003 stores pounds; the CSV template header is "Weight (lbs)").', 'cigarholic' ),
			);
		}
		if ( 'in' !== get_option( 'woocommerce_dimension_unit' ) ) {
			$result['warnings'][] = array(
				'code'       => 'dimension_unit',
				'product_id' => 0,
				'sku'        => '',
				'message'    => __( 'Store dimension unit is not in (ADR 0003 stores inches; the CSV template headers are "Length (in)" etc.).', 'cigarholic' ),
			);
		}

		return $result;
	}

	/**
	 * SKU presence, scheme, sample data on production; collects SKUs for the duplicate check.
	 *
	 * @param WC_Product                 $product    Product or variation.
	 * @param array<string, list<int>>   $skus       SKU => product IDs, appended to.
	 * @param bool                       $production Whether the environment is production.
	 * @param list<array<string, mixed>> $problems   Problems, appended to.
	 * @return void
	 */
	private static function check_sku( WC_Product $product, array &$skus, bool $production, array &$problems ): void {
		$sku = (string) $product->get_sku( 'edit' );
		if ( '' === $sku ) {
			self::add( $problems, 'missing_sku', $product, __( 'No SKU.', 'cigarholic' ) );
			return;
		}
		$skus[ $sku ][] = $product->get_id();
		if ( ! Sku::is_valid( $sku ) ) {
			self::add( $problems, 'invalid_sku', $product, __( 'SKU does not follow CH-{TYPE}-{CAPACITY}-{WOOD}-{FINISH}.', 'cigarholic' ) );
		}
		if ( $production && ( Sku::is_sample( $sku ) || str_contains( strtolower( $product->get_name( 'edit' ) ), 'sample' ) ) ) {
			self::add( $problems, 'sample_data', $product, __( 'Sample product on production.', 'cigarholic' ) );
		}
	}

	/**
	 * Weight and dimensions (a variation falls back to its parent's values).
	 *
	 * @param WC_Product                 $product  Simple product or variation.
	 * @param list<array<string, mixed>> $problems Problems, appended to.
	 * @return void
	 */
	private static function check_shipping_data( WC_Product $product, array &$problems ): void {
		if ( $product->is_virtual() ) {
			return;
		}
		if ( ! $product->has_weight() ) {
			self::add( $problems, 'missing_weight', $product, __( 'No shipping weight.', 'cigarholic' ) );
		}
		if ( ! $product->has_dimensions() ) {
			self::add( $problems, 'missing_dimensions', $product, __( 'No package dimensions.', 'cigarholic' ) );
		}
	}

	/**
	 * Whether a product carries an exterior wood or interior lining attribute.
	 *
	 * @param WC_Product $product Product.
	 * @return bool
	 */
	private static function has_wood_attribute( WC_Product $product ): bool {
		foreach ( DataModel::WOOD_ATTRIBUTES as $slug ) {
			$terms = wc_get_product_terms( $product->get_id(), wc_attribute_taxonomy_name( $slug ), array( 'fields' => 'ids' ) );
			if ( is_array( $terms ) && array() !== $terms ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Category slugs must be unique across the whole tree (ADR 0002): WordPress suffixes a clash with
	 * "-2", so suffixed slugs and repeated names are both reported.
	 *
	 * @param list<array<string, mixed>> $problems Problems, appended to.
	 * @return void
	 */
	private static function check_categories( array &$problems ): void {
		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
			)
		);
		if ( ! is_array( $terms ) ) {
			return;
		}
		$by_slug = array();
		$by_name = array();
		foreach ( $terms as $term ) {
			if ( ! $term instanceof WP_Term || 'uncategorized' === $term->slug ) {
				continue;
			}
			$base               = strtolower( (string) preg_replace( '/-\d+$/', '', $term->slug ) );
			$by_slug[ $base ][] = $term->slug;
			$name               = strtolower( trim( $term->name ) );
			$by_name[ $name ][] = $term->slug;
		}
		foreach ( $by_slug as $slugs ) {
			if ( count( $slugs ) > 1 ) {
				$problems[] = array(
					'code'       => 'duplicate_category_slug',
					'product_id' => 0,
					'sku'        => '',
					/* translators: %s: comma-separated category slugs */
					'message'    => sprintf( __( 'Category slugs clash: %s (URLs must be unique across the tree).', 'cigarholic' ), implode( ', ', $slugs ) ),
				);
			}
		}
		foreach ( $by_name as $slugs ) {
			if ( count( $slugs ) > 1 ) {
				$problems[] = array(
					'code'       => 'duplicate_category_name',
					'product_id' => 0,
					'sku'        => '',
					/* translators: %s: comma-separated category slugs */
					'message'    => sprintf( __( 'Two categories share a name: %s.', 'cigarholic' ), implode( ', ', $slugs ) ),
				);
			}
		}
	}

	/**
	 * Appends a problem for a product.
	 *
	 * @param list<array<string, mixed>> $problems Problems.
	 * @param string                     $code     Problem code.
	 * @param WC_Product                 $product  Product or variation.
	 * @param string                     $message  Message.
	 * @return void
	 */
	private static function add( array &$problems, string $code, WC_Product $product, string $message ): void {
		$problems[] = array(
			'code'       => $code,
			'product_id' => $product->get_id(),
			'sku'        => (string) $product->get_sku( 'edit' ),
			'message'    => $message,
		);
	}
}
