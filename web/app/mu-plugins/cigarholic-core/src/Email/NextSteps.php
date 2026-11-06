<?php
/**
 * "What happens next" in the customer's processing and on-hold emails.
 *
 * @package Cigarholic
 */

namespace Cigarholic\Core\Email;

use Cigarholic\Core\Checkout\OrderNextSteps;
use Cigarholic\Core\Feature;
use WC_Email;
use WC_Order;

/**
 * The customer's "processing" and "on hold" order emails carry the same promise as the order-received
 * card (CHK-06), from the same code: when the order is packed and handed to the carrier (the manager's
 * ship time, SHP-03), what happens to items on backorder, that a tracking email follows, and for an
 * on-hold order that the payment is being checked. It sits after the addresses, before any extra text
 * the manager sets in the email's settings. Support contact is in every email's footer already, so the
 * card's "Questions" step and the seasoning tip are left out. Admin copies get nothing.
 *
 * Feature IDs: ORD-03 (primary), SHP-03, CMP-05.
 */
final class NextSteps implements Feature {

	/**
	 * WooCommerce email IDs that get the section.
	 */
	public const EMAILS = array( 'customer_processing_order', 'customer_on_hold_order' );

	/**
	 * Order-received steps used in the emails, in their order.
	 */
	private const STEPS = array( 'payment', 'packing', 'tracking' );

	/**
	 * Feature ID.
	 *
	 * @return string
	 */
	public static function id(): string {
		return 'ORD-03';
	}

	/**
	 * Hooks: after WooCommerce's customer details (10) and email address (20).
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'woocommerce_email_customer_details', array( $this, 'render' ), 30, 4 );
	}

	/**
	 * Prints the section in the customer's processing and on-hold emails.
	 *
	 * @param mixed $order         Order.
	 * @param mixed $sent_to_admin Whether this copy goes to the store.
	 * @param mixed $plain_text    Whether the email is plain text.
	 * @param mixed $email         Email object.
	 * @return void
	 */
	public function render( $order, $sent_to_admin = false, $plain_text = false, $email = null ): void {
		if ( $sent_to_admin || ! $order instanceof WC_Order || ! $email instanceof WC_Email || ! in_array( $email->id, self::EMAILS, true ) ) {
			return;
		}
		echo self::content( $order, (bool) $plain_text ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped text and kses-filtered links in content().
	}

	/**
	 * The section for an order: HTML (a heading and one paragraph per step) or plain text; '' when the
	 * order gets no next steps (not paid).
	 *
	 * @param WC_Order $order      Order.
	 * @param bool     $plain_text Plain text instead of HTML.
	 * @return string
	 */
	public static function content( WC_Order $order, bool $plain_text ): string {
		$steps = array_values(
			array_filter(
				OrderNextSteps::steps( $order )['steps'],
				static fn( array $step ): bool => in_array( $step['key'], self::STEPS, true )
			)
		);
		if ( array() === $steps ) {
			return '';
		}
		$heading = __( 'What happens next', 'cigarholic' );

		if ( $plain_text ) {
			$text = "\n" . strtoupper( $heading ) . "\n\n";
			foreach ( $steps as $step ) {
				$text .= $step['title'] . ' ' . html_entity_decode( wp_strip_all_tags( $step['html'] ), ENT_QUOTES, 'UTF-8' ) . "\n\n";
			}
			return $text;
		}

		$links = array( 'a' => array( 'href' => array() ) );
		$html  = '<h2>' . esc_html( $heading ) . '</h2>';
		foreach ( $steps as $step ) {
			$html .= '<p><strong>' . esc_html( $step['title'] ) . '</strong> ' . wp_kses( $step['html'], $links ) . '</p>';
		}
		return '<div class="cigarholic-next-steps">' . $html . '</div>';
	}
}
