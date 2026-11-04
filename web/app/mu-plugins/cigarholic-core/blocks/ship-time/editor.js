/**
 * Editor registration for cigarholic/ship-time (plain JavaScript, no build step): a server-side
 * rendered preview of the ship-time promise. The days are set under WooCommerce > Settings >
 * Shipping > Shipping settings, "Ship time". (SHP-03)
 */
( function ( wp ) {
	const { registerBlockType } = wp.blocks;
	const { createElement: el } = wp.element;
	const { useBlockProps } = wp.blockEditor;
	const { Placeholder } = wp.components;
	const { __ } = wp.i18n;
	const ServerSideRender = wp.serverSideRender;

	function EmptyPlaceholder() {
		return el( Placeholder, {
			icon: 'clock',
			label: __( 'Ship time', 'cigarholic' ),
			instructions: __(
				'Shows the ship-time promise set under WooCommerce > Settings > Shipping. Nothing renders for a product on backorder or out of stock.',
				'cigarholic'
			),
		} );
	}

	registerBlockType( 'cigarholic/ship-time', {
		edit( props ) {
			const postId = props.context && props.context.postId ? props.context.postId : 0;
			return el(
				'div',
				useBlockProps(),
				el( ServerSideRender, {
					block: 'cigarholic/ship-time',
					attributes: props.attributes,
					urlQueryArgs: postId ? { post_id: postId } : {},
					EmptyResponsePlaceholder: EmptyPlaceholder,
				} )
			);
		},
		save() {
			return null;
		},
	} );
} )( window.wp );
