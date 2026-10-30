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

// The mu-plugin loads from WP_CONTENT_DIR/mu-plugins through wp-settings.php; nothing to add here.
require $cigarholic_wp_phpunit . '/includes/bootstrap.php';
