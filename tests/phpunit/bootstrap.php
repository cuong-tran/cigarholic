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

// WooCommerce is not an active plugin in the test install, yet the provisioning steps (FND-03), the
// roles (ORD-04) and every commerce feature depend on it: load it like an mu-plugin and run its
// installer once (tables, roles, options, pages) before the first test, as WooCommerce's own test
// suite does. The mu-plugin itself loads from WP_CONTENT_DIR/mu-plugins through wp-settings.php.
tests_add_filter(
	'muplugins_loaded',
	static function (): void {
		require_once WP_PLUGIN_DIR . '/woocommerce/woocommerce.php';
	}
);
tests_add_filter(
	'setup_theme',
	static function (): void {
		WC_Install::install();
		// Roles were created in the database after WP_Roles loaded them: reload (core ticket 28374).
		$GLOBALS['wp_roles'] = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		wp_roles();
	}
);

require $cigarholic_wp_phpunit . '/includes/bootstrap.php';
