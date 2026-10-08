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
 *
 * The pieces (1.31.0): each look's numbered dots are placed on the photo as
 * it's actually cropped (cover-fit at the look's "Which part to keep"), so
 * they stay on the furniture at any screen size; a dot cut off by the crop
 * is hidden. A dot opens a small card (photo, name, price, stock, View
 * product), and hovering a dot or a card lights up its partner. Price and
 * stock come from WooCommerce's Store API, read once per page in the
 * visitor's browser, so a full-page cache can't serve stale prices.
 *
 * 1.33.0: a look's optional button on the photo (.rapm-look-photo-btn) is
 * placed by placeDots() with the same cover-fit math, centered on its
 * spot, and nudged inward so the whole button stays on screen at every
 * crop instead of being hidden like a cut-off dot.
 *
 * 1.32.0: with width="full" (class is-full), fullWidth() gives the CSS the
 * page's width without the scrollbar, which 100vw would include and so
 * cause a sideways scroll. With height="screen" (class is-fit), fitHeight() tells the CSS
 * where the section starts on the page and how tall the tab bar is, so the
 * photo plus the bar end at the bottom of the screen. With text="overlay"
 * the words are on each photo for computers and tablets, and the space
 * below holds only the piece cards (class is-bare hides it on those
 * screens for a look without any). Phones show a copy of the words below.
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
		var bar     = root.querySelector( '.rapm-looks-bar' );
		var full    = root.classList.contains( 'is-full' );
		var fit     = root.classList.contains( 'is-fit' );
		var overlay = root.classList.contains( 'is-overlay' );

		var reduceMotion = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
		var stopped      = ! options.autoplay || reduceMotion;
		var hovering     = false;
		var focused      = false;
		var live         = [];
		var tabs         = [];
		var index        = 0;
		var popOpen      = null;
		var pop          = null;
		var products     = {};
		var storeUrl     = root.getAttribute( 'data-rapm-store' ) || '';

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

		// height="screen" (1.32.0): the header, admin bar and anything else
		// above the section, measured from the top of the page, so it's the
		// same wherever the visitor has scrolled to.
		function fitHeight() {
			if ( ! fit || 'none' === root.style.display ) { return; }
			var top = root.getBoundingClientRect().top + ( window.pageYOffset || document.documentElement.scrollTop || 0 );
			root.style.setProperty( '--rapm-looks-top', Math.max( 0, Math.round( top ) ) + 'px' );
			root.style.setProperty( '--rapm-looks-bar', bar.offsetHeight + 'px' );
		}

		function fullWidth() {
			if ( full ) { root.style.setProperty( '--rapm-looks-vw', document.documentElement.clientWidth + 'px' ); }
		}

		function setPaused() {
			root.classList.toggle( 'is-paused', hovering || focused || !! popOpen );
		}

		/* ---- The pieces: dots, product card, live prices (1.31.0) ---- */

		function cardsFor( photo ) {
			var text = textFor( photo );
			return text ? Array.prototype.slice.call( text.querySelectorAll( '.rapm-look-card' ) ) : [];
		}

		// Cover-fit math: where a point of the photo lands in the stage.
		function placeDots() {
			var cw = stage.clientWidth, ch = stage.clientHeight;
			if ( ! cw || ! ch ) { return; }
			photos.forEach( function ( photo ) {
				var img = photo.querySelector( 'img' );
				var w   = parseFloat( photo.getAttribute( 'data-w' ) ) || ( img && img.naturalWidth ) || 0;
				var h   = parseFloat( photo.getAttribute( 'data-h' ) ) || ( img && img.naturalHeight ) || 0;
				if ( ! w || ! h ) { return; }
				var fx = parseFloat( photo.getAttribute( 'data-fx' ) ), fy = parseFloat( photo.getAttribute( 'data-fy' ) );
				fx = isNaN( fx ) ? 0.5 : fx;
				fy = isNaN( fy ) ? 0.5 : fy;
				var scale = Math.max( cw / w, ch / h ), dw = w * scale, dh = h * scale;
				var ox = ( cw - dw ) * fx, oy = ( ch - dh ) * fy, r = 20;
				Array.prototype.forEach.call( photo.querySelectorAll( '.rapm-look-dot' ), function ( dot ) {
					var x = ox + parseFloat( dot.getAttribute( 'data-x' ) ) / 100 * dw;
					var y = oy + parseFloat( dot.getAttribute( 'data-y' ) ) / 100 * dh;
					dot.hidden = x < 4 || x > cw - 4 || y < 4 || y > ch - 4; // cut off by this screen's crop
					dot.style.left = Math.min( Math.max( x, r ), cw - r ) + 'px';
					dot.style.top  = Math.min( Math.max( y, r ), ch - r ) + 'px';
				} );
				var pbtn = photo.querySelector( '.rapm-look-photo-btn' );
				if ( pbtn ) {
					var bx = ox + parseFloat( pbtn.getAttribute( 'data-x' ) ) / 100 * dw;
					var by = oy + parseFloat( pbtn.getAttribute( 'data-y' ) ) / 100 * dh;
					var bw = pbtn.offsetWidth, bh = pbtn.offsetHeight, m = 8;
					pbtn.style.left = Math.min( Math.max( bx - bw / 2, m ), Math.max( m, cw - bw - m ) ) + 'px';
					pbtn.style.top  = Math.min( Math.max( by - bh / 2, m ), Math.max( m, ch - bh - m ) ) + 'px';
				}
			} );
			placePop();
		}

		function esc( text ) {
			var span = document.createElement( 'span' );
			span.textContent = text == null ? '' : String( text );
			return span.innerHTML;
		}

		// Text only: the store's words go through an inert document, never live HTML.
		function plain( html ) {
			return window.DOMParser ? new window.DOMParser().parseFromString( String( html || '' ), 'text/html' ).body.textContent.trim() : '';
		}

		function money( value, p ) {
			var minor = p.currency_minor_unit || 0;
			var n     = ( parseInt( value, 10 ) || 0 ) / Math.pow( 10, minor );
			var parts = n.toFixed( minor ).split( '.' );
			parts[0]  = parts[0].replace( /\B(?=(\d{3})+(?!\d))/g, p.currency_thousand_separator || ',' );
			return esc( ( p.currency_prefix || '' ) + parts.join( p.currency_decimal_separator || '.' ) + ( p.currency_suffix || '' ) );
		}

		// The store's own price, as WooCommerce shows it: a sale shows the old price crossed out.
		function priceHtml( item ) {
			var p = item && item.prices;
			if ( ! p ) { return ''; }
			if ( p.price_range && p.price_range.min_amount !== p.price_range.max_amount ) {
				return money( p.price_range.min_amount, p ) + ' – ' + money( p.price_range.max_amount, p );
			}
			if ( ! parseInt( p.price, 10 ) ) { return ''; }
			var now = money( p.price, p );
			if ( item.on_sale && parseInt( p.regular_price, 10 ) > parseInt( p.price, 10 ) ) {
				return '<del>' + money( p.regular_price, p ) + '</del> <ins>' + now + '</ins>';
			}
			return now;
		}

		function fillCards() {
			Array.prototype.forEach.call( root.querySelectorAll( '.rapm-look-card' ), function ( card ) {
				var item = products[ card.getAttribute( 'data-pid' ) ];
				if ( ! item ) { return; }
				var price = card.querySelector( '.rapm-look-price' );
				var stock = card.querySelector( '.rapm-look-stock' );
				price.innerHTML = priceHtml( item );
				var avail = item.stock_availability || {};
				stock.textContent = plain( avail.text );
				stock.className = 'rapm-look-stock' + ( avail['class'] ? ' ' + String( avail['class'] ).split( ' ' )[0] : '' );
			} );
			if ( popOpen ) { openPop( popOpen, true ); }
		}

		function loadPrices() {
			var ids = {};
			Array.prototype.forEach.call( root.querySelectorAll( '.rapm-look-card' ), function ( card ) {
				ids[ card.getAttribute( 'data-pid' ) ] = true;
			} );
			var list = Object.keys( ids );
			if ( ! storeUrl || ! list.length || ! window.fetch ) { return; }
			var url = storeUrl + ( storeUrl.indexOf( '?' ) === -1 ? '?' : '&' ) + 'include=' + list.join( ',' ) + '&per_page=100';
			window.fetch( url, { credentials: 'same-origin' } )
				.then( function ( r ) { return r.ok ? r.json() : []; } )
				.then( function ( items ) {
					( items || [] ).forEach( function ( item ) { products[ String( item.id ) ] = item; } );
					fillCards();
				} )
				.catch( function () {} ); // No prices is better than a broken look.
		}

		function hot( photo, k, on ) {
			var dot  = photo.querySelector( '.rapm-look-dot[data-k="' + k + '"]' );
			var card = cardsFor( photo ).filter( function ( c ) { return c.getAttribute( 'data-k' ) === String( k ); } )[0];
			if ( dot ) { dot.classList.toggle( 'is-hot', on ); }
			if ( card ) { card.classList.toggle( 'is-hot', on ); }
		}

		function closePop( returnFocus ) {
			if ( ! popOpen ) { return; }
			popOpen.setAttribute( 'aria-expanded', 'false' );
			if ( returnFocus ) { popOpen.focus(); }
			popOpen = null;
			pop.hidden = true;
			setPaused();
		}

		function openPop( dot, refresh ) {
			if ( popOpen === dot && ! refresh ) { closePop( false ); return; }
			if ( ! refresh ) { closePop( false ); }
			var photo = dot.closest( '.rapm-look-photo' );
			var card  = cardsFor( photo ).filter( function ( c ) { return c.getAttribute( 'data-k' ) === dot.getAttribute( 'data-k' ); } )[0];
			if ( ! card ) { return; }
			if ( ! pop ) {
				pop = document.createElement( 'div' );
				pop.className = 'rapm-look-pop';
				pop.setAttribute( 'role', 'dialog' );
				pop.hidden = true;
				stage.appendChild( pop );
				pop.addEventListener( 'click', function ( e ) {
					if ( e.target.closest( '.rapm-look-pop-x' ) ) { closePop( true ); }
				} );
			}
			var name  = card.querySelector( '.rapm-look-card-name' );
			var img   = card.querySelector( '.rapm-look-card-img img' );
			var cardStock = card.querySelector( '.rapm-look-stock' );
			pop.setAttribute( 'aria-label', name.textContent );
			pop.innerHTML = '<span class="rapm-look-pop-img"></span><div class="rapm-look-pop-meta"><p class="rapm-look-pop-name"></p>' +
				'<span class="rapm-look-price"></span><span></span><a class="rapm-look-view"></a></div>' +
				'<button type="button" class="rapm-look-pop-x">&times;</button>';
			if ( img ) {
				var copy = document.createElement( 'img' );
				copy.src = img.getAttribute( 'src' );
				copy.alt = '';
				pop.querySelector( '.rapm-look-pop-img' ).appendChild( copy );
			}
			var meta = pop.querySelector( '.rapm-look-pop-meta' );
			meta.querySelector( '.rapm-look-pop-name' ).textContent = name.textContent;
			meta.querySelector( '.rapm-look-price' ).innerHTML = card.querySelector( '.rapm-look-price' ).innerHTML; // built by priceHtml()
			meta.children[2].className = cardStock.className;
			meta.children[2].textContent = cardStock.textContent;
			meta.querySelector( '.rapm-look-view' ).textContent = T.view || 'View product';
			meta.querySelector( '.rapm-look-view' ).setAttribute( 'href', name.getAttribute( 'href' ) );
			pop.querySelector( '.rapm-look-pop-x' ).setAttribute( 'aria-label', T.close || 'Close' );
			pop.hidden = false;
			popOpen = dot;
			dot.setAttribute( 'aria-expanded', 'true' );
			setPaused();
			placePop();
		}

		function placePop() {
			if ( ! popOpen || ! pop ) { return; }
			var cw = stage.clientWidth, ch = stage.clientHeight;
			var x = parseFloat( popOpen.style.left ), y = parseFloat( popOpen.style.top );
			var pw = pop.offsetWidth, ph = pop.offsetHeight, gap = 26, m = 8, left, top;
			if ( x + gap + pw <= cw - m ) { left = x + gap; top = y - ph / 2; }
			else if ( x - gap - pw >= m ) { left = x - gap - pw; top = y - ph / 2; }
			else {
				left = Math.min( Math.max( x - pw / 2, m ), cw - pw - m );
				top  = y + gap + ph <= ch - m ? y + gap : y - gap - ph;
			}
			pop.style.left = left + 'px';
			pop.style.top  = Math.min( Math.max( top, m ), ch - ph - m ) + 'px';
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
			closePop( false );
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
			// Nothing to show below on computers: no cards and no words below
			// (1.35.0: a look with a photo button keeps its words below).
			root.classList.toggle( 'is-bare', overlay && ! ( text && ( text.querySelector( '.rapm-look-card' ) || text.querySelector( '.rapm-look-intro:not(.is-phone)' ) ) ) );
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
			fullWidth();
			updateMore();
			fitHeight();
			placeDots();
		}

		stage.addEventListener( 'click', function ( e ) {
			var dot = e.target.closest( '.rapm-look-dot' );
			if ( dot ) { openPop( dot, false ); }
		} );
		document.addEventListener( 'click', function ( e ) {
			if ( popOpen && ! stage.contains( e.target ) ) { closePop( false ); }
		} );
		root.addEventListener( 'keydown', function ( e ) {
			if ( 'Escape' === e.key && popOpen ) { closePop( true ); }
		} );
		root.addEventListener( 'mouseover', function ( e ) {
			var el = e.target.closest( '.rapm-look-dot, .rapm-look-card' );
			if ( el && live[ index ] ) { hot( live[ index ], el.getAttribute( 'data-k' ), true ); }
		} );
		root.addEventListener( 'mouseout', function ( e ) {
			var el = e.target.closest( '.rapm-look-dot, .rapm-look-card' );
			if ( el && live[ index ] ) { hot( live[ index ], el.getAttribute( 'data-k' ), false ); }
		} );
		photos.forEach( function ( photo ) {
			var img = photo.querySelector( 'img' );
			if ( img && ! img.complete ) { img.addEventListener( 'load', placeDots ); }
		} );
		if ( window.ResizeObserver ) { new window.ResizeObserver( placeDots ).observe( stage ); }
		window.addEventListener( 'resize', placeDots );
		if ( full ) { window.addEventListener( 'resize', fullWidth ); }
		if ( fit ) {
			window.addEventListener( 'resize', fitHeight );
			window.addEventListener( 'load', fitHeight ); // the header's logo and fonts can change its height
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
			if ( 'mouse' === e.pointerType || ( e.target.closest && e.target.closest( '.rapm-looks-arrows, .rapm-look-dot, .rapm-look-pop, .rapm-look-photo-btn' ) ) ) { startX = null; return; }
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
		loadPrices();
	}

	window.RAPM_Looks = { init: init };
} )( window );
