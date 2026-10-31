// Browse → product → cart → checkout (Cash on delivery, development only) → order received → email in Mailpit.
// FND-12 smoke subset; skipped while the catalog has no products (seed data arrives with CAT-03).
import { test, expect } from '@playwright/test';
import { login, mailpitMessages, uniqueEmail } from './helpers.js';

test.describe( 'storefront', () => {
	test.beforeEach( async ( { page } ) => {
		// The store is behind coming-soon mode; shop as a logged-in administrator.
		await login( page );
		await page.goto( '/shop/' );
		const products = page.locator( '.wc-block-product, li.product, .wc-block-product-template__responsive' );
		test.skip( ( await products.count() ) === 0, 'no products in the catalog yet' );
	} );

	test( 'shop, product page with spec table, cart', async ( { page } ) => {
		await page.goto( '/shop/' );
		await expect( page.getByRole( 'heading', { level: 1, name: 'Shop' } ) ).toBeVisible();
		await page.locator( '.wc-block-product a.wc-block-components-product-name, li.product a.woocommerce-loop-product__link, .wc-block-product-template a' ).first().click();
		await expect( page ).toHaveURL( /\/products\// );
		await expect( page.getByRole( 'heading', { level: 1 } ) ).toBeVisible();
		await expect( page.locator( 'table' ).filter( { hasText: /capacity|dimensions|weight/i } ).first() ).toBeVisible();
	} );

	test( 'checkout with Cash on delivery produces an order and an email', async ( { page, request } ) => {
		// The test user's cart persists server-side between runs: start from an empty cart (Store API).
		const cartResponse = await page.request.get( '/wp-json/wc/store/v1/cart' );
		await page.request.delete( '/wp-json/wc/store/v1/cart/items', { headers: { Nonce: cartResponse.headers().nonce || '' } } );

		// Add the first simple product (variable products show "Select options" on the archive).
		await page.goto( '/shop/' );
		const simpleAdd = page.getByRole( 'button', { name: /^add to cart/i } ).first();
		// Wait for the Store API call the button makes; navigating earlier would leave the cart empty.
		const added = page.waitForResponse( ( r ) => r.url().includes( '/wc/store/v1/' ) && r.request().method() === 'POST' && r.ok(), { timeout: 20_000 } );
		if ( await simpleAdd.count() ) {
			await simpleAdd.click();
		} else {
			await page.locator( '.wc-block-product a, li.product a' ).first().click();
			await page.getByRole( 'button', { name: /add to cart/i } ).click();
		}
		await added;
		await expect( page.locator( '.wc-block-mini-cart__badge' ).first() ).toHaveText( '1', { timeout: 15_000 } );

		await page.goto( '/cart/' );
		await expect( page.getByRole( 'heading', { level: 1, name: 'Cart' } ) ).toBeVisible();
		await expect( page.getByRole( 'table', { name: 'Products in cart' } ).getByRole( 'row' ).nth( 1 ) ).toBeVisible();
		await page.getByRole( 'link', { name: /proceed to checkout/i } ).click();
		await expect( page ).toHaveURL( /\/checkout\// );

		const email = uniqueEmail( 'buyer' );
		await page.getByLabel( 'Email address' ).fill( email );
		// A logged-in test user with a saved address sees an address card instead of the fields.
		const editAddress = page.getByRole( 'button', { name: /edit shipping address/i } );
		if ( await editAddress.count() ) {
			await editAddress.click();
		}
		await page.getByLabel( 'First name' ).first().fill( 'Test' );
		await page.getByLabel( 'Last name' ).first().fill( 'Buyer' );
		await page.getByLabel( 'Address', { exact: true } ).first().fill( '8388 W Sam Houston Pkwy S' );
		await page.getByLabel( 'City' ).first().fill( 'Houston' );
		const state = page.getByLabel( /State/ ).first();
		if ( await state.evaluate( ( el ) => el.tagName === 'SELECT' ) ) {
			await state.selectOption( { label: 'Texas' } );
		} else {
			await state.fill( 'Texas' );
			await page.getByRole( 'option', { name: 'Texas' } ).first().click();
		}
		await page.getByLabel( /ZIP/i ).first().fill( '77072' );
		await page.getByLabel( 'Phone' ).first().fill( '8136027585' );

		// Cash on delivery is enabled by scripts/runtime-up.sh in development only (ADR 0004).
		const cod = page.getByRole( 'radio', { name: /cash on delivery/i } );
		if ( await cod.count() ) {
			await cod.check();
		} else {
			await page.getByText( /cash on delivery/i ).first().click();
		}
		const terms = page.getByRole( 'checkbox', { name: /terms/i } );
		if ( await terms.count() ) {
			await terms.check();
		}
		await page.getByRole( 'button', { name: /place order/i } ).click();

		await expect( page ).toHaveURL( /order-received/, { timeout: 30_000 } );
		await expect( page.getByText( /thank you/i ).first() ).toBeVisible();
		const orderNumber = ( await page.locator( '.wc-block-order-confirmation-summary-list-item__value' ).first().textContent() )?.trim();
		expect( orderNumber ).toMatch( /^\d+$/ );

		// WooCommerce sends "[Cigarholic]: New order #N" to the shop and "Your Cigarholic order has been received!" to the buyer.
		await expect.poll( async () => ( await mailpitMessages( request, `#${ orderNumber }` ) ).length, { timeout: 30_000 } ).toBeGreaterThan( 0 );
		await expect.poll( async () => ( await mailpitMessages( request, 'order has been received' ) ).length, { timeout: 30_000 } ).toBeGreaterThan( 0 );
	} );
} );
