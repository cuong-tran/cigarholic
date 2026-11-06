<?php
/**
 * The customer's "shipped" email: WooCommerce's "Fulfillment created" email in the store's words (SHP-04).
 *
 * Served by Cigarholic\Core\Shipping\Tracking instead of woocommerce/templates/emails/customer-fulfillment-created.php
 * (version 10.4.0, same hooks in the same order); a theme override still wins.
 *
 * @var WC_Order       $order              Order.
 * @var mixed          $fulfillment        The shipment (Automattic\WooCommerce\Admin\Features\Fulfillments\Fulfillment).
 * @var string         $email_heading      Heading.
 * @var string         $additional_content Text from the email's settings.
 * @var bool           $sent_to_admin      Always false: a customer email.
 * @var bool           $plain_text         False.
 * @var WC_Email       $email              Email object.
 *
 * @package Cigarholic
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce's own email hooks, fired as its template fires them.

use Cigarholic\Core\Shipping\Tracking;

defined( 'ABSPATH' ) || exit;

/** This action is documented in woocommerce/templates/emails/customer-fulfillment-created.php */
do_action( 'woocommerce_email_header', $email_heading, $email ); ?>

<div class="email-introduction">
	<p><?php echo esc_html( Tracking::introduction( $order, $fulfillment ) ); ?></p>
</div>

<?php

/** This action is documented in woocommerce/templates/emails/customer-fulfillment-created.php */
do_action( 'woocommerce_email_fulfillment_details', $order, $fulfillment, $sent_to_admin, $plain_text, $email );

/** This action is documented in woocommerce/templates/emails/customer-fulfillment-created.php */
do_action( 'woocommerce_email_fulfillment_meta', $order, $fulfillment, $sent_to_admin, $plain_text, $email );

/** This action is documented in woocommerce/templates/emails/customer-fulfillment-created.php */
do_action( 'woocommerce_email_customer_details', $order, $sent_to_admin, $plain_text, $email );

if ( $additional_content ) {
	echo '<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation"><tr><td class="email-additional-content">';
	echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
	echo '</td></tr></table>';
}

/** This action is documented in woocommerce/templates/emails/customer-fulfillment-created.php */
do_action( 'woocommerce_email_footer', $email );
