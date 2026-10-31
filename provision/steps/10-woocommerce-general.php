<?php
/**
 * Provisioning step: WooCommerce general settings (FND-03).
 *
 * Store address (HuyLinh LLC, Florida HQ), currency, US-only selling and shipping, units, stock
 * management and alerts (ORD-07), core product reviews (MKT-05), no telemetry or marketplace
 * nagging, Order Attribution, HPOS (brief: HPOS and block cart/checkout only) and the WooCommerce
 * pages the block cart and checkout live on (CHK-01).
 *
 * Feature IDs: FND-03, CHK-01, ORD-07, MKT-05.
 *
 * @package Cigarholic
 */

use Automattic\WooCommerce\Utilities\OrderUtil;
use Cigarholic\Core\Provision\Context;

return static function ( Context $ctx ): void {
	if ( ! function_exists( 'WC' ) ) {
		$ctx->log( 'skip: WooCommerce is not active' );
		return;
	}

	// Store address: HuyLinh LLC headquarters (brief section 1). The company name itself is not a
	// WooCommerce setting; it goes into the email footer (70-emails) and the theme footer.
	$ctx->option( 'woocommerce_store_address', '7901 4th St N STE 300' );
	$ctx->option( 'woocommerce_store_address_2', '' );
	$ctx->option( 'woocommerce_store_city', 'St Petersburg' );
	$ctx->option( 'woocommerce_default_country', 'US:FL' );
	$ctx->option( 'woocommerce_store_postcode', '33702' );

	// Currency: USD, $1,234.56.
	$ctx->option( 'woocommerce_currency', 'USD' );
	$ctx->option( 'woocommerce_currency_pos', 'left' );
	$ctx->option( 'woocommerce_price_thousand_sep', ',' );
	$ctx->option( 'woocommerce_price_decimal_sep', '.' );
	$ctx->option( 'woocommerce_price_num_decimals', '2' );

	// US only: sell to and ship to the United States; address forms default to the store country.
	$ctx->option( 'woocommerce_allowed_countries', 'specific' );
	$ctx->option( 'woocommerce_specific_allowed_countries', array( 'US' ) );
	$ctx->option( 'woocommerce_ship_to_countries', 'specific' );
	$ctx->option( 'woocommerce_specific_ship_to_countries', array( 'US' ) );
	$ctx->option( 'woocommerce_default_customer_address', 'base' );

	// Units: pounds and inches (the spec table converts to kg and cm itself, CAT-04).
	$ctx->option( 'woocommerce_weight_unit', 'lbs' );
	$ctx->option( 'woocommerce_dimension_unit', 'in' );

	// Inventory (INV-01, ORD-07): stock managed, reserved for 60 minutes during checkout, low and
	// out-of-stock alerts to the site admin (the manager's address is set when the account exists, C4).
	$ctx->option( 'woocommerce_manage_stock', 'yes' );
	$ctx->option( 'woocommerce_hold_stock_minutes', '60' );
	$ctx->option( 'woocommerce_notify_low_stock', 'yes' );
	$ctx->option( 'woocommerce_notify_no_stock', 'yes' );
	$ctx->option( 'woocommerce_stock_email_recipient', (string) get_option( 'admin_email' ) );
	$ctx->option( 'woocommerce_notify_low_stock_amount', '2' );
	$ctx->option( 'woocommerce_notify_no_stock_amount', '0' );

	// Reviews (MKT-05): core reviews with star ratings, only from verified owners, labelled as such.
	$ctx->option( 'woocommerce_enable_reviews', 'yes' );
	$ctx->option( 'woocommerce_review_rating_verification_label', 'yes' );
	$ctx->option( 'woocommerce_review_rating_verification_required', 'yes' );
	$ctx->option( 'woocommerce_enable_review_rating', 'yes' );
	$ctx->option( 'woocommerce_review_rating_required', 'yes' );

	// No telemetry, no marketplace suggestions, no setup wizard nagging the manager.
	$ctx->option( 'woocommerce_allow_tracking', 'no' );
	$ctx->option( 'woocommerce_show_marketplace_suggestions', 'no' );

	$profile = get_option( 'woocommerce_onboarding_profile', array() );
	$profile = is_array( $profile ) ? $profile : array();
	$ctx->option( 'woocommerce_onboarding_profile', array_merge( $profile, array( 'skipped' => true ) ) );

	$hidden = get_option( 'woocommerce_task_list_hidden_lists', array() );
	$hidden = is_array( $hidden ) ? array_values( array_map( 'strval', $hidden ) ) : array();
	$wanted = array_values( array_unique( array_merge( $hidden, array( 'setup', 'extended' ) ) ) );
	sort( $hidden, SORT_STRING );
	sort( $wanted, SORT_STRING );
	$ctx->option( 'woocommerce_task_list_hidden_lists', $hidden === $wanted ? $hidden : $wanted );

	// Order Attribution (where orders come from) stays on.
	$ctx->option( 'woocommerce_feature_order_attribution_enabled', 'yes' );

	// HPOS. Updating the feature option is the supported path (it is what WooCommerce > Settings >
	// Features and `wp wc hpos enable` do): WooCommerce's CustomOrdersTableController creates the
	// order tables on the fly and refuses the switch while orders are out of sync. Provisioning only
	// flips it on a store without orders; a store with legacy orders is migrated by hand first
	// (`wp wc hpos sync`, then `wp wc hpos enable`).
	if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
		$ctx->log( 'ok: woocommerce_custom_orders_table_enabled' );
	} else {
		$orders = wc_get_orders(
			array(
				'limit'  => 1,
				'status' => 'any',
				'return' => 'ids',
			)
		);
		if ( array() !== $orders ) {
			$ctx->log( 'warning: HPOS is off and the store has orders; run `wp wc hpos sync` then `wp wc hpos enable` by hand' );
		} else {
			if ( ! $ctx->is_dry_run() ) {
				update_option( 'woocommerce_custom_orders_table_enabled', 'yes' );
				if ( 'yes' !== get_option( 'woocommerce_custom_orders_table_enabled' ) ) {
					throw new RuntimeException( 'WooCommerce refused to enable HPOS (order tables could not be created).' );
				}
			}
			$ctx->changed( 'HPOS enabled (woocommerce_custom_orders_table_enabled)' );
		}
	}
	// Compatibility mode (posts table kept in sync) is off: every plugin in docs/plugins.md is HPOS native.
	$ctx->option( 'woocommerce_custom_orders_table_data_sync_enabled', 'no' );

	// Block cart and checkout (CHK-01): WooCommerce creates shop, cart, checkout and my-account pages
	// with block content on install and stores their IDs. Recreate whatever is missing or trashed.
	$missing = array();
	foreach ( array( 'shop', 'cart', 'checkout', 'myaccount' ) as $page ) {
		$id   = wc_get_page_id( $page );
		$post = $id > 0 ? get_post( $id ) : null;
		if ( ! $post instanceof WP_Post || 'page' !== $post->post_type || 'publish' !== $post->post_status ) {
			$missing[] = $page;
		}
	}
	if ( array() === $missing ) {
		$ctx->log( 'ok: WooCommerce pages (shop, cart, checkout, my-account)' );
	} else {
		if ( ! $ctx->is_dry_run() ) {
			WC_Install::create_pages();
		}
		$ctx->changed( 'WooCommerce pages created: ' . implode( ', ', $missing ) );
	}
	foreach ( array(
		'cart'     => 'woocommerce/cart',
		'checkout' => 'woocommerce/checkout',
	) as $page => $block ) {
		$post = get_post( wc_get_page_id( $page ) );
		if ( $post instanceof WP_Post && ! has_block( $block, $post ) ) {
			$ctx->log( sprintf( 'warning: the %s page (#%d) does not contain the %s block; the classic shortcode is not supported', $page, $post->ID, $block ) );
		}
	}
};
