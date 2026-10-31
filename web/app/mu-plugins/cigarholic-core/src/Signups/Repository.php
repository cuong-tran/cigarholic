<?php
/**
 * Signup storage.
 *
 * @package Cigarholic
 */

namespace Cigarholic\Core\Signups;

/**
 * The {prefix}cigarholic_signups table: one row per email, deduplicated, with the source of the
 * signup (coming-soon page, footer, ...). Minimal personal data: email, source, timestamps. The
 * table is our own, so direct queries are the right tool (no WooCommerce tables involved).
 *
 * Feature IDs: CNT-04, MKT-01.
 */
final class Repository {

	public const SCHEMA_VERSION = '1';
	public const OPTION         = 'cigarholic_signups_schema';

	/**
	 * Table name with prefix.
	 *
	 * @return string
	 */
	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'cigarholic_signups';
	}

	/**
	 * Creates or updates the table when the schema version changed. Cheap option check otherwise.
	 *
	 * @return void
	 */
	public static function install(): void {
		if ( get_option( self::OPTION ) === self::SCHEMA_VERSION ) {
			return;
		}
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table   = self::table();
		$charset = $wpdb->get_charset_collate();
		dbDelta(
			"CREATE TABLE {$table} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				email varchar(190) NOT NULL,
				source varchar(50) NOT NULL DEFAULT '',
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				synced_at datetime NULL DEFAULT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY email (email),
				KEY source (source),
				KEY created_at (created_at)
			) {$charset};"
		);
		update_option( self::OPTION, self::SCHEMA_VERSION, true );
	}

	/**
	 * Adds an email, or refreshes the existing row. Idempotent.
	 *
	 * @param string $email  Valid email (already validated).
	 * @param string $source Source key, for example "coming-soon".
	 * @return bool True when the email was new.
	 */
	public static function subscribe( string $email, string $source ): bool {
		global $wpdb;
		$email = strtolower( trim( $email ) );
		$now   = current_time( 'mysql', true );
		$table = self::table();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- own table.
		$existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE email = %s", $email ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from prefix.
		if ( $existing ) {
			$wpdb->update( $table, array( 'updated_at' => $now ), array( 'id' => (int) $existing ), array( '%s' ), array( '%d' ) );
			return false;
		}
		$wpdb->insert(
			$table,
			array(
				'email'      => $email,
				'source'     => $source,
				'created_at' => $now,
				'updated_at' => $now,
			),
			array( '%s', '%s', '%s', '%s' )
		);
		// phpcs:enable
		return true;
	}

	/**
	 * Number of signups.
	 *
	 * @return int
	 */
	public static function count(): int {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- own table.
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
	}

	/**
	 * Rows, newest first.
	 *
	 * @param int $limit  Maximum rows (0 = all).
	 * @param int $offset Offset.
	 * @return list<array{id: int, email: string, source: string, created_at: string, synced_at: string|null}>
	 */
	public static function all( int $limit = 0, int $offset = 0 ): array {
		global $wpdb;
		$table = self::table();
		$sql   = "SELECT id, email, source, created_at, synced_at FROM {$table} ORDER BY created_at DESC, id DESC";
		if ( $limit > 0 ) {
			$sql = $wpdb->prepare( $sql . ' LIMIT %d OFFSET %d', $limit, $offset ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql built from the table name.
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- own table.
		$rows = $wpdb->get_results( $sql, ARRAY_A );
		$out  = array();
		foreach ( (array) $rows as $row ) {
			$out[] = array(
				'id'         => (int) $row['id'],
				'email'      => (string) $row['email'],
				'source'     => (string) $row['source'],
				'created_at' => (string) $row['created_at'],
				'synced_at'  => null === $row['synced_at'] ? null : (string) $row['synced_at'],
			);
		}
		return $out;
	}

	/**
	 * Deletes an email (privacy requests).
	 *
	 * @param string $email Email.
	 * @return bool Whether a row was deleted.
	 */
	public static function delete( string $email ): bool {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- own table.
		return (bool) $wpdb->delete( self::table(), array( 'email' => strtolower( trim( $email ) ) ), array( '%s' ) );
	}
}
