<?php
/**
 * WP-CLI: signups.
 *
 * @package Cigarholic
 */

namespace Cigarholic\Core\Signups;

use WP_CLI;

/**
 * `wp cigarholic signups` commands for agents and for syncing to the email platform (D-04).
 *
 * Feature IDs: CNT-04, MKT-01.
 */
final class Cli {

	/**
	 * Prints the number of signups.
	 *
	 * ## EXAMPLES
	 *
	 *     wp cigarholic signups count
	 *
	 * @return void
	 */
	public function count(): void {
		WP_CLI::line( (string) Repository::count() );
	}

	/**
	 * Lists signups, newest first.
	 *
	 * ## OPTIONS
	 *
	 * [--limit=<n>]
	 * : Maximum rows.
	 * ---
	 * default: 50
	 * ---
	 *
	 * [--format=<format>]
	 * : table, json, csv or yaml.
	 * ---
	 * default: table
	 * ---
	 *
	 * @param array<int, string>    $args       Positional arguments (none).
	 * @param array<string, string> $assoc_args Options.
	 * @return void
	 */
	public function list( array $args, array $assoc_args ): void {
		$limit = (int) ( $assoc_args['limit'] ?? 50 );
		WP_CLI\Utils\format_items( $assoc_args['format'] ?? 'table', Repository::all( $limit ), array( 'id', 'email', 'source', 'created_at', 'synced_at' ) );
	}

	/**
	 * Writes every signup as CSV to a file or to standard output.
	 *
	 * ## OPTIONS
	 *
	 * [--file=<path>]
	 * : Output file; prints to stdout when omitted.
	 *
	 * ## EXAMPLES
	 *
	 *     wp cigarholic signups export --file=/tmp/signups.csv
	 *
	 * @param array<int, string>    $args       Positional arguments (none).
	 * @param array<string, string> $assoc_args Options.
	 * @return void
	 */
	public function export( array $args, array $assoc_args ): void {
		$csv = Signups::csv( Repository::all() );
		if ( empty( $assoc_args['file'] ) ) {
			WP_CLI::line( rtrim( $csv, "\n" ) );
			return;
		}
		$file = (string) $assoc_args['file'];
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- CLI export to a path the operator chose.
		if ( false === file_put_contents( $file, $csv ) ) {
			WP_CLI::error( sprintf( 'Could not write %s', $file ) );
		}
		WP_CLI::success( sprintf( 'Wrote %d signup(s) to %s', Repository::count(), $file ) );
	}

	/**
	 * Deletes one email (privacy request).
	 *
	 * ## OPTIONS
	 *
	 * <email>
	 * : The email address to delete.
	 *
	 * @param array<int, string> $args Positional arguments.
	 * @return void
	 */
	public function delete( array $args ): void {
		$email = $args[0] ?? '';
		if ( Repository::delete( $email ) ) {
			WP_CLI::success( 'Deleted.' );
			return;
		}
		WP_CLI::warning( 'No such signup.' );
	}
}
