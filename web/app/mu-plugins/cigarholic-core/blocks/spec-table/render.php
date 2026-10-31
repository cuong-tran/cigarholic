<?php
/**
 * Server render of cigarholic/spec-table (CAT-04).
 *
 * @var array<string, mixed> $attributes Block attributes (none).
 * @var string               $content    Inner content (unused).
 * @var WP_Block             $block      Block instance; context postId names the product.
 *
 * @package Cigarholic
 */

use Cigarholic\Core\Catalog\SpecTable;

$cigarholic_post_id = isset( $block->context['postId'] ) ? (int) $block->context['postId'] : (int) get_the_ID();
$cigarholic_product = $cigarholic_post_id > 0 && function_exists( 'wc_get_product' ) ? wc_get_product( $cigarholic_post_id ) : false;

if ( ! $cigarholic_product instanceof WC_Product ) {
	return;
}

echo SpecTable::render( $cigarholic_product, get_block_wrapper_attributes( array( 'class' => 'cigarholic-spec-table' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in SpecTable::render().
