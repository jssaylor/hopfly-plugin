( function ( wp ) {
	const { registerBlockType } = wp.blocks;
	const { useBlockProps, InspectorControls } = wp.blockEditor;
	const { PanelBody, SelectControl } = wp.components;
	const el = wp.element.createElement;
	const { __ } = wp.i18n;
	const labels = { badge: 'Pre-order badge', notice: 'Pre-order window card', 'final-sale': 'Final sale line', 'size-chart': 'Size chart link' };

	registerBlockType( 'hopfly/product-preorder', {
		edit: function ( props ) {
			const { attributes, setAttributes } = props;
			return el(
				wp.element.Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'Pre-order', 'hopfly' ) },
						el( SelectControl, {
							label: __( 'Show', 'hopfly' ),
							value: attributes.part,
							options: Object.keys( labels ).map( function ( k ) { return { label: labels[ k ], value: k }; } ),
							onChange: function ( v ) { setAttributes( { part: v } ); },
						} )
					)
				),
				el( 'div', useBlockProps( { className: 'hopfly-product-preorder-placeholder' } ), ( labels[ attributes.part ] || 'Pre-order' ) + ' (shows on pre-order products only; set dates in the product\'s General tab)' )
			);
		},
		save: function () {
			return null;
		},
	} );
} )( window.wp );
