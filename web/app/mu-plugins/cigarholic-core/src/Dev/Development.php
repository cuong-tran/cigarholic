<?php
/**
 * Development environment adjustments.
 *
 * @package Cigarholic
 */

namespace Cigarholic\Core\Dev;

use Cigarholic\Core\Feature;

/**
 * Makes the Compose runtime behave: a valid sender for wp_mail() when the host is "localhost" (the
 * default wordpress@localhost is rejected by PHPMailer), and WordPress's HTTP client trusting the
 * system CA store (the sandbox's TLS-intercepting gateway is trusted there; WordPress ships its own
 * bundle). Does nothing outside development/local.
 *
 * Feature ID: FND-02.
 */
final class Development implements Feature {

	/**
	 * Feature ID.
	 *
	 * @return string
	 */
	public static function id(): string {
		return 'FND-02';
	}

	/**
	 * Hooks, only in development or local.
	 *
	 * @return void
	 */
	public function register(): void {
		if ( ! in_array( wp_get_environment_type(), array( 'development', 'local' ), true ) ) {
			return;
		}
		add_filter( 'wp_mail_from', array( $this, 'mail_from' ) );
		add_filter( 'https_ssl_verify', array( $this, 'verify_with_system_ca' ) );
	}

	/**
	 * Replaces the invalid default sender wordpress@localhost with the admin email.
	 *
	 * @param string $from Sender address.
	 * @return string
	 */
	public function mail_from( string $from ): string {
		if ( str_ends_with( $from, '@localhost' ) || ! is_email( $from ) ) {
			$admin = (string) get_option( 'admin_email' );
			return is_email( $admin ) ? $admin : 'dev@cigarholic.test';
		}
		return $from;
	}

	/**
	 * Verifies HTTPS against the system CA store instead of WordPress's bundled one, so requests
	 * through the sandbox gateway (Turnstile, wordpress.org) pass. Verification stays on.
	 *
	 * @param bool|string $verify Current setting: true (bundled CA), false, or a CA file path.
	 * @return bool|string
	 */
	public function verify_with_system_ca( bool|string $verify ): bool|string {
		$bundle = '/etc/ssl/certs/ca-certificates.crt';
		if ( false !== $verify && is_readable( $bundle ) ) {
			return $bundle;
		}
		return $verify;
	}
}
