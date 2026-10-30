<?php
/**
 * Title: FAQ
 * Slug: cigarholic/faq
 * Categories: cigarholic, text
 * Template Types: page
 * Keywords: faq, questions, help, details, accordion
 * Description: Frequently asked questions as native details blocks (keyboard and screen-reader friendly, no script). Starts at H2 so it sits under a page title.
 *
 * Feature IDs: CNT-02 (FAQ page), CNT-05 (care and seasoning), CMP-02 (origin wording), CMP-04 (accessibility).
 *
 * @package Cigarholic
 */

$cigarholic_faq = array(
	array(
		'q' => __( 'Do you sell cigars?', 'cigarholic' ),
		'a' => __( 'No. Cigarholic sells humidors, cigar boxes and humidor accessories only. We do not sell cigars, tobacco or nicotine products of any kind.', 'cigarholic' ),
	),
	array(
		'q' => __( 'Where are your humidors made?', 'cigarholic' ),
		'a' => __( 'Every piece is handcrafted in Vietnam, inspected before export and shipped to you from our warehouse in Houston, Texas.', 'cigarholic' ),
	),
	array(
		'q' => __( 'How do I season a new humidor?', 'cigarholic' ),
		'a' => __( 'Place a fresh humidifier or seasoning pack inside, close the lid and check the hygrometer daily. When it holds a steady 65 to 72 percent, usually after two to five days, it is ready for cigars. Do not wipe the cedar with water.', 'cigarholic' ),
	),
	array(
		'q' => __( 'How long does shipping take?', 'cigarholic' ),
		'a' => __( 'We hand orders to the carrier within 1 to 2 business days from Houston, Texas. Transit time depends on the carrier service you choose at checkout and your address; the tracking email shows the estimate.', 'cigarholic' ),
	),
	array(
		'q' => __( 'Do you ship outside the United States?', 'cigarholic' ),
		'a' => __( 'At the moment we ship within the contiguous United States only. The shipping policy page is updated if that changes.', 'cigarholic' ),
	),
	array(
		'q' => __( 'What is your return policy?', 'cigarholic' ),
		'a' => __( 'The returns and refunds page explains the return window, the condition we need the piece in and how to start a return. If something arrived damaged, contact us first and keep the packaging.', 'cigarholic' ),
	),
	array(
		'q' => __( 'How do I contact you?', 'cigarholic' ),
		'a' => __( 'Use the contact page or call +1 813 602 7585 during US business hours. Please have your order number ready if your question is about an order.', 'cigarholic' ),
	),
);
?>
<!-- wp:group {"tagName":"section","align":"full","className":"faq","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|70"},"blockGap":"var:preset|spacing|50"}},"layout":{"type":"constrained","contentSize":"760px"}} -->
<section class="wp-block-group alignfull faq" style="padding-top:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--70)">
	<!-- wp:heading -->
	<h2 class="wp-block-heading"><?php esc_html_e( 'Frequently asked questions', 'cigarholic' ); ?></h2>
	<!-- /wp:heading -->

	<!-- wp:group {"style":{"spacing":{"blockGap":"0"}},"layout":{"type":"constrained"}} -->
	<div class="wp-block-group">
		<?php foreach ( $cigarholic_faq as $cigarholic_entry ) : ?>
		<!-- wp:details -->
		<details class="wp-block-details"><summary><?php echo esc_html( $cigarholic_entry['q'] ); ?></summary>
			<!-- wp:paragraph -->
			<p><?php echo esc_html( $cigarholic_entry['a'] ); ?></p>
			<!-- /wp:paragraph -->
		</details>
		<!-- /wp:details -->
		<?php endforeach; ?>
	</div>
	<!-- /wp:group -->

	<!-- wp:paragraph -->
	<p><?php esc_html_e( 'Still have a question?', 'cigarholic' ); ?> <a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>"><?php esc_html_e( 'Contact us', 'cigarholic' ); ?></a></p>
	<!-- /wp:paragraph -->
</section>
<!-- /wp:group -->
