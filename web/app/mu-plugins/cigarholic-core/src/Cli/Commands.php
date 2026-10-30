<?php
/**
 * WP-CLI commands.
 *
 * @package Cigarholic
 */

namespace Cigarholic\Core\Cli;

use Cigarholic\Core\Plugin;
use Cigarholic\Core\Provision\Runner;
use WP_CLI;

/**
 * The `wp cigarholic` command namespace.
 *
 * Feature IDs: FND-05 (namespace, features), FND-03 (provision).
 */
final class Commands {

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin The plugin.
	 */
	public function __construct( private readonly Plugin $plugin ) {}

	/**
	 * Registers the namespace with WP-CLI.
	 *
	 * @param Plugin $plugin The plugin.
	 * @return void
	 */
	public static function register( Plugin $plugin ): void {
		WP_CLI::add_command( 'cigarholic', new self( $plugin ) );
	}

	/**
	 * Prints the cigarholic-core version.
	 *
	 * ## EXAMPLES
	 *
	 *     wp cigarholic version
	 *
	 * @return void
	 */
	public function version(): void {
		WP_CLI::line( Plugin::VERSION );
	}

	/**
	 * Lists every feature with its toggle state.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : table, json, csv or yaml.
	 * ---
	 * default: table
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp cigarholic features
	 *
	 * @param array<int, string>    $args       Positional arguments (none).
	 * @param array<string, string> $assoc_args Options.
	 * @return void
	 */
	public function features( array $args, array $assoc_args ): void {
		$format = $assoc_args['format'] ?? 'table';
		WP_CLI\Utils\format_items( $format, $this->plugin->features()->all(), array( 'slug', 'id', 'default', 'enabled', 'class' ) );
	}

	/**
	 * Applies settings as code: every step in provision/steps, in order, idempotently.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Report what would change without changing anything.
	 *
	 * [--step=<name>]
	 * : Run a single step (file name without .php).
	 *
	 * ## EXAMPLES
	 *
	 *     wp cigarholic provision
	 *     wp cigarholic provision --dry-run
	 *     wp cigarholic provision --step=00-core
	 *
	 * @param array<int, string>    $args       Positional arguments (none).
	 * @param array<string, string> $assoc_args Options.
	 * @return void
	 */
	public function provision( array $args, array $assoc_args ): void {
		$dry_run = (bool) WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false );
		$only    = isset( $assoc_args['step'] ) ? (string) $assoc_args['step'] : null;

		$runner = new Runner(
			$this->plugin->provision_dir(),
			static function ( string $line ): void {
				WP_CLI::log( $line );
			}
		);

		try {
			$changes = $runner->run( $dry_run, $only );
		} catch ( \RuntimeException $e ) {
			WP_CLI::error( $e->getMessage() );
		}

		if ( $dry_run ) {
			WP_CLI::success( sprintf( 'Dry run: %d change(s) would be applied.', $changes ) );
			return;
		}
		WP_CLI::success( sprintf( 'Provisioning complete: %d change(s) applied.', $changes ) );
	}
}
