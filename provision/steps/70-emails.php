<?php
/**
 * Provisioning step: transactional emails (ORD-02, ORD-03, CMP-07).
 *
 * Sender "Cigarholic <support@cigarholic.com>", a footer with the company name and both addresses
 * (CAN-SPAM wants a physical address), colours from the theme palette (theme.json), the manager's
 * order notifications (new, cancelled, failed) to the admin email, every core order and account
 * email enabled. The logo (header image) is added when the brand assets arrive (B9). The low-stock
 * recipient is set with the inventory options in 10-woocommerce-general.
 *
 * Feature IDs: FND-03, ORD-02, ORD-03, CMP-07, CNT-01 (colours), SHP-04 (shipping emails).
 *
 * @package Cigarholic
 */

use Cigarholic\Core\Provision\Context;

return static function ( Context $ctx ): void {
	if ( ! function_exists( 'WC' ) ) {
		$ctx->log( 'skip: WooCommerce is not active' );
		return;
	}

	$ctx->option( 'woocommerce_email_from_name', 'Cigarholic' );
	$ctx->option( 'woocommerce_email_from_address', 'support@cigarholic.com' );
	$ctx->option(
		'woocommerce_email_footer_text',
		"Cigarholic by HuyLinh LLC, 7901 4th St N STE 300, St Petersburg, FL 33702\n"
		. "US warehouse: 8388 W Sam Houston Pkwy S #172, Houston, TX 77072. Phone +1 813 602 7585\n"
		. 'Handcrafted in Vietnam, shipped from Texas. Questions: support@cigarholic.com'
	);

	// Colours from the theme palette (CNT-01 brand system), with the theme.json values as fallback when the
	// theme is not active: Forest header, Ash outer background, Ash tint body, Ink text, Stone footer text.
	$palette = array(
		'forest'    => '#1f2b25',
		'base'      => '#eee9e0',
		'neutral-1' => '#f7f4ef',
		'contrast'  => '#1a1815',
		'neutral-2' => '#5e574e',
	);
	if ( function_exists( 'wp_get_global_settings' ) ) {
		$theme_palette = wp_get_global_settings( array( 'color', 'palette', 'theme' ) );
		if ( is_array( $theme_palette ) ) {
			foreach ( $theme_palette as $entry ) {
				if ( is_array( $entry ) && isset( $entry['slug'], $entry['color'] ) && isset( $palette[ $entry['slug'] ] ) && is_string( $entry['color'] ) ) {
					$palette[ $entry['slug'] ] = strtolower( $entry['color'] );
				}
			}
		}
	}
	$ctx->option( 'woocommerce_email_base_color', $palette['forest'] );
	$ctx->option( 'woocommerce_email_background_color', $palette['base'] );
	$ctx->option( 'woocommerce_email_body_background_color', $palette['neutral-1'] );
	$ctx->option( 'woocommerce_email_text_color', $palette['contrast'] );
	$ctx->option( 'woocommerce_email_footer_text_color', $palette['neutral-2'] );
	$ctx->option( 'woocommerce_email_auto_sync_with_theme', 'no' );

	/**
	 * Merges values into an email's settings option (woocommerce_<email>_settings), keeping the rest.
	 *
	 * @param string                $email_id Email ID, for example new_order.
	 * @param array<string, string> $values   Settings to enforce.
	 * @return void
	 */
	$email = static function ( string $email_id, array $values ) use ( $ctx ): void {
		$key     = 'woocommerce_' . $email_id . '_settings';
		$current = get_option( $key, array() );
		$current = is_array( $current ) ? $current : array();
		if ( array_intersect_key( $current, $values ) === $values ) {
			$ctx->log( sprintf( 'ok: %s', $key ) );
			return;
		}
		if ( ! $ctx->is_dry_run() ) {
			update_option( $key, array_merge( $current, $values ) );
		}
		$ctx->changed( sprintf( 'option %s', $key ) );
	};

	// Manager notifications (ORD-02): new orders, cancellations and failed payments.
	$admin_email = (string) get_option( 'admin_email' );
	foreach ( array( 'new_order', 'cancelled_order', 'failed_order' ) as $email_id ) {
		$email(
			$email_id,
			array(
				'enabled'   => 'yes',
				'recipient' => $admin_email,
			)
		);
	}

	// Customer emails (ORD-03): order lifecycle and account emails on, and the shipping emails of WooCommerce's
	// Order Fulfillments (SHP-04: shipped, shipment updated, shipment removed; sent only when the manager leaves
	// "Fulfillment notification" on). The completed-order email stays on for orders completed without a
	// shipment; cigarholic-core skips it for orders whose items all shipped.
	foreach ( array( 'customer_on_hold_order', 'customer_processing_order', 'customer_completed_order', 'customer_refunded_order', 'customer_cancelled_order', 'customer_failed_order', 'customer_note', 'customer_new_account', 'customer_reset_password', 'customer_fulfillment_created', 'customer_fulfillment_updated', 'customer_fulfillment_deleted' ) as $email_id ) {
		$email( $email_id, array( 'enabled' => 'yes' ) );
	}
};
