( function ( wp ) {
	const { registerBlockType } = wp.blocks;
	const { useBlockProps, InspectorControls } = wp.blockEditor;
	const { PanelBody, RangeControl, ToggleControl, TextControl } = wp.components;
	const ServerSideRender = wp.serverSideRender;
	const el = wp.element.createElement;
	const { __ } = wp.i18n;

	registerBlockType( 'hopfly/upcoming-events', {
		edit: function ( props ) {
			const { attributes, setAttributes } = props;
			return el(
				'div',
				useBlockProps(),
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'Events list', 'hopfly' ) },
						el( RangeControl, { label: __( 'How many (0 = all)', 'hopfly' ), min: 0, max: 20, value: attributes.limit, onChange: ( v ) => setAttributes( { limit: v } ) } ),
						el( TextControl, { label: __( 'Only this event type (slug)', 'hopfly' ), help: __( 'For example: group-ride, charity-ride, fundraiser. Leave empty for all.', 'hopfly' ), value: attributes.type, onChange: ( v ) => setAttributes( { type: v } ) } ),
						el( ToggleControl, { label: __( 'Group by type', 'hopfly' ), checked: attributes.grouped, onChange: ( v ) => setAttributes( { grouped: v } ) } ),
						el( ToggleControl, { label: __( 'Show photos', 'hopfly' ), checked: attributes.photos, onChange: ( v ) => setAttributes( { photos: v } ) } ),
						el( ToggleControl, { label: __( 'On a gray background', 'hopfly' ), checked: attributes.onGray, onChange: ( v ) => setAttributes( { onGray: v } ) } )
					)
				),
				el( ServerSideRender, { block: 'hopfly/upcoming-events', attributes: attributes } )
			);
		},
		save: function () {
			return null;
		},
	} );
} )( window.wp );
