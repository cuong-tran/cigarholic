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

		// 45: authenticator app and backup codes only (OPS-07).
		$this->assertSame( array( 'Two_Factor_Totp', 'Two_Factor_Backup_Codes' ), get_option( 'two_factor_enabled_providers' ) );

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
		// Brand colours from the theme palette (CNT-01): Forest header, Ash outer, Ash tint body, Ink text, Stone footer.
		$this->assertSame( '#1f2b25', get_option( 'woocommerce_email_base_color' ) );
		$this->assertSame( '#eee9e0', get_option( 'woocommerce_email_background_color' ) );
		$this->assertSame( '#f7f4ef', get_option( 'woocommerce_email_body_background_color' ) );
		$this->assertSame( '#1a1815', get_option( 'woocommerce_email_text_color' ) );
		$this->assertSame( '#5e574e', get_option( 'woocommerce_email_footer_text_color' ) );
		$new_order = get_option( 'woocommerce_new_order_settings' );
		$this->assertSame( get_option( 'admin_email' ), $new_order['recipient'] );
		$this->assertSame( 'yes', get_option( 'woocommerce_coming_soon' ) );
		$this->assertSame( ( defined( 'DISALLOW_INDEXING' ) && DISALLOW_INDEXING ) ? '0' : '1', get_option( 'blog_public' ) );
		$this->assertSame( 'no', get_option( 'woocommerce_store_pages_only' ) );
	}

	/**
	 * Step 85: a coming-soon template or a header/footer part saved in the Site Editor hides theme
	 * changes; the run names each one and keeps it (CNT-01, NAV-01, NAV-03).
	 */
	public function test_saved_theme_files_are_reported_not_deleted(): void {
		$this->runner()->run( true );
		$this->assertEmpty( array_filter( $this->lines, static fn( string $line ): bool => str_contains( $line, 'was saved in the Site Editor' ) ) );

		$saved = array();
		foreach ( array(
			'coming-soon' => 'wp_template',
			'front-page'  => 'wp_template',
			'header'      => 'wp_template_part',
		) as $slug => $type ) {
			$saved[ $slug ] = self::factory()->post->create(
				array(
					'post_type'    => $type,
					'post_name'    => $slug,
					'post_status'  => 'publish',
					'post_content' => '<!-- wp:paragraph --><p>Saved in the Site Editor</p><!-- /wp:paragraph -->',
				)
			);
			wp_set_post_terms( $saved[ $slug ], get_stylesheet(), 'wp_theme' );
		}
		wp_set_post_terms( $saved['header'], 'header', 'wp_template_part_area' );

		$this->runner()->run( false );
		$this->assertNotEmpty( array_filter( $this->lines, static fn( string $line ): bool => str_contains( $line, 'warning: the coming-soon template was saved in the Site Editor (post ' . $saved['coming-soon'] . ')' ) ) );
		$this->assertNotEmpty( array_filter( $this->lines, static fn( string $line ): bool => str_contains( $line, 'warning: the front-page template was saved in the Site Editor (post ' . $saved['front-page'] . ')' ) ) );
		$this->assertNotEmpty( array_filter( $this->lines, static fn( string $line ): bool => str_contains( $line, 'warning: the header template part was saved in the Site Editor (post ' . $saved['header'] . ')' ) ) );
		$this->assertNotEmpty( array_filter( $this->lines, static fn( string $line ): bool => str_contains( $line, 'ok: footer comes from the theme' ) ) );
		$this->assertNotEmpty( array_filter( $this->lines, static fn( string $line ): bool => str_contains( $line, 'ok: checkout-header comes from the theme' ) ) );
		foreach ( $saved as $post ) {
			$this->assertSame( 'publish', get_post_status( $post ) );
		}
	}

	/**
	 * Step 25 (CNT-01, ORD-03): the logo and the site icon come from the theme's brand files into the
	 * media library once, and the email logo is the theme's PNG.
	 */
	public function test_brand_assets_are_imported_once(): void {
		$this->runner()->run( false );

		$logo = (int) get_option( 'site_logo' );
		$icon = (int) get_option( 'site_icon' );
		$this->assertGreaterThan( 0, $logo );
		$this->assertGreaterThan( 0, $icon );
		$this->assertSame( 'image/webp', get_post_mime_type( $logo ) );
		$this->assertSame( 'Cigarholic', get_post_meta( $logo, '_wp_attachment_image_alt', true ) );
		$this->assertSame( 'image/png', get_post_mime_type( $icon ) );
		$icon_meta = wp_get_attachment_metadata( $icon );
		$this->assertSame( 512, $icon_meta['width'] );
		$this->assertSame( 512, $icon_meta['height'] );
		$this->assertStringEndsWith( '.png', (string) get_site_icon_url( 180 ), 'iOS needs a PNG apple-touch-icon' );
		// The email logo is an upload (its URL survives a code rollback) and stays PNG for Outlook.
		$email = attachment_url_to_postid( (string) get_option( 'woocommerce_email_header_image' ) );
		$this->assertGreaterThan( 0, $email );
		$this->assertSame( 'image/png', get_post_mime_type( $email ) );
		$this->assertMatchesRegularExpression( '#/uploads/.+cigarholic-email-logo[^/]*\.png$#', (string) get_option( 'woocommerce_email_header_image' ) );
		$this->assertSame( '200', get_option( 'woocommerce_email_header_image_width' ) );

		// A second real run imports nothing and keeps the same attachments.
		$this->runner()->run( false );
		$this->assertSame( $logo, (int) get_option( 'site_logo' ) );
		$this->assertSame( $icon, (int) get_option( 'site_icon' ) );
		$this->assertCount(
			1,
			get_posts(
				array(
					'post_type'   => 'attachment',
					'post_status' => 'inherit',
					'title'       => 'Cigarholic logo',
					'fields'      => 'ids',
				)
			)
		);
		$this->assertEmpty( array_filter( $this->lines, static fn( string $line ): bool => str_contains( $line, 'import ' ) ) );

		// A dry run with everything in place reports nothing for this step.
		$this->runner()->run( true );
		$this->assertEmpty( array_filter( $this->lines, static fn( string $line ): bool => str_contains( $line, 'cigarholic-' ) && str_contains( $line, 'import' ) ) );

		// The files are real uploads; remove them so the test leaves nothing on disk.
		wp_delete_attachment( $logo, true );
		wp_delete_attachment( $icon, true );
		wp_delete_attachment( $email, true );
	}

	/**
	 * Step 25: an imported copy whose file disappeared is imported again, and the alt text is put back;
	 * a dry run reports the import and the option it would set.
	 */
	public function test_brand_assets_are_repaired(): void {
		$this->runner()->run( false );
		$logo = (int) get_option( 'site_logo' );
		wp_delete_file( (string) get_attached_file( $logo ) );
		update_post_meta( (int) get_option( 'site_icon' ), '_wp_attachment_context', 'edited' );

		$this->runner()->run( true );
		$this->assertNotEmpty( array_filter( $this->lines, static fn( string $line ): bool => str_contains( $line, 'import cigarholic-logo.webp' ) ) );
		$this->assertNotEmpty( array_filter( $this->lines, static fn( string $line ): bool => str_contains( $line, 'option site_logo' ) ) );

		$this->runner()->run( false );
		$repaired = (int) get_option( 'site_logo' );
		$this->assertNotSame( $logo, $repaired );
		$this->assertFileExists( (string) get_attached_file( $repaired ) );
		$this->assertSame( 'site-icon', get_post_meta( (int) get_option( 'site_icon' ), '_wp_attachment_context', true ) );

		foreach ( array( $logo, $repaired, (int) get_option( 'site_icon' ), attachment_url_to_postid( (string) get_option( 'woocommerce_email_header_image' ) ) ) as $attachment ) {
			wp_delete_attachment( $attachment, true );
		}
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

	/**
	 * The installer's "Hello world!" post and its comment leave the public site; a post the manager
	 * reused under that slug stays.
	 */
	public function test_sample_post_is_trashed_only_while_untouched(): void {
		// Earlier tests leave $wp_rewrite at /%postname%/ in memory after the database rollback.
		$this->set_permalink_structure( '' );
		$existing = get_page_by_path( 'hello-world', OBJECT, 'post' );
		if ( $existing instanceof \WP_Post ) {
			wp_delete_post( $existing->ID, true );
		}
		$hello   = self::factory()->post->create(
			array(
				'post_name'   => 'hello-world',
				'post_title'  => 'Hello world!',
				'post_status' => 'publish',
			)
		);
		$comment = self::factory()->comment->create( array( 'comment_post_ID' => $hello ) );

		$this->runner()->run( true );
		$this->assertSame( 'publish', get_post_status( $hello ), 'a dry run trashed the post' );

		$this->runner()->run( false );
		$this->assertSame( 'trash', get_post_status( $hello ) );
		$this->assertSame( 'post-trashed', get_comment( $comment )->comment_approved ?? null );
		$this->assertContains( '   changed: post hello-world trashed', $this->lines );
		$this->assertSame( 0, $this->runner()->run( true ), "second run is not a no-op:\n" . implode( "\n", $this->lines ) );

		$reused = self::factory()->post->create(
			array(
				'post_name'   => 'hello-world',
				'post_title'  => 'Hello from the workshop',
				'post_status' => 'publish',
			)
		);
		$this->runner()->run( false );
		$this->assertSame( 'publish', get_post_status( $reused ) );
	}
}
