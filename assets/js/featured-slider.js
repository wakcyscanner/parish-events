/**
 * Featured-events slider: arrows, dots, and button state on top of the
 * native scroll-snap strip. Without this script the strip still scrolls.
 */
( function () {
	'use strict';

	function init( slider ) {
		var track = slider.querySelector( '.pe-slider-track' );
		var prev  = slider.querySelector( '.pe-slider-prev' );
		var next  = slider.querySelector( '.pe-slider-next' );
		var dots  = slider.querySelector( '.pe-slider-dots' );

		if ( ! track || ! track.children.length ) {
			return;
		}

		function pageCount() {
			return Math.max( 1, Math.round( track.scrollWidth / track.clientWidth ) );
		}

		function update() {
			var max = track.scrollWidth - track.clientWidth;
			if ( prev ) {
				prev.disabled = track.scrollLeft <= 4;
			}
			if ( next ) {
				next.disabled = track.scrollLeft >= max - 4;
			}
			if ( dots && dots.children.length ) {
				var idx = Math.round( track.scrollLeft / track.clientWidth );
				Array.prototype.forEach.call( dots.children, function ( dot, i ) {
					dot.classList.toggle( 'pe-dot-active', i === idx );
				} );
			}
		}

		function buildDots() {
			if ( ! dots ) {
				return;
			}
			dots.innerHTML = '';
			var pages = pageCount();
			if ( pages < 2 ) {
				return;
			}
			for ( var i = 0; i < pages; i++ ) {
				var dot = document.createElement( 'button' );
				dot.type = 'button';
				dot.className = 'pe-dot';
				dot.setAttribute( 'aria-label', 'Slide ' + ( i + 1 ) + ' of ' + pages );
				( function ( page ) {
					dot.addEventListener( 'click', function () {
						track.scrollTo( { left: page * track.clientWidth, behavior: 'smooth' } );
					} );
				} )( i );
				dots.appendChild( dot );
			}
		}

		if ( prev ) {
			prev.addEventListener( 'click', function () {
				track.scrollBy( { left: -track.clientWidth, behavior: 'smooth' } );
			} );
		}
		if ( next ) {
			next.addEventListener( 'click', function () {
				track.scrollBy( { left: track.clientWidth, behavior: 'smooth' } );
			} );
		}
		track.addEventListener( 'scroll', function () {
			window.requestAnimationFrame( update );
		} );
		window.addEventListener( 'resize', function () {
			buildDots();
			update();
		} );

		slider.classList.add( 'pe-slider-ready' );
		buildDots();
		update();

		// With everything visible there is nothing to slide.
		if ( track.scrollWidth <= track.clientWidth + 4 ) {
			if ( prev ) {
				prev.disabled = true;
			}
			if ( next ) {
				next.disabled = true;
			}
		}
	}

	function boot() {
		Array.prototype.forEach.call( document.querySelectorAll( '.pe-featured-slider' ), init );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
} )();
