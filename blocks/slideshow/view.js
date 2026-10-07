/**
 * HopFly slideshow: swipe (native scroll-snap), arrow buttons, keyboard arrows, position indicator.
 * No autoplay. Respects reduced motion.
 */
( function () {
	function init( root ) {
		var track = root.querySelector( '.hopfly-slideshow__track' );
		var prev = root.querySelector( '.hopfly-slideshow__btn--prev' );
		var next = root.querySelector( '.hopfly-slideshow__btn--next' );
		var counter = root.querySelector( '.hopfly-slideshow__counter' );
		var fill = root.querySelector( '.hopfly-slideshow__fill' );
		var slides = root.querySelectorAll( '.hopfly-slideshow__slide' );
		if ( ! track || slides.length < 2 ) {
			return;
		}
		var total = slides.length;
		var reduce = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
		var pad = function ( n ) {
			return ( n < 10 ? '0' : '' ) + n;
		};
		function index() {
			return Math.max( 0, Math.min( total - 1, Math.round( track.scrollLeft / track.clientWidth ) ) );
		}
		function update() {
			var i = index();
			if ( counter ) {
				counter.textContent = pad( i + 1 ) + ' / ' + pad( total );
			}
			if ( fill ) {
				fill.style.width = ( ( i + 1 ) / total ) * 100 + '%';
			}
			if ( prev ) {
				prev.disabled = i === 0;
			}
			if ( next ) {
				next.disabled = i === total - 1;
			}
		}
		function go( i ) {
			i = Math.max( 0, Math.min( total - 1, i ) );
			track.scrollTo( { left: i * track.clientWidth, behavior: reduce ? 'auto' : 'smooth' } );
		}
		track.addEventListener( 'scroll', update, { passive: true } );
		if ( prev ) {
			prev.addEventListener( 'click', function () {
				go( index() - 1 );
			} );
		}
		if ( next ) {
			next.addEventListener( 'click', function () {
				go( index() + 1 );
			} );
		}
		track.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'ArrowLeft' ) {
				e.preventDefault();
				go( index() - 1 );
			} else if ( e.key === 'ArrowRight' ) {
				e.preventDefault();
				go( index() + 1 );
			}
		} );
		window.addEventListener( 'resize', function () {
			track.scrollLeft = index() * track.clientWidth;
		} );
		update();
	}
	function boot() {
		document.querySelectorAll( '[data-hopfly-slideshow]' ).forEach( init );
	}
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
} )();
