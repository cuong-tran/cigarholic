<?php
/**
 * Title: Trust bar
 * Slug: cigarholic/trust-bar
 * Categories: cigarholic, featured
 * Template Types: front-page, page
 * Keywords: trust, shipping, returns, warranty, secure checkout
 * Description: Five reasons to buy: handcrafted, US shipping, returns, warranty, secure checkout.
 *
 * Feature IDs: NAV-04 (homepage "why buy from us"), CNT-07 (trust bar), CMP-02 (origin wording).
 *
 * @package Cigarholic
 */

$cigarholic_trust_items = array(
	array(
		'title' => __( 'Handcrafted in Vietnam', 'cigarholic' ),
		'text'  => __( 'Every piece is made by hand from kiln-dried hardwood and inspected before it leaves the workshop.', 'cigarholic' ),
		'url'   => '/about/',
		'link'  => __( 'How we make them', 'cigarholic' ),
	),
	array(
		'title' => __( 'Ships from Texas', 'cigarholic' ),
		'text'  => __( 'Orders leave our warehouse in Houston, Texas within 1 to 2 business days, with tracking by email.', 'cigarholic' ),
		'url'   => '/shipping-policy/',
		'link'  => __( 'Shipping policy', 'cigarholic' ),
	),
	array(
		'title' => __( 'Straightforward returns', 'cigarholic' ),
		'text'  => __( 'Not the right fit for your collection? Our returns policy explains how to send it back.', 'cigarholic' ),
		'url'   => '/returns-refunds/',
		'link'  => __( 'Returns and refunds', 'cigarholic' ),
	),
	array(
		'title' => __( 'Craftsmanship warranty', 'cigarholic' ),
		'text'  => __( 'Joinery, hinges and seal are covered by our warranty. The warranty page has the terms.', 'cigarholic' ),
		'url'   => '/warranty/',
		'link'  => __( 'Warranty', 'cigarholic' ),
	),
	array(
		'title' => __( 'Secure checkout', 'cigarholic' ),
		'text'  => __( 'Cards, Apple Pay, Google Pay and PayPal, processed by Stripe and PayPal. Your card number never touches our servers.', 'cigarholic' ),
		'url'   => '/privacy-policy/',
		'link'  => __( 'Privacy policy', 'cigarholic' ),
	),
);
?>
<!-- wp:group {"tagName":"section","align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|70","bottom":"var:preset|spacing|70"},"blockGap":"var:preset|spacing|60"}},"backgroundColor":"neutral-1","layout":{"type":"constrained"}} -->
<section class="wp-block-group alignfull has-neutral-1-background-color has-background" style="padding-top:var(--wp--preset--spacing--70);padding-bottom:var(--wp--preset--spacing--70)">
	<!-- wp:heading {"textAlign":"center"} -->
	<h2 class="wp-block-heading has-text-align-center"><?php esc_html_e( 'Why buy from Cigarholic', 'cigarholic' ); ?></h2>
	<!-- /wp:heading -->

	<!-- wp:group {"align":"wide","style":{"spacing":{"blockGap":"var:preset|spacing|40"}},"layout":{"type":"grid","minimumColumnWidth":"14rem"}} -->
	<div class="wp-block-group alignwide">
		<?php foreach ( $cigarholic_trust_items as $cigarholic_item ) : ?>
		<!-- wp:group {"className":"is-style-card","style":{"spacing":{"blockGap":"var:preset|spacing|20"}},"layout":{"type":"constrained"}} -->
		<div class="wp-block-group is-style-card">
			<!-- wp:heading {"level":3,"fontSize":"medium"} -->
			<h3 class="wp-block-heading has-medium-font-size"><?php echo esc_html( $cigarholic_item['title'] ); ?></h3>
			<!-- /wp:heading -->

			<!-- wp:paragraph {"fontSize":"small"} -->
			<p class="has-small-font-size"><?php echo esc_html( $cigarholic_item['text'] ); ?></p>
			<!-- /wp:paragraph -->

			<!-- wp:paragraph {"fontSize":"small"} -->
			<p class="has-small-font-size"><a href="<?php echo esc_url( home_url( $cigarholic_item['url'] ) ); ?>"><?php echo esc_html( $cigarholic_item['link'] ); ?></a></p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:group -->
		<?php endforeach; ?>
	</div>
	<!-- /wp:group -->
</section>
<!-- /wp:group -->
