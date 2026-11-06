<?php
/**
 * Tracking details and the items of one shipment, in the customer's fulfillment emails (SHP-04).
 *
 * Served by Cigarholic\Core\Shipping\Tracking instead of woocommerce/templates/emails/email-fulfillment-details.php
 * (version 10.7.0, same hooks and item table); a theme override still wins. Differences: the carrier's name
 * instead of its key, a tracking button only when there is a link, no empty "Tracking Number" line, and the
 * account link only for a customer with an account, to the order itself (WooCommerce's link used
 * site_url(), which is Bedrock's /wp/ core path).
 *
 * @var WC_Order       $order         Order.
 * @var mixed          $fulfillment   The shipment (Automattic\WooCommerce\Admin\Features\Fulfillments\Fulfillment).
 * @var bool           $sent_to_admin Whether the store gets this copy.
 * @var bool           $plain_text    False.
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
		echo '<p><strong>' . esc_html__( 'Carrier:', 'cigarholic' ) . '</strong> ' . esc_html( $cigarholic_tracking['carrier'] ) . '</p>';
	}
	if ( '' !== $cigarholic_tracking['number'] ) {
		echo '<p><strong>' . esc_html__( 'Tracking number:', 'cigarholic' ) . '</strong> ' . esc_html( $cigarholic_tracking['number'] ) . '</p>';
	}
	if ( '' !== $cigarholic_tracking['url'] ) {
		$cigarholic_base = (string) get_option( 'woocommerce_email_base_color', '#1f2b25' );
		$cigarholic_text = wc_light_or_dark( $cigarholic_base, '#1a1815', '#ffffff' );
		printf(
			'<p><a class="cigarholic-track" href="%1$s" style="display: inline-block; padding: 12px 24px; background-color: %2$s; color: %3$s; font-weight: bold; text-decoration: none; border-radius: 4px;">%4$s</a></p>',
			esc_url( $cigarholic_tracking['url'] ),
			esc_attr( $cigarholic_base ),
			esc_attr( $cigarholic_text ),
			esc_html__( 'Track your package', 'cigarholic' )
		);
	}
	if ( '' === $cigarholic_tracking['number'] && '' === $cigarholic_tracking['url'] ) {
		echo '<p>' . esc_html__( 'This package has no tracking number. If you have a question about its delivery, reply to this email.', 'cigarholic' ) . '</p>';
	}
	$cigarholic_account = $sent_to_admin ? '' : Tracking::account_url( $order );
	if ( '' !== $cigarholic_account ) {
		printf(
			'<p>%s</p>',
			sprintf(
				/* translators: %s: link to the order in the customer's account */
				esc_html__( 'You can also follow your order in %s.', 'cigarholic' ),
				'<a href="' . esc_url( $cigarholic_account ) . '">' . esc_html__( 'your account', 'cigarholic' ) . '</a>'
			)
		);
	}
}

/** This action is documented in woocommerce/templates/emails/email-fulfillment-details.php */
do_action( 'woocommerce_email_before_fulfillment_table', $order, $fulfillment, $sent_to_admin, $plain_text, $email );
?>

<h2 class="email-order-detail-heading">
	<?php
	echo null === $fulfillment->get_date_deleted() ? esc_html__( 'In this package', 'cigarholic' ) : esc_html__( 'Removed shipment', 'cigarholic' );
	echo '<br><span>';
	$cigarholic_order_label = sprintf(
		/* translators: %s: order number */
		esc_html__( 'Order #%s', 'cigarholic' ),
		esc_html( $order->get_order_number() )
	);
	if ( $sent_to_admin ) {
		$cigarholic_order_label = '<a class="link" href="' . esc_url( $order->get_edit_order_url() ) . '">' . $cigarholic_order_label . '</a>';
	}
	$cigarholic_created = $order->get_date_created();
	if ( $cigarholic_created ) {
		$cigarholic_order_label .= ' (<time datetime="' . esc_attr( $cigarholic_created->format( 'c' ) ) . '">' . esc_html( wc_format_datetime( $cigarholic_created ) ) . '</time>)';
	}
	echo wp_kses_post( $cigarholic_order_label );
	echo '</span>';
	?>
</h2>

<div style="margin-bottom: 24px;">
	<table class="td font-family email-order-details" cellspacing="0" cellpadding="6" style="width: 100%;" border="1">
		<tbody>
			<?php
			echo wc_get_email_fulfillment_items( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WooCommerce's item rows, escaped in its own template.
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
			?>
		</tbody>
	</table>
</div>

<?php
/** This action is documented in woocommerce/templates/emails/email-fulfillment-details.php */
do_action( 'woocommerce_email_after_fulfillment_table', $order, $fulfillment, $sent_to_admin, $plain_text, $email );
