/**
 * Editor registration for cigarholic/signup-form (plain JavaScript, no build step): server-side
 * rendered preview plus inspector controls for the heading, button label and source. (CNT-04)
 */
( function ( wp ) {
	const { registerBlockType } = wp.blocks;
	const { createElement: el } = wp.element;
	const { InspectorControls, useBlockProps } = wp.blockEditor;
	const { PanelBody, TextControl } = wp.components;
	const { __ } = wp.i18n;
	const ServerSideRender = wp.serverSideRender;

	registerBlockType( 'cigarholic/signup-form', {
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
						{ title: __( 'Signup form', 'cigarholic' ) },
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
						el( TextControl, {
							label: __( 'Source (reporting key)', 'cigarholic' ),
							help: __( 'Lowercase letters, digits and hyphens, for example coming-soon or footer.', 'cigarholic' ),
							value: attributes.source,
							onChange: ( source ) => setAttributes( { source } ),
						} )
					)
				),
				el( ServerSideRender, { block: 'cigarholic/signup-form', attributes } )
			);
		},
		save() {
			return null;
		},
	} );
} )( window.wp );
