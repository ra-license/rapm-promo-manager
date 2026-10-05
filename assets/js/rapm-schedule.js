/**
 * Shared scheduling engine for every RAPM display mode. Every asset is
 * always rendered in the page HTML with its schedule as data attributes
 * (data-rapm-start / data-rapm-end) — deliberately not filtered server-side
 * by the current time. Under a full-page cache plugin (WP Rocket etc.),
 * server-rendered HTML is only generated once and then served statically
 * for hours, so a server-side time check would "freeze" whichever assets
 * happened to be active when the cache was built. This module decides
 * what's actually active in the visitor's own browser instead, which
 * works correctly no matter how the HTML itself is cached — this is the
 * exact fix NovaSlider already proved out in production.
 */
( function ( window ) {
	'use strict';

	// Every live Swiper instance, so they can all be re-measured when an
	// Elementor popup opens (see refreshCarousels below).
	var liveCarousels = [];

	function isActive( el ) {
		var start = el.getAttribute( 'data-rapm-start' );
		var end   = el.getAttribute( 'data-rapm-end' );
		var now   = new Date();
		if ( start && now < new Date( start ) ) { return false; }
		if ( end && now > new Date( end ) ) { return false; }
		return true;
	}

	/**
	 * @param {string} selector      CSS selector for the .swiper root element.
	 * @param {object} options
	 * @param {number} options.loopMinSlides  Minimum active slides before Swiper loop mode is enabled.
	 * @param {string} options.effect         Swiper effect ('slide'|'fade').
	 * @param {boolean} options.autoplay
	 * @param {number} options.autoplaySpeed
	 * @param {string} options.nav            'both'|'arrows'|'dots'|'none'.
	 * @param {number} options.max            Show at most this many slides (0 = no limit).
	 */
	function init( selector, options ) {
		var root = document.querySelector( selector + '[data-rapm-carousel]' );
		if ( ! root ) { return; }

		var wrapper    = root.querySelector( '.swiper-wrapper' );
		var allSlides  = Array.prototype.slice.call( wrapper.querySelectorAll( '.swiper-slide' ) );
		var instance   = null;
		var max        = Math.max( 0, parseInt( options.max, 10 ) || 0 );

		// The slides to show right now: everything live today, in the order
		// set on the Sliders screen, then cut to "show at most" if set. The
		// cap is applied after the schedule check, so a promotion that hasn't
		// started yet (or has ended) never takes up one of the spots.
		function shownSlides() {
			var active = allSlides.filter( isActive );
			return max ? active.slice( 0, max ) : active;
		}

		function build() {
			// Shut the old carousel down FIRST. In loop mode, Swiper's
			// destroy() re-sorts the wrapper's slides by the numbers it gave
			// them. Destroying after the new set was appended (as before
			// 1.27.0) let it re-sort that new set, so a slide the old carousel
			// never had (no number) jumped to the front.
			if ( instance ) {
				liveCarousels = liveCarousels.filter( function ( s ) { return s !== instance; } );
				instance.destroy( true, true );
				instance = null;
			}

			allSlides.forEach( function ( s ) {
				if ( s.parentNode === wrapper ) { wrapper.removeChild( s ); }
			} );
			var active = shownSlides();

			if ( 0 === active.length ) {
				root.style.display = 'none';
				return;
			}
			active.forEach( function ( s ) { wrapper.appendChild( s ); } );
			root.style.display = '';

			if ( typeof window.Swiper === 'undefined' ) {
				return; // Not loaded yet (e.g. delayed by a JS optimizer) — slides stay visible as a static stack.
			}

			var config = {
				loop:       active.length >= ( options.loopMinSlides || 2 ),
				effect:     options.effect || 'slide',
				// Each slide sets its own height via CSS aspect-ratio (see
				// RAPM_Hero_Carousel) rather than inheriting a single fixed
				// height for the whole carousel — a slide with no dedicated
				// mobile picture uses its desktop image's own shape on
				// phones instead of being shrunk inside a taller box sized
				// for a mobile picture it doesn't have. autoHeight keeps the
				// visible carousel sized to match whichever slide is active.
				autoHeight: true,
				// A carousel inside an Elementor popup starts while the popup
				// is still hidden, so Swiper measures a zero-width box. These
				// two tell Swiper to re-measure itself when it or any parent
				// changes, such as the popup becoming visible.
				observer: true,
				observeParents: true,
			};
			if ( 'fade' === config.effect ) {
				config.fadeEffect = { crossFade: true };
			}
			if ( options.autoplay ) {
				config.autoplay = { delay: options.autoplaySpeed || 7000, disableOnInteraction: false, pauseOnMouseEnter: true };
			}
			if ( 'both' === options.nav || 'arrows' === options.nav ) {
				config.navigation = {
					nextEl: selector + ' .swiper-button-next',
					prevEl: selector + ' .swiper-button-prev',
				};
			}
			if ( 'both' === options.nav || 'dots' === options.nav ) {
				config.pagination = { el: selector + ' .swiper-pagination', clickable: true };
			}

			instance = new window.Swiper( selector, config );
			liveCarousels.push( instance );
		}

		build();

		// Re-check periodically so a scheduled asset can appear or
		// disappear on a page a visitor left open — or one served straight
		// from a full-page cache — without a reload.
		// Compares against the same capped list build() uses, so a capped
		// carousel isn't rebuilt (and reset to slide one) every minute.
		setInterval( function () {
			var stillShown     = shownSlides();
			var currentlyShown = wrapper.querySelectorAll( '.swiper-slide' ).length;
			var changed = stillShown.length !== currentlyShown ||
				stillShown.some( function ( s ) { return s.parentNode !== wrapper; } );
			if ( changed ) { build(); }
		}, 60000 );
	}

	/**
	 * A lighter-weight alternative to init() for display modes that aren't
	 * a Swiper carousel (Marquee, Coupon Book) — just re-filters a list of
	 * items by schedule and hands the active set to a callback, which
	 * decides what to do with them (rebuild a scrolling track, just
	 * show/hide cards, etc.). Same re-check cadence and reasoning as
	 * init(): a full-page cache can only ever serve a static snapshot, so
	 * "what's active" has to be decided here, in the visitor's own browser.
	 *
	 * @param {Element} container
	 * @param {string} itemSelector
	 * @param {function(Element[])} onChange  Called once immediately, then
	 *   again only when the active set actually changes.
	 */
	function watch( container, itemSelector, onChange ) {
		var allItems = Array.prototype.slice.call( container.querySelectorAll( itemSelector ) );
		var lastActiveIds = null;

		function apply() {
			var active = allItems.filter( isActive );
			var activeIds = active.map( function ( el, i ) { return el.getAttribute( 'data-rapm-key' ) || i; } ).join( ',' );
			if ( activeIds === lastActiveIds ) { return; }
			lastActiveIds = activeIds;
			onChange( active );
		}

		apply();
		setInterval( apply, 60000 );
	}

	/**
	 * Backup for the observer settings above: Elementor Pro announces every
	 * popup it opens with an 'elementor/popup/show' event. Re-measure every
	 * carousel then, so one inside the popup sizes to the popup. Since
	 * Elementor Pro 3.9 the event is a native CustomEvent on window; older
	 * versions sent it as a jQuery event on document. Listening for both
	 * covers either version (an extra update() is harmless).
	 */
	function refreshCarousels() {
		liveCarousels.forEach( function ( s ) {
			if ( s && ! s.destroyed ) { s.update(); }
		} );
	}
	window.addEventListener( 'elementor/popup/show', refreshCarousels );
	function bindJqueryPopupRefresh() {
		if ( ! window.jQuery ) { return false; }
		window.jQuery( window.document ).on( 'elementor/popup/show', refreshCarousels );
		return true;
	}
	if ( ! bindJqueryPopupRefresh() ) {
		window.document.addEventListener( 'DOMContentLoaded', bindJqueryPopupRefresh );
	}

	window.RAPM_Schedule = { init: init, isActive: isActive, watch: watch };
} )( window );
