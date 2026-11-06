// SEO-01: SEOPress as provisioned by step 75-seo. Titles and descriptions come from the templates, the
// XML sitemaps list what customers can land on (pages, products, product categories) and never the
// cart, checkout or account pages, and the home page names the organisation for the knowledge graph.
// Coming soon hides the store from visitors, so pages are read logged in; sitemaps are public.
import { test, expect } from '@playwright/test';
import { login } from './helpers.js';

const CATEGORY = 'desktop-humidors';
const PRODUCT = 'sample-mahogany-desktop-humidor-100';

/** Every <loc> of a sitemap document. */
async function locs( request, path ) {
	const response = await request.get( path );
	expect( response.status(), path ).toBe( 200 );
	expect( response.headers()[ 'content-type' ] ).toMatch( /xml/ );
	const body = await response.text();
	return [ ...body.matchAll( /<loc>([^<]+)<\/loc>/g ) ].map( ( m ) => new URL( m[ 1 ] ).pathname );
}

test.describe( 'sitemaps', () => {
	test( 'the index lists pages, products and product categories, not empty post taxonomies', async ( { request } ) => {
		const index = await locs( request, '/sitemaps.xml' );
		expect( index ).toEqual( expect.arrayContaining( [ '/page-sitemap1.xml', '/product-sitemap1.xml', '/product_cat-sitemap1.xml' ] ) );
		expect( index ).not.toContain( '/category-sitemap1.xml' );
		expect( index ).not.toContain( '/post_tag-sitemap1.xml' );
		expect( index.some( ( p ) => /author/.test( p ) ) ).toBe( false );
	} );

	test( 'the page sitemap has the shop and home but no cart, checkout or account page', async ( { request } ) => {
		const pages = await locs( request, '/page-sitemap1.xml' );
		expect( pages ).toEqual( expect.arrayContaining( [ '/', '/shop/' ] ) );
		for ( const path of [ '/cart/', '/checkout/', '/my-account/' ] ) {
			expect( pages, path ).not.toContain( path );
		}
		// Draft policy pages are not published yet, so they are not listed either.
		expect( pages ).not.toContain( '/returns-refunds/' );
	} );

	test( 'the product category sitemap lists the collections', async ( { request } ) => {
		const categories = await locs( request, '/product_cat-sitemap1.xml' );
		expect( categories ).toContain( `/collections/${ CATEGORY }/` );
		expect( categories ).not.toContain( '/collections/uncategorized/' );
	} );
} );

test.describe( 'titles and metas', () => {
	test.beforeEach( async ( { page } ) => {
		await login( page );
	} );

	test( 'home, shop, category and product titles follow the templates', async ( { page } ) => {
		await page.goto( '/' );
		await expect( page ).toHaveTitle( 'Cigarholic | Handcrafted Wooden Humidors and Cigar Boxes' );
		await expect( page.locator( 'meta[name="description"]' ) ).toHaveAttribute( 'content', /handcrafted in Vietnam/ );

		await page.goto( '/shop/' );
		await expect( page ).toHaveTitle( /^Shop Humidors and Cigar Boxes\s+\| Cigarholic$/ );

		await page.goto( `/collections/${ CATEGORY }/` );
		await expect( page ).toHaveTitle( /^Desktop Humidors\s+\| Cigarholic$/ );

		const found = await page.request.get( `/wp-json/wc/store/v1/products?slug=${ PRODUCT }` );
		test.skip( ! found.ok() || ( await found.json() ).length === 0, 'sample catalog not seeded' );
		await page.goto( `/products/${ PRODUCT }/` );
		await expect( page ).toHaveTitle( /^Sample .+ \| Cigarholic$/ );
		await expect( page.locator( 'link[rel="canonical"]' ) ).toHaveAttribute( 'href', new RegExp( `/products/${ PRODUCT }/$` ) );
		await expect( page.locator( 'meta[property="og:type"]' ) ).toHaveAttribute( 'content', 'product' );
		await expect( page.locator( 'meta[name="twitter:card"]' ) ).toHaveAttribute( 'content', 'summary_large_image' );
	} );

	test( 'the home page names the site and the store for the knowledge graph (SEO-02)', async ( { page } ) => {
		await page.goto( '/' );
		const nodes = ( await page.locator( 'script[type="application/ld+json"]' ).allTextContents() ).map( ( text ) => JSON.parse( text ) );
		const website = nodes.find( ( node ) => node[ '@type' ] === 'WebSite' );
		const store = nodes.find( ( node ) => node[ '@type' ] === 'OnlineStore' );
		expect( website.name ).toBe( 'Cigarholic' );
		expect( website.publisher[ '@id' ] ).toBe( store[ '@id' ] );
		expect( store ).toMatchObject( {
			name: 'Cigarholic',
			legalName: 'HuyLinh LLC',
			address: { addressLocality: 'St Petersburg', addressRegion: 'FL', addressCountry: 'US' },
		} );
		expect( store.description ).toContain( 'does not sell cigars, tobacco or nicotine' );
		expect( store.contactPoint[ 0 ] ).toMatchObject( { telephone: '+1-813-602-7585', email: 'support@cigarholic.com' } );
		// The logo the brand step imported, served from the media library.
		expect( ( await page.request.get( store.logo ) ).status() ).toBe( 200 );
		// No placeholder social profile is published.
		expect( store.sameAs ).toBeUndefined();
	} );
} );
