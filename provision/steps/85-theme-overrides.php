<?php
/**
 * Provisioning step: report theme files hidden by copies saved in the Site Editor (CNT-01).
 *
 * A template or template part saved in the Site Editor (for example the coming-soon page through
 * WooCommerce's "Customize" link, or the header after a menu tweak) replaces the theme file, so theme
 * changes to it would not show. Saved work is never deleted here; the run names it, and the owner
 * resets it under Appearance > Editor (Templates or Patterns > Template parts > the item > Reset).
 *
 * Feature IDs: FND-03, CNT-01, CNT-04, NAV-01, NAV-03.
 *
 * @package Cigarholic
 */

use Cigarholic\Core\Provision\Context;

return static function ( Context $ctx ): void {
	if ( ! function_exists( 'get_block_template' ) ) {
		$ctx->log( 'skip: block templates are not available' );
		return;
	}
	$watched = array(
		'coming-soon' => 'wp_template',
		'header'      => 'wp_template_part',
		'footer'      => 'wp_template_part',
	);
	foreach ( $watched as $slug => $type ) {
		$saved = get_block_template( get_stylesheet() . '//' . $slug, $type );
		if ( $saved instanceof WP_Block_Template && 'custom' === $saved->source ) {
			$ctx->log( sprintf( 'warning: the %s %s was saved in the Site Editor (post %d); the theme file is not used until it is reset', $slug, 'wp_template' === $type ? 'template' : 'template part', (int) $saved->wp_id ) );
		} else {
			$ctx->log( sprintf( 'ok: %s comes from the theme', $slug ) );
		}
	}
};
