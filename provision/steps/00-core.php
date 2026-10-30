<?php
/**
 * Provisioning step: WordPress core settings (FND-03).
 *
 * @package Cigarholic
 */

use Cigarholic\Core\Provision\Context;

return static function ( Context $ctx ): void {
	$ctx->option( 'blogname', 'Cigarholic' );
	$ctx->option( 'blogdescription', 'Handcrafted wooden humidors, cigar boxes and humidor accessories' );
	$ctx->option( 'timezone_string', 'America/New_York' );
	$ctx->option( 'date_format', 'F j, Y' );
	$ctx->option( 'time_format', 'g:i a' );
	$ctx->option( 'start_of_week', '0' );
	$ctx->option( 'WPLANG', '' );
	$ctx->option( 'users_can_register', '0' );
	$ctx->option( 'default_comment_status', 'closed' );
	$ctx->option( 'default_ping_status', 'closed' );
	$ctx->option( 'comment_moderation', '1' );
	$ctx->option( 'uploads_use_yearmonth_folders', '1' );

	// URL structure (ADR 0002): /%postname%/ for pages and journal posts.
	if ( '/%postname%/' !== get_option( 'permalink_structure' ) ) {
		if ( ! $ctx->is_dry_run() ) {
			global $wp_rewrite;
			$wp_rewrite->set_permalink_structure( '/%postname%/' );
			$wp_rewrite->flush_rules( true );
		}
		$ctx->changed( 'permalink structure /%postname%/' );
	} else {
		$ctx->log( 'ok: permalink_structure' );
	}
};
