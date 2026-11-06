<?php
/**
 * Order-received next steps.
 *
 * @package Cigarholic
 */

namespace Cigarholic\Core\Checkout;

use Cigarholic\Core\Feature;
use Cigarholic\Core\Plugin;
use Cigarholic\Core\Shipping\ShipTime;
use WC_Order;
use WC_Order_Item_Product;

/**
 * The "What happens next" card on the order-received page, rendered by the
 * `cigarholic/order-next-steps` block from the order itself: packing and the ship time from the
 * manager's setting (SHP-03), what happens to items on backorder, the tracking email, the account
 * link for customers with an account, how to reach support with the order number, and the seasoning
 * tip when a humidor was ordered. Nothing renders for an order that is not paid (pending, failed,
 * cancelled, refunded): WooCommerce's status block already tells the buyer what to do. The order is
 * only read when the URL carries its valid order key, as WooCommerce's own confirmation blocks
 * require; without it the card shows the general steps.
 *
 * Feature IDs: CHK-06, SHP-03, CMP-05.
 */
final class OrderNextSteps implements Feature {

	public const BLOCK = 'cigarholic/order-next-steps';
	public const PHONE = '+1 813 602 7585';

	/**
	 * Order statuses that get next steps (WooCommerce slugs without the wc- prefix).
	 */
	private const PAID_STATUSES = array( 'processing', 'on-hold', 'completed' );

	/**
	 * Product categories (slugs) whose products need seasoning before the first use.
	 */
	private const HUMIDOR_CATEGORIES = array( 'desktop-humidors', 'cabinet-humidors', 'travel-humidors' );

	/**
	 * Feature ID.
	 *
	 * @return string
	 */
	public static function id(): string {
		return 'CHK-06';
	}

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'register_block' ) );
	}

	/**
	 * Block registration.
	 *
	 * @return void
	 */
	public function register_block(): void {
		register_block_type( Plugin::instance()->dir() . '/blocks/order-next-steps' );
	}

	/**
	 * The order on the order-received page when the URL carries its valid key, else null.
	 *
	 * @return WC_Order|null
	 */
	public static function current_order(): ?WC_Order {
		$order_id = absint( get_query_var( 'order-received' ) );
		$key      = isset( $_GET['key'] ) ? wc_clean( wp_unslash( $_GET['key'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the order key is the capability, as in WooCommerce's confirmation blocks.
		if ( $order_id <= 0 || ! is_string( $key ) || '' === $key ) {
			return null;
		}
		$order = wc_get_order( $order_id );
		return $order instanceof WC_Order && $order->key_is_valid( $key ) ? $order : null;
	}

	/**
	 * The steps for an order (or the general steps without one), each a key (payment, packing,
	 * tracking, questions), a bold title and an HTML text that contains only escaped text and links;
	 * an empty list when the card must not show. The processing and on-hold emails reuse them (ORD-03).
	 *
	 * @param WC_Order|null $order Order with a verified key, or null.
	 * @return array{steps: list<array{key: string, title: string, html: string}>, tip: string}
	 */
	public static function steps( ?WC_Order $order ): array {
		if ( null !== $order && ! in_array( $order->get_status(), self::PAID_STATUSES, true ) ) {
			return array(
				'steps' => array(),
				'tip'   => '',
			);
		}

		$range      = ShipTime::range_text();
		$steps      = array();
		$backorders = null === $order ? array( 0, 0 ) : self::backorder_counts( $order );

		if ( null !== $order && 'on-hold' === $order->get_status() ) {
			$steps[] = array(
				'key'   => 'payment',
				'title' => __( 'Payment check.', 'cigarholic' ),
				'html'  => esc_html__( 'Your payment is being confirmed. We start packing as soon as it is, and email you if we need anything from you.', 'cigarholic' ),
			);
		}

		if ( $backorders[0] > 0 && $backorders[0] === $backorders[1] ) {
			$packing = esc_html__( 'Your order is on backorder. We ship it as soon as it is back in stock and email you the expected ship date; if that date does not suit you, you can cancel for a full refund.', 'cigarholic' );
		} elseif ( $backorders[0] > 0 ) {
			/* translators: %s: business days, for example "1–2 business days" */
			$packing = esc_html( sprintf( __( 'In-stock items leave our US warehouse in Houston, Texas within %s. Items on backorder follow as soon as they are back in stock; we email you their expected ship date, and you can cancel them for a full refund.', 'cigarholic' ), $range ) );
		} else {
			/* translators: %s: business days, for example "1–2 business days" */
			$packing = esc_html( sprintf( __( 'We pack your order and hand it to the carrier within %s from our US warehouse in Houston, Texas.', 'cigarholic' ), $range ) );
		}
		$steps[] = array(
			'key'   => 'packing',
			'title' => __( 'Packing and shipping.', 'cigarholic' ),
			'html'  => $packing,
		);

		$tracking = esc_html__( 'When your order ships, you receive an email with the tracking number.', 'cigarholic' );
		if ( null !== $order && $order->get_customer_id() > 0 ) {
			$tracking .= ' ' . sprintf(
				/* translators: %s: link to the orders list in the customer's account */
				esc_html__( 'You can also follow it under %s in your account.', 'cigarholic' ),
				'<a href="' . esc_url( wc_get_account_endpoint_url( 'orders' ) ) . '">' . esc_html__( 'Orders', 'cigarholic' ) . '</a>'
			);
		}
		$steps[] = array(
			'key'   => 'tracking',
			'title' => __( 'Tracking.', 'cigarholic' ),
			'html'  => $tracking,
		);

		$email    = sanitize_email( (string) get_option( 'woocommerce_email_from_address', '' ) );
		$contacts = array( esc_html__( 'reply to your confirmation email', 'cigarholic' ) );
		if ( '' !== $email ) {
			$contacts[] = sprintf(
				/* translators: %s: support email address as a link */
				esc_html__( 'write to %s', 'cigarholic' ),
				'<a href="' . esc_url( 'mailto:' . $email ) . '">' . esc_html( $email ) . '</a>'
			);
		}
		$contact_page = self::published_page_url( 'contact' );
		if ( '' !== $contact_page ) {
			$contacts[] = sprintf(
				/* translators: %s: link to the contact page */
				esc_html__( 'use the %s', 'cigarholic' ),
				'<a href="' . esc_url( $contact_page ) . '">' . esc_html__( 'contact page', 'cigarholic' ) . '</a>'
			);
		}
		$contacts[] = sprintf(
			/* translators: %s: phone number as a link */
			esc_html__( 'call %s', 'cigarholic' ),
			'<a href="tel:' . esc_attr( str_replace( ' ', '', self::PHONE ) ) . '">' . esc_html( self::PHONE ) . '</a>'
		);
		$last      = array_pop( $contacts );
		$questions = ucfirst( implode( ', ', $contacts ) ) . ' ' . esc_html__( 'or', 'cigarholic' ) . ' ' . $last . '.';
		if ( null !== $order ) {
			/* translators: %s: order number */
			$questions .= ' ' . esc_html( sprintf( __( 'Please quote order number %s.', 'cigarholic' ), $order->get_order_number() ) );
		} else {
			$questions .= ' ' . esc_html__( 'Please quote your order number.', 'cigarholic' );
		}
		$steps[] = array(
			'key'   => 'questions',
			'title' => __( 'Questions.', 'cigarholic' ),
			'html'  => $questions,
		);

		$tip = '';
		if ( null !== $order && self::has_humidor( $order ) ) {
			$tip = esc_html__( 'New humidor? Season it before the first use: place a humidifier or seasoning pack inside, close the lid and check the hygrometer daily. When it holds a steady 65 to 72 percent, usually after two to five days, it is ready for cigars. Do not wipe the cedar with water.', 'cigarholic' );
			$faq = self::published_page_url( 'faq' );
			if ( '' !== $faq ) {
				$tip .= ' <a href="' . esc_url( $faq ) . '">' . esc_html__( 'Care and seasoning guide', 'cigarholic' ) . '</a>';
			}
		}

		return array(
			'steps' => $steps,
			'tip'   => $tip,
		);
	}

	/**
	 * How many product lines are on backorder, and how many product lines there are. A line is on
	 * backorder when WooCommerce recorded a backordered quantity on it at checkout (backorders
	 * "allowed, but notify customer"), or when its product's managed stock is below zero (plain
	 * "allow" records nothing; a paid order has already taken its units from the stock).
	 *
	 * @param WC_Order $order Order.
	 * @return array{0: int, 1: int}
	 */
	private static function backorder_counts( WC_Order $order ): array {
		$backordered = 0;
		$lines       = 0;
		foreach ( $order->get_items() as $item ) {
			if ( ! $item instanceof WC_Order_Item_Product ) {
				continue;
			}
			++$lines;
			/** This filter is documented in woocommerce/includes/class-wc-order-item-product.php */
			$key     = (string) apply_filters( 'woocommerce_backordered_item_meta_name', __( 'Backordered', 'woocommerce' ), $item ); // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce's own filter and meta key, read exactly as WooCommerce writes them.
			$product = $item->get_product();
			if ( (int) $item->get_meta( $key ) > 0 || ( $product instanceof \WC_Product && $product->managing_stock() && (int) $product->get_stock_quantity() < 0 ) ) {
				++$backordered;
			}
		}
		return array( $backordered, $lines );
	}

	/**
	 * Whether the order contains a product from a humidor category.
	 *
	 * @param WC_Order $order Order.
	 * @return bool
	 */
	private static function has_humidor( WC_Order $order ): bool {
		foreach ( $order->get_items() as $item ) {
			if ( ! $item instanceof WC_Order_Item_Product ) {
				continue;
			}
			$product_id = $item->get_product_id();
			if ( $product_id > 0 && has_term( self::HUMIDOR_CATEGORIES, 'product_cat', $product_id ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * URL of a published page by slug, '' while it is a draft or missing (the policy and help pages
	 * are drafts until the owner approves their text).
	 *
	 * @param string $slug Page slug.
	 * @return string
	 */
	private static function published_page_url( string $slug ): string {
		$page = get_page_by_path( $slug, OBJECT, 'page' );
		if ( ! $page instanceof \WP_Post || 'publish' !== $page->post_status ) {
			return '';
		}
		$url = get_permalink( $page );
		return is_string( $url ) ? $url : '';
	}
}
