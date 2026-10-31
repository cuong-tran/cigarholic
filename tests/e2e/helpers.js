// Shared helpers for the smoke suite (FND-12).
import { expect } from '@playwright/test';

export const ADMIN = {
	user: process.env.E2E_ADMIN_USER || 'admin',
	password: process.env.E2E_ADMIN_PASSWORD || 'admin',
};

export const MAILPIT = process.env.MAILPIT_URL || 'http://localhost:8025';

/** Logs in through wp-login.php (two-factor is not enforced on the development stack). */
export async function login( page ) {
	// WordPress refuses logins without its test cookie; set it up front instead of racing the page script.
	const base = new URL( page.context()._options?.baseURL || process.env.E2E_BASE_URL || 'http://localhost:8080' );
	await page.context().addCookies( [ { name: 'wordpress_test_cookie', value: 'WP Cookie check', domain: base.hostname, path: '/' } ] );
	// Logged-in admins get fixed chrome customers never see (admin bar, WooCommerce's coming-soon banner);
	// it can cover elements Playwright scrolls into view and swallow clicks, so hide it on every page.
	await page.addInitScript(
		"document.addEventListener('DOMContentLoaded', () => { const s = document.createElement('style'); s.textContent = '#wpadminbar, #coming-soon-footer-banner { display: none !important; } html { margin-top: 0 !important; }'; document.head.appendChild(s); });"
	);
	for ( let attempt = 1; attempt <= 2; attempt++ ) {
		await page.goto( '/wp/wp-login.php' );
		await page.getByLabel( 'Username or Email Address' ).fill( ADMIN.user );
		await page.getByLabel( 'Password', { exact: true } ).fill( ADMIN.password );
		await page.getByRole( 'button', { name: 'Log In' } ).click();
		await page.waitForLoadState( 'domcontentloaded' );
		if ( /wp-admin/.test( page.url() ) ) {
			return;
		}
		const error = await page.locator( '#login_error' ).textContent().catch( () => '' );
		if ( attempt === 2 ) {
			throw new Error( `Login failed: ${ ( error || page.url() ).trim() }` );
		}
	}
}

/** Waits until the Turnstile widget (test keys auto-pass) has filled its hidden response field. */
export async function waitForTurnstile( form ) {
	const token = form.locator( 'input[name="cf-turnstile-response"]' );
	if ( ( await token.count() ) === 0 ) {
		return; // Turnstile not configured on this environment.
	}
	await expect.poll( async () => ( await token.first().inputValue() ).length, { timeout: 20_000 } ).toBeGreaterThan( 0 );
}

/** Recent Mailpit messages whose subject contains the text (case-insensitive), newest first. */
export async function mailpitMessages( request, subjectPart ) {
	const response = await request.get( `${ MAILPIT }/api/v1/messages?limit=50` );
	if ( ! response.ok() ) {
		return [];
	}
	const json = await response.json();
	const needle = subjectPart.toLowerCase();
	return ( json.messages || [] ).filter( ( m ) => ( m.Subject || '' ).toLowerCase().includes( needle ) );
}

export function uniqueEmail( prefix ) {
	return `${ prefix }-${ Date.now() }@example.test`;
}
