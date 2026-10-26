/**
 * "Place the words" (1.64.0, RAPM_Place): across and up and down for the
 * words on a Slider, Feature banner or Coupon row picture, on computers
 * and, if wanted, their own places on phones.
 *
 * The hidden fields are rapm_text_align (computers' across, as before),
 * rapm_place_y (computers' up and down) and rapm_place_pa / rapm_place_py
 * (phones', '' for the same as computers). The Live Preview follows the
 * screen it shows, and a note says when the words are taller than the
 * picture there, so some would be cut off.
 */
( function () {
	var root = document.querySelector( '.rapm-place' );
	if ( ! root ) {
		return;
	}
	var T = JSON.parse( root.getAttribute( 'data-text' ) || '{}' );
	var align = document.getElementById( 'rapm_text_align' );
	var y = document.getElementById( 'rapm_place_y' );
	var pa = document.getElementById( 'rapm_place_pa' );
	var py = document.getElementById( 'rapm_place_py' );
	var note = root.querySelector( '.rapm-place-phone-note' );
	var copyBtn = root.querySelector( '.rapm-place-copy' );
	var fit = root.querySelector( '.rapm-place-fit' );
	var pvDesktop = document.getElementById( 'rapm-preview-toggle-desktop' );
	var pvPhone = document.getElementById( 'rapm-preview-toggle-mobile' );
	var screen = 'computer';

	function set( input, value ) {
		input.value = value;
		input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
	}

	// What each screen uses now.
	function across( s ) { return 'phone' === s && pa.value ? pa.value : align.value || 'left'; }
	function down( s ) { return 'phone' === s && py.value ? py.value : y.value || 'bottom'; }

	function previewScreen() {
		return pvPhone && 'true' === pvPhone.getAttribute( 'aria-pressed' ) ? 'phone' : 'computer';
	}

	// The Live Preview's words, placed for the screen it shows.
	function paint() {
		var copy = document.getElementById( 'rapm-preview-copy' );
		var box = document.getElementById( 'rapm-preview' );
		if ( ! copy ) {
			return;
		}
		var s = previewScreen();
		copy.setAttribute( 'data-align', across( s ) );
		if ( 'bottom' === down( s ) ) {
			copy.removeAttribute( 'data-y' );
		} else {
			copy.setAttribute( 'data-y', down( s ) );
		}
		if ( fit && box ) {
			// Cut off means a line of words reaches past the picture's top or
			// bottom; the box's own padding around them doesn't count.
			var b = box.getBoundingClientRect(), cut = false, covered = false;
			// The Shop button placed on the picture (Place the pieces), if any.
			var pin = box.querySelector( '.rapm-pin-btn' ), p = pin ? pin.getBoundingClientRect() : null;
			Array.prototype.forEach.call( copy.children, function ( n ) {
				if ( ! n.offsetParent || '' === ( n.textContent || '' ).trim() ) {
					return;
				}
				var r = n.getBoundingClientRect();
				if ( r.top < b.top - 1 || r.bottom > b.bottom + 1 ) {
					cut = true;
				}
				if ( p && p.left < r.right && p.right > r.left && p.top < r.bottom && p.bottom > r.top ) {
					covered = true;
				}
			} );
			fit.hidden = ! cut && ! covered;
			fit.textContent = [ cut ? T.tooTall : '', covered ? T.covered : '' ].filter( Boolean ).join( ' ' );
		}
	}

	function render() {
		root.querySelectorAll( '[data-screen]' ).forEach( function ( b ) { b.setAttribute( 'aria-pressed', String( b.getAttribute( 'data-screen' ) === screen ) ); } );
		root.querySelectorAll( '.rapm-place-btn[data-x]' ).forEach( function ( b ) { b.setAttribute( 'aria-pressed', String( b.getAttribute( 'data-x' ) === across( screen ) ) ); } );
		root.querySelectorAll( '.rapm-place-btn[data-y]' ).forEach( function ( b ) { b.setAttribute( 'aria-pressed', String( b.getAttribute( 'data-y' ) === down( screen ) ) ); } );
		var own = !! ( pa.value || py.value );
		note.textContent = own ? T.phoneOwn : T.phoneSame;
		copyBtn.textContent = 'phone' === screen ? T.same : T.copy;
		copyBtn.hidden = ! own;
		paint();
	}

	root.addEventListener( 'click', function ( e ) {
		var b = e.target.closest( 'button' );
		if ( ! b ) {
			return;
		}
		if ( b.hasAttribute( 'data-screen' ) ) {
			screen = b.getAttribute( 'data-screen' );
			// Show the same screen in the Live Preview.
			var pv = 'phone' === screen ? pvPhone : pvDesktop;
			if ( pv && 'true' !== pv.getAttribute( 'aria-pressed' ) ) {
				pv.click();
			}
		} else if ( b.hasAttribute( 'data-x' ) ) {
			var x = b.getAttribute( 'data-x' );
			if ( 'phone' === screen ) {
				set( pa, x === align.value && ! py.value ? '' : x );
			} else {
				set( align, x );
			}
		} else if ( b.hasAttribute( 'data-y' ) ) {
			var v = b.getAttribute( 'data-y' );
			if ( 'phone' === screen ) {
				set( py, v === y.value && ! pa.value ? '' : v );
			} else {
				set( y, v );
			}
		} else if ( b === copyBtn ) {
			set( pa, '' );
			set( py, '' );
		}
		render();
	} );

	// The Live Preview's own Computer and Phone buttons switch this too.
	[ pvDesktop, pvPhone ].forEach( function ( pv ) {
		if ( pv ) {
			pv.addEventListener( 'click', function () {
				screen = pv === pvPhone ? 'phone' : 'computer';
				setTimeout( render, 0 );
			} );
		}
	} );
	// The preview redraws the words as they're typed; place them again after.
	document.addEventListener( 'input', function () { setTimeout( paint, 0 ); } );
	document.addEventListener( 'change', function () { setTimeout( paint, 0 ); } );
	window.addEventListener( 'resize', paint );
	// The Shop button is drawn into the preview by rapm-look-editor.js; check again when it moves.
	var pv = document.getElementById( 'rapm-preview' );
	if ( pv && window.MutationObserver ) {
		var timer = 0;
		new window.MutationObserver( function () { clearTimeout( timer ); timer = setTimeout( paint, 30 ); } ).observe( pv, { childList: true, subtree: true, attributes: true, attributeFilter: [ 'style' ] } );
	}
	window.addEventListener( 'load', paint );
	render();
}() );
