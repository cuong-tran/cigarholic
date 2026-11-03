<?php
/**
 * Provisioning step: brand logo, site icon and email logo (CNT-01, ORD-03).
 *
 * The files live in the theme (`assets/brand/`, made from the owner's artwork, masters in `brand/`).
 * The logo and the site icon are imported into the media library once per file version and set as
 * `site_logo` and `site_icon`, so the Site logo block, the favicon and the app icons use WordPress's
 * own sizes. The import is keyed by the file's SHA-256 (option `cigarholic_brand_assets`): an
 * unchanged file is never imported twice, a changed file is imported as a new attachment and the old
 * one stays in the media library. Modern Image Formats makes AVIF or WebP copies of the logo; the icon
 * stays PNG for iOS. The email logo is a PNG served from the theme (Outlook cannot show WebP or AVIF),
 * 200 px wide in the email.
 *
 * Feature IDs: CNT-01 (brand system), ORD-03 (branded emails), FND-03 (provisioning).
 *
 * @package Cigarholic
 */

use Cigarholic\Core\Provision\Context;

return static function ( Context $ctx ): void {
	$assets = array(
		'logo' => array(
			'file'   => 'cigarholic-logo.webp',
			'title'  => 'Cigarholic logo',
			'alt'    => 'Cigarholic',
			'option' => 'site_logo',
		),
		'icon' => array(
			'file'   => 'cigarholic-icon.png',
			'title'  => 'Cigarholic site icon',
			'alt'    => '',
			'option' => 'site_icon',
			// iOS reads only PNG apple-touch-icons, so the icon's sizes stay PNG (no AVIF/WebP copies).
			'keep'   => 'image/png',
			'icon'   => true,
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

		if ( ! $id || ( $known['sha'] ?? '' ) !== $sha || 'attachment' !== get_post_type( $id ) ) {
			if ( $ctx->is_dry_run() ) {
				$ctx->changed( sprintf( 'import %s into the media library', $asset['file'] ) );
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
			update_post_meta( $id, '_wp_attachment_image_alt', $asset['alt'] );
			if ( isset( $asset['icon'] ) ) {
				// Core's marker for site icons: kept out of the media grid like a Customizer-cropped icon.
				update_post_meta( $id, '_wp_attachment_context', 'site-icon' );
			}
			$state[ $key ] = array(
				'id'  => $id,
				'sha' => $sha,
			);
			update_option( 'cigarholic_brand_assets', $state, false );
			$ctx->changed( sprintf( 'import %s as attachment %d', $asset['file'], $id ) );
		}

		$ctx->option( $asset['option'], $id );
	}

	$ctx->option( 'woocommerce_email_header_image', get_theme_file_uri( 'assets/brand/cigarholic-email-logo.png' ) );
	$ctx->option( 'woocommerce_email_header_image_width', '200' );
};
