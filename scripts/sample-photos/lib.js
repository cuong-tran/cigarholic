/**
 * Cigarholic sample photo studio: materials, models and the accumulation renderer.
 *
 * Everything is procedural (no downloaded textures or models): wood grain from value noise, dials and
 * displays drawn on canvases, soft shadows, anti-aliasing and depth of field from averaging many frames
 * with a jittered area light, sky light, sub-pixel offset and lens position.
 *
 * Units are centimetres. Feature IDs: CNT-01 (brand imagery), NAV-04 (homepage), FND-02 (seed images).
 */

import * as THREE from 'three';
import { RoomEnvironment } from 'three/addons/environments/RoomEnvironment.js';
import { RoundedBoxGeometry } from 'three/addons/geometries/RoundedBoxGeometry.js';
import { ParametricGeometry } from 'three/addons/geometries/ParametricGeometry.js';
import { EffectComposer } from 'three/addons/postprocessing/EffectComposer.js';
import { RenderPass } from 'three/addons/postprocessing/RenderPass.js';
import { GTAOPass } from 'three/addons/postprocessing/GTAOPass.js';
import { OutputPass } from 'three/addons/postprocessing/OutputPass.js';

export const DEG = Math.PI / 180;

/* ------------------------------------------------------------------ random and noise */

export function rng( seed ) {
	let a = seed >>> 0;
	return () => {
		a = ( a + 0x6d2b79f5 ) | 0;
		let t = Math.imul( a ^ ( a >>> 15 ), 1 | a );
		t = ( t + Math.imul( t ^ ( t >>> 7 ), 61 | t ) ) ^ t;
		return ( ( t ^ ( t >>> 14 ) ) >>> 0 ) / 4294967296;
	};
}

function makeNoise( seed ) {
	const s = Math.imul( seed | 0, 1442695041 );
	const hash = ( x, y ) => {
		let h = ( Math.imul( x, 374761393 ) + Math.imul( y, 668265263 ) + s ) | 0;
		h = Math.imul( h ^ ( h >>> 13 ), 1274126177 );
		return ( ( h ^ ( h >>> 16 ) ) >>> 0 ) / 4294967296;
	};
	const noise = ( x, y ) => {
		const xi = Math.floor( x );
		const yi = Math.floor( y );
		const xf = x - xi;
		const yf = y - yi;
		const u = xf * xf * ( 3 - 2 * xf );
		const v = yf * yf * ( 3 - 2 * yf );
		const a = hash( xi, yi );
		const b = hash( xi + 1, yi );
		const c = hash( xi, yi + 1 );
		const d = hash( xi + 1, yi + 1 );
		return a + ( b - a ) * u + ( c - a ) * v + ( a - b - c + d ) * u * v;
	};
	const cell = ( i ) => hash( i | 0, 9173 );
	const fbm = ( x, y, octaves = 4 ) => {
		let sum = 0;
		let amp = 0.5;
		let f = 1;
		for ( let i = 0; i < octaves; i++ ) {
			sum += amp * noise( x * f, y * f );
			f *= 2.03;
			amp *= 0.5;
		}
		return sum / ( 1 - Math.pow( 0.5, octaves ) );
	};
	return { noise, fbm, cell };
}

const smooth = ( a, b, x ) => {
	const t = Math.min( 1, Math.max( 0, ( x - a ) / ( b - a ) ) );
	return t * t * ( 3 - 2 * t );
};
const hex = ( h ) => [ parseInt( h.slice( 1, 3 ), 16 ), parseInt( h.slice( 3, 5 ), 16 ), parseInt( h.slice( 5, 7 ), 16 ) ];

/* ------------------------------------------------------------------ wood textures */

/**
 * Wood species presets: colours of early wood (light) and late wood (dark), ring density, figure.
 * One texture covers `tile` cm along the grain and half that across it.
 */
export const WOODS = {
	walnut: { light: '#4a2f1f', dark: '#21140c', rings: 46, warp: 5, cath: 3.5, contrast: 0.55, pores: 0.6, streak: 0.4, seed: 11 },
	walnutRaw: { light: '#86613f', dark: '#4e3322', rings: 46, warp: 5, cath: 3.5, contrast: 0.5, pores: 0.5, streak: 0.35, seed: 12 },
	mahogany: { light: '#6e2f1b', dark: '#43190c', rings: 70, warp: 1.5, cath: 0.8, contrast: 0.35, pores: 0.45, streak: 0.45, ribbon: 0.3, seed: 21 },
	oak: { light: '#c49c6b', dark: '#93693e', rings: 34, warp: 5, cath: 4, contrast: 0.6, pores: 0.75, streak: 0.3, ray: 0.3, seed: 31 },
	acacia: { light: '#a8703f', dark: '#5f3518', rings: 40, warp: 7, cath: 4, contrast: 0.7, pores: 0.35, streak: 0.45, seed: 41 },
	cherry: { light: '#8c4429', dark: '#5e2a17', rings: 40, warp: 4, cath: 3, contrast: 0.38, pores: 0.12, streak: 0.3, seed: 51 },
	cedar: { light: '#c99c74', dark: '#a87650', rings: 40, warp: 3, cath: 2.5, contrast: 0.55, pores: 0.25, streak: 0.3, seed: 61 },
	beech: { light: '#d8b892', dark: '#c29d72', rings: 30, warp: 3, cath: 2, contrast: 0.3, pores: 0.12, streak: 0.25, ray: 0.15, seed: 71 },
	pine: { light: '#d6b487', dark: '#a8784a', rings: 26, warp: 6, cath: 4, contrast: 0.75, pores: 0.04, streak: 0.2, seed: 81 },
};

const textureCache = new Map();

/**
 * Colour, roughness and bump canvases for a species. Grain runs along u (canvas x).
 *
 * @param {string} name Preset key.
 * @param {number} W    Canvas width (along the grain).
 * @param {number} H    Canvas height (across).
 */
export function woodTextures( name, W = 2048, H = 1024 ) {
	const key = `${ name }:${ W }x${ H }`;
	if ( textureCache.has( key ) ) {
		return textureCache.get( key );
	}
	const p = WOODS[ name ];
	const { noise, fbm, cell } = makeNoise( p.seed );
	const L = hex( p.light );
	const D = hex( p.dark );
	const color = document.createElement( 'canvas' );
	color.width = W;
	color.height = H;
	const bump = document.createElement( 'canvas' );
	bump.width = W;
	bump.height = H;
	const cx = color.getContext( '2d' );
	const bx = bump.getContext( '2d' );
	const ci = cx.createImageData( W, H );
	const bi = bx.createImageData( W, H );
	for ( let y = 0; y < H; y++ ) {
		const v = y / H;
		for ( let x = 0; x < W; x++ ) {
			const u = ( x / W ) * 2;
			const warp = ( fbm( u * 0.7, v * 1.2, 4 ) - 0.5 ) * 2 * p.warp;
			const cathedral = Math.sin( u * 1.6 + fbm( u * 0.35, v * 0.5, 3 ) * 4 ) * p.cath * ( 0.5 + fbm( u * 0.3, v * 0.3 + 7, 2 ) );
			const t = v * p.rings + warp + cathedral;
			const ri = Math.floor( t );
			const ring = t - ri;
			const k = cell( ri );
			const k2 = cell( ri + 1000 );
			const start = 0.35 + k2 * 0.45;
			let late = smooth( start, Math.min( 0.97, start + 0.25 ), ring ) * ( 1 - smooth( 0.92, 1.0, ring ) );
			late = late * p.contrast * ( 0.25 + 0.95 * k * k );
			const streak = ( fbm( u * 4, v * 160, 3 ) - 0.5 ) * p.streak;
			const mottle = ( fbm( u * 0.5, v * 1.8, 4 ) - 0.5 ) * 0.9;
			let ribbon = 0;
			if ( p.ribbon ) {
				ribbon = ( Math.sin( v * 38 + fbm( u * 1.4, v * 2, 3 ) * 6 ) * 0.5 + 0.5 ) * p.ribbon;
			}
			let m = Math.min( 1, Math.max( 0, late + streak + mottle * 0.5 + ribbon * 0.6 ) );
			const pore = noise( u * 260, v * 1500 );
			const isPore = pore > 1 - p.pores * 0.18 ? 1 : 0;
			let ray = 0;
			if ( p.ray ) {
				ray = noise( u * 30, v * 260 ) > 0.86 ? p.ray : 0;
			}
			m = Math.min( 1, m + isPore * 0.45 );
			let r = L[ 0 ] + ( D[ 0 ] - L[ 0 ] ) * m;
			let g = L[ 1 ] + ( D[ 1 ] - L[ 1 ] ) * m;
			let b = L[ 2 ] + ( D[ 2 ] - L[ 2 ] ) * m;
			if ( ray ) {
				r += ( 255 - r ) * ray * 0.25;
				g += ( 255 - g ) * ray * 0.22;
				b += ( 255 - b ) * ray * 0.18;
			}
			const i = ( y * W + x ) * 4;
			ci.data[ i ] = r;
			ci.data[ i + 1 ] = g;
			ci.data[ i + 2 ] = b;
			ci.data[ i + 3 ] = 255;
			const h = 200 - isPore * 120 - late * 40 + streak * 40;
			bi.data[ i ] = h;
			bi.data[ i + 1 ] = h;
			bi.data[ i + 2 ] = h;
			bi.data[ i + 3 ] = 255;
		}
	}
	cx.putImageData( ci, 0, 0 );
	bx.putImageData( bi, 0, 0 );
	const out = { color, bump };
	textureCache.set( key, out );
	return out;
}

function canvasTexture( canvas, srgb ) {
	const t = new THREE.CanvasTexture( canvas );
	t.wrapS = THREE.RepeatWrapping;
	t.wrapT = THREE.RepeatWrapping;
	t.colorSpace = srgb ? THREE.SRGBColorSpace : THREE.NoColorSpace;
	t.anisotropy = 16;
	return t;
}

/**
 * Finishes: how the clear coat and the wood itself reflect.
 */
const FINISHES = {
	gloss: { roughness: 0.42, clearcoat: 1, clearcoatRoughness: 0.04, bump: 0.25 },
	matte: { roughness: 0.55, clearcoat: 0.6, clearcoatRoughness: 0.32, bump: 0.5 },
	oil: { roughness: 0.62, clearcoat: 0.25, clearcoatRoughness: 0.45, bump: 0.8 },
	raw: { roughness: 0.82, clearcoat: 0, clearcoatRoughness: 1, bump: 1.0 },
};

/**
 * A wood material. `tile` is the length in cm one texture covers along the grain.
 */
export function wood( species, finish = 'matte', { tile = 60, tint } = {} ) {
	const tex = woodTextures( species );
	const f = FINISHES[ finish ];
	const map = canvasTexture( tex.color, true );
	const bumpMap = canvasTexture( tex.bump, false );
	const mat = new THREE.MeshPhysicalMaterial( {
		map,
		bumpMap,
		bumpScale: f.bump * 0.6,
		roughness: f.roughness,
		clearcoat: f.clearcoat,
		clearcoatRoughness: f.clearcoatRoughness,
		color: tint ? new THREE.Color( tint ) : new THREE.Color( 0xffffff ),
	} );
	mat.userData.tile = tile;
	return mat;
}

export const MAT = {
	brass: () => new THREE.MeshPhysicalMaterial( { color: '#c9a35e', metalness: 1, roughness: 0.28 } ),
	brassBrushed: () => new THREE.MeshPhysicalMaterial( { color: '#b8924f', metalness: 1, roughness: 0.42 } ),
	steel: () => new THREE.MeshPhysicalMaterial( { color: '#d4d6d8', metalness: 1, roughness: 0.22 } ),
	darkSteel: () => new THREE.MeshPhysicalMaterial( { color: '#3a3d40', metalness: 0.9, roughness: 0.45 } ),
	blackPlastic: () => new THREE.MeshPhysicalMaterial( { color: '#1b1b1c', roughness: 0.38, clearcoat: 0.3, clearcoatRoughness: 0.3 } ),
	glass: () => new THREE.MeshPhysicalMaterial( { color: '#ffffff', metalness: 0, roughness: 0.02, transmission: 1, thickness: 0.4, ior: 1.5, specularIntensity: 1, envMapIntensity: 1.2 } ),
	felt: ( c = '#24372d' ) => new THREE.MeshPhysicalMaterial( { color: c, roughness: 1, sheen: 1, sheenRoughness: 0.6, sheenColor: new THREE.Color( '#6f8a78' ) } ),
	kraft: () => new THREE.MeshPhysicalMaterial( { map: paperTexture( '#b48c5e', 5 ), roughness: 0.95 } ),
	paper: ( c = '#f3eee4' ) => new THREE.MeshPhysicalMaterial( { color: c, roughness: 0.9 } ),
	ribbon: ( c = '#1f2b25' ) => new THREE.MeshPhysicalMaterial( { color: c, roughness: 0.45, sheen: 0.8, sheenColor: new THREE.Color( '#4f6a5a' ) } ),
	rubber: () => new THREE.MeshPhysicalMaterial( { color: '#2a2a2a', roughness: 0.85 } ),
	foam: ( c = '#2a2a2a' ) => new THREE.MeshPhysicalMaterial( { color: c, roughness: 1 } ),
};

function paperTexture( base, seed ) {
	const W = 512;
	const c = document.createElement( 'canvas' );
	c.width = W;
	c.height = W;
	const x = c.getContext( '2d' );
	const img = x.createImageData( W, W );
	const { noise, fbm } = makeNoise( seed );
	const B = hex( base );
	for ( let j = 0; j < W; j++ ) {
		for ( let i = 0; i < W; i++ ) {
			const n = ( fbm( i / 40, j / 40, 4 ) - 0.5 ) * 18 + ( noise( i * 0.9, j * 0.9 ) - 0.5 ) * 14;
			const k = ( j * W + i ) * 4;
			img.data[ k ] = B[ 0 ] + n;
			img.data[ k + 1 ] = B[ 1 ] + n;
			img.data[ k + 2 ] = B[ 2 ] + n * 0.9;
			img.data[ k + 3 ] = 255;
		}
	}
	x.putImageData( img, 0, 0 );
	const t = canvasTexture( c, true );
	t.repeat.set( 4, 4 );
	return t;
}

/* ------------------------------------------------------------------ geometry helpers */

const AXES = [ 'x', 'y', 'z' ];

/**
 * Box projection UVs in centimetres / tile, grain along the requested axis where the face allows it.
 */
function boxUV( geo, tile, grain, offset ) {
	const pos = geo.attributes.position;
	const nor = geo.attributes.normal;
	const uv = new Float32Array( pos.count * 2 );
	for ( let i = 0; i < pos.count; i++ ) {
		const n = [ Math.abs( nor.getX( i ) ), Math.abs( nor.getY( i ) ), Math.abs( nor.getZ( i ) ) ];
		const dom = n[ 0 ] >= n[ 1 ] && n[ 0 ] >= n[ 2 ] ? 0 : n[ 1 ] >= n[ 2 ] ? 1 : 2;
		const perp = AXES.filter( ( _, k ) => k !== dom );
		const ua = perp.includes( grain ) ? grain : perp[ 0 ];
		const va = perp.find( ( a ) => a !== ua );
		const p = { x: pos.getX( i ), y: pos.getY( i ), z: pos.getZ( i ) };
		uv[ i * 2 ] = p[ ua ] / tile + offset[ 0 ];
		uv[ i * 2 + 1 ] = p[ va ] / ( tile / 2 ) + offset[ 1 ];
	}
	geo.setAttribute( 'uv', new THREE.BufferAttribute( uv, 2 ) );
	return geo;
}

let uvSeed = rng( 7 );
export function resetSeeds( seed ) {
	uvSeed = rng( seed );
}

/**
 * A (rounded) box mesh. Wood materials get box-projected grain; `grain` is the axis the grain follows.
 */
export function box( w, h, d, mat, { r = 0.12, grain = 'x', seg = 3, shadow = true } = {} ) {
	const rr = Math.min( r, Math.min( w, h, d ) / 2 - 0.001 );
	const geo = rr > 0.01 ? new RoundedBoxGeometry( w, h, d, seg, rr ) : new THREE.BoxGeometry( w, h, d );
	if ( mat.userData.tile ) {
		// The grain texture is not tileable: choose an offset that keeps every face inside one tile.
		const tile = mat.userData.tile;
		const ext = { x: w, y: h, z: d };
		const nonGrain = Math.max( ...AXES.filter( ( a ) => a !== grain ).map( ( a ) => ext[ a ] ) );
		const halfU = Math.max( ext[ grain ], nonGrain ) / ( 2 * tile );
		const halfV = nonGrain / tile;
		const pick = ( half ) => ( half < 0.5 ? half + uvSeed() * ( 1 - 2 * half ) : 0.5 );
		boxUV( geo, tile, grain, [ pick( halfU ), pick( halfV ) ] );
	}
	const m = new THREE.Mesh( geo, mat );
	m.castShadow = shadow;
	m.receiveShadow = true;
	return m;
}

export function cyl( rt, rb, h, mat, { seg = 64, open = false } = {} ) {
	const m = new THREE.Mesh( new THREE.CylinderGeometry( rt, rb, h, seg, 1, open ), mat );
	m.castShadow = true;
	m.receiveShadow = true;
	return m;
}

export function at( obj, x = 0, y = 0, z = 0, rx = 0, ry = 0, rz = 0 ) {
	obj.position.set( x, y, z );
	obj.rotation.set( rx, ry, rz );
	return obj;
}

export function group( ...children ) {
	const g = new THREE.Group();
	children.forEach( ( c ) => g.add( c ) );
	return g;
}

/**
 * Photo sweep: floor that curves up into a back wall, like a studio paper roll.
 */
export function sweep( color, { width = 600, depth = 400, radius = 60, height = 300, z0 = -60, rough = 0.92 } = {} ) {
	const prof = [];
	prof.push( [ depth, 0 ] );
	prof.push( [ z0, 0 ] );
	for ( let i = 1; i <= 24; i++ ) {
		const a = ( i / 24 ) * ( Math.PI / 2 );
		prof.push( [ z0 - Math.sin( a ) * radius, radius - Math.cos( a ) * radius ] );
	}
	prof.push( [ z0 - radius, height ] );
	const pos = [];
	const idx = [];
	prof.forEach( ( [ z, y ] ) => {
		pos.push( -width / 2, y, z, width / 2, y, z );
	} );
	for ( let i = 0; i < prof.length - 1; i++ ) {
		const a = i * 2;
		idx.push( a, a + 2, a + 1, a + 1, a + 2, a + 3 );
	}
	const geo = new THREE.BufferGeometry();
	geo.setAttribute( 'position', new THREE.Float32BufferAttribute( pos, 3 ) );
	geo.setIndex( idx );
	geo.computeVertexNormals();
	const mat = new THREE.MeshPhysicalMaterial( { color, roughness: rough, side: THREE.DoubleSide } );
	const m = new THREE.Mesh( geo, mat );
	m.receiveShadow = true;
	return m;
}

/* ------------------------------------------------------------------ dials and displays */

function canvas2d( w, h = w ) {
	const c = document.createElement( 'canvas' );
	c.width = w;
	c.height = h;
	return [ c, c.getContext( '2d' ) ];
}

function dialTexture( { face = '#efe6d2', ink = '#2a2420', value = 70 } = {} ) {
	const S = 1024;
	const [ c, x ] = canvas2d( S );
	const g = x.createRadialGradient( S / 2, S / 2, 40, S / 2, S / 2, S / 2 );
	g.addColorStop( 0, face );
	g.addColorStop( 1, '#d9ccb2' );
	x.fillStyle = g;
	x.fillRect( 0, 0, S, S );
	x.translate( S / 2, S / 2 );
	const a0 = -225 * DEG;
	const span = 270 * DEG;
	// comfort band 65-75 %
	x.strokeStyle = '#7d8f6a';
	x.lineWidth = 26;
	x.beginPath();
	x.arc( 0, 0, 380, a0 + span * 0.65, a0 + span * 0.75 );
	x.stroke();
	x.strokeStyle = ink;
	for ( let i = 0; i <= 100; i += 2 ) {
		const a = a0 + span * ( i / 100 );
		const major = i % 10 === 0;
		x.lineWidth = major ? 7 : 3;
		x.beginPath();
		x.moveTo( Math.cos( a ) * ( major ? 330 : 355 ), Math.sin( a ) * ( major ? 330 : 355 ) );
		x.lineTo( Math.cos( a ) * 395, Math.sin( a ) * 395 );
		x.stroke();
	}
	x.fillStyle = ink;
	x.font = '600 62px "Hanken Grotesk", sans-serif';
	x.textAlign = 'center';
	x.textBaseline = 'middle';
	for ( let i = 0; i <= 100; i += 20 ) {
		const a = a0 + span * ( i / 100 );
		x.fillText( String( i ), Math.cos( a ) * 265, Math.sin( a ) * 265 );
	}
	x.font = '600 46px "Hanken Grotesk", sans-serif';
	x.fillText( 'HUMIDITY', 0, 150 );
	x.font = '400 40px "Hanken Grotesk", sans-serif';
	x.fillText( '% RH', 0, 210 );
	// needle
	const a = a0 + span * ( value / 100 );
	x.strokeStyle = '#8a2f1d';
	x.lineWidth = 12;
	x.lineCap = 'round';
	x.beginPath();
	x.moveTo( -Math.cos( a ) * 60, -Math.sin( a ) * 60 );
	x.lineTo( Math.cos( a ) * 360, Math.sin( a ) * 360 );
	x.stroke();
	x.fillStyle = '#2a2420';
	x.beginPath();
	x.arc( 0, 0, 34, 0, Math.PI * 2 );
	x.fill();
	const t = new THREE.CanvasTexture( c );
	t.colorSpace = THREE.SRGBColorSpace;
	t.anisotropy = 16;
	return t;
}

function lcdTexture( { round = true, rh = '70', temp = '68°F' } = {} ) {
	const S = 1024;
	const [ c, x ] = canvas2d( S );
	x.fillStyle = '#151617';
	x.fillRect( 0, 0, S, S );
	const lcd = x.createLinearGradient( 0, 200, 0, 820 );
	lcd.addColorStop( 0, '#c3cbb8' );
	lcd.addColorStop( 1, '#aab5a0' );
	x.fillStyle = lcd;
	if ( round ) {
		x.beginPath();
		x.arc( S / 2, S / 2, 400, 0, Math.PI * 2 );
		x.fill();
	} else {
		x.fillRect( 40, 40, S - 80, S - 80 );
	}
	x.fillStyle = '#23281f';
	x.textAlign = 'center';
	x.textBaseline = 'alphabetic';
	x.font = '700 330px "Hanken Grotesk", sans-serif';
	x.fillText( rh, S / 2 - 60, S / 2 + 110 );
	x.font = '700 120px "Hanken Grotesk", sans-serif';
	x.fillText( '%', S / 2 + 230, S / 2 + 110 );
	x.font = '600 70px "Hanken Grotesk", sans-serif';
	x.fillText( 'RH', S / 2 + 230, S / 2 - 70 );
	x.font = '600 110px "Hanken Grotesk", sans-serif';
	x.fillText( temp, S / 2, S / 2 + 270 );
	x.globalAlpha = 0.08;
	x.font = '700 330px "Hanken Grotesk", sans-serif';
	x.fillText( '88', S / 2 - 60, S / 2 + 110 );
	const t = new THREE.CanvasTexture( c );
	t.colorSpace = THREE.SRGBColorSpace;
	t.anisotropy = 16;
	return t;
}

/** Analog hygrometer, face towards +z, back at z = 0. Diameter in cm. */
export function analogHygrometer( dia = 7, bezel = MAT.brass() ) {
	const g = new THREE.Group();
	const r = dia / 2;
	const ring = new THREE.Mesh( new THREE.TorusGeometry( r - 0.25, 0.32, 24, 96 ), bezel );
	ring.position.z = 0.55;
	ring.castShadow = true;
	g.add( ring );
	g.add( at( cyl( r - 0.1, r - 0.1, 0.5, bezel ), 0, 0, 0.25, Math.PI / 2 ) );
	const face = new THREE.Mesh( new THREE.CircleGeometry( r - 0.5, 96 ), new THREE.MeshPhysicalMaterial( { map: dialTexture(), roughness: 0.6 } ) );
	face.position.z = 0.52;
	g.add( face );
	const glass = new THREE.Mesh( new THREE.CircleGeometry( r - 0.45, 96 ), new THREE.MeshPhysicalMaterial( { color: '#ffffff', transparent: true, opacity: 0.08, roughness: 0.02, metalness: 0, clearcoat: 1 } ) );
	glass.position.z = 0.75;
	g.add( glass );
	return g;
}

/** Round digital hygrometer, face towards +z. */
export function digitalHygrometer( dia = 6.4, { body = MAT.blackPlastic(), rim = MAT.steel() } = {} ) {
	const g = new THREE.Group();
	const r = dia / 2;
	g.add( at( cyl( r, r, 1.4, body ), 0, 0, 0.7, Math.PI / 2 ) );
	const ring = new THREE.Mesh( new THREE.TorusGeometry( r - 0.1, 0.18, 20, 96 ), rim );
	ring.position.z = 1.4;
	g.add( ring );
	const face = new THREE.Mesh( new THREE.CircleGeometry( r - 0.3, 96 ), new THREE.MeshPhysicalMaterial( { map: lcdTexture(), roughness: 0.25, clearcoat: 1, clearcoatRoughness: 0.05 } ) );
	face.position.z = 1.41;
	g.add( face );
	return g;
}

/** Rectangular front-mount digital display, face towards +z. */
export function digitalPanel( w = 5.5, h = 3.4 ) {
	const g = new THREE.Group();
	g.add( at( box( w, h, 0.6, MAT.blackPlastic(), { r: 0.2 } ), 0, 0, 0.3 ) );
	const [ c, x ] = canvas2d( 1024, Math.round( 1024 * h / w ) );
	x.fillStyle = '#121314';
	x.fillRect( 0, 0, c.width, c.height );
	x.fillStyle = '#b9c3ad';
	x.fillRect( 60, 60, c.width - 120, c.height - 120 );
	x.fillStyle = '#23281f';
	x.font = '700 300px "Hanken Grotesk", sans-serif';
	x.textBaseline = 'middle';
	x.fillText( '70', 110, c.height / 2 + 10 );
	x.font = '700 110px "Hanken Grotesk", sans-serif';
	x.fillText( '%RH', 470, c.height / 2 - 50 );
	x.font = '600 100px "Hanken Grotesk", sans-serif';
	x.fillText( '68°F', 480, c.height / 2 + 80 );
	const t = new THREE.CanvasTexture( c );
	t.colorSpace = THREE.SRGBColorSpace;
	const face = new THREE.Mesh( new THREE.PlaneGeometry( w - 0.5, h - 0.5 ), new THREE.MeshPhysicalMaterial( { map: t, roughness: 0.2, clearcoat: 1 } ) );
	face.position.z = 0.61;
	g.add( face );
	return g;
}

/** Humidifier jar: short black jar with a perforated brass lid, standing on y = 0. */
export function humidifierJar( r = 2.4, h = 3.2 ) {
	const g = new THREE.Group();
	g.add( at( cyl( r, r, h, MAT.blackPlastic(), { seg: 64 } ), 0, h / 2, 0 ) );
	const lid = MAT.brassBrushed();
	g.add( at( cyl( r + 0.1, r + 0.1, 0.5, lid ), 0, h + 0.25, 0 ) );
	const holeMat = new THREE.MeshPhysicalMaterial( { color: '#141414', roughness: 0.9 } );
	for ( let ring = 0; ring < 3; ring++ ) {
		const rr = 0.45 + ring * 0.6;
		const n = ring === 0 ? 1 : 6 * ring;
		for ( let i = 0; i < n; i++ ) {
			const a = ( i / n ) * Math.PI * 2;
			const hole = new THREE.Mesh( new THREE.CircleGeometry( 0.16, 16 ), holeMat );
			hole.rotation.x = -Math.PI / 2;
			hole.position.set( ring === 0 ? 0 : Math.cos( a ) * rr, h + 0.51, ring === 0 ? 0 : Math.sin( a ) * rr );
			g.add( hole );
		}
	}
	return g;
}

/** Humidifier bar: flat black bar with slots, lying on y = 0. */
export function humidifierBar( l = 14, w = 3.2, h = 1.6 ) {
	const g = new THREE.Group();
	g.add( at( box( l, h, w, MAT.blackPlastic(), { r: 0.4 } ), 0, h / 2, 0 ) );
	const slot = new THREE.MeshPhysicalMaterial( { color: '#070707', roughness: 1 } );
	for ( let i = 0; i < 9; i++ ) {
		const s = new THREE.Mesh( new THREE.PlaneGeometry( 0.35, w * 0.55 ), slot );
		s.rotation.x = -Math.PI / 2;
		s.position.set( -l / 2 + 2 + i * ( ( l - 4 ) / 8 ), h + 0.005, 0 );
		g.add( s );
	}
	return g;
}

/** Double-blade stainless cutter, lying flat on y = 0. */
export function cutter() {
	const s = new THREE.Shape();
	const L = 4.6;
	const R = 1.75;
	s.moveTo( -L + R, -R );
	s.lineTo( L - R, -R );
	s.absarc( L - R, 0, R, -Math.PI / 2, Math.PI / 2, false );
	s.lineTo( -L + R, R );
	s.absarc( -L + R, 0, R, Math.PI / 2, ( 3 * Math.PI ) / 2, false );
	const hole = new THREE.Path();
	hole.absarc( 0, 0, 1.05, 0, Math.PI * 2, true );
	s.holes.push( hole );
	[ -1, 1 ].forEach( ( side ) => {
		const f = new THREE.Path();
		f.absellipse( side * ( L - 1.25 ), 0, 0.7, 1.05, 0, Math.PI * 2, true );
		s.holes.push( f );
	} );
	const geo = new THREE.ExtrudeGeometry( s, { depth: 0.55, bevelEnabled: true, bevelThickness: 0.12, bevelSize: 0.1, bevelSegments: 4, curveSegments: 48 } );
	geo.rotateX( -Math.PI / 2 );
	const m = new THREE.Mesh( geo, MAT.steel() );
	m.castShadow = true;
	m.receiveShadow = true;
	m.position.y = 0.12;
	return m;
}

/* ------------------------------------------------------------------ humidors */

/**
 * A box humidor (or cigar box): walls of `wood`, Spanish cedar lining with a raised lip, a hinged lid.
 * Origin at the centre of the base; front faces +z. `open` is the lid angle in degrees.
 *
 * @param {object} o Options.
 */
export function humidor( o ) {
	const {
		W = 32, D = 22, H = 12.5, lidH = 4.2, t = 1.3, wood: wd, lining, open = 0, gap = 0.12,
		r = 0.35, lip = 0.9, divider = null, hygro = null, front = null, lock = false, plinth = false,
		hinges = true, lidLining = null, humidifier = 'bar', tray = false, noLid = false,
	} = o;
	const g = new THREE.Group();
	const bodyH = H - lidH;
	const y0 = plinth ? 1.2 : 0;
	const bh = bodyH - y0;
	if ( plinth ) {
		g.add( at( box( W - 1.2, y0, D - 1.2, wd, { r: 0.2 } ), 0, y0 / 2, 0 ) );
	}
	const iw = W - 2 * t;
	const id = D - 2 * t;
	g.add( at( box( W, bh, t, wd, { r } ), 0, y0 + bh / 2, D / 2 - t / 2 ) );
	g.add( at( box( W, bh, t, wd, { r } ), 0, y0 + bh / 2, -D / 2 + t / 2 ) );
	g.add( at( box( t, bh, id + 0.02, wd, { r, grain: 'z' } ), W / 2 - t / 2, y0 + bh / 2, 0 ) );
	g.add( at( box( t, bh, id + 0.02, wd, { r, grain: 'z' } ), -W / 2 + t / 2, y0 + bh / 2, 0 ) );
	g.add( at( box( iw, t, id, wd, { r: 0.05 } ), 0, y0 + t / 2, 0 ) );
	if ( lining ) {
		const lt = 0.45;
		const lh = bh - t + lip;
		const ly = y0 + t + lh / 2;
		g.add( at( box( iw, 0.3, id, lining, { r: 0.02 } ), 0, y0 + t + 0.15, 0 ) );
		g.add( at( box( iw, lh, lt, lining, { r: 0.08 } ), 0, ly, D / 2 - t - lt / 2 ) );
		g.add( at( box( iw, lh, lt, lining, { r: 0.08 } ), 0, ly, -D / 2 + t + lt / 2 ) );
		g.add( at( box( lt, lh, id - 2 * lt, lining, { r: 0.08, grain: 'z' } ), iw / 2 - lt / 2, ly, 0 ) );
		g.add( at( box( lt, lh, id - 2 * lt, lining, { r: 0.08, grain: 'z' } ), -iw / 2 + lt / 2, ly, 0 ) );
		if ( divider !== null ) {
			g.add( at( box( 0.5, lh - 2.2, id - 2 * lt - 0.1, lining, { r: 0.1, grain: 'z' } ), divider, y0 + t + 0.3 + ( lh - 2.2 ) / 2, 0 ) );
		}
		if ( tray ) {
			// lift-out tray resting on the lining ledge, left half
			const tw = iw / 2 - 1.2;
			const td = id - 2 * lt - 0.4;
			const th = 2.4;
			const ty = y0 + bh - th - 0.2;
			const tg = new THREE.Group();
			tg.add( at( box( tw, 0.3, td, lining, { r: 0.05 } ), 0, 0.15, 0 ) );
			tg.add( at( box( tw, th, 0.35, lining, { r: 0.06 } ), 0, th / 2, td / 2 - 0.175 ) );
			tg.add( at( box( tw, th, 0.35, lining, { r: 0.06 } ), 0, th / 2, -td / 2 + 0.175 ) );
			tg.add( at( box( 0.35, th, td, lining, { r: 0.06, grain: 'z' } ), tw / 2 - 0.175, th / 2, 0 ) );
			tg.add( at( box( 0.35, th, td, lining, { r: 0.06, grain: 'z' } ), -tw / 2 + 0.175, th / 2, 0 ) );
			tg.position.set( -iw / 4 + 0.4, ty, 0 );
			g.add( tg );
		}
	}
	if ( front === 'digital' ) {
		g.add( at( digitalPanel(), 0, y0 + bh * 0.52, D / 2 ) );
	}
	if ( lock ) {
		const plate = box( 1.6, 2.0, 0.12, MAT.brass(), { r: 0.06 } );
		g.add( at( plate, 0, y0 + bh - 1.4, D / 2 + 0.06 ) );
		const hole = new THREE.Mesh( new THREE.CircleGeometry( 0.22, 24 ), new THREE.MeshBasicMaterial( { color: '#0b0b0b' } ) );
		g.add( at( hole, 0, y0 + bh - 1.25, D / 2 + 0.125 ) );
		const slot = new THREE.Mesh( new THREE.PlaneGeometry( 0.16, 0.5 ), new THREE.MeshBasicMaterial( { color: '#0b0b0b' } ) );
		g.add( at( slot, 0, y0 + bh - 1.55, D / 2 + 0.125 ) );
	}

	if ( noLid ) {
		return g;
	}
	const lid = new THREE.Group();
	lid.position.set( 0, bodyH + gap, -D / 2 );
	lid.rotation.x = -open * DEG;
	const lw = lidH - t;
	const lr = Math.min( r, 0.08 );
	lid.add( at( box( W, t, D, wd, { r } ), 0, lidH - t / 2, D / 2 ) );
	lid.add( at( box( W, lw + 0.1, t, wd, { r: lr } ), 0, lw / 2, D - t / 2 ) );
	lid.add( at( box( W, lw + 0.1, t, wd, { r: lr } ), 0, lw / 2, t / 2 ) );
	lid.add( at( box( t, lw + 0.1, D - 2 * t + 0.02, wd, { r: lr, grain: 'z' } ), W / 2 - t / 2, lw / 2, D / 2 ) );
	lid.add( at( box( t, lw + 0.1, D - 2 * t + 0.02, wd, { r: lr, grain: 'z' } ), -W / 2 + t / 2, lw / 2, D / 2 ) );
	const underside = lidLining || lining;
	if ( underside ) {
		lid.add( at( box( iw, 0.4, id, underside, { r: 0.04 } ), 0, lidH - t - 0.2, D / 2 ) );
	}
	if ( hygro === 'analog' ) {
		const hg = analogHygrometer( 6.4 );
		hg.rotation.x = Math.PI / 2;
		hg.position.set( iw * 0.22, lidH - t - 0.4, D / 2 );
		lid.add( hg );
		if ( humidifier === 'bar' ) {
			const hb = humidifierBar( 10, 2.8, 1.2 );
			hb.rotation.x = Math.PI;
			hb.position.set( -iw * 0.2, lidH - t - 0.4, D / 2 );
			lid.add( hb );
		}
	}
	g.add( lid );

	if ( hinges ) {
		const brass = MAT.brass();
		[ -1, 1 ].forEach( ( s ) => {
			const k = cyl( 0.32, 0.32, 3.2, brass, { seg: 24 } );
			k.rotation.z = Math.PI / 2;
			k.position.set( s * W * 0.3, bodyH + gap / 2, -D / 2 - 0.12 );
			g.add( k );
		} );
	}
	g.userData.lid = lid;
	return g;
}

/**
 * Clamshell travel humidor with rounded corners: two halves meeting at the seam.
 */
export function travelHumidor( { W = 23, D = 9.5, H = 6.4, wood: wd, lining, open = 0 } = {} ) {
	const g = new THREE.Group();
	const half = H / 2;
	g.add( at( box( W, half, D, wd, { r: 1.2, seg: 6 } ), 0, half / 2, 0 ) );
	if ( lining && open > 0 ) {
		// cedar insert with channels, sitting proud of the lower half
		const ins = new THREE.Group();
		ins.add( at( box( W - 1.6, 0.8, D - 1.6, lining, { r: 0.1 } ), 0, half - 0.1, 0 ) );
		for ( let i = 0; i < 6; i++ ) {
			ins.add( at( box( W - 2.4, 0.6, 0.3, lining, { r: 0.1 } ), 0, half + 0.55, -D / 2 + 1.2 + i * ( ( D - 2.4 ) / 5 ) ) );
		}
		g.add( ins );
	}
	const top = new THREE.Group();
	top.position.set( 0, half + 0.08, -D / 2 );
	top.rotation.x = -open * DEG;
	top.add( at( box( W, half, D, wd, { r: 1.2, seg: 6 } ), 0, half / 2, D / 2 ) );
	if ( lining && open > 0 ) {
		top.add( at( box( W - 1.6, 0.4, D - 1.6, lining, { r: 0.1 } ), 0, -0.15, D / 2 ) );
	}
	g.add( top );
	return g;
}

/* ------------------------------------------------------------------ studio */

/**
 * Environment for reflections: a dark room with emissive softboxes, so lacquer and brass reflect
 * the same lights that light the scene.
 *
 * @param {string} ground   Room colour.
 * @param {Array}  panels   [{ dir: [x,y,z], w, h, color, power }]
 */
function softboxRoom( ground, panels ) {
	const scene = new THREE.Scene();
	const room = new THREE.Mesh( new THREE.SphereGeometry( 100, 32, 16 ), new THREE.MeshBasicMaterial( { color: ground, side: THREE.BackSide } ) );
	scene.add( room );
	const floor = new THREE.Mesh( new THREE.CircleGeometry( 99, 32 ), new THREE.MeshBasicMaterial( { color: new THREE.Color( ground ).multiplyScalar( 0.6 ) } ) );
	floor.rotation.x = -Math.PI / 2;
	floor.position.y = -20;
	scene.add( floor );
	panels.forEach( ( p ) => {
		const m = new THREE.Mesh( new THREE.PlaneGeometry( p.w, p.h ), new THREE.MeshBasicMaterial( { color: new THREE.Color( p.color || '#ffffff' ).multiplyScalar( p.power || 4 ), side: THREE.DoubleSide } ) );
		const d = new THREE.Vector3( ...p.dir ).normalize().multiplyScalar( 80 );
		m.position.copy( d );
		m.lookAt( 0, 0, 0 );
		scene.add( m );
	} );
	return scene;
}

/**
 * Lights for a shot. `key` is an area light (position, softness radius), `sky` a hemisphere of
 * shadow-casting light sampled one direction per frame, `fill` and `rim` plain lights.
 */
export class Studio {
	constructor( w, h ) {
		this.w = w;
		this.h = h;
		this.renderer = new THREE.WebGLRenderer( { antialias: false, preserveDrawingBuffer: true } );
		this.renderer.setPixelRatio( 1 );
		this.renderer.setSize( w, h );
		this.renderer.shadowMap.enabled = true;
		this.renderer.shadowMap.type = THREE.PCFShadowMap;
		this.renderer.toneMapping = THREE.NeutralToneMapping;
		this.renderer.toneMappingExposure = 1;
		this.renderer.outputColorSpace = THREE.SRGBColorSpace;
		this.scene = new THREE.Scene();
		const pmrem = new THREE.PMREMGenerator( this.renderer );
		this.scene.environment = pmrem.fromScene( new RoomEnvironment(), 0.04 ).texture;
		this.scene.environmentIntensity = 0.45;
		this.camera = new THREE.PerspectiveCamera( 30, w / h, 1, 3000 );
		this.lights = [];
	}

	add( ...objs ) {
		objs.forEach( ( o ) => this.scene.add( o ) );
	}

	shadowLight( color, intensity, extent, size = 2048 ) {
		const l = new THREE.DirectionalLight( color, intensity );
		l.castShadow = true;
		l.shadow.mapSize.set( size, size );
		const c = l.shadow.camera;
		c.left = -extent;
		c.right = extent;
		c.top = extent;
		c.bottom = -extent;
		c.near = 1;
		c.far = 2000;
		l.shadow.bias = -0.0006;
		l.shadow.normalBias = 0.12;
		this.scene.add( l );
		this.scene.add( l.target );
		return l;
	}

	/**
	 * @param {object} o key: {pos, color, intensity, soft}; sky: {color, intensity, cone}; fill, rim: {pos, color, intensity}
	 */
	lighting( o ) {
		const ext = o.extent || 80;
		const target = new THREE.Vector3( ...( o.target || [ 0, 0, 0 ] ) );
		if ( o.key ) {
			this.key = this.shadowLight( o.key.color || '#fff1df', o.key.intensity ?? 3, ext );
			this.key.target.position.copy( target );
			this.keyCfg = o.key;
		}
		if ( o.sky ) {
			this.sky = this.shadowLight( o.sky.color || '#f4f1ec', o.sky.intensity ?? 1.2, ext, 1024 );
			this.sky.target.position.copy( target );
			this.skyCfg = o.sky;
		}
		( o.extra || [] ).forEach( ( e ) => {
			const l = e.spot ? new THREE.SpotLight( e.color, e.intensity, 0, e.angle || 0.6, 0.8, 0 ) : new THREE.DirectionalLight( e.color, e.intensity );
			l.position.set( ...e.pos );
			l.target.position.copy( e.target ? new THREE.Vector3( ...e.target ) : target );
			if ( e.shadow ) {
				l.castShadow = true;
				l.shadow.mapSize.set( 1024, 1024 );
				l.shadow.bias = -0.0006;
				l.shadow.normalBias = 0.12;
				if ( ! e.spot ) {
					Object.assign( l.shadow.camera, { left: -ext, right: ext, top: ext, bottom: -ext, near: 1, far: 2000 } );
				}
			}
			this.scene.add( l );
			this.scene.add( l.target );
		} );
		if ( o.room ) {
			const pmrem = new THREE.PMREMGenerator( this.renderer );
			this.scene.environment = pmrem.fromScene( softboxRoom( o.room.ground, o.room.panels ), 0.02 ).texture;
		}
		if ( o.env !== undefined ) {
			this.scene.environmentIntensity = o.env;
		}
		if ( o.ao !== false ) {
			const ao = Object.assign( { radius: 6, distanceExponent: 1.4, thickness: 4, scale: 1.2, samples: 16, blend: 0.9 }, o.ao || {} );
			this.composer = new EffectComposer( this.renderer );
			this.composer.setPixelRatio( 1 );
			this.composer.setSize( this.w, this.h );
			this.composer.addPass( new RenderPass( this.scene, this.camera ) );
			const pass = new GTAOPass( this.scene, this.camera, this.w, this.h );
			pass.updateGtaoMaterial( { radius: ao.radius, distanceExponent: ao.distanceExponent, thickness: ao.thickness, scale: ao.scale, samples: ao.samples } );
			pass.updatePdMaterial( { lumaPhi: 10, depthPhi: 2, normalPhi: 3, radius: 6, rings: 2, samples: 16 } );
			pass.blendIntensity = ao.blend;
			this.composer.addPass( pass );
			this.composer.addPass( new OutputPass() );
		}
		if ( o.exposure ) {
			this.renderer.toneMappingExposure = o.exposure;
		}
		this.target = target;
	}

	/**
	 * Camera: position, target (focus), vertical fov, aperture radius in cm (0 = pinhole).
	 */
	view( pos, target, fov = 30, aperture = 0 ) {
		this.camPos = new THREE.Vector3( ...pos );
		this.camTarget = new THREE.Vector3( ...target );
		this.camera.fov = fov;
		this.aperture = aperture;
		this.camera.updateProjectionMatrix();
	}

	/**
	 * Renders `frames` jittered frames and returns their average on a 2D canvas.
	 */
	async render( frames = 32, seed = 1 ) {
		const rand = rng( seed );
		const out = document.createElement( 'canvas' );
		out.width = this.w;
		out.height = this.h;
		const ctx = out.getContext( '2d' );
		const gl = this.renderer.domElement;
		const fwd = new THREE.Vector3();
		const right = new THREE.Vector3();
		const up = new THREE.Vector3();
		for ( let i = 0; i < frames; i++ ) {
			// stratified jitter
			const u1 = ( i + rand() ) / frames;
			const u2 = rand();
			// camera: sub-pixel offset and lens position
			this.camera.setViewOffset( this.w, this.h, rand() - 0.5, rand() - 0.5, this.w, this.h );
			fwd.subVectors( this.camTarget, this.camPos ).normalize();
			right.crossVectors( fwd, new THREE.Vector3( 0, 1, 0 ) ).normalize();
			up.crossVectors( right, fwd ).normalize();
			const lr = Math.sqrt( u1 ) * this.aperture;
			const la = u2 * Math.PI * 2 * 7.13;
			this.camera.position.copy( this.camPos ).addScaledVector( right, Math.cos( la ) * lr ).addScaledVector( up, Math.sin( la ) * lr );
			this.camera.lookAt( this.camTarget );
			if ( this.key ) {
				const k = this.keyCfg;
				const base = new THREE.Vector3( ...k.pos );
				const dir = base.clone().sub( this.target ).normalize();
				const kr = new THREE.Vector3().crossVectors( dir, new THREE.Vector3( 0, 1, 0 ) ).normalize();
				const ku = new THREE.Vector3().crossVectors( kr, dir ).normalize();
				const s = Math.sqrt( rand() ) * ( k.soft || 20 );
				const a = rand() * Math.PI * 2;
				this.key.position.copy( base ).addScaledVector( kr, Math.cos( a ) * s ).addScaledVector( ku, Math.sin( a ) * s );
			}
			if ( this.sky ) {
				const cone = ( this.skyCfg.cone || 55 ) * DEG;
				const th = Math.acos( 1 - rand() * ( 1 - Math.cos( cone ) ) );
				const ph = rand() * Math.PI * 2;
				const dist = 600;
				const dirv = new THREE.Vector3( Math.sin( th ) * Math.cos( ph ), Math.cos( th ), Math.sin( th ) * Math.sin( ph ) );
				if ( this.skyCfg.bias ) {
					dirv.add( new THREE.Vector3( ...this.skyCfg.bias ) ).normalize();
				}
				this.sky.position.copy( this.target ).addScaledVector( dirv, dist );
			}
			if ( this.composer ) {
				this.composer.render();
			} else {
				this.renderer.render( this.scene, this.camera );
			}
			ctx.globalAlpha = 1 / ( i + 1 );
			ctx.drawImage( gl, 0, 0 );
			if ( i % 4 === 3 ) {
				await new Promise( ( r ) => setTimeout( r, 0 ) );
			}
		}
		ctx.globalAlpha = 1;
		return out;
	}
}

/**
 * Photographic finish on the averaged frame: gentle vignette and fine grain.
 */
export function finish( canvas, { vignette = 0.22, grain = 3.2, label = '' } = {} ) {
	const ctx = canvas.getContext( '2d' );
	const { width: w, height: h } = canvas;
	const g = ctx.createRadialGradient( w / 2, h / 2, Math.min( w, h ) * 0.35, w / 2, h / 2, Math.hypot( w, h ) * 0.6 );
	g.addColorStop( 0, 'rgba(0,0,0,0)' );
	g.addColorStop( 1, `rgba(0,0,0,${ vignette })` );
	ctx.fillStyle = g;
	ctx.fillRect( 0, 0, w, h );
	const img = ctx.getImageData( 0, 0, w, h );
	const r = rng( 99 );
	for ( let i = 0; i < img.data.length; i += 4 ) {
		const n = ( r() + r() - 1 ) * grain;
		img.data[ i ] += n;
		img.data[ i + 1 ] += n;
		img.data[ i + 2 ] += n;
	}
	ctx.putImageData( img, 0, 0 );
	if ( label ) {
		const fs = Math.round( w * 0.018 );
		ctx.font = `600 ${ fs }px "Hanken Grotesk", sans-serif`;
		ctx.textAlign = 'right';
		ctx.textBaseline = 'bottom';
		ctx.fillStyle = 'rgba(26,24,21,0.42)';
		ctx.fillText( label, w - fs * 1.4, h - fs * 1.2 );
	}
	return canvas;
}

export { THREE, ParametricGeometry };
