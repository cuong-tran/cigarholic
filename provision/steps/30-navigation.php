<?php
/**
 * Provisioning step: primary navigation (NAV-01).
 *
 * The header's Navigation block has no `ref`, so WordPress renders the most recently published
 * wp_navigation post. This step owns that post ("Primary"): it creates it, updates it in place and
 * removes every other wp_navigation post (WordPress creates a page-list fallback when a page is
 * rendered before any menu exists). Collection URLs follow ADR 0002; the categories themselves are
 * created by the catalog step (CAT-03).
 *
 * Feature IDs: FND-03, NAV-01.
 *
 * @package Cigarholic
 */

use Cigarholic\Core\Provision\Context;

return static function ( Context $ctx ): void {
	$id = $ctx->navigation(
		'Primary',
		'primary',
		array(
			array(
				'label' => 'Shop',
				'url'   => '/shop/',
			),
			array(
				'label' => 'Humidors',
				'url'   => '/collections/desktop-humidors/',
			),
			array(
				'label' => 'Cigar Boxes',
				'url'   => '/collections/cigar-boxes/',
			),
			array(
				'label' => 'Accessories',
				'url'   => '/collections/humidor-accessories/',
			),
			array(
				'label' => 'Gift Sets',
				'url'   => '/collections/gift-sets/',
			),
			array(
				'label' => 'Journal',
				'url'   => '/journal/',
			),
			array(
				'label' => 'About',
				'url'   => '/about/',
			),
			array(
				'label' => 'Contact',
				'url'   => '/contact/',
			),
		)
	);

	// Keep exactly one menu so the fallback cannot pick anything else.
	$others  = get_posts(
		array(
			'post_type'        => 'wp_navigation',
			'post_status'      => 'any',
			'posts_per_page'   => 50,
			'exclude'          => $id > 0 ? array( $id ) : array(),
			'suppress_filters' => false,
			'no_found_rows'    => true,
		)
	);
	$removed = 0;
	foreach ( $others as $post ) {
		if ( ! $post instanceof WP_Post || 'primary' === $post->post_name ) {
			continue;
		}
		if ( ! $ctx->is_dry_run() ) {
			wp_delete_post( $post->ID, true );
		}
		$ctx->changed( sprintf( 'navigation post #%d "%s" removed', $post->ID, $post->post_title ) );
		++$removed;
	}
	if ( 0 === $removed ) {
		$ctx->log( 'ok: no other navigation posts' );
	}
};
