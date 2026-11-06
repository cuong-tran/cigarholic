<?php
/**
 * Tracking details and the items of one shipment, plain text (SHP-04).
 *
 * Served by Cigarholic\Core\Shipping\Tracking instead of
 * woocommerce/templates/emails/plain/email-fulfillment-details.php (version 10.1.0, same hooks and item list);
 * a theme override still wins.
 *
 * @var WC_Order       $order         Order.
 * @var mixed          $fulfillment   The shipment (Automattic\WooCommerce\Admin\Features\Fulfillments\Fulfillment).
 * @var bool           $sent_to_admin Whether the store gets this copy.
 * @var bool           $plain_text    True.
 * @var WC_Email|mixed $email         Email object.
 *
 * @package Cigarholic
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce's own email hooks, fired as its template fires them.

use Cigarholic\Core\Shipping\Tracking;

defined( 'ABSPATH' ) || exit;

if ( null === $fulfillment->get_date_deleted() ) {
	$cigarholic_tracking = Tracking::details( $fulfillment );
	if ( '' !== $cigarholic_tracking['carrier'] ) {
		echo esc_html__( 'Carrier:', 'cigarholic' ) . ' ' . esc_html( $cigarholic_tracking['carrier'] ) . "\n";
	}
	if ( '' !== $cigarholic_tracking['number'] ) {
		echo esc_html__( 'Tracking number:', 'cigarholic' ) . ' ' . esc_html( $cigarholic_tracking['number'] ) . "\n";
	}
	if ( '' !== $cigarholic_tracking['url'] ) {
		echo esc_html__( 'Track your package:', 'cigarholic' ) . ' ' . esc_url_raw( $cigarholic_tracking['url'] ) . "\n";
	}
	if ( '' === $cigarholic_tracking['number'] && '' === $cigarholic_tracking['url'] ) {
		echo esc_html__( 'This package has no tracking number. If you have a question about its delivery, reply to this email.', 'cigarholic' ) . "\n";
	}
	$cigarholic_account = $sent_to_admin ? '' : Tracking::account_url( $order );
	if ( '' !== $cigarholic_account ) {
		echo "\n" . esc_html__( 'You can also follow your order in your account:', 'cigarholic' ) . ' ' . esc_url_raw( $cigarholic_account ) . "\n";
	}
	echo "\n\n";
}

/** This action is documented in woocommerce/templates/emails/email-fulfillment-details.php */
do_action( 'woocommerce_email_before_fulfillment_table', $order, $fulfillment, $sent_to_admin, $plain_text, $email );

echo esc_html( null === $fulfillment->get_date_deleted() ? __( 'In this package', 'cigarholic' ) : __( 'Removed shipment', 'cigarholic' ) );
echo "\n\n==========\n\n";

/* translators: %s: order number */
echo esc_html( sprintf( __( 'Order #%s', 'cigarholic' ), $order->get_order_number() ) );
if ( $sent_to_admin ) {
	echo ' (' . esc_url_raw( $order->get_edit_order_url() ) . ')';
}
$cigarholic_created = $order->get_date_created();
if ( $cigarholic_created ) {
	echo ' (' . esc_html( wc_format_datetime( $cigarholic_created ) ) . ')';
}
echo "\n\n\n";
echo wc_get_email_fulfillment_items( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WooCommerce's plain item list, escaped in its own template.
	$order,
	$fulfillment,
	array(
		'show_sku'      => $sent_to_admin,
		'show_image'    => true,
		'image_size'    => array( 48, 48 ),
		'plain_text'    => $plain_text,
		'sent_to_admin' => $sent_to_admin,
	)
);

/** This action is documented in woocommerce/templates/emails/email-fulfillment-details.php */
do_action( 'woocommerce_email_after_fulfillment_table', $order, $fulfillment, $sent_to_admin, $plain_text, $email );
