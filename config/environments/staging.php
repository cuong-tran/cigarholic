<?php
/**
 * Staging: staging.cigarholic.com. Mirrors production, but never indexed and with error logging on.
 *
 * @package Cigarholic
 */

use Roots\WPConfig\Config;

use function Env\env;

Config::define( 'DISALLOW_INDEXING', true );
Config::define( 'WP_DEBUG', true );
Config::define( 'WP_DEBUG_DISPLAY', false );
Config::define( 'WP_DEBUG_LOG', env( 'WP_DEBUG_LOG' ) ?: dirname( __DIR__, 2 ) . '/logs/debug.log' );
Config::define( 'SCRIPT_DEBUG', false );
Config::define( 'FORCE_SSL_ADMIN', true );
