<?php
/**
 * Minimal WP-CLI stubs for PHPStan (php-stubs/wp-cli-stubs conflicts with wordpress-stubs 7.x).
 * Only what cigarholic-core calls; extend when a new WP_CLI method is used.
 *
 * @package Cigarholic
 */

// phpcs:ignoreFile

namespace {
	/**
	 * WP-CLI entry points.
	 */
	class WP_CLI {
		/**
		 * @param string                 $name
		 * @param callable|object|string $callable
		 * @param array<string, mixed>   $args
		 */
		public static function add_command( string $name, $callable, array $args = array() ): bool {}
		public static function line( string $message = '' ): void {}
		public static function log( string $message ): void {}
		public static function warning( string $message ): void {}
		public static function success( string $message ): void {}
		/**
		 * @return never
		 */
		public static function error( string $message, bool|int $exit = true ): void {}
	}
}

namespace WP_CLI\Utils {
	/**
	 * @param array<int, mixed>|\Traversable<mixed> $items
	 * @param array<int, string>|string              $fields
	 */
	function format_items( string $format, $items, $fields ): void {}
	/**
	 * @param array<string, mixed> $assoc_args
	 * @param mixed                $default
	 * @return mixed
	 */
	function get_flag_value( array $assoc_args, string $flag, $default = null ) {}
}
