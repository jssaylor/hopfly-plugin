/**
 * Adds a focal point control to the Image block. Used by the slideshow and anywhere an image is cropped to fill.
 */
( function ( wp ) {
	var el = wp.element.createElement;
	var __ = wp.i18n.__;

	wp.hooks.addFilter(
		'editor.BlockEdit',
		'hopfly/image-focal',
		wp.compose.createHigherOrderComponent( function ( BlockEdit ) {
			return function ( props ) {
				if ( props.name !== 'core/image' || ! props.attributes.url ) {
					return el( BlockEdit, props );
				}
				var focal = props.attributes.hopflyFocal || { x: 0.5, y: 0.5 };
				return el(
					wp.element.Fragment,
					null,
					el( BlockEdit, props ),
					el(
						wp.blockEditor.InspectorControls,
						null,
						el(
							wp.components.PanelBody,
							{ title: __( 'Crop focus', 'hopfly' ), initialOpen: false },
							el( wp.components.FocalPointPicker, {
								label: __( 'Keep this part in view when the photo is cropped', 'hopfly' ),
								url: props.attributes.url,
								value: focal,
								onChange: function ( v ) {
									props.setAttributes( { hopflyFocal: v } );
								},
								__nextHasNoMarginBottom: true,
							} )
						)
					)
				);
			};
		}, 'withHopflyFocal' )
	);
} )( window.wp );
