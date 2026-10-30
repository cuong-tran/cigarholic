<?php
/**
 * Title: Product content
 * Slug: cigarholic/product-content
 * Categories: cigarholic, woocommerce
 * Template Types: single-product
 * Inserter: true
 * Keywords: product, specifications, care, seasoning, shipping, returns, origin
 * Description: The product page structure below the gallery: description, spec table, what is in the box, care and seasoning, shipping and returns, Handcrafted in Vietnam. Used by the single-product template so every product reads the same way.
 *
 * Feature IDs: CAT-06 (product content structure), CAT-04 (spec table block from cigarholic-core),
 * CNT-05 (care and seasoning), CMP-02 (country of origin).
 *
 * The product summary is the short description shown beside the gallery in the
 * single-product template; this pattern starts with the full description.
 *
 * @package Cigarholic
 */

?>
<!-- wp:group {"tagName":"section","align":"wide","className":"product-content","style":{"spacing":{"margin":{"top":"var:preset|spacing|70"},"blockGap":"var:preset|spacing|60"}},"layout":{"type":"constrained"}} -->
<section class="wp-block-group alignwide product-content" style="margin-top:var(--wp--preset--spacing--70)">
	<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|40"}},"layout":{"type":"constrained"}} -->
	<div class="wp-block-group">
		<!-- wp:heading -->
		<h2 class="wp-block-heading"><?php esc_html_e( 'About this piece', 'cigarholic' ); ?></h2>
		<!-- /wp:heading -->

		<!-- wp:woocommerce/product-details {"align":"wide","hideTabTitle":true} /-->
	</div>
	<!-- /wp:group -->

	<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|40"}},"layout":{"type":"constrained"}} -->
	<div class="wp-block-group">
		<!-- wp:heading -->
		<h2 class="wp-block-heading"><?php esc_html_e( 'Specifications', 'cigarholic' ); ?></h2>
		<!-- /wp:heading -->

		<!-- wp:paragraph {"fontSize":"small","textColor":"neutral-2"} -->
		<p class="has-neutral-2-color has-text-color has-small-font-size"><?php esc_html_e( 'Dimensions are given in inches and centimetres, weight in pounds and kilograms. Capacity is counted in 50 ring gauge cigars.', 'cigarholic' ); ?></p>
		<!-- /wp:paragraph -->

		<!-- wp:cigarholic/spec-table /-->
	</div>
	<!-- /wp:group -->

	<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|40"}},"layout":{"type":"constrained"}} -->
	<div class="wp-block-group">
		<!-- wp:heading -->
		<h2 class="wp-block-heading"><?php esc_html_e( 'What is in the box', 'cigarholic' ); ?></h2>
		<!-- /wp:heading -->

		<!-- wp:paragraph -->
		<p><?php esc_html_e( 'The description above lists exactly what ships with this piece. Every humidor arrives in protective packaging with a printed care and seasoning card; humidification devices and hygrometers are included only where the description says so.', 'cigarholic' ); ?></p>
		<!-- /wp:paragraph -->
	</div>
	<!-- /wp:group -->

	<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|40"}},"layout":{"type":"constrained"}} -->
	<div class="wp-block-group">
		<!-- wp:heading -->
		<h2 class="wp-block-heading"><?php esc_html_e( 'Care and seasoning', 'cigarholic' ); ?></h2>
		<!-- /wp:heading -->

		<!-- wp:details -->
		<details class="wp-block-details"><summary><?php esc_html_e( 'Season it before the first use', 'cigarholic' ); ?></summary>
			<!-- wp:paragraph -->
			<p><?php esc_html_e( 'A new humidor needs a few days to take on moisture before it can hold a steady humidity. Place a fresh humidifier or seasoning pack inside with the lid closed, then check the hygrometer daily; once it reads a steady 65 to 72 percent, the humidor is ready. Never wipe the cedar with water.', 'cigarholic' ); ?></p>
			<!-- /wp:paragraph -->
		</details>
		<!-- /wp:details -->

		<!-- wp:details -->
		<details class="wp-block-details"><summary><?php esc_html_e( 'Keep humidity between 65 and 72 percent', 'cigarholic' ); ?></summary>
			<!-- wp:paragraph -->
			<p><?php esc_html_e( 'Refill the humidifier with distilled water or the solution it was designed for, and recalibrate the hygrometer twice a year. Open the lid only when you need to.', 'cigarholic' ); ?></p>
			<!-- /wp:paragraph -->
		</details>
		<!-- /wp:details -->

		<!-- wp:details -->
		<details class="wp-block-details"><summary><?php esc_html_e( 'Protect the wood', 'cigarholic' ); ?></summary>
			<!-- wp:paragraph -->
			<p><?php esc_html_e( 'Keep the humidor out of direct sunlight and away from heating vents. Dust the exterior with a dry, soft cloth; no sprays or polishes. Our care guide has the full routine.', 'cigarholic' ); ?> <a href="<?php echo esc_url( home_url( '/faq/' ) ); ?>"><?php esc_html_e( 'Read the care guide', 'cigarholic' ); ?></a></p>
			<!-- /wp:paragraph -->
		</details>
		<!-- /wp:details -->
	</div>
	<!-- /wp:group -->

	<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|40"}},"layout":{"type":"constrained"}} -->
	<div class="wp-block-group">
		<!-- wp:heading -->
		<h2 class="wp-block-heading"><?php esc_html_e( 'Shipping and returns', 'cigarholic' ); ?></h2>
		<!-- /wp:heading -->

		<!-- wp:paragraph -->
		<p><?php esc_html_e( 'Orders ship within 1 to 2 business days from our warehouse in Houston, Texas, and you receive the tracking number by email. Shipping options and costs are shown at checkout.', 'cigarholic' ); ?> <a href="<?php echo esc_url( home_url( '/shipping-policy/' ) ); ?>"><?php esc_html_e( 'Shipping policy', 'cigarholic' ); ?></a></p>
		<!-- /wp:paragraph -->

		<!-- wp:paragraph -->
		<p><?php esc_html_e( 'Returns, exchanges and refunds follow our returns policy, and the joinery, hinges and seal are covered by our warranty.', 'cigarholic' ); ?> <a href="<?php echo esc_url( home_url( '/returns-refunds/' ) ); ?>"><?php esc_html_e( 'Returns and refunds', 'cigarholic' ); ?></a> &middot; <a href="<?php echo esc_url( home_url( '/warranty/' ) ); ?>"><?php esc_html_e( 'Warranty', 'cigarholic' ); ?></a></p>
		<!-- /wp:paragraph -->
	</div>
	<!-- /wp:group -->

	<!-- wp:group {"className":"is-style-card origin-notice","style":{"spacing":{"blockGap":"var:preset|spacing|20"}},"layout":{"type":"constrained"}} -->
	<div class="wp-block-group is-style-card origin-notice">
		<!-- wp:heading -->
		<h2 class="wp-block-heading"><?php esc_html_e( 'Handcrafted in Vietnam', 'cigarholic' ); ?></h2>
		<!-- /wp:heading -->

		<!-- wp:paragraph -->
		<p><?php esc_html_e( 'Country of origin: Vietnam. This piece was made by hand in our Vietnam workshop, inspected before export and shipped to you from our warehouse in Texas.', 'cigarholic' ); ?></p>
		<!-- /wp:paragraph -->
	</div>
	<!-- /wp:group -->
</section>
<!-- /wp:group -->
