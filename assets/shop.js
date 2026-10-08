/**
 * Quantity stepper for the product page. Without this script the number input still works.
 */
( function () {
	function init( scope ) {
		scope.querySelectorAll( '.quantity' ).forEach( function ( wrap ) {
			var input = wrap.querySelector( 'input.qty' );
			var buttons = wrap.querySelectorAll( '[data-hopfly-qty]' );
			if ( ! input || ! buttons.length || wrap.dataset.hopflyReady ) {
				return;
			}
			wrap.dataset.hopflyReady = '1';
			wrap.classList.add( 'hopfly-qty' );
			buttons.forEach( function ( b ) {
				b.hidden = false;
				b.addEventListener( 'click', function () {
					var step = parseFloat( input.step ) || 1;
					var min = input.min === '' ? 0 : parseFloat( input.min );
					var max = input.max === '' ? Infinity : parseFloat( input.max );
					var val = ( parseFloat( input.value ) || 0 ) + step * parseInt( b.dataset.hopflyQty, 10 );
					input.value = Math.max( min, Math.min( max, val ) );
					input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
				} );
			} );
		} );
	}
	function boot() {
		init( document );
		// Variable products re-render the quantity wrapper when a variation is chosen.
		var form = document.querySelector( 'form.variations_form' );
		if ( form && window.jQuery ) {
			window.jQuery( form ).on( 'show_variation reset_data', function () {
				init( document );
			} );
		}
	}
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
} )();
