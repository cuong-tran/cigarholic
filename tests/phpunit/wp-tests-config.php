<?php
/**
 * WordPress test-suite configuration for the Compose runtime (FND-12).
 *
 * Values default to docker-compose.yml; override with WP_TESTS_* environment variables.
 *
 * @package Cigarholic
 */

$cigarholic_root = dirname( __DIR__, 2 );

/**
 * Environment variable with a default.
 *
 * @param string $name     Variable name.
 * @param string $fallback Value when unset or empty.
 * @return string
 */
function cigarholic_test_env( string $name, string $fallback ): string {
	$value = getenv( $name );
	return ( is_string( $value ) && '' !== $value ) ? $value : $fallback;
}

define( 'ABSPATH', $cigarholic_root . '/web/wp/' );
define( 'WP_CONTENT_DIR', $cigarholic_root . '/web/app' );
define( 'WP_CONTENT_URL', 'http://localhost:8080/app' );
define( 'WP_DEFAULT_THEME', 'cigarholic' );

define( 'DB_NAME', cigarholic_test_env( 'WP_TESTS_DB_NAME', 'cigarholic_test' ) );
define( 'DB_USER', cigarholic_test_env( 'WP_TESTS_DB_USER', 'cigarholic' ) );
define( 'DB_PASSWORD', cigarholic_test_env( 'WP_TESTS_DB_PASSWORD', 'cigarholic' ) );
define( 'DB_HOST', cigarholic_test_env( 'WP_TESTS_DB_HOST', 'db' ) );
define( 'DB_CHARSET', 'utf8mb4' );
define( 'DB_COLLATE', '' );

$table_prefix = 'wptests_'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

define( 'WP_TESTS_DOMAIN', 'localhost' );
define( 'WP_TESTS_EMAIL', 'tests@cigarholic.test' );
define( 'WP_TESTS_TITLE', 'Cigarholic test' );
define( 'WP_PHP_BINARY', 'php' );
define( 'WP_DEBUG', true );
define( 'WP_ENVIRONMENT_TYPE', 'development' );
define( 'WPLANG', '' );
// Tests run without the Valkey object cache (the drop-in may exist in web/app).
define( 'WP_REDIS_DISABLED', true );

// What the mu-plugin reads from .env in a real request.
define( 'CIGARHOLIC_FEATURES', cigarholic_test_env( 'CIGARHOLIC_FEATURES', '' ) );
define( 'CIGARHOLIC_TURNSTILE_SITE_KEY', '1x00000000000000000000AA' );
define( 'CIGARHOLIC_TURNSTILE_SECRET_KEY', '1x0000000000000000000000000000000AA' );
