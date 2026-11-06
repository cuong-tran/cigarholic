/**
 * Shots for the Cigarholic sample photos. Each shot builds a scene in a Studio and returns it;
 * `window.renderShot( name, frames )` renders one and returns a data URL.
 *
 * Feature IDs: CNT-01, NAV-04, FND-02.
 */

import { Studio, THREE, DEG, wood, MAT, box, cyl, at, sweep, humidor, travelHumidor, analogHygrometer, digitalHygrometer, humidifierJar, humidifierBar, cutter, finish, resetSeeds, rng, ParametricGeometry } from './lib.js';

const SAND = '#dcd3c4';
const ASH = '#ece6dc';
const FOREST = '#26342d';

/* ------------------------------------------------------------------ reusable pieces */

function walnutDesktop( { open = 100, finishName = 'gloss' } = {} ) {
	return humidor( {
		W: 32, D: 23, H: 12.5, lidH: 4.4, wood: wood( 'walnut', finishName ), lining: wood( 'cedar', 'raw', { tile: 40 } ),
		open, divider: 3.5, hygro: 'analog', plinth: true, r: 0.18,
	} );
}

function mahoganyDesktop( { open = 0 } = {} ) {
	return humidor( {
		W: 38, D: 26.5, H: 16.5, lidH: 5.2, wood: wood( 'mahogany', 'gloss', { tile: 70 } ), lining: wood( 'cedar', 'raw', { tile: 40 } ),
		open, front: 'digital', lock: true, plinth: true, divider: 2, tray: open > 0, hygro: open > 0 ? 'analog' : null,
	} );
}

function oakCabinet() {
	const g = new THREE.Group();
	const oak = wood( 'oak', 'matte', { tile: 120 } );
	const cedar = wood( 'cedar', 'raw', { tile: 50 } );
	const W = 60;
	const D = 45;
	const H = 102;
	const base = 8;
	const t = 2;
	// plinth and carcass
	g.add( at( box( W - 3, base, D - 3, wood( 'oak', 'matte', { tile: 120, tint: '#d8d0c4' } ), { r: 0.3 } ), 0, base / 2, -0.5 ) );
	const cy = base + ( H - base ) / 2;
	const ch = H - base;
	g.add( at( box( t, ch, D, oak, { r: 0.4, grain: 'y' } ), -W / 2 + t / 2, cy, 0 ) );
	g.add( at( box( t, ch, D, oak, { r: 0.4, grain: 'y' } ), W / 2 - t / 2, cy, 0 ) );
	g.add( at( box( W, t * 1.5, D + 1, oak, { r: 0.5 } ), 0, H - t * 0.75, 0.5 ) );
	g.add( at( box( W - 2 * t, t, D, oak, { r: 0.2 } ), 0, base + t / 2, 0 ) );
	g.add( at( box( W - 2 * t, ch, t, cedar, { r: 0.1, grain: 'y' } ), 0, cy, -D / 2 + t / 2 ) );
	// two drawers below the door
	const drawerH = 13;
	const df = base + t;
	[ -1, 1 ].forEach( ( s ) => {
		const dw = ( W - 2 * t ) / 2 - 0.3;
		g.add( at( box( dw, drawerH - 0.4, 1.8, oak, { r: 0.3 } ), s * ( dw / 2 + 0.15 ), df + drawerH / 2, D / 2 - 0.9 ) );
		g.add( at( box( 8, 0.9, 1.2, MAT.brassBrushed(), { r: 0.35 } ), s * ( dw / 2 + 0.15 ), df + drawerH / 2, D / 2 + 0.6 ) );
	} );
	const shelfY = df + drawerH;
	g.add( at( box( W - 2 * t, t, D - 2, oak, { r: 0.2 } ), 0, shelfY + t / 2, -1 ) );
	// interior: cedar side linings and four pull-out trays
	const innerTop = H - t * 1.5;
	const doorBottom = shelfY + t;
	g.add( at( box( 0.6, innerTop - doorBottom, D - 4, cedar, { r: 0.05, grain: 'y' } ), -W / 2 + t + 0.3, ( innerTop + doorBottom ) / 2, -1 ) );
	g.add( at( box( 0.6, innerTop - doorBottom, D - 4, cedar, { r: 0.05, grain: 'y' } ), W / 2 - t - 0.3, ( innerTop + doorBottom ) / 2, -1 ) );
	const span = innerTop - doorBottom - 14;
	for ( let i = 0; i < 4; i++ ) {
		const ty = doorBottom + 3 + i * ( span / 3 );
		const tw = W - 2 * t - 2;
		const tray = new THREE.Group();
		tray.add( at( box( tw, 0.6, D - 9, cedar, { r: 0.05 } ), 0, 0.3, 0 ) );
		tray.add( at( box( tw, 4.2, 0.8, cedar, { r: 0.2 } ), 0, 2.1, ( D - 9 ) / 2 - 0.4 ) );
		tray.add( at( box( tw, 4.2, 0.8, cedar, { r: 0.2 } ), 0, 2.1, -( D - 9 ) / 2 + 0.4 ) );
		tray.add( at( box( 5, 0.7, 0.8, MAT.brassBrushed(), { r: 0.3 } ), 0, 2.4, ( D - 9 ) / 2 + 0.3 ) );
		tray.position.set( 0, ty, -1.5 + ( i === 1 ? 4 : 0 ) );
		g.add( tray );
	}
	// display on the inside of the top rail
	const panel = digitalHygrometer( 5.2 );
	panel.position.set( 0, innerTop - 5.5, -D / 2 + t + 0.1 );
	g.add( panel );
	// glazed door, slightly open
	const door = new THREE.Group();
	const dh = innerTop - doorBottom + t * 1.5;
	const dw = W;
	const fr = 5.5;
	const fd = 2.6;
	door.add( at( box( fr, dh, fd, oak, { r: 0.4, grain: 'y' } ), fr / 2, dh / 2, 0 ) );
	door.add( at( box( fr, dh, fd, oak, { r: 0.4, grain: 'y' } ), dw - fr / 2, dh / 2, 0 ) );
	door.add( at( box( dw - 2 * fr + 0.2, fr, fd, oak, { r: 0.4 } ), dw / 2, fr / 2, 0 ) );
	door.add( at( box( dw - 2 * fr + 0.2, fr, fd, oak, { r: 0.4 } ), dw / 2, dh - fr / 2, 0 ) );
	const glass = new THREE.Mesh( new THREE.BoxGeometry( dw - 2 * fr + 1, dh - 2 * fr + 1, 0.5 ), MAT.glass() );
	glass.position.set( dw / 2, dh / 2, 0 );
	door.add( glass );
	door.add( at( box( 1.1, 22, 1.4, MAT.brassBrushed(), { r: 0.5 } ), dw - fr / 2, dh / 2, fd / 2 + 1.4 ) );
	[ -7, 7 ].forEach( ( o ) => door.add( at( cyl( 0.4, 0.4, 1.6, MAT.brassBrushed(), { seg: 16 } ), dw - fr / 2, dh / 2 + o, fd / 2 + 0.7, Math.PI / 2 ) ) );
	door.position.set( -W / 2, doorBottom - t * 0.5, D / 2 + fd / 2 );
	door.rotation.y = -14 * DEG;
	g.add( door );
	// warm interior light under the top
	const glow = new THREE.PointLight( '#ffcf9a', 900, 80, 2 );
	glow.position.set( 0, innerTop - 2, 6 );
	g.add( glow );
	return g;
}

function cherryBox( { open = 0, W = 26.5, D = 19, H = 8.2 } = {} ) {
	return humidor( {
		W, D, H, lidH: 2.4, t: 1.0, r: 0.25, wood: wood( 'cherry', 'oil', { tile: 50 } ), lining: wood( 'cedar', 'raw', { tile: 40 } ),
		lidLining: MAT.felt(), open, lip: 0.5, hinges: true,
	} );
}

function acaciaTravel( { open = 0, species = 'acacia', fin = 'oil' } = {} ) {
	return travelHumidor( { wood: wood( species, fin, { tile: 40 } ), lining: wood( 'cedar', 'raw', { tile: 30 } ), open } );
}

function shaving( rand, matl ) {
	const turns = 0.55 + rand() * 0.7;
	const r0 = 0.6 + rand() * 0.7;
	const w = 1.0 + rand() * 1.2;
	const twist = ( rand() - 0.5 ) * 0.8;
	const geo = new ParametricGeometry( ( u, v, target ) => {
		const a = u * turns * Math.PI * 2;
		const r = r0 * ( 1 + u * 0.9 );
		const off = ( v - 0.5 ) * w;
		target.set( Math.cos( a ) * r, off + u * twist * w, Math.sin( a ) * r + off * 0.15 );
	}, 40, 6 );
	const m = new THREE.Mesh( geo, matl );
	m.material.side = THREE.DoubleSide;
	m.castShadow = true;
	m.receiveShadow = true;
	return m;
}

function barClamp( length = 52 ) {
	const g = new THREE.Group();
	const steel = new THREE.MeshPhysicalMaterial( { color: '#9ea3a8', metalness: 1, roughness: 0.38 } );
	g.add( at( box( length, 2.0, 0.6, steel, { r: 0.08 } ), 0, 0, 0 ) );
	const jaw = new THREE.MeshPhysicalMaterial( { color: '#9c5034', roughness: 0.45, clearcoat: 0.4 } );
	g.add( at( box( 2.6, 7.5, 3.2, jaw, { r: 0.5 } ), -length / 2 + 4, -4.2, 0 ) );
	g.add( at( box( 2.6, 7.5, 3.2, jaw, { r: 0.5 } ), length / 2 - 9, -4.2, 0 ) );
	const handle = cyl( 1.3, 1.5, 9, wood( 'beech', 'oil', { tile: 20 } ), { seg: 32 } );
	handle.rotation.z = Math.PI / 2;
	handle.position.set( length / 2 - 2, -6.5, 0 );
	g.add( handle );
	g.add( at( cyl( 0.45, 0.45, 8, MAT.steel(), { seg: 16 } ), length / 2 - 7, -6.5, 0, 0, 0, Math.PI / 2 ) );
	return g;
}

function chisel() {
	const g = new THREE.Group();
	const h = cyl( 1.1, 1.4, 11, wood( 'beech', 'oil', { tile: 20 } ), { seg: 32 } );
	h.rotation.z = Math.PI / 2;
	h.position.x = -5.5;
	g.add( h );
	g.add( at( cyl( 1.15, 1.15, 0.8, MAT.brassBrushed(), { seg: 32 } ), -0.2, 0, 0, 0, 0, Math.PI / 2 ) );
	g.add( at( box( 11, 0.45, 2.4, MAT.steel(), { r: 0.05 } ), 5.6, -0.6, 0 ) );
	return g;
}

function mallet() {
	const g = new THREE.Group();
	const w = wood( 'beech', 'oil', { tile: 25 } );
	g.add( at( box( 13, 7, 6.5, w, { r: 0.8 } ), 0, 3.5, 0 ) );
	g.add( at( box( 3, 2.2, 26, w, { r: 0.7, grain: 'z' } ), 0, 1.6, 15 ) );
	return g;
}

function square() {
	const g = new THREE.Group();
	const s = MAT.steel();
	g.add( at( box( 24, 0.25, 2.4, s, { r: 0.05 } ), 0, 0.13, 0 ) );
	g.add( at( box( 2.4, 0.25, 14, s, { r: 0.05 } ), -10.8, 0.13, 7 ) );
	return g;
}

/* ------------------------------------------------------------------ gift box, care card, flat-lay pieces */

function careCard( w = 9, h = 6 ) {
	const c = document.createElement( 'canvas' );
	c.width = 900;
	c.height = 600;
	const x = c.getContext( '2d' );
	x.fillStyle = '#f3eee4';
	x.fillRect( 0, 0, 900, 600 );
	x.fillStyle = '#1f2b25';
	x.textAlign = 'center';
	x.font = '400 92px "Young Serif", serif';
	x.fillText( 'Cigarholic', 450, 250 );
	x.fillStyle = '#9c5034';
	x.fillRect( 400, 300, 100, 4 );
	x.fillStyle = '#5e574e';
	x.font = '600 40px "Hanken Grotesk", sans-serif';
	x.fillText( 'CARE AND SEASONING', 450, 390 );
	x.font = '400 34px "Hanken Grotesk", sans-serif';
	x.fillText( 'Handcrafted in Vietnam', 450, 450 );
	const t = new THREE.CanvasTexture( c );
	t.colorSpace = THREE.SRGBColorSpace;
	t.anisotropy = 16;
	const mats = [ MAT.paper(), MAT.paper(), new THREE.MeshPhysicalMaterial( { map: t, roughness: 0.85 } ), MAT.paper(), MAT.paper(), MAT.paper() ];
	const m = new THREE.Mesh( new THREE.BoxGeometry( w, 0.06, h ), mats );
	m.castShadow = true;
	m.receiveShadow = true;
	return m;
}

function giftSet() {
	const g = new THREE.Group();
	const kraft = MAT.kraft();
	const W = 36;
	const D = 22;
	const H = 8;
	const t = 0.35;
	g.add( at( box( W, t, D, kraft, { r: 0.05 } ), 0, t / 2, 0 ) );
	g.add( at( box( W, H, t, kraft, { r: 0.05 } ), 0, H / 2, D / 2 - t / 2 ) );
	g.add( at( box( W, H, t, kraft, { r: 0.05 } ), 0, H / 2, -D / 2 + t / 2 ) );
	g.add( at( box( t, H, D, kraft, { r: 0.05 } ), W / 2 - t / 2, H / 2, 0 ) );
	g.add( at( box( t, H, D, kraft, { r: 0.05 } ), -W / 2 + t / 2, H / 2, 0 ) );
	// dark felt insert with two wells
	const felt = MAT.felt( '#1f2b25' );
	g.add( at( box( W - 2 * t, 3.2, D - 2 * t, felt, { r: 0.1 } ), 0, 1.9, 0 ) );
	const th = travelHumidor( { wood: wood( 'walnut', 'matte', { tile: 40 } ), lining: null } );
	th.position.set( -4.5, 3.2, 0.5 );
	g.add( th );
	const cut = cutter();
	cut.position.set( 12.2, 3.55, 1.5 );
	cut.rotation.y = 90 * DEG;
	g.add( cut );
	// lid with a Forest ribbon, leaning on the back
	const lid = new THREE.Group();
	const LW = W + 0.8;
	const LD = D + 0.8;
	const LH = 3.2;
	lid.add( at( box( LW, t, LD, kraft, { r: 0.05 } ), 0, LH - t / 2, 0 ) );
	lid.add( at( box( LW, LH, t, kraft, { r: 0.05 } ), 0, LH / 2, LD / 2 - t / 2 ) );
	lid.add( at( box( LW, LH, t, kraft, { r: 0.05 } ), 0, LH / 2, -LD / 2 + t / 2 ) );
	lid.add( at( box( t, LH, LD, kraft, { r: 0.05 } ), LW / 2 - t / 2, LH / 2, 0 ) );
	lid.add( at( box( t, LH, LD, kraft, { r: 0.05 } ), -LW / 2 + t / 2, LH / 2, 0 ) );
	const rib = MAT.ribbon();
	lid.add( at( box( 3, 0.12, LD + 0.1, rib, { r: 0.02 } ), LW * 0.28, LH + 0.06, 0 ) );
	lid.add( at( box( 3, LH, 0.12, rib, { r: 0.02 } ), LW * 0.28, LH / 2, LD / 2 + 0.06 ) );
	lid.add( at( box( 3, LH, 0.12, rib, { r: 0.02 } ), LW * 0.28, LH / 2, -LD / 2 - 0.06 ) );
	const tag = box( 4.2, 0.05, 6.2, MAT.paper( '#efe7d6' ), { r: 0.02 } );
	tag.position.set( LW * 0.28 - 3.4, LH + 0.1, 4 );
	tag.rotation.y = 0.35;
	lid.add( tag );
	lid.add( at( cyl( 0.35, 0.35, 0.08, MAT.brass(), { seg: 20 } ), LW * 0.28 - 4.2, LH + 0.15, 1.6 ) );
	// stand the lid on its long edge behind the box, top facing the camera
	lid.rotation.x = 72 * DEG;
	lid.position.set( 0, LD / 2 * Math.sin( 72 * DEG ) + 0.1, -D / 2 - 6 );
	g.add( lid );
	const card = careCard();
	card.position.set( 15, 0.04, D / 2 + 6 );
	card.rotation.y = -0.18;
	g.add( card );
	return g;
}

function cedarStrip( l = 22, h = 5, t = 0.5 ) {
	return box( l, t, h, wood( 'cedar', 'raw', { tile: 40 } ), { r: 0.08 } );
}

/* ------------------------------------------------------------------ lighting presets */

function bright( st, { color = SAND, target = [ 0, 5, 0 ], extent = 70, key = [ -90, 150, 90 ], exposure = 1, env = 0.55 } = {} ) {
	st.add( sweep( color, { rough: 0.95 } ) );
	st.lighting( {
		key: { pos: key, color: '#fff8f0', intensity: 2.4, soft: 45 },
		sky: { intensity: 1.1, cone: 60 },
		extra: [ { pos: [ 120, 60, -90 ], color: '#fff7ee', intensity: 0.8, shadow: true } ],
		env, extent, target, exposure,
	} );
}

const PRODUCT = { type: 'image/jpeg', post: { vignette: 0.08, grain: 2.4, label: 'Sample image' } };
const CARD = { post: { vignette: 0.1, grain: 2.6 } };

/* ------------------------------------------------------------------ shots */

export const SHOTS = {
	hero: {
		size: [ 1440, 1080 ],
		build( st ) {
			const bg = sweep( FOREST, { rough: 0.85 } );
			bg.position.y = -3;
			st.add( bg );
			const top = box( 320, 3, 70, wood( 'walnut', 'matte', { tile: 330, tint: '#8a8378' } ), { r: 0.6 } );
			st.add( at( top, 0, -1.5, 8 ) );
			const h = walnutDesktop( { open: 102 } );
			h.rotation.y = -24 * DEG;
			st.add( h );
			const jar = humidifierJar( 2.6, 3.4 );
			jar.position.set( 24, 0, 14 );
			st.add( jar );
			st.lighting( {
				key: { pos: [ -150, 120, 70 ], color: '#ffe4c4', intensity: 4.4, soft: 35 },
				sky: { intensity: 0.9, cone: 55 },
				extra: [ { pos: [ 130, 70, -130 ], color: '#ffe2bd', intensity: 1.4, shadow: true } ],
				room: { ground: '#101512', panels: [ { dir: [ -150, 120, 70 ], w: 70, h: 50, color: '#ffe4c4', power: 3 }, { dir: [ -100, -8, 30 ], w: 60, h: 14, color: '#ffe9d2', power: 1.6 }, { dir: [ 130, 70, -130 ], w: 25, h: 70, color: '#ffe2bd', power: 2 }, { dir: [ 0, 100, 0 ], w: 60, h: 60, color: '#ffffff', power: 0.6 } ] },
				env: 1, target: [ 0, 6, 0 ], exposure: 1.05,
			} );
			st.view( [ 58, 46, 96 ], [ 1, 9, 0 ], 26, 0.45 );
		},
		post: { vignette: 0.3 },
	},

	workshop: {
		size: [ 1400, 1050 ],
		build( st ) {
			const rand = rng( 42 );
			const bench = box( 200, 7, 80, wood( 'beech', 'oil', { tile: 210, tint: '#d9c6ad' } ), { r: 0.6 } );
			st.add( at( bench, 0, -3.5, 10 ) );
			// dark stained boards behind the bench, well out of focus
			const boards = wood( 'pine', 'matte', { tile: 240, tint: '#6a5848' } );
			for ( let i = 0; i < 24; i++ ) {
				st.add( at( box( 13.6, 220, 2, boards, { r: 0.4, grain: 'y' } ), -165 + i * 14, 100, -45 ) );
			}
			st.add( at( box( 600, 2, 400, MAT.paper( '#3d342b' ), { r: 0 } ), 0, -95, 0 ) );
			// shelf with offcuts
			st.add( at( box( 130, 2.5, 20, wood( 'beech', 'oil', { tile: 140 } ), { r: 0.3 } ), -20, 48, -34 ) );
			[ 'walnutRaw', 'cedar', 'mahogany', 'oak', 'cherry' ].forEach( ( sp, i ) => {
				st.add( at( box( 30, 2.6, 11, wood( sp, 'raw', { tile: 60 } ), { r: 0.2 } ), -70 + i * 24, 50.6 + ( i % 2 ) * 2.6, -34 + ( i % 2 ) * 1.5, 0, ( rand() - 0.5 ) * 0.25, 0 ) );
			} );
			// boards leaning on the wall
			[ [ 'walnutRaw', -95 ], [ 'cedar', -82 ], [ 'oak', 80 ] ].forEach( ( [ sp, x ], i ) => {
				st.add( at( box( 16, 110, 2.2, wood( sp, 'raw', { tile: 120 } ), { r: 0.2, grain: 'y' } ), x, 52, -40, -0.12, 0.1 * ( i - 1 ), 0.05 ) );
			} );
			// carcass in clamps
			const walnut = wood( 'walnutRaw', 'raw' );
			const carcass = humidor( { W: 32, D: 23, H: 12.5, lidH: 4.4, wood: walnut, lining: null, noLid: true, hinges: false, r: 0.12 } );
			const piece = new THREE.Group();
			piece.add( carcass );
			const bodyH = 12.5 - 4.4;
			[ -6, 6 ].forEach( ( z ) => {
				const c = barClamp( 47.6 );
				c.position.set( 2.5, bodyH + 1.1, z );
				piece.add( c );
			} );
			piece.rotation.y = 26 * DEG;
			piece.position.set( 2, 0, 6 );
			st.add( piece );
			const lidPanel = box( 32, 1.3, 23, walnut, { r: 0.15 } );
			st.add( at( lidPanel, -40, 0.65, 16, 0, -0.18, 0 ) );
			const pencil = cyl( 0.38, 0.38, 16, wood( 'cedar', 'oil', { tile: 20 } ), { seg: 6 } );
			pencil.rotation.set( 0, 0.7, Math.PI / 2 );
			pencil.position.set( -40, 1.7, 18 );
			st.add( pencil );
			st.add( at( chisel(), 30, 1.4, 34, 0, -0.35, 0 ) );
			st.add( at( mallet(), 52, 0, -6, 0, 0.75, 0 ) );
			st.add( at( square(), -30, 0, 38, 0, 0.12, 0 ) );
			const shavingMat = wood( 'walnutRaw', 'raw', { tile: 20, tint: '#f0dcc4' } );
			for ( let i = 0; i < 20; i++ ) {
				const sh = shaving( rand, shavingMat );
				const a = rand() * Math.PI * 2;
				const rr = 22 + rand() * 26;
				const x = 6 + Math.cos( a ) * rr * 1.3;
				const z = 10 + Math.sin( a ) * rr * 0.6;
				sh.position.set( x, 0.9 + rand() * 0.6, z );
				sh.rotation.set( Math.PI / 2 + ( rand() - 0.5 ) * 0.5, rand() * Math.PI * 2, ( rand() - 0.5 ) * 0.5 );
				st.add( sh );
			}
			st.lighting( {
				key: { pos: [ -220, 150, 70 ], color: '#ffdfb8', intensity: 3.8, soft: 55 },
				sky: { intensity: 0.55, cone: 50, bias: [ -0.4, 0, 0.2 ] },
				extra: [ { pos: [ 160, 80, 140 ], color: '#d8e2ea', intensity: 0.25 } ],
				env: 0.22, extent: 120, target: [ 0, 5, 5 ], exposure: 1.0,
				ao: { radius: 5 },
			} );
			st.view( [ 30, 56, 94 ], [ 3, 5, 6 ], 31, 1.3 );
		},
		post: { vignette: 0.32 },
	},

	'category-desktop-humidors': {
		size: [ 1200, 800 ],
		build( st ) {
			const a = walnutDesktop( { open: 98 } );
			a.rotation.y = -18 * DEG;
			a.position.set( 14, 0, 6 );
			const b = mahoganyDesktop();
			b.rotation.y = 22 * DEG;
			b.position.set( -26, 0, -10 );
			st.add( a, b );
			bright( st, { target: [ -4, 8, 0 ] } );
			st.view( [ 20, 52, 118 ], [ -3, 9, 0 ], 28, 0.3 );
		},
		...CARD,
	},

	'category-cabinet-humidors': {
		size: [ 1200, 800 ],
		build( st ) {
			const c = oakCabinet();
			c.rotation.y = -16 * DEG;
			st.add( c );
			bright( st, { target: [ 0, 50, 0 ], extent: 110, key: [ -120, 200, 140 ] } );
			st.view( [ 60, 68, 200 ], [ 0, 52, 0 ], 31, 0.3 );
		},
		...CARD,
	},

	'category-travel-humidors': {
		size: [ 1200, 800 ],
		build( st ) {
			const a = acaciaTravel( { open: 100 } );
			a.rotation.y = -12 * DEG;
			a.position.set( 6, 0, 6 );
			const b = acaciaTravel( { species: 'walnut', fin: 'matte' } );
			b.rotation.y = 18 * DEG;
			b.position.set( -14, 0, -10 );
			const c = acaciaTravel( { species: 'cherry', fin: 'oil' } );
			c.rotation.y = 10 * DEG;
			c.position.set( -15, 6.5, -10.5 );
			st.add( a, b, c );
			bright( st, { target: [ -3, 4, 0 ], extent: 50 } );
			st.view( [ 12, 34, 66 ], [ -3, 4, -1 ], 30, 0.25 );
		},
		...CARD,
	},

	'category-cigar-boxes': {
		size: [ 1200, 800 ],
		build( st ) {
			const a = cherryBox( { open: 70 } );
			a.rotation.y = -14 * DEG;
			a.position.set( 10, 0, 6 );
			const b = humidor( { W: 24, D: 17, H: 7.4, lidH: 2.2, t: 1.0, r: 0.25, wood: wood( 'walnut', 'oil', { tile: 50 } ), lining: null, hinges: true } );
			b.rotation.y = 20 * DEG;
			b.position.set( -20, 0, -8 );
			const c = humidor( { W: 22, D: 15, H: 6.6, lidH: 2.0, t: 1.0, r: 0.25, wood: wood( 'acacia', 'oil', { tile: 50 } ), lining: null, hinges: true } );
			c.rotation.y = 12 * DEG;
			c.position.set( -20, 7.5, -8.5 );
			st.add( a, b, c );
			bright( st, { target: [ -4, 5, 0 ], extent: 55 } );
			st.view( [ 16, 44, 82 ], [ -4, 6, -1 ], 30, 0.25 );
		},
		...CARD,
	},

	'category-humidor-accessories': {
		size: [ 1200, 800 ],
		build( st ) {
			const an = analogHygrometer( 7.2 );
			an.rotation.x = -Math.PI / 2;
			an.position.set( -11, 0, -1 );
			const dg = digitalHygrometer( 6.4 );
			dg.rotation.x = -Math.PI / 2;
			dg.position.set( 1.5, 0, 4 );
			const jar = humidifierJar( 2.6, 3.4 );
			jar.position.set( 12.5, 0, -3 );
			const bar = humidifierBar( 14, 3.2, 1.6 );
			bar.position.set( 0, 0, -8 );
			bar.rotation.y = 0.1;
			const d1 = cedarStrip( 22, 4.6 );
			d1.position.set( -1, 0.25, 11.5 );
			d1.rotation.y = -0.06;
			const d2 = cedarStrip( 22, 4.6 );
			d2.position.set( 1.5, 0.75, 13 );
			d2.rotation.y = 0.05;
			st.add( an, dg, jar, bar, d1, d2 );
			bright( st, { target: [ 0, 0, 2 ], extent: 40, key: [ -80, 160, 60 ] } );
			st.view( [ 0, 52, 32 ], [ 0.5, 0, 2.5 ], 27, 0.15 );
		},
		...CARD,
	},

	'category-gift-sets': {
		size: [ 1200, 800 ],
		build( st ) {
			const g = giftSet();
			g.rotation.y = -10 * DEG;
			st.add( g );
			bright( st, { target: [ 0, 5, 0 ], extent: 55 } );
			st.view( [ 14, 50, 74 ], [ 1, 4, 0 ], 32, 0.25 );
		},
		...CARD,
	},

	'product-sample-walnut-desktop-humidor-50': {
		size: [ 1200, 1200 ],
		build( st ) {
			const a = walnutDesktop( { open: 100, finishName: 'matte' } );
			a.rotation.y = -24 * DEG;
			st.add( a );
			bright( st, { color: ASH, target: [ 0, 9, 0 ] } );
			st.view( [ 44, 50, 78 ], [ 0, 10, -2 ], 30, 0.2 );
		},
		...PRODUCT,
	},

	'product-sample-mahogany-desktop-humidor-100': {
		size: [ 1200, 1200 ],
		build( st ) {
			const a = mahoganyDesktop();
			a.rotation.y = -26 * DEG;
			st.add( a );
			bright( st, { color: ASH, target: [ 0, 8, 0 ] } );
			st.view( [ 42, 42, 82 ], [ 0, 8, 0 ], 30, 0.2 );
		},
		...PRODUCT,
	},

	'product-sample-oak-cabinet-humidor-300': {
		size: [ 1200, 1200 ],
		build( st ) {
			const c = oakCabinet();
			c.rotation.y = -20 * DEG;
			st.add( c );
			bright( st, { color: ASH, target: [ 0, 50, 0 ], extent: 110, key: [ -120, 200, 140 ] } );
			st.view( [ 70, 80, 210 ], [ 0, 51, 0 ], 34, 0.2 );
		},
		...PRODUCT,
	},

	'product-sample-acacia-travel-humidor-10': {
		size: [ 1200, 1200 ],
		build( st ) {
			const a = acaciaTravel( { open: 100 } );
			a.rotation.y = -20 * DEG;
			st.add( a );
			bright( st, { color: ASH, target: [ 0, 5, 0 ], extent: 40 } );
			st.view( [ 20, 34, 52 ], [ 0, 5, -1 ], 30, 0.15 );
		},
		...PRODUCT,
	},

	'product-sample-cherry-cigar-box-25': {
		size: [ 1200, 1200 ],
		build( st ) {
			const a = cherryBox( { open: 72 } );
			a.rotation.y = -22 * DEG;
			st.add( a );
			bright( st, { color: ASH, target: [ 0, 6, 0 ], extent: 45 } );
			st.view( [ 28, 40, 62 ], [ 0, 6, -2 ], 30, 0.15 );
		},
		...PRODUCT,
	},

	'product-sample-digital-hygrometer': {
		size: [ 1200, 1200 ],
		build( st ) {
			const dg = digitalHygrometer( 6.4 );
			dg.rotation.set( -62 * DEG, 0, 0 );
			dg.position.set( 0, 2.9, 0 );
			const plate = cyl( 2.4, 2.4, 0.25, new THREE.MeshPhysicalMaterial( { color: '#b9bcc0', metalness: 1, roughness: 0.45 } ), { seg: 64 } );
			plate.position.set( 6.5, 0.125, 2 );
			st.add( dg, plate );
			// a short cedar block props the hygrometer at an angle
			st.add( at( box( 6, 2.2, 2, wood( 'cedar', 'raw', { tile: 20 } ), { r: 0.15 } ), 0, 1.1, -2.2 ) );
			bright( st, { color: ASH, target: [ 1, 2, 0 ], extent: 20 } );
			st.view( [ 6, 15, 24 ], [ 1.5, 2.2, 0 ], 30, 0.08 );
		},
		...PRODUCT,
	},

	'product-sample-groomsmen-gift-set': {
		size: [ 1200, 1200 ],
		build( st ) {
			const g = giftSet();
			g.rotation.y = -12 * DEG;
			st.add( g );
			bright( st, { color: ASH, target: [ 0, 5, 0 ], extent: 55 } );
			st.view( [ 15, 64, 80 ], [ 1, 4, 0 ], 34, 0.2 );
		},
		...PRODUCT,
	},
};

window.renderShot = async ( name, frames = 32 ) => {
	await document.fonts.load( '600 40px "Hanken Grotesk"' );
	await document.fonts.load( '400 40px "Young Serif"' );
	resetSeeds( 7 );
	const shot = SHOTS[ name ];
	const [ w, h ] = shot.size;
	const st = new Studio( w, h );
	shot.build( st );
	const canvas = await st.render( frames, 3 );
	finish( canvas, shot.post || {} );
	st.renderer.dispose();
	const type = shot.type || 'image/webp';
	return canvas.toDataURL( type, type === 'image/jpeg' ? 0.86 : 0.84 );
};
window.shotNames = () => Object.keys( SHOTS );
window.studioReady = true;

