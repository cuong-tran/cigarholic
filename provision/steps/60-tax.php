<?php
/**
 * Provisioning step: sales tax (CMP-01, D-07).
 *
 * Taxes are calculated at checkout from the shipping address; prices are entered without tax and
 * shown without tax in the shop and the cart, totals itemized, no rounding at subtotal level.
 * Rates come from Stripe Tax (plugin stripe-tax-for-woocommerce), so no rate rows are provisioned.
 * The owner connects the Stripe account in staging WP admin (docs/OWNER-ACTIONS.md B4); until then
 * checkout charges no tax.
 *
 * Feature IDs: FND-03, CMP-01.
 *
 * @package Cigarholic
 */

use Cigarholic\Core\Provision\Context;

return static function ( Context $ctx ): void {
	if ( ! function_exists( 'WC' ) ) {
		$ctx->log( 'skip: WooCommerce is not active' );
		return;
	}

	$ctx->option( 'woocommerce_calc_taxes', 'yes' );
	$ctx->option( 'woocommerce_prices_include_tax', 'no' );
	$ctx->option( 'woocommerce_tax_based_on', 'shipping' );
	// Shipping is taxable at the rate of the goods in the cart.
	$ctx->option( 'woocommerce_shipping_tax_class', 'inherit' );
	$ctx->option( 'woocommerce_tax_round_at_subtotal', 'no' );
	$ctx->option( 'woocommerce_tax_display_shop', 'excl' );
	$ctx->option( 'woocommerce_tax_display_cart', 'excl' );
	$ctx->option( 'woocommerce_price_display_suffix', '' );
	$ctx->option( 'woocommerce_tax_total_display', 'itemized' );
};
