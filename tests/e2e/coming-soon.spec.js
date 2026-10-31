// CNT-04: anonymous visitors see the coming-soon page and can sign up; admins see the store.
import { test, expect } from '@playwright/test';
import { login, waitForTurnstile, uniqueEmail } from './helpers.js';

test.describe( 'coming soon', () => {
	test.beforeEach( async ( { request } ) => {
		const home = await request.get( '/' );
		const html = await home.text();
		test.skip( ! html.includes( 'cigarholic-signup__form' ), 'coming-soon mode is off on this environment' );
	} );

	test( 'anonymous visitor sees the launch page with a working signup form', async ( { page } ) => {
		await page.goto( '/' );
		await expect( page.getByRole( 'main' ) ).toBeVisible();
		// The launch page has no navigation and exactly one signup form; the store has both.
		await expect( page.getByRole( 'navigation', { name: 'Primary' } ) ).toHaveCount( 0 );
		await expect( page.locator( '.cigarholic-signup__form' ) ).toHaveCount( 1 );
		await expect( page.getByText( 'HuyLinh LLC' ) ).toBeVisible();

		const form = page.locator( '.cigarholic-signup__form' );
		await expect( form.getByLabel( 'Email address' ) ).toBeVisible();
		await waitForTurnstile( form );

		const email = uniqueEmail( 'visitor' );
		await form.getByLabel( 'Email address' ).fill( email );
		await form.getByRole( 'button' ).click();
		await expect( form.locator( '.cigarholic-signup__status' ) ).toContainText( 'Thank you', { timeout: 15_000 } );

		// The same address again is accepted without an error (deduplicated).
		await page.goto( '/' );
		const again = page.locator( '.cigarholic-signup__form' );
		await waitForTurnstile( again );
		await again.getByLabel( 'Email address' ).fill( email );
		await again.getByRole( 'button' ).click();
		await expect( again.locator( '.cigarholic-signup__status' ) ).toContainText( 'Thank you', { timeout: 15_000 } );
	} );

	test( 'an invalid email is refused with a message', async ( { page } ) => {
		await page.goto( '/' );
		const form = page.locator( '.cigarholic-signup__form' ).first();
		await waitForTurnstile( form );
		await form.getByLabel( 'Email address' ).fill( 'not-an-email' );
		await form.getByRole( 'button' ).click();
		await expect( form.locator( '.cigarholic-signup__status' ) ).toContainText( 'valid email', { timeout: 15_000 } );
	} );

	test( 'a logged-in administrator sees the store', async ( { page } ) => {
		await login( page );
		await page.goto( '/' );
		await expect( page.getByRole( 'navigation', { name: 'Primary' } ) ).toBeVisible();
		await expect( page.getByRole( 'heading', { level: 1 } ) ).toBeVisible();
		await expect( page.getByRole( 'link', { name: /shop/i } ).first() ).toBeVisible();
	} );
} );
