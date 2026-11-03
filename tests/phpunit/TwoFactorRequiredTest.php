<?php
/**
 * Tests for two-factor enforcement.
 *
 * @package Cigarholic
 */

namespace Cigarholic\Tests;

use Cigarholic\Core\Security\TwoFactorRequired;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * Feature IDs: OPS-07.
 */
final class TwoFactorRequiredTest extends WP_UnitTestCase {

	/**
	 * Feature instance.
	 *
	 * @var TwoFactorRequired
	 */
	private TwoFactorRequired $feature;

	/**
	 * Fresh feature instance per test.
	 */
	public function set_up(): void {
		parent::set_up();
		$this->assertTrue( TwoFactorRequired::available(), 'the Two Factor plugin is loaded by tests/phpunit/bootstrap.php' );
		$this->feature = new TwoFactorRequired();
	}

	/**
	 * Moves an account's grace period into the past.
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	private function expire_grace( int $user_id ): void {
		update_user_meta( $user_id, TwoFactorRequired::GRACE_META, time() - TwoFactorRequired::GRACE_SECONDS - 60 );
	}

	/**
	 * Gives an account a working authenticator app.
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	private function enrol( int $user_id ): void {
		update_user_meta( $user_id, \Two_Factor_Totp::SECRET_META_KEY, \Two_Factor_Totp::generate_key() );
		update_user_meta( $user_id, \Two_Factor_Core::ENABLED_PROVIDERS_USER_META_KEY, array( 'Two_Factor_Totp' ) );
	}

	/**
	 * Customers, subscribers and editors are never affected.
	 */
	public function test_accounts_that_cannot_manage_the_store_are_exempt(): void {
		foreach ( array( 'customer', 'subscriber', 'editor' ) as $role ) {
			$user = self::factory()->user->create( array( 'role' => $role ) );
			$this->expire_grace( $user );
			$this->assertSame( 'exempt', TwoFactorRequired::state( get_userdata( $user ) ), $role );
		}
		$editor = self::factory()->user->create( array( 'role' => 'editor' ) );
		$this->expire_grace( $editor );
		$this->assertTrue( user_can( $editor, 'edit_others_posts' ) );
	}

	/**
	 * Administrators and shop managers keep full access during the grace period, which starts at
	 * the first login, and keep only their profile after it.
	 */
	public function test_grace_then_restricted(): void {
		$roles = array(
			'administrator' => 'manage_options',
			'shop_manager'  => 'manage_woocommerce',
		);
		foreach ( $roles as $role => $cap ) {
			$user = self::factory()->user->create( array( 'role' => $role ) );
			$this->assertSame( 'grace', TwoFactorRequired::state( get_userdata( $user ) ), $role );
			$this->assertNull( TwoFactorRequired::deadline( get_userdata( $user ) ) );

			do_action( 'wp_login', 'login', get_userdata( $user ) );
			$deadline = TwoFactorRequired::deadline( get_userdata( $user ) );
			$this->assertNotNull( $deadline );
			$this->assertEqualsWithDelta( time() + TwoFactorRequired::GRACE_SECONDS, $deadline, 5 );
			$this->assertTrue( user_can( $user, $cap ), "$role keeps $cap during the grace period" );

			do_action( 'wp_login', 'login', get_userdata( $user ) );
			$this->assertSame( $deadline, TwoFactorRequired::deadline( get_userdata( $user ) ), 'a second login does not extend the grace period' );

			$this->expire_grace( $user );
			$this->assertSame( 'restricted', TwoFactorRequired::state( get_userdata( $user ) ), $role );
			$this->assertFalse( user_can( $user, $cap ), $role );
			$this->assertFalse( user_can( $user, 'edit_posts' ), $role );
			$this->assertFalse( user_can( $user, 'edit_shop_orders' ), $role );
			$this->assertTrue( user_can( $user, 'read' ), $role );
			$this->assertTrue( user_can( $user, 'edit_user', $user ), "$role can still edit its own profile" );
			$this->assertFalse( user_can( $user, 'edit_user', self::factory()->user->create() ), $role );
			$this->assertSame( array( $role ), get_userdata( $user )->roles, 'the role is never changed' );
		}
	}

	/**
	 * Enrolling restores everything, whenever it happens.
	 */
	public function test_enrolled_accounts_keep_their_capabilities(): void {
		$user = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->expire_grace( $user );
		$this->assertFalse( user_can( $user, 'manage_options' ) );

		$this->enrol( $user );
		$this->assertSame( 'enrolled', TwoFactorRequired::state( get_userdata( $user ) ) );
		$this->assertTrue( user_can( $user, 'manage_options' ) );
		$this->assertTrue( user_can( $user, 'manage_woocommerce' ) );
	}

	/**
	 * The REST API refuses store settings to a restricted account and still lets it set up TOTP.
	 */
	public function test_rest_follows_the_capabilities(): void {
		$user = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user );
		$this->assertSame( 200, rest_do_request( new WP_REST_Request( 'GET', '/wp/v2/settings' ) )->get_status() );

		$this->expire_grace( $user );
		wp_set_current_user( $user );
		$this->assertContains( rest_do_request( new WP_REST_Request( 'GET', '/wp/v2/settings' ) )->get_status(), array( 401, 403 ) );

		// The permission check of the plugin's TOTP and backup-code routes.
		$this->assertTrue( \Two_Factor_Core::rest_api_can_edit_user_and_update_two_factor_options( $user ), 'the authenticator setup stays reachable' );
	}

	/**
	 * A restricted account is sent from any admin page to the two-factor part of its profile.
	 */
	public function test_restricted_admin_pages_redirect_to_the_profile(): void {
		global $pagenow;
		$user = self::factory()->user->create( array( 'role' => 'shop_manager' ) );
		$this->expire_grace( $user );
		wp_set_current_user( $user );
		add_filter(
			'wp_redirect',
			static function ( string $location ): string {
				throw new \RuntimeException( $location ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- test signal.
			}
		);

		$registered = \Cigarholic\Core\Plugin::instance()->features()->get( 'two-factor' );
		$this->assertInstanceOf( TwoFactorRequired::class, $registered );
		$this->assertSame( 1, has_action( 'admin_init', array( $registered, 'guard_admin' ) ) );
		$this->assertSame( 1, has_action( 'admin_page_access_denied', array( $registered, 'guard_admin' ) ), 'menu.php refuses pages before admin_init' );

		$pagenow = 'profile.php'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- the admin page under test.
		$this->feature->guard_admin();

		$pagenow = 'edit.php'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- the admin page under test.
		try {
			$this->feature->guard_admin();
			$this->fail( 'no redirect from edit.php' );
		} catch ( \RuntimeException $redirect ) {
			$this->assertSame( TwoFactorRequired::profile_url(), $redirect->getMessage() );
		}
	}

	/**
	 * WP admin names the deadline during the grace period and explains the restriction after it.
	 */
	public function test_notices(): void {
		$user = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user );
		TwoFactorRequired::start_grace( get_userdata( $user ) );

		ob_start();
		$this->feature->notice();
		$html = (string) ob_get_clean();
		$this->assertStringContainsString( 'notice-warning', $html );
		$this->assertStringContainsString( 'profile.php#two-factor-options', $html );

		$this->expire_grace( $user );
		ob_start();
		$this->feature->notice();
		$this->assertStringContainsString( 'notice-error', (string) ob_get_clean() );

		$this->enrol( $user );
		ob_start();
		$this->feature->notice();
		$this->assertSame( '', (string) ob_get_clean() );
	}
}
