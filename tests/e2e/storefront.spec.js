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
		await page.goto( '/shop/' );
		// First simple product: add from the archive when possible, else from the product page.
		const addButton = page.getByRole( 'button', { name: /add to cart/i } ).first();
		if ( await addButton.count() ) {
			await addButton.click();
		} else {
			await page.locator( '.wc-block-product a, li.product a' ).first().click();
			await page.getByRole( 'button', { name: /add to cart/i } ).click();
		}
		await page.goto( '/checkout/' );
		await expect( page.getByRole( 'heading', { level: 1, name: /checkout/i } ) ).toBeVisible();

		const email = uniqueEmail( 'buyer' );
		await page.getByLabel( 'Email address' ).fill( email );
		const shipping = page.locator( '#shipping, #shipping-fields, .wc-block-checkout__shipping-fields' );
		await page.getByLabel( 'First name' ).first().fill( 'Test' );
		await page.getByLabel( 'Last name' ).first().fill( 'Buyer' );
		await page.getByLabel( 'Address', { exact: true } ).first().fill( '8388 W Sam Houston Pkwy S');
		await page.getByLabel( 'City' ).first().fill( 'Houston' );
		await page.getByLabel( /State/ ).first().selectOption( { label: 'Texas' } ).catch( async () => {
			await page.getByLabel( /State/ ).first().fill( 'Texas' );
		} );
		await page.getByLabel( /ZIP/i ).first().fill( '77072' );
		await page.getByLabel( 'Phone' ).first().fill( '8136027585' );
		void shipping;

		await page.getByText( 'Cash on delivery', { exact: false } ).first().click();
		const terms = page.getByLabel( /terms/i ).first();
		if ( await terms.count() ) {
			await terms.check();
		}
		await page.getByRole( 'button', { name: /place order/i } ).click();

		await expect( page ).toHaveURL( /order-received/, { timeout: 30_000 } );
		await expect( page.getByText( /thank you/i ).first() ).toBeVisible();
		const orderNumber = ( await page.locator( '.wc-block-order-confirmation-summary-list-item__value, .woocommerce-order-overview__order strong' ).first().textContent() )?.trim();
		expect( orderNumber ).toBeTruthy();

		await expect.poll( async () => ( await mailpitMessages( request, `Order #${ orderNumber }` ) ).length, { timeout: 30_000 } ).toBeGreaterThan( 0 );
	} );
} );
