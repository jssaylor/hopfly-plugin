( function ( wp ) {
	const { registerBlockType } = wp.blocks;
	const { useBlockProps, useInnerBlocksProps, InspectorControls, InnerBlocks } = wp.blockEditor;
	const { PanelBody, TextControl, ToggleControl } = wp.components;
	const el = wp.element.createElement;
	const { __ } = wp.i18n;

	registerBlockType( 'hopfly/slideshow', {
		edit: function ( props ) {
			const { attributes, setAttributes } = props;
			const blockProps = useBlockProps( { className: 'hopfly-slideshow-editor' } );
			const innerProps = useInnerBlocksProps( blockProps, {
				allowedBlocks: [ 'core/image' ],
				template: [ [ 'core/image' ] ],
				orientation: 'horizontal',
				templateLock: false,
			} );
			return el(
				wp.element.Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'Slideshow', 'hopfly' ) },
						el( TextControl, { label: __( 'Accessible name', 'hopfly' ), help: __( 'Read aloud by screen readers, for example "Saturday ride photos".', 'hopfly' ), value: attributes.label, onChange: ( v ) => setAttributes( { label: v } ) } ),
						el( ToggleControl, { label: __( 'Dark background styling', 'hopfly' ), checked: attributes.dark, onChange: ( v ) => setAttributes( { dark: v } ) } )
					)
				),
				el( 'div', innerProps )
			);
		},
		save: function () {
			return el( InnerBlocks.Content );
		},
	} );
} )( window.wp );
