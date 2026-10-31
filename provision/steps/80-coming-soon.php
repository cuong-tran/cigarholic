<?php
/**
 * Provisioning step: coming-soon mode (CNT-04).
 *
 * The whole site shows the theme's coming-soon template (templates/coming-soon.html: brand, one line,
 * signup form, company footer, no navigation) to visitors; administrators and shop managers see the
 * store. No private share link. Coming soon stays on until the owner says "turn coming-soon off"
 * (milestone M4): that is a change to this step, not a click in WP admin.
 *
 * Feature IDs: FND-03, CNT-04.
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
};
