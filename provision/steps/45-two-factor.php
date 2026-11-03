<?php
/**
 * Provisioning step: two-factor methods (OPS-07).
 *
 * Only the authenticator app (TOTP) and backup codes are offered (Settings → Two-Factor). Emailed
 * codes are off: there is no transactional mail yet (Postmark, OWNER-ACTIONS B1), so a manager who
 * picked email would be locked out at the next login, and a mailbox is a weaker second factor than
 * a phone app. `cigarholic-core` (Security\TwoFactorRequired) requires one of these for every
 * administrator and shop manager.
 *
 * Feature IDs: FND-03, OPS-07.
 *
 * @package Cigarholic
 */

use Cigarholic\Core\Provision\Context;

return static function ( Context $ctx ): void {
	if ( ! class_exists( 'Two_Factor_Core' ) ) {
		$ctx->log( 'skip: the Two Factor plugin is not active (the deploy activates it)' );
		return;
	}
	$ctx->option( Two_Factor_Core::ENABLED_PROVIDERS_OPTION_KEY, array( 'Two_Factor_Totp', 'Two_Factor_Backup_Codes' ) );
};
