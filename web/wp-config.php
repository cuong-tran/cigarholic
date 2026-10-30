<?php
/**
 * Bedrock entry point: load Composer and the configuration, then WordPress.
 */

require_once dirname( __DIR__ ) . '/vendor/autoload.php';
require_once dirname( __DIR__ ) . '/config/application.php';
require_once ABSPATH . 'wp-settings.php';
