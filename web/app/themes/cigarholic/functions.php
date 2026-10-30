<?php
/**
 * Cigarholic block theme: setup only.
 *
 * The theme is presentation only. Tokens live in theme.json, markup in
 * templates/, parts/ and patterns/. Anything dynamic (announcement bar,
 * signup form, spec table) is a block provided by the cigarholic-core
 * mu-plugin and referenced by name; the templates render without it.
 *
 * Feature IDs: FND-05 (theme), CNT-01 (brand system), NAV-01 to NAV-04
 * (header, announcement bar, footer, homepage), CNT-04 (coming soon),
 * CAT-06 (product content structure), CHK-06 (order received next steps),
 * CMP-04 (accessibility).
 *
 * @package Cigarholic
 */

namespace Cigarholic\Theme;

/**
 * Theme supports and WooCommerce integration.
 *
 * Feature IDs: FND-05, CNT-01, CAT-05 (gallery zoom, lightbox, slider).
 *
 * The skip link is printed by core for block themes
 * (wp_enqueue_block_template_skip_link), so it is not duplicated here.
 *
 * @return void
 */
function setup(): void {
	load_theme_textdomain( 'cigarholic', get_template_directory() . '/languages' );

	add_theme_support( 'wp-block-styles' );
	add_theme_support( 'editor-styles' );
	add_editor_style( 'style.css' );
	add_theme_support( 'responsive-embeds' );

	add_theme_support( 'woocommerce' );
	add_theme_support( 'wc-product-gallery-zoom' );
	add_theme_support( 'wc-product-gallery-lightbox' );
	add_theme_support( 'wc-product-gallery-slider' );

	// Only the theme's own patterns (and WooCommerce's) are offered: no core
	// library, no remote pattern directory (CNT-01: manager edits content, not design).
	remove_theme_support( 'core-block-patterns' );
}
add_action( 'after_setup_theme', __NAMESPACE__ . '\\setup' );

add_filter( 'should_load_remote_block_patterns', '__return_false' );

/**
 * Pattern category for everything under patterns/ (CNT-01, NAV-04).
 *
 * @return void
 */
function register_pattern_categories(): void {
	register_block_pattern_category(
		'cigarholic',
		array(
			'label'       => __( 'Cigarholic', 'cigarholic' ),
			'description' => __( 'Sections for the Cigarholic store: hero, categories, best sellers, craftsmanship, trust bar, product content, gifting, newsletter and FAQ.', 'cigarholic' ),
		)
	);
}
add_action( 'init', __NAMESPACE__ . '\\register_pattern_categories' );

/**
 * Block styles used by the patterns (CNT-01).
 *
 * "Card" is a bordered surface for the category grid, the trust bar and the
 * order-received next steps. It is declared as theme.json-style data so no
 * extra stylesheet is needed.
 *
 * @return void
 */
function register_block_styles(): void {
	register_block_style(
		'core/group',
		array(
			'name'       => 'card',
			'label'      => __( 'Card', 'cigarholic' ),
			'style_data' => array(
				'border'  => array(
					'color'  => 'var:preset|color|neutral-1',
					'style'  => 'solid',
					'width'  => '1px',
					'radius' => '4px',
				),
				'color'   => array(
					'background' => 'var:preset|color|base',
				),
				'spacing' => array(
					'padding' => array(
						'top'    => 'var:preset|spacing|50',
						'right'  => 'var:preset|spacing|50',
						'bottom' => 'var:preset|spacing|50',
						'left'   => 'var:preset|spacing|50',
					),
				),
				'shadow'  => 'var:preset|shadow|soft',
			),
		)
	);
}
add_action( 'init', __NAMESPACE__ . '\\register_block_styles' );

/**
 * Front-end stylesheet (focus, target sizes, reduced motion, print).
 *
 * Feature IDs: FND-05, CMP-04.
 *
 * @return void
 */
function enqueue_styles(): void {
	wp_enqueue_style(
		'cigarholic-style',
		get_stylesheet_uri(),
		array(),
		(string) wp_get_theme()->get( 'Version' )
	);
}
add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\\enqueue_styles' );
