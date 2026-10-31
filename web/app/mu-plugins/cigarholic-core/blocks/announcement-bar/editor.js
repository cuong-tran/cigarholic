/**
 * Editor registration for cigarholic/announcement-bar: server-side preview; the content is edited
 * under WooCommerce > Announcement bar. (NAV-02)
 */
( function ( wp ) {
	const { registerBlockType } = wp.blocks;
	const { createElement: el } = wp.element;
	const { useBlockProps } = wp.blockEditor;
	const { Notice } = wp.components;
	const { __ } = wp.i18n;
	const ServerSideRender = wp.serverSideRender;

	registerBlockType( 'cigarholic/announcement-bar', {
		edit( props ) {
			return el(
				'div',
				useBlockProps(),
				el( Notice, { status: 'info', isDismissible: false }, __( 'Announcement bar: the text, link and dates are set under WooCommerce > Announcement bar. Nothing renders while it is off.', 'cigarholic' ) ),
				el( ServerSideRender, { block: 'cigarholic/announcement-bar', attributes: props.attributes } )
			);
		},
		save() {
			return null;
		},
	} );
} )( window.wp );
