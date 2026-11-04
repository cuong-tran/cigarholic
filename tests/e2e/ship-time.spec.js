// Ship-time promise (SHP-03): one setting, shown on the product page and under every in-stock item in
// the cart, the checkout summary and the mini cart. Default 1–2 business days.
import { test, expect } from '@playwright/test';
import { login } from './helpers.js';

const PRODUCT = 'sample-mahogany-desktop-humidor-100';
const PROMISE = 'Ships in 1–2 business days from our US warehouse in Houston, Texas.';
const LINE = 'Ships in 1–2 business days';

test.describe( 'ship time', () => {
	test.beforeEach( async ( { page } ) => {
		await login( page );
		const found = await page.request.get( `/wp-json/wc/store/v1/products?slug=${ PRODUCT }` );
		test.skip( ! found.ok() || ( await found.json() ).length === 0, 'sample catalog not seeded' );
	} );

	test( 'product page states the ship time once, under the price and in the shipping section', async ( { page } ) => {
		await page.goto( `/products/${ PRODUCT }/` );
		const summary = page.locator( '.wp-block-column' ).filter( { has: page.getByRole( 'heading', { level: 1 } ) } );
		await expect( summary.locator( '.cigarholic-ship-time' ) ).toHaveText( PROMISE );
		await expect( summary.getByText( 'Handcrafted in Vietnam.', { exact: true } ) ).toBeVisible();
		const shipping = page.locator( '.wp-block-group' ).filter( { has: page.getByRole( 'heading', { name: 'Shipping and returns' } ) } ).last();
		await expect( shipping.locator( '.cigarholic-ship-time' ) ).toHaveText( PROMISE );
		// The old hard-coded wording is gone everywhere on the page.
		await expect( page.getByText( /1 to 2 business days/ ) ).toHaveCount( 0 );
	} );

	test( 'cart, checkout summary and mini cart show the line under the item', async ( { page } ) => {
		const cart = await page.request.get( '/wp-json/wc/store/v1/cart' );
		const nonce = cart.headers().nonce || '';
		await page.request.delete( '/wp-json/wc/store/v1/cart/items', { headers: { Nonce: nonce } } );
		const product = ( await ( await page.request.get( `/wp-json/wc/store/v1/products?slug=${ PRODUCT }` ) ).json() )[ 0 ];
		const added = await page.request.post( '/wp-json/wc/store/v1/cart/add-item', { headers: { Nonce: nonce }, data: { id: product.id, quantity: 1 } } );
		expect( added.ok() ).toBeTruthy();
		const item = ( await added.json() ).items.find( ( i ) => i.id === product.id );
		expect( item.item_data.map( ( d ) => d.value ) ).toEqual( [ LINE ] );

		await page.goto( '/cart/' );
		const row = page.getByRole( 'table', { name: 'Products in cart' } ).getByRole( 'row' ).nth( 1 );
		await expect( row.locator( '.cigarholic-ship-time' ) ).toHaveText( LINE );
		// No label in front of the value ("Ships in…", not ": Ships in…").
		await expect( row.locator( '.cigarholic-ship-time .wc-block-components-product-details__name' ) ).toHaveCount( 0 );

		await page.goto( '/checkout/' );
		await expect( page.locator( '.wc-block-components-order-summary-item .cigarholic-ship-time' ).first() ).toHaveText( LINE );

		await page.goto( `/products/${ PRODUCT }/` );
		await page.locator( '.wc-block-mini-cart__button' ).first().click();
		await expect( page.getByRole( 'dialog' ).getByText( LINE, { exact: true } ) ).toBeVisible();

		await page.request.delete( '/wp-json/wc/store/v1/cart/items', { headers: { Nonce: nonce } } );
	} );
} );
