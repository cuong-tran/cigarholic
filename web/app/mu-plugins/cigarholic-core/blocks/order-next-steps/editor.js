/**
 * Editor registration for cigarholic/order-next-steps (plain JavaScript, no build step): a
 * server-side rendered preview of the general steps. On the order-received page the card is written
 * from the order itself. (CHK-06)
 */
( function ( wp ) {
	const { registerBlockType } = wp.blocks;
	const { createElement: el } = wp.element;
	const { useBlockProps } = wp.blockEditor;
	const { Notice } = wp.components;
	const { __ } = wp.i18n;
	const ServerSideRender = wp.serverSideRender;

	registerBlockType( 'cigarholic/order-next-steps', {
		edit( props ) {
			return el(
				'div',
				useBlockProps(),
				el( Notice, { status: 'info', isDismissible: false }, __( 'Order next steps: on the order-received page this card follows the order (ship time from WooCommerce > Settings > Shipping, backorders, account link, order number, seasoning tip for humidors). Unpaid orders show nothing.', 'cigarholic' ) ),
				el( ServerSideRender, { block: 'cigarholic/order-next-steps', attributes: props.attributes } )
			);
		},
		save() {
			return null;
		},
	} );
} )( window.wp );
