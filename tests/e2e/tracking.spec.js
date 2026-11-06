// SHP-04: the manager adds a tracking number on the order screen; the order completes and the customer
// gets the shipping email with the carrier and a tracking link (WooCommerce Order Fulfillments + cigarholic-core).
import { test, expect } from '@playwright/test';
import { login, MAILPIT, mailpitMessages, uniqueEmail } from './helpers.js';

const USPS = '9400111899223197428490';

test.describe( 'tracking', () => {
	test( 'manager ships an order with a USPS tracking number', async ( { page, request } ) => {
		await login( page );
		const nonce = ( await ( await page.request.get( '/wp/wp-admin/admin-ajax.php?action=rest-nonce' ) ).text() ).trim();
		const headers = { 'X-WP-Nonce': nonce };

		const products = await ( await page.request.get( '/wp-json/wc/v3/products?per_page=1&type=simple&status=publish', { headers } ) ).json();
		test.skip( ! Array.isArray( products ) || products.length === 0, 'no products in the catalog yet' );

		// A paid order as checkout leaves it (processing), created through WooCommerce's REST API.
		const email = uniqueEmail( 'shipped' );
		const address = { first_name: 'Test', last_name: 'Buyer', address_1: '100 Congress Ave', city: 'Austin', state: 'TX', postcode: '78701', country: 'US' };
		const created = await page.request.post( '/wp-json/wc/v3/orders', {
			headers,
			data: {
				status: 'processing',
				billing: { ...address, email, phone: '5125550100' },
				shipping: address,
				line_items: [ { product_id: products[ 0 ].id, quantity: 1 } ],
			},
		} );
		expect( created.status() ).toBe( 201 );
		const order = await created.json();

		// The manager's flow: order screen → fulfillment badge → tracking number → Find info → Fulfill items.
		await page.goto( `/wp/wp-admin/admin.php?page=wc-orders&action=edit&id=${ order.id }` );
		const badges = page.locator( '.wc-order-fulfillment-badges' );
		await expect( badges ).toContainText( 'No fulfillments' );
		await badges.locator( '.fulfillments-trigger' ).first().click();
		await page.getByPlaceholder( 'Enter tracking number' ).fill( USPS );
		await page.getByRole( 'button', { name: 'Find info' } ).click();
		const drawer = page.locator( '#wc_order_fulfillments_panel_container' );
		await expect( drawer ).toContainText( 'USPS' );
		await expect( drawer ).toContainText( `https://tools.usps.com/go/TrackConfirmAction?tLabels=${ USPS }` );
		const saved = page.waitForResponse( ( r ) => r.url().includes( `/orders/${ order.id }/fulfillments` ) && r.request().method() === 'POST' );
		await page.getByRole( 'button', { name: 'Fulfill items' } ).click();
		expect( ( await saved ).status() ).toBe( 201 );

		await page.reload();
		await expect( badges ).toContainText( 'Fulfilled' );
		await expect( badges ).toContainText( 'Completed' );

		// The customer's email: the store's wording, the carrier's name, a tracking button; no "Completed order" email.
		const subject = `Your Cigarholic order ${ order.number } has shipped`;
		await expect.poll( async () => ( await mailpitMessages( request, subject ) ).length, { timeout: 20_000 } ).toBeGreaterThan( 0 );
		const [ message ] = await mailpitMessages( request, subject );
		expect( message.To[ 0 ].Address ).toBe( email );
		const detail = await ( await request.get( `${ MAILPIT }/api/v1/message/${ message.ID }` ) ).json();
		expect( detail.HTML ).toContain( 'Your order is on its way' );
		expect( detail.HTML ).toMatch( /<strong[^>]*>Carrier:<\/strong> USPS/ );
		expect( detail.HTML ).toMatch( new RegExp( `href="https://tools\\.usps\\.com/go/TrackConfirmAction\\?tLabels=${ USPS }"[^>]*>Track your package</a>` ) );
		expect( detail.Text ).not.toContain( 'Woo!' );
		expect( detail.HTML ).not.toContain( '/wp/my-account' );
		const toBuyer = async ( part ) => ( await mailpitMessages( request, part ) ).filter( ( m ) => m.To[ 0 ].Address === email );
		expect( await toBuyer( 'complete' ) ).toHaveLength( 0 );
	} );
} );
