// OPS-07: the familiar admin addresses lead to the WordPress login page. WordPress lives in /wp/
// (Bedrock); Nginx redirects /wp-login.php and /wp-admin there (docker/nginx, infra/server/nginx).
import { test, expect } from '@playwright/test';

test.describe( 'short admin URLs', () => {
	for ( const path of [ '/wp-login.php', '/wp-admin', '/wp-admin/' ] ) {
		test( `${ path } leads to the login form`, async ( { page } ) => {
			await page.goto( path );
			await expect( page ).toHaveURL( /\/wp\/wp-login\.php/ );
			await expect( page.locator( '#loginform' ) ).toBeVisible();
		} );
	}

	test( 'redirects keep the path and the query string', async ( { request } ) => {
		const cases = {
			'/wp-login.php?action=lostpassword': '/wp/wp-login.php?action=lostpassword',
			'/wp-admin/edit.php?post_type=product': '/wp/wp-admin/edit.php?post_type=product',
		};
		for ( const [ from, to ] of Object.entries( cases ) ) {
			const response = await request.get( from, { maxRedirects: 0 } );
			expect( response.status(), from ).toBe( 301 );
			const location = new URL( response.headers().location, 'http://placeholder' );
			expect( location.pathname + location.search, from ).toBe( to );
		}
	} );
} );
