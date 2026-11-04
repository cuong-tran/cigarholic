<?php
/**
 * Tests for the ship-time promise.
 *
 * @package Cigarholic
 */

namespace Cigarholic\Tests;

use Cigarholic\Core\Shipping\ShipTime;
use WC_Product;
use WC_Product_Simple;
use WC_Product_Variable;
use WC_Product_Variation;
use WP_UnitTestCase;

/**
 * Feature IDs: SHP-03, CMP-05.
 */
final class ShipTimeTest extends WP_UnitTestCase {

	/**
	 * A simple product with managed stock.
	 *
	 * @param int    $stock      Stock quantity.
	 * @param string $backorders Backorders setting: no, notify or yes.
	 * @return WC_Product_Simple
	 */
	private function simple( int $stock = 5, string $backorders = 'no' ): WC_Product_Simple {
		$product = new WC_Product_Simple();
		$product->set_name( 'Ship time test humidor' );
		$product->set_regular_price( '189' );
		$product->set_manage_stock( true );
		$product->set_backorders( $backorders );
		$product->set_stock_quantity( $stock );
		$product->save();
		return $product;
	}

	/**
	 * A variable product with one variation per stock quantity (0 with backorders allowed means on
	 * backorder).
	 *
	 * @param int[] $stocks Stock per variation.
	 * @return WC_Product_Variable
	 */
	private function variable( array $stocks ): WC_Product_Variable {
		$parent = new WC_Product_Variable();
		$parent->set_name( 'Ship time test variable humidor' );
		$parent->save();
		foreach ( $stocks as $i => $stock ) {
			$variation = new WC_Product_Variation();
			$variation->set_parent_id( $parent->get_id() );
			$variation->set_regular_price( '199' );
			$variation->set_sku( 'SHIP-TIME-TEST-' . $parent->get_id() . '-' . $i );
			$variation->set_manage_stock( true );
			$variation->set_backorders( 'notify' );
			$variation->set_stock_quantity( $stock );
			$variation->save();
		}
		\WC_Product_Variable::sync( $parent->get_id() );
		$reloaded = wc_get_product( $parent->get_id() );
		$this->assertInstanceOf( WC_Product_Variable::class, $reloaded );
		return $reloaded;
	}

	/**
	 * The default is 1 to 2 business days; stored values are clamped to 1..60 and max never falls
	 * below min.
	 */
	public function test_days_default_and_clamp(): void {
		$this->assertSame(
			array(
				'min' => 1,
				'max' => 2,
			),
			ShipTime::days()
		);
		$this->assertSame( '1–2 business days', ShipTime::range_text() );

		update_option( ShipTime::OPTION_MIN, '3' );
		update_option( ShipTime::OPTION_MAX, '5' );
		$this->assertSame( '3–5 business days', ShipTime::range_text() );

		update_option( ShipTime::OPTION_MAX, '3' );
		$this->assertSame( '3 business days', ShipTime::range_text() );

		update_option( ShipTime::OPTION_MIN, '1' );
		update_option( ShipTime::OPTION_MAX, '1' );
		$this->assertSame( '1 business day', ShipTime::range_text() );

		update_option( ShipTime::OPTION_MIN, '4' );
		update_option( ShipTime::OPTION_MAX, '2' );
		$this->assertSame(
			array(
				'min' => 4,
				'max' => 4,
			),
			ShipTime::days()
		);

		update_option( ShipTime::OPTION_MIN, '0' );
		update_option( ShipTime::OPTION_MAX, '900' );
		$this->assertSame(
			array(
				'min' => 1,
				'max' => 60,
			),
			ShipTime::days()
		);

		update_option( ShipTime::OPTION_MIN, 'soon' );
		$this->assertSame( 1, ShipTime::days()['min'] );
		$this->assertSame( 7, ShipTime::sanitize_days( '7.6' ) );
		$this->assertSame( 1, ShipTime::sanitize_days( '-3' ) );
	}

	/**
	 * A product gets the promise only while it ships from stock.
	 */
	public function test_product_sentence_follows_stock(): void {
		$this->assertSame( 'Ships in 1–2 business days from our US warehouse in Houston, Texas.', ShipTime::sentence( $this->simple() ) );
		$this->assertSame( '', ShipTime::sentence( $this->simple( 0 ) ), 'out of stock' );
		$this->assertSame( '', ShipTime::sentence( $this->simple( 0, 'notify' ) ), 'on backorder' );
		$this->assertSame( 'In-stock items ship in 1–2 business days from our US warehouse in Houston, Texas.', ShipTime::sentence( null ) );

		$this->assertSame( 'Ships in 1–2 business days from our US warehouse in Houston, Texas.', ShipTime::sentence( $this->variable( array( 4, 2 ) ) ) );
		$this->assertSame( 'In-stock options ship in 1–2 business days from our US warehouse in Houston, Texas.', ShipTime::sentence( $this->variable( array( 4, 0 ) ) ) );
		$this->assertSame( '', ShipTime::sentence( $this->variable( array( 0, 0 ) ) ), 'every option on backorder' );

		$no_price = $this->simple();
		$no_price->set_regular_price( '' );
		$no_price->save();
		$this->assertSame( '', ShipTime::sentence( $no_price ), 'not purchasable' );
	}

	/**
	 * Cart items get "Ships in …" without a label while the quantity ships from stock; a backorder,
	 * or a quantity above the stock, gets nothing.
	 */
	public function test_cart_item_data(): void {
		$feature = new ShipTime();
		$product = $this->simple( 2, 'notify' );

		$data = $feature->cart_item_data(
			array(),
			array(
				'data'     => $product,
				'quantity' => 2,
			)
		);
		$this->assertCount( 1, $data );
		$this->assertSame( 'Ships in 1–2 business days', $data[0]['value'] );
		$this->assertSame( '', $data[0]['key'] );
		$this->assertSame( 'cigarholic_ship_time', $data[0]['raw_key'] );

		$this->assertSame(
			array(),
			$feature->cart_item_data(
				array(),
				array(
					'data'     => $product,
					'quantity' => 3,
				)
			),
			'a quantity above the stock is partly backordered'
		);
		$this->assertSame(
			array(),
			$feature->cart_item_data(
				array(),
				array(
					'data'     => $this->simple( 0, 'notify' ),
					'quantity' => 1,
				)
			)
		);
		$this->assertSame( array( 'x' ), $feature->cart_item_data( array( 'x' ), array( 'quantity' => 1 ) ), 'no product, untouched' );

		// What the Store API returns for a real cart line, through the hook the plugin registers.
		WC()->cart->empty_cart();
		WC()->cart->add_to_cart( $product->get_id(), 1 );
		$cart = rest_get_server()->dispatch( new \WP_REST_Request( 'GET', '/wc/store/v1/cart' ) )->get_data();
		WC()->cart->empty_cart();
		$this->assertIsArray( $cart );
		$values = array_column( $cart['items'][0]['item_data'], 'value' );
		$this->assertSame( array( 'Ships in 1–2 business days' ), $values );
	}

	/**
	 * The shipping settings get the two fields and their section; WooCommerce's sanitize filter clamps.
	 */
	public function test_settings_fields(): void {
		$fields = ( new ShipTime() )->settings_fields(
			array(
				array(
					'id'   => 'shipping_options',
					'type' => 'title',
				),
			)
		);
		$ids    = array_column( $fields, 'id' );
		$this->assertSame( array( 'shipping_options', 'cigarholic_ship_time', ShipTime::OPTION_MIN, ShipTime::OPTION_MAX, 'cigarholic_ship_time' ), $ids );
		$this->assertSame( 'sectionend', $fields[4]['type'] );
		$this->assertSame( 12, apply_filters( 'woocommerce_admin_settings_sanitize_option_' . ShipTime::OPTION_MAX, '12' ) );
		$this->assertSame( 60, apply_filters( 'woocommerce_admin_settings_sanitize_option_' . ShipTime::OPTION_MIN, '61' ) );
	}

	/**
	 * The block: the product's sentence in product context, nothing for a backorder, the in-stock
	 * sentence elsewhere, and colour and font-size classes from its supports.
	 */
	public function test_block_render(): void {
		$this->assertTrue( \WP_Block_Type_Registry::get_instance()->is_registered( ShipTime::BLOCK ) );

		$render = static function ( int $post_id ): string {
			$block = new \WP_Block(
				array(
					'blockName'    => ShipTime::BLOCK,
					'attrs'        => array(
						'textColor' => 'neutral-2',
						'fontSize'  => 'small',
					),
					'innerBlocks'  => array(),
					'innerHTML'    => '',
					'innerContent' => array(),
				),
				array( 'postId' => $post_id )
			);
			return $block->render();
		};

		$html = $render( $this->simple()->get_id() );
		$this->assertStringContainsString( 'Ships in 1–2 business days from our US warehouse in Houston, Texas.', $html );
		$this->assertStringContainsString( 'has-neutral-2-color', $html );
		$this->assertStringContainsString( 'has-small-font-size', $html );
		$this->assertMatchesRegularExpression( '#^<p [^>]*class="cigarholic-ship-time#', trim( $html ) );

		$this->assertSame( '', trim( $render( $this->simple( 0, 'notify' )->get_id() ) ) );

		$page = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$this->assertStringContainsString( 'In-stock items ship in 1–2 business days', $render( $page ) );
	}
}
