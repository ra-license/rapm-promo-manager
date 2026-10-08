/**
 * Product dots and the "Shop now" button on Slider and Feature banner
 * slides (1.35.0). RAPM_Hero_Carousel prints them inside each slide with
 * their spots as % of the picture: data-x/data-y on the desktop picture,
 * data-mx/data-my on the phone one (the desktop spot when there isn't one).
 *
 * - Placed with the picture's real fit (object-fit and object-position as
 *   the browser applies them, so cover and the phone fallback's contain
 *   both work), again on resize and whenever Swiper adds slide copies.
 * - A dot cut off by the crop is hidden; the button is nudged inward so
 *   it's always wholly on the picture.
 * - A dot opens a small card: picture, name, live price and stock (one
 *   WooCommerce Store API request per carousel), and "View product".
 * - Dots and the card stop the slide's own link from firing.
 */
( function ( window, document ) {
	'use strict';

	var PHONE = '(max-width: 768px)';

	function onPhone() { return window.matchMedia && window.matchMedia( PHONE ).matches; }

	// Where the picture actually sits inside its box, from its computed fit.
	function fit( img, box ) {
		var cw = box.clientWidth, ch = box.clientHeight, iw = img.naturalWidth, ih = img.naturalHeight;
		if ( ! cw || ! ch || ! iw || ! ih ) { return null; }
		var cs = window.getComputedStyle( img );
		var scale = 'contain' === cs.objectFit ? Math.min( cw / iw, ch / ih ) : Math.max( cw / iw, ch / ih );
		var dw = iw * scale, dh = ih * scale;
		var pos = ( cs.objectPosition || '50% 50%' ).split( ' ' );
		function frac( v ) { var n = parseFloat( v ); return /%$/.test( v ) ? n / 100 : ( isNaN( n ) ? 0.5 : 0.5 ); }
		return { cw: cw, ch: ch, dw: dw, dh: dh, ox: ( cw - dw ) * frac( pos[0] ), oy: ( ch - dh ) * frac( pos[1] || '50%' ) };
	}

	function spot( el, slide ) {
		var phone = onPhone() && slide.classList.contains( 'has-mobile-img' );
		var x = phone && el.hasAttribute( 'data-mx' ) ? el.getAttribute( 'data-mx' ) : el.getAttribute( 'data-x' );
		var y = phone && el.hasAttribute( 'data-my' ) ? el.getAttribute( 'data-my' ) : el.getAttribute( 'data-y' );
		return [ parseFloat( x ), parseFloat( y ) ];
	}

	function placeSlide( slide ) {
		var img = slide.querySelector( '.rapm-slide-img' );
		if ( ! img || ! slide.querySelector( '.rapm-pin-dot, .rapm-pin-btn' ) ) { return; }
		var f = fit( img, slide );
		if ( ! f ) { return; }
		Array.prototype.forEach.call( slide.querySelectorAll( '.rapm-pin-dot' ), function ( dot ) {
			var s = spot( dot, slide ), x = f.ox + s[0] / 100 * f.dw, y = f.oy + s[1] / 100 * f.dh, r = 18;
			dot.hidden = x < 4 || x > f.cw - 4 || y < 4 || y > f.ch - 4;
			dot.style.left = Math.min( Math.max( x, r ), f.cw - r ) + 'px';
			dot.style.top  = Math.min( Math.max( y, r ), f.ch - r ) + 'px';
		} );
		var btn = slide.querySelector( '.rapm-pin-btn' );
		if ( btn ) {
			var s2 = spot( btn, slide ), bw = btn.offsetWidth, bh = btn.offsetHeight, m = 8;
			btn.style.left = Math.min( Math.max( f.ox + s2[0] / 100 * f.dw - bw / 2, m ), Math.max( m, f.cw - bw - m ) ) + 'px';
			btn.style.top  = Math.min( Math.max( f.oy + s2[1] / 100 * f.dh - bh / 2, m ), Math.max( m, f.ch - bh - m ) ) + 'px';
		}
	}

	function esc( text ) { var s = document.createElement( 'span' ); s.textContent = text == null ? '' : String( text ); return s.innerHTML; }
	function plain( html ) { return window.DOMParser ? new window.DOMParser().parseFromString( String( html || '' ), 'text/html' ).body.textContent.trim() : ''; }
	function money( value, p ) {
		var minor = p.currency_minor_unit || 0, n = ( parseInt( value, 10 ) || 0 ) / Math.pow( 10, minor ), parts = n.toFixed( minor ).split( '.' );
		parts[0] = parts[0].replace( /\B(?=(\d{3})+(?!\d))/g, p.currency_thousand_separator || ',' );
		return esc( ( p.currency_prefix || '' ) + parts.join( p.currency_decimal_separator || '.' ) + ( p.currency_suffix || '' ) );
	}
	function priceHtml( item ) {
		var p = item && item.prices;
		if ( ! p ) { return ''; }
		if ( p.price_range && p.price_range.min_amount !== p.price_range.max_amount ) { return money( p.price_range.min_amount, p ) + ' – ' + money( p.price_range.max_amount, p ); }
		if ( ! parseInt( p.price, 10 ) ) { return ''; }
		var now = money( p.price, p );
		return item.on_sale && parseInt( p.regular_price, 10 ) > parseInt( p.price, 10 ) ? '<del>' + money( p.regular_price, p ) + '</del> <ins>' + now + '</ins>' : now;
	}

	function init( root ) {
		if ( ! root || root.getAttribute( 'data-rapm-pins-ready' ) ) { return; }
		root.setAttribute( 'data-rapm-pins-ready', '1' );
		var products = {}, open = null, pop = null;
		var T = { view: root.getAttribute( 'data-rapm-pins-view' ) || 'View product', close: root.getAttribute( 'data-rapm-pins-close' ) || 'Close' };

		function placeAll() { Array.prototype.forEach.call( root.querySelectorAll( '.rapm-slide' ), placeSlide ); if ( open ) { placePop(); } }

		function closePop( focusDot ) {
			if ( ! open ) { return; }
			open.setAttribute( 'aria-expanded', 'false' );
			if ( focusDot ) { open.focus(); }
			open = null;
			if ( pop ) { pop.hidden = true; }
		}
		function placePop() {
			var slide = open.closest( '.rapm-slide' ), cw = slide.clientWidth, ch = slide.clientHeight;
			var x = parseFloat( open.style.left ), y = parseFloat( open.style.top ), pw = pop.offsetWidth, ph = pop.offsetHeight, gap = 24, m = 8, left, top;
			if ( x + gap + pw <= cw - m ) { left = x + gap; top = y - ph / 2; }
			else if ( x - gap - pw >= m ) { left = x - gap - pw; top = y - ph / 2; }
			else { left = Math.min( Math.max( x - pw / 2, m ), cw - pw - m ); top = y + gap + ph <= ch - m ? y + gap : y - gap - ph; }
			pop.style.left = left + 'px';
			pop.style.top  = Math.min( Math.max( top, m ), ch - ph - m ) + 'px';
		}
		function openPop( dot ) {
			if ( open === dot ) { closePop( false ); return; }
			closePop( false );
			var slide = dot.closest( '.rapm-slide' );
			if ( ! pop || pop.parentNode !== slide ) {
				if ( pop ) { pop.remove(); }
				pop = document.createElement( 'div' );
				pop.className = 'rapm-pin-pop';
				pop.setAttribute( 'role', 'dialog' );
				slide.appendChild( pop );
			}
			var item = products[ dot.getAttribute( 'data-pid' ) ] || {}, avail = item.stock_availability || {};
			pop.setAttribute( 'aria-label', dot.getAttribute( 'data-name' ) );
			pop.innerHTML = ( dot.getAttribute( 'data-thumb' ) ? '<img alt="" src="' + esc( dot.getAttribute( 'data-thumb' ) ) + '">' : '<span></span>' ) +
				'<div class="rapm-pin-pop-meta"><p class="rapm-pin-pop-name">' + esc( dot.getAttribute( 'data-name' ) ) + '</p>' +
				'<span class="rapm-pin-pop-price">' + priceHtml( item ) + '</span>' +
				'<span class="rapm-pin-pop-stock ' + esc( avail['class'] ? String( avail['class'] ).split( ' ' )[0] : '' ) + '">' + esc( plain( avail.text ) ) + '</span>' +
				'<a class="rapm-pin-pop-view" href="' + esc( dot.getAttribute( 'data-link' ) ) + '">' + esc( T.view ) + '</a></div>' +
				'<button type="button" class="rapm-pin-pop-x" aria-label="' + esc( T.close ) + '">&times;</button>';
			pop.hidden = false;
			open = dot;
			dot.setAttribute( 'aria-expanded', 'true' );
			placePop();
		}

		// Dots and the card sit on top of the slide's own link: keep their clicks to themselves.
		root.addEventListener( 'click', function ( e ) {
			var dot = e.target.closest( '.rapm-pin-dot' );
			if ( dot ) { e.preventDefault(); e.stopPropagation(); openPop( dot ); return; }
			if ( e.target.closest( '.rapm-pin-pop-x' ) ) { e.preventDefault(); closePop( true ); return; }
			if ( e.target.closest( '.rapm-pin-pop' ) ) { e.stopPropagation(); return; }
			if ( open ) { closePop( false ); }
		}, true );
		root.addEventListener( 'keydown', function ( e ) { if ( 'Escape' === e.key && open ) { closePop( true ); } } );
		document.addEventListener( 'click', function ( e ) { if ( open && ! root.contains( e.target ) ) { closePop( false ); } } );

		// Live prices and stock, once per carousel.
		var store = root.getAttribute( 'data-rapm-store' ), ids = {};
		Array.prototype.forEach.call( root.querySelectorAll( '.rapm-pin-dot' ), function ( d ) { ids[ d.getAttribute( 'data-pid' ) ] = true; } );
		var list = Object.keys( ids );
		if ( store && list.length && window.fetch ) {
			window.fetch( store + ( store.indexOf( '?' ) === -1 ? '?' : '&' ) + 'include=' + list.join( ',' ) + '&per_page=100', { credentials: 'same-origin' } )
				.then( function ( r ) { return r.ok ? r.json() : []; } )
				.then( function ( items ) { ( items || [] ).forEach( function ( it ) { products[ String( it.id ) ] = it; } ); if ( open ) { var d = open; open = null; openPop( d ); } } )
				.catch( function () {} );
		}

		placeAll();
		Array.prototype.forEach.call( root.querySelectorAll( '.rapm-slide-img' ), function ( img ) { if ( ! img.complete ) { img.addEventListener( 'load', placeAll ); } } );
		window.addEventListener( 'resize', placeAll );
		window.addEventListener( 'load', placeAll );
		if ( window.ResizeObserver ) { new window.ResizeObserver( placeAll ).observe( root ); }
		// Swiper adds slide copies in loop mode and shows the carousel after the schedule check.
		if ( window.MutationObserver ) {
			var t = null;
			var mo = new window.MutationObserver( function () { clearTimeout( t ); t = setTimeout( placeAll, 30 ); } );
			mo.observe( root, { childList: true, subtree: true } ); // slide copies (placing never adds nodes)
			mo.observe( root, { attributes: true, attributeFilter: [ 'style' ] } ); // shown after the schedule check
		}
	}

	function initAll() { Array.prototype.forEach.call( document.querySelectorAll( '[data-rapm-pins]' ), init ); }
	window.RAPM_Pins = { init: init, initAll: initAll };
	if ( 'loading' === document.readyState ) { document.addEventListener( 'DOMContentLoaded', initAll ); } else { initAll(); }
} )( window, document );
