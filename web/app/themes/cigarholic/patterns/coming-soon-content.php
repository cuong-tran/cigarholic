<?php
/**
 * Title: Coming soon content
 * Slug: cigarholic/coming-soon-content
 * Categories: cigarholic
 * Template Types: coming-soon
 * Inserter: false
 * Keywords: coming soon, launch, signup
 * Description: Brand name and label, one line about the launch and the signup form, on the Forest section style. Used by the coming-soon template; no navigation.
 *
 * Feature IDs: CNT-04 (coming-soon page with email signup), CNT-01 (brand system), CMP-02 (origin wording).
 *
 * @package Cigarholic
 */

?>
<!-- wp:group {"className":"coming-soon-content","style":{"spacing":{"blockGap":"var:preset|spacing|50"}},"layout":{"type":"flex","orientation":"vertical","justifyContent":"center"}} -->
<div class="wp-block-group coming-soon-content">
	<!-- wp:site-logo {"width":96,"shouldSyncIcon":true} /-->

	<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|30"}},"layout":{"type":"flex","orientation":"vertical","justifyContent":"center"}} -->
	<div class="wp-block-group">
		<!-- wp:site-title {"level":1,"textAlign":"center","fontSize":"xxx-large"} /-->

		<!-- wp:paragraph {"align":"center","className":"is-style-eyebrow"} -->
		<p class="has-text-align-center is-style-eyebrow"><?php esc_html_e( 'Handcrafted humidors', 'cigarholic' ); ?></p>
		<!-- /wp:paragraph -->
	</div>
	<!-- /wp:group -->

	<!-- wp:paragraph {"align":"center","textColor":"mist","fontSize":"large"} -->
	<p class="has-text-align-center has-mist-color has-text-color has-large-font-size"><?php esc_html_e( 'Handcrafted wooden humidors, cigar boxes and humidor accessories, made by hand in Vietnam and shipped from Texas. The store opens soon.', 'cigarholic' ); ?></p>
	<!-- /wp:paragraph -->

	<!-- wp:cigarholic/signup-form {"heading":"<?php echo esc_attr__( 'Be the first to know when we open', 'cigarholic' ); ?>","buttonLabel":"<?php echo esc_attr__( 'Notify me', 'cigarholic' ); ?>"} /-->

	<!-- wp:paragraph {"align":"center","fontSize":"small","textColor":"mist"} -->
	<p class="has-text-align-center has-mist-color has-text-color has-small-font-size"><?php esc_html_e( 'One email when the store opens, then occasional humidor care tips. Unsubscribe any time.', 'cigarholic' ); ?></p>
	<!-- /wp:paragraph -->
</div>
<!-- /wp:group -->
