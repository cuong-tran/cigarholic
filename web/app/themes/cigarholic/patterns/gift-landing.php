<?php
/**
 * Title: Gift landing
 * Slug: cigarholic/gift-landing
 * Categories: cigarholic, call-to-action
 * Template Types: page
 * Keywords: gifts, gift sets, occasions, corporate
 * Description: Intro for a gifting page: promise, call to action to the Gift Sets collection and three occasion cards. Starts at H2 so it sits under a page title.
 *
 * Feature IDs: NAV-04 (patterns the manager composes with), CAT-02 (Gift Sets category), CNT-10 (corporate gifting, link only).
 *
 * @package Cigarholic
 */

$cigarholic_occasions = array(
	array(
		'title' => __( 'Father\'s Day', 'cigarholic' ),
		'text'  => __( 'A desktop humidor he will use every week, with a hygrometer and humidifier included.', 'cigarholic' ),
		'url'   => '/collections/gift-sets/',
		'link'  => __( 'Shop gift sets', 'cigarholic' ),
		'alt'   => __( 'Placeholder for a photo of a desktop humidor gift set', 'cigarholic' ),
	),
	array(
		'title' => __( 'Groomsmen', 'cigarholic' ),
		'text'  => __( 'Matching travel humidors or cigar boxes for the whole party, shipped together to one address.', 'cigarholic' ),
		'url'   => '/collections/travel-humidors/',
		'link'  => __( 'Shop travel humidors', 'cigarholic' ),
		'alt'   => __( 'Placeholder for a photo of a set of travel humidors', 'cigarholic' ),
	),
	array(
		'title' => __( 'Corporate gifts', 'cigarholic' ),
		'text'  => __( 'Larger quantities and client gifts: tell us the quantity and your deadline and we will come back with options.', 'cigarholic' ),
		'url'   => '/contact/',
		'link'  => __( 'Ask about corporate orders', 'cigarholic' ),
		'alt'   => __( 'Placeholder for a photo of cigar boxes prepared as corporate gifts', 'cigarholic' ),
	),
);
?>
<!-- wp:group {"tagName":"section","align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|70"},"blockGap":"var:preset|spacing|60"}},"layout":{"type":"constrained"}} -->
<section class="wp-block-group alignfull" style="padding-top:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--70)">
	<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|40"}},"layout":{"type":"constrained","contentSize":"760px"}} -->
	<div class="wp-block-group">
		<!-- wp:heading {"textAlign":"center"} -->
		<h2 class="wp-block-heading has-text-align-center"><?php esc_html_e( 'A gift that keeps a collection for decades', 'cigarholic' ); ?></h2>
		<!-- /wp:heading -->

		<!-- wp:paragraph {"align":"center","fontSize":"large"} -->
		<p class="has-text-align-center has-large-font-size"><?php esc_html_e( 'A well-made humidor outlasts most gifts. Every Cigarholic gift set ships with a printed care and seasoning card, so the person opening it knows exactly how to start.', 'cigarholic' ); ?></p>
		<!-- /wp:paragraph -->

		<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->
		<div class="wp-block-buttons">
			<!-- wp:button -->
			<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( home_url( '/collections/gift-sets/' ) ); ?>"><?php esc_html_e( 'Shop gift sets', 'cigarholic' ); ?></a></div>
			<!-- /wp:button -->
		</div>
		<!-- /wp:buttons -->
	</div>
	<!-- /wp:group -->

	<!-- wp:group {"align":"wide","style":{"spacing":{"blockGap":"var:preset|spacing|50"}},"layout":{"type":"grid","minimumColumnWidth":"16rem"}} -->
	<div class="wp-block-group alignwide">
		<?php foreach ( $cigarholic_occasions as $cigarholic_occasion ) : ?>
		<!-- wp:group {"className":"is-style-card","style":{"spacing":{"blockGap":"var:preset|spacing|30"}},"layout":{"type":"constrained"}} -->
		<div class="wp-block-group is-style-card">
			<!-- wp:image {"aspectRatio":"4/3","scale":"cover","sizeSlug":"medium"} -->
			<figure class="wp-block-image size-medium"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/images/placeholder-product.svg' ) ); ?>" alt="<?php echo esc_attr( $cigarholic_occasion['alt'] ); ?>" style="aspect-ratio:4/3;object-fit:cover"/></figure>
			<!-- /wp:image -->

			<!-- wp:heading {"level":3,"fontSize":"large"} -->
			<h3 class="wp-block-heading has-large-font-size"><?php echo esc_html( $cigarholic_occasion['title'] ); ?></h3>
			<!-- /wp:heading -->

			<!-- wp:paragraph {"fontSize":"small"} -->
			<p class="has-small-font-size"><?php echo esc_html( $cigarholic_occasion['text'] ); ?></p>
			<!-- /wp:paragraph -->

			<!-- wp:paragraph {"fontSize":"small"} -->
			<p class="has-small-font-size"><a href="<?php echo esc_url( home_url( $cigarholic_occasion['url'] ) ); ?>"><?php echo esc_html( $cigarholic_occasion['link'] ); ?></a></p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:group -->
		<?php endforeach; ?>
	</div>
	<!-- /wp:group -->
</section>
<!-- /wp:group -->
