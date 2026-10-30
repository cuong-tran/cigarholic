<?php
/**
 * Title: Category grid
 * Slug: cigarholic/category-grid
 * Categories: cigarholic, featured
 * Template Types: front-page
 * Keywords: categories, collections, shop by category
 * Description: The six product categories as cards linking to /collections/<slug>/.
 *
 * Feature IDs: NAV-04 (homepage), CAT-02 (category tree), SEO-03 (collection URLs, ADR 0002).
 *
 * @package Cigarholic
 */

$cigarholic_categories = array(
	'desktop-humidors'    => array(
		'name' => __( 'Desktop Humidors', 'cigarholic' ),
		'text' => __( 'For the desk, the study or the bar: 25 to 150 cigars at a steady humidity.', 'cigarholic' ),
		'alt'  => __( 'Placeholder for a photo of a desktop humidor', 'cigarholic' ),
	),
	'cabinet-humidors'    => array(
		'name' => __( 'Cabinet Humidors', 'cigarholic' ),
		'text' => __( 'Furniture-sized storage for serious collections, built to stand in the room.', 'cigarholic' ),
		'alt'  => __( 'Placeholder for a photo of a cabinet humidor', 'cigarholic' ),
	),
	'travel-humidors'     => array(
		'name' => __( 'Travel Humidors', 'cigarholic' ),
		'text' => __( 'Compact, sealed and padded, so a few cigars arrive in the same condition they left.', 'cigarholic' ),
		'alt'  => __( 'Placeholder for a photo of a travel humidor', 'cigarholic' ),
	),
	'cigar-boxes'         => array(
		'name' => __( 'Cigar Boxes', 'cigarholic' ),
		'text' => __( 'Hand-finished wooden boxes for presentation, gifting and everyday storage.', 'cigarholic' ),
		'alt'  => __( 'Placeholder for a photo of a wooden cigar box', 'cigarholic' ),
	),
	'humidor-accessories' => array(
		'name' => __( 'Humidor Accessories', 'cigarholic' ),
		'text' => __( 'Hygrometers, humidifiers, seasoning kits and dividers that keep a humidor working.', 'cigarholic' ),
		'alt'  => __( 'Placeholder for a photo of humidor accessories', 'cigarholic' ),
	),
	'gift-sets'           => array(
		'name' => __( 'Gift Sets', 'cigarholic' ),
		'text' => __( 'A humidor and its accessories, boxed together and ready to give.', 'cigarholic' ),
		'alt'  => __( 'Placeholder for a photo of a humidor gift set', 'cigarholic' ),
	),
);
?>
<!-- wp:group {"tagName":"section","align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|70","bottom":"var:preset|spacing|70"},"blockGap":"var:preset|spacing|60"}},"layout":{"type":"constrained"}} -->
<section class="wp-block-group alignfull" style="padding-top:var(--wp--preset--spacing--70);padding-bottom:var(--wp--preset--spacing--70)">
	<!-- wp:heading {"textAlign":"center"} -->
	<h2 class="wp-block-heading has-text-align-center"><?php esc_html_e( 'Shop by category', 'cigarholic' ); ?></h2>
	<!-- /wp:heading -->

	<!-- wp:group {"align":"wide","style":{"spacing":{"blockGap":"var:preset|spacing|50"}},"layout":{"type":"grid","minimumColumnWidth":"16rem"}} -->
	<div class="wp-block-group alignwide">
		<?php foreach ( $cigarholic_categories as $cigarholic_slug => $cigarholic_category ) : ?>
		<!-- wp:group {"className":"is-style-card","style":{"spacing":{"blockGap":"var:preset|spacing|30"}},"layout":{"type":"constrained"}} -->
		<div class="wp-block-group is-style-card">
			<!-- wp:image {"aspectRatio":"4/3","scale":"cover","sizeSlug":"medium"} -->
			<figure class="wp-block-image size-medium"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/images/placeholder-product.svg' ) ); ?>" alt="<?php echo esc_attr( $cigarholic_category['alt'] ); ?>" style="aspect-ratio:4/3;object-fit:cover"/></figure>
			<!-- /wp:image -->

			<!-- wp:heading {"level":3,"fontSize":"large"} -->
			<h3 class="wp-block-heading has-large-font-size"><a href="<?php echo esc_url( home_url( '/collections/' . $cigarholic_slug . '/' ) ); ?>"><?php echo esc_html( $cigarholic_category['name'] ); ?></a></h3>
			<!-- /wp:heading -->

			<!-- wp:paragraph {"fontSize":"small"} -->
			<p class="has-small-font-size"><?php echo esc_html( $cigarholic_category['text'] ); ?></p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:group -->
		<?php endforeach; ?>
	</div>
	<!-- /wp:group -->
</section>
<!-- /wp:group -->
