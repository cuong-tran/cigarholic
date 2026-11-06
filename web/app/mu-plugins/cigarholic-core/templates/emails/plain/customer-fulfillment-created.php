<?php
/**
 * The customer's "shipped" email, plain text (SHP-04).
 *
 * Served by Cigarholic\Core\Shipping\Tracking instead of
 * woocommerce/templates/emails/plain/customer-fulfillment-created.php (version 10.1.0, same hooks in the same
 * order); a theme override still wins.
 *
 * @var WC_Order       $order              Order.
 * @var mixed          $fulfillment        The shipment (Automattic\WooCommerce\Admin\Features\Fulfillments\Fulfillment).
 * @var string         $email_heading      Heading.
 * @var string         $additional_content Text from the email's settings.
 * @var bool           $sent_to_admin      Always false: a customer email.
 * @var bool           $plain_text         True.
 * @var WC_Email       $email              Email object.
 *
 * @package Cigarholic
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce's own email hooks and filter, used as its template uses them.

use Cigarholic\Core\Shipping\Tracking;

defined( 'ABSPATH' ) || exit;

echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";
echo esc_html( wp_strip_all_tags( $email_heading ) );
echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

echo esc_html( Tracking::introduction( $order, $fulfillment ) ) . "\n\n";

/** This action is documented in woocommerce/templates/emails/customer-fulfillment-created.php */
do_action( 'woocommerce_email_fulfillment_details', $order, $fulfillment, $sent_to_admin, $plain_text, $email );

echo "\n----------------------------------------\n\n";

/** This action is documented in woocommerce/templates/emails/customer-fulfillment-created.php */
do_action( 'woocommerce_email_fulfillment_meta', $order, $fulfillment, $sent_to_admin, $plain_text, $email );

/** This action is documented in woocommerce/templates/emails/customer-fulfillment-created.php */
do_action( 'woocommerce_email_customer_details', $order, $sent_to_admin, $plain_text, $email );

echo "\n\n----------------------------------------\n\n";

if ( $additional_content ) {
	echo esc_html( wp_strip_all_tags( wptexturize( $additional_content ) ) );
	echo "\n\n----------------------------------------\n\n";
}

/** This filter is documented in woocommerce/includes/emails/class-wc-email.php */
echo wp_kses_post( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) );
