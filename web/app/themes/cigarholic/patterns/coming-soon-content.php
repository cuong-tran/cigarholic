<?php
/**
 * Title: Coming soon content
 * Slug: cigarholic/coming-soon-content
 * Categories: cigarholic
 * Template Types: coming-soon
 * Inserter: false
 * Keywords: coming soon, launch, signup
 * Description: Brand name, one line about the launch and the signup form. Used by the coming-soon template; no navigation.
 *
 * Feature IDs: CNT-04 (coming-soon page with email signup), CMP-02 (origin wording).
 *
 * @package Cigarholic
 */

?>
<!-- wp:group {"className":"coming-soon-content","style":{"spacing":{"blockGap":"var:preset|spacing|50"}},"layout":{"type":"flex","orientation":"vertical","justifyContent":"center"}} -->
<div class="wp-block-group coming-soon-content">
	<!-- wp:site-logo {"width":96,"shouldSyncIcon":true} /-->

	<!-- wp:site-title {"level":1,"textAlign":"center","fontSize":"xx-large"} /-->

	<!-- wp:paragraph {"align":"center","fontSize":"large"} -->
	<p class="has-text-align-center has-large-font-size"><?php esc_html_e( 'Handcrafted wooden humidors, cigar boxes and humidor accessories, made by hand in Vietnam and shipped from Texas. The store opens soon.', 'cigarholic' ); ?></p>
	<!-- /wp:paragraph -->

	<!-- wp:cigarholic/signup-form {"heading":"<?php echo esc_attr__( 'Be the first to know when we open', 'cigarholic' ); ?>","buttonLabel":"<?php echo esc_attr__( 'Notify me', 'cigarholic' ); ?>"} /-->

	<!-- wp:paragraph {"align":"center","fontSize":"small","textColor":"neutral-2"} -->
	<p class="has-text-align-center has-neutral-2-color has-text-color has-small-font-size"><?php esc_html_e( 'One email when the store opens, then occasional humidor care tips. Unsubscribe any time.', 'cigarholic' ); ?></p>
	<!-- /wp:paragraph -->
</div>
<!-- /wp:group -->
