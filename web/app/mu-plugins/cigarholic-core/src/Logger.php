<?php
/**
 * Logging.
 *
 * @package Cigarholic
 */

namespace Cigarholic\Core;

/**
 * Logs through WooCommerce's logger under the "cigarholic" source (WooCommerce > Status > Logs),
 * falling back to the PHP error log only while WooCommerce is not loaded and WP_DEBUG is on.
 * Never log card data, keys, tokens or personal data beyond what an order needs.
 *
 * Feature ID: FND-05.
 */
final class Logger {

	public const SOURCE = 'cigarholic';

	/**
	 * Logs a message.
	 *
	 * @param string               $level   PSR-3 level: debug, info, notice, warning, error, critical.
	 * @param string               $message Message.
	 * @param array<string, mixed> $context Extra context (no secrets).
	 * @return void
	 */
	public static function log( string $level, string $message, array $context = array() ): void {
		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->log( $level, $message, array_merge( $context, array( 'source' => self::SOURCE ) ) );
			return;
		}
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- fallback before WooCommerce loads.
			error_log( sprintf( '[cigarholic] %s: %s %s', $level, $message, $context ? wp_json_encode( $context ) : '' ) );
		}
	}

	/**
	 * Info-level message.
	 *
	 * @param string               $message Message.
	 * @param array<string, mixed> $context Context.
	 * @return void
	 */
	public static function info( string $message, array $context = array() ): void {
		self::log( 'info', $message, $context );
	}

	/**
	 * Warning-level message.
	 *
	 * @param string               $message Message.
	 * @param array<string, mixed> $context Context.
	 * @return void
	 */
	public static function warning( string $message, array $context = array() ): void {
		self::log( 'warning', $message, $context );
	}

	/**
	 * Error-level message.
	 *
	 * @param string               $message Message.
	 * @param array<string, mixed> $context Context.
	 * @return void
	 */
	public static function error( string $message, array $context = array() ): void {
		self::log( 'error', $message, $context );
	}
}
