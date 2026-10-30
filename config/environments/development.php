<?php
/**
 * Development: the Docker Compose runtime in the sandbox, CI and on laptops.
 *
 * @package Cigarholic
 */

use Roots\WPConfig\Config;

use function Env\env;

Config::define( 'SAVEQUERIES', true );
Config::define( 'WP_DEBUG', true );
// Browser: show errors. WP-CLI: log only, because WP-CLI 2.12's own libraries emit PHP 8.5 deprecations.
Config::define( 'WP_DEBUG_DISPLAY', ! defined( 'WP_CLI' ) );
Config::define( 'WP_DEBUG_LOG', env( 'WP_DEBUG_LOG' ) ?? true );
Config::define( 'WP_DISABLE_FATAL_ERROR_HANDLER', true );
Config::define( 'SCRIPT_DEBUG', true );
Config::define( 'DISALLOW_INDEXING', true );

ini_set( 'display_errors', '1' ); // phpcs:ignore WordPress.PHP.IniSet.display_errors_Disallowed
