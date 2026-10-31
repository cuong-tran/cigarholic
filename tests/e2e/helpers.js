// Shared helpers for the smoke suite (FND-12).
import { expect } from '@playwright/test';

export const ADMIN = {
	user: process.env.E2E_ADMIN_USER || 'admin',
	password: process.env.E2E_ADMIN_PASSWORD || 'admin',
};

export const MAILPIT = process.env.MAILPIT_URL || 'http://localhost:8025';

/** Logs in through wp-login.php (two-factor is not enforced on the development stack). */
export async function login( page ) {
	await page.goto( '/wp/wp-login.php' );
	await page.getByLabel( 'Username or Email Address' ).fill( ADMIN.user );
	await page.getByLabel( 'Password', { exact: true } ).fill( ADMIN.password );
	await page.getByRole( 'button', { name: 'Log In' } ).click();
	await expect( page ).toHaveURL( /wp-admin/ );
}

/** Waits until the Turnstile widget (test keys auto-pass) has filled its hidden response field. */
export async function waitForTurnstile( form ) {
	const token = form.locator( 'input[name="cf-turnstile-response"]' );
	if ( ( await token.count() ) === 0 ) {
		return; // Turnstile not configured on this environment.
	}
	await expect.poll( async () => ( await token.first().inputValue() ).length, { timeout: 20_000 } ).toBeGreaterThan( 0 );
}

/** Mailpit messages whose subject matches, newest first. */
export async function mailpitMessages( request, subjectPart ) {
	const response = await request.get( `${ MAILPIT }/api/v1/search?query=${ encodeURIComponent( 'subject:"' + subjectPart + '"' ) }` );
	if ( ! response.ok() ) {
		return [];
	}
	const json = await response.json();
	return json.messages || [];
}

export function uniqueEmail( prefix ) {
	return `${ prefix }-${ Date.now() }@example.test`;
}
