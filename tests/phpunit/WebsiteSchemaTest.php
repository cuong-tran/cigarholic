<?php
/**
 * Tests for the site name in SEOPress's WebSite structured data.
 *
 * @package Cigarholic
 */

namespace Cigarholic\Tests;

use Cigarholic\Core\Plugin;
use Cigarholic\Core\Provision\Runner;
use Cigarholic\Core\Seo\WebsiteSchema;
use WP_UnitTestCase;

/**
 * Feature IDs: SEO-02, SEO-01.
 */
final class WebsiteSchemaTest extends WP_UnitTestCase {

	/**
	 * The JSON-LD nodes SEOPress prints in the head of the current page, by type.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function nodes(): array {
		seopress_load_social_options();
		ob_start();
		seopress_social_website_option();
		$html = (string) ob_get_clean();
		$this->assertSame( 1, preg_match( '#<script id="website-schema" type="application/ld\+json">(.*?)</script>#s', $html, $match ), $html );
		$node = json_decode( $match[1], true );
		$this->assertIsArray( $node );
		return array( (string) $node['@type'] => $node );
	}

	/**
	 * The name is the site title and an alternate name that repeats it is dropped.
	 */
	public function test_filter_sets_the_site_name(): void {
		update_option( 'blogname', 'Cigarholic' );
		$schema = ( new WebsiteSchema() )->website(
			array(
				'@type'         => 'WebSite',
				'name'          => 'Cigarholic | Handcrafted Wooden Humidors and Cigar Boxes',
				'alternateName' => 'Cigarholic',
				'url'           => 'https://cigarholic.com',
			)
		);
		$this->assertSame( 'Cigarholic', $schema['name'] );
		$this->assertArrayNotHasKey( 'alternateName', $schema );
		$this->assertSame( 'https://cigarholic.com', $schema['url'] );

		$other = ( new WebsiteSchema() )->website( array( 'alternateName' => 'Cigarholic Humidors' ) );
		$this->assertSame( 'Cigarholic Humidors', $other['alternateName'], 'a real alternate name stays' );
		$this->assertSame( 'not an array', ( new WebsiteSchema() )->website( 'not an array' ) );
	}

	/**
	 * After provisioning, the home page's WebSite node is named "Cigarholic" while the home title stays
	 * descriptive, and it points at the OnlineStore from the knowledge graph (step 75-seo).
	 */
	public function test_home_page_website_node_after_provisioning(): void {
		$this->set_permalink_structure( '' );
		( new Runner( Plugin::instance()->provision_dir(), static function (): void {} ) )->run( false );
		$this->assertSame( 'Cigarholic | Handcrafted Wooden Humidors and Cigar Boxes', get_option( 'seopress_titles_option_name' )['seopress_titles_home_site_title'] );

		$this->go_to( home_url( '/' ) );
		$this->assertTrue( is_front_page() );
		$website = $this->nodes()['WebSite'];

		$this->assertSame( get_bloginfo( 'name' ), $website['name'] );
		$this->assertArrayNotHasKey( 'alternateName', $website );
		$this->assertStringContainsString( 'handcrafted in Vietnam', $website['description'] );
		$this->assertSame( home_url( '/#organization' ), $website['publisher']['@id'] );
	}
}
