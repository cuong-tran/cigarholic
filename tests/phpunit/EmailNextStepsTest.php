<?php
/**
 * Tests for "What happens next" in the customer's order emails.
 *
 * @package Cigarholic
 */

namespace Cigarholic\Tests;

use Cigarholic\Core\Email\NextSteps;
use Cigarholic\Core\Shipping\ShipTime;
use WC_Email;
use WC_Order;
use WC_Product_Simple;
use WP_UnitTestCase;

/**
 * Feature IDs: ORD-03, SHP-03, CMP-05.
 */
final class EmailNextStepsTest extends WP_UnitTestCase {

	/**
	 * A paid order for one simple product.
	 *
	 * @param string $status Order status.
	 * @return WC_Order
	 */
	private function order( string $status = 'processing' ): WC_Order {
		$product = new WC_Product_Simple();
		$product->set_name( 'Email next steps test product' );
		$product->set_regular_price( '120' );
		$product->save();
		$order = wc_create_order();
		$this->assertInstanceOf( WC_Order::class, $order );
		$order->add_product( $product, 1 );
		$order->set_billing_email( 'buyer@example.test' );
		$order->set_billing_first_name( 'Sam' );
		$order->set_status( $status );
		$order->save();
		return $order;
	}

	/**
	 * A WooCommerce email by class name, with the order as its object, as trigger() would set it up.
	 *
	 * @param string   $class_name Email class, for example WC_Email_Customer_Processing_Order.
	 * @param WC_Order $order      Order.
	 * @return WC_Email
	 */
	private function email( string $class_name, WC_Order $order ): WC_Email {
		$emails = WC()->mailer()->get_emails();
		$this->assertArrayHasKey( $class_name, $emails );
		$email                                 = $emails[ $class_name ];
		$email->object                         = $order;
		$email->placeholders['{order_number}'] = $order->get_order_number();
		return $email;
	}

	/**
	 * The processing email carries the ship time from the setting and the tracking line, in HTML and
	 * in plain text, after the addresses.
	 */
	public function test_processing_email_states_ship_time_and_tracking(): void {
		update_option( ShipTime::OPTION_MIN, '2' );
		update_option( ShipTime::OPTION_MAX, '4' );
		$email = $this->email( 'WC_Email_Customer_Processing_Order', $this->order() );

		$html = $email->get_content_html();
		$this->assertStringContainsString( '<h2>What happens next</h2>', $html );
		$this->assertStringContainsString( 'within 2–4 business days from our US warehouse in Houston, Texas', $html );
		$this->assertStringContainsString( 'When your order ships, you receive an email with the tracking number.', $html );
		$this->assertStringNotContainsString( 'Questions.', $html, 'support contact is in the footer' );
		$this->assertStringNotContainsString( 'Payment check.', $html );
		$this->assertGreaterThan( strpos( $html, 'buyer@example.test' ), strpos( $html, 'What happens next' ), 'after the addresses' );

		$plain = $email->get_content_plain();
		$this->assertStringContainsString( "WHAT HAPPENS NEXT\n\nPacking and shipping. We pack your order and hand it to the carrier within 2–4 business days", $plain );
		$this->assertStringNotContainsString( '<', NextSteps::content( $this->order(), true ) );
	}

	/**
	 * The on-hold email starts with the payment check; admin emails and unpaid orders get nothing.
	 */
	public function test_on_hold_email_admin_copies_and_unpaid_orders(): void {
		$on_hold = $this->email( 'WC_Email_Customer_On_Hold_Order', $this->order( 'on-hold' ) )->get_content_html();
		$this->assertStringContainsString( 'Payment check.', $on_hold );
		$this->assertLessThan( strpos( $on_hold, 'Packing and shipping.' ), strpos( $on_hold, 'Payment check.' ) );

		$admin = $this->email( 'WC_Email_New_Order', $this->order() )->get_content_html();
		$this->assertStringNotContainsString( 'What happens next', $admin );

		$completed = $this->email( 'WC_Email_Customer_Completed_Order', $this->order( 'completed' ) )->get_content_html();
		$this->assertStringNotContainsString( 'What happens next', $completed, 'a shipped order has its own email' );

		$this->assertSame( '', NextSteps::content( $this->order( 'pending' ), false ) );
		$this->assertSame( '', NextSteps::content( $this->order( 'failed' ), true ) );
	}
}
