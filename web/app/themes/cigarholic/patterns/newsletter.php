<?php
/**
 * Title: Newsletter
 * Slug: cigarholic/newsletter
 * Categories: cigarholic, call-to-action
 * Template Types: front-page, page
 * Keywords: newsletter, signup, email, subscribe
 * Description: Email signup section built on the cigarholic-core signup form block, with the privacy note. The block renders its own heading.
 *
 * Feature IDs: NAV-04 (homepage newsletter), NAV-03 (same block in the footer), CNT-04 (signups stored by cigarholic-core, D-04).
 *
 * @package Cigarholic
 */

?>
<!-- wp:group {"tagName":"section","align":"full","className":"newsletter","style":{"spacing":{"padding":{"top":"var:preset|spacing|70","bottom":"var:preset|spacing|70"},"blockGap":"var:preset|spacing|40"}},"backgroundColor":"neutral-1","layout":{"type":"constrained","contentSize":"640px"}} -->
<section class="wp-block-group alignfull newsletter has-neutral-1-background-color has-background" style="padding-top:var(--wp--preset--spacing--70);padding-bottom:var(--wp--preset--spacing--70)">
	<!-- wp:cigarholic/signup-form {"heading":"<?php echo esc_attr__( 'Join the Cigarholic list', 'cigarholic' ); ?>","buttonLabel":"<?php echo esc_attr__( 'Subscribe', 'cigarholic' ); ?>"} /-->

	<!-- wp:paragraph {"align":"center","fontSize":"small","textColor":"neutral-2"} -->
	<p class="has-text-align-center has-neutral-2-color has-text-color has-small-font-size"><?php esc_html_e( 'Humidor care tips, new pieces and launch news, a few times a year. Unsubscribe any time.', 'cigarholic' ); ?> <a href="<?php echo esc_url( home_url( '/privacy-policy/' ) ); ?>"><?php esc_html_e( 'Privacy policy', 'cigarholic' ); ?></a></p>
	<!-- /wp:paragraph -->
</section>
<!-- /wp:group -->
