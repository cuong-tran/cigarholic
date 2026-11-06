<?php
/**
 * Tracking number and "shipped" email on WooCommerce's Order Fulfillments.
 *
 * @package Cigarholic
 */

namespace Cigarholic\Core\Shipping;

use Automattic\WooCommerce\Admin\Features\Fulfillments\DataStore\FulfillmentsDataStore;
use Automattic\WooCommerce\Admin\Features\Fulfillments\DataStore\FulfillmentsDataStoreInterface;
use Automattic\WooCommerce\Admin\Features\Fulfillments\Fulfillment;
use Automattic\WooCommerce\Admin\Features\Fulfillments\FulfillmentUtils;
use Cigarholic\Core\Feature;
use Cigarholic\Core\Plugin;
use WC_Email;
use WC_Order;

/**
 * The manager enters the carrier's tracking number on the order with WooCommerce's own Order
 * Fulfillments (switched on by provisioning step 50-shipping): Orders → the order's fulfillment badge →
 * items, tracking number, "Find info" (USPS, UPS, FedEx, DHL and others are recognised and get their
 * tracking link) → Fulfill items. WooCommerce stores the shipment, notes it on the order and sends the
 * customer its "Fulfillment created" email. This class turns that into the store's "shipped" email and
 * makes one action ship an order:
 *
 * - The fulfillment emails' text and tracking block come from `templates/` here instead of WooCommerce's
 *   (no "Woo!", the carrier's name instead of its key, a tracking button only when there is a link, the
 *   account link only for customers with an account and through the account endpoint, which
 *   WooCommerce's `site_url()` link misses on Bedrock's `/wp/` core path). A theme override still wins.
 * - The default subject and heading say "has shipped" for the whole order, "part of" while items are
 *   still to ship, "the rest of" for the last package; a subject or heading set in the email's settings
 *   (WooCommerce → Settings → Emails → Fulfillment created) is kept.
 * - A processing order whose items have all shipped is completed; WooCommerce's "Completed order" email
 *   is not sent for such an order, because the shipping email already told the customer.
 * - The order page in the customer's account says "It has shipped." instead of "It has been Fulfilled."
 *
 * Feature IDs: SHP-04 (primary), ORD-03.
 */
final class Tracking implements Feature {

	/**
	 * WooCommerce email templates served from this plugin, with the WooCommerce template version each
	 * copy was written against (TrackingTest fails when WooCommerce changes one).
	 */
	public const TEMPLATES = array(
		'emails/customer-fulfillment-created.php'       => '10.4.0',
		'emails/plain/customer-fulfillment-created.php' => '10.1.0',
		'emails/email-fulfillment-details.php'          => '10.7.0',
		'emails/plain/email-fulfillment-details.php'    => '10.1.0',
	);

	/**
	 * Feature ID.
	 *
	 * @return string
	 */
	public static function id(): string {
		return 'SHP-04';
	}

	/**
	 * Hooks. The core fulfillment hooks after WooCommerce's own (10), which records the order's
	 * fulfillment status first.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'wc_get_template', array( $this, 'template' ), 10, 2 );
		add_filter( 'woocommerce_email_subject_customer_fulfillment_created', array( $this, 'subject' ), 10, 3 );
		add_filter( 'woocommerce_email_heading_customer_fulfillment_created', array( $this, 'heading' ), 10, 3 );
		add_action( 'woocommerce_fulfillment_after_create', array( $this, 'complete_shipped_order' ), 20 );
		add_action( 'woocommerce_fulfillment_after_update', array( $this, 'complete_shipped_order' ), 20 );
		add_filter( 'woocommerce_email_enabled_customer_completed_order', array( $this, 'completed_email_enabled' ), 10, 2 );
		add_filter( 'woocommerce_fulfillment_order_fulfillment_status_text', array( $this, 'status_text' ), 10, 2 );
	}

	/**
	 * Serves this plugin's copy of a fulfillment email template when WooCommerce would use its own.
	 *
	 * @param mixed $template      Located template path.
	 * @param mixed $template_name Template name, for example emails/email-fulfillment-details.php.
	 * @return mixed
	 */
	public function template( $template, $template_name ) {
		if ( ! is_string( $template ) || ! is_string( $template_name ) || ! isset( self::TEMPLATES[ $template_name ] ) || ! function_exists( 'WC' ) ) {
			return $template;
		}
		if ( ! str_starts_with( wp_normalize_path( $template ), wp_normalize_path( WC()->plugin_path() ) . '/templates/' ) ) {
			return $template; // A theme override.
		}
		$own = Plugin::instance()->dir() . '/templates/' . $template_name;
		return is_readable( $own ) ? $own : $template;
	}

	/**
	 * Which package of the order a "Fulfillment created" email announces: 'all' (the order ships in
	 * one package), 'part' (items are still to ship) or 'rest' (the last of several packages).
	 *
	 * @param WC_Order $order Order, with its fulfillment status already updated by WooCommerce.
	 * @return string
	 */
	public static function shipment( WC_Order $order ): string {
		if ( ! class_exists( FulfillmentUtils::class ) ) {
			return 'all';
		}
		$status = FulfillmentUtils::get_order_fulfillment_status( $order );
		if ( 'partially_fulfilled' === $status ) {
			return 'part';
		}
		if ( 'fulfilled' === $status && count( self::shipped( $order ) ) > 1 ) {
			return 'rest';
		}
		return 'all';
	}

	/**
	 * The default subject for the customer's "Fulfillment created" email.
	 *
	 * @param mixed $subject Subject after WooCommerce's placeholders.
	 * @param mixed $order   Order.
	 * @param mixed $email   Email object.
	 * @return mixed
	 */
	public function subject( $subject, $order, $email ) {
		if ( ! $order instanceof WC_Order || ! $email instanceof WC_Email || self::set_in_settings( $email, 'subject' ) ) {
			return $subject;
		}
		$texts = array(
			/* translators: placeholders {site_title} and {order_number} stay as written */
			'all'  => __( 'Your {site_title} order {order_number} has shipped', 'cigarholic' ),
			/* translators: placeholders {site_title} and {order_number} stay as written */
			'part' => __( 'Part of your {site_title} order {order_number} has shipped', 'cigarholic' ),
			/* translators: placeholders {site_title} and {order_number} stay as written */
			'rest' => __( 'The rest of your {site_title} order {order_number} has shipped', 'cigarholic' ),
		);
		return $email->format_string( $texts[ self::shipment( $order ) ] );
	}

	/**
	 * The default heading for the customer's "Fulfillment created" email.
	 *
	 * @param mixed $heading Heading after WooCommerce's placeholders.
	 * @param mixed $order   Order.
	 * @param mixed $email   Email object.
	 * @return mixed
	 */
	public function heading( $heading, $order, $email ) {
		if ( ! $order instanceof WC_Order || ! $email instanceof WC_Email || self::set_in_settings( $email, 'heading' ) ) {
			return $heading;
		}
		$texts = array(
			'all'  => __( 'Your order is on its way', 'cigarholic' ),
			'part' => __( 'Part of your order is on its way', 'cigarholic' ),
			'rest' => __( 'The rest of your order is on its way', 'cigarholic' ),
		);
		return $texts[ self::shipment( $order ) ];
	}

	/**
	 * The first paragraph of the "Fulfillment created" email.
	 *
	 * @param WC_Order $order       Order.
	 * @param mixed    $fulfillment The shipment.
	 * @return string Plain text.
	 */
	public static function introduction( WC_Order $order, $fulfillment ): string {
		$texts = array(
			'all'  => __( 'Good news: your order has left our US warehouse.', 'cigarholic' ),
			'part' => __( 'Good news: part of your order has left our US warehouse. The rest follows in a separate package, and we email you again when it ships.', 'cigarholic' ),
			'rest' => __( 'Good news: the rest of your order has left our US warehouse.', 'cigarholic' ),
		);
		$text  = $texts[ self::shipment( $order ) ];
		if ( $fulfillment instanceof Fulfillment && null !== $fulfillment->get_tracking_number() ) {
			$text .= ' ' . __( 'You can follow the package with the tracking details below.', 'cigarholic' );
		}
		return $text;
	}

	/**
	 * Tracking details of a shipment for the emails: carrier name, tracking number and link, each ''
	 * when unknown. The link is kept only when it is an http(s) URL.
	 *
	 * @param mixed $fulfillment The shipment.
	 * @return array{carrier: string, number: string, url: string}
	 */
	public static function details( $fulfillment ): array {
		$details = array(
			'carrier' => '',
			'number'  => '',
			'url'     => '',
		);
		if ( ! $fulfillment instanceof Fulfillment ) {
			return $details;
		}
		$details['number'] = trim( (string) $fulfillment->get_tracking_number() );
		$url               = trim( (string) $fulfillment->get_tracking_url() );
		$details['url']    = '' !== $url && in_array( wp_parse_url( $url, PHP_URL_SCHEME ), array( 'http', 'https' ), true ) ? $url : '';

		$key = (string) $fulfillment->get_shipment_provider();
		if ( 'other' === $key || '' === $key ) {
			$details['carrier'] = trim( (string) $fulfillment->get_meta( '_provider_name', true ) );
		} elseif ( class_exists( FulfillmentUtils::class ) ) {
			$providers          = FulfillmentUtils::get_shipping_providers();
			$details['carrier'] = isset( $providers[ $key ] ) ? $providers[ $key ]->get_name() : $key;
		} else {
			$details['carrier'] = $key;
		}
		return $details;
	}

	/**
	 * Where a customer with an account can follow the order (the order's page in My Account); '' for a
	 * guest order, which has no account to look in.
	 *
	 * @param WC_Order $order Order.
	 * @return string
	 */
	public static function account_url( WC_Order $order ): string {
		return $order->get_customer_id() > 0 ? $order->get_view_order_url() : '';
	}

	/**
	 * Completes a processing order once every item has shipped, so the manager ships an order with one
	 * action. Runs after WooCommerce recorded the order's fulfillment status; does nothing for an order
	 * on hold, already completed or with items still to ship, so repeated or concurrent saves are safe.
	 *
	 * @param mixed $fulfillment The fulfillment just created or updated.
	 * @return void
	 */
	public function complete_shipped_order( $fulfillment ): void {
		if ( ! $fulfillment instanceof Fulfillment || ! $fulfillment->get_is_fulfilled() ) {
			return;
		}
		$order = $fulfillment->get_order();
		if ( ! $order instanceof WC_Order || ! $order->has_status( 'processing' ) || 'fulfilled' !== FulfillmentUtils::get_order_fulfillment_status( $order ) ) {
			return;
		}
		$order->update_status( 'completed', __( 'Every item has shipped, so the order is complete. The customer had the shipping email; the "Completed order" email is not sent for shipped orders.', 'cigarholic' ) );
	}

	/**
	 * Keeps WooCommerce's "Completed order" email from following the shipping email: an order whose
	 * items have all shipped through fulfillments gets none.
	 *
	 * @param mixed $enabled Whether the email is on.
	 * @param mixed $order   Order the email is about, if any.
	 * @return mixed
	 */
	public function completed_email_enabled( $enabled, $order ) {
		if ( $enabled && $order instanceof WC_Order && class_exists( FulfillmentUtils::class ) && 'fulfilled' === FulfillmentUtils::get_order_fulfillment_status( $order ) ) {
			return false;
		}
		return $enabled;
	}

	/**
	 * The sentence WooCommerce adds after the order status on the customer's order page.
	 *
	 * @param mixed $text   WooCommerce's sentence.
	 * @param mixed $status Order fulfillment status.
	 * @return mixed
	 */
	public function status_text( $text, $status ) {
		if ( 'fulfilled' === $status ) {
			return esc_html__( 'It has shipped.', 'cigarholic' );
		}
		if ( 'partially_fulfilled' === $status ) {
			return esc_html__( 'Part of it has shipped.', 'cigarholic' );
		}
		return is_string( $status ) && in_array( $status, array( 'unfulfilled', 'no_fulfillments' ), true ) ? '' : $text;
	}

	/**
	 * Whether the manager set this text in the email's settings (or typed it in the settings preview). Read
	 * from the saved option, because WooCommerce keeps the first default it computes in the email object.
	 *
	 * @param WC_Email $email Email object.
	 * @param string   $key   Setting: subject or heading.
	 * @return bool
	 */
	private static function set_in_settings( WC_Email $email, string $key ): bool {
		$settings = get_option( $email->get_option_key(), array() );
		$value    = is_array( $settings ) && isset( $settings[ $key ] ) && is_string( $settings[ $key ] ) ? $settings[ $key ] : '';
		/** This filter is documented in woocommerce/templates/emails/email-styles.php */
		if ( apply_filters( 'woocommerce_is_email_preview', false ) ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce's own filter.
			$typed = get_transient( $email->plugin_id . $email->id . '_' . $key );
			if ( is_string( $typed ) ) {
				$value = $typed;
			}
		}
		return '' !== trim( $value );
	}

	/**
	 * The order's shipped (fulfilled) fulfillments.
	 *
	 * @param WC_Order $order Order.
	 * @return list<Fulfillment>
	 */
	private static function shipped( WC_Order $order ): array {
		if ( $order->get_id() <= 0 ) {
			return array();
		}
		try {
			$store        = wc_get_container()->get( FulfillmentsDataStore::class );
			$fulfillments = $store instanceof FulfillmentsDataStoreInterface ? $store->read_fulfillments( WC_Order::class, (string) $order->get_id() ) : array();
		} catch ( \Throwable $e ) {
			return array();
		}
		return array_values(
			array_filter(
				$fulfillments,
				static fn( $fulfillment ): bool => $fulfillment instanceof Fulfillment && $fulfillment->get_is_fulfilled()
			)
		);
	}
}
