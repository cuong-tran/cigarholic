<?php
/**
 * Provisioning step: coming-soon mode (CNT-04).
 *
 * The whole site shows the theme's coming-soon template (templates/coming-soon.html: brand, one line,
 * signup form, company footer, no navigation) to visitors; administrators and shop managers see the
 * store. No private share link. Coming soon stays on until the owner says "turn coming-soon off"
 * (milestone M4): that is a change to this step, not a click in WP admin. A copy of the template saved
 * in the Site Editor would hide theme changes, so the run warns about one (CNT-01).
 *
 * Feature IDs: FND-03, CNT-04, CNT-01.
 *
 * @package Cigarholic
 */

use Cigarholic\Core\Provision\Context;

return static function ( Context $ctx ): void {
	if ( ! function_exists( 'WC' ) ) {
		$ctx->log( 'skip: WooCommerce is not active' );
		return;
	}

	$ctx->option( 'woocommerce_coming_soon', 'yes' );
	// Whole site, not only the store pages.
	$ctx->option( 'woocommerce_store_pages_only', 'no' );
	$ctx->option( 'woocommerce_private_link', 'no' );

	// A coming-soon template saved in the Site Editor (for example through WooCommerce's "Customize" link)
	// replaces the theme file, so theme changes to the page would not show. Saved work is never deleted
	// here; the run says so, and the owner resets it under Appearance > Editor > Templates > Coming soon.
	$saved = function_exists( 'get_block_template' ) ? get_block_template( get_stylesheet() . '//coming-soon', 'wp_template' ) : null;
	if ( $saved instanceof WP_Block_Template && 'custom' === $saved->source ) {
		$ctx->log( sprintf( 'warning: the coming-soon template was saved in the Site Editor (post %d); the theme file is not used until it is reset', (int) $saved->wp_id ) );
	}
};
