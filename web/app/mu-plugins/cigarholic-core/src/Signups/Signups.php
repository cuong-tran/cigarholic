<?php
/**
 * Coming-soon and newsletter signups.
 *
 * @package Cigarholic
 */

namespace Cigarholic\Core\Signups;

use Cigarholic\Core\Feature;
use Cigarholic\Core\Logger;
use Cigarholic\Core\Plugin;
use Cigarholic\Core\Security\Turnstile;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Email capture while the store is coming soon, and the footer newsletter form later: the
 * `cigarholic/signup-form` block, a REST endpoint used by the block's script, a plain form POST
 * fallback for visitors without JavaScript, Turnstile + honeypot + rate limit against abuse,
 * deduplicated storage in our own table, CSV export for the manager (admin page) and for agents
 * (`wp cigarholic signups export`). Emails are synced to the marketing platform once D-04 is decided.
 *
 * Feature IDs: CNT-04 (coming soon), MKT-01 (signup collection), D-04.
 */
final class Signups implements Feature {

	public const BLOCK          = 'cigarholic/signup-form';
	public const REST_NAMESPACE = 'cigarholic/v1';
	public const ACTION         = 'cigarholic_signup';
	public const EXPORT_ACTION  = 'cigarholic_signups_export';
	public const HONEYPOT       = 'website_url';
	public const RATE_LIMIT     = 10;
	public const DEFAULT_SOURCE = 'coming-soon';

	/**
	 * Feature ID.
	 *
	 * @return string
	 */
	public static function id(): string {
		return 'CNT-04';
	}

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'init' ) );
		add_action( 'rest_api_init', array( $this, 'register_rest_route' ) );
		add_action( 'admin_post_nopriv_' . self::ACTION, array( $this, 'handle_form_post' ) );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle_form_post' ) );
		add_action( 'admin_post_' . self::EXPORT_ACTION, array( $this, 'handle_export' ) );
		add_action( 'admin_menu', array( $this, 'add_admin_page' ) );
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'cigarholic signups', Cli::class );
		}
	}

	/**
	 * Table, block and script registration.
	 *
	 * @return void
	 */
	public function init(): void {
		Repository::install();
		Turnstile::register_script();
		register_block_type( Plugin::instance()->dir() . '/blocks/signup-form' );
	}

	/**
	 * POST /wp-json/cigarholic/v1/signups
	 *
	 * @return void
	 */
	public function register_rest_route(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			'/signups',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_rest' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'email'          => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_email',
					),
					'source'         => array(
						'type'              => 'string',
						'default'           => self::DEFAULT_SOURCE,
						'sanitize_callback' => 'sanitize_key',
					),
					Turnstile::FIELD => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
					self::HONEYPOT   => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * REST handler.
	 *
	 * @param WP_REST_Request<array<string, mixed>> $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_rest( WP_REST_Request $request ): WP_REST_Response|WP_Error { // phpcs:ignore Squiz.Commenting.FunctionComment.IncorrectTypeHint -- generic annotation for PHPStan.
		$result = $this->subscribe(
			(string) $request->get_param( 'email' ),
			(string) $request->get_param( 'source' ),
			(string) $request->get_param( Turnstile::FIELD ),
			(string) $request->get_param( self::HONEYPOT )
		);
		if ( is_wp_error( $result ) ) {
			$result->add_data( array( 'status' => 'rate_limited' === $result->get_error_code() ? 429 : 400 ) );
			return $result;
		}
		return new WP_REST_Response(
			array(
				'ok'      => true,
				'message' => self::success_message(),
			),
			200
		);
	}

	/**
	 * Plain form POST (no JavaScript): subscribe and redirect back with a status.
	 *
	 * @return void
	 */
	public function handle_form_post(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- public form on cached pages; Turnstile, honeypot and the rate limit protect it.
		$email    = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$source   = isset( $_POST['source'] ) ? sanitize_key( wp_unslash( $_POST['source'] ) ) : self::DEFAULT_SOURCE;
		$token    = isset( $_POST[ Turnstile::FIELD ] ) ? sanitize_text_field( wp_unslash( $_POST[ Turnstile::FIELD ] ) ) : '';
		$honeypot = isset( $_POST[ self::HONEYPOT ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::HONEYPOT ] ) ) : '';
		// phpcs:enable

		$result = $this->subscribe( $email, $source, $token, $honeypot );
		$status = is_wp_error( $result ) ? $result->get_error_code() : 'ok';

		$back = wp_get_referer();
		if ( ! $back ) {
			$back = home_url( '/' );
		}
		wp_safe_redirect( add_query_arg( 'signup', rawurlencode( $status ), $back ) . '#cigarholic-signup' );
		exit;
	}

	/**
	 * Validates, protects and stores one signup. Idempotent for repeated emails.
	 *
	 * @param string $email    Email.
	 * @param string $source   Source key.
	 * @param string $token    Turnstile token.
	 * @param string $honeypot Honeypot field value (must be empty).
	 * @return true|WP_Error
	 */
	public function subscribe( string $email, string $source, string $token, string $honeypot ): true|WP_Error {
		if ( '' !== $honeypot ) {
			// Bots fill every field; pretend it worked.
			return true;
		}
		if ( ! is_email( $email ) ) {
			return new WP_Error( 'invalid_email', __( 'Please enter a valid email address.', 'cigarholic' ) );
		}
		$ip = self::client_ip();
		if ( ! $this->within_rate_limit( $ip ) ) {
			return new WP_Error( 'rate_limited', __( 'Too many attempts. Please try again in a minute.', 'cigarholic' ) );
		}
		if ( ! Turnstile::verify( $token, $ip, 'signup' ) ) {
			return new WP_Error( 'verification_failed', __( 'We could not verify that you are human. Please try again.', 'cigarholic' ) );
		}
		$source = '' === $source ? self::DEFAULT_SOURCE : substr( $source, 0, 50 );
		$is_new = Repository::subscribe( $email, $source );
		Logger::info( $is_new ? 'New signup' : 'Repeated signup', array( 'source' => $source ) );
		return true;
	}

	/**
	 * At most RATE_LIMIT attempts per IP per minute (transient, so it works without an object cache).
	 *
	 * @param string $ip Visitor IP.
	 * @return bool
	 */
	private function within_rate_limit( string $ip ): bool {
		if ( '' === $ip ) {
			return true;
		}
		$key   = 'cigarholic_signup_' . md5( $ip );
		$count = (int) get_transient( $key );
		if ( $count >= self::RATE_LIMIT ) {
			return false;
		}
		set_transient( $key, $count + 1, MINUTE_IN_SECONDS );
		return true;
	}

	/**
	 * Visitor IP as seen by the server (Cloudflare's header when proxied).
	 *
	 * @return string
	 */
	public static function client_ip(): string {
		foreach ( array( 'HTTP_CF_CONNECTING_IP', 'REMOTE_ADDR' ) as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$ip = sanitize_text_field( wp_unslash( (string) $_SERVER[ $key ] ) );
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					return $ip;
				}
			}
		}
		return '';
	}

	/**
	 * Message shown after a successful signup.
	 *
	 * @return string
	 */
	public static function success_message(): string {
		return __( 'Thank you. We will email you when the store opens.', 'cigarholic' );
	}

	/**
	 * Message for a status code carried in the ?signup= query parameter.
	 *
	 * @param string $status Status code.
	 * @return string
	 */
	public static function status_message( string $status ): string {
		switch ( $status ) {
			case 'ok':
				return self::success_message();
			case 'invalid_email':
				return __( 'Please enter a valid email address.', 'cigarholic' );
			case 'rate_limited':
				return __( 'Too many attempts. Please try again in a minute.', 'cigarholic' );
			case 'verification_failed':
				return __( 'We could not verify that you are human. Please try again.', 'cigarholic' );
			default:
				return '';
		}
	}

	/**
	 * WooCommerce > Signups: count, latest rows, CSV download. Shop managers can use it.
	 *
	 * @return void
	 */
	public function add_admin_page(): void {
		add_submenu_page(
			'woocommerce',
			__( 'Signups', 'cigarholic' ),
			__( 'Signups', 'cigarholic' ),
			'manage_woocommerce',
			'cigarholic-signups',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Admin page markup.
	 *
	 * @return void
	 */
	public function render_admin_page(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to view signups.', 'cigarholic' ) );
		}
		$count = Repository::count();
		$rows  = Repository::all( 50 );
		$url   = wp_nonce_url( admin_url( 'admin-post.php?action=' . self::EXPORT_ACTION ), self::EXPORT_ACTION );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Email signups', 'cigarholic' ); ?></h1>
			<p>
				<?php
				printf(
					/* translators: %s: number of signups */
					esc_html( _n( '%s signup so far.', '%s signups so far.', $count, 'cigarholic' ) ),
					esc_html( number_format_i18n( $count ) )
				);
				?>
				<a class="button button-primary" href="<?php echo esc_url( $url ); ?>"><?php esc_html_e( 'Download CSV', 'cigarholic' ); ?></a>
			</p>
			<p class="description"><?php esc_html_e( 'Collected by the coming-soon page and the newsletter form. Personal data: handle according to the privacy policy; export only into the email platform.', 'cigarholic' ); ?></p>
			<table class="widefat striped">
				<thead><tr><th><?php esc_html_e( 'Email', 'cigarholic' ); ?></th><th><?php esc_html_e( 'Source', 'cigarholic' ); ?></th><th><?php esc_html_e( 'Signed up (UTC)', 'cigarholic' ); ?></th></tr></thead>
				<tbody>
				<?php if ( array() === $rows ) : ?>
					<tr><td colspan="3"><?php esc_html_e( 'No signups yet.', 'cigarholic' ); ?></td></tr>
				<?php endif; ?>
				<?php foreach ( $rows as $row ) : ?>
					<tr><td><?php echo esc_html( $row['email'] ); ?></td><td><?php echo esc_html( $row['source'] ); ?></td><td><?php echo esc_html( $row['created_at'] ); ?></td></tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Streams every signup as CSV (capability + nonce checked).
	 *
	 * @return void
	 */
	public function handle_export(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! check_admin_referer( self::EXPORT_ACTION ) ) {
			wp_die( esc_html__( 'You do not have permission to export signups.', 'cigarholic' ) );
		}
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="cigarholic-signups-' . gmdate( 'Y-m-d' ) . '.csv"' );
		echo self::csv( Repository::all() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV file body, escaped per cell in csv().
		exit;
	}

	/**
	 * CSV text for rows (RFC 4180 quoting through fputcsv; formula injection neutralised).
	 *
	 * @param list<array{id: int, email: string, source: string, created_at: string, synced_at: string|null}> $rows Rows.
	 * @return string
	 */
	public static function csv( array $rows ): string {
		$handle = fopen( 'php://temp', 'r+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- in-memory stream.
		if ( false === $handle ) {
			return '';
		}
		fputcsv( $handle, array( 'email', 'source', 'created_at_utc', 'synced_at_utc' ), ',', '"', '\\' );
		foreach ( $rows as $row ) {
			fputcsv(
				$handle,
				array_map(
					static fn( ?string $value ): string => preg_match( '/^[=+\-@\t\r]/', (string) $value ) ? "'" . $value : (string) $value,
					array( $row['email'], $row['source'], $row['created_at'], $row['synced_at'] )
				),
				',',
				'"',
				'\\'
			);
		}
		rewind( $handle );
		$csv = (string) stream_get_contents( $handle );
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- in-memory stream.
		return $csv;
	}
}
