/**
 * Promotion views and clicks in Google Analytics (1.59.0, RAPM_Track).
 * Loaded only when R&A has turned it on in Settings.
 *
 * - A view: at least half of a promotion on screen for one second without
 *   a break, once per promotion per page view (the ad industry's display
 *   rule; GA4 sets none). In a slider or Shop the Look, only the slide or
 *   look that's showing counts. Sent as GA4's view_promotion.
 * - A click: a link inside a promotion, or a product in it (a dot's "View
 *   product", a shop bar or look card). Sent as select_promotion with the
 *   product clicked, or all of the promotion's products otherwise.
 * - Items are the promotion's real products (RAPM_Track::items()); one with
 *   none sends a single item named after the promotion, since GA4 needs one.
 * - Sent one way only: the Google tag (gtag) or Tag Manager (dataLayer),
 *   from Settings, or found on the page. Found neither: nothing is sent.
 */
( function () {
	'use strict';

	var VIEW_MS = 1000;
	var TICK_MS = 250;
	var UNITS   = '.rapm-slide[data-rapm-key], .rapm-pb-item[data-rapm-key], .rapm-ql-item[data-rapm-key], .rapm-rv-panel[data-rapm-key], .rapm-coupon-card[data-rapm-key], .rapm-marquee-tile[data-rapm-key], .rapm-look-photo[data-rapm-key]';
	var ROOTS   = '[data-rapm-carousel], [data-rapm-looks]';

	var watched = []; // { el, key, half, since }
	var sentView = {};
	var io = window.IntersectionObserver ? new IntersectionObserver( onIntersect, { threshold: [ 0, 0.5 ] } ) : null;
	var foundMode = '';

	function cfg() {
		return window.RAPM_Track || { mode: 'auto', data: {} };
	}

	function info( key ) {
		var d = cfg().data || {};
		return d[ key ] || { n: 'rapm-' + key, s: '', k: '', i: [] };
	}

	/* ---- Sending ----------------------------------------------------------- */

	// The Google tag puts a gtag/js?id=G- (or a Google tag GT-) script on the
	// page, also when an optimizer holds it back; Tag Manager puts gtm.js.
	function hasScript( pattern ) {
		var scripts = document.getElementsByTagName( 'script' );
		for ( var i = 0; i < scripts.length; i++ ) {
			var src = scripts[ i ].getAttribute( 'src' ) || scripts[ i ].getAttribute( 'data-rocket-src' ) || scripts[ i ].getAttribute( 'data-src' ) || '';
			if ( pattern.test( src ) ) {
				return true;
			}
		}
		return false;
	}

	function mode() {
		var m = cfg().mode;
		if ( 'gtag' === m || 'gtm' === m ) {
			return m;
		}
		if ( ! foundMode ) {
			if ( 'function' === typeof window.gtag || hasScript( /googletagmanager\.com\/gtag\/js\?id=GT?-/ ) ) {
				foundMode = 'gtag';
			} else if ( window.google_tag_manager || hasScript( /googletagmanager\.com\/gtm\.js/ ) ) {
				foundMode = 'gtm';
			}
		}
		return foundMode;
	}

	function send( name, key, pid ) {
		var p = info( key );
		var items = p.i.filter( function ( it ) {
			return ! pid || it[ 2 ] === String( pid );
		} );
		if ( ! items.length ) {
			items = pid ? p.i : [];
		}
		items = items.length ? items.map( function ( it ) {
			return { item_id: it[ 0 ], item_name: it[ 1 ] };
		} ) : [ { item_id: 'rapm-' + key, item_name: p.n } ];
		var params = {
			promotion_id: 'rapm-' + key,
			promotion_name: p.n,
			creative_name: p.k,
			creative_slot: p.s,
			items: items
		};
		var m = mode();
		window.dataLayer = window.dataLayer || [];
		if ( 'gtag' === m ) {
			// The same as the Google tag's own gtag(): it queues safely even
			// before gtag.js has loaded.
			( function () {
				window.dataLayer.push( arguments );
			}( 'event', name, params ) );
		} else if ( 'gtm' === m ) {
			window.dataLayer.push( { ecommerce: null } );
			window.dataLayer.push( { event: name, ecommerce: params } );
		}
		if ( 'select_promotion' === name && 'function' === typeof window.clarity ) {
			window.clarity( 'event', 'rapm_promotion_click' );
		}
	}

	/* ---- Views ------------------------------------------------------------- */

	function onIntersect( entries ) {
		entries.forEach( function ( e ) {
			for ( var i = 0; i < watched.length; i++ ) {
				if ( watched[ i ].el === e.target ) {
					watched[ i ].half = e.isIntersecting && e.intersectionRatio >= 0.5;
				}
			}
		} );
	}

	// In a slider only the slide on screen counts (fading slides are all
	// "on screen" to the browser); in Shop the Look only the look showing.
	function isShowing( el ) {
		if ( el.classList.contains( 'swiper-slide' ) && el.closest( '.swiper-initialized' ) ) {
			return el.classList.contains( 'swiper-slide-active' );
		}
		if ( el.classList.contains( 'rapm-look-photo' ) ) {
			return el.classList.contains( 'is-active' );
		}
		return true;
	}

	function scan() {
		if ( ! io ) {
			return;
		}
		Array.prototype.forEach.call( document.querySelectorAll( UNITS ), function ( el ) {
			if ( el.rapmTracked ) {
				return;
			}
			el.rapmTracked = true;
			watched.push( { el: el, key: el.getAttribute( 'data-rapm-key' ), half: false, since: 0 } );
			io.observe( el );
		} );
	}

	var ticks = 0;
	function tick() {
		if ( ++ticks % 8 === 0 ) {
			scan(); // Popups and tabs can add promotions later.
		}
		var now = Date.now();
		watched.forEach( function ( w ) {
			if ( sentView[ w.key ] ) {
				return;
			}
			if ( ! w.half || document.hidden || ! isShowing( w.el ) ) {
				w.since = 0;
				return;
			}
			if ( ! w.since ) {
				w.since = now;
			} else if ( now - w.since >= VIEW_MS ) {
				sentView[ w.key ] = true;
				send( 'view_promotion', w.key );
			}
		} );
	}

	/* ---- Clicks ------------------------------------------------------------ */

	// The promotion a click belongs to: the one it's inside, or, for a part
	// of a slider or Shop the Look outside any one promotion, the one showing.
	function keyFor( target ) {
		var item = target.closest( '[data-rapm-key]' );
		if ( item ) {
			return { key: item.getAttribute( 'data-rapm-key' ), scope: item };
		}
		var root = target.closest( ROOTS );
		if ( ! root ) {
			return null;
		}
		var current = root.querySelector( '.swiper-slide-active[data-rapm-key], .rapm-look-photo.is-active[data-rapm-key]' );
		return current ? { key: current.getAttribute( 'data-rapm-key' ), scope: root } : null;
	}

	// The product clicked: a card or dot names it, and a dot's "View product"
	// belongs to the dot that's open.
	function productFor( target, scope ) {
		var named = target.closest( '[data-pid]' );
		if ( named ) {
			return named.getAttribute( 'data-pid' );
		}
		var open = scope.querySelector( '[data-pid][aria-expanded="true"]' );
		return open ? open.getAttribute( 'data-pid' ) : '';
	}

	// Capture phase: the dots stop their clicks before they reach the page.
	document.addEventListener( 'click', function ( e ) {
		var link = e.target.closest ? e.target.closest( 'a[href]' ) : null;
		if ( ! link ) {
			return;
		}
		var found = keyFor( link );
		if ( found ) {
			send( 'select_promotion', found.key, productFor( link, found.scope ) );
		}
	}, true );

	function start() {
		scan();
		window.setInterval( tick, TICK_MS );
	}
	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', start );
	} else {
		start();
	}
}() );
