<?php
/**
 * Server render of cigarholic/order-next-steps (CHK-06).
 *
 * The card is a core/group with the theme's Card style, rendered through do_blocks() so WordPress
 * applies the style variation (it gives each rendered instance its own `is-style-card--N` class).
 * An unpaid order renders nothing, not an empty card.
 *
 * @var array<string, mixed> $attributes Block attributes.
 * @var string               $content    Inner content (unused).
 * @var WP_Block             $block      Block instance.
 *
 * @package Cigarholic
 */

use Cigarholic\Core\Checkout\OrderNextSteps;

$cigarholic_next = OrderNextSteps::steps( OrderNextSteps::current_order() );
if ( array() === $cigarholic_next['steps'] ) {
	return;
}
$cigarholic_links = array(
	'a' => array( 'href' => array() ),
);

$cigarholic_inner = '<h2 class="wp-block-heading">' . esc_html__( 'What happens next', 'cigarholic' ) . '</h2><ul class="wp-block-list">';
foreach ( $cigarholic_next['steps'] as $cigarholic_step ) {
	$cigarholic_inner .= '<li><strong>' . esc_html( $cigarholic_step['title'] ) . '</strong> ' . wp_kses( $cigarholic_step['html'], $cigarholic_links ) . '</li>';
}
$cigarholic_inner .= '</ul>';
if ( '' !== $cigarholic_next['tip'] ) {
	$cigarholic_inner .= '<p class="has-neutral-2-color has-text-color has-small-font-size">' . wp_kses( $cigarholic_next['tip'], $cigarholic_links ) . '</p>';
}

$cigarholic_classes = 'is-style-card order-next-steps wp-block-cigarholic-order-next-steps';
$cigarholic_group   = array(
	'className' => $cigarholic_classes,
	'layout'    => array( 'type' => 'default' ),
);
$cigarholic_align   = isset( $attributes['align'] ) && is_string( $attributes['align'] ) ? sanitize_html_class( $attributes['align'] ) : '';
if ( '' !== $cigarholic_align ) {
	$cigarholic_group['align'] = $cigarholic_align;
	$cigarholic_classes       .= ' align' . $cigarholic_align;
}

// Every part of $cigarholic_inner is escaped above; do_blocks() only adds the group's support classes and styles.
echo do_blocks( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts.
	'<!-- wp:group ' . wp_json_encode( $cigarholic_group ) . ' --><div class="wp-block-group ' . esc_attr( $cigarholic_classes ) . '">'
	. $cigarholic_inner
	. '</div><!-- /wp:group -->'
);
