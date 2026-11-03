<?php
/**
 * Provisioning step: brand logo, site icon and email logo (CNT-01, ORD-03).
 *
 * The files live in the theme (`assets/brand/`, made from the owner's artwork, masters in `brand/`).
 * The logo, the site icon and the email logo are imported into the media library once per file
 * version and set as `site_logo`, `site_icon` and the WooCommerce email header image (200 px wide), so
 * WordPress makes their sizes and their URLs survive a code rollback. The import is keyed by the file's
 * SHA-256 (option `cigarholic_brand_assets`): an unchanged file is never imported twice; a changed
 * file, or an imported copy that was deleted, trashed or edited in the media library, is imported
 * again and the old attachment stays. Alt text and the site-icon marker are enforced on every run.
 * Modern Image Formats makes AVIF or WebP copies of the logo; the icon and the email logo stay PNG
 * (iOS touch icons, and Outlook shows neither WebP nor AVIF).
 *
 * Feature IDs: CNT-01 (brand system), ORD-03 (branded emails), FND-03 (provisioning).
 *
 * @package Cigarholic
 */

use Cigarholic\Core\Provision\Context;

return static function ( Context $ctx ): void {
	$assets = array(
		'logo'  => array(
			'file'   => 'cigarholic-logo.webp',
			'title'  => 'Cigarholic logo',
			'alt'    => 'Cigarholic',
			'option' => 'site_logo',
			'value'  => 'id',
		),
		'icon'  => array(
			'file'   => 'cigarholic-icon.png',
			'title'  => 'Cigarholic site icon',
			'alt'    => '',
			'option' => 'site_icon',
			'value'  => 'id',
			// iOS reads only PNG apple-touch-icons, so the icon's sizes stay PNG (no AVIF/WebP copies).
			'keep'   => 'image/png',
			'icon'   => true,
		),
		// Email clients need PNG (Outlook shows neither WebP nor AVIF). An upload URL lives in the shared
		// uploads directory, so it keeps working when a deploy rolls the code back; a theme URL would not.
		'email' => array(
			'file'   => 'cigarholic-email-logo.png',
			'title'  => 'Cigarholic email logo',
			'alt'    => 'Cigarholic',
			'option' => 'woocommerce_email_header_image',
			'value'  => 'url',
			'keep'   => 'image/png',
		),
	);

	$state = get_option( 'cigarholic_brand_assets', array() );
	$state = is_array( $state ) ? $state : array();

	foreach ( $assets as $key => $asset ) {
		$path = get_theme_file_path( 'assets/brand/' . $asset['file'] );
		if ( ! is_readable( $path ) ) {
			$ctx->log( sprintf( 'skip: %s is not in the active theme', $asset['file'] ) );
			continue;
		}
		$sha   = (string) hash_file( 'sha256', $path );
		$known = isset( $state[ $key ] ) && is_array( $state[ $key ] ) ? $state[ $key ] : array();
		$id    = isset( $known['id'] ) ? (int) $known['id'] : 0;

		// The imported copy is reused only while it is the same version, still a live attachment, not
		// edited in the media library (an edit saves a new file name) and its file is still on disk.
		$usable = $id
			&& ( $known['sha'] ?? '' ) === $sha
			&& 'attachment' === get_post_type( $id )
			&& 'trash' !== get_post_status( $id ) // An unattached upload reports 'publish', a trashed one 'trash'.
			&& ( $known['file'] ?? '' ) === (string) get_post_meta( $id, '_wp_attached_file', true )
			&& is_readable( (string) get_attached_file( $id ) );

		if ( ! $usable ) {
			if ( $ctx->is_dry_run() ) {
				$ctx->changed( sprintf( 'import %s into the media library', $asset['file'] ) );
				$ctx->changed( sprintf( 'option %s', $asset['option'] ) );
				continue;
			}
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';

			// media_handle_sideload() moves its input, so it gets a temporary copy, never the theme file.
			$tmp = wp_tempnam( $asset['file'] );
			if ( ! $tmp || ! copy( $path, $tmp ) ) {
				throw new RuntimeException( sprintf( 'Could not copy %s to a temporary file.', esc_html( $asset['file'] ) ) );
			}
			// Modern Image Formats (OPS-09) turns uploads into AVIF or WebP; an asset that must keep its
			// format opts out for its own import only.
			$keep_format = static function ( array $transforms ) use ( $asset ): array {
				$transforms[ $asset['keep'] ] = array( $asset['keep'] );
				return $transforms;
			};
			if ( isset( $asset['keep'] ) ) {
				add_filter( 'webp_uploads_upload_image_mime_transforms', $keep_format );
			}
			// The site icon gets core's own sizes (32, 180, 192, 270, 512), as when it is set in the Customizer.
			require_once ABSPATH . 'wp-admin/includes/class-wp-site-icon.php';
			$site_icon = new WP_Site_Icon();
			if ( isset( $asset['icon'] ) ) {
				add_filter( 'intermediate_image_sizes_advanced', array( $site_icon, 'additional_sizes' ) );
			}
			$id = media_handle_sideload(
				array(
					'name'     => $asset['file'],
					'tmp_name' => $tmp,
				),
				0,
				$asset['title']
			);
			remove_filter( 'webp_uploads_upload_image_mime_transforms', $keep_format );
			remove_filter( 'intermediate_image_sizes_advanced', array( $site_icon, 'additional_sizes' ) );
			if ( is_wp_error( $id ) ) {
				wp_delete_file( $tmp );
				throw new RuntimeException( sprintf( 'Could not import %s: %s', esc_html( $asset['file'] ), esc_html( $id->get_error_message() ) ) );
			}
			$state[ $key ] = array(
				'id'   => $id,
				'sha'  => $sha,
				'file' => (string) get_post_meta( $id, '_wp_attached_file', true ),
			);
			update_option( 'cigarholic_brand_assets', $state, false );
			$ctx->changed( sprintf( 'import %s as attachment %d', $asset['file'], $id ) );
		}

		// Enforced on every run, so a failed write or a later edit is put back.
		$meta = array( '_wp_attachment_image_alt' => $asset['alt'] );
		if ( isset( $asset['icon'] ) ) {
			// Core's marker for site icons: kept out of the media grid like a Customizer-cropped icon.
			$meta['_wp_attachment_context'] = 'site-icon';
		}
		foreach ( $meta as $meta_key => $meta_value ) {
			if ( (string) get_post_meta( $id, $meta_key, true ) !== $meta_value ) {
				if ( ! $ctx->is_dry_run() ) {
					update_post_meta( $id, $meta_key, $meta_value );
				}
				$ctx->changed( sprintf( 'attachment %d %s', $id, $meta_key ) );
			}
		}

		$ctx->option( $asset['option'], 'url' === $asset['value'] ? (string) wp_get_attachment_url( $id ) : $id );
	}

	$ctx->option( 'woocommerce_email_header_image_width', '200' );
};
