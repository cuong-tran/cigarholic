<?php
/**
 * Two-factor authentication required for administrators and shop managers.
 *
 * @package Cigarholic
 */

namespace Cigarholic\Core\Security;

use Cigarholic\Core\Feature;

/**
 * Every account that can manage the store (`manage_options` or `manage_woocommerce`: administrators
 * and shop managers) must use two-factor authentication from the Two Factor plugin.
 *
 * A privileged account without a second factor gets a grace period, counted from its first login
 * or admin page view after this feature is on (GRACE_SECONDS, filter
 * `cigarholic_two_factor_grace_seconds`); WP admin shows the deadline. After it, the account keeps
 * only `read` and `view_admin_dashboard` until a method is set up: it can open its own profile and
 * set up two-factor there, and every other admin page sends it to that profile. Because the
 * capabilities themselves are withheld, the REST API, admin-ajax, admin-post and application
 * passwords refuse store management in the same way, with no list of routes to keep up to date.
 * The account's role is never changed; enrolling restores everything at once.
 *
 * Without the Two Factor plugin nothing can be enforced: administrators see a notice instead of
 * losing access (the deploy activates every plugin, so this is a broken release, not a state to
 * live with). Recovery for a lost device is in docs/runbooks/two-factor.md.
 *
 * Feature ID: OPS-07.
 */
final class TwoFactorRequired implements Feature {

	/**
	 * User meta: Unix time the grace period started.
	 */
	public const GRACE_META = 'cigarholic_two_factor_grace_start';

	/**
	 * Default grace period: three days.
	 */
	public const GRACE_SECONDS = 259200;

	/**
	 * Capabilities a privileged account keeps while it has to enrol.
	 */
	private const KEPT_CAPS = array(
		'read'                 => true,
		'view_admin_dashboard' => true,
	);

	/**
	 * Per-request cache of the enrolment state by user ID.
	 *
	 * @var array<int, bool>
	 */
	private static array $enrolled = array();

	/**
	 * Re-entrancy guard: the Two Factor plugin may check capabilities while it answers.
	 *
	 * @var bool
	 */
	private static bool $checking = false;

	/**
	 * Feature ID.
	 *
	 * @return string
	 */
	public static function id(): string {
		return 'OPS-07';
	}

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'user_has_cap', array( $this, 'withhold_caps' ), 999, 4 );
		add_action( 'wp_login', array( $this, 'start_grace_on_login' ), 10, 2 );
		add_action( 'admin_init', array( $this, 'guard_admin' ), 1 );
		// wp-admin/menu.php refuses a page the account cannot open before admin_init runs.
		add_action( 'admin_page_access_denied', array( $this, 'guard_admin' ), 1 );
		add_action( 'admin_notices', array( $this, 'notice' ) );
		add_action( 'updated_user_meta', array( $this, 'forget' ), 10, 2 );
		add_action( 'added_user_meta', array( $this, 'forget' ), 10, 2 );
		add_action( 'deleted_user_meta', array( $this, 'forget' ), 10, 2 );
	}

	/**
	 * Whether the Two Factor plugin is loaded.
	 *
	 * @return bool
	 */
	public static function available(): bool {
		return class_exists( '\Two_Factor_Core' );
	}

	/**
	 * Whether an account can manage the store, judged by its role capabilities (never by the
	 * filtered ones, which this feature changes).
	 *
	 * @param \WP_User $user Account.
	 * @return bool
	 */
	public static function is_privileged( \WP_User $user ): bool {
		return $user->exists() && ( ! empty( $user->allcaps['manage_options'] ) || ! empty( $user->allcaps['manage_woocommerce'] ) );
	}

	/**
	 * Whether the account has a working second factor.
	 *
	 * @param \WP_User $user Account.
	 * @return bool
	 */
	public static function is_enrolled( \WP_User $user ): bool {
		if ( ! self::available() ) {
			return false;
		}
		if ( ! isset( self::$enrolled[ $user->ID ] ) ) {
			self::$enrolled[ $user->ID ] = \Two_Factor_Core::is_user_using_two_factor( $user );
		}
		return self::$enrolled[ $user->ID ];
	}

	/**
	 * End of the grace period (Unix time), or null while it has not started.
	 *
	 * @param \WP_User $user Account.
	 * @return int|null
	 */
	public static function deadline( \WP_User $user ): ?int {
		$start = (int) get_user_meta( $user->ID, self::GRACE_META, true );
		if ( $start <= 0 ) {
			return null;
		}
		$grace = (int) apply_filters( 'cigarholic_two_factor_grace_seconds', self::GRACE_SECONDS, $user );
		return $start + max( 0, $grace );
	}

	/**
	 * Enrolment state of an account: `exempt` (not privileged, or no plugin), `enrolled`, `grace`
	 * (must enrol, still has full access) or `restricted` (must enrol, capabilities withheld).
	 *
	 * @param \WP_User $user Account.
	 * @return string
	 */
	public static function state( \WP_User $user ): string {
		if ( ! self::available() || ! self::is_privileged( $user ) ) {
			return 'exempt';
		}
		if ( self::is_enrolled( $user ) ) {
			return 'enrolled';
		}
		$deadline = self::deadline( $user );
		return ( null === $deadline || time() < $deadline ) ? 'grace' : 'restricted';
	}

	/**
	 * Starts the grace period of a privileged account without a second factor (idempotent).
	 *
	 * @param \WP_User $user Account.
	 * @return void
	 */
	public static function start_grace( \WP_User $user ): void {
		if ( 'grace' === self::state( $user ) && null === self::deadline( $user ) ) {
			update_user_meta( $user->ID, self::GRACE_META, time() );
		}
	}

	/**
	 * `user_has_cap`: a restricted account keeps only KEPT_CAPS.
	 *
	 * @param array<string, bool> $allcaps Capabilities of the account.
	 * @param string[]            $caps    Primitive capabilities being checked.
	 * @param array<int, mixed>   $args    Original check arguments.
	 * @param \WP_User            $user    Account.
	 * @return array<string, bool>
	 */
	public function withhold_caps( array $allcaps, array $caps, array $args, $user ): array {
		if ( self::$checking || ! $user instanceof \WP_User ) {
			return $allcaps;
		}
		self::$checking = true;
		try {
			$restricted = 'restricted' === self::state( $user );
		} finally {
			self::$checking = false;
		}
		return $restricted ? self::KEPT_CAPS : $allcaps;
	}

	/**
	 * `wp_login`: the grace period starts at the first login.
	 *
	 * @param string   $login Login name (unused).
	 * @param \WP_User $user  Account.
	 * @return void
	 */
	public function start_grace_on_login( $login, $user ): void {
		if ( $user instanceof \WP_User ) {
			self::start_grace( $user );
		}
	}

	/**
	 * `admin_init` and `admin_page_access_denied`: starts the grace period for an account that was
	 * logged in before this feature existed, and sends a restricted account to its profile from
	 * every other admin page (instead of WordPress's "not allowed" page).
	 *
	 * @return void
	 */
	public function guard_admin(): void {
		$user = wp_get_current_user();
		self::start_grace( $user );
		if ( wp_doing_ajax() || wp_doing_cron() || 'restricted' !== self::state( $user ) ) {
			return;
		}
		global $pagenow;
		if ( in_array( $pagenow, array( 'profile.php', 'admin-post.php' ), true ) ) {
			return;
		}
		wp_safe_redirect( self::profile_url() );
		exit;
	}

	/**
	 * Admin notice with the deadline, the restriction, or the missing plugin.
	 *
	 * @return void
	 */
	public function notice(): void {
		$user = wp_get_current_user();
		if ( ! self::available() ) {
			if ( ! empty( $user->allcaps['manage_options'] ) ) {
				printf(
					'<div class="notice notice-error"><p>%s</p></div>',
					esc_html__( 'The Two Factor plugin is not active, so two-factor authentication cannot be required for store managers. Deploy the release again, which activates it.', 'cigarholic' )
				);
			}
			return;
		}
		$state = self::state( $user );
		if ( 'grace' === $state ) {
			$deadline = self::deadline( $user );
			printf(
				'<div class="notice notice-warning"><p>%s <a href="%s">%s</a></p></div>',
				esc_html(
					sprintf(
						/* translators: %s: date and time in the site timezone. */
						__( 'Two-factor authentication is required for your account. Set it up before %s; after that, only your profile is available until you do.', 'cigarholic' ),
						wp_date( (string) get_option( 'date_format' ) . ' ' . (string) get_option( 'time_format' ), $deadline ?? time() + self::GRACE_SECONDS )
					)
				),
				esc_url( self::profile_url() ),
				esc_html__( 'Set up two-factor now', 'cigarholic' )
			);
		} elseif ( 'restricted' === $state ) {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html__( 'Your store access is paused until you set up two-factor authentication below: at "Authenticator App", scan the QR code with your phone and enter the six digits, then generate recovery codes and keep them safe.', 'cigarholic' )
			);
		}
	}

	/**
	 * Forgets the cached enrolment state when a user's two-factor or grace meta changes.
	 *
	 * @param int|int[] $meta_ids Meta ID(s) (unused).
	 * @param int       $user_id  User ID.
	 * @return void
	 */
	public function forget( $meta_ids, $user_id ): void {
		unset( self::$enrolled[ (int) $user_id ] );
	}

	/**
	 * The two-factor section of the current user's profile.
	 *
	 * @return string
	 */
	public static function profile_url(): string {
		return admin_url( 'profile.php#two-factor-options' );
	}
}
