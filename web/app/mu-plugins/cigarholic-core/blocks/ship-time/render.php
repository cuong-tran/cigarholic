<?php
/**
 * Server render of cigarholic/ship-time (SHP-03).
 *
 * @var array<string, mixed> $attributes Block attributes (none).
 * @var string               $content    Inner content (unused).
 * @var WP_Block             $block      Block instance; context postId names the product, if any.
 *
 * @package Cigarholic
 */

use Cigarholic\Core\Shipping\ShipTime;

$cigarholic_post_id = isset( $block->context['postId'] ) ? (int) $block->context['postId'] : (int) get_the_ID();
$cigarholic_product = null;
if ( $cigarholic_post_id > 0 && 'product' === get_post_type( $cigarholic_post_id ) && function_exists( 'wc_get_product' ) ) {
	$cigarholic_found   = wc_get_product( $cigarholic_post_id );
	$cigarholic_product = $cigarholic_found instanceof WC_Product ? $cigarholic_found : null;
	if ( null === $cigarholic_product ) {
		return;
	}
}

$cigarholic_sentence = ShipTime::sentence( $cigarholic_product );
if ( '' === $cigarholic_sentence ) {
	return;
}
?>
<p <?php echo get_block_wrapper_attributes( array( 'class' => 'cigarholic-ship-time' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by get_block_wrapper_attributes(). ?>><?php echo esc_html( $cigarholic_sentence ); ?></p>
