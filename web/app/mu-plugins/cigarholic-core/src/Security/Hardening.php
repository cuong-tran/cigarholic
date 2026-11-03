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
 * off, Nginx rate limits): XML-RPC off, no generator tag, generic login errors, and no anonymous
 * user enumeration. A login name never reaches a visitor: not through the REST API, not through
 * `?author=N` and author archives (a store has none; they answer 404), not through the users
 * sitemap, and not as the author name of a feed item or an oEmbed card while the display name is
 * still the login (WordPress's default for every new user). The theme prints no author bylines.
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
		add_filter( 'redirect_canonical', array( $this, 'no_author_redirect' ) );
		add_action( 'template_redirect', array( $this, 'author_archives_not_found' ), 1 );
		add_filter( 'author_link', array( $this, 'author_link' ) );
		add_filter( 'wp_sitemaps_add_provider', array( $this, 'drop_users_sitemap' ), 10, 2 );
		add_filter( 'the_author', array( $this, 'public_author_name' ) );
		add_filter( 'oembed_response_data', array( $this, 'oembed_author' ), 10, 2 );
	}

	/**
	 * `?author=N` would redirect to /author/<login>/; no redirect for author queries.
	 *
	 * @param string|false $redirect_url Canonical URL WordPress would redirect to.
	 * @return string|false
	 */
	public function no_author_redirect( $redirect_url ) {
		$author_query = '' !== (string) get_query_var( 'author' ) || '' !== (string) get_query_var( 'author_name' );
		return is_author() || $author_query ? false : $redirect_url;
	}

	/**
	 * Author archives (`/author/<slug>/`, `?author=N`, `?author_name=...`) answer 404 for everyone.
	 *
	 * @return void
	 */
	public function author_archives_not_found(): void {
		if ( ! is_author() ) {
			return;
		}
		global $wp_query;
		$wp_query->set_404();
		status_header( 404 );
		nocache_headers();
	}

	/**
	 * On the front end, author links point at the home page instead of a (404) archive named after
	 * the login. WP admin keeps the real link.
	 *
	 * @param string $link Author archive URL.
	 * @return string
	 */
	public function author_link( $link ) {
		return is_admin() ? $link : home_url( '/' );
	}

	/**
	 * Removes the users provider from the core sitemaps.
	 *
	 * @param \WP_Sitemaps_Provider|mixed $provider Provider instance.
	 * @param string                      $name     Provider name.
	 * @return \WP_Sitemaps_Provider|mixed|false
	 */
	public function drop_users_sitemap( $provider, string $name ) {
		return 'users' === $name ? false : $provider;
	}

	/**
	 * Outside WP admin, the site name instead of an author display name that is still the login
	 * (feeds use it as `dc:creator`). A display name the user chose is left alone.
	 *
	 * @param string|null $display_name Display name of the current post's author.
	 * @return string|null
	 */
	public function public_author_name( $display_name ) {
		global $authordata;
		if ( ! is_admin() && $authordata instanceof \WP_User && self::is_login( (string) $display_name, $authordata ) ) {
			return get_bloginfo( 'name' );
		}
		return $display_name;
	}

	/**
	 * Embed cards (oEmbed): the same author name rule, and the home page as the author URL.
	 *
	 * @param array<string, mixed> $data oEmbed response data.
	 * @param \WP_Post             $post Embedded post.
	 * @return array<string, mixed>
	 */
	public function oembed_author( array $data, $post ): array {
		if ( isset( $data['author_url'] ) ) {
			$data['author_url'] = home_url( '/' );
		}
		$author = $post instanceof \WP_Post ? get_userdata( (int) $post->post_author ) : false;
		if ( isset( $data['author_name'] ) && $author instanceof \WP_User && self::is_login( (string) $data['author_name'], $author ) ) {
			$data['author_name'] = get_bloginfo( 'name' );
		}
		return $data;
	}

	/**
	 * Whether a public name would give away the user's login or author slug.
	 *
	 * @param string   $name Name about to be shown.
	 * @param \WP_User $user Its user.
	 * @return bool
	 */
	private static function is_login( string $name, \WP_User $user ): bool {
		$name = strtolower( trim( $name ) );
		return '' !== $name && ( strtolower( $user->user_login ) === $name || strtolower( $user->user_nicename ) === $name );
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
