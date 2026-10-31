<?php
/**
 * Cloudflare Turnstile.
 *
 * @package Cigarholic
 */

namespace Cigarholic\Core\Security;

use Cigarholic\Core\Logger;

/**
 * Renders the Turnstile widget and verifies tokens server-side. Keys come from .env
 * (CIGARHOLIC_TURNSTILE_SITE_KEY / _SECRET_KEY); the sandbox and CI use Cloudflare's public
 * always-pass test keys. Shared by the signup form (CNT-04) and the contact form (CNT-03).
 *
 * Feature IDs: CNT-04, CNT-03.
 */
final class Turnstile {

	public const SCRIPT_HANDLE = 'cigarholic-turnstile';
	public const FIELD         = 'cf-turnstile-response';

	/**
	 * Site key, or empty when not configured.
	 *
	 * @return string
	 */
	public static function site_key(): string {
		return defined( 'CIGARHOLIC_TURNSTILE_SITE_KEY' ) ? (string) CIGARHOLIC_TURNSTILE_SITE_KEY : '';
	}

	/**
	 * Secret key, or empty when not configured.
	 *
	 * @return string
	 */
	private static function secret_key(): string {
		return defined( 'CIGARHOLIC_TURNSTILE_SECRET_KEY' ) ? (string) CIGARHOLIC_TURNSTILE_SECRET_KEY : '';
	}

	/**
	 * Whether both keys are configured.
	 *
	 * @return bool
	 */
	public static function is_configured(): bool {
		return '' !== self::site_key() && '' !== self::secret_key();
	}

	/**
	 * Registers the widget script (loaded only where a widget is rendered).
	 *
	 * @return void
	 */
	public static function register_script(): void {
		wp_register_script(
			self::SCRIPT_HANDLE,
			'https://challenges.cloudflare.com/turnstile/v0/api.js',
			array(),
			null, // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- Cloudflare serves one evergreen version.
			array(
				'strategy'  => 'defer',
				'in_footer' => true,
			)
		);
	}

	/**
	 * Widget markup; enqueues the script. Empty string when not configured.
	 *
	 * @param string $action Action name reported to Cloudflare (letters, digits, underscore, hyphen).
	 * @return string
	 */
	public static function widget( string $action ): string {
		if ( ! self::is_configured() ) {
			return '';
		}
		wp_enqueue_script( self::SCRIPT_HANDLE );
		return sprintf(
			'<div class="cf-turnstile" data-sitekey="%s" data-action="%s" data-size="flexible" data-appearance="always"></div>',
			esc_attr( self::site_key() ),
			esc_attr( preg_replace( '/[^a-zA-Z0-9_-]/', '', $action ) ?? '' )
		);
	}

	/**
	 * Verifies a token. When Turnstile is not configured the check passes, so a misconfigured
	 * environment degrades to the honeypot and rate limit instead of blocking every visitor.
	 *
	 * @param string $token  Token from the widget.
	 * @param string $ip     Visitor IP for Cloudflare's check (not stored).
	 * @param string $action Expected action name.
	 * @return bool
	 */
	public static function verify( string $token, string $ip, string $action ): bool {
		/**
		 * Short-circuits Turnstile verification (tests, or an outage drill). Return true or false
		 * to decide, null to verify with Cloudflare.
		 *
		 * @param bool|null $verified Decision, or null.
		 * @param string    $token    Token from the widget.
		 * @param string    $action   Expected action name.
		 */
		$pre = apply_filters( 'cigarholic_turnstile_verified', null, $token, $action );
		if ( is_bool( $pre ) ) {
			return $pre;
		}
		if ( ! self::is_configured() ) {
			return true;
		}
		if ( '' === $token ) {
			return false;
		}
		$response = wp_remote_post(
			'https://challenges.cloudflare.com/turnstile/v0/siteverify',
			array(
				'timeout' => 10,
				'body'    => array(
					'secret'   => self::secret_key(),
					'response' => $token,
					'remoteip' => $ip,
				),
			)
		);
		if ( is_wp_error( $response ) ) {
			Logger::warning( 'Turnstile verification request failed', array( 'error' => $response->get_error_message() ) );
			return false;
		}
		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) || empty( $body['success'] ) ) {
			return false;
		}
		// Cloudflare's test keys report no action; a real key must echo the one we rendered.
		if ( ! empty( $body['action'] ) && $body['action'] !== $action ) {
			return false;
		}
		return true;
	}
}
