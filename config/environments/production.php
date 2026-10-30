<?php
/**
 * Production: cigarholic.com. Everything is in config/application.php; only hard production rules here.
 */

use Roots\WPConfig\Config;

Config::define( 'WP_DEBUG', false );
Config::define( 'FORCE_SSL_ADMIN', true );
