<?php
/**
 * Tests for the feature registry and toggles.
 *
 * @package Cigarholic
 */

namespace Cigarholic\Tests;

use Cigarholic\Core\Features;
use Cigarholic\Core\Plugin;
use WP_UnitTestCase;

/**
 * Feature IDs: FND-05.
 */
final class FeaturesTest extends WP_UnitTestCase {

	/**
	 * A specification turns into slug => bool, tolerating spaces, bare slugs and junk.
	 */
	public function test_parse_specification(): void {
		$parsed = Features::parse( 'signups:on, Engraving:OFF ,spec-table, bad entry, :on, x:maybe' );

		$this->assertSame(
			array(
				'signups'    => true,
				'engraving'  => false,
				'spec-table' => true,
			),
			$parsed
		);
		$this->assertSame( array(), Features::parse( '' ) );
	}

	/**
	 * The two skeleton features are known and on by default; unknown slugs are off.
	 */
	public function test_defaults(): void {
		$features = Plugin::instance()->features();

		$this->assertTrue( $features->is_enabled( 'development' ) );
		$this->assertTrue( $features->is_enabled( 'hardening' ) );
		$this->assertFalse( $features->is_enabled( 'does-not-exist' ) );
		$this->assertNotNull( $features->get( 'hardening' ) );

		$slugs = array_column( $features->all(), 'slug' );
		$this->assertContains( 'development', $slugs );
		$this->assertContains( 'hardening', $slugs );
	}

	/**
	 * The filter can switch a feature off.
	 */
	public function test_filter_overrides(): void {
		$features = Plugin::instance()->features();
		$off      = static fn( bool $enabled, string $slug ): bool => 'hardening' === $slug ? false : $enabled;

		add_filter( 'cigarholic_feature_enabled', $off, 10, 2 );
		$this->assertFalse( $features->is_enabled( 'hardening' ) );
		remove_filter( 'cigarholic_feature_enabled', $off );
		$this->assertTrue( $features->is_enabled( 'hardening' ) );
	}
}
