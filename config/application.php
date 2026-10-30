<?php
/**
 * Base configuration for every environment. Environment-specific overrides live in
 * config/environments/{WP_ENV}.php. Deviate from this file as little as possible.
 *
 * Based on Roots Bedrock 1.31.
 */

use Roots\WPConfig\Config;

use function Env\env;

Env\Env::$options |= Env\Env::USE_ENV_ARRAY;

/**
 * Directory containing all of the site's files.
 *
 * @var string
 */
$root_dir = dirname( __DIR__ );

/**
 * Document root.
 *
 * @var string
 */
$webroot_dir = $root_dir . '/web';

/**
 * Load .env from the project root; .env.local overrides it when present.
 */
if ( file_exists( $root_dir . '/.env' ) ) {
	$env_files = file_exists( $root_dir . '/.env.local' )
		? array( '.env', '.env.local' )
		: array( '.env' );

	$dotenv = Dotenv\Dotenv::createImmutable( $root_dir, $env_files, false );
	$dotenv->load();

	$dotenv->required( array( 'WP_HOME', 'WP_SITEURL' ) );
	if ( ! env( 'DATABASE_URL' ) ) {
		$dotenv->required( array( 'DB_NAME', 'DB_USER', 'DB_PASSWORD' ) );
	}
}

/**
 * Environment: production (default), staging or development.
 */
define( 'WP_ENV', env( 'WP_ENV' ) ?: 'production' );

if ( ! env( 'WP_ENVIRONMENT_TYPE' ) && in_array( WP_ENV, array( 'production', 'staging', 'development', 'local' ), true ) ) {
	Config::define( 'WP_ENVIRONMENT_TYPE', WP_ENV );
}

/**
 * URLs.
 */
Config::define( 'WP_HOME', env( 'WP_HOME' ) );
Config::define( 'WP_SITEURL', env( 'WP_SITEURL' ) );

/**
 * Content directory: web/app instead of wp-content.
 */
Config::define( 'CONTENT_DIR', '/app' );
Config::define( 'WP_CONTENT_DIR', $webroot_dir . Config::get( 'CONTENT_DIR' ) );
Config::define( 'WP_CONTENT_URL', Config::get( 'WP_HOME' ) . Config::get( 'CONTENT_DIR' ) );

/**
 * Database.
 */
if ( env( 'DB_SSL' ) ) {
	Config::define( 'MYSQL_CLIENT_FLAGS', MYSQLI_CLIENT_SSL );
}

Config::define( 'DB_NAME', env( 'DB_NAME' ) );
Config::define( 'DB_USER', env( 'DB_USER' ) );
Config::define( 'DB_PASSWORD', env( 'DB_PASSWORD' ) );
Config::define( 'DB_HOST', env( 'DB_HOST' ) ?: 'localhost' );
Config::define( 'DB_CHARSET', 'utf8mb4' );
Config::define( 'DB_COLLATE', '' );
$table_prefix = env( 'DB_PREFIX' ) ?: 'wp_'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

if ( env( 'DATABASE_URL' ) ) {
	$dsn = (object) parse_url( env( 'DATABASE_URL' ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url

	Config::define( 'DB_NAME', substr( $dsn->path, 1 ) );
	Config::define( 'DB_USER', $dsn->user );
	Config::define( 'DB_PASSWORD', $dsn->pass ?? null );
	Config::define( 'DB_HOST', isset( $dsn->port ) ? "{$dsn->host}:{$dsn->port}" : $dsn->host );
}

/**
 * Authentication unique keys and salts. Generate: https://roots.io/salts.html
 */
Config::define( 'AUTH_KEY', env( 'AUTH_KEY' ) );
Config::define( 'SECURE_AUTH_KEY', env( 'SECURE_AUTH_KEY' ) );
Config::define( 'LOGGED_IN_KEY', env( 'LOGGED_IN_KEY' ) );
Config::define( 'NONCE_KEY', env( 'NONCE_KEY' ) );
Config::define( 'AUTH_SALT', env( 'AUTH_SALT' ) );
Config::define( 'SECURE_AUTH_SALT', env( 'SECURE_AUTH_SALT' ) );
Config::define( 'LOGGED_IN_SALT', env( 'LOGGED_IN_SALT' ) );
Config::define( 'NONCE_SALT', env( 'NONCE_SALT' ) );

/**
 * Custom settings.
 */
// Core, plugins and the theme are managed by Composer: no updates or installs from the admin (OPS-08).
Config::define( 'AUTOMATIC_UPDATER_DISABLED', true );
Config::define( 'DISALLOW_FILE_EDIT', true );
Config::define( 'DISALLOW_FILE_MODS', true );
Config::define( 'WP_POST_REVISIONS', env( 'WP_POST_REVISIONS' ) ?? 10 );
Config::define( 'WP_DEFAULT_THEME', 'cigarholic' );
Config::define( 'WP_MEMORY_LIMIT', env( 'WP_MEMORY_LIMIT' ) ?: '256M' );
Config::define( 'WP_MAX_MEMORY_LIMIT', env( 'WP_MAX_MEMORY_LIMIT' ) ?: '512M' );

// Redis Object Cache plugin talking to Valkey (OPS-09). The prefix keeps staging and production apart.
Config::define( 'WP_REDIS_HOST', env( 'WP_REDIS_HOST' ) ?: '127.0.0.1' );
Config::define( 'WP_REDIS_PORT', (int) ( env( 'WP_REDIS_PORT' ) ?: 6379 ) );
Config::define( 'WP_REDIS_PREFIX', env( 'WP_REDIS_PREFIX' ) ?: 'cigarholic' );
Config::define( 'WP_REDIS_DISABLE_BANNERS', true );

// Cigarholic application settings read by cigarholic-core (names only; values come from .env).
Config::define( 'CIGARHOLIC_TURNSTILE_SITE_KEY', env( 'TURNSTILE_SITE_KEY' ) ?: '' );
Config::define( 'CIGARHOLIC_TURNSTILE_SECRET_KEY', env( 'TURNSTILE_SECRET_KEY' ) ?: '' );
Config::define( 'CIGARHOLIC_FEATURES', env( 'CIGARHOLIC_FEATURES' ) ?: '' );
Config::define( 'CIGARHOLIC_SMTP_HOST', env( 'SMTP_HOST' ) ?: '' );
Config::define( 'CIGARHOLIC_SMTP_PORT', (int) ( env( 'SMTP_PORT' ) ?: 25 ) );

/**
 * Debugging defaults (overridden per environment).
 */
Config::define( 'WP_DEBUG_DISPLAY', false );
Config::define( 'WP_DEBUG_LOG', false );
Config::define( 'SCRIPT_DEBUG', false );
ini_set( 'display_errors', '0' ); // phpcs:ignore WordPress.PHP.IniSet.display_errors_Disallowed

/**
 * Detect HTTPS behind a reverse proxy (Cloudflare, Nginx).
 */
if ( isset( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) && 'https' === $_SERVER['HTTP_X_FORWARDED_PROTO'] ) {
	$_SERVER['HTTPS'] = 'on';
}

$env_config = __DIR__ . '/environments/' . WP_ENV . '.php';

if ( file_exists( $env_config ) ) {
	require_once $env_config;
}

Config::apply();

/**
 * Bootstrap WordPress.
 */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', $webroot_dir . '/wp/' );
}
