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
 * Feature IDs: FND-03, ORD-02, ORD-03, CMP-07.
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

	// Colours from the theme palette, with the theme.json values as fallback when the theme is not active.
	$palette = array(
		'accent'    => '#6b4a2e',
		'neutral-1' => '#f4f1ec',
		'base'      => '#ffffff',
		'contrast'  => '#1c1917',
		'neutral-2' => '#5b564f',
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
	$ctx->option( 'woocommerce_email_base_color', $palette['accent'] );
	$ctx->option( 'woocommerce_email_background_color', $palette['neutral-1'] );
	$ctx->option( 'woocommerce_email_body_background_color', $palette['base'] );
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

	// Customer emails (ORD-03): order lifecycle and account emails on.
	foreach ( array( 'customer_on_hold_order', 'customer_processing_order', 'customer_completed_order', 'customer_refunded_order', 'customer_cancelled_order', 'customer_failed_order', 'customer_note', 'customer_new_account', 'customer_reset_password' ) as $email_id ) {
		$email( $email_id, array( 'enabled' => 'yes' ) );
	}
};
