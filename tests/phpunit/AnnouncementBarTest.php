<?php
/**
 * Tests for the announcement bar.
 *
 * @package Cigarholic
 */

namespace Cigarholic\Tests;

use Cigarholic\Core\Storefront\AnnouncementBar;
use DateTimeImmutable;
use DateTimeZone;
use WP_UnitTestCase;

/**
 * Feature IDs: NAV-02.
 */
final class AnnouncementBarTest extends WP_UnitTestCase {

	/**
	 * Settings fixture.
	 *
	 * @param array<string, mixed> $overrides Overrides.
	 * @return array{enabled: bool, text: string, url: string, starts_at: string, ends_at: string}
	 */
	private function settings( array $overrides = array() ): array {
		return array_merge(
			array(
				'enabled'   => true,
				'text'      => 'Free US shipping on every humidor',
				'url'       => '',
				'starts_at' => '',
				'ends_at'   => '',
			),
			$overrides
		);
	}

	/**
	 * Date window is evaluated in the site's timezone.
	 */
	public function test_active_window(): void {
		$tz  = new DateTimeZone( 'America/New_York' );
		$now = new DateTimeImmutable( '2026-11-27 09:30', $tz );

		$this->assertTrue( AnnouncementBar::is_active( $this->settings(), $now, $tz ) );
		$this->assertFalse( AnnouncementBar::is_active( $this->settings( array( 'enabled' => false ) ), $now, $tz ) );
		$this->assertFalse( AnnouncementBar::is_active( $this->settings( array( 'text' => '  ' ) ), $now, $tz ) );
		$this->assertTrue(
			AnnouncementBar::is_active(
				$this->settings(
					array(
						'starts_at' => '2026-11-27T09:00',
						'ends_at'   => '2026-11-30T23:59',
					)
				),
				$now,
				$tz
			)
		);
		$this->assertFalse( AnnouncementBar::is_active( $this->settings( array( 'starts_at' => '2026-11-27T10:00' ) ), $now, $tz ), 'not started yet' );
		$this->assertFalse( AnnouncementBar::is_active( $this->settings( array( 'ends_at' => '2026-11-27T09:00' ) ), $now, $tz ), 'already over' );
		$this->assertTrue( AnnouncementBar::is_active( $this->settings( array( 'starts_at' => 'garbage' ) ), $now, $tz ), 'unparseable dates are ignored' );
	}

	/**
	 * The block renders the text (escaped) with an optional link, and nothing when off.
	 */
	public function test_block_render(): void {
		update_option(
			AnnouncementBar::OPTION,
			array(
				'enabled' => true,
				'text'    => 'Holiday cut-off <b>Dec 18</b>',
				'url'     => '/collections/gift-sets/',
			)
		);
		$html = do_blocks( '<!-- wp:cigarholic/announcement-bar /-->' );
		$this->assertStringContainsString( 'role="region"', $html );
		$this->assertStringContainsString( 'href="/collections/gift-sets/"', $html );
		$this->assertStringContainsString( '&lt;b&gt;Dec 18&lt;/b&gt;', $html );

		update_option(
			AnnouncementBar::OPTION,
			array(
				'enabled' => false,
				'text'    => 'x',
			)
		);
		$this->assertSame( '', trim( do_blocks( '<!-- wp:cigarholic/announcement-bar /-->' ) ) );
	}
}
