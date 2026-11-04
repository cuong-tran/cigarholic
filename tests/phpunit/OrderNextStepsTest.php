<?php
/**
 * Tests for the order-received next steps.
 *
 * @package Cigarholic
 */

namespace Cigarholic\Tests;

use Cigarholic\Core\Checkout\OrderNextSteps;
use WC_Order;
use WC_Order_Item_Product;
use WC_Product_Simple;
use WP_UnitTestCase;

/**
 * Feature IDs: CHK-06, SHP-03, CMP-05.
 */
final class OrderNextStepsTest extends WP_UnitTestCase {

	/**
	 * Clean query state after each test.
	 */
	public function tear_down(): void {
		unset( $_GET['key'] );
		set_query_var( 'order-received', '' );
		parent::tear_down();
	}

	/**
	 * A simple product, optionally in a category.
	 *
	 * @param string $category Category slug, '' for none.
	 * @return WC_Product_Simple
	 */
	private function product( string $category = '' ): WC_Product_Simple {
		$product = new WC_Product_Simple();
		$product->set_name( 'Next steps test product' );
		$product->set_regular_price( '120' );
		if ( '' !== $category ) {
			$term = term_exists( $category, 'product_cat' );
			if ( ! is_array( $term ) ) {
				$term = wp_insert_term( $category, 'product_cat', array( 'slug' => $category ) );
			}
			$this->assertIsArray( $term );
			$product->set_category_ids( array( (int) $term['term_id'] ) );
		}
		$product->save();
		return $product;
	}

	/**
	 * An order for the given products in a status; lines listed in $backordered carry WooCommerce's
	 * "Backordered" meta.
	 *
	 * @param WC_Product_Simple[] $products    Products, one line each.
	 * @param string              $status      Order status.
	 * @param int[]               $backordered Indexes of backordered lines.
	 * @param int                 $customer_id Customer user ID, 0 for a guest.
	 * @return WC_Order
	 */
	private function order( array $products, string $status = 'processing', array $backordered = array(), int $customer_id = 0 ): WC_Order {
		$order = wc_create_order( array( 'customer_id' => $customer_id ) );
		$this->assertInstanceOf( WC_Order::class, $order );
		foreach ( $products as $i => $product ) {
			$item_id = $order->add_product( $product, 1 );
			if ( in_array( $i, $backordered, true ) ) {
				$item = $order->get_item( $item_id );
				$this->assertInstanceOf( WC_Order_Item_Product::class, $item );
				$item->add_meta_data( 'Backordered', 1, true );
				$item->save();
			}
		}
		$order->set_status( $status );
		$order->save();
		return $order;
	}

	/**
	 * Step titles and plain text of a result.
	 *
	 * @param array{steps: list<array{title: string, html: string}>, tip: string} $result Result.
	 * @return string
	 */
	private function text( array $result ): string {
		$lines = array();
		foreach ( $result['steps'] as $step ) {
			$lines[] = $step['title'] . ' ' . html_entity_decode( wp_strip_all_tags( $step['html'] ) );
		}
		return implode( "\n", $lines );
	}

	/**
	 * A paid guest order: packing with the ship-time setting, tracking without an account link,
	 * support with the order number, the seasoning tip for a humidor.
	 */
	public function test_paid_guest_order_with_a_humidor(): void {
		$order  = $this->order( array( $this->product( 'desktop-humidors' ) ) );
		$result = OrderNextSteps::steps( $order );
		$text   = $this->text( $result );

		$this->assertCount( 3, $result['steps'] );
		$this->assertStringContainsString( 'Packing and shipping. We pack your order and hand it to the carrier within 1–2 business days from our US warehouse in Houston, Texas.', $text );
		$this->assertStringContainsString( 'Tracking. When your order ships, you receive an email with the tracking number.', $text );
		$this->assertStringNotContainsString( 'in your account', $text );
		$this->assertStringContainsString( 'Please quote order number ' . $order->get_order_number() . '.', $text );
		$this->assertStringContainsString( 'call +1 813 602 7585', $text );
		$this->assertStringContainsString( 'New humidor? Season it before the first use', $result['tip'] );

		update_option( 'cigarholic_ship_days_min', '3' );
		update_option( 'cigarholic_ship_days_max', '5' );
		$this->assertStringContainsString( 'within 3–5 business days', $this->text( OrderNextSteps::steps( $order ) ) );
	}

	/**
	 * Accessories get no seasoning tip; a customer with an account gets the Orders link.
	 */
	public function test_account_link_and_no_tip_without_a_humidor(): void {
		$customer = self::factory()->user->create( array( 'role' => 'customer' ) );
		$result   = OrderNextSteps::steps( $this->order( array( $this->product( 'humidor-accessories' ) ), 'processing', array(), $customer ) );
		$this->assertSame( '', $result['tip'] );
		$this->assertStringContainsString( 'under Orders in your account', $this->text( $result ) );
		$this->assertStringContainsString( 'href="' . esc_url( wc_get_account_endpoint_url( 'orders' ) ) . '"', $result['steps'][1]['html'] );
	}

	/**
	 * On hold: a payment check comes first. Unpaid, failed, cancelled and refunded orders get no card.
	 */
	public function test_statuses(): void {
		$product = $this->product();
		$on_hold = OrderNextSteps::steps( $this->order( array( $product ), 'on-hold' ) );
		$this->assertSame( 'Payment check.', $on_hold['steps'][0]['title'] );
		$this->assertCount( 4, $on_hold['steps'] );
		$this->assertCount( 3, OrderNextSteps::steps( $this->order( array( $product ), 'completed' ) )['steps'] );
		foreach ( array( 'pending', 'failed', 'cancelled', 'refunded' ) as $status ) {
			$this->assertSame( array(), OrderNextSteps::steps( $this->order( array( $product ), $status ) )['steps'], $status );
		}
	}

	/**
	 * Backordered lines: the whole order, or part of it, ships when back in stock, with the right to
	 * cancel (FTC Mail Order Rule); no ship-time promise for what is not in stock.
	 */
	public function test_backorders(): void {
		$all = $this->text( OrderNextSteps::steps( $this->order( array( $this->product() ), 'processing', array( 0 ) ) ) );
		$this->assertStringContainsString( 'Your order is on backorder.', $all );
		$this->assertStringContainsString( 'cancel for a full refund', $all );
		$this->assertStringNotContainsString( 'business days', $all );

		$some = $this->text( OrderNextSteps::steps( $this->order( array( $this->product(), $this->product() ), 'processing', array( 1 ) ) ) );
		$this->assertStringContainsString( 'In-stock items leave our US warehouse in Houston, Texas within 1–2 business days. Items on backorder follow', $some );

		// Plain "allow" backorders record nothing on the line; the stock below zero tells. Setting the
		// order to processing takes its unit from the stock, as a paid checkout does.
		$silent = $this->product();
		$silent->set_manage_stock( true );
		$silent->set_backorders( 'yes' );
		$silent->set_stock_quantity( 0 );
		$silent->save();
		$this->assertStringContainsString( 'Your order is on backorder.', $this->text( OrderNextSteps::steps( $this->order( array( $silent ) ) ) ) );
		$this->assertSame( -1, wc_get_product( $silent->get_id() )?->get_stock_quantity() );

		$last = $this->product();
		$last->set_manage_stock( true );
		$last->set_backorders( 'yes' );
		$last->set_stock_quantity( 1 );
		$last->save();
		$this->assertStringContainsString( 'We pack your order', $this->text( OrderNextSteps::steps( $this->order( array( $last ) ) ) ), 'the last unit is not a backorder' );
		$this->assertSame( 0, wc_get_product( $last->get_id() )?->get_stock_quantity() );
	}

	/**
	 * Without an order (no valid key) the card shows the general steps; the contact page and the FAQ
	 * are linked only once they are published.
	 */
	public function test_general_steps_and_page_links(): void {
		$general = OrderNextSteps::steps( null );
		$text    = $this->text( $general );
		$this->assertCount( 3, $general['steps'] );
		$this->assertStringContainsString( 'Please quote your order number.', $text );
		$this->assertStringContainsString( 'Reply to your confirmation email', $text );
		$this->assertStringNotContainsString( 'contact page', $text );
		$this->assertSame( '', $general['tip'] );

		$contact = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_name'   => 'contact',
				'post_status' => 'draft',
			)
		);
		$this->assertStringNotContainsString( 'contact page', $this->text( OrderNextSteps::steps( null ) ) );
		wp_update_post(
			array(
				'ID'          => $contact,
				'post_status' => 'publish',
			)
		);
		$html = OrderNextSteps::steps( null )['steps'][2]['html'];
		$this->assertStringContainsString( '<a href="' . esc_url( (string) get_permalink( $contact ) ) . '">contact page</a>', $html );
	}

	/**
	 * The order is read only with its valid key, as WooCommerce's confirmation blocks require.
	 */
	public function test_current_order_needs_the_key(): void {
		$order = $this->order( array( $this->product() ) );
		$this->assertNull( OrderNextSteps::current_order() );

		set_query_var( 'order-received', (string) $order->get_id() );
		$this->assertNull( OrderNextSteps::current_order(), 'no key' );
		$_GET['key'] = 'wc_order_wrong';
		$this->assertNull( OrderNextSteps::current_order(), 'wrong key' );
		$_GET['key'] = $order->get_order_key();
		$found       = OrderNextSteps::current_order();
		$this->assertInstanceOf( WC_Order::class, $found );
		$this->assertSame( $order->get_id(), $found->get_id() );
	}

	/**
	 * The block renders the card with the Card group classes, and nothing for an unpaid order.
	 */
	public function test_block_render(): void {
		$this->assertTrue( \WP_Block_Type_Registry::get_instance()->is_registered( OrderNextSteps::BLOCK ) );
		$render = static function (): string {
			return render_block(
				array(
					'blockName'    => OrderNextSteps::BLOCK,
					'attrs'        => array( 'align' => 'wide' ),
					'innerBlocks'  => array(),
					'innerHTML'    => '',
					'innerContent' => array(),
				)
			);
		};

		$order = $this->order( array( $this->product( 'travel-humidors' ) ) );
		set_query_var( 'order-received', (string) $order->get_id() );
		$_GET['key'] = $order->get_order_key();
		$html        = $render();
		$this->assertMatchesRegularExpression( '#^<div [^>]*class="wp-block-group is-style-card order-next-steps wp-block-cigarholic-order-next-steps alignwide [^"]*is-style-card--\d+#', trim( $html ), 'a real Card group, so WordPress applies the style variation' );
		$this->assertStringContainsString( '<h2 class="wp-block-heading">What happens next</h2>', $html );
		$this->assertStringContainsString( '<li><strong>Packing and shipping.</strong>', $html );
		$this->assertStringContainsString( 'New humidor?', $html );

		$order->set_status( 'failed' );
		$order->save();
		$this->assertSame( '', trim( $render() ) );
	}
}
