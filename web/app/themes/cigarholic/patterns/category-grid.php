<?php
/**
 * Title: Category grid
 * Slug: cigarholic/category-grid
 * Categories: cigarholic, featured
 * Template Types: front-page
 * Keywords: categories, collections, shop by category
 * Description: The six product categories in the canvas card layout (photo, serif title, short text, one link each to /collections/<slug>/), with a link to the whole shop.
 *
 * Feature IDs: NAV-04 (homepage), CAT-02 (category tree), SEO-03 (collection URLs, ADR 0002).
 *
 * @package Cigarholic
 */

$cigarholic_categories = array(
	'desktop-humidors'    => array(
		'name' => __( 'Desktop Humidors', 'cigarholic' ),
		'text' => __( 'For the desk, the study or the bar: 25 to 150 cigars at a steady humidity.', 'cigarholic' ),
		'alt'  => __( 'A walnut desktop humidor with its lid open beside a closed mahogany humidor', 'cigarholic' ),
		'link' => __( 'Shop desktop humidors', 'cigarholic' ),
	),
	'cabinet-humidors'    => array(
		'name' => __( 'Cabinet Humidors', 'cigarholic' ),
		'text' => __( 'Furniture-sized storage for serious collections, built to stand in the room.', 'cigarholic' ),
		'alt'  => __( 'A white oak cabinet humidor with its glass door ajar and Spanish cedar trays inside', 'cigarholic' ),
		'link' => __( 'Shop cabinet humidors', 'cigarholic' ),
	),
	'travel-humidors'     => array(
		'name' => __( 'Travel Humidors', 'cigarholic' ),
		'text' => __( 'Compact, sealed and padded, so a few cigars arrive in the same condition they left.', 'cigarholic' ),
		'alt'  => __( 'An open acacia travel humidor in front of two closed travel humidors', 'cigarholic' ),
		'link' => __( 'Shop travel humidors', 'cigarholic' ),
	),
	'cigar-boxes'         => array(
		'name' => __( 'Cigar Boxes', 'cigarholic' ),
		'text' => __( 'Hand-finished wooden boxes for presentation, gifting and everyday storage.', 'cigarholic' ),
		'alt'  => __( 'An open cherry cigar box with a green felt-lined lid beside two stacked wooden boxes', 'cigarholic' ),
		'link' => __( 'Shop cigar boxes', 'cigarholic' ),
	),
	'humidor-accessories' => array(
		'name' => __( 'Humidor Accessories', 'cigarholic' ),
		'text' => __( 'Hygrometers, humidifiers, seasoning kits and dividers that keep a humidor working.', 'cigarholic' ),
		'alt'  => __( 'Humidor accessories: an analog and a digital hygrometer, a humidifier jar, a humidifier bar and cedar dividers', 'cigarholic' ),
		'link' => __( 'Shop accessories', 'cigarholic' ),
	),
	'gift-sets'           => array(
		'name' => __( 'Gift Sets', 'cigarholic' ),
		'text' => __( 'A humidor and its accessories, boxed together and ready to give.', 'cigarholic' ),
		'alt'  => __( 'A gift set in a kraft box: a walnut travel humidor and a stainless cutter, with a care card', 'cigarholic' ),
		'link' => __( 'Shop gift sets', 'cigarholic' ),
	),
);
?>
<!-- wp:group {"tagName":"section","align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|70","bottom":"var:preset|spacing|70"},"blockGap":"var:preset|spacing|60"}},"layout":{"type":"constrained"}} -->
<section class="wp-block-group alignfull" style="padding-top:var(--wp--preset--spacing--70);padding-bottom:var(--wp--preset--spacing--70)">
	<!-- wp:group {"align":"wide","layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between","verticalAlignment":"bottom"}} -->
	<div class="wp-block-group alignwide">
		<!-- wp:heading -->
		<h2 class="wp-block-heading"><?php esc_html_e( 'Shop by category', 'cigarholic' ); ?></h2>
		<!-- /wp:heading -->

		<!-- wp:paragraph {"style":{"typography":{"fontWeight":"600"}}} -->
		<p style="font-weight:600"><a href="<?php echo esc_url( home_url( '/shop/' ) ); ?>"><?php esc_html_e( 'Shop all humidors', 'cigarholic' ); ?> &rarr;</a></p>
		<!-- /wp:paragraph -->
	</div>
	<!-- /wp:group -->

	<!-- wp:group {"align":"wide","style":{"spacing":{"blockGap":"var:preset|spacing|50"}},"layout":{"type":"grid","columnCount":3,"minimumColumnWidth":"16rem"}} -->
	<div class="wp-block-group alignwide">
		<?php foreach ( $cigarholic_categories as $cigarholic_slug => $cigarholic_category ) : ?>
		<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|30"}},"layout":{"type":"flex","orientation":"vertical","flexWrap":"nowrap"}} -->
		<div class="wp-block-group">
			<!-- wp:image {"aspectRatio":"3/2","scale":"cover","sizeSlug":"medium"} -->
			<figure class="wp-block-image size-medium"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/images/sample-category-' . $cigarholic_slug . '.webp' ) ); ?>" alt="<?php echo esc_attr( $cigarholic_category['alt'] ); ?>" style="aspect-ratio:3/2;object-fit:cover"/></figure>
			<!-- /wp:image -->

			<!-- wp:heading {"level":3,"fontSize":"x-large"} -->
			<h3 class="wp-block-heading has-x-large-font-size"><?php echo esc_html( $cigarholic_category['name'] ); ?></h3>
			<!-- /wp:heading -->

			<!-- wp:paragraph {"textColor":"neutral-2"} -->
			<p class="has-neutral-2-color has-text-color"><?php echo esc_html( $cigarholic_category['text'] ); ?></p>
			<!-- /wp:paragraph -->

			<!-- wp:paragraph {"style":{"typography":{"fontWeight":"600"}},"fontSize":"small"} -->
			<p class="has-small-font-size" style="font-weight:600"><a href="<?php echo esc_url( home_url( '/collections/' . $cigarholic_slug . '/' ) ); ?>"><?php echo esc_html( $cigarholic_category['link'] ); ?> &rarr;</a></p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:group -->
		<?php endforeach; ?>
	</div>
	<!-- /wp:group -->
</section>
<!-- /wp:group -->
