<?php
/**
 * Announcement bar.
 *
 * @package Cigarholic
 */

namespace Cigarholic\Core\Storefront;

use Cigarholic\Core\Feature;
use Cigarholic\Core\Plugin;
use DateTimeImmutable;
use DateTimeZone;

/**
 * A one-line bar at the top of every page (promo, shipping message, holiday cut-off) that the
 * manager edits under WooCommerce > Announcement bar, with optional start and end dates in the
 * site's timezone. Rendered by the `cigarholic/announcement-bar` block in the theme header.
 *
 * Feature ID: NAV-02 (SHP-09 later adds a countdown).
 */
final class AnnouncementBar implements Feature {

	public const OPTION = 'cigarholic_announcement_bar';
	public const BLOCK  = 'cigarholic/announcement-bar';
	public const PAGE   = 'cigarholic-announcement-bar';

	/**
	 * Feature ID.
	 *
	 * @return string
	 */
	public static function id(): string {
		return 'NAV-02';
	}

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'register_block' ) );
		add_action( 'admin_menu', array( $this, 'add_admin_page' ) );
		add_action( 'admin_post_' . self::OPTION, array( $this, 'handle_save' ) );
	}

	/**
	 * Block registration.
	 *
	 * @return void
	 */
	public function register_block(): void {
		register_block_type( Plugin::instance()->dir() . '/blocks/announcement-bar' );
	}

	/**
	 * Current settings with defaults.
	 *
	 * @return array{enabled: bool, text: string, url: string, starts_at: string, ends_at: string}
	 */
	public static function settings(): array {
		$saved = get_option( self::OPTION, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		return array(
			'enabled'   => ! empty( $saved['enabled'] ),
			'text'      => isset( $saved['text'] ) ? (string) $saved['text'] : '',
			'url'       => isset( $saved['url'] ) ? (string) $saved['url'] : '',
			'starts_at' => isset( $saved['starts_at'] ) ? (string) $saved['starts_at'] : '',
			'ends_at'   => isset( $saved['ends_at'] ) ? (string) $saved['ends_at'] : '',
		);
	}

	/**
	 * Whether the bar shows for the given settings at the given moment (site timezone for the dates).
	 *
	 * @param array{enabled: bool, text: string, url: string, starts_at: string, ends_at: string} $settings Settings.
	 * @param DateTimeImmutable                                                                   $now      Now.
	 * @param DateTimeZone                                                                        $timezone Site timezone.
	 * @return bool
	 */
	public static function is_active( array $settings, DateTimeImmutable $now, DateTimeZone $timezone ): bool {
		if ( ! $settings['enabled'] || '' === trim( $settings['text'] ) ) {
			return false;
		}
		$starts = self::parse_local( $settings['starts_at'], $timezone );
		$ends   = self::parse_local( $settings['ends_at'], $timezone );
		if ( null !== $starts && $now < $starts ) {
			return false;
		}
		if ( null !== $ends && $now > $ends ) {
			return false;
		}
		return true;
	}

	/**
	 * Parses a datetime-local value ("2026-11-27T09:00") in the site timezone.
	 *
	 * @param string       $value    Value.
	 * @param DateTimeZone $timezone Timezone.
	 * @return DateTimeImmutable|null
	 */
	private static function parse_local( string $value, DateTimeZone $timezone ): ?DateTimeImmutable {
		if ( '' === $value ) {
			return null;
		}
		$parsed = DateTimeImmutable::createFromFormat( 'Y-m-d\TH:i', $value, $timezone );
		return false === $parsed ? null : $parsed;
	}

	/**
	 * Whether the bar shows right now.
	 *
	 * @return bool
	 */
	public static function is_active_now(): bool {
		return self::is_active( self::settings(), new DateTimeImmutable( 'now', wp_timezone() ), wp_timezone() );
	}

	/**
	 * WooCommerce > Announcement bar.
	 *
	 * @return void
	 */
	public function add_admin_page(): void {
		add_submenu_page(
			'woocommerce',
			__( 'Announcement bar', 'cigarholic' ),
			__( 'Announcement bar', 'cigarholic' ),
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
			wp_die( esc_html__( 'You do not have permission to edit the announcement bar.', 'cigarholic' ) );
		}
		$settings = self::settings();
		$saved    = isset( $_GET['saved'] ) ? '1' === sanitize_key( wp_unslash( $_GET['saved'] ) ) : false; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only flag.
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Announcement bar', 'cigarholic' ); ?></h1>
			<?php if ( $saved ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Announcement bar saved.', 'cigarholic' ); ?></p></div>
			<?php endif; ?>
			<p class="description"><?php esc_html_e( 'One line shown at the top of every page: a promotion, a shipping message or a holiday cut-off. Leave the dates empty to show it until you turn it off.', 'cigarholic' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::OPTION ); ?>">
				<?php wp_nonce_field( self::OPTION ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Show the bar', 'cigarholic' ); ?></th>
						<td><label><input type="checkbox" name="enabled" value="1" <?php checked( $settings['enabled'] ); ?>> <?php esc_html_e( 'Enabled', 'cigarholic' ); ?></label></td>
					</tr>
					<tr>
						<th scope="row"><label for="cigarholic-ab-text"><?php esc_html_e( 'Text', 'cigarholic' ); ?></label></th>
						<td><input class="large-text" type="text" id="cigarholic-ab-text" name="text" maxlength="160" value="<?php echo esc_attr( $settings['text'] ); ?>"><p class="description"><?php esc_html_e( 'Up to 160 characters, plain text.', 'cigarholic' ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><label for="cigarholic-ab-url"><?php esc_html_e( 'Link (optional)', 'cigarholic' ); ?></label></th>
						<td><input class="regular-text code" type="url" id="cigarholic-ab-url" name="url" value="<?php echo esc_attr( $settings['url'] ); ?>" placeholder="/collections/gift-sets/"></td>
					</tr>
					<tr>
						<th scope="row"><label for="cigarholic-ab-start"><?php esc_html_e( 'Start (optional)', 'cigarholic' ); ?></label></th>
						<td><input type="datetime-local" id="cigarholic-ab-start" name="starts_at" value="<?php echo esc_attr( $settings['starts_at'] ); ?>"> <span class="description"><?php echo esc_html( sprintf( /* translators: %s: timezone */ __( 'Site timezone: %s', 'cigarholic' ), wp_timezone_string() ) ); ?></span></td>
					</tr>
					<tr>
						<th scope="row"><label for="cigarholic-ab-end"><?php esc_html_e( 'End (optional)', 'cigarholic' ); ?></label></th>
						<td><input type="datetime-local" id="cigarholic-ab-end" name="ends_at" value="<?php echo esc_attr( $settings['ends_at'] ); ?>"></td>
					</tr>
				</table>
				<?php submit_button( __( 'Save announcement bar', 'cigarholic' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Saves the form (capability + nonce checked).
	 *
	 * @return void
	 */
	public function handle_save(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! check_admin_referer( self::OPTION ) ) {
			wp_die( esc_html__( 'You do not have permission to edit the announcement bar.', 'cigarholic' ) );
		}
		$url = isset( $_POST['url'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['url'] ) ) ) : '';
		if ( '' !== $url && ! str_starts_with( $url, '/' ) ) {
			$url = esc_url_raw( $url, array( 'http', 'https' ) );
		}
		update_option(
			self::OPTION,
			array(
				'enabled'   => ! empty( $_POST['enabled'] ),
				'text'      => isset( $_POST['text'] ) ? mb_substr( sanitize_text_field( wp_unslash( $_POST['text'] ) ), 0, 160 ) : '',
				'url'       => $url,
				'starts_at' => isset( $_POST['starts_at'] ) ? self::sanitize_datetime( sanitize_text_field( wp_unslash( $_POST['starts_at'] ) ) ) : '',
				'ends_at'   => isset( $_POST['ends_at'] ) ? self::sanitize_datetime( sanitize_text_field( wp_unslash( $_POST['ends_at'] ) ) ) : '',
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

	/**
	 * Keeps only a valid datetime-local value.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	private static function sanitize_datetime( string $value ): string {
		return preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $value ) ? $value : '';
	}
}
