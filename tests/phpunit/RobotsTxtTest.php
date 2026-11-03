<?php
/**
 * Tests for robots.txt on sites that discourage search engines.
 *
 * @package Cigarholic
 */

namespace Cigarholic\Tests;

use WP_UnitTestCase;

/**
 * Feature IDs: SEO-01.
 */
final class RobotsTxtTest extends WP_UnitTestCase {

	/**
	 * The robots.txt WordPress prints, with every filter applied.
	 *
	 * @return string
	 */
	private function robots(): string {
		ob_start();
		do_robots();
		return (string) ob_get_clean();
	}

	/**
	 * A site that discourages search engines disallows everything, whatever was added before.
	 */
	public function test_non_public_site_disallows_everything(): void {
		update_option( 'blog_public', '0' );
		$this->assertSame( "User-agent: *\nDisallow: /\n", $this->robots() );
	}

	/**
	 * A public site keeps WordPress's own rules (and those plugins add); no blanket disallow.
	 */
	public function test_public_site_keeps_wordpress_rules(): void {
		update_option( 'blog_public', '1' );
		$robots = $this->robots();
		$this->assertStringStartsWith( "User-agent: *\n", $robots );
		$this->assertStringContainsString( 'Disallow: ' . wp_parse_url( admin_url(), PHP_URL_PATH ) . "\n", $robots );
		$this->assertDoesNotMatchRegularExpression( '#^Disallow: /$#m', $robots );
	}
}
