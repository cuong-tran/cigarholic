#!/usr/bin/env node
/**
 * Renders the Cigarholic sample photos with three.js in headless Chromium and writes them where the
 * theme and the seed command read them.
 *
 *   node scripts/sample-photos/render.mjs                    # every shot, final quality, into the repo
 *   node scripts/sample-photos/render.mjs --only hero --frames 8 --out /tmp/preview
 *
 * Feature IDs: CNT-01, NAV-04, FND-02.
 */

import http from 'node:http';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from '@playwright/test';

const root = path.resolve( path.dirname( fileURLToPath( import.meta.url ) ), '../..' );

/** Where each shot is written, relative to the repository root. */
const TARGETS = {
	hero: 'web/app/themes/cigarholic/assets/images/sample-hero.webp',
	workshop: 'web/app/themes/cigarholic/assets/images/sample-workshop.webp',
	'category-desktop-humidors': 'web/app/themes/cigarholic/assets/images/sample-category-desktop-humidors.webp',
	'category-cabinet-humidors': 'web/app/themes/cigarholic/assets/images/sample-category-cabinet-humidors.webp',
	'category-travel-humidors': 'web/app/themes/cigarholic/assets/images/sample-category-travel-humidors.webp',
	'category-cigar-boxes': 'web/app/themes/cigarholic/assets/images/sample-category-cigar-boxes.webp',
	'category-humidor-accessories': 'web/app/themes/cigarholic/assets/images/sample-category-humidor-accessories.webp',
	'category-gift-sets': 'web/app/themes/cigarholic/assets/images/sample-category-gift-sets.webp',
	'product-sample-walnut-desktop-humidor-50': 'web/app/mu-plugins/cigarholic-core/assets/sample-products/sample-walnut-desktop-humidor-50.jpg',
	'product-sample-mahogany-desktop-humidor-100': 'web/app/mu-plugins/cigarholic-core/assets/sample-products/sample-mahogany-desktop-humidor-100.jpg',
	'product-sample-oak-cabinet-humidor-300': 'web/app/mu-plugins/cigarholic-core/assets/sample-products/sample-oak-cabinet-humidor-300.jpg',
	'product-sample-acacia-travel-humidor-10': 'web/app/mu-plugins/cigarholic-core/assets/sample-products/sample-acacia-travel-humidor-10.jpg',
	'product-sample-cherry-cigar-box-25': 'web/app/mu-plugins/cigarholic-core/assets/sample-products/sample-cherry-cigar-box-25.jpg',
	'product-sample-digital-hygrometer': 'web/app/mu-plugins/cigarholic-core/assets/sample-products/sample-digital-hygrometer.jpg',
	'product-sample-groomsmen-gift-set': 'web/app/mu-plugins/cigarholic-core/assets/sample-products/sample-groomsmen-gift-set.jpg',
};

const TYPES = { '.html': 'text/html', '.js': 'text/javascript', '.mjs': 'text/javascript', '.woff2': 'font/woff2' };
const SERVED = [ 'scripts/sample-photos/', 'node_modules/three/', 'web/app/themes/cigarholic/assets/fonts/' ];

function args() {
	const a = process.argv.slice( 2 );
	const o = { frames: 48, only: null, out: null };
	const value = ( i ) => {
		if ( i >= a.length || a[ i ].startsWith( '--' ) ) {
			throw new Error( `${ a[ i - 1 ] } needs a value` );
		}
		return a[ i ];
	};
	for ( let i = 0; i < a.length; i++ ) {
		if ( '--frames' === a[ i ] ) {
			o.frames = Number( value( ++i ) );
			if ( ! Number.isInteger( o.frames ) || o.frames < 1 ) {
				throw new Error( '--frames must be a whole number of 1 or more' );
			}
		} else if ( '--only' === a[ i ] ) {
			o.only = value( ++i ).split( ',' );
			const unknown = o.only.filter( ( n ) => ! TARGETS[ n ] );
			if ( unknown.length ) {
				throw new Error( `unknown shot(s): ${ unknown.join( ', ' ) }; known: ${ Object.keys( TARGETS ).join( ', ' ) }` );
			}
		} else if ( '--out' === a[ i ] ) {
			o.out = path.resolve( value( ++i ) );
		} else {
			throw new Error( `unknown argument ${ a[ i ] }` );
		}
	}
	return o;
}

function serve() {
	const server = http.createServer( ( req, res ) => {
		const file = path.resolve( root, '.' + decodeURIComponent( new URL( req.url, 'http://x' ).pathname ) );
		const rel = path.relative( root, file ).split( path.sep ).join( '/' );
		let ok = ! rel.startsWith( '..' ) && ! path.isAbsolute( rel ) && SERVED.some( ( p ) => rel.startsWith( p ) );
		try {
			ok = ok && fs.statSync( file ).isFile();
		} catch {
			ok = false;
		}
		if ( ! ok ) {
			res.writeHead( 404 ).end();
			return;
		}
		res.writeHead( 200, { 'content-type': TYPES[ path.extname( file ) ] || 'application/octet-stream' } );
		fs.createReadStream( file ).on( 'error', () => res.destroy() ).pipe( res );
	} );
	return new Promise( ( resolve ) => server.listen( 0, '127.0.0.1', () => resolve( server ) ) );
}

const opts = args();
const server = await serve();
const { port } = server.address();
const browser = await chromium.launch( { args: [ '--use-angle=swiftshader', '--enable-unsafe-swiftshader', '--ignore-gpu-blocklist' ] } );
try {
	const page = await browser.newPage();
	page.on( 'console', ( m ) => {
		if ( 'error' === m.type() || 'warning' === m.type() ) {
			process.stderr.write( `[page] ${ m.text() }\n` );
		}
	} );
	page.on( 'pageerror', ( e ) => process.stderr.write( `[page error] ${ e.message }\n` ) );
	await page.goto( `http://127.0.0.1:${ port }/scripts/sample-photos/studio.html` );
	await page.waitForFunction( () => window.studioReady === true );
	const names = opts.only || ( await page.evaluate( () => window.shotNames() ) );
	for ( const name of names ) {
		const started = Date.now();
		const url = await page.evaluate( ( [ n, f ] ) => window.renderShot( n, f ), [ name, opts.frames ] );
		const buf = Buffer.from( url.split( ',' )[ 1 ], 'base64' );
		if ( ! TARGETS[ name ] ) {
			throw new Error( `shot ${ name } has no target file in TARGETS` );
		}
		const target = opts.out ? path.join( opts.out, path.basename( TARGETS[ name ] ) ) : path.join( root, TARGETS[ name ] );
		fs.mkdirSync( path.dirname( target ), { recursive: true } );
		fs.writeFileSync( target, buf );
		process.stdout.write( `${ name }: ${ path.relative( process.cwd(), target ) } (${ Math.round( buf.length / 1024 ) } KB, ${ ( ( Date.now() - started ) / 1000 ).toFixed( 1 ) } s)\n` );
	}
} finally {
	await browser.close();
	server.close();
}
