<?php
/**
 * Provisioning step: checkout and accounts (FND-03).
 *
 * Guest checkout with optional account creation (CHK-01), phone required for the carrier and
 * company optional (CHK-04), coupons on (CHK-03). The terms and privacy pages checkout links to
 * (CHK-05) are assigned in 20-pages, where those pages are created.
 *
 * Feature IDs: FND-03, CHK-01, CHK-03, CHK-04, CHK-05.
 *
 * @package Cigarholic
 */

use Cigarholic\Core\Provision\Context;

return static function ( Context $ctx ): void {
	if ( ! function_exists( 'WC' ) ) {
		$ctx->log( 'skip: WooCommerce is not active' );
		return;
	}

	// Accounts: guests check out; they may tick "create an account" and get a generated password
	// by email. No stand-alone registration on My account (customers come from orders).
	$ctx->option( 'woocommerce_enable_guest_checkout', 'yes' );
	$ctx->option( 'woocommerce_enable_checkout_login_reminder', 'yes' );
	$ctx->option( 'woocommerce_enable_signup_and_login_from_checkout', 'yes' );
	$ctx->option( 'woocommerce_registration_generate_password', 'yes' );
	$ctx->option( 'woocommerce_enable_myaccount_registration', 'no' );
	$ctx->option( 'woocommerce_enable_delayed_account_creation', 'no' );

	// Address fields (CHK-04): phone required (carriers want it for furniture-sized parcels),
	// company optional, address line 2 optional.
	$ctx->option( 'woocommerce_checkout_phone_field', 'required' );
	$ctx->option( 'woocommerce_checkout_company_field', 'optional' );
	$ctx->option( 'woocommerce_checkout_address_2_field', 'optional' );

	// Coupons (CHK-03): on, each coupon applied to the original price.
	$ctx->option( 'woocommerce_enable_coupons', 'yes' );
	$ctx->option( 'woocommerce_calc_discounts_sequentially', 'no' );
};
