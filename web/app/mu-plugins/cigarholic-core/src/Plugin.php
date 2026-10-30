<?php
/**
 * Plugin bootstrap.
 *
 * @package Cigarholic
 */

namespace Cigarholic\Core;

use Automattic\WooCommerce\Utilities\FeaturesUtil;

/**
 * Boots cigarholic-core: WooCommerce compatibility, text domain, features, WP-CLI commands.
 *
 * Feature ID: FND-05.
 */
final class Plugin {

	public const VERSION = '0.1.0';

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Feature registry.
	 *
	 * @var Features
	 */
	private Features $features;

	/**
	 * Constructor.
	 *
	 * @param string $file Absolute path of the mu-plugin loader file.
	 * @param string $dir  Absolute path of the plugin directory (cigarholic-core/).
	 */
	private function __construct( private readonly string $file, private readonly string $dir ) {
		$this->features = new Features( $this );
	}

	/**
	 * Boots the plugin once.
	 *
	 * @param string $file Absolute path of the mu-plugin loader file.
	 * @param string $dir  Absolute path of the plugin directory.
	 * @return Plugin
	 */
	public static function boot( string $file, string $dir ): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self( $file, $dir );
			self::$instance->run();
		}
		return self::$instance;
	}

	/**
	 * Returns the booted instance.
	 *
	 * @return Plugin
	 * @throws \LogicException When called before boot().
	 */
	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			throw new \LogicException( 'Cigarholic\Core\Plugin::boot() has not run.' );
		}
		return self::$instance;
	}

	/**
	 * Absolute path of the loader file.
	 *
	 * @return string
	 */
	public function file(): string {
		return $this->file;
	}

	/**
	 * Absolute path of the plugin directory, without a trailing slash.
	 *
	 * @return string
	 */
	public function dir(): string {
		return rtrim( $this->dir, '/' );
	}

	/**
	 * URL of the plugin directory, without a trailing slash.
	 *
	 * @return string
	 */
	public function url(): string {
		return rtrim( plugins_url( '', $this->dir . '/cigarholic-core.php' ), '/' );
	}

	/**
	 * Absolute path of the provisioning steps directory (provision/steps at the repository root).
	 *
	 * @return string
	 */
	public function provision_dir(): string {
		$root = dirname( $this->dir, 4 );
		return $root . '/provision/steps';
	}

	/**
	 * Feature registry.
	 *
	 * @return Features
	 */
	public function features(): Features {
		return $this->features;
	}

	/**
	 * Hooks everything.
	 *
	 * @return void
	 */
	private function run(): void {
		add_action( 'before_woocommerce_init', array( $this, 'declare_woocommerce_compatibility' ) );
		add_action( 'init', array( $this, 'load_textdomain' ), 0 );

		$this->features->register_all();

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			Cli\Commands::register( $this );
		}
	}

	/**
	 * Declares HPOS and block cart/checkout compatibility (brief: HPOS and block checkout only).
	 *
	 * @return void
	 */
	public function declare_woocommerce_compatibility(): void {
		if ( ! class_exists( FeaturesUtil::class ) ) {
			return;
		}
		FeaturesUtil::declare_compatibility( 'custom_order_tables', $this->file, true );
		FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', $this->file, true );
	}

	/**
	 * Loads translations from cigarholic-core/languages.
	 *
	 * @return void
	 */
	public function load_textdomain(): void {
		load_muplugin_textdomain( 'cigarholic', basename( $this->dir ) . '/languages' );
	}
}
