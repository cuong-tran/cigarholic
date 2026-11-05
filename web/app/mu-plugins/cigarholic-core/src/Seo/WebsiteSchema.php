<?php
/**
 * The site name in SEOPress's WebSite structured data.
 *
 * @package Cigarholic
 */

namespace Cigarholic\Core\Seo;

use Cigarholic\Core\Feature;

/**
 * SEOPress prints a WebSite node on the home page and fills its `name` with the home page title
 * ("Cigarholic | Handcrafted Wooden Humidors and Cigar Boxes", provisioning step 75-seo). Google reads
 * that field as the site's name in search results, so it must be the name alone. This keeps the
 * descriptive home title and sets the WebSite name to the site title ("Cigarholic"); an
 * `alternateName` that only repeats it is dropped. SEOPress has no setting for this, only the filter.
 *
 * Feature ID: SEO-02 (WebSite).
 */
final class WebsiteSchema implements Feature {

	/**
	 * Feature ID.
	 *
	 * @return string
	 */
	public static function id(): string {
		return 'SEO-02';
	}

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'seopress_schemas_website', array( $this, 'website' ) );
	}

	/**
	 * The WebSite node with the site title as its name.
	 *
	 * @param mixed $schema WebSite node as SEOPress built it.
	 * @return mixed
	 */
	public function website( $schema ) {
		$name = trim( wp_strip_all_tags( (string) get_bloginfo( 'name' ) ) );
		if ( ! is_array( $schema ) || '' === $name ) {
			return $schema;
		}
		$schema['name'] = $name;
		if ( isset( $schema['alternateName'] ) && $name === $schema['alternateName'] ) {
			unset( $schema['alternateName'] );
		}
		return $schema;
	}
}
