/**
 * Shop the Look (RAPM_Looks, 1.30.0): builds the tab bar from the looks
 * that are live right now (RAPM_Schedule.watch(), so dates work behind a
 * full-page cache), shows one look at a time, and runs the tour.
 *
 * The tour, as approved in the 2026-10-06 mockup:
 * - moves to the next look every --rapm-looks-speed (8 seconds by default);
 * - pauses while the mouse is over the photo or a control has keyboard focus;
 * - stops for good once a shopper picks a tab, uses an arrow or swipes;
 * - has a pause/play button;
 * - never starts for visitors who have reduced motion turned on.
 * The timer is a CSS animation on the tab's top line, so pausing is just a
 * class, and the next look starts when the animation ends.
 */
( function ( window ) {
	'use strict';

	var PLAY  = '<svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false"><path d="M7 4.5v15l13-7.5z"/></svg>';
	var PAUSE = '<svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false"><path d="M6 4h4v16H6zM14 4h4v16h-4z"/></svg>';

	function init( root, options ) {
		if ( ! root || root.getAttribute( 'data-rapm-looks-ready' ) ) { return; }
		root.setAttribute( 'data-rapm-looks-ready', '1' );
		options = options || {};

		var T       = options.text || {};
		var stage   = root.querySelector( '.rapm-looks-stage' );
		var tabsEl  = root.querySelector( '.rapm-looks-tabs' );
		var tourBtn = root.querySelector( '.rapm-looks-tour' );
		var prevBtn = root.querySelector( '.rapm-looks-prev' );
		var nextBtn = root.querySelector( '.rapm-looks-next' );
		var shelf   = root.querySelector( '.rapm-looks-shelf' );
		var photos  = Array.prototype.slice.call( root.querySelectorAll( '.rapm-look-photo' ) );
		var texts   = Array.prototype.slice.call( root.querySelectorAll( '.rapm-look-text' ) );

		var reduceMotion = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
		var stopped      = ! options.autoplay || reduceMotion;
		var hovering     = false;
		var focused      = false;
		var live         = [];
		var tabs         = [];
		var index        = 0;

		function keyOf( el ) { return el.getAttribute( 'data-rapm-key' ); }
		function textFor( photo ) {
			var key = keyOf( photo );
			for ( var i = 0; i < texts.length; i++ ) {
				if ( keyOf( texts[ i ] ) === key ) { return texts[ i ]; }
			}
			return null;
		}

		// 1.30.1: fade an edge of the tab bar while more tabs are hidden past it.
		function updateMore() {
			tabsEl.classList.toggle( 'is-more', tabsEl.scrollLeft + tabsEl.clientWidth < tabsEl.scrollWidth - 2 );
			tabsEl.classList.toggle( 'is-less', tabsEl.scrollLeft > 2 );
		}

		function setPaused() {
			root.classList.toggle( 'is-paused', hovering || focused );
		}

		function tourLabel() {
			tourBtn.setAttribute( 'aria-label', stopped ? T.play : T.pause );
			tourBtn.innerHTML = stopped ? PLAY : PAUSE;
			shelf.setAttribute( 'aria-live', stopped ? 'polite' : 'off' );
		}

		function startTimer() {
			tabs.forEach( function ( tab ) {
				tab.querySelector( '.rapm-looks-fill' ).classList.remove( 'is-running' );
			} );
			if ( stopped || live.length < 2 || ! tabs[ index ] ) { return; }
			var fill = tabs[ index ].querySelector( '.rapm-looks-fill' );
			void fill.offsetWidth; // restart the animation from zero
			fill.classList.add( 'is-running' );
		}

		function show( n ) {
			if ( ! live.length ) { return; }
			index = ( n + live.length ) % live.length;
			var current = live[ index ];
			var text    = textFor( current );
			photos.forEach( function ( p ) {
				var on = p === current;
				p.classList.toggle( 'is-active', on );
				p.setAttribute( 'aria-hidden', on ? 'false' : 'true' );
				if ( on ) { p.removeAttribute( 'inert' ); } else { p.setAttribute( 'inert', '' ); }
			} );
			texts.forEach( function ( t ) { t.hidden = t !== text; } );
			tabs.forEach( function ( tab, i ) {
				var on = i === index;
				tab.setAttribute( 'aria-selected', on ? 'true' : 'false' );
				tab.tabIndex = on ? 0 : -1;
			} );
			var tab = tabs[ index ];
			if ( tab && tabsEl.scrollWidth > tabsEl.clientWidth ) {
				var left = tab.offsetLeft - ( tabsEl.clientWidth - tab.offsetWidth ) / 2;
				tabsEl.scrollTo( { left: Math.max( left, 0 ), behavior: reduceMotion ? 'auto' : 'smooth' } );
			}
			startTimer();
		}

		// A shopper choosing a look ends the tour.
		function pick( n ) {
			if ( ! stopped ) {
				stopped = true;
				tourLabel();
			}
			show( n );
		}

		function build( active ) {
			var currentKey = live[ index ] ? keyOf( live[ index ] ) : null;
			live = active;
			tabs = [];
			tabsEl.innerHTML = '';
			live.forEach( function ( photo, i ) {
				var tab   = document.createElement( 'button' );
				var track = document.createElement( 'span' );
				var fill  = document.createElement( 'span' );
				var text  = textFor( photo );
				tab.type      = 'button';
				tab.className = 'rapm-looks-tab';
				tab.id        = photo.id + '-tab';
				tab.setAttribute( 'role', 'tab' );
				tab.setAttribute( 'aria-controls', photo.id + ( text ? ' ' + text.id : '' ) );
				track.className = 'rapm-looks-track';
				fill.className  = 'rapm-looks-fill';
				track.appendChild( fill );
				tab.appendChild( track );
				tab.appendChild( document.createTextNode( photo.getAttribute( 'data-tab' ) || '' ) );
				tab.addEventListener( 'click', function () { pick( i ); } );
				tabsEl.appendChild( tab );
				tabs.push( tab );
			} );

			root.style.display = live.length ? '' : 'none';
			var several = live.length > 1;
			prevBtn.hidden = ! several;
			nextBtn.hidden = ! several;
			tourBtn.hidden = ! several || ! options.autoplay || reduceMotion;

			var keep = 0;
			live.forEach( function ( p, i ) { if ( keyOf( p ) === currentKey ) { keep = i; } } );
			show( keep );
			updateMore();
		}

		tabsEl.addEventListener( 'scroll', updateMore, { passive: true } );
		window.addEventListener( 'resize', updateMore );

		tabsEl.addEventListener( 'animationend', function ( e ) {
			if ( stopped || ! e.target.classList.contains( 'rapm-looks-fill' ) ) { return; }
			show( index + 1 );
		} );

		tabsEl.addEventListener( 'keydown', function ( e ) {
			var n = null;
			if ( 'ArrowRight' === e.key ) { n = index + 1; }
			if ( 'ArrowLeft' === e.key ) { n = index - 1; }
			if ( 'Home' === e.key ) { n = 0; }
			if ( 'End' === e.key ) { n = live.length - 1; }
			if ( null === n ) { return; }
			e.preventDefault();
			pick( n );
			tabs[ index ].focus();
		} );

		prevBtn.addEventListener( 'click', function () { pick( index - 1 ); } );
		nextBtn.addEventListener( 'click', function () { pick( index + 1 ); } );
		tourBtn.addEventListener( 'click', function () {
			stopped = ! stopped;
			tourLabel();
			startTimer();
		} );

		stage.addEventListener( 'pointerenter', function ( e ) {
			if ( 'mouse' === e.pointerType ) { hovering = true; setPaused(); }
		} );
		stage.addEventListener( 'pointerleave', function ( e ) {
			if ( 'mouse' === e.pointerType ) { hovering = false; setPaused(); }
		} );
		root.addEventListener( 'focusin', function ( e ) {
			if ( e.target.matches && e.target.matches( ':focus-visible' ) ) { focused = true; setPaused(); }
		} );
		root.addEventListener( 'focusout', function ( e ) {
			if ( ! root.contains( e.relatedTarget ) ) { focused = false; setPaused(); }
		} );

		// Swipe between looks on touch screens.
		var startX = null, startY = null;
		stage.addEventListener( 'pointerdown', function ( e ) {
			if ( 'mouse' === e.pointerType || ( e.target.closest && e.target.closest( '.rapm-looks-arrows' ) ) ) { startX = null; return; }
			startX = e.clientX;
			startY = e.clientY;
		} );
		stage.addEventListener( 'pointerup', function ( e ) {
			if ( null === startX ) { return; }
			var dx = e.clientX - startX, dy = e.clientY - startY;
			startX = null;
			if ( live.length > 1 && Math.abs( dx ) > 50 && Math.abs( dx ) > Math.abs( dy ) ) { pick( index + ( dx < 0 ? 1 : -1 ) ); }
		} );

		tourLabel();
		window.RAPM_Schedule.watch( root, '.rapm-look-photo', build );
	}

	window.RAPM_Looks = { init: init };
} )( window );
