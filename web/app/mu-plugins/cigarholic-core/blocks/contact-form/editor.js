/**
 * Editor registration for cigarholic/contact-form (plain JavaScript, no build step). (CNT-03)
 */
( function ( wp ) {
	const { registerBlockType } = wp.blocks;
	const { createElement: el } = wp.element;
	const { InspectorControls, useBlockProps } = wp.blockEditor;
	const { PanelBody, TextControl, ToggleControl } = wp.components;
	const { __ } = wp.i18n;
	const ServerSideRender = wp.serverSideRender;

	registerBlockType( 'cigarholic/contact-form', {
		edit( props ) {
			const { attributes, setAttributes } = props;
			return el(
				'div',
				useBlockProps(),
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'Contact form', 'cigarholic' ) },
						el( TextControl, {
							label: __( 'Heading', 'cigarholic' ),
							value: attributes.heading,
							onChange: ( heading ) => setAttributes( { heading } ),
						} ),
						el( TextControl, {
							label: __( 'Button label', 'cigarholic' ),
							value: attributes.buttonLabel,
							onChange: ( buttonLabel ) => setAttributes( { buttonLabel } ),
						} ),
						el( ToggleControl, {
							label: __( 'Ask for an order number', 'cigarholic' ),
							checked: attributes.showOrderNumber,
							onChange: ( showOrderNumber ) => setAttributes( { showOrderNumber } ),
						} )
					)
				),
				el( ServerSideRender, { block: 'cigarholic/contact-form', attributes } )
			);
		},
		save() {
			return null;
		},
	} );
} )( window.wp );
