<?php
/**
 * Tests for the hardening feature.
 *
 * @package Cigarholic
 */

namespace Cigarholic\Tests;

use WP_UnitTestCase;

/**
 * Feature IDs: OPS-07.
 */
final class HardeningTest extends WP_UnitTestCase {

	/**
	 * XML-RPC is disabled and the generator tag is empty.
	 */
	public function test_xmlrpc_and_generator_are_off(): void {
		$this->assertFalse( apply_filters( 'xmlrpc_enabled', true ) );
		$this->assertSame( array(), apply_filters( 'xmlrpc_methods', array( 'demo.sayHello' => 'x' ) ) );
		$this->assertFalse( has_action( 'wp_head', 'wp_generator' ) );
		$this->assertSame( '', apply_filters( 'the_generator', get_the_generator( 'html' ), 'html' ) );
	}

	/**
	 * Login errors never reveal which part was wrong.
	 */
	public function test_login_error_is_generic(): void {
		$message = apply_filters( 'login_errors', '<strong>Error:</strong> The password you entered for the username admin is incorrect.' );
		$this->assertStringNotContainsString( 'admin', $message );
	}

	/**
	 * Anonymous REST clients cannot list users; logged-in users can.
	 */
	public function test_users_endpoint_hidden_from_anonymous(): void {
		wp_set_current_user( 0 );
		$server = rest_get_server();
		$this->assertArrayNotHasKey( '/wp/v2/users', $server->get_routes() );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->assertArrayHasKey( '/wp/v2/users', rest_get_server()->get_routes() );
	}
}
