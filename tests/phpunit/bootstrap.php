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
		}
	);
}

// The mu-plugin loads from WP_CONTENT_DIR/mu-plugins through wp-settings.php; nothing to add here.
require $cigarholic_wp_phpunit . '/includes/bootstrap.php';
