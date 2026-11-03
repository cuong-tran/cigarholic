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

	/**
	 * `?author=N` does not redirect to /author/<login>/, and author archives answer 404.
	 */
	public function test_author_archives_do_not_reveal_logins(): void {
		$user = self::factory()->user->create( array( 'user_login' => 'secretlogin' ) );
		self::factory()->post->create( array( 'post_author' => $user ) );

		$this->go_to( home_url( '/?author=' . $user ) );
		$this->assertTrue( is_author() );
		$this->assertFalse( apply_filters( 'redirect_canonical', home_url( '/author/secretlogin/' ), home_url( '/?author=' . $user ) ) );
		do_action( 'template_redirect' );
		$this->assertTrue( is_404() );

		$this->go_to( home_url( '/?author_name=secretlogin' ) );
		do_action( 'template_redirect' );
		$this->assertTrue( is_404() );

		$this->go_to( home_url( '/' ) );
		$this->assertSame( home_url( '/' ), get_author_posts_url( $user ) );
		$this->assertSame( 'https://example.test/a/', apply_filters( 'redirect_canonical', 'https://example.test/a/', 'https://example.test/a' ) );
	}

	/**
	 * The core sitemaps have no users provider.
	 */
	public function test_users_sitemap_is_removed(): void {
		$this->assertFalse( apply_filters( 'wp_sitemaps_add_provider', new \WP_Sitemaps_Users(), 'users' ) );
		$posts = new \WP_Sitemaps_Posts();
		$this->assertSame( $posts, apply_filters( 'wp_sitemaps_add_provider', $posts, 'posts' ) );
	}

	/**
	 * Feeds and oEmbed show the site name while the display name is still the login, and the
	 * chosen display name once there is one.
	 */
	public function test_public_author_name_is_never_the_login(): void {
		$user = self::factory()->user->create(
			array(
				'user_login'   => 'secretlogin',
				'display_name' => 'secretlogin',
			)
		);
		$post = self::factory()->post->create_and_get( array( 'post_author' => $user ) );
		$this->go_to( get_permalink( $post ) );
		$GLOBALS['authordata'] = get_userdata( $user ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- what the loop sets.
		$this->assertSame( get_bloginfo( 'name' ), get_the_author() );

		$data = get_oembed_response_data( $post, 600 );
		$this->assertIsArray( $data );
		$this->assertSame( get_bloginfo( 'name' ), $data['author_name'] );
		$this->assertSame( home_url( '/' ), $data['author_url'] );

		wp_update_user(
			array(
				'ID'           => $user,
				'display_name' => 'Linh Tran',
			)
		);
		$GLOBALS['authordata'] = get_userdata( $user ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- what the loop sets.
		$this->assertSame( 'Linh Tran', get_the_author() );
		$data = get_oembed_response_data( $post, 600 );
		$this->assertIsArray( $data );
		$this->assertSame( 'Linh Tran', $data['author_name'] );
	}
}
