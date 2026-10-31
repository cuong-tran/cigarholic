<?php
/**
 * Contact form.
 *
 * @package Cigarholic
 */

namespace Cigarholic\Core\Contact;

use Cigarholic\Core\Feature;
use Cigarholic\Core\Logger;
use Cigarholic\Core\Plugin;
use Cigarholic\Core\Security\Turnstile;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * The `cigarholic/contact-form` block: name, email, optional order number and message, protected by
 * Turnstile, a honeypot and a rate limit, delivered by email to the support inbox with the visitor
 * as Reply-To. Nothing is stored; the message exists only in the support mailbox. The REST endpoint
 * serves the block's script, the admin-post handler serves visitors without JavaScript.
 *
 * Feature IDs: CNT-03 (contact form), D-10 (launch support = contact form → support inbox).
 */
final class ContactForm implements Feature {

	public const BLOCK       = 'cigarholic/contact-form';
	public const OPTION      = 'cigarholic_contact_form';
	public const PAGE        = 'cigarholic-contact-form';
	public const ACTION      = 'cigarholic_contact';
	public const HONEYPOT    = 'company_website';
	public const RATE_LIMIT  = 5;
	public const MAX_MESSAGE = 5000;
	public const DEFAULT_TO  = 'support@cigarholic.com';

	/**
	 * Feature ID.
	 *
	 * @return string
	 */
	public static function id(): string {
		return 'CNT-03';
	}

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'register_block' ) );
		add_action( 'rest_api_init', array( $this, 'register_rest_route' ) );
		add_action( 'admin_post_nopriv_' . self::ACTION, array( $this, 'handle_form_post' ) );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle_form_post' ) );
		add_action( 'admin_menu', array( $this, 'add_admin_page' ) );
		add_action( 'admin_post_' . self::OPTION, array( $this, 'handle_save' ) );
	}

	/**
	 * Block registration.
	 *
	 * @return void
	 */
	public function register_block(): void {
		Turnstile::register_script();
		register_block_type( Plugin::instance()->dir() . '/blocks/contact-form' );
	}

	/**
	 * Settings with defaults. In development the recipient falls back to the admin email so Mailpit
	 * shows the message; on staging and production the support inbox is the default.
	 *
	 * @return array{recipient: string, subject_prefix: string}
	 */
	public static function settings(): array {
		$saved = get_option( self::OPTION, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		$recipient = isset( $saved['recipient'] ) && is_email( (string) $saved['recipient'] ) ? (string) $saved['recipient'] : self::DEFAULT_TO;
		return array(
			'recipient'      => $recipient,
			'subject_prefix' => isset( $saved['subject_prefix'] ) && '' !== $saved['subject_prefix'] ? (string) $saved['subject_prefix'] : '[Cigarholic]',
		);
	}

	/**
	 * POST /wp-json/cigarholic/v1/contact
	 *
	 * @return void
	 */
	public function register_rest_route(): void {
		$text = array(
			'type'              => 'string',
			'default'           => '',
			'sanitize_callback' => 'sanitize_text_field',
		);
		register_rest_route(
			'cigarholic/v1',
			'/contact',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_rest' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'name'           => array_merge( $text, array( 'required' => true ) ),
					'email'          => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_email',
					),
					'order_number'   => $text,
					'message'        => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_textarea_field',
					),
					Turnstile::FIELD => $text,
					self::HONEYPOT   => $text,
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
		$result = $this->send(
			array(
				'name'         => (string) $request->get_param( 'name' ),
				'email'        => (string) $request->get_param( 'email' ),
				'order_number' => (string) $request->get_param( 'order_number' ),
				'message'      => (string) $request->get_param( 'message' ),
			),
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
				'message' => self::status_message( 'ok' ),
			),
			200
		);
	}

	/**
	 * Plain form POST (no JavaScript): send and redirect back with a status.
	 *
	 * @return void
	 */
	public function handle_form_post(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- public form on cached pages; Turnstile, honeypot and the rate limit protect it.
		$fields   = array(
			'name'         => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '',
			'email'        => isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '',
			'order_number' => isset( $_POST['order_number'] ) ? sanitize_text_field( wp_unslash( $_POST['order_number'] ) ) : '',
			'message'      => isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '',
		);
		$token    = isset( $_POST[ Turnstile::FIELD ] ) ? sanitize_text_field( wp_unslash( $_POST[ Turnstile::FIELD ] ) ) : '';
		$honeypot = isset( $_POST[ self::HONEYPOT ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::HONEYPOT ] ) ) : '';
		// phpcs:enable

		$result = $this->send( $fields, $token, $honeypot );
		$status = is_wp_error( $result ) ? $result->get_error_code() : 'ok';
		$back   = wp_get_referer();
		if ( ! $back ) {
			$back = home_url( '/contact/' );
		}
		wp_safe_redirect( add_query_arg( 'contact', rawurlencode( $status ), $back ) . '#cigarholic-contact' );
		exit;
	}

	/**
	 * Validates, protects and emails one message.
	 *
	 * @param array{name: string, email: string, order_number: string, message: string} $fields   Fields.
	 * @param string                                                                    $token    Turnstile token.
	 * @param string                                                                    $honeypot Honeypot value (must be empty).
	 * @return true|WP_Error
	 */
	public function send( array $fields, string $token, string $honeypot ): true|WP_Error {
		if ( '' !== $honeypot ) {
			return true; // Bots fill every field; pretend it worked.
		}
		// Sanitised again here so direct callers get the same guarantees as REST and admin-post.
		$name    = trim( sanitize_text_field( $fields['name'] ) );
		$email   = trim( sanitize_email( $fields['email'] ) );
		$message = trim( sanitize_textarea_field( $fields['message'] ) );
		if ( '' === $name || mb_strlen( $name ) > 120 ) {
			return new WP_Error( 'invalid_name', __( 'Please enter your name.', 'cigarholic' ) );
		}
		if ( ! is_email( $email ) ) {
			return new WP_Error( 'invalid_email', __( 'Please enter a valid email address.', 'cigarholic' ) );
		}
		if ( '' === $message || mb_strlen( $message ) > self::MAX_MESSAGE ) {
			return new WP_Error( 'invalid_message', __( 'Please enter a message (up to 5,000 characters).', 'cigarholic' ) );
		}
		$ip = self::client_ip();
		if ( ! $this->within_rate_limit( $ip ) ) {
			return new WP_Error( 'rate_limited', __( 'Too many messages. Please try again in a minute.', 'cigarholic' ) );
		}
		if ( ! Turnstile::verify( $token, $ip, 'contact' ) ) {
			return new WP_Error( 'verification_failed', __( 'We could not verify that you are human. Please try again.', 'cigarholic' ) );
		}

		$settings = self::settings();
		$order    = preg_replace( '/[^A-Za-z0-9#-]/', '', $fields['order_number'] ) ?? '';
		$subject  = sprintf( '%s %s', $settings['subject_prefix'], sprintf( /* translators: %s: sender name */ __( 'Contact from %s', 'cigarholic' ), $name ) );
		if ( '' !== $order ) {
			$subject .= sprintf( ' (%s %s)', __( 'order', 'cigarholic' ), $order );
		}
		$body    = implode(
			"\n",
			array_filter(
				array(
					sprintf( '%s: %s', __( 'Name', 'cigarholic' ), $name ),
					sprintf( '%s: %s', __( 'Email', 'cigarholic' ), $email ),
					'' !== $order ? sprintf( '%s: %s', __( 'Order number', 'cigarholic' ), $order ) : '',
					sprintf( '%s: %s', __( 'Sent from', 'cigarholic' ), home_url( '/' ) ),
					'',
					$message,
				),
				static fn( string $line ): bool => '' !== $line || true
			)
		);
		$headers = array(
			'Content-Type: text/plain; charset=UTF-8',
			sprintf( 'Reply-To: %s <%s>', str_replace( array( "\r", "\n", '<', '>' ), '', $name ), $email ),
		);
		if ( ! wp_mail( $settings['recipient'], $subject, $body, $headers ) ) {
			Logger::error( 'Contact form email could not be sent' );
			return new WP_Error( 'mail_failed', __( 'Your message could not be sent. Please email us directly.', 'cigarholic' ) );
		}
		Logger::info( 'Contact form message delivered', array( 'has_order' => '' !== $order ) );
		return true;
	}

	/**
	 * At most RATE_LIMIT messages per IP per minute.
	 *
	 * @param string $ip Visitor IP.
	 * @return bool
	 */
	private function within_rate_limit( string $ip ): bool {
		if ( '' === $ip ) {
			return true;
		}
		$key   = 'cigarholic_contact_' . md5( $ip );
		$count = (int) get_transient( $key );
		if ( $count >= self::RATE_LIMIT ) {
			return false;
		}
		set_transient( $key, $count + 1, MINUTE_IN_SECONDS );
		return true;
	}

	/**
	 * Visitor IP (Cloudflare's header when proxied).
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
	 * Message for a status code (REST response or ?contact= query parameter).
	 *
	 * @param string $status Status code.
	 * @return string
	 */
	public static function status_message( string $status ): string {
		switch ( $status ) {
			case 'ok':
				return __( 'Thank you. We have your message and reply within one business day.', 'cigarholic' );
			case 'invalid_name':
				return __( 'Please enter your name.', 'cigarholic' );
			case 'invalid_email':
				return __( 'Please enter a valid email address.', 'cigarholic' );
			case 'invalid_message':
				return __( 'Please enter a message (up to 5,000 characters).', 'cigarholic' );
			case 'rate_limited':
				return __( 'Too many messages. Please try again in a minute.', 'cigarholic' );
			case 'verification_failed':
				return __( 'We could not verify that you are human. Please try again.', 'cigarholic' );
			case 'mail_failed':
				return __( 'Your message could not be sent. Please email us directly.', 'cigarholic' );
			default:
				return '';
		}
	}

	/**
	 * WooCommerce > Contact form: recipient and subject prefix.
	 *
	 * @return void
	 */
	public function add_admin_page(): void {
		add_submenu_page(
			'woocommerce',
			__( 'Contact form', 'cigarholic' ),
			__( 'Contact form', 'cigarholic' ),
			'manage_woocommerce',
			self::PAGE,
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Settings form.
	 *
	 * @return void
	 */
	public function render_admin_page(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to edit the contact form.', 'cigarholic' ) );
		}
		$settings = self::settings();
		$saved    = isset( $_GET['saved'] ) && '1' === sanitize_key( wp_unslash( $_GET['saved'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only flag.
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Contact form', 'cigarholic' ); ?></h1>
			<?php if ( $saved ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Contact form settings saved.', 'cigarholic' ); ?></p></div>
			<?php endif; ?>
			<p class="description"><?php esc_html_e( 'Messages from the contact form are emailed to this address with the visitor as Reply-To. Nothing is stored on the site.', 'cigarholic' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::OPTION ); ?>">
				<?php wp_nonce_field( self::OPTION ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="cigarholic-cf-recipient"><?php esc_html_e( 'Recipient', 'cigarholic' ); ?></label></th>
						<td><input class="regular-text" type="email" id="cigarholic-cf-recipient" name="recipient" value="<?php echo esc_attr( $settings['recipient'] ); ?>" required></td>
					</tr>
					<tr>
						<th scope="row"><label for="cigarholic-cf-prefix"><?php esc_html_e( 'Subject prefix', 'cigarholic' ); ?></label></th>
						<td><input class="regular-text" type="text" id="cigarholic-cf-prefix" name="subject_prefix" maxlength="40" value="<?php echo esc_attr( $settings['subject_prefix'] ); ?>"></td>
					</tr>
				</table>
				<?php submit_button( __( 'Save contact form settings', 'cigarholic' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Saves the settings (capability + nonce checked).
	 *
	 * @return void
	 */
	public function handle_save(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! check_admin_referer( self::OPTION ) ) {
			wp_die( esc_html__( 'You do not have permission to edit the contact form.', 'cigarholic' ) );
		}
		$recipient = isset( $_POST['recipient'] ) ? sanitize_email( wp_unslash( $_POST['recipient'] ) ) : '';
		update_option(
			self::OPTION,
			array(
				'recipient'      => is_email( $recipient ) ? $recipient : self::DEFAULT_TO,
				'subject_prefix' => isset( $_POST['subject_prefix'] ) ? mb_substr( sanitize_text_field( wp_unslash( $_POST['subject_prefix'] ) ), 0, 40 ) : '[Cigarholic]',
			),
			false
		);
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'  => self::PAGE,
					'saved' => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
