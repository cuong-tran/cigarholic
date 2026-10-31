<?php
/**
 * Provisioning step: WooCommerce URL structure (SEO-03, ADR 0002).
 *
 * Products at /products/<slug>/, categories at /collections/<slug>/ (flat), tags at
 * /product-tag/<slug>/, attributes with no prefix. Pages and journal posts keep /%postname%/ from
 * 00-core; the posts page /journal/ is set in 20-pages.
 *
 * Feature IDs: FND-03, SEO-03.
 *
 * @package Cigarholic
 */

use Cigarholic\Core\Provision\Context;

return static function ( Context $ctx ): void {
	if ( ! function_exists( 'WC' ) ) {
		$ctx->log( 'skip: WooCommerce is not active' );
		return;
	}

	// Same key order and value types as WooCommerce's own settings screen stores, so that
	// wc_get_permalink_structure() does not rewrite the option on every request.
	$wanted = array(
		'product_base'           => '/products',
		'category_base'          => 'collections',
		'tag_base'               => 'product-tag',
		'attribute_base'         => '',
		'use_verbose_page_rules' => false,
	);

	if ( $ctx->option( 'woocommerce_permalinks', $wanted ) && ! $ctx->is_dry_run() ) {
		// The product post type and taxonomies are already registered with the old bases in this
		// process, so flushing now would persist them. WooCommerce flushes a queued request on the
		// next load, right after it registers them with the new bases (WC_Post_Types::maybe_flush_rewrite_rules).
		update_option( 'woocommerce_queue_flush_rewrite_rules', 'yes' );
		$ctx->log( 'rewrite rules flush queued for the next request' );
	}
};
