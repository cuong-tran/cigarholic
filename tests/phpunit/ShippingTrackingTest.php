<?php
/**
 * Tests for the tracking number and the shipping email (WooCommerce Order Fulfillments).
 *
 * @package Cigarholic
 */

namespace Cigarholic\Tests;

use Automattic\WooCommerce\Admin\Features\Fulfillments\Fulfillment;
use Cigarholic\Core\Plugin;
use Cigarholic\Core\Shipping\Tracking;
use WC_Order;
use WC_Product_Simple;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * Every shipment goes through WooCommerce's own REST route, as the order screen's drawer sends it, so the
 * hooks, the order's fulfillment status and the emails run as they do for the manager.
 *
 * Feature IDs: SHP-04, ORD-03.
 */
final class ShippingTrackingTest extends WP_UnitTestCase {

	private const USPS = array(
		'_tracking_number'   => '9400111899223197428490',
		'_shipment_provider' => 'usps',
		'_tracking_url'      => 'https://tools.usps.com/go/TrackConfirmAction?tLabels=9400111899223197428490',
	);

	private const UPS = array(
		'_tracking_number'   => '1Z999AA10123456784',
		'_shipment_provider' => 'ups',
		'_tracking_url'      => 'https://www.ups.com/track?tracknum=1Z999AA10123456784',
	);

	/**
	 * Logged in as an administrator, mail trapped.
	 */
	public function set_up(): void {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		reset_phpmailer_instance();
	}

	/**
	 * An order for two products.
	 *
	 * @param string $status      Order status.
	 * @param int    $customer_id Customer, 0 for a guest.
	 * @return WC_Order
	 */
	private function order( string $status = 'processing', int $customer_id = 0 ): WC_Order {
		$order = wc_create_order( array( 'customer_id' => $customer_id ) );
		$this->assertInstanceOf( WC_Order::class, $order );
		foreach ( array( 'Tracking test humidor', 'Tracking test hygrometer' ) as $name ) {
			$product = new WC_Product_Simple();
			$product->set_name( $name );
			$product->set_regular_price( '50' );
			$product->save();
			$order->add_product( $product, 1 );
		}
		$order->set_billing_email( 'buyer@example.test' );
		$order->set_billing_first_name( 'Sam' );
		$order->calculate_totals();
		$order->set_status( $status );
		$order->save();
		reset_phpmailer_instance();
		return $order;
	}

	/**
	 * Ships items through WooCommerce's fulfillment REST route with the customer notification on.
	 *
	 * @param WC_Order              $order Order.
	 * @param int[]                 $lines Indexes of the order's lines to ship.
	 * @param array<string, string> $meta  Tracking meta.
	 * @return void
	 */
	private function ship( WC_Order $order, array $lines, array $meta ): void {
		$items   = array_values( $order->get_items() );
		$request = new WP_REST_Request( 'POST', '/wc/v3/orders/' . $order->get_id() . '/fulfillments' );
		$request->set_query_params( array( 'notify_customer' => true ) );
		$request->set_header( 'content-type', 'application/json' );
		$request->set_body(
			(string) wp_json_encode(
				array(
					'status'       => 'fulfilled',
					'is_fulfilled' => true,
					'items'        => array_map(
						static fn( int $line ): array => array(
							'item_id' => $items[ $line ]->get_id(),
							'qty'     => $items[ $line ]->get_quantity(),
						),
						$lines
					),
					'meta_data'    => array_map(
						static fn( string $key, string $value ): array => array(
							'key'   => $key,
							'value' => $value,
						),
						array_keys( $meta ),
						array_values( $meta )
					),
				)
			)
		);
		$this->assertSame( 201, rest_do_request( $request )->get_status() );
	}

	/**
	 * The site title as the email subjects print it.
	 *
	 * @return string
	 */
	private function site(): string {
		return wp_specialchars_decode( (string) get_option( 'blogname' ), ENT_QUOTES );
	}

	/**
	 * Emails sent since the last reset, as subject => body.
	 *
	 * @return array<string, string>
	 */
	private function sent(): array {
		$sent = array();
		foreach ( tests_retrieve_phpmailer_instance()->mock_sent as $mail ) {
			$sent[ (string) $mail['subject'] ] = (string) $mail['body'];
		}
		return $sent;
	}

	/**
	 * One package for the whole order: the store's shipping email with carrier, number and button, the
	 * order completed, and no "Completed order" email after it.
	 */
	public function test_whole_order_ships_in_one_package(): void {
		$order  = $this->order();
		$number = $order->get_order_number();
		$this->ship( $order, array( 0, 1 ), self::USPS );

		$sent = $this->sent();
		$this->assertSame( array( "Your {$this->site()} order {$number} has shipped" ), array_keys( $sent ), 'one email, the shipping email' );
		$body = $sent[ "Your {$this->site()} order {$number} has shipped" ];
		$this->assertStringContainsString( 'Your order is on its way', $body );
		$this->assertStringContainsString( 'Good news: your order has left our US warehouse. You can follow the package with the tracking details below.', $body );
		$this->assertStringContainsString( '<strong>Carrier:</strong> USPS', $body );
		$this->assertStringContainsString( '<strong>Tracking number:</strong> 9400111899223197428490', $body );
		$this->assertMatchesRegularExpression( '#<a class="cigarholic-track" href="https://tools\.usps\.com/go/TrackConfirmAction\?tLabels=9400111899223197428490"[^>]*>Track your package</a>#', $body );
		$this->assertStringContainsString( 'In this package', $body );
		$this->assertStringContainsString( 'Tracking test hygrometer', $body );
		$this->assertStringNotContainsString( 'Woo!', $body );
		$this->assertStringNotContainsString( 'fulfilled', strtolower( $body ) );
		$this->assertStringNotContainsString( '/wp/my-account', $body, "WooCommerce's site_url() link misses Bedrock's core path" );
		$this->assertStringNotContainsString( 'your account', $body, 'a guest has no account' );

		$order = wc_get_order( $order->get_id() );
		$this->assertInstanceOf( WC_Order::class, $order );
		$this->assertSame( 'completed', $order->get_status() );
		$notes = wp_list_pluck( wc_get_order_notes( array( 'order_id' => $order->get_id() ) ), 'content' );
		$this->assertNotEmpty( preg_grep( '/^Every item has shipped, so the order is complete\./', $notes ) );
	}

	/**
	 * Two packages: "part of" while an item is still to ship, "the rest of" for the last one; the order
	 * completes only with the last.
	 */
	public function test_order_ships_in_two_packages(): void {
		$order  = $this->order();
		$number = $order->get_order_number();

		$this->ship( $order, array( 0 ), self::USPS );
		$sent = $this->sent();
		$this->assertArrayHasKey( "Part of your {$this->site()} order {$number} has shipped", $sent );
		$body = $sent[ "Part of your {$this->site()} order {$number} has shipped" ];
		$this->assertStringContainsString( 'Part of your order is on its way', $body );
		$this->assertStringContainsString( 'The rest follows in a separate package, and we email you again when it ships.', $body );
		$this->assertStringContainsString( 'Tracking test humidor', $body );
		$this->assertStringNotContainsString( 'Tracking test hygrometer', $body, 'only the items in this package' );
		$this->assertSame( 'processing', wc_get_order( $order->get_id() )->get_status() );

		reset_phpmailer_instance();
		$this->ship( $order, array( 1 ), self::UPS );
		$sent = $this->sent();
		$this->assertSame( array( "The rest of your {$this->site()} order {$number} has shipped" ), array_keys( $sent ) );
		$this->assertStringContainsString( '<strong>Carrier:</strong> UPS', $sent[ "The rest of your {$this->site()} order {$number} has shipped" ] );
		$this->assertSame( 'completed', wc_get_order( $order->get_id() )->get_status() );
	}

	/**
	 * Without a tracking number the email says so and shows no button; a customer with an account gets
	 * the link to the order in it; the plain-text email carries the same.
	 */
	public function test_no_tracking_number_account_link_and_plain_text(): void {
		$customer = self::factory()->user->create( array( 'role' => 'customer' ) );
		$order    = $this->order( 'processing', $customer );
		$this->ship( $order, array( 0, 1 ), array() );

		$body = (string) current( $this->sent() );
		$this->assertStringContainsString( 'Good news: your order has left our US warehouse.</p>', $body, 'no tracking sentence without a number' );
		$this->assertStringContainsString( 'This package has no tracking number. If you have a question about its delivery, reply to this email.', $body );
		$this->assertStringNotContainsString( 'Track your package', $body );
		$this->assertStringNotContainsString( 'Carrier:', $body );
		$this->assertMatchesRegularExpression( '#You can also follow your order in <a href="' . preg_quote( esc_url( $order->get_view_order_url() ), '#' ) . '"[^>]*>your account</a>#', $body );

		$emails            = WC()->mailer()->get_emails();
		$email             = $emails['WC_Email_Customer_Fulfillment_Created'];
		$type              = $email->email_type;
		$second            = $this->order( 'processing', $customer );
		$email->email_type = 'plain';
		try {
			$this->ship( $second, array( 0, 1 ), self::USPS );
		} finally {
			$email->email_type = $type;
		}
		// PHPMailer wraps plain text at 76 characters.
		$plain = (string) preg_replace( '/\s+/', ' ', (string) current( $this->sent() ) );
		$this->assertStringContainsString( 'Carrier: USPS Tracking number: 9400111899223197428490 Track your package: https://tools.usps.com/go/TrackConfirmAction?tLabels=9400111899223197428490 ', $plain );
		$this->assertStringContainsString( 'You can also follow your order in your account: ' . $second->get_view_order_url(), $plain );
		$this->assertStringNotContainsString( '<', $plain );
		$this->assertStringNotContainsString( 'Woo!', $plain );
	}

	/**
	 * An order on hold (payment being checked) is not completed by a shipment, and an order completed
	 * without any shipment still gets WooCommerce's "Completed order" email.
	 */
	public function test_on_hold_order_and_completion_without_shipment(): void {
		$on_hold = $this->order( 'on-hold' );
		$this->ship( $on_hold, array( 0, 1 ), self::USPS );
		$this->assertSame( 'on-hold', wc_get_order( $on_hold->get_id() )->get_status() );

		$order = $this->order();
		$order->update_status( 'completed' );
		$subjects = array_keys( $this->sent() );
		$this->assertCount( 1, $subjects );
		$this->assertStringContainsString( 'complete', strtolower( $subjects[0] ) );

		$tracking = new Tracking();
		$this->assertTrue( $tracking->completed_email_enabled( true, $order ) );
		$this->assertFalse( $tracking->completed_email_enabled( false, $order ), 'never switches a disabled email on' );
	}

	/**
	 * A subject or heading set in the email's settings is kept; the customer's order page says
	 * "shipped", not "fulfilled".
	 */
	public function test_manager_wording_and_order_page_sentence(): void {
		$order                                 = $this->order();
		$email                                 = WC()->mailer()->get_emails()['WC_Email_Customer_Fulfillment_Created'];
		$tracking                              = new Tracking();
		$email->placeholders['{order_number}'] = $order->get_order_number();
		$this->assertSame( "Your {$this->site()} order {$order->get_order_number()} has shipped", $tracking->subject( 'WooCommerce default', $order, $email ) );
		$this->assertSame( 'Your order is on its way', $tracking->heading( 'WooCommerce default', $order, $email ) );
		update_option(
			$email->get_option_key(),
			array(
				'enabled' => 'yes',
				'subject' => 'Shipped: order {order_number}',
				'heading' => 'On the way',
			)
		);
		$this->assertSame( 'Shipped: order 12', $tracking->subject( 'Shipped: order 12', $order, $email ) );
		$this->assertSame( 'On the way', $tracking->heading( 'On the way', $order, $email ) );

		$this->assertSame( 'It has shipped.', $tracking->status_text( 'It has been Fulfilled.', 'fulfilled' ) );
		$this->assertSame( 'Part of it has shipped.', $tracking->status_text( 'x', 'partially_fulfilled' ) );
		$this->assertSame( '', $tracking->status_text( 'It has no fulfillments yet.', 'no_fulfillments' ) );
		$this->assertSame( '', $tracking->status_text( 'It is currently Unfulfilled.', 'unfulfilled' ) );
		$this->assertSame( 'custom', $tracking->status_text( 'custom', 'a-plugin-status' ) );
	}

	/**
	 * Tracking links that are not http(s) are dropped; a carrier entered by hand keeps its name.
	 */
	public function test_tracking_details(): void {
		$fulfillment = new Fulfillment();
		$fulfillment->add_meta_data( '_tracking_number', 'ABC123' );
		$fulfillment->add_meta_data( '_tracking_url', 'javascript:alert(1)' );
		$fulfillment->add_meta_data( '_shipment_provider', 'other' );
		$fulfillment->add_meta_data( '_provider_name', 'Houston Freight Co.' );
		$this->assertSame(
			array(
				'carrier' => 'Houston Freight Co.',
				'number'  => 'ABC123',
				'url'     => '',
			),
			Tracking::details( $fulfillment )
		);
		$this->assertSame( 'FedEx', Tracking::details( $this->fulfillment( array( '_shipment_provider' => 'fedex' ) ) )['carrier'] );
		$this->assertSame( 'DHL', Tracking::details( $this->fulfillment( array( '_shipment_provider' => 'dhl' ) ) )['carrier'] );
	}

	/**
	 * Each template copy was written against the WooCommerce template version it names; a WooCommerce
	 * update that changes one fails here until the copy is checked. A theme override is left alone.
	 */
	public function test_templates_follow_woocommerce(): void {
		foreach ( Tracking::TEMPLATES as $name => $version ) {
			$theirs = WC()->plugin_path() . '/templates/' . $name;
			$ours   = Plugin::instance()->dir() . '/templates/' . $name;
			$this->assertFileExists( $ours );
			$this->assertSame( 1, preg_match( '/@version\s+(\S+)/', (string) file_get_contents( $theirs ), $match ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a local template file.
			$this->assertSame( $version, $match[1], "WooCommerce changed {$name}: compare it with the copy in cigarholic-core/templates, then update the version in Tracking::TEMPLATES" );
			$this->assertStringContainsString( "(version {$version},", (string) file_get_contents( $ours ), "{$name} names the version it follows" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a local template file.
			$this->assertSame( $ours, ( new Tracking() )->template( $theirs, $name ) );
		}
		$theme = get_stylesheet_directory() . '/woocommerce/emails/email-fulfillment-details.php';
		$this->assertSame( $theme, ( new Tracking() )->template( $theme, 'emails/email-fulfillment-details.php' ) );
		$other = WC()->plugin_path() . '/templates/emails/customer-completed-order.php';
		$this->assertSame( $other, ( new Tracking() )->template( $other, 'emails/customer-completed-order.php' ) );
	}

	/**
	 * A fulfillment with these meta values.
	 *
	 * @param array<string, string> $meta Meta.
	 * @return Fulfillment
	 */
	private function fulfillment( array $meta ): Fulfillment {
		$fulfillment = new Fulfillment();
		foreach ( $meta as $key => $value ) {
			$fulfillment->add_meta_data( $key, $value );
		}
		return $fulfillment;
	}
}
