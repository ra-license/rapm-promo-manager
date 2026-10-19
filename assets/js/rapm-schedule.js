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

	// Our own Swiper 11, saved right after its file runs (RAPM_Assets). It
	// has to be ours: Elementor's Swiper 8 can take over window.Swiper, and
	// Swiper 8 loops by adding copies of the slides, which this file and
	// rapm-hero.css weren't built for. Falls back to whatever window.Swiper
	// is if ours wasn't saved.
	function swiperClass() {
		return window.RAPM_Swiper || window.Swiper;
	}

	/**
	 * A placement marked as the page's main heading (heading="h1", 1.29.1; data-rapm-h1): the
	 * first slide showing that has big words gets an h1, every other one an
	 * h2. The server gives the h1 to the first slide in order, but the
	 * schedule may be hiding that one, so it's set again here from what is
	 * actually showing.
	 */
	function setMainHeading( slides ) {
		var used = false;
		slides.forEach( function ( slide ) {
			var heading = slide.querySelector( '.rapm-headline' );
			if ( ! heading ) { return; }
			var want = used ? 'H2' : 'H1';
			used = true;
			if ( heading.tagName === want ) { return; }
			var swap = document.createElement( want );
			Array.prototype.slice.call( heading.attributes ).forEach( function ( a ) { swap.setAttribute( a.name, a.value ); } );
			while ( heading.firstChild ) { swap.appendChild( heading.firstChild ); }
			heading.parentNode.replaceChild( swap, heading );
		} );
	}

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
		// 1.52.0: a split slider can set root.rapmArrange( slides ) to
		// choose the play order (linked groups). It may return copies of a
		// slide (a photo that plays in every group); those are kept here so
		// the next build removes them.
		var extras     = [];
		var lastSig    = null;

		// 1.41.1: a picture-only slide shows its whole phone picture with a
		// blurred copy of it filling the sides (rapm-hero.css). The copy is
		// whatever picture the browser actually loaded, once it has (after a
		// lazy-loader's stand-in too), so it's already downloaded and a slide
		// nobody reaches costs nothing.
		allSlides.forEach( function ( slide ) {
			var img = slide.classList.contains( 'is-picture-only' ) ? slide.querySelector( '.rapm-slide-img' ) : null;
			if ( ! img ) { return; }
			function blur() {
				var src = img.currentSrc || '';
				if ( src && ! /^data:/i.test( src ) ) {
					slide.style.setProperty( '--rapm-blur', 'url("' + src.replace( /["\\\n]/g, encodeURIComponent ) + '")' );
				}
			}
			img.addEventListener( 'load', blur );
			if ( img.complete ) { blur(); }
		} );

		// The slides to show right now: everything live today, in the order
		// set on the Sliders screen, then cut to "show at most" if set. The
		// cap is applied after the schedule check, so a promotion that hasn't
		// started yet (or has ended) never takes up one of the spots.
		function shownSlides() {
			var active = allSlides.filter( isActive );
			active = max ? active.slice( 0, max ) : active;
			return 'function' === typeof root.rapmArrange ? root.rapmArrange( active ) : active;
		}

		// What's playing, to tell whether anything changed.
		function sig( list ) {
			return list.map( function ( s ) { return ( s.getAttribute( 'data-rapm-key' ) || '' ) + '@' + ( s.getAttribute( 'data-rapm-pair' ) || '' ); } ).join( ',' );
		}

		// 1.52.0: tells the split view which slide is showing.
		function announce( slide, index ) {
			var ev;
			try {
				ev = new CustomEvent( 'rapm:slide', { detail: { slide: slide || null, index: index || 0 } } );
			} catch ( e ) {
				ev = document.createEvent( 'CustomEvent' );
				ev.initCustomEvent( 'rapm:slide', false, false, { slide: slide || null, index: index || 0 } );
			}
			root.dispatchEvent( ev );
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

			allSlides.concat( extras ).forEach( function ( s ) {
				if ( s.parentNode === wrapper ) { wrapper.removeChild( s ); }
			} );
			var active = shownSlides();
			extras = active.filter( function ( s ) { return -1 === allSlides.indexOf( s ); } );
			lastSig = sig( active );

			if ( 0 === active.length ) {
				root.style.display = 'none';
				announce( null, 0 );
				return;
			}
			active.forEach( function ( s ) { wrapper.appendChild( s ); } );
			if ( root.hasAttribute( 'data-rapm-h1' ) ) { setMainHeading( active ); }
			root.style.display = '';

			var SwiperClass = swiperClass();
			if ( typeof SwiperClass === 'undefined' ) {
				announce( active[ 0 ], 0 );
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

			instance = new SwiperClass( selector, config );
			liveCarousels.push( instance );
			var current = instance;
			current.on( 'slideChange', function () {
				if ( current.destroyed ) { return; }
				announce( current.slides[ current.activeIndex ], current.realIndex );
			} );
			announce( current.slides[ current.activeIndex ], current.realIndex );
		}

		root.rapmRebuild = build;
		build();

		// Re-check periodically so a scheduled asset can appear or
		// disappear on a page a visitor left open — or one served straight
		// from a full-page cache — without a reload.
		// Compares against the same capped list build() uses, so a capped
		// carousel isn't rebuilt (and reset to slide one) every minute.
		// Counts only this carousel's own slides, not every .swiper-slide
		// in the wrapper: Swiper 8 adds copies of the slides in loop mode,
		// and counting those (before 1.27.1) rebuilt the carousel every
		// minute even when nothing had changed.
		// 1.52.0: compares what would play (keys, and each one's linked
		// promotion) with what is playing, since a linked group can change
		// when only its promotion starts or ends.
		setInterval( function () {
			if ( sig( shownSlides() ) !== lastSig ) { build(); }
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

	/**
	 * 1.37.1, Phil's standing rule: anything that scrolls sideways shows
	 * dots for where you are. Fills `dotsBox` with one dot per screenful of
	 * `list` (none, and hidden, when everything fits), keeps the active dot
	 * in step with the scroll, and scrolls there when a dot is tapped.
	 * Returns the rebuild function, to call again whenever the row's items
	 * change (a link or coupon starting or ending).
	 *
	 * @param {Element} list    The element that scrolls sideways.
	 * @param {Element} dotsBox An empty element for the dots.
	 * @return {function} Rebuild the dots.
	 */
	function scrollDots( list, dotsBox ) {
		var pages = 0;
		var reduceMotion = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

		function overflow() { return list.scrollWidth - list.clientWidth; }

		function mark() {
			if ( pages < 2 ) { return; }
			var over = overflow();
			var on   = over > 0 ? Math.round( list.scrollLeft / over * ( pages - 1 ) ) : 0;
			Array.prototype.forEach.call( dotsBox.children, function ( d, i ) { d.classList.toggle( 'is-active', i === on ); } );
		}

		function build() {
			var over = overflow();
			pages = over > 2 && list.clientWidth > 0 ? Math.max( 2, Math.ceil( list.scrollWidth / list.clientWidth ) ) : 0;
			dotsBox.innerHTML = '';
			dotsBox.hidden = pages < 2;
			for ( var i = 0; i < pages; i++ ) {
				var b = document.createElement( 'button' );
				b.type = 'button';
				b.className = 'rapm-scroll-dot';
				b.tabIndex = -1; // The links themselves are the keyboard path.
				b.setAttribute( 'data-page', i );
				dotsBox.appendChild( b );
			}
			mark();
		}

		dotsBox.addEventListener( 'click', function ( e ) {
			var b = e.target.closest ? e.target.closest( '.rapm-scroll-dot' ) : null;
			if ( ! b || pages < 2 ) { return; }
			var left = overflow() * parseInt( b.getAttribute( 'data-page' ), 10 ) / ( pages - 1 );
			if ( list.scrollTo ) { list.scrollTo( { left: left, behavior: reduceMotion ? 'auto' : 'smooth' } ); } else { list.scrollLeft = left; }
		} );
		list.addEventListener( 'scroll', mark, { passive: true } );
		window.addEventListener( 'resize', build );
		window.addEventListener( 'load', build );
		return build;
	}

	/**
	 * A split slider's promotion box, or [rapm_promo_box] on its own
	 * (RAPM_Split). Moved here from an inline script in 1.52.0: WordPress
	 * rewrites "&&" in a page's own content to "&#038;&#038;", which broke
	 * the inline copy wherever the slider's code was typed into a page
	 * rather than an Elementor widget. Runs for every .rapm-split and
	 * .rapm-pb-solo on the page, once each.
	 *
	 * Shows the first live promotion and hides the rest, checking again
	 * every minute (watch). With none live, the box hides and the Slider
	 * takes the full width; Swiper is told to measure again.
	 *
	 * 1.52.0: with "Changes with its room photos" (linked), the room slider
	 * plays, for each live promotion in order, the photos that go with it
	 * and then the "Any promotion" photos (copies, after the first group),
	 * each marked with data-rapm-pair; photos of a promotion that isn't live
	 * drop out. The box shows the promotion paired with the slide that's
	 * showing. With "Rotates on its own" (timer), it moves to the next live
	 * promotion every few room photos. Either way the box changes at the
	 * same moment as the photo, with a short fade.
	 */
	function split( wrap ) {
		if ( ! wrap || wrap.rapmSplit ) { return; }
		wrap.rapmSplit = true;
		var box = wrap && wrap.querySelector( '[data-rapm-pb]' );
		if ( ! box ) { return; }
		// Full page width: the page's width without the scrollbar,
		// which 100vw would include (as in rapm-looks.js).
		var isFull = wrap.classList.contains( 'is-full' );
		var main = wrap.querySelector( '.rapm-split-main' );
		// 1.43.2 (Phil, on staging at 1903px wide): a 4:3 room
		// in a wide column ran past the bottom of the screen. On
		// computers the room is kept within the screen below
		// where the block starts (the header), when it starts in
		// the first screen, but never less than half as tall as
		// its column is wide. rapm-hero.css trims the photos
		// evenly top and bottom to that height.
		var size = function () {
			if ( ! main ) { return; } // [rapm_promo_box] on its own has no room photos.
			if ( isFull ) { wrap.style.setProperty( '--rapm-split-vw', document.documentElement.clientWidth + 'px' ); }
			var top = wrap.getBoundingClientRect().top + ( window.pageYOffset || document.documentElement.scrollTop || 0 );
			var screenH = window.innerHeight;
			var room = screenH - ( top < screenH * 0.6 ? top : 0 );
			wrap.style.setProperty( '--rapm-split-maxh', Math.round( Math.max( room, main.clientWidth / 2 ) ) + 'px' );
		};
		size();
		var sizing = 0;
		window.addEventListener( 'resize', function () {
			if ( sizing ) { return; }
			sizing = 1;
			window.requestAnimationFrame( function () { size(); sizing = 0; } );
		} );
		var mode = wrap.getAttribute( 'data-rapm-box-mode' ) || 'still';
		var every = parseInt( wrap.getAttribute( 'data-rapm-box-every' ), 10 ) || 3;
		var items = Array.prototype.slice.call( box.querySelectorAll( '.rapm-pb-item' ) );
		var keyOf = function ( el ) { return el ? el.getAttribute( 'data-rapm-key' ) : ''; };
		var shown = null;
		var empty = null;
		// Shows one promotion (or none), fading it in when it changes.
		var show = function ( item ) {
			if ( item === shown && null !== empty ) { return; }
			var had = shown;
			shown = item || null;
			items.forEach( function ( el ) {
				el.hidden = el !== shown;
				el.classList.remove( 'is-in' );
			} );
			if ( shown && had && shown !== had ) {
				void shown.offsetWidth; // restart the fade
				shown.classList.add( 'is-in' );
			}
			if ( empty !== ! shown ) {
				empty = ! shown;
				wrap.classList.toggle( 'rapm-split-empty', empty );
				window.dispatchEvent( new Event( 'resize' ) );
			}
		};
		var carousel = 'still' === mode ? null : wrap.querySelector( '.rapm-split-main [data-rapm-carousel]' );
		if ( ! carousel ) {
			watch( box, '.rapm-pb-item', function ( active ) { show( active[ 0 ] ); } );
		} else {
			var liveItems = function () { return items.filter( isActive ); };
			if ( 'linked' === mode ) {
				carousel.rapmArrange = function ( slides ) {
					var keys = items.map( keyOf );
					var live = liveItems();
					var anyPhotos = slides.filter( function ( s ) {
						var g = s.getAttribute( 'data-rapm-goes' );
						return ! g || -1 === keys.indexOf( g );
					} );
					var out = [];
					var used = [];
					live.forEach( function ( item ) {
						var k = keyOf( item );
						slides.filter( function ( s ) { return s.getAttribute( 'data-rapm-goes' ) === k; } ).concat( anyPhotos ).forEach( function ( s ) {
							var el = -1 === used.indexOf( s ) ? s : s.cloneNode( true );
							used.push( s );
							el.setAttribute( 'data-rapm-pair', k );
							out.push( el );
						} );
					} );
					if ( ! live.length ) {
						anyPhotos.forEach( function ( s ) { s.removeAttribute( 'data-rapm-pair' ); out.push( s ); } );
					}
					return out;
				};
			}
			carousel.addEventListener( 'rapm:slide', function ( e ) {
				var slide = e.detail && e.detail.slide;
				if ( 'linked' === mode ) {
					var pair = slide ? slide.getAttribute( 'data-rapm-pair' ) : '';
					show( pair ? items.filter( function ( el ) { return keyOf( el ) === pair; } )[ 0 ] : null );
				} else {
					var live = liveItems();
					show( live.length ? live[ Math.floor( ( e.detail.index || 0 ) / every ) % live.length ] : null );
				}
			} );
			// The slider may already be running (it starts first on
			// the page): build it again with the new order.
			if ( carousel.rapmRebuild ) { carousel.rapmRebuild(); }
		}
	}

	function splitAll() {
		Array.prototype.forEach.call( document.querySelectorAll( '.rapm-split, .rapm-pb-solo' ), split );
	}
	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', splitAll );
	} else {
		splitAll();
	}
	window.addEventListener( 'load', splitAll );
	window.addEventListener( 'elementor/popup/show', splitAll );

	window.RAPM_Schedule = { init: init, isActive: isActive, watch: watch, scrollDots: scrollDots, split: split };
} )( window );
