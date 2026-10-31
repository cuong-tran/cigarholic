<?php
/**
 * Provisioning step: shipping classes, zones and methods (SHP-01, SHP-02).
 *
 * Defaults D-05 (humidors ship free, accessories at a flat rate, an oversized class for cabinet
 * humidors) and D-06 (contiguous US only at launch). The accessory rate is a placeholder until the
 * owner sets prices and shipping rules (L-07). Alaska, Hawaii and the territories get a zone with
 * no method, so checkout tells those customers shipping is not available instead of silently
 * charging the mainland rate. Everything goes through WC_Shipping_Zone(s); zones are matched by name.
 *
 * Feature IDs: FND-03, SHP-01, SHP-02.
 *
 * @package Cigarholic
 */

use Cigarholic\Core\Provision\Context;

return static function ( Context $ctx ): void {
	if ( ! function_exists( 'WC' ) || ! class_exists( 'WC_Shipping_Zones' ) ) {
		$ctx->log( 'skip: WooCommerce is not active' );
		return;
	}

	// Shipping classes (product_shipping_class terms).
	$classes = array(
		'humidor'           => $ctx->term( 'product_shipping_class', 'humidor', 'Humidors', array( 'description' => 'Desktop and travel humidors, cigar boxes: shipping priced into the product.' ) ),
		'oversized-humidor' => $ctx->term( 'product_shipping_class', 'oversized-humidor', 'Cabinet humidors (oversized)', array( 'description' => 'Cabinet humidors and other oversized pieces: freight or oversized parcel, priced into the product.' ) ),
		'accessory'         => $ctx->term( 'product_shipping_class', 'accessory', 'Accessories', array( 'description' => 'Humidification, hygrometers, trays and other small items: flat rate.' ) ),
	);

	// Placeholder rates until L-07 (prices, shipping rates and free-shipping rule).
	$class_costs = array(
		'humidor'           => '0',
		'oversized-humidor' => '0',
		'accessory'         => '9.95',
	);

	$lower_48 = array( 'AL', 'AZ', 'AR', 'CA', 'CO', 'CT', 'DE', 'DC', 'FL', 'GA', 'ID', 'IL', 'IN', 'IA', 'KS', 'KY', 'LA', 'ME', 'MD', 'MA', 'MI', 'MN', 'MS', 'MO', 'MT', 'NE', 'NV', 'NH', 'NJ', 'NM', 'NY', 'NC', 'ND', 'OH', 'OK', 'OR', 'PA', 'RI', 'SC', 'SD', 'TN', 'TX', 'UT', 'VT', 'VA', 'WA', 'WV', 'WI', 'WY' );
	$remote   = array( 'AK', 'HI', 'AS', 'GU', 'MP', 'PR', 'UM', 'VI' );

	/**
	 * Finds a zone by name.
	 *
	 * @param string $name Zone name.
	 * @return WC_Shipping_Zone|null
	 */
	$find_zone = static function ( string $name ): ?WC_Shipping_Zone {
		foreach ( WC_Shipping_Zones::get_zones() as $data ) {
			if ( isset( $data['zone_name'], $data['zone_id'] ) && $data['zone_name'] === $name ) {
				$zone = WC_Shipping_Zones::get_zone( (int) $data['zone_id'] );
				return $zone instanceof WC_Shipping_Zone ? $zone : null;
			}
		}
		return null;
	};

	/**
	 * Ensures a zone exists with exactly these US states and this order.
	 *
	 * @param string       $name   Zone name.
	 * @param int          $order  Zone order (lower matches first).
	 * @param list<string> $states Two-letter US state or territory codes.
	 * @return WC_Shipping_Zone|null The zone, null in a dry run when it would be created.
	 */
	$ensure_zone = static function ( string $name, int $order, array $states ) use ( $ctx, $find_zone ): ?WC_Shipping_Zone {
		$wanted = array_map( static fn( string $state ): string => 'state:US:' . $state, $states );
		sort( $wanted, SORT_STRING );

		$zone = $find_zone( $name );
		if ( null === $zone ) {
			$ctx->changed( sprintf( 'shipping zone "%s" (new)', $name ) );
			if ( $ctx->is_dry_run() ) {
				return null;
			}
			$zone = new WC_Shipping_Zone();
			$zone->set_zone_name( $name );
			$zone->set_zone_order( $order );
			$zone->save();
		}

		$current = array();
		foreach ( $zone->get_zone_locations() as $location ) {
			$current[] = $location->type . ':' . $location->code;
		}
		sort( $current, SORT_STRING );

		$dirty = false;
		if ( $current !== $wanted ) {
			$zone->clear_locations();
			foreach ( $states as $state ) {
				$zone->add_location( 'US:' . $state, 'state' );
			}
			$ctx->changed( sprintf( 'shipping zone "%s" locations (%d states)', $name, count( $states ) ) );
			$dirty = true;
		}
		if ( $zone->get_zone_order() !== $order ) {
			$zone->set_zone_order( $order );
			$ctx->changed( sprintf( 'shipping zone "%s" order %d', $name, $order ) );
			$dirty = true;
		}
		if ( $dirty ) {
			if ( ! $ctx->is_dry_run() ) {
				$zone->save();
			}
		} else {
			$ctx->log( sprintf( 'ok: shipping zone "%s"', $name ) );
		}
		return $zone;
	};

	// Zone 1: the 48 contiguous states and DC, one flat-rate method priced per shipping class.
	$zone = $ensure_zone( 'Contiguous US', 0, $lower_48 );
	if ( null !== $zone ) {
		$flat_rate = null;
		foreach ( $zone->get_shipping_methods() as $method ) {
			if ( 'flat_rate' === $method->id ) {
				$flat_rate = $method;
				break;
			}
		}
		if ( null === $flat_rate ) {
			$ctx->changed( 'flat rate method on "Contiguous US" (new)' );
			if ( ! $ctx->is_dry_run() ) {
				$instance_id = $zone->add_shipping_method( 'flat_rate' );
				$flat_rate   = $instance_id > 0 ? WC_Shipping_Zones::get_shipping_method( $instance_id ) : null;
				if ( ! $flat_rate instanceof WC_Shipping_Method ) {
					throw new RuntimeException( 'Could not add the flat rate method to the "Contiguous US" zone.' );
				}
			}
		}

		if ( $flat_rate instanceof WC_Shipping_Method ) {
			// Per order: the cart pays the most expensive class present, so a humidor plus an
			// accessory pays the accessory rate once. Costs are placeholders until L-07.
			$wanted = array(
				'title'         => 'Flat rate',
				'tax_status'    => 'taxable',
				'cost'          => '0',
				'no_class_cost' => '0',
				'type'          => 'order',
			);
			foreach ( $class_costs as $slug => $cost ) {
				if ( $classes[ $slug ] > 0 ) {
					$wanted[ 'class_cost_' . $classes[ $slug ] ] = $cost;
				}
			}
			$option_key = $flat_rate->get_instance_option_key();
			$current    = get_option( $option_key, array() );
			$current    = is_array( $current ) ? $current : array();
			if ( array_intersect_key( $current, $wanted ) === $wanted ) {
				$ctx->log( 'ok: flat rate settings on "Contiguous US"' );
			} else {
				if ( ! $ctx->is_dry_run() ) {
					update_option( $option_key, array_merge( $current, $wanted ) );
				}
				$ctx->changed( sprintf( 'flat rate settings on "Contiguous US" (%s)', $option_key ) );
			}
		}
	}

	// Zone 2: Alaska, Hawaii and US territories, no method on purpose (D-06).
	$ensure_zone( 'Alaska, Hawaii and US territories', 1, $remote );

	// Shipping options: rates from the shipping address, calculator on the cart page.
	$ctx->option( 'woocommerce_ship_to_destination', 'shipping' );
	$ctx->option( 'woocommerce_enable_shipping_calc', 'yes' );
	$ctx->option( 'woocommerce_shipping_cost_requires_address', 'no' );
	$ctx->option( 'woocommerce_shipping_debug_mode', 'no' );
};
