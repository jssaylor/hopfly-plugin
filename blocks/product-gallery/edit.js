( function ( wp ) {
	const { registerBlockType } = wp.blocks;
	const { useBlockProps } = wp.blockEditor;
	const el = wp.element.createElement;

	registerBlockType( 'hopfly/product-gallery', {
		edit: function () {
			return el( 'div', useBlockProps( { className: 'hopfly-product-preorder-placeholder' } ), 'Product photos in the HopFly slideshow (from the product image and gallery).' );
		},
		save: function () {
			return null;
		},
	} );
} )( window.wp );
