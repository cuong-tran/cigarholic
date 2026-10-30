<?php
/**
 * Title: Craftsmanship story
 * Slug: cigarholic/craftsmanship-story
 * Categories: cigarholic, about
 * Template Types: front-page, page
 * Keywords: craftsmanship, workshop, vietnam, story
 * Description: Workshop photo placeholder beside a short story about how the humidors are made, with a link to the About page.
 *
 * Feature IDs: NAV-04 (homepage), CNT-08 (craftsmanship story), CMP-02 (origin wording).
 *
 * @package Cigarholic
 */

?>
<!-- wp:group {"tagName":"section","align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|70","bottom":"var:preset|spacing|70"}}},"layout":{"type":"constrained"}} -->
<section class="wp-block-group alignfull" style="padding-top:var(--wp--preset--spacing--70);padding-bottom:var(--wp--preset--spacing--70)">
	<!-- wp:media-text {"align":"wide","mediaType":"image"} -->
	<div class="wp-block-media-text alignwide is-stacked-on-mobile"><figure class="wp-block-media-text__media"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/images/placeholder-workshop.svg' ) ); ?>" alt="<?php esc_attr_e( 'Placeholder for a photo of a craftsman fitting a humidor lid in the Vietnam workshop', 'cigarholic' ); ?>"/></figure><div class="wp-block-media-text__content">
		<!-- wp:heading -->
		<h2 class="wp-block-heading"><?php esc_html_e( 'Made by hand in Vietnam', 'cigarholic' ); ?></h2>
		<!-- /wp:heading -->

		<!-- wp:paragraph -->
		<p><?php esc_html_e( 'Each humidor starts as kiln-dried hardwood, cut and joined by hand in our Vietnam workshop. The interior is lined with Spanish cedar, the lid is fitted until it closes with a soft, even seal, and the finish is built up in thin coats and polished between each one.', 'cigarholic' ); ?></p>
		<!-- /wp:paragraph -->

		<!-- wp:paragraph -->
		<p><?php esc_html_e( 'Finished pieces are inspected before export, then shipped to our warehouse in Houston, Texas, so your order travels the last leg quickly and arrives ready to season.', 'cigarholic' ); ?></p>
		<!-- /wp:paragraph -->

		<!-- wp:buttons -->
		<div class="wp-block-buttons">
			<!-- wp:button {"className":"is-style-outline"} -->
			<div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( home_url( '/about/' ) ); ?>"><?php esc_html_e( 'Read our story', 'cigarholic' ); ?></a></div>
			<!-- /wp:button -->
		</div>
		<!-- /wp:buttons -->
	</div></div>
	<!-- /wp:media-text -->
</section>
<!-- /wp:group -->
