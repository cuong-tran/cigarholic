<?php
/**
 * Tests for the Shop Manager role provisioning.
 *
 * @package Cigarholic
 */

namespace Cigarholic\Tests;

use Cigarholic\Core\Plugin;
use Cigarholic\Core\Provision\Runner;
use WP_UnitTestCase;

/**
 * Feature IDs: ORD-04, FND-03.
 */
final class RolesTest extends WP_UnitTestCase {

	/**
	 * Capabilities the manager must not have: plugins, themes, code, site configuration.
	 *
	 * @var list<string>
	 */
	private const FORBIDDEN = array(
		'install_plugins',
		'activate_plugins',
		'update_plugins',
		'edit_plugins',
		'edit_themes',
		'switch_themes',
		'edit_theme_options',
		'update_core',
		'edit_files',
		'manage_options',
	);

	/**
	 * Capabilities the manager needs: store, catalog, coupons, reports, content.
	 *
	 * @var list<string>
	 */
	private const REQUIRED = array(
		'manage_woocommerce',
		'view_woocommerce_reports',
		'edit_products',
		'publish_products',
		'delete_products',
		'manage_product_terms',
		'edit_shop_orders',
		'edit_others_shop_orders',
		'edit_shop_coupons',
		'publish_shop_coupons',
		'edit_pages',
		'edit_others_pages',
		'publish_pages',
		'edit_posts',
		'publish_posts',
		'upload_files',
		'import',
		'export',
	);

	/**
	 * Runs the roles step before each test (WooCommerce gives shop_manager edit_theme_options by default).
	 */
	public function set_up(): void {
		parent::set_up();
		$runner = new Runner(
			Plugin::instance()->provision_dir(),
			static function (): void {}
		);
		$runner->run( false, '40-roles' );
	}

	/**
	 * The Shop Manager can run the store and edit content but cannot touch plugins, themes or code.
	 */
	public function test_shop_manager_capabilities(): void {
		$role = get_role( 'shop_manager' );
		$this->assertNotNull( $role, 'WooCommerce creates the shop_manager role on install' );

		foreach ( self::REQUIRED as $cap ) {
			$this->assertTrue( $role->has_cap( $cap ), sprintf( 'shop_manager must have %s', $cap ) );
		}
		foreach ( self::FORBIDDEN as $cap ) {
			$this->assertFalse( $role->has_cap( $cap ), sprintf( 'shop_manager must not have %s', $cap ) );
		}

		// A user with the role, through WordPress's capability resolution.
		$user = self::factory()->user->create_and_get( array( 'role' => 'shop_manager' ) );
		$this->assertTrue( user_can( $user, 'manage_woocommerce' ) );
		$this->assertTrue( user_can( $user, 'edit_products' ) );
		$this->assertFalse( user_can( $user, 'activate_plugins' ) );
		$this->assertFalse( user_can( $user, 'edit_theme_options' ) );
		$this->assertFalse( user_can( $user, 'update_core' ) );
	}

	/**
	 * Administrators keep everything, including every WooCommerce capability.
	 */
	public function test_administrator_capabilities(): void {
		$role = get_role( 'administrator' );
		$this->assertNotNull( $role );

		foreach ( array_merge( self::REQUIRED, self::FORBIDDEN ) as $cap ) {
			$this->assertTrue( $role->has_cap( $cap ), sprintf( 'administrator must have %s', $cap ) );
		}
		foreach ( \WC_Install::get_core_capabilities() as $group ) {
			foreach ( $group as $cap ) {
				$this->assertTrue( $role->has_cap( $cap ), sprintf( 'administrator must have %s', $cap ) );
			}
		}
	}

	/**
	 * Running the step again changes nothing.
	 */
	public function test_step_is_idempotent(): void {
		$lines  = array();
		$runner = new Runner(
			Plugin::instance()->provision_dir(),
			static function ( string $line ) use ( &$lines ): void {
				$lines[] = $line;
			}
		);
		$this->assertSame( 0, $runner->run( true, '40-roles' ), implode( "\n", $lines ) );
	}
}
