<?php
/**
 * Plugin Name: Cigarholic Core
 * Plugin URI: https://github.com/cuong-tran/cigarholic
 * Description: All custom functionality of the Cigarholic store, one class per feature ID from docs/FEATURES.md.
 * Version: 0.1.0
 * Requires at least: 7.0
 * Requires PHP: 8.3
 * Author: HuyLinh LLC
 * Text Domain: cigarholic
 *
 * WordPress only loads top-level files in mu-plugins/, so this file boots the plugin that lives in
 * cigarholic-core/. Its classes are autoloaded by Composer (composer.json maps Cigarholic\Core\ to
 * cigarholic-core/src/). Feature ID: FND-05.
 *
 * @package Cigarholic
 */

if ( ! class_exists( \Cigarholic\Core\Plugin::class ) ) {
	add_action(
		'admin_notices',
		static function (): void {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Cigarholic Core is not loaded: run composer install.', 'cigarholic' ) . '</p></div>';
		}
	);
	return;
}

\Cigarholic\Core\Plugin::boot( __FILE__, __DIR__ . '/cigarholic-core' );
