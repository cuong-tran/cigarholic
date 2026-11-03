<?php
/**
 * The robots.txt of sites that discourage search engines.
 *
 * @package Cigarholic
 */

namespace Cigarholic\Core\Seo;

use Cigarholic\Core\Feature;

/**
 * A site that discourages search engines (`blog_public` 0: staging and development, set from
 * DISALLOW_INDEXING by provisioning step 00-core) answers robots.txt with "Disallow: /". WordPress
 * stopped writing that line itself in 5.3 and relies on the noindex robots meta tag alone, so
 * staging's robots.txt would otherwise invite crawlers in. Staging also sends `X-Robots-Tag: noindex`
 * and sits behind basic auth (infra/server/site.sh). A public site keeps WordPress's rules and what
 * WooCommerce and SEOPress add to them. Nginx hands /robots.txt to WordPress (site.conf.tpl).
 *
 * Feature ID: SEO-01.
 */
final class RobotsTxt implements Feature {

	/**
	 * Feature ID.
	 *
	 * @return string
	 */
	public static function id(): string {
		return 'SEO-01';
	}

	/**
	 * Hooks. Last on the filter, so no plugin re-opens a non-public site.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'robots_txt', array( $this, 'robots_txt' ), PHP_INT_MAX, 2 );
	}

	/**
	 * Everything disallowed unless the site is public.
	 *
	 * @param mixed $output    robots.txt as built so far.
	 * @param mixed $is_public Whether the site is public (`blog_public`).
	 * @return string
	 */
	public function robots_txt( $output, $is_public ): string {
		if ( $is_public ) {
			return (string) $output;
		}
		return "User-agent: *\nDisallow: /\n";
	}
}
