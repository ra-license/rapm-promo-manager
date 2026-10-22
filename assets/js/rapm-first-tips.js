/**
 * One-time tips (1.58.0, RAPM_First_Tips). Shows at most one tip per page
 * view: the first unseen tip whose element is on screen, pointing at it.
 * It never takes focus away from what someone is typing; it's announced
 * politely, and Esc closes it when focus is inside it.
 */
( function () {
	'use strict';

	var C = window.RAPM_First_Tips;
	if ( ! C || ! C.tips || ! C.tips.length || ! window.IntersectionObserver ) {
		return;
	}
	var shown = false;
	var watchers = [];

	function send( data ) {
		var body = new FormData();
		body.append( 'action', 'rapm_first_tip' );
		body.append( 'nonce', C.nonce );
		Object.keys( data ).forEach( function ( k ) {
			body.append( k, data[ k ] );
		} );
		return fetch( C.ajax, { method: 'POST', body: body, credentials: 'same-origin' } ).catch( function () {} );
	}

	// An anchor inside a bar that stays on screen (the editor's top bar):
	// the tip goes inside that bar, so it moves with it as the page scrolls.
	function pinnedBox( el ) {
		for ( ; el && el !== document.body; el = el.parentElement ) {
			var pos = window.getComputedStyle( el ).position;
			if ( 'sticky' === pos || 'fixed' === pos ) {
				return el;
			}
		}
		return null;
	}

	function place( tip, anchor ) {
		var box = tip.parentElement === document.body ? null : tip.parentElement;
		var r = anchor.getBoundingClientRect();
		var origin = box ? box.getBoundingClientRect() : { left: -window.scrollX, top: -window.scrollY };
		var width = document.documentElement.clientWidth;
		var leftOnScreen = Math.max( 12, Math.min( r.left, width - tip.offsetWidth - 12 ) );
		tip.style.top = ( r.bottom - origin.top + 12 ) + 'px';
		tip.style.left = ( leftOnScreen - origin.left ) + 'px';
		var arrow = tip.querySelector( '.rapm-ft-arrow' );
		arrow.style.left = Math.max( 16, Math.min( r.left - leftOnScreen + 24, tip.offsetWidth - 32 ) ) + 'px';
	}

	function show( t, anchor ) {
		shown = true;
		watchers.forEach( function ( w ) {
			w.disconnect();
		} );
		var tip = document.createElement( 'div' );
		tip.className = 'rapm-ft';
		tip.setAttribute( 'role', 'region' );
		tip.setAttribute( 'aria-label', C.text.label );
		tip.innerHTML = '<span class="rapm-ft-arrow" aria-hidden="true"></span>' +
			'<p class="rapm-ft-title" aria-live="polite"></p><p class="rapm-ft-text"></p>' +
			'<p class="rapm-ft-buttons"><button type="button" class="rapm-ft-ok"></button> <button type="button" class="rapm-ft-off"></button></p>';
		tip.querySelector( '.rapm-ft-text' ).textContent = t.text;
		tip.querySelector( '.rapm-ft-ok' ).textContent = C.text.gotIt;
		tip.querySelector( '.rapm-ft-off' ).textContent = C.text.off;
		( pinnedBox( anchor ) || document.body ).appendChild( tip );
		// Set after it's in the page, so screen readers announce it once.
		window.setTimeout( function () {
			tip.querySelector( '.rapm-ft-title' ).textContent = t.title;
		}, 50 );
		anchor.classList.add( 'rapm-ft-target' );
		place( tip, anchor );
		var onResize = function () {
			place( tip, anchor );
		};
		window.addEventListener( 'resize', onResize );

		function close( data ) {
			window.removeEventListener( 'resize', onResize );
			anchor.classList.remove( 'rapm-ft-target' );
			tip.remove();
			send( data );
		}
		tip.querySelector( '.rapm-ft-ok' ).addEventListener( 'click', function () {
			close( { do: 'seen', tip: t.id } );
		} );
		tip.querySelector( '.rapm-ft-off' ).addEventListener( 'click', function () {
			close( { do: 'off' } );
		} );
		tip.addEventListener( 'keydown', function ( e ) {
			if ( 'Escape' === e.key ) {
				close( { do: 'seen', tip: t.id } );
			}
		} );
	}

	function start() {
		C.tips.forEach( function ( t ) {
			var anchor = document.querySelector( t.anchor );
			if ( ! anchor ) {
				return;
			}
			var io = new IntersectionObserver( function ( entries ) {
				entries.forEach( function ( e ) {
					if ( ! shown && e.isIntersecting && e.intersectionRatio > 0.5 ) {
						show( t, anchor );
					}
				} );
			}, { threshold: [ 0.5 ] } );
			io.observe( anchor );
			watchers.push( io );
		} );
	}

	// Some anchors appear after a picture is chosen (dots, focal point), so
	// look again a few times as the page changes.
	var tries = 0;
	function retry() {
		if ( shown || tries++ > 20 ) {
			return;
		}
		watchers.forEach( function ( w ) {
			w.disconnect();
		} );
		watchers = [];
		start();
		window.setTimeout( retry, 3000 );
	}
	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', retry );
	} else {
		retry();
	}
}() );
