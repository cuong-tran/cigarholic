<?php
/**
 * PHPUnit bootstrap: WordPress test suite (wp-phpunit) with cigarholic-core loaded as an mu-plugin.
 *
 * @package Cigarholic
 */

$cigarholic_root = dirname( __DIR__, 2 );

require $cigarholic_root . '/vendor/autoload.php';

$cigarholic_wp_phpunit = getenv( 'WP_PHPUNIT__DIR' );
if ( ! is_string( $cigarholic_wp_phpunit ) || '' === $cigarholic_wp_phpunit ) {
	$cigarholic_wp_phpunit = $cigarholic_root . '/vendor/wp-phpunit/wp-phpunit';
}
if ( ! file_exists( $cigarholic_wp_phpunit . '/includes/functions.php' ) ) {
	echo "wp-phpunit not found; run composer install.\n";
	exit( 1 );
}

putenv( 'WP_PHPUNIT__TESTS_CONFIG=' . __DIR__ . '/wp-tests-config.php' );

require $cigarholic_wp_phpunit . '/includes/functions.php';

// WooCommerce is a regular plugin and the test install starts with no active plugins. Load it the way the
// WooCommerce test suite does and install its tables before the first test, so the catalog tests (CAT-03
// and later) run against real products. cigarholic-core ensures the catalog data model on init.
$cigarholic_woocommerce = $cigarholic_root . '/web/app/plugins/woocommerce/woocommerce.php';
if ( file_exists( $cigarholic_woocommerce ) ) {
	tests_add_filter(
		'muplugins_loaded',
		static function () use ( $cigarholic_woocommerce ): void {
			// Each test runs inside the suite's transaction; a nested WooCommerce transaction would commit it.
			if ( ! defined( 'WC_USE_TRANSACTIONS' ) ) {
				define( 'WC_USE_TRANSACTIONS', false );
			}
			require $cigarholic_woocommerce;
		}
	);
	tests_add_filter(
		'setup_theme',
		static function (): void {
			WC_Install::install();
			// Roles were read before the install added WooCommerce's capabilities (core ticket 28374).
			wp_roles()->for_site();
			// Order Fulfillments (SHP-04) registers on init only when it is on, as provisioning step 50 leaves it.
			update_option( 'woocommerce_feature_fulfillments_enabled', 'yes' );
		}
	);
	// The mailer hooks each email's trigger when it is first created. Created inside a test, those hooks are
	// removed with the test's own when the suite restores the hooks, and later tests send no email.
	tests_add_filter(
		'wp_loaded',
		static function (): void {
			WC()->mailer();
		}
	);
}

// Two Factor (OPS-07): TwoFactorRequired and the 45-two-factor step run against the real plugin.
$cigarholic_two_factor = $cigarholic_root . '/web/app/plugins/two-factor/two-factor.php';
if ( file_exists( $cigarholic_two_factor ) ) {
	tests_add_filter(
		'muplugins_loaded',
		static function () use ( $cigarholic_two_factor ): void {
			require $cigarholic_two_factor;
		}
	);
}

// SEOPress (SEO-01): the 75-seo step and its rendered robots tag run against the real plugin. SEOPress decides
// which modules to load from its `seopress_toggle` option on plugins_loaded (priority 999), so its activation
// defaults are written just before, as activating it in WP admin would.
$cigarholic_seopress = $cigarholic_root . '/web/app/plugins/wp-seopress/seopress.php';
if ( file_exists( $cigarholic_seopress ) ) {
	tests_add_filter(
		'muplugins_loaded',
		static function () use ( $cigarholic_seopress ): void {
			require $cigarholic_seopress;
		}
	);
	tests_add_filter(
		'plugins_loaded',
		static function (): void {
			( new \SEOPress\Actions\Options\Init() )->activate();
		},
		998
	);
}

// The mu-plugin loads from WP_CONTENT_DIR/mu-plugins through wp-settings.php; nothing to add here.

require $cigarholic_wp_phpunit . '/includes/bootstrap.php';
