// CNT-01 brand system: the self-hosted fonts load and really render, the Forest section style, the
// section-aware focus ring and error colour, and WCAG 2.2 AA contrast for every visible text (CMP-04).
// Functions passed to page.evaluate() run in the browser, hence these globals.
/* global document, getComputedStyle, NodeFilter */
import { test, expect } from '@playwright/test';
import { login, waitForTurnstile } from './helpers.js';

const FOREST = 'rgb(31, 43, 37)';
const GOLD = 'rgb(193, 144, 79)';
const ERROR_ON_FOREST = 'rgb(242, 160, 138)';
const ERROR_ON_LIGHT = 'rgb(155, 28, 28)';
const ASH_TINT = 'rgb(247, 244, 239)';
const STONE = 'rgb(94, 87, 78)';
const CEDAR = 'rgb(156, 80, 52)';

/** Whether anonymous visitors get the coming-soon page here (provisioning step 80). */
async function comingSoonIsOn( request ) {
	const html = await ( await request.get( '/' ) ).text();
	return html.includes( 'cigarholic-signup__form' ) && ! html.includes( 'aria-label="Primary"' );
}

/** Empties the cart, adds the first simple product (Store API) and returns that product's path. */
async function cartWithOneProduct( page ) {
	const products = await ( await page.request.get( '/wp-json/wc/store/v1/products?type=simple&per_page=1' ) ).json();
	test.skip( ! products.length, 'no simple product in the catalog yet' );
	const cart = await page.request.get( '/wp-json/wc/store/v1/cart' );
	const headers = { Nonce: cart.headers().nonce || '' };
	await page.request.delete( '/wp-json/wc/store/v1/cart/items', { headers } );
	await page.request.post( '/wp-json/wc/store/v1/cart/add-item', { headers, data: { id: products[ 0 ].id, quantity: 1 } } );
	return new URL( products[ 0 ].permalink ).pathname;
}

/**
 * Every visible text node whose colour fails AA against the background it is painted on
 * (4.5:1, or 3:1 for large text: 24 px, or 18.66 px bold). Opacity and translucent colours
 * are blended over the first opaque ancestor background, after finite animations finish. Text over a background image, a
 * gradient or a Cover block is reported too, because a colour comparison cannot judge it.
 */
async function contrastFailures( page ) {
	// Measure the settled page: a fade-in (the phone menu opens from opacity 0 over 100 ms) would
	// otherwise be judged mid-animation. Infinite animations (spinners) never finish, so skip them.
	await page.evaluate( () => Promise.all(
		document.getAnimations()
			.filter( ( a ) => a.effect?.getTiming().iterations !== Infinity )
			.map( ( a ) => a.finished.catch( () => {} ) )
	) );
	return page.evaluate( () => {
		const parse = ( value ) => {
			const m = value.match( /rgba?\(([^)]+)\)/ );
			if ( ! m ) {
				return null;
			}
			const [ r, g, b, a = 1 ] = m[ 1 ].split( /[ ,/]+/ ).filter( Boolean ).map( Number );
			return { r, g, b, a };
		};
		const blend = ( top, bottom, alpha ) => ( {
			r: top.r * alpha + bottom.r * ( 1 - alpha ),
			g: top.g * alpha + bottom.g * ( 1 - alpha ),
			b: top.b * alpha + bottom.b * ( 1 - alpha ),
			a: 1,
		} );
		const luminance = ( c ) => {
			const f = ( v ) => {
				const s = v / 255;
				return s <= 0.04045 ? s / 12.92 : ( ( s + 0.055 ) / 1.055 ) ** 2.4;
			};
			return 0.2126 * f( c.r ) + 0.7152 * f( c.g ) + 0.0722 * f( c.b );
		};
		const ratio = ( x, y ) => {
			const [ hi, lo ] = [ luminance( x ), luminance( y ) ].sort( ( p, q ) => q - p );
			return ( hi + 0.05 ) / ( lo + 0.05 );
		};
		// The colour behind an element; null when an image, gradient or Cover block paints it, which a
		// colour comparison cannot judge.
		const background = ( el ) => {
			const layers = [];
			for ( let node = el; node; node = node.parentElement ) {
				const computed = getComputedStyle( node );
				if ( computed.backgroundImage !== 'none' || node.classList.contains( 'wp-block-cover' ) ) {
					return null;
				}
				const bg = parse( computed.backgroundColor );
				if ( bg && bg.a > 0 ) {
					layers.push( bg );
					if ( bg.a === 1 ) {
						break;
					}
				}
			}
			let result = { r: 255, g: 255, b: 255, a: 1 };
			for ( const layer of layers.reverse() ) {
				result = blend( layer, result, layer.a );
			}
			return result;
		};
		const opacity = ( el ) => {
			let value = 1;
			for ( let node = el; node; node = node.parentElement ) {
				value *= Number( getComputedStyle( node ).opacity );
			}
			return value;
		};

		const failures = [];
		const walker = document.createTreeWalker( document.body, NodeFilter.SHOW_TEXT );
		for ( let text = walker.nextNode(); text; text = walker.nextNode() ) {
			const el = text.parentElement;
			if ( ! el || ! text.textContent.trim() || el.closest( 'script, style, noscript, [aria-hidden="true"]' ) ) {
				continue;
			}
			if ( el.closest( ':disabled, [aria-disabled="true"]' ) ) {
				continue; // WCAG 1.4.3 exempts inactive controls (the quantity "−" at the minimum is dimmed on purpose).
			}
			const range = document.createRange();
			range.selectNodeContents( text );
			const rect = range.getBoundingClientRect();
			const style = getComputedStyle( el );
			if ( rect.width < 2 || rect.height < 2 || style.visibility === 'hidden' ) {
				continue; // Screen-reader-only text and the honeypot are clipped to 1 px.
			}
			const fg = parse( style.color );
			const bg = background( el );
			if ( ! bg ) {
				// Fail loudly rather than pass unchecked: text over an image needs a manual check (or an
				// overlay that gives it a solid colour) before it can be listed here as verified.
				failures.push( `"${ text.textContent.trim().slice( 0, 40 ) }" is over an image or gradient: check its contrast by hand` );
				continue;
			}
			const painted = blend( fg, bg, fg.a * opacity( el ) );
			const size = parseFloat( style.fontSize );
			const large = size >= 24 || ( size >= 18.66 && Number( style.fontWeight ) >= 700 );
			const value = ratio( painted, bg );
			if ( value < ( large ? 3 : 4.5 ) ) {
				failures.push( `"${ text.textContent.trim().slice( 0, 40 ) }" ${ style.color } on rgb(${ bg.r }, ${ bg.g }, ${ bg.b }): ${ value.toFixed( 2 ) }:1` );
			}
		}
		return failures;
	} );
}

test.describe( 'brand system', () => {
	test( 'fonts are self-hosted, loaded and actually paint the headings', async ( { page, browserName } ) => {
		const external = [];
		const served = new Set();
		page.on( 'request', ( r ) => {
			if ( /fonts\.(googleapis|gstatic)\.com/.test( r.url() ) ) {
				external.push( r.url() );
			}
		} );
		page.on( 'response', ( r ) => {
			if ( /\/themes\/cigarholic\/assets\/fonts\/[a-z0-9-]+\.woff2$/.test( r.url() ) && r.status() === 200 ) {
				served.add( r.url().replace( /^.*\//, '' ) );
			}
		} );

		await page.goto( '/' );
		await page.evaluate( () => document.fonts.ready );

		// document.fonts.check() is true even when no face matches, so look at the faces themselves.
		const faces = await page.evaluate( () => [ ...document.fonts ].map( ( f ) => ( { family: f.family.replace( /["']/g, '' ), status: f.status } ) ) );
		expect( faces ).toContainEqual( { family: 'Young Serif', status: 'loaded' } );
		expect( faces ).toContainEqual( { family: 'Hanken Grotesk', status: 'loaded' } );
		expect( [ ...served ].sort() ).toEqual( [ 'hanken-grotesk-latin-wght-normal.woff2', 'young-serif-latin-400-normal.woff2' ] );
		expect( external ).toEqual( [] );

		// The first heading set in Young Serif (the store's H1, the coming-soon signup heading), marked on the
		// element that holds its text node, because Chromium reports fonts for direct text children only.
		const marked = await page.evaluate( () => {
			const heading = [ ...document.querySelectorAll( 'h1, h2' ) ].find( ( h ) => getComputedStyle( h ).fontFamily.startsWith( '"Young Serif"' ) );
			if ( ! heading ) {
				return null;
			}
			const walker = document.createTreeWalker( heading, NodeFilter.SHOW_TEXT );
			let node = walker.nextNode();
			while ( node && ! node.textContent.trim() ) {
				node = walker.nextNode();
			}
			node.parentElement.setAttribute( 'data-brand-probe', '' );
			return { text: heading.textContent.trim(), weight: getComputedStyle( heading ).fontWeight };
		} );
		expect( marked, 'a heading set in Young Serif' ).not.toBeNull();
		expect( marked.weight ).toBe( '400' );

		// The font that actually paints that text, as Chromium reports it: a silent fallback to Georgia fails.
		test.skip( browserName !== 'chromium', 'platform font check needs the Chrome DevTools Protocol' );
		const cdp = await page.context().newCDPSession( page );
		await cdp.send( 'DOM.enable' );
		await cdp.send( 'CSS.enable' );
		const { root } = await cdp.send( 'DOM.getDocument', { depth: -1 } );
		const { nodeId } = await cdp.send( 'DOM.querySelector', { nodeId: root.nodeId, selector: '[data-brand-probe]' } );
		const { fonts } = await cdp.send( 'CSS.getPlatformFontsForNode', { nodeId } );
		expect( fonts.filter( ( f ) => f.isCustomFont ).map( ( f ) => f.familyName ), marked.text ).toContain( 'Young Serif' );
	} );

	test( 'coming-soon page: Forest ground, Gold focus ring, Forest error colour, AA contrast', async ( { page, request } ) => {
		test.skip( ! ( await comingSoonIsOn( request ) ), 'coming-soon mode is off on this environment' );
		await page.goto( '/' );

		const forest = page.locator( '.is-style-forest' ).first();
		await expect( forest ).toHaveCSS( 'background-color', FOREST );
		const height = await forest.evaluate( ( el ) => el.getBoundingClientRect().height );
		expect( height ).toBeGreaterThanOrEqual( page.viewportSize().height );

		// The logo from the media library (provisioning step 25), loaded, named for screen readers; the H1 under it.
		const logo = page.locator( '.wp-block-site-logo img' );
		await expect( logo ).toHaveAttribute( 'alt', 'Cigarholic' );
		// Modern Image Formats (OPS-09) may serve the upload as AVIF or WebP.
		await expect( logo ).toHaveAttribute( 'src', /\/uploads\/.+cigarholic-logo.*\.(webp|avif)$/ );
		expect( await logo.evaluate( ( img ) => img.complete && img.naturalWidth > 0 ) ).toBe( true );
		await expect( page.getByRole( 'heading', { level: 1 } ) ).toHaveText( /handcrafted humidors/i );
		await expect( page.getByRole( 'heading', { level: 1 } ) ).toHaveCSS( 'color', GOLD );

		// Keyboard focus on a link inside Forest draws the Gold ring (Ink would be 1.21:1 there).
		const phone = page.locator( 'a[href^="tel:"]' );
		for ( let i = 0; i < 30 && ! ( await phone.evaluate( ( el ) => el === document.activeElement ) ); i++ ) {
			await page.keyboard.press( 'Tab' );
		}
		await expect( phone ).toBeFocused();
		await expect( phone ).toHaveCSS( 'outline-color', GOLD );

		expect( await contrastFailures( page ) ).toEqual( [] );

		// A refused address shows the error in the Forest error colour.
		const form = page.locator( '.cigarholic-signup__form' );
		await waitForTurnstile( form );
		await form.getByLabel( 'Email address' ).fill( 'not-an-email' );
		await form.getByRole( 'button' ).click();
		const status = form.locator( '.cigarholic-signup__status' );
		await expect( status ).toHaveAttribute( 'data-state', 'error', { timeout: 15_000 } );
		await expect( status ).toHaveCSS( 'color', ERROR_ON_FOREST );
		expect( await contrastFailures( page ) ).toEqual( [] );
	} );

	test( 'site icon comes from the brand files and stays PNG', async ( { page, request } ) => {
		await page.goto( '/' );
		for ( const rel of [ 'icon', 'apple-touch-icon' ] ) {
			const href = await page.locator( `link[rel="${ rel }"]` ).first().getAttribute( 'href' );
			expect( href, rel ).toMatch( /\/uploads\/.+cigarholic-icon.*\.png$/ );
			expect( ( await request.get( href ) ).status(), rel ).toBe( 200 );
		}
	} );

	test( 'header shows the logo', async ( { page } ) => {
		await login( page );
		await page.goto( '/' );
		const logo = page.locator( 'header .wp-block-site-logo img' );
		await expect( logo ).toBeVisible();
		await expect( logo ).toHaveAttribute( 'alt', 'Cigarholic' );
		expect( await logo.evaluate( ( img ) => img.complete && img.naturalWidth > 0 ) ).toBe( true );
	} );

	test( 'header and footer are Forest sections (NAV-01, NAV-03)', async ( { page } ) => {
		await login( page );
		await page.goto( '/' );
		const header = page.locator( '.site-header' );
		const footer = page.locator( '.site-footer' );
		await expect( header ).toHaveCSS( 'background-color', FOREST );
		await expect( footer ).toHaveCSS( 'background-color', FOREST );
		await expect( footer.locator( '.wp-block-site-logo img' ) ).toHaveAttribute( 'alt', 'Cigarholic' );
		await expect( footer.getByRole( 'heading', { name: 'Shop' } ) ).toHaveCSS( 'color', GOLD );

		// The search toggle is an icon like account and cart: no Gold fill, a 44 px target.
		const search = header.locator( '.wp-block-search__button' );
		await expect( search ).toHaveCSS( 'background-color', 'rgba(0, 0, 0, 0)' );
		const box = await search.boundingBox();
		expect( Math.min( box.width, box.height ) ).toBeGreaterThanOrEqual( 44 );

		// Keyboard focus on a header link draws the Gold ring.
		const firstLink = header.getByRole( 'navigation', { name: 'Primary' } ).getByRole( 'link' ).first();
		for ( let i = 0; i < 30 && ! ( await firstLink.evaluate( ( el ) => el === document.activeElement ) ); i++ ) {
			await page.keyboard.press( 'Tab' );
		}
		await expect( firstLink ).toBeFocused();
		await expect( firstLink ).toHaveCSS( 'outline-color', GOLD );
	} );

	test( 'phone header: one row, focus order matches what you see, Forest menu', async ( { page } ) => {
		await page.setViewportSize( { width: 390, height: 844 } );
		await login( page );
		await page.goto( '/' );
		const header = page.locator( '.site-header' );
		const open = header.getByRole( 'button', { name: /open menu/i } );
		const logo = header.locator( '.wp-block-site-logo' );
		const [ logoBox, openBox ] = await Promise.all( [ logo.boundingBox(), open.boundingBox() ] );
		// Everything on the logo's row.
		expect( Math.abs( ( openBox.y + openBox.height / 2 ) - ( logoBox.y + logoBox.height / 2 ) ) ).toBeLessThan( 12 );
		// The gold hairline follows the gold token at 35 %.
		expect( await header.evaluate( ( el ) => getComputedStyle( el ).borderBottomColor ) ).toMatch( /0\.35\)$/ );

		// Keyboard focus visits the header controls left to right (WCAG 2.4.3): no CSS reordering.
		const xs = [];
		await page.locator( 'body' ).focus();
		for ( let i = 0; i < 12 && xs.length < 5; i++ ) {
			await page.keyboard.press( 'Tab' );
			const x = await page.evaluate( () => {
				const el = document.activeElement;
				return el && el.closest( '.site-header' ) && ! el.closest( '.cigarholic-announcement-bar' ) ? el.getBoundingClientRect().x : null;
			} );
			if ( null !== x ) {
				xs.push( x );
			}
		}
		expect( xs.length ).toBe( 5 ); // Logo, menu, search, account, cart.
		expect( xs ).toEqual( [ ...xs ].sort( ( a, b ) => a - b ) );

		await open.click();
		const menu = page.locator( '.wp-block-navigation__responsive-container.is-menu-open' );
		await expect( menu ).toBeVisible();
		await expect( menu ).toHaveCSS( 'background-color', FOREST );
		await expect( menu.getByRole( 'link', { name: 'Shop', exact: true } ) ).toHaveCSS( 'color', 'rgb(238, 233, 224)' );
		expect( await contrastFailures( page ) ).toEqual( [] );
	} );

	test( 'header row stays inside the viewport from 320 px up', async ( { page } ) => {
		await login( page );
		for ( const width of [ 320, 360, 599, 600, 782, 1024 ] ) {
			await page.setViewportSize( { width, height: 800 } );
			await page.goto( '/' );
			// Each control of the header row ends inside the viewport (the menu wraps inside its own box).
			const overflow = await page.evaluate( () => {
				const row = document.querySelector( '.site-header .alignwide' );
				return Math.max( ...[ ...row.children ].map( ( c ) => c.getBoundingClientRect().right ) ) - document.documentElement.clientWidth;
			} );
			expect( overflow, `${ width } px` ).toBeLessThanOrEqual( 0 );
		}
	} );

	test( 'homepage sections follow the canvas (NAV-04)', async ( { page } ) => {
		await login( page );
		await page.goto( '/' );

		// The hero is a Forest section that starts where the header ends (no Ash band between them).
		const hero = page.locator( 'main .is-style-forest' ).filter( { has: page.getByRole( 'heading', { level: 1 } ) } );
		await expect( hero ).toHaveCSS( 'background-color', FOREST );
		const [ headerBox, heroBox ] = await Promise.all( [ page.locator( '.site-header' ).boundingBox(), hero.boundingBox() ] );
		expect( Math.abs( heroBox.y - ( headerBox.y + headerBox.height ) ) ).toBeLessThanOrEqual( 1 );
		await expect( hero.locator( '.is-style-eyebrow' ) ).toHaveCSS( 'color', GOLD );

		// Gold primary with Ink text, Ash outline on Forest.
		const primary = hero.getByRole( 'link', { name: 'Shop humidors' } );
		await expect( primary ).toHaveCSS( 'background-color', GOLD );
		await expect( primary ).toHaveCSS( 'color', 'rgb(26, 24, 21)' );
		await expect( hero.getByRole( 'link', { name: 'See how they are made' } ) ).toHaveCSS( 'border-top-color', 'rgb(238, 233, 224)' );

		// One descriptive link per category card, each to its own collection.
		const cards = page.locator( 'main a[href*="/collections/"]' ).filter( { hasText: /^Shop .+ →$/ } );
		await expect( cards ).toHaveCount( 6 );
		const links = await cards.evaluateAll( ( as ) => Object.fromEntries( as.map( ( a ) => [ a.textContent.trim(), new URL( a.href ).pathname ] ) ) );
		expect( links ).toEqual( {
			'Shop desktop humidors →': '/collections/desktop-humidors/',
			'Shop cabinet humidors →': '/collections/cabinet-humidors/',
			'Shop travel humidors →': '/collections/travel-humidors/',
			'Shop cigar boxes →': '/collections/cigar-boxes/',
			'Shop accessories →': '/collections/humidor-accessories/',
			'Shop gift sets →': '/collections/gift-sets/',
		} );

		// The hero columns stay inside their row, and the cards reflow: one per row on phones, three on desktop.
		const grid = cards.first().locator( 'xpath=ancestor::*[contains(@class, "is-layout-grid")][1]' );
		for ( const [ width, perRow ] of [ [ 360, 1 ], [ 1280, 3 ] ] ) {
			await page.setViewportSize( { width, height: 900 } );
			const row = await hero.locator( '.wp-block-columns' ).boundingBox();
			const last = await hero.locator( '.wp-block-column' ).last().boundingBox();
			expect( last.x + last.width, `hero at ${ width }px` ).toBeLessThanOrEqual( row.x + row.width + 1 );
			const tops = await grid.evaluate( ( g ) => new Set( [ ...g.children ].map( ( c ) => Math.round( c.getBoundingClientRect().top ) ) ).size );
			expect( 6 / tops, `cards per row at ${ width }px` ).toBe( perRow );
		}
	} );

	test( 'WooCommerce pages follow the brand (CNT-01)', async ( { page } ) => {
		await login( page );
		const product = await cartWithOneProduct( page );

		// Shop: the sorting select is a 44 px field; product cards line up on the left like the homepage cards.
		await page.goto( '/shop/' );
		const sorting = page.locator( 'select.orderby' );
		await expect( sorting ).toHaveCSS( 'background-color', ASH_TINT );
		await expect( sorting ).toHaveCSS( 'border-top-color', STONE );
		await expect( sorting ).toHaveCSS( 'font-size', '16px' ); // Under 16 px, phones zoom in on focus.
		expect( ( await sorting.boundingBox() ).height ).toBeGreaterThanOrEqual( 44 );
		await expect( page.locator( 'main .wp-block-post-title' ).first() ).toHaveCSS( 'text-align', /^(start|left)$/ );

		// Product: the gallery fills its column (WooCommerce caps it at 512 px), full-Ink quantity signs
		// in a Stone box (WooCommerce dims them to 4.35:1), and plain tabs with the open one on a Cedar rule.
		await page.goto( product );
		const gallery = page.locator( '.wp-block-woocommerce-product-image-gallery' );
		const column = gallery.locator( 'xpath=ancestor::*[contains(@class, "wp-block-column")][1]' );
		expect( ( await gallery.boundingBox() ).width ).toBeGreaterThan( ( await column.boundingBox() ).width - 2 );
		const quantity = page.locator( '.wc-block-add-to-cart-with-options__quantity-selector' );
		await expect( quantity ).toHaveCSS( 'border-top-color', STONE );
		const plus = quantity.locator( '.wc-block-components-quantity-selector__button--plus' );
		await expect( plus ).toHaveCSS( 'opacity', '1' );
		await expect( plus ).toHaveCSS( 'color', 'rgb(26, 24, 21)' );
		const activeTab = page.locator( '.woocommerce-tabs ul.tabs li.active' );
		await expect( activeTab ).toHaveCSS( 'border-bottom-color', CEDAR );
		await expect( activeTab ).toHaveCSS( 'background-color', 'rgba(0, 0, 0, 0)' );

		// Cart: the title lines up with the cart (WooCommerce caps it at 1000 px).
		await page.goto( '/cart/' );
		await expect( page.locator( '.wc-block-cart-items .wc-block-components-product-name' ).first() ).toBeVisible();
		const [ title, cart ] = await Promise.all( [ page.locator( 'main h1' ).boundingBox(), page.locator( '.wp-block-woocommerce-cart' ).boundingBox() ] );
		expect( Math.abs( title.x - cart.x ) ).toBeLessThanOrEqual( 1 );

		// Checkout: the Forest header with the logo, Ash tint fields with a Stone border, brand error colour.
		await page.goto( '/checkout/' );
		const header = page.locator( '.checkout-header' );
		await expect( header ).toHaveCSS( 'background-color', FOREST );
		await expect( header.locator( '.wp-block-site-logo img' ) ).toBeVisible();
		const email = page.getByLabel( 'Email address' );
		await expect( email ).toHaveCSS( 'background-color', ASH_TINT );
		await expect( email ).toHaveCSS( 'border-top-color', STONE );
		await email.fill( '' );
		await page.getByRole( 'button', { name: /place order/i } ).click();
		await expect( page.locator( '.wc-block-components-validation-error' ).first() ).toHaveCSS( 'color', ERROR_ON_LIGHT );
	} );

	test( 'no store page scrolls sideways from 320 px up', async ( { page } ) => {
		test.setTimeout( 120_000 );
		await login( page );
		const product = await cartWithOneProduct( page );
		// 782 to 850 px used to overflow by up to 24 px: the footer signup column was narrower than the Turnstile widget.
		for ( const path of [ '/shop/', product ] ) {
			await page.goto( path );
			for ( const width of [ 320, 390, 782, 800, 834, 1024, 1440 ] ) {
				await page.setViewportSize( { width, height: 900 } );
				await expect.poll(
					() => page.evaluate( () => document.documentElement.scrollWidth - document.documentElement.clientWidth ),
					{ message: `${ path } at ${ width }px` }
				).toBe( 0 );
			}
		}
	} );

	test( 'store pages pass AA contrast for every visible text', async ( { page } ) => {
		test.setTimeout( 120_000 );
		await login( page );
		const product = await cartWithOneProduct( page );
		const pages = [
			[ '/' ],
			[ '/shop/' ],
			[ product, '.wc-block-add-to-cart-with-options' ],
			[ '/cart/', '.wc-block-cart-items .wc-block-components-product-name' ],
			[ '/checkout/', '.wc-block-components-order-summary-item' ],
			[ '/my-account/' ],
		];
		for ( const [ path, ready ] of pages ) {
			await page.goto( path );
			if ( ready ) {
				await expect( page.locator( ready ).first() ).toBeVisible();
			}
			await page.evaluate( () => document.fonts.ready );
			expect( await contrastFailures( page ), path ).toEqual( [] );
		}
	} );
} );
