<?php
/**
 * Tests for the provisioning steps: they apply and are idempotent.
 *
 * @package Cigarholic
 */

namespace Cigarholic\Tests;

use Automattic\WooCommerce\Utilities\OrderUtil;
use Cigarholic\Core\Plugin;
use Cigarholic\Core\Provision\Runner;
use WP_UnitTestCase;

/**
 * Feature IDs: FND-03, CHK-01, CNT-02, CNT-04, NAV-01, SHP-01, SHP-02, SEO-03.
 */
final class ProvisionTest extends WP_UnitTestCase {

	/**
	 * Log lines of the last run.
	 *
	 * @var list<string>
	 */
	private array $lines = array();

	/**
	 * A runner over the repository's provision/steps that collects its output.
	 *
	 * @return Runner
	 */
	private function runner(): Runner {
		$this->lines = array();
		return new Runner(
			Plugin::instance()->provision_dir(),
			function ( string $line ): void {
				$this->lines[] = $line;
			}
		);
	}

	/**
	 * Every step applies on a fresh install and a second (dry) run reports no change.
	 */
	public function test_all_steps_apply_once(): void {
		$runner = $this->runner();
		$this->assertGreaterThan( 0, $runner->run( false ), 'the first run must change a fresh install' );
		$this->assertSame( 0, $runner->run( true ), "second run is not a no-op:\n" . implode( "\n", $this->lines ) );
	}

	/**
	 * The settings the store relies on are in place after a run.
	 */
	public function test_settings_after_run(): void {
		$this->runner()->run( false );

		// 10: store, HPOS, block pages.
		$this->assertSame( 'US:FL', get_option( 'woocommerce_default_country' ) );
		$this->assertSame( array( 'US' ), get_option( 'woocommerce_specific_allowed_countries' ) );
		$this->assertSame( 'yes', get_option( 'woocommerce_custom_orders_table_enabled' ) );
		$this->assertTrue( OrderUtil::custom_orders_table_usage_is_enabled() );
		foreach ( array(
			'cart'     => 'woocommerce/cart',
			'checkout' => 'woocommerce/checkout',
		) as $page => $block ) {
			$post = get_post( wc_get_page_id( $page ) );
			$this->assertInstanceOf( \WP_Post::class, $post, $page );
			$this->assertTrue( has_block( $block, $post ), sprintf( '%s page carries %s', $page, $block ) );
		}

		// 11, 12.
		$this->assertSame( 'required', get_option( 'woocommerce_checkout_phone_field' ) );
		$permalinks = get_option( 'woocommerce_permalinks' );
		$this->assertIsArray( $permalinks );
		$this->assertSame( '/products', $permalinks['product_base'] );
		$this->assertSame( 'collections', $permalinks['category_base'] );

		// 20: pages as drafts, reading settings, checkout links.
		foreach ( array( 'about', 'contact', 'faq', 'shipping-policy', 'returns-refunds', 'warranty', 'privacy-policy', 'terms-of-service', 'accessibility-statement' ) as $slug ) {
			$page = get_page_by_path( $slug, OBJECT, 'page' );
			$this->assertInstanceOf( \WP_Post::class, $page, $slug );
			$this->assertSame( 'draft', $page->post_status, $slug );
		}
		$this->assertStringContainsString( 'DRAFT: for owner review', (string) get_page_by_path( 'warranty', OBJECT, 'page' )->post_content );
		$this->assertSame( 'page', get_option( 'show_on_front' ) );
		$this->assertSame( 'journal', get_post_field( 'post_name', (int) get_option( 'page_for_posts' ) ) );
		$this->assertSame( 'home', get_post_field( 'post_name', (int) get_option( 'page_on_front' ) ) );
		$this->assertSame( 'terms-of-service', get_post_field( 'post_name', (int) get_option( 'woocommerce_terms_page_id' ) ) );
		$this->assertSame( 'privacy-policy', get_post_field( 'post_name', (int) get_option( 'wp_page_for_privacy_policy' ) ) );

		// 30: exactly one navigation post, with the primary links.
		$menus = get_posts(
			array(
				'post_type'      => 'wp_navigation',
				'post_status'    => 'any',
				'posts_per_page' => 10,
			)
		);
		$this->assertCount( 1, $menus );
		$this->assertSame( 'primary', $menus[0]->post_name );
		$this->assertStringContainsString( '"url":"/collections/cigar-boxes/"', $menus[0]->post_content );
		$this->assertStringContainsString( '"url":"/journal/"', $menus[0]->post_content );
		$this->assertSame( $menus[0]->ID, \WP_Navigation_Fallback::get_fallback()->ID );

		// 50: two zones, one flat rate method on the mainland, none for the remote states.
		$zones = array_column( \WC_Shipping_Zones::get_zones(), null, 'zone_name' );
		$this->assertArrayHasKey( 'Contiguous US', $zones );
		$this->assertArrayHasKey( 'Alaska, Hawaii and US territories', $zones );
		$this->assertCount( 49, $zones['Contiguous US']['zone_locations'] );
		$this->assertCount( 1, $zones['Contiguous US']['shipping_methods'] );
		$this->assertCount( 8, $zones['Alaska, Hawaii and US territories']['zone_locations'] );
		$this->assertCount( 0, $zones['Alaska, Hawaii and US territories']['shipping_methods'] );
		$method = reset( $zones['Contiguous US']['shipping_methods'] );
		$this->assertSame( 'flat_rate', $method->id );
		$accessory = get_term_by( 'slug', 'accessory', 'product_shipping_class' );
		$this->assertInstanceOf( \WP_Term::class, $accessory );
		$this->assertSame( '9.95', $method->get_instance_option( 'class_cost_' . $accessory->term_id ) );
		$this->assertSame( 'order', $method->get_instance_option( 'type' ) );

		// 60, 70, 80.
		$this->assertSame( 'yes', get_option( 'woocommerce_calc_taxes' ) );
		$this->assertSame( 'support@cigarholic.com', get_option( 'woocommerce_email_from_address' ) );
		$this->assertSame( '#6b4a2e', get_option( 'woocommerce_email_base_color' ) );
		$new_order = get_option( 'woocommerce_new_order_settings' );
		$this->assertSame( get_option( 'admin_email' ), $new_order['recipient'] );
		$this->assertSame( 'yes', get_option( 'woocommerce_coming_soon' ) );
		$this->assertSame( 'no', get_option( 'woocommerce_store_pages_only' ) );
	}

	/**
	 * A dry run never writes.
	 */
	public function test_dry_run_changes_nothing(): void {
		$before = get_option( 'woocommerce_default_country' );
		$this->assertNotSame( 'US:FL', $before );

		$this->assertGreaterThan( 0, $this->runner()->run( true ) );

		$this->assertSame( $before, get_option( 'woocommerce_default_country' ) );
		$this->assertNull( get_page_by_path( 'warranty', OBJECT, 'page' ) );
		$this->assertNotEmpty( array_filter( $this->lines, static fn( string $line ): bool => str_contains( $line, 'would change' ) ) );
	}
}
