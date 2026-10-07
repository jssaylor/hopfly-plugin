( function ( wp ) {
	const { registerBlockType } = wp.blocks;
	const { useBlockProps } = wp.blockEditor;
	const el = wp.element.createElement;

	registerBlockType( 'hopfly/event-details', {
		edit: function () {
			return el(
				'div',
				useBlockProps( { className: 'hopfly-event-details-placeholder' } ),
				'Event details: date, time, venue with map link, status and registration button come from the event fields.'
			);
		},
		save: function () {
			return null;
		},
	} );
} )( window.wp );
