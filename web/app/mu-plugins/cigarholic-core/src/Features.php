<?php
/**
 * Feature registry and toggles.
 *
 * @package Cigarholic
 */

namespace Cigarholic\Core;

/**
 * Registers features and decides which are on.
 *
 * Resolution order for a toggle: the default in REGISTRY, then CIGARHOLIC_FEATURES from .env
 * ("signups:on,engraving:off"), then the `cigarholic_feature_enabled` filter. Unfinished work
 * ships with default false and is listed as pending in docs/STATUS.md.
 *
 * Feature ID: FND-05.
 */
final class Features {

	/**
	 * Every feature: toggle slug => class and default state.
	 *
	 * @var array<string, array{class: class-string<Feature>, default: bool}>
	 */
	private const REGISTRY = array(
		'development'      => array(
			'class'   => Dev\Development::class,
			'default' => true,
		),
		'hardening'        => array(
			'class'   => Security\Hardening::class,
			'default' => true,
		),
		'signups'          => array(
			'class'   => Signups\Signups::class,
			'default' => true,
		),
		'announcement-bar' => array(
			'class'   => Storefront\AnnouncementBar::class,
			'default' => true,
		),
		'contact-form'     => array(
			'class'   => Contact\ContactForm::class,
			'default' => true,
		),
	);

	/**
	 * Overrides parsed from CIGARHOLIC_FEATURES.
	 *
	 * @var array<string, bool>
	 */
	private array $overrides;

	/**
	 * Registered feature instances by slug.
	 *
	 * @var array<string, Feature>
	 */
	private array $registered = array();

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin The plugin.
	 */
	public function __construct( private readonly Plugin $plugin ) {
		$spec            = defined( 'CIGARHOLIC_FEATURES' ) ? (string) CIGARHOLIC_FEATURES : '';
		$this->overrides = self::parse( $spec );
	}

	/**
	 * Parses a toggle specification such as "signups:on, engraving:off,spec-table".
	 *
	 * A bare slug means on. Unknown words and malformed entries are ignored.
	 *
	 * @param string $spec Comma-separated list.
	 * @return array<string, bool>
	 */
	public static function parse( string $spec ): array {
		$result = array();
		foreach ( explode( ',', $spec ) as $entry ) {
			$entry = strtolower( trim( $entry ) );
			if ( '' === $entry ) {
				continue;
			}
			$parts = explode( ':', $entry, 2 );
			$slug  = trim( $parts[0] );
			$state = isset( $parts[1] ) ? trim( $parts[1] ) : 'on';
			if ( '' === $slug || ! preg_match( '/^[a-z0-9-]+$/', $slug ) ) {
				continue;
			}
			if ( in_array( $state, array( 'on', 'true', '1', 'yes' ), true ) ) {
				$result[ $slug ] = true;
			} elseif ( in_array( $state, array( 'off', 'false', '0', 'no' ), true ) ) {
				$result[ $slug ] = false;
			}
		}
		return $result;
	}

	/**
	 * Whether a feature is on.
	 *
	 * @param string $slug Toggle slug.
	 * @return bool
	 */
	public function is_enabled( string $slug ): bool {
		if ( ! isset( self::REGISTRY[ $slug ] ) ) {
			return false;
		}
		$enabled = $this->overrides[ $slug ] ?? $this->default_for( $slug );

		/**
		 * Filters whether a cigarholic-core feature is enabled.
		 *
		 * @param bool   $enabled Current state.
		 * @param string $slug    Toggle slug.
		 */
		return (bool) apply_filters( 'cigarholic_feature_enabled', $enabled, $slug );
	}

	/**
	 * Default state of a feature from the registry.
	 *
	 * @param string $slug Toggle slug.
	 * @return bool
	 */
	private function default_for( string $slug ): bool {
		return (bool) ( self::REGISTRY[ $slug ]['default'] ?? false );
	}

	/**
	 * Instantiates and registers every enabled feature.
	 *
	 * @return void
	 */
	public function register_all(): void {
		foreach ( self::REGISTRY as $slug => $definition ) {
			if ( ! $this->is_enabled( $slug ) ) {
				continue;
			}
			$feature = new $definition['class']();
			$feature->register();
			$this->registered[ $slug ] = $feature;
		}
	}

	/**
	 * A registered (enabled) feature, or null.
	 *
	 * @param string $slug Toggle slug.
	 * @return Feature|null
	 */
	public function get( string $slug ): ?Feature {
		return $this->registered[ $slug ] ?? null;
	}

	/**
	 * Every known feature with its state, for `wp cigarholic features`.
	 *
	 * @return list<array{slug: string, id: string, class: string, default: string, enabled: string}>
	 */
	public function all(): array {
		$rows = array();
		foreach ( self::REGISTRY as $slug => $definition ) {
			$class  = $definition['class'];
			$rows[] = array(
				'slug'    => $slug,
				'id'      => $class::id(),
				'class'   => $class,
				'default' => $this->default_for( $slug ) ? 'on' : 'off',
				'enabled' => $this->is_enabled( $slug ) ? 'on' : 'off',
			);
		}
		return $rows;
	}

	/**
	 * The plugin.
	 *
	 * @return Plugin
	 */
	public function plugin(): Plugin {
		return $this->plugin;
	}
}
