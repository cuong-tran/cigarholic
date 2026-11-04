<?php
/**
 * Ship-time messaging.
 *
 * @package Cigarholic
 */

namespace Cigarholic\Core\Shipping;

use Cigarholic\Core\Feature;
use Cigarholic\Core\Plugin;
use WC_Product;

/**
 * One ship-time promise for the whole store: the business days from order to hand-off to the carrier
 * for in-stock items, set by the manager under WooCommerce > Settings > Shipping > Shipping settings
 * (default 1 to 2). Shown by the `cigarholic/ship-time` block on the product page, as a line under
 * every in-stock cart item (cart, checkout summary, mini cart) and in the order-received next steps
 * (CHK-06). Nothing is promised for an item on
 * backorder or out of stock, or for a quantity above the stock, so the promise stays true (CMP-05, FTC
 * Mail Order Rule): when orders will leave later, the manager changes the days here and every
 * message follows.
 *
 * Feature IDs: SHP-03, CMP-05.
 */
final class ShipTime implements Feature {

	public const OPTION_MIN = 'cigarholic_ship_days_min';
	public const OPTION_MAX = 'cigarholic_ship_days_max';
	public const BLOCK      = 'cigarholic/ship-time';
	public const DEFAULT    = array(
		'min' => 1,
		'max' => 2,
	);
	public const LIMIT      = 60;

	/**
	 * Feature ID.
	 *
	 * @return string
	 */
	public static function id(): string {
		return 'SHP-03';
	}

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'register_block' ) );
		add_filter( 'woocommerce_get_item_data', array( $this, 'cart_item_data' ), 10, 2 );
		add_filter( 'woocommerce_shipping_settings', array( $this, 'settings_fields' ) );
		add_filter( 'woocommerce_admin_settings_sanitize_option_' . self::OPTION_MIN, array( self::class, 'sanitize_days' ) );
		add_filter( 'woocommerce_admin_settings_sanitize_option_' . self::OPTION_MAX, array( self::class, 'sanitize_days' ) );
	}

	/**
	 * Block registration.
	 *
	 * @return void
	 */
	public function register_block(): void {
		register_block_type( Plugin::instance()->dir() . '/blocks/ship-time' );
	}

	/**
	 * The configured range in business days: at least 1, at most LIMIT, max never below min.
	 *
	 * @return array{min: int, max: int}
	 */
	public static function days(): array {
		$min = self::sanitize_days( get_option( self::OPTION_MIN, self::DEFAULT['min'] ) );
		$max = self::sanitize_days( get_option( self::OPTION_MAX, self::DEFAULT['max'] ) );
		return array(
			'min' => $min,
			'max' => max( $min, $max ),
		);
	}

	/**
	 * A stored or submitted day count as a whole number from 1 to LIMIT.
	 *
	 * @param mixed $value Value.
	 * @return int
	 */
	public static function sanitize_days( mixed $value ): int {
		$days = is_numeric( $value ) ? (int) $value : 0;
		return min( self::LIMIT, max( 1, $days ) );
	}

	/**
	 * "1–2 business days", or "2 business days" when both ends are the same.
	 *
	 * @return string
	 */
	public static function range_text(): string {
		$days = self::days();
		if ( $days['min'] === $days['max'] ) {
			/* translators: %d: number of business days */
			return sprintf( _n( '%d business day', '%d business days', $days['max'], 'cigarholic' ), $days['max'] );
		}
		/* translators: 1: fewest business days, 2: most business days */
		return sprintf( __( '%1$d–%2$d business days', 'cigarholic' ), $days['min'], $days['max'] );
	}

	/**
	 * How much of a product the promise covers: 'all' (in stock), 'some' (a variable product with at
	 * least one option in stock and one on backorder) or 'none' (backorder, out of stock, not for sale).
	 *
	 * @param WC_Product $product Product.
	 * @return string
	 */
	public static function coverage( WC_Product $product ): string {
		if ( ! $product->is_purchasable() || ! $product->is_in_stock() ) {
			return 'none';
		}
		if ( $product->is_type( 'variable' ) ) {
			$statuses = array();
			foreach ( $product->get_children() as $child_id ) {
				$child = wc_get_product( $child_id );
				if ( $child instanceof WC_Product && $child->is_purchasable() ) {
					$statuses[] = $child->get_stock_status();
				}
			}
			if ( ! in_array( 'instock', $statuses, true ) ) {
				return 'none';
			}
			return in_array( 'onbackorder', $statuses, true ) ? 'some' : 'all';
		}
		return 'instock' === $product->get_stock_status() ? 'all' : 'none';
	}

	/**
	 * The sentence for a product page, or for any other page when no product is given; '' when the
	 * product gets no promise.
	 *
	 * @param WC_Product|null $product Product, or null outside a product.
	 * @return string
	 */
	public static function sentence( ?WC_Product $product = null ): string {
		$range = self::range_text();
		if ( null === $product ) {
			/* translators: %s: business days, for example "1–2 business days" */
			return sprintf( __( 'In-stock items ship in %s from our US warehouse in Houston, Texas.', 'cigarholic' ), $range );
		}
		switch ( self::coverage( $product ) ) {
			case 'all':
				/* translators: %s: business days, for example "1–2 business days" */
				return sprintf( __( 'Ships in %s from our US warehouse in Houston, Texas.', 'cigarholic' ), $range );
			case 'some':
				/* translators: %s: business days, for example "1–2 business days" */
				return sprintf( __( 'In-stock options ship in %s from our US warehouse in Houston, Texas.', 'cigarholic' ), $range );
			default:
				return '';
		}
	}

	/**
	 * Adds "Ships in 1–2 business days" under an in-stock cart item (Store API item_data, shown by the
	 * cart, checkout and mini-cart blocks without a label). Items on backorder, or ordered above the
	 * stock with backorders allowed, get nothing; WooCommerce shows its own backorder notice.
	 *
	 * @param array<int, array<string, mixed>> $item_data Item data.
	 * @param array<string, mixed>             $cart_item Cart item.
	 * @return array<int, array<string, mixed>>
	 */
	public function cart_item_data( $item_data, $cart_item ) {
		if ( ! is_array( $item_data ) || ! is_array( $cart_item ) ) {
			return $item_data;
		}
		$product  = $cart_item['data'] ?? null;
		$quantity = isset( $cart_item['quantity'] ) ? (int) $cart_item['quantity'] : 1;
		if ( ! $product instanceof WC_Product || 'instock' !== $product->get_stock_status() || $product->is_on_backorder( $quantity ) ) {
			return $item_data;
		}
		$item_data[] = array(
			'key'       => '',
			'name'      => '',
			/* translators: %s: business days, for example "1–2 business days" */
			'value'     => sprintf( __( 'Ships in %s', 'cigarholic' ), self::range_text() ),
			'raw_key'   => 'cigarholic_ship_time',
			'className' => 'cigarholic-ship-time',
		);
		return $item_data;
	}

	/**
	 * Two fields at the end of WooCommerce > Settings > Shipping > Shipping settings. WooCommerce's
	 * settings API saves them (manage_woocommerce and its nonce); sanitize_days() clamps the values.
	 *
	 * @param array<int, array<string, mixed>> $settings Settings.
	 * @return array<int, array<string, mixed>>
	 */
	public function settings_fields( $settings ) {
		if ( ! is_array( $settings ) ) {
			return $settings;
		}
		$settings[] = array(
			'title' => __( 'Ship time', 'cigarholic' ),
			'type'  => 'title',
			'desc'  => __( 'Business days from the order to the hand-off to the carrier, for items in stock. Shown on product pages, under each item in the cart, the checkout and the mini cart, and on the order-received page. Keep it true: before orders will leave later (holidays, stock, a delay), change it here first, and tell customers whose orders are already late (Mail Order Rule; see the manager guide).', 'cigarholic' ),
			'id'    => 'cigarholic_ship_time',
		);
		$settings[] = array(
			'title'             => __( 'Ships in at least', 'cigarholic' ),
			'id'                => self::OPTION_MIN,
			'type'              => 'number',
			'default'           => (string) self::DEFAULT['min'],
			'css'               => 'width: 6em;',
			'desc'              => __( 'business days', 'cigarholic' ),
			'custom_attributes' => array(
				'min'  => '1',
				'max'  => (string) self::LIMIT,
				'step' => '1',
			),
		);
		$settings[] = array(
			'title'             => __( 'Ships in at most', 'cigarholic' ),
			'id'                => self::OPTION_MAX,
			'type'              => 'number',
			'default'           => (string) self::DEFAULT['max'],
			'css'               => 'width: 6em;',
			'desc'              => __( 'business days (the same number as above shows a single day count)', 'cigarholic' ),
			'custom_attributes' => array(
				'min'  => '1',
				'max'  => (string) self::LIMIT,
				'step' => '1',
			),
		);
		$settings[] = array(
			'type' => 'sectionend',
			'id'   => 'cigarholic_ship_time',
		);
		return $settings;
	}
}
