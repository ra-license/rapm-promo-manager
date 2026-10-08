/**
 * Shop the Look's form (RAPM_Upload_Handler, 1.31.0), ported from the editor
 * mockup Phil approved on 2026-10-06:
 * - Step 1: the room photo's size note and "Use a link" check.
 * - Place the Pieces: click the photo to add a numbered dot, drag it (or
 *   use the arrow keys) to move it, and pick its product by name or SKU.
 *   "Which part to keep" sets the crop, and the Computer and Phone previews
 *   warn when a dot is cut off.
 * - Review & Schedule: the look as shoppers see it, with live prices.
 *
 * Settings and words come from window.RAPM_LookEditorConfig (printed by
 * RAPM_Upload_Handler::look_editor_config()). Products are searched and
 * priced through WooCommerce's Store API, the same source the website uses.
 * The dots are saved as JSON in the hidden #rapm_dots field:
 * [{ p: product ID, x: 0-100, y: 0-100 }], positions as % of the photo.
 *
 * 1.33.0: the optional button on the photo ("Show a Shop now button on the
 * photo"), dragged or moved with the arrow keys like a dot, saved in the
 * hidden #rapm_photo_btn field as { on: true, x, y } (its center, % of the
 * photo), or {} when it's off. Its words are the look's Button Text.
 */
( function () {
	'use strict';

	var C = window.RAPM_LookEditorConfig;
	if ( ! C ) { return; }
	var T = C.text || {};

	var WIDE = 2.6, PHONE = 4 / 3, MARGIN = 0.02;
	var ANCHOR = { left: 0, top: 0, center: 0.5, right: 1, bottom: 1 };

	function $( id ) { return document.getElementById( id ); }
	function fmt( text ) {
		var args = Array.prototype.slice.call( arguments, 1 );
		var out  = String( text || '' );
		args.forEach( function ( a, i ) {
			out = out.split( '%' + ( i + 1 ) + '$d' ).join( a ).split( '%' + ( i + 1 ) + '$s' ).join( a );
		} );
		return out.replace( '%d', args[0] ).replace( '%s', args[0] );
	}
	function plain( html ) {
		return window.DOMParser ? new window.DOMParser().parseFromString( String( html || '' ), 'text/html' ).body.textContent.trim() : '';
	}
	function el( tag, cls, text ) {
		var node = document.createElement( tag );
		if ( cls ) { node.className = cls; }
		if ( null != text ) { node.textContent = text; }
		return node;
	}
	function live( message ) {
		var box = $( 'rapm-pieces-live' );
		if ( ! box ) {
			box = el( 'p', 'screen-reader-text' );
			box.id = 'rapm-pieces-live';
			box.setAttribute( 'aria-live', 'polite' );
			document.body.appendChild( box );
		}
		box.textContent = '';
		setTimeout( function () { box.textContent = message; }, 30 );
	}

	/* ---- State ------------------------------------------------------------ */

	// 1.35.0: Slider and Feature banner promotions (C.mode 'banner') use the
	// same Place the Pieces on two pictures, desktop and phone. A dot or the
	// button keeps x, y for the desktop picture and mx, my for the phone
	// one; until it's moved on the phone picture it sits at its desktop spot.
	var BANNER = 'banner' === C.mode;
	var photos = {
		desktop: { src: C.src || '', w: C.width || 0, h: C.height || 0 },
		mobile: C.mobile ? { src: C.mobile.src || '', w: C.mobile.width || 0, h: C.mobile.height || 0 } : { src: '', w: 0, h: 0 }
	};
	var view = 'desktop';
	function onPhone() { return BANNER && 'mobile' === view && !! photos.mobile.src; }
	function gx( o ) { return onPhone() && null != o.mx ? o.mx : o.x; }
	function gy( o ) { return onPhone() && null != o.my ? o.my : o.y; }
	function sxy( o, x, y ) {
		if ( onPhone() ) { o.mx = x; o.my = y; } else { o.x = x; o.y = y; }
	}

	var state = {
		src: C.src || '',
		w: C.width || 0,
		h: C.height || 0,
		focus: C.focus || 'center center',
		dots: ( C.dots || [] ).map( function ( d ) { var o = { p: d.p, x: d.x, y: d.y }; if ( null != d.mx ) { o.mx = d.mx; o.my = d.my; } return o; } ),
		btn: C.photoBtn && C.photoBtn.on ? { on: true, x: C.photoBtn.x, y: C.photoBtn.y, mx: C.photoBtn.mx, my: C.photoBtn.my } : { on: false, x: 50, y: 78 },
		selected: -1
	};
	var products = {};
	Object.keys( C.products || {} ).forEach( function ( id ) { products[ id ] = C.products[ id ]; } );

	function focusXY() {
		var parts = state.focus.split( ' ' );
		return [ ANCHOR[ parts[0] ] !== undefined ? ANCHOR[ parts[0] ] : 0.5, ANCHOR[ parts[1] ] !== undefined ? ANCHOR[ parts[1] ] : 0.5 ];
	}

	// The part of the photo (0-1 on each axis) a frame of this shape shows.
	function cropBox( aspect ) {
		var f = focusXY(), ia = state.w / state.h;
		if ( aspect >= ia ) {
			var vh = ia / aspect, y0 = ( 1 - vh ) * f[1];
			return { x0: 0, x1: 1, y0: y0, y1: y0 + vh };
		}
		var vw = aspect / ia, x0 = ( 1 - vw ) * f[0];
		return { x0: x0, x1: x0 + vw, y0: 0, y1: 1 };
	}

	function cutOff() {
		var out = {};
		if ( BANNER ) { return out; } // A banner's pictures are shown whole, made for their screens.
		if ( ! state.src || ! state.w || ! state.h ) { return out; }
		var bw = cropBox( WIDE ), bp = cropBox( PHONE );
		state.dots.forEach( function ( d, k ) {
			var x = d.x / 100, y = d.y / 100;
			var wide  = x < bw.x0 + MARGIN || x > bw.x1 - MARGIN || y < bw.y0 + MARGIN || y > bw.y1 - MARGIN;
			var phone = x < bp.x0 + MARGIN || x > bp.x1 - MARGIN || y < bp.y0 + MARGIN || y > bp.y1 - MARGIN;
			if ( wide || phone ) { out[ k ] = wide && phone ? T.cutBoth : ( wide ? T.cutComputers : T.cutPhones ); }
		} );
		return out;
	}

	function sync() {
		var field = $( 'rapm_dots' );
		if ( field ) {
			field.value = JSON.stringify( state.dots.map( function ( d ) {
				var o = { p: d.p || 0, x: d.x, y: d.y };
				if ( null != d.mx ) { o.mx = d.mx; o.my = d.my; }
				return o;
			} ) );
		}
		var focusField = $( 'rapm_focus' );
		if ( focusField ) { focusField.value = state.focus; }
		var btnField = $( 'rapm_photo_btn' );
		if ( btnField ) {
			var bo = state.btn.on ? { on: true, x: state.btn.x, y: state.btn.y } : {};
			if ( state.btn.on && null != state.btn.mx ) { bo.mx = state.btn.mx; bo.my = state.btn.my; }
			btnField.value = JSON.stringify( bo );
		}
	}

	function btnText() {
		var field = $( 'rapm_cta_text' ), v = field ? field.value.trim() : '';
		return v || T.btnDefault || 'Shop now';
	}

	/* ---- Store API: search and live prices ---------------------------------- */

	function money( value, p ) {
		var minor = p.currency_minor_unit || 0;
		var n     = ( parseInt( value, 10 ) || 0 ) / Math.pow( 10, minor );
		var parts = n.toFixed( minor ).split( '.' );
		parts[0]  = parts[0].replace( /\B(?=(\d{3})+(?!\d))/g, p.currency_thousand_separator || ',' );
		return ( p.currency_prefix || '' ) + parts.join( p.currency_decimal_separator || '.' ) + ( p.currency_suffix || '' );
	}

	function remember( item ) {
		var p = item.prices || {}, price = '';
		if ( p.price_range && p.price_range.min_amount !== p.price_range.max_amount ) {
			price = money( p.price_range.min_amount, p ) + ' – ' + money( p.price_range.max_amount, p );
		} else if ( parseInt( p.price, 10 ) ) {
			price = money( p.price, p );
		}
		var avail = item.stock_availability || {};
		var old   = products[ item.id ] || {};
		products[ item.id ] = {
			name: plain( item.name ),
			sku: item.sku || '',
			thumb: item.images && item.images[0] ? ( item.images[0].thumbnail || item.images[0].src ) : ( old.thumb || '' ),
			link: item.permalink || old.link || '',
			price: price,
			regular: item.on_sale && parseInt( p.regular_price, 10 ) > parseInt( p.price, 10 ) ? money( p.regular_price, p ) : '',
			stock: plain( avail.text ),
			stockClass: avail['class'] ? String( avail['class'] ).split( ' ' )[0] : ''
		};
	}

	function store( query ) {
		if ( ! C.store || ! window.fetch ) { return Promise.reject( new Error( 'no store' ) ); }
		var url = C.store + ( C.store.indexOf( '?' ) === -1 ? '?' : '&' ) + query;
		return window.fetch( url, { credentials: 'same-origin' } ).then( function ( r ) {
			if ( ! r.ok ) { throw new Error( 'store ' + r.status ); }
			var total = parseInt( r.headers.get( 'X-WP-Total' ), 10 );
			return r.json().then( function ( items ) { return { items: items || [], total: isNaN( total ) ? ( items || [] ).length : total }; } );
		} );
	}

	// Some stores only find the words in the order they're typed ("Console
	// Loveseat" works, "Apple Cider Loveseat" doesn't). So when a search of
	// several words finds nothing, look up the word with the fewest products
	// and keep the ones whose name or SKU has every word.
	function wordSearch( q ) {
		var words = [];
		q.toLowerCase().split( /\s+/ ).forEach( function ( w ) {
			w = w.replace( /^[.,;:!?"'()\[\]–—-]+|[.,;:!?"'()\[\]–—-]+$/g, '' );
			if ( w.length > 1 && words.indexOf( w ) === -1 ) { words.push( w ); }
		} );
		if ( words.length < 2 ) { return Promise.resolve( { items: [], total: 0 } ); }
		words = words.slice( 0, 5 );
		return Promise.all( words.map( function ( w ) {
			return store( 'search=' + encodeURIComponent( w ) + '&per_page=1' );
		} ) ).then( function ( counts ) {
			var best = 0;
			counts.forEach( function ( c, i ) { if ( c.total < counts[ best ].total ) { best = i; } } );
			if ( ! counts[ best ].total ) { return { items: [], total: 0 }; }
			return store( 'search=' + encodeURIComponent( words[ best ] ) + '&per_page=100' ).then( function ( res ) {
				var items = res.items.filter( function ( item ) {
					var hay = ( plain( item.name ) + ' ' + ( item.sku || '' ) ).toLowerCase();
					return words.every( function ( w ) { return hay.indexOf( w ) > -1; } );
				} );
				return { items: items, total: items.length };
			} );
		} );
	}

	function loadPrices() {
		var ids = state.dots.map( function ( d ) { return d.p; } ).filter( Boolean );
		if ( ! ids.length ) { return; }
		store( 'include=' + ids.join( ',' ) + '&per_page=100' ).then( function ( res ) {
			res.items.forEach( remember );
			renderList();
			renderPreview();
		} ).catch( function () {} );
	}

	/* ---- Step 1: photo -------------------------------------------------------- */

	var sizeNote = $( 'rapm-look-size-note' );

	function sizeText() {
		if ( ! sizeNote ) { return; }
		sizeNote.className = 'description rapm-look-size-note';
		var w = state.w;
		if ( ! w || ! state.src ) { sizeNote.textContent = ''; return; }
		if ( w < C.minWidth ) {
			sizeNote.classList.add( 'is-bad' );
			sizeNote.textContent = fmt( T.sizeBad, w, C.minWidth );
		} else if ( w < 1200 ) {
			sizeNote.classList.add( 'is-warn' );
			sizeNote.textContent = fmt( T.sizeBlurry, w );
		} else if ( w < 2000 ) {
			sizeNote.classList.add( 'is-warn' );
			sizeNote.textContent = fmt( T.sizeSoft, w );
		} else {
			sizeNote.classList.add( 'is-ok' );
			sizeNote.textContent = w > C.maxWidth ? fmt( T.sizeBig, w, C.maxWidth ) : fmt( T.sizeOk, w );
		}
	}

	function setPhoto( src, w, h, which ) {
		which = which || 'desktop';
		photos[ which ] = { src: src, w: w, h: h };
		if ( ! BANNER || which === view || ( 'mobile' === view && ! photos.mobile.src ) ) {
			showView( view );
		}
		sizeText();
		renderAll();
	}

	// Which picture Place the Pieces shows: a look's one photo, or a banner's
	// desktop or phone picture (the phone one falls back to the desktop one).
	function showView( v ) {
		view = v;
		var p = onPhone() ? photos.mobile : photos.desktop;
		state.src = p.src;
		state.w   = p.w;
		state.h   = p.h;
		var stageEl = $( 'rapm-pieces-stage' );
		if ( stageEl ) { stageEl.classList.toggle( 'is-phone', onPhone() ); } // A tall phone picture stays phone-sized.
		var note = $( 'rapm-pieces-view-note' );
		if ( note ) { note.textContent = BANNER && 'mobile' === v && ! photos.mobile.src ? ( T.noMobile || '' ) : ''; }
	}
	showView( 'desktop' );

	[ 'desktop', 'mobile' ].forEach( function ( which ) {
		var file = $( 'rapm_image_' + which );
		if ( ! file || ( 'mobile' === which && ! BANNER ) ) { return; }
		file.addEventListener( 'change', function () {
			if ( ! this.files || ! this.files[0] ) { return; }
			var url = URL.createObjectURL( this.files[0] ), probe = new Image();
			probe.onload = function () { setPhoto( url, probe.naturalWidth, probe.naturalHeight, which ); };
			probe.src = url;
		} );
	} );

	Array.prototype.forEach.call( document.querySelectorAll( '.rapm-pieces-view' ), function ( b ) {
		b.addEventListener( 'click', function () {
			Array.prototype.forEach.call( document.querySelectorAll( '.rapm-pieces-view' ), function ( x ) {
				var on = x === b;
				x.classList.toggle( 'is-selected', on );
				x.setAttribute( 'aria-pressed', on ? 'true' : 'false' );
			} );
			showView( b.getAttribute( 'data-view' ) );
			renderAll();
		} );
	} );

	// "Use a link": ask the server which picture the link really is.
	var urlInput = $( 'rapm_image_desktop_url' ), linkStatus = $( 'rapm-desktop-link-status' ), linkTimer = null, linkSeq = 0;
	function showLink( text, isError ) {
		if ( ! linkStatus ) { return; }
		linkStatus.textContent = text;
		linkStatus.style.color = isError ? '#b32d2e' : '';
	}
	function checkLink() {
		if ( BANNER ) { return; } // A banner's link check is step 1's own; its picture shows here once saved.
		var url = urlInput.value.trim(), mine = ++linkSeq;
		if ( ! /^https?:\/\/\S+$/i.test( url ) ) { showLink( '', false ); return; }
		showLink( T.checking, false );
		var body = new FormData();
		body.append( 'action', 'rapm_preview_link' );
		body.append( 'nonce', C.linkNonce );
		body.append( 'url', url );
		body.append( 'which', 'desktop' );
		body.append( 'kind', 'look' );
		var assetField = document.querySelector( 'input[name="asset_id"]' );
		body.append( 'asset_id', assetField ? assetField.value : '0' );
		window.fetch( C.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' } )
			.then( function ( r ) { return r.json(); } )
			.then( function ( res ) {
				if ( mine !== linkSeq ) { return; }
				if ( ! res || ! res.success ) {
					showLink( ( res && res.data && res.data.message ) || T.linkFailed, true );
					return;
				}
				showLink( fmt( res.data.is_folder ? T.foundFolder : T.found, res.data.name || url ), false );
				setPhoto( res.data.src, res.data.width, res.data.height );
			} )
			.catch( function () { if ( mine === linkSeq ) { showLink( T.linkFailed, true ); } } );
	}
	if ( urlInput ) {
		urlInput.addEventListener( 'input', function () {
			clearTimeout( linkTimer );
			linkTimer = setTimeout( checkLink, 700 );
		} );
	}

	/* ---- Place the Pieces: the photo --------------------------------------------- */

	var stage = $( 'rapm-pieces-stage' ), stageImg = stage ? stage.querySelector( 'img' ) : null;

	function renderStage() {
		if ( ! stage ) { return; }
		var has = !! state.src;
		stage.hidden = ! has;
		$( 'rapm-pieces-empty' ).hidden = has;
		if ( ! has ) { return; }
		if ( stageImg.getAttribute( 'src' ) !== state.src ) { stageImg.setAttribute( 'src', state.src ); }
		Array.prototype.forEach.call( stage.querySelectorAll( '.rapm-pieces-dot, .rapm-pieces-btn' ), function ( d ) { d.remove(); } );
		if ( state.btn.on ) {
			var pb = el( 'button', 'rapm-pieces-btn', btnText() );
			pb.type = 'button';
			pb.style.left = gx( state.btn ) + '%';
			pb.style.top  = gy( state.btn ) + '%';
			pb.setAttribute( 'aria-label', T.btnLabel || '' );
			stage.appendChild( pb );
		}
		state.dots.forEach( function ( d, k ) {
			var b = el( 'button', 'rapm-pieces-dot' + ( k === state.selected ? ' is-selected' : '' ) + ( d.p ? ( products[ d.p ] ? '' : ' is-gone' ) : ' is-empty' ), String( k + 1 ) );
			b.type = 'button';
			b.style.left = gx( d ) + '%';
			b.style.top  = gy( d ) + '%';
			b.setAttribute( 'data-k', k );
			b.setAttribute( 'aria-label', d.p && products[ d.p ] ? fmt( T.dotLabel, k + 1, products[ d.p ].name ) : fmt( T.dotEmpty, k + 1 ) );
			stage.appendChild( b );
		} );
	}

	function addDot( x, y ) {
		if ( state.dots.length >= C.maxDots ) {
			live( fmt( T.tooMany, C.maxDots ) );
			warn( fmt( T.tooMany, C.maxDots ) );
			return false;
		}
		var nd = { p: 0, x: x, y: y };
		if ( onPhone() ) { nd.mx = x; nd.my = y; } // Added on the phone picture: same spot to start on the desktop one.
		state.dots.push( nd );
		state.selected = state.dots.length - 1;
		live( fmt( T.added, state.dots.length ) );
		renderAll();
		var search = $( 'rapm-pieces-search-' + state.selected );
		if ( search ) { search.focus(); }
		return true;
	}

	if ( stage ) {
		var drag = null;
		stage.addEventListener( 'pointerdown', function ( e ) {
			var pbtn = e.target.closest( '.rapm-pieces-btn' );
			if ( pbtn ) {
				drag = { btn: true, el: pbtn };
				try { pbtn.setPointerCapture( e.pointerId ); } catch ( err ) {} // Keeps working without capture.
				pbtn.classList.add( 'is-dragging' );
				e.preventDefault();
				return;
			}
			if ( ! C.store ) { return; }
			var dot = e.target.closest( '.rapm-pieces-dot' );
			var box = stage.getBoundingClientRect();
			if ( dot ) {
				drag = { k: parseInt( dot.getAttribute( 'data-k' ), 10 ), el: dot, sx: e.clientX, sy: e.clientY, moved: false };
				try { dot.setPointerCapture( e.pointerId ); } catch ( err ) {} // Keeps working without capture.
				dot.classList.add( 'is-dragging' );
				e.preventDefault();
				return;
			}
			addDot( Math.round( ( e.clientX - box.left ) / box.width * 1000 ) / 10, Math.round( ( e.clientY - box.top ) / box.height * 1000 ) / 10 );
		} );
		// Clicking the photo itself would move focus to nowhere right after
		// addDot() put it in the new dot's search box, so keep it where it is.
		stage.addEventListener( 'mousedown', function ( e ) {
			if ( ! e.target.closest( '.rapm-pieces-dot, .rapm-pieces-btn' ) ) { e.preventDefault(); }
		} );
		stage.addEventListener( 'pointermove', function ( e ) {
			if ( ! drag ) { return; }
			var box = stage.getBoundingClientRect(), d = drag.btn ? state.btn : state.dots[ drag.k ];
			if ( ! drag.btn && Math.abs( e.clientX - drag.sx ) + Math.abs( e.clientY - drag.sy ) > 3 ) { drag.moved = true; }
			sxy( d, Math.round( Math.min( Math.max( ( e.clientX - box.left ) / box.width, 0 ), 1 ) * 1000 ) / 10,
				Math.round( Math.min( Math.max( ( e.clientY - box.top ) / box.height, 0 ), 1 ) * 1000 ) / 10 );
			drag.el.style.left = gx( d ) + '%';
			drag.el.style.top  = gy( d ) + '%';
			renderFit();
			renderPreview();
			sync();
		} );
		var endDrag = function () {
			if ( ! drag ) { return; }
			if ( drag.btn ) {
				drag.el.classList.remove( 'is-dragging' );
				drag = null;
				renderAll();
				var pbAgain = stage.querySelector( '.rapm-pieces-btn' );
				if ( pbAgain ) { pbAgain.focus( { preventScroll: true } ); }
				return;
			}
			var k = drag.k;
			drag.el.classList.remove( 'is-dragging' );
			drag = null;
			select( k );
			var again = stage.querySelector( '.rapm-pieces-dot[data-k="' + k + '"]' );
			if ( again ) { again.focus( { preventScroll: true } ); }
		};
		stage.addEventListener( 'pointerup', endDrag );
		stage.addEventListener( 'pointercancel', endDrag );
		stage.addEventListener( 'keydown', function ( e ) {
			var dot = e.target.closest( '.rapm-pieces-dot' );
			var moves = { ArrowLeft: [ -1, 0 ], ArrowRight: [ 1, 0 ], ArrowUp: [ 0, -1 ], ArrowDown: [ 0, 1 ] };
			if ( e.target.closest( '.rapm-pieces-btn' ) && moves[ e.key ] ) {
				e.preventDefault();
				var bstep = e.shiftKey ? 5 : 1;
				sxy( state.btn, Math.min( Math.max( gx( state.btn ) + moves[ e.key ][0] * bstep, 0 ), 100 ),
					Math.min( Math.max( gy( state.btn ) + moves[ e.key ][1] * bstep, 0 ), 100 ) );
				renderAll();
				var bAgain = stage.querySelector( '.rapm-pieces-btn' );
				if ( bAgain ) { bAgain.focus(); }
				return;
			}
			if ( ! dot || ! moves[ e.key ] ) { return; }
			e.preventDefault();
			var k = parseInt( dot.getAttribute( 'data-k' ), 10 ), d = state.dots[ k ], step = e.shiftKey ? 5 : 1;
			sxy( d, Math.min( Math.max( gx( d ) + moves[ e.key ][0] * step, 0 ), 100 ),
				Math.min( Math.max( gy( d ) + moves[ e.key ][1] * step, 0 ), 100 ) );
			state.selected = k;
			renderAll();
			var again = stage.querySelector( '.rapm-pieces-dot[data-k="' + k + '"]' );
			if ( again ) { again.focus(); }
		} );
	}

	var btnToggle = $( 'rapm-photo-btn-on' );
	if ( btnToggle ) {
		btnToggle.addEventListener( 'change', function () {
			state.btn.on = btnToggle.checked;
			renderAll();
		} );
	}
	if ( $( 'rapm_cta_text' ) ) {
		$( 'rapm_cta_text' ).addEventListener( 'input', function () {
			var pb = stage ? stage.querySelector( '.rapm-pieces-btn' ) : null;
			if ( pb ) { pb.textContent = btnText(); }
			renderPreview();
		} );
	}

	var addMiddle = $( 'rapm-pieces-add' );
	if ( addMiddle ) {
		addMiddle.addEventListener( 'click', function () {
			if ( ! state.src || ! C.store ) { return; }
			if ( addDot( 50, 50 ) ) {
				var dot = stage.querySelector( '.rapm-pieces-dot[data-k="' + state.selected + '"]' );
				if ( dot ) { dot.focus(); }
			}
		} );
	}

	function select( k ) {
		state.selected = k;
		renderAll();
	}

	/* ---- Place the Pieces: the list ----------------------------------------- */

	var list = $( 'rapm-pieces-list' );

	function thumb( url, cls ) {
		if ( url ) {
			var img = el( 'img', cls );
			img.src = url;
			img.alt = '';
			return img;
		}
		return el( 'span', cls );
	}

	function productBlock( info ) {
		var wrap = el( 'div', 'rapm-pieces-product' );
		wrap.appendChild( thumb( info.thumb, 'rapm-pieces-thumb' ) );
		var text = el( 'div' );
		text.appendChild( el( 'div', 'rapm-pieces-name', info.name ) );
		var meta = [];
		if ( info.sku ) { meta.push( fmt( T.sku, info.sku ) ); }
		if ( info.price ) { meta.push( info.price ); } else if ( info.price === '' ) { meta.push( T.noPrice ); }
		text.appendChild( el( 'div', 'rapm-pieces-meta', meta.join( ' · ' ) ) );
		if ( info.stock ) { text.appendChild( el( 'div', 'rapm-pieces-meta ' + ( info.stockClass || '' ), info.stock ) ); }
		wrap.appendChild( text );
		return wrap;
	}

	function renderList() {
		if ( ! list ) { return; }
		var count = $( 'rapm-pieces-count' );
		count.textContent = state.dots.length ? ( 1 === state.dots.length ? T.oneDot : fmt( T.manyDots, state.dots.length ) ) : '';
		list.innerHTML = '';
		if ( ! state.dots.length ) {
			list.appendChild( el( 'li', 'rapm-pieces-list-empty', T.listEmpty ) );
			return;
		}
		var cut = cutOff();
		state.dots.forEach( function ( d, k ) {
			var info = d.p ? products[ d.p ] : null;
			var row  = el( 'li', 'rapm-pieces-row' + ( k === state.selected ? ' is-selected' : '' ) + ( d.p ? ( info ? '' : ' is-gone' ) : ' is-missing' ) );
			row.setAttribute( 'data-k', k );
			row.appendChild( el( 'span', 'rapm-pieces-num', String( k + 1 ) ) );
			var body = el( 'div', 'rapm-pieces-body' );
			if ( d.p && info ) {
				body.appendChild( productBlock( info ) );
			} else if ( d.p ) {
				body.appendChild( el( 'p', 'rapm-pieces-gone', T.gone ) );
			} else {
				var label = el( 'label', 'screen-reader-text', fmt( T.searchLabel, k + 1 ) );
				label.htmlFor = 'rapm-pieces-search-' + k;
				var input = el( 'input', 'rapm-pieces-search' );
				input.type = 'search';
				input.id = 'rapm-pieces-search-' + k;
				input.placeholder = T.search;
				input.autocomplete = 'off';
				input.setAttribute( 'data-k', k );
				var results = el( 'div', 'rapm-pieces-results' );
				results.id = 'rapm-pieces-results-' + k;
				body.appendChild( label );
				body.appendChild( input );
				body.appendChild( results );
			}
			if ( cut[ k ] ) { body.appendChild( el( 'p', 'rapm-pieces-cut', cut[ k ] ) ); }
			var actions = el( 'div', 'rapm-pieces-actions' );
			function action( text, name, cls ) {
				var b = el( 'button', 'button-link' + ( cls ? ' ' + cls : '' ), text );
				b.type = 'button';
				b.setAttribute( 'data-act', name );
				b.setAttribute( 'data-k', k );
				actions.appendChild( b );
			}
			if ( d.p ) { action( T.change, 'change' ); }
			if ( k > 0 ) { action( T.moveUp, 'up' ); }
			if ( k < state.dots.length - 1 ) { action( T.moveDown, 'down' ); }
			action( T.remove, 'remove', 'is-danger' );
			body.appendChild( actions );
			row.appendChild( body );
			list.appendChild( row );
		} );
	}

	if ( list ) {
		list.addEventListener( 'click', function ( e ) {
			var pick = e.target.closest( '.rapm-pieces-result' );
			if ( pick ) {
				var k = parseInt( pick.getAttribute( 'data-k' ), 10 ), id = pick.getAttribute( 'data-id' );
				state.dots[ k ].p = parseInt( id, 10 );
				warn( '' );
				live( fmt( T.picked, k + 1, products[ id ] ? products[ id ].name : id ) );
				select( k );
				return;
			}
			var b = e.target.closest( 'button[data-act]' );
			if ( ! b ) {
				var row = e.target.closest( '.rapm-pieces-row' );
				if ( row && ! e.target.closest( 'input' ) ) { select( parseInt( row.getAttribute( 'data-k' ), 10 ) ); }
				return;
			}
			var n = parseInt( b.getAttribute( 'data-k' ), 10 ), act = b.getAttribute( 'data-act' ), other;
			if ( 'remove' === act ) {
				state.dots.splice( n, 1 );
				state.selected = -1;
				live( T.removed );
				renderAll();
			} else if ( 'change' === act ) {
				state.dots[ n ].p = 0;
				select( n );
				var search = $( 'rapm-pieces-search-' + n );
				if ( search ) { search.focus(); }
			} else if ( 'up' === act || 'down' === act ) {
				other = 'up' === act ? n - 1 : n + 1;
				var tmp = state.dots[ n ];
				state.dots[ n ] = state.dots[ other ];
				state.dots[ other ] = tmp;
				select( other );
			}
		} );

		var searchTimer = null, searchSeq = 0;
		list.addEventListener( 'input', function ( e ) {
			var input = e.target.closest( '.rapm-pieces-search' );
			if ( ! input ) { return; }
			var k = parseInt( input.getAttribute( 'data-k' ), 10 ), q = input.value.trim();
			var box = $( 'rapm-pieces-results-' + k );
			clearTimeout( searchTimer );
			if ( q.length < 2 ) { box.innerHTML = ''; return; }
			searchTimer = setTimeout( function () {
				var mine = ++searchSeq;
				box.innerHTML = '';
				box.appendChild( el( 'div', 'rapm-pieces-note', T.searching ) );
				var byName = store( 'search=' + encodeURIComponent( q ) + '&per_page=8' );
				var bySku  = /\s/.test( q ) ? Promise.resolve( { items: [], total: 0 } ) : store( 'sku=' + encodeURIComponent( q ) + '&per_page=8' ).catch( function () { return { items: [], total: 0 }; } );
				Promise.all( [ byName, bySku ] ).then( function ( res ) {
					var seen = {}, items = [];
					res[1].items.concat( res[0].items ).forEach( function ( item ) {
						if ( ! seen[ item.id ] ) { seen[ item.id ] = true; items.push( item ); }
					} );
					if ( items.length || ! /\s/.test( q ) || mine !== searchSeq ) { return { items: items, total: res[0].total }; }
					return wordSearch( q );
				} ).then( function ( res ) {
					if ( mine !== searchSeq ) { return; }
					var items = res.items;
					box.innerHTML = '';
					if ( ! items.length ) {
						box.appendChild( el( 'div', 'rapm-pieces-note', fmt( T.noMatch, q ) ) );
						return;
					}
					items.slice( 0, 8 ).forEach( function ( item ) {
						remember( item );
						var info = products[ item.id ];
						var b = el( 'button', 'rapm-pieces-result' );
						b.type = 'button';
						b.setAttribute( 'data-k', k );
						b.setAttribute( 'data-id', item.id );
						b.appendChild( thumb( info.thumb, 'rapm-pieces-thumb' ) );
						var text = el( 'span' );
						text.appendChild( el( 'span', 'rapm-pieces-name', info.name ) );
						text.appendChild( el( 'br' ) );
						var meta = [];
						if ( info.sku ) { meta.push( fmt( T.sku, info.sku ) ); }
						meta.push( info.price || T.noPrice );
						if ( info.stock ) { meta.push( info.stock ); }
						text.appendChild( el( 'span', 'rapm-pieces-meta', meta.join( ' · ' ) ) );
						b.appendChild( text );
						box.appendChild( b );
					} );
					if ( res.total > 8 ) { box.appendChild( el( 'div', 'rapm-pieces-note', fmt( T.more, 8, res.total ) ) ); }
				} ).catch( function () {
					if ( mine === searchSeq ) {
						box.innerHTML = '';
						box.appendChild( el( 'div', 'rapm-pieces-note', T.searchFailed ) );
					}
				} );
			}, 250 );
		} );
		list.addEventListener( 'keydown', function ( e ) {
			var input = e.target.closest( '.rapm-pieces-search' );
			if ( input && 'Escape' === e.key ) { $( 'rapm-pieces-results-' + input.getAttribute( 'data-k' ) ).innerHTML = ''; }
		} );
	}

	/* ---- Which part to keep + how it fits ------------------------------------ */

	var focusGrid = $( 'rapm-look-focus' );
	if ( focusGrid ) {
		focusGrid.addEventListener( 'click', function ( e ) {
			var b = e.target.closest( 'button[data-anchor]' );
			if ( ! b ) { return; }
			state.focus = b.getAttribute( 'data-anchor' );
			Array.prototype.forEach.call( focusGrid.querySelectorAll( 'button' ), function ( x ) {
				var on = x === b;
				x.classList.toggle( 'is-selected', on );
				x.setAttribute( 'aria-pressed', on ? 'true' : 'false' );
			} );
			renderAll();
		} );
	}

	function renderFrame( frame, aspect ) {
		if ( ! frame ) { return; }
		var img = frame.querySelector( 'img' );
		Array.prototype.forEach.call( frame.querySelectorAll( '.rapm-look-pin' ), function ( p ) { p.remove(); } );
		img.style.visibility = state.src ? 'visible' : 'hidden';
		if ( ! state.src ) { return; }
		if ( img.getAttribute( 'src' ) !== state.src ) { img.setAttribute( 'src', state.src ); }
		img.style.objectPosition = state.focus;
		if ( ! state.w || ! state.h ) { return; }
		var b = cropBox( aspect );
		state.dots.forEach( function ( d, k ) {
			var x = ( d.x / 100 - b.x0 ) / ( b.x1 - b.x0 ) * 100, y = ( d.y / 100 - b.y0 ) / ( b.y1 - b.y0 ) * 100;
			if ( x < 0 || x > 100 || y < 0 || y > 100 ) { return; }
			var pin = el( 'span', 'rapm-look-pin', String( k + 1 ) );
			pin.style.left = x + '%';
			pin.style.top  = y + '%';
			frame.appendChild( pin );
		} );
	}

	function renderFit() {
		renderFrame( document.querySelector( '.rapm-look-frame-wide' ), WIDE );
		renderFrame( document.querySelector( '.rapm-look-frame-phone' ), PHONE );
		var msg = $( 'rapm-pieces-fit-msg' );
		if ( ! msg ) { return; }
		msg.className = 'rapm-pieces-fit-msg';
		if ( ! state.src || ! state.dots.length ) { msg.textContent = ''; return; }
		var cut = cutOff();
		if ( Object.keys( cut ).length ) {
			msg.classList.add( 'is-warn' );
			msg.textContent = T.fitWarn;
		} else {
			msg.classList.add( 'is-ok' );
			msg.textContent = T.fitOk;
		}
		Array.prototype.forEach.call( document.querySelectorAll( '.rapm-pieces-row' ), function ( row ) {
			var k = parseInt( row.getAttribute( 'data-k' ), 10 ), note = row.querySelector( '.rapm-pieces-cut' );
			if ( cut[ k ] && ! note ) {
				note = el( 'p', 'rapm-pieces-cut', cut[ k ] );
				row.querySelector( '.rapm-pieces-actions' ).before( note );
			} else if ( cut[ k ] ) {
				note.textContent = cut[ k ];
			} else if ( note ) {
				note.remove();
			}
		} );
	}

	/* ---- Review & Schedule: the live preview ------------------------------- */

	var pv = $( 'rapm-look-pv' );

	function words() {
		if ( ! pv ) { return; }
		var tab = $( 'rapm_tab_label' ) ? $( 'rapm_tab_label' ).value.trim() : '';
		$( 'rapm-look-pv-tab' ).textContent  = tab || T.tabEmpty;
		$( 'rapm-look-pv-tab2' ).textContent = tab;
		[ [ 'title', 'rapm_headline' ], [ 'blurb', 'rapm_subhead' ], [ 'btn', 'rapm_cta_text' ] ].forEach( function ( pair ) {
			var out = $( 'rapm-look-pv-' + pair[0] ), field = $( pair[1] ), v = field ? field.value : '';
			out.textContent = v;
			out.style.display = v ? '' : 'none';
		} );
	}

	function renderPreview() {
		if ( ! pv ) { return; }
		var img = $( 'rapm-look-pv-img' ), pvStage = $( 'rapm-look-pv-stage' );
		img.style.visibility = state.src ? 'visible' : 'hidden';
		if ( state.src && img.getAttribute( 'src' ) !== state.src ) { img.setAttribute( 'src', state.src ); }
		img.style.objectPosition = state.focus;
		$( 'rapm-look-pv-empty' ).style.display = state.src ? 'none' : '';

		// Dots, placed and numbered the way the website does it: a product
		// that's off the website loses its dot and card, and the rest close up.
		var shown = state.dots.filter( function ( d ) { return d.p && products[ d.p ]; } );
		Array.prototype.forEach.call( pvStage.querySelectorAll( '.rapm-look-dot, .rapm-look-photo-btn' ), function ( d ) { d.remove(); } );
		var cw = pvStage.clientWidth, ch = pvStage.clientHeight;
		if ( state.src && state.w && state.h && cw && ch ) {
			var f = focusXY(), scale = Math.max( cw / state.w, ch / state.h ), dw = state.w * scale, dh = state.h * scale;
			var ox = ( cw - dw ) * f[0], oy = ( ch - dh ) * f[1];
			// The photo button, kept wholly inside the photo the way the website does.
			if ( state.btn.on ) {
				var pbtn = el( 'span', 'rapm-look-photo-btn', btnText() );
				pvStage.querySelector( '.rapm-look-photo' ).appendChild( pbtn );
				var bw = pbtn.offsetWidth, bh = pbtn.offsetHeight;
				pbtn.style.left = Math.min( Math.max( ox + state.btn.x / 100 * dw - bw / 2, 8 ), cw - bw - 8 ) + 'px';
				pbtn.style.top  = Math.min( Math.max( oy + state.btn.y / 100 * dh - bh / 2, 8 ), ch - bh - 8 ) + 'px';
			}
			shown.forEach( function ( d, k ) {
				var x = ox + d.x / 100 * dw, y = oy + d.y / 100 * dh;
				if ( x < 4 || x > cw - 4 || y < 4 || y > ch - 4 ) { return; }
				var dot = el( 'span', 'rapm-look-dot', String( k + 1 ) );
				dot.style.left = Math.min( Math.max( x, 18 ), cw - 18 ) + 'px';
				dot.style.top  = Math.min( Math.max( y, 18 ), ch - 18 ) + 'px';
				pvStage.querySelector( '.rapm-look-photo' ).appendChild( dot );
			} );
		}

		// Cards for the pieces.
		var cards = $( 'rapm-look-pv-cards' ), text = $( 'rapm-look-pv-text' );
		cards.innerHTML = '';
		text.classList.toggle( 'has-cards', shown.length > 0 );
		shown.forEach( function ( d, k ) {
			var info = products[ d.p ];
			var li = el( 'li', 'rapm-look-card' ), pic = el( 'span', 'rapm-look-card-img' ), meta = el( 'div', 'rapm-look-card-meta' );
			if ( info.thumb ) { var im = el( 'img' ); im.src = info.thumb; im.alt = ''; pic.appendChild( im ); }
			pic.appendChild( el( 'span', 'rapm-look-num', String( k + 1 ) ) );
			meta.appendChild( el( 'span', 'rapm-look-card-name', info.name ) );
			var price = el( 'span', 'rapm-look-price' );
			if ( info.regular ) { price.appendChild( el( 'del', '', info.regular ) ); price.appendChild( document.createTextNode( ' ' ) ); }
			if ( info.price ) { price.appendChild( el( 'ins', '', info.price ) ); }
			meta.appendChild( price );
			meta.appendChild( el( 'span', 'rapm-look-stock ' + ( info.stockClass || '' ), info.stock || '' ) );
			li.appendChild( pic );
			li.appendChild( meta );
			cards.appendChild( li );
		} );
		words();
	}

	if ( pv ) {
		$( 'rapm-look-pv-desktop' ).addEventListener( 'click', function () {
			pv.classList.remove( 'is-mobile' );
			this.setAttribute( 'aria-pressed', 'true' );
			$( 'rapm-look-pv-mobile' ).setAttribute( 'aria-pressed', 'false' );
			renderPreview();
		} );
		$( 'rapm-look-pv-mobile' ).addEventListener( 'click', function () {
			pv.classList.add( 'is-mobile' );
			this.setAttribute( 'aria-pressed', 'true' );
			$( 'rapm-look-pv-desktop' ).setAttribute( 'aria-pressed', 'false' );
			renderPreview();
		} );
		[ 'rapm_tab_label', 'rapm_headline', 'rapm_subhead', 'rapm_cta_text' ].forEach( function ( id ) {
			if ( $( id ) ) { $( id ).addEventListener( 'input', words ); }
		} );
		if ( window.ResizeObserver ) { new window.ResizeObserver( function () { renderPreview(); } ).observe( $( 'rapm-look-pv-stage' ) ); }
	}

	/* ---- Checks ------------------------------------------------------------- */

	function warn( text ) {
		var box = document.querySelector( '#rapm-pieces' ) ? document.querySelector( '#rapm-pieces' ).closest( '.rapm-step' ).querySelector( '.rapm-wizard-next-warning' ) : null;
		if ( ! box ) { return; }
		box.textContent = text;
		box.style.display = text ? 'block' : 'none';
	}

	// Every dot needs a product before moving on or saving.
	function validate() {
		var missing = [];
		state.dots.forEach( function ( d, k ) { if ( ! d.p ) { missing.push( k + 1 ); } } );
		if ( missing.length ) {
			warn( fmt( T.needProduct, missing.join( ', ' ) ) );
			return false;
		}
		warn( '' );
		return true;
	}

	var form = document.querySelector( 'form input[name="rapm_kind"][value="look"]' );
	form = form ? form.form : null;
	if ( form ) {
		form.addEventListener( 'submit', function ( e ) {
			if ( ! validate() ) {
				e.preventDefault();
				var step = $( 'rapm-pieces' ).closest( '.rapm-step' );
				if ( step.hidden ) {
					// New look: go back to Place the Pieces through its step button.
					var pill = document.querySelector( '.rapm-wizard-step[data-step="' + step.getAttribute( 'data-step' ) + '"]' );
					if ( pill ) { pill.click(); }
				}
				step.scrollIntoView( { block: 'start' } );
			}
		} );
	}

	/* ---- 1.35.0: a banner's Live Preview (Review & Schedule) --------------- */

	// Dots and the photo button on the banner preview (#rapm-preview), on
	// whichever picture its Desktop/Mobile toggle shows, the way the website
	// places them (RAPM_Pins: cover-fit, button kept inside).
	function renderBannerPreview() {
		var box = BANNER ? $( 'rapm-preview' ) : null, img = $( 'rapm-preview-img' );
		if ( ! box || ! img ) { return; }
		Array.prototype.forEach.call( box.querySelectorAll( '.rapm-pin-dot, .rapm-pin-btn' ), function ( n ) { n.remove(); } );
		var mobile = $( 'rapm-preview-toggle-mobile' ) && 'true' === $( 'rapm-preview-toggle-mobile' ).getAttribute( 'aria-pressed' ) && !! photos.mobile.src;
		var cw = box.clientWidth, ch = box.clientHeight, iw = img.naturalWidth, ih = img.naturalHeight;
		if ( ! cw || ! ch || ! iw || ! ih || 'none' === img.style.display ) { return; }
		var scale = Math.max( cw / iw, ch / ih ), dw = iw * scale, dh = ih * scale, ox = ( cw - dw ) / 2, oy = ( ch - dh ) / 2;
		function px( o ) { return mobile && null != o.mx ? o.mx : o.x; }
		function py( o ) { return mobile && null != o.my ? o.my : o.y; }
		box.style.setProperty( '--rapm-pins-accent', C.accent || '#2271b1' );
		state.dots.filter( function ( d ) { return d.p && products[ d.p ]; } ).forEach( function ( d, k ) {
			var x = ox + px( d ) / 100 * dw, y = oy + py( d ) / 100 * dh;
			if ( x < 4 || x > cw - 4 || y < 4 || y > ch - 4 ) { return; }
			var dot = el( 'span', 'rapm-pin-dot', String( k + 1 ) );
			dot.style.left = x + 'px';
			dot.style.top  = y + 'px';
			box.appendChild( dot );
		} );
		if ( state.btn.on ) {
			var b = el( 'span', 'rapm-pin-btn', btnText() );
			box.appendChild( b );
			var bw = b.offsetWidth, bh = b.offsetHeight;
			b.style.left = Math.min( Math.max( ox + px( state.btn ) / 100 * dw - bw / 2, 8 ), cw - bw - 8 ) + 'px';
			b.style.top  = Math.min( Math.max( oy + py( state.btn ) / 100 * dh - bh / 2, 8 ), ch - bh - 8 ) + 'px';
		}
	}
	if ( BANNER && $( 'rapm-preview' ) ) {
		[ 'rapm-preview-toggle-desktop', 'rapm-preview-toggle-mobile' ].forEach( function ( id ) {
			if ( $( id ) ) { $( id ).addEventListener( 'click', function () { setTimeout( renderBannerPreview, 60 ); } ); }
		} );
		$( 'rapm-preview-img' ).addEventListener( 'load', renderBannerPreview );
		if ( window.ResizeObserver ) { new window.ResizeObserver( renderBannerPreview ).observe( $( 'rapm-preview' ) ); }
		if ( $( 'rapm_cta_text' ) ) { $( 'rapm_cta_text' ).addEventListener( 'input', renderBannerPreview ); }
	}

	function renderAll() {
		renderStage();
		renderList();
		renderFit();
		renderPreview();
		renderBannerPreview();
		sync();
	}

	window.RAPM_LookEditor = { validate: validate, state: state };
	sizeText();
	renderAll();
	loadPrices();
} )();
