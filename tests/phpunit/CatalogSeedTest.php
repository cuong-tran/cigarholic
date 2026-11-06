<?php
/**
 * Tests for the sample catalog.
 *
 * @package Cigarholic
 */

namespace Cigarholic\Tests;

use Cigarholic\Core\Catalog\Check;
use Cigarholic\Core\Catalog\DataModel;
use Cigarholic\Core\Catalog\Seed;
use Cigarholic\Core\Catalog\Sku;
use Cigarholic\Core\Catalog\SpecTable;
use WC_Product;
use WP_UnitTestCase;

/**
 * Feature IDs: FND-02, CAT-01, CAT-03, CAT-09.
 */
final class CatalogSeedTest extends WP_UnitTestCase {

	/**
	 * Uploaded sample images are deleted from the uploads directory.
	 */
	public function tear_down(): void {
		$this->remove_added_uploads();
		parent::tear_down();
	}

	/**
	 * Products (every type including variations) and attachments.
	 *
	 * @return array<string, int>
	 */
	private function counts(): array {
		$products = wc_get_products(
			array(
				'type'   => array( 'simple', 'variable', 'variation', 'grouped', 'external' ),
				'status' => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'limit'  => -1,
				'return' => 'ids',
			)
		);
		$images   = get_posts(
			array(
				'post_type'   => 'attachment',
				'post_status' => 'any',
				'numberposts' => -1,
				'fields'      => 'ids',
			)
		);
		return array(
			'products' => count( (array) $products ),
			'images'   => count( $images ),
		);
	}

	/**
	 * Two runs produce the same catalog, which passes the check and fails it on production.
	 */
	public function test_seed_twice_then_check(): void {
		$first = Seed::run();
		$this->assertCount( 7, $first['ids'] );
		$this->assertSame( 7, $first['created'] );
		$this->assertSame( 0, $first['updated'] );
		$counts = $this->counts();
		$this->assertSame( 9, $counts['products'], '7 products and 2 variations' );
		$this->assertSame( 7, $counts['images'], 'one image per product' );

		$second = Seed::run();
		$this->assertSame( 0, $second['created'] );
		$this->assertSame( 7, $second['updated'] );
		$this->assertSame( $first['ids'], $second['ids'], 'the same products, by SKU' );
		$this->assertSame( $counts, $this->counts(), 'nothing duplicated' );

		$check = Check::run( 'development' );
		$this->assertSame( array(), $check['problems'], wp_json_encode( $check['problems'] ) );
		$this->assertSame( 7, $check['products'] );
		$this->assertSame( 2, $check['variations'] );
		$this->assertSame( array(), $check['warnings'] );

		$production = Check::run( 'production' );
		$codes      = array_unique( array_column( $production['problems'], 'code' ) );
		$this->assertSame( array( 'sample_data' ), array_values( $codes ) );
		$this->assertCount( 9, $production['problems'], 'every sample product and variation is flagged' );
	}

	/**
	 * The variable product has finish variations with their own SKU, price and stock; every product is
	 * clearly sample data and carries occasions, attributes, compliance fields and an image with alt text.
	 */
	public function test_products(): void {
		Seed::run();

		foreach ( Seed::products() as $definition ) {
			$product = wc_get_product( wc_get_product_id_by_sku( (string) $definition['sku'] ) );
			$this->assertInstanceOf( WC_Product::class, $product, (string) $definition['sku'] );
			$this->assertTrue( Sku::is_sample( $product->get_sku() ) );
			$this->assertTrue( Sku::is_valid( $product->get_sku() ), $product->get_sku() );
			$this->assertStringContainsString( 'Sample', $product->get_name() );
			$this->assertSame( 'publish', $product->get_status() );
			$this->assertSame( array( $definition['category'] ), wp_get_post_terms( $product->get_id(), 'product_cat', array( 'fields' => 'slugs' ) ) );
			$this->assertSame( $definition['shipping_class'], $product->get_shipping_class() );
			$this->assertGreaterThan( 0, $product->get_image_id() );
			$this->assertSame( $definition['image']['alt'], get_post_meta( $product->get_image_id(), '_wp_attachment_image_alt', true ) );
			$this->assertSame( (string) $definition['weight_lb'], $product->get_weight() );
			$this->assertSame( (string) $definition['dimensions_in'][0], $product->get_length() );
			$occasions = (array) wp_get_post_terms( $product->get_id(), DataModel::TAXONOMY, array( 'fields' => 'slugs' ) );
			$expected  = array_values( $definition['occasions'] );
			sort( $occasions );
			sort( $expected );
			$this->assertSame( $expected, $occasions, 'occasions assigned' );
			foreach ( $definition['attributes'] as $slug => $term_slugs ) {
				$actual = (array) wc_get_product_terms( $product->get_id(), 'pa_' . $slug, array( 'fields' => 'slugs' ) );
				sort( $actual );
				sort( $term_slugs );
				$this->assertSame( $term_slugs, $actual, $slug );
			}
			foreach ( $definition['meta'] as $key => $value ) {
				$this->assertSame( DataModel::sanitize_value( (string) $key, (string) $value ), $product->get_meta( (string) $key ), $key );
			}
			$this->assertStringContainsString( ' in (', SpecTable::render( $product ) );
		}

		$parent = wc_get_product( wc_get_product_id_by_sku( 'SAMPLE-DESK-050-WAL-VAR' ) );
		$this->assertInstanceOf( WC_Product::class, $parent );
		$this->assertTrue( $parent->is_type( 'variable' ) );
		$this->assertCount( 2, $parent->get_children() );
		$this->assertSame( array( 'pa_finish' => 'matte-lacquer' ), $parent->get_default_attributes() );
		$this->assertTrue( $parent->is_purchasable() );
		$this->assertTrue( $parent->is_in_stock() );

		$matte = wc_get_product( wc_get_product_id_by_sku( 'SAMPLE-DESK-050-WAL-MATTE' ) );
		$gloss = wc_get_product( wc_get_product_id_by_sku( 'SAMPLE-DESK-050-WAL-GLOSS' ) );
		$this->assertInstanceOf( WC_Product::class, $matte );
		$this->assertInstanceOf( WC_Product::class, $gloss );
		$this->assertSame( '289', $matte->get_regular_price() );
		$this->assertSame( '309', $gloss->get_regular_price() );
		$this->assertSame( 12, $matte->get_stock_quantity() );
		$this->assertSame( array( 'pa_finish' => 'gloss-lacquer' ), $gloss->get_attributes() );
		$this->assertSame( '6.5', $gloss->get_weight(), 'variations inherit the parent weight' );

		$cabinet = wc_get_product( wc_get_product_id_by_sku( 'SAMPLE-CAB-300-OAK-MATTE' ) );
		$this->assertInstanceOf( WC_Product::class, $cabinet );
		$this->assertTrue( DataModel::is_yes( $cabinet->get_meta( '_ch_prop65' ) ), 'one sample shows the Prop 65 warning' );
	}

	/**
	 * Every sample product gets its rendered photo (a JPEG shipped in cigarholic-core), not a GD placeholder.
	 */
	public function test_products_use_the_sample_photos(): void {
		Seed::run();

		foreach ( Seed::products() as $definition ) {
			$slug  = (string) $definition['slug'];
			$photo = Seed::photo_path( $slug );
			$this->assertNotSame( '', $photo, "sample photo for {$slug}" );
			$size = getimagesize( $photo );
			$this->assertIsArray( $size );
			$this->assertSame( array( 1200, 1200, IMAGETYPE_JPEG ), array( $size[0], $size[1], $size[2] ), $slug );

			$product = wc_get_product( wc_get_product_id_by_sku( (string) $definition['sku'] ) );
			$this->assertInstanceOf( WC_Product::class, $product );
			$image = $product->get_image_id();
			$this->assertSame( 'image/jpeg', get_post_mime_type( $image ), $slug );
			$this->assertSame( sha1_file( $photo ), get_post_meta( $image, Seed::SOURCE_META, true ), $slug );
			$this->assertNotEmpty( wp_get_attachment_image_src( (int) $image, 'woocommerce_thumbnail' ), $slug );
		}
		$this->assertSame( '', Seed::photo_path( '../sample-walnut-desktop-humidor-50' ), 'no path outside the folder' );
	}

	/**
	 * A catalog seeded before the photos existed (GD placeholders, no source meta) gets the photos on the
	 * next run, once: the run after that changes nothing.
	 */
	public function test_placeholders_from_an_earlier_seed_are_replaced_once(): void {
		Seed::run();
		$map = (array) get_option( Seed::IMAGES_OPTION );
		$key = 'sample-cherry-cigar-box-25';
		$old = (int) $map[ $key ];
		delete_post_meta( $old, Seed::SOURCE_META );

		Seed::run();
		$product = wc_get_product( wc_get_product_id_by_sku( 'SAMPLE-BOX-025-CHE-OIL' ) );
		$this->assertInstanceOf( WC_Product::class, $product );
		$new = (int) $product->get_image_id();
		$this->assertNotSame( $old, $new, 'a new attachment for the photo' );
		$this->assertSame( $new, (int) ( (array) get_option( Seed::IMAGES_OPTION ) )[ $key ] );
		$this->assertSame( 'attachment', get_post_type( $old ), 'the old image stays in the media library' );

		$counts = $this->counts();
		Seed::run();
		$this->assertSame( $new, (int) wc_get_product( wc_get_product_id_by_sku( 'SAMPLE-BOX-025-CHE-OIL' ) )->get_image_id() );
		$this->assertSame( $counts, $this->counts(), 'nothing uploaded again' );

		// A failed upload of a newer photo keeps the image the product already has.
		delete_post_meta( $new, Seed::SOURCE_META );
		$broken = static fn(): string => 'uploads unavailable';
		add_filter( 'wp_upload_bits', $broken );
		Seed::run();
		remove_filter( 'wp_upload_bits', $broken );
		$this->assertSame( $new, (int) wc_get_product( wc_get_product_id_by_sku( 'SAMPLE-BOX-025-CHE-OIL' ) )->get_image_id(), 'image kept when the upload fails' );
		$this->assertSame( $counts, $this->counts() );
	}
}
