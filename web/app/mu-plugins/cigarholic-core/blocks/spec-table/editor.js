/**
 * Editor registration for cigarholic/spec-table (plain JavaScript, no build step): a server-side
 * rendered preview of the current product's specification table. In the Site Editor's product
 * template there is no product, so a placeholder explains what renders on the storefront. (CAT-04)
 */
( function ( wp ) {
	const { registerBlockType } = wp.blocks;
	const { createElement: el } = wp.element;
	const { useBlockProps } = wp.blockEditor;
	const { Placeholder } = wp.components;
	const { __ } = wp.i18n;
	const ServerSideRender = wp.serverSideRender;

	function EmptyPlaceholder() {
		return el(
			Placeholder,
			{
				icon: 'editor-table',
				label: __( 'Specification table', 'cigarholic' ),
				instructions: __(
					'On the product page this block lists the product’s specifications (capacity, cigar fit, exterior and interior dimensions, weight and attributes) in inches and centimetres, pounds and kilograms. It renders nothing for a product without data.',
					'cigarholic'
				),
			}
		);
	}

	registerBlockType( 'cigarholic/spec-table', {
		edit( props ) {
			const postId = props.context && props.context.postId ? props.context.postId : 0;
			return el(
				'div',
				useBlockProps(),
				el( ServerSideRender, {
					block: 'cigarholic/spec-table',
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
