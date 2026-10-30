<?php
/**
 * Admin and surface hardening.
 *
 * @package Cigarholic
 */

namespace Cigarholic\Core\Security;

use Cigarholic\Core\Feature;

/**
 * Small, safe hardening that belongs in code (the rest is configuration: 2FA plugin, file editor
 * off, Nginx rate limits): XML-RPC off, no generator tag, generic login errors, no anonymous user
 * enumeration through the REST API.
 *
 * Feature ID: OPS-07.
 */
final class Hardening implements Feature {

	/**
	 * Feature ID.
	 *
	 * @return string
	 */
	public static function id(): string {
		return 'OPS-07';
	}

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'xmlrpc_enabled', '__return_false' );
		add_filter( 'xmlrpc_methods', '__return_empty_array' );
		remove_action( 'wp_head', 'wp_generator' );
		add_filter( 'the_generator', '__return_empty_string' );
		add_filter( 'login_errors', array( $this, 'login_error' ) );
		add_filter( 'rest_endpoints', array( $this, 'hide_users_from_anonymous' ) );
		add_action( 'init', array( $this, 'remove_plugin_generators' ) );
	}

	/**
	 * Plugins that print their own generator tag (Modern Image Formats does) are silenced too.
	 *
	 * @return void
	 */
	public function remove_plugin_generators(): void {
		remove_action( 'wp_head', 'webp_uploads_render_generator' );
	}

	/**
	 * One generic login error, whatever failed.
	 *
	 * @return string
	 */
	public function login_error(): string {
		return __( 'The username, email address or password is incorrect.', 'cigarholic' );
	}

	/**
	 * Removes the users endpoints for visitors who are not logged in.
	 *
	 * @param array<string, mixed> $endpoints Registered REST endpoints.
	 * @return array<string, mixed>
	 */
	public function hide_users_from_anonymous( array $endpoints ): array {
		if ( is_user_logged_in() ) {
			return $endpoints;
		}
		unset( $endpoints['/wp/v2/users'], $endpoints['/wp/v2/users/(?P<id>[\d]+)'] );
		return $endpoints;
	}
}
