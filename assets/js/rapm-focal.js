/**
 * "What stays in view" on the promotion form (1.54.0, RAPM_Focal): drag
 * (or use the arrow keys) to set the point that has to stay in view when
 * the website trims a picture, and see every shape it's trimmed to, live.
 * The previews use the same rule as the website (object-position at the
 * point), so what shows here is what shows there.
 */
( function () {
	'use strict';

	function clamp( v ) { return Math.min( 100, Math.max( 0, Math.round( v * 10 ) / 10 ) ); }

	function init( box ) {
		var which  = box.getAttribute( 'data-which' );
		var shapes = JSON.parse( box.getAttribute( 'data-shapes' ) || '[]' );
		var input  = box.querySelector( 'input[type="hidden"]' );
		var photo  = box.querySelector( '.rapm-focal-photo' );
		var img    = photo.querySelector( 'img' );
		var dot    = photo.querySelector( '.rapm-focal-dot' );
		var pvs    = box.querySelector( '.rapm-focal-pvs' );
		var kv     = box.querySelector( '.rapm-focal-kv' );
		var T      = window.RAPM_Focal_Text || {};
		var parts  = ( input.value || '50% 50%' ).replace( /%/g, '' ).split( ' ' );
		var pt     = [ parseFloat( parts[ 0 ] ) || 50, parseFloat( parts[ 1 ] ) || 50 ];

		function kept( w, h ) {
			var r = img.naturalWidth / img.naturalHeight, b = w / h;
			if ( ! r || Math.abs( r - b ) / b < 0.03 ) { return T.nothing || 'Nothing trimmed'; }
			return r > b ? ( T.width || 'Keeps %d%% of the width' ).replace( '%d', Math.round( b / r * 100 ) ).replace( '%%', '%' ) : ( T.height || 'Keeps %d%% of the height' ).replace( '%d', Math.round( r / b * 100 ) ).replace( '%%', '%' );
		}

		function draw() {
			var pos = pt[ 0 ] + '% ' + pt[ 1 ] + '%';
			input.value = pos;
			dot.style.left = pt[ 0 ] + '%';
			dot.style.top = pt[ 1 ] + '%';
			kv.textContent = ( T.at || 'Kept in view: %1$s across, %2$s down' ).replace( '%1$s', Math.round( pt[ 0 ] ) + '%' ).replace( '%2$s', Math.round( pt[ 1 ] ) + '%' );
			photo.setAttribute( 'aria-valuetext', kv.textContent );
			if ( ! img.getAttribute( 'src' ) ) { return; }
			pvs.innerHTML = '';
			shapes.forEach( function ( s ) {
				var scale = Math.min( 1, 150 / s[ 1 ] );
				var w = Math.max( 40, s[ 1 ] * scale ), h = w * s[ 2 ] / s[ 1 ];
				var fig = document.createElement( 'figure' );
				fig.className = 'rapm-focal-pv';
				var b = document.createElement( 'span' );
				b.className = 'rapm-focal-box' + ( s[ 3 ] ? ' is-round' : '' );
				b.style.width = w + 'px';
				b.style.height = h + 'px';
				b.style.backgroundImage = 'url("' + img.src.replace( /"/g, '%22' ) + '")';
				b.style.backgroundPosition = pos;
				var cap = document.createElement( 'figcaption' );
				cap.innerHTML = '<b></b><br><span></span>';
				cap.querySelector( 'b' ).textContent = s[ 0 ];
				cap.querySelector( 'span' ).textContent = kept( s[ 1 ], s[ 2 ] );
				fig.appendChild( b );
				fig.appendChild( cap );
				pvs.appendChild( fig );
			} );
		}

		function setFrom( e ) {
			var r = img.getBoundingClientRect();
			if ( ! r.width ) { return; }
			pt = [ clamp( ( e.clientX - r.left ) / r.width * 100 ), clamp( ( e.clientY - r.top ) / r.height * 100 ) ];
			draw();
		}
		var dragging = false;
		photo.addEventListener( 'pointerdown', function ( e ) { dragging = true; photo.setPointerCapture( e.pointerId ); setFrom( e ); e.preventDefault(); } );
		photo.addEventListener( 'pointermove', function ( e ) { if ( dragging ) { setFrom( e ); } } );
		photo.addEventListener( 'pointerup', function () { dragging = false; } );
		photo.addEventListener( 'keydown', function ( e ) {
			var d = e.shiftKey ? 10 : 2;
			if ( 'ArrowLeft' === e.key ) { pt[ 0 ] = clamp( pt[ 0 ] - d ); } else if ( 'ArrowRight' === e.key ) { pt[ 0 ] = clamp( pt[ 0 ] + d ); } else if ( 'ArrowUp' === e.key ) { pt[ 1 ] = clamp( pt[ 1 ] - d ); } else if ( 'ArrowDown' === e.key ) { pt[ 1 ] = clamp( pt[ 1 ] + d ); } else { return; }
			e.preventDefault();
			draw();
		} );
		box.querySelector( '.rapm-focal-reset' ).addEventListener( 'click', function () { pt = [ 50, 50 ]; draw(); } );
		img.addEventListener( 'load', draw );

		// A newly chosen file is a new picture: show it, with the point back in the middle.
		var file = document.getElementById( 'mobile' === which ? 'rapm_image_mobile' : 'rapm_image_desktop' );
		if ( file ) {
			file.addEventListener( 'change', function () {
				if ( ! file.files || ! file.files[ 0 ] ) { return; }
				pt = [ 50, 50 ];
				img.src = URL.createObjectURL( file.files[ 0 ] );
				box.hidden = false;
				draw();
			} );
		}
		draw();
	}

	function start() {
		Array.prototype.forEach.call( document.querySelectorAll( '.rapm-focal' ), init );
	}
	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', start );
	} else {
		start();
	}
}() );
