/**
 * Shop the Look's form (RAPM_Upload_Handler, 1.31.0), ported from the editor
 * mockup Phil approved on 2026-10-06:
 * - Step 1: the room photo's size note and "Use a link" check.
 * - Place the Pieces: click the photo to add a numbered dot, drag it (or
 *   use the arrow keys) to move it, and pick its product by name or SKU.
 *   "What each screen shows" (1.32.0): drag the photo in the "On computers"
 *   and "On phones" boxes to set each screen's crop on its own; each box
 *   names any dot it cuts off.
 * - Review & Schedule: the look as shoppers see it, with live prices.
 *
 * Settings and words come from window.RAPM_LookEditorConfig (printed by
 * RAPM_Upload_Handler::look_editor_config()). Products are searched and
 * priced through WooCommerce's Store API, the same source the website uses.
 * The dots are saved as JSON in the hidden #rapm_dots field:
 * [{ p: product ID, x: 0-100, y: 0-100 }], positions as % of the photo.
 */
( function () {
	'use strict';

	var C = window.RAPM_LookEditorConfig;
	if ( ! C ) { return; }
	var T = C.text || {};

	// Box shapes: the live photo is 38% of the screen width tall on computers
	// (rapm-looks.css), so about 2.63 : 1, and 4 : 3 on phones.
	var WIDE = 1 / 0.38, PHONE = 4 / 3, MARGIN = 0.02;

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

	var state = {
		src: C.src || '',
		w: C.width || 0,
		h: C.height || 0,
		// Where the photo sits in each screen's box, as fractions ( 0 = left /
		// top edge, 1 = right / bottom edge ). Computers and phones each have
		// their own (1.32.0); a look saved before uses one for both.
		fc: ( C.focus || [ 0.5, 0.5 ] ).slice(),
		fp: ( C.focusPhone || C.focus || [ 0.5, 0.5 ] ).slice(),
		dots: ( C.dots || [] ).map( function ( d ) { return { p: d.p, x: d.x, y: d.y }; } ),
		selected: -1
	};
	var products = {};
	Object.keys( C.products || {} ).forEach( function ( id ) { products[ id ] = C.products[ id ]; } );

	function round1( n ) { return Math.round( n * 10 ) / 10; }
	function posText( f ) { return round1( f[0] * 100 ) + '% ' + round1( f[1] * 100 ) + '%'; }

	// The part of the photo (0-1 on each axis) a frame of this shape shows at position f.
	function cropBox( aspect, f ) {
		var ia = state.w / state.h;
		if ( aspect >= ia ) {
			var vh = ia / aspect, y0 = ( 1 - vh ) * f[1];
			return { x0: 0, x1: 1, y0: y0, y1: y0 + vh };
		}
		var vw = aspect / ia, x0 = ( 1 - vw ) * f[0];
		return { x0: x0, x1: x0 + vw, y0: 0, y1: 1 };
	}

	function cutOff() {
		var out = {};
		if ( ! state.src || ! state.w || ! state.h ) { return out; }
		var bw = cropBox( WIDE, state.fc ), bp = cropBox( PHONE, state.fp );
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
			field.value = JSON.stringify( state.dots.map( function ( d ) { return { p: d.p || 0, x: d.x, y: d.y }; } ) );
		}
		if ( $( 'rapm_focus' ) ) { $( 'rapm_focus' ).value = posText( state.fc ); }
		if ( $( 'rapm_focus_phone' ) ) { $( 'rapm_focus_phone' ).value = posText( state.fp ); }
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

	function setPhoto( src, w, h ) {
		state.src = src;
		state.w   = w;
		state.h   = h;
		sizeText();
		renderAll();
	}

	var file = $( 'rapm_image_desktop' );
	if ( file ) {
		file.addEventListener( 'change', function () {
			if ( ! this.files || ! this.files[0] ) { return; }
			var url = URL.createObjectURL( this.files[0] ), probe = new Image();
			probe.onload = function () { setPhoto( url, probe.naturalWidth, probe.naturalHeight ); };
			probe.src = url;
		} );
	}

	// "Use a link": ask the server which picture the link really is.
	var urlInput = $( 'rapm_image_desktop_url' ), linkStatus = $( 'rapm-desktop-link-status' ), linkTimer = null, linkSeq = 0;
	function showLink( text, isError ) {
		if ( ! linkStatus ) { return; }
		linkStatus.textContent = text;
		linkStatus.style.color = isError ? '#b32d2e' : '';
	}
	function checkLink() {
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
		Array.prototype.forEach.call( stage.querySelectorAll( '.rapm-pieces-dot' ), function ( d ) { d.remove(); } );
		state.dots.forEach( function ( d, k ) {
			var b = el( 'button', 'rapm-pieces-dot' + ( k === state.selected ? ' is-selected' : '' ) + ( d.p ? ( products[ d.p ] ? '' : ' is-gone' ) : ' is-empty' ), String( k + 1 ) );
			b.type = 'button';
			b.style.left = d.x + '%';
			b.style.top  = d.y + '%';
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
		state.dots.push( { p: 0, x: x, y: y } );
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
			if ( ! e.target.closest( '.rapm-pieces-dot' ) ) { e.preventDefault(); }
		} );
		stage.addEventListener( 'pointermove', function ( e ) {
			if ( ! drag ) { return; }
			var box = stage.getBoundingClientRect(), d = state.dots[ drag.k ];
			if ( Math.abs( e.clientX - drag.sx ) + Math.abs( e.clientY - drag.sy ) > 3 ) { drag.moved = true; }
			d.x = Math.round( Math.min( Math.max( ( e.clientX - box.left ) / box.width, 0 ), 1 ) * 1000 ) / 10;
			d.y = Math.round( Math.min( Math.max( ( e.clientY - box.top ) / box.height, 0 ), 1 ) * 1000 ) / 10;
			drag.el.style.left = d.x + '%';
			drag.el.style.top  = d.y + '%';
			renderFit();
			renderPreview();
			sync();
		} );
		var endDrag = function () {
			if ( ! drag ) { return; }
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
			if ( ! dot || ! moves[ e.key ] ) { return; }
			e.preventDefault();
			var k = parseInt( dot.getAttribute( 'data-k' ), 10 ), d = state.dots[ k ], step = e.shiftKey ? 5 : 1;
			d.x = Math.min( Math.max( d.x + moves[ e.key ][0] * step, 0 ), 100 );
			d.y = Math.min( Math.max( d.y + moves[ e.key ][1] * step, 0 ), 100 );
			state.selected = k;
			renderAll();
			var again = stage.querySelector( '.rapm-pieces-dot[data-k="' + k + '"]' );
			if ( again ) { again.focus(); }
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

	/* ---- What each screen shows (1.32.0): drag the photo in each box --------- */

	// Like moving a photo inside a Canva frame. The photo is drawn bigger
	// than its box, covering it the way the website's object-fit: cover
	// does, and slides along the one direction it overflows: up and down
	// for a strip wider than the photo (computers, usually), left and right
	// for a box narrower than it (phones, usually). Sizes are percentages of
	// the box, so it lays out right even while this step is hidden.

	var fitBoxes = Array.prototype.slice.call( document.querySelectorAll( '.rapm-fit-box' ) );
	var fitDrag  = null;

	function fitInfo( box ) {
		var phone = 'phone' === box.getAttribute( 'data-device' );
		return { box: box, aspect: phone ? PHONE : WIDE, f: phone ? state.fp : state.fc, frame: box.querySelector( '.rapm-fit-frame' ) };
	}

	function fitGeometry( info ) {
		if ( ! state.w || ! state.h ) { return null; }
		var ia = state.w / state.h, g = {};
		if ( ia < info.aspect ) {
			g.axis = 'y'; g.ratio = info.aspect / ia;
			g.w = 100; g.h = g.ratio * 100; g.left = 0; g.top = -( g.ratio - 1 ) * info.f[1] * 100;
		} else {
			g.axis = 'x'; g.ratio = ia / info.aspect;
			g.h = 100; g.w = g.ratio * 100; g.top = 0; g.left = -( g.ratio - 1 ) * info.f[0] * 100;
		}
		if ( g.ratio - 1 < 0.005 ) { g.axis = ''; }
		return g;
	}

	function fitNow( info, g ) { return 'x' === g.axis ? info.f[0] : info.f[1]; }

	function fitSet( info, value ) {
		var g = fitGeometry( info );
		if ( ! g || ! g.axis ) { return; }
		info.f[ 'x' === g.axis ? 0 : 1 ] = Math.round( Math.max( 0, Math.min( 1, value ) ) * 1000 ) / 1000;
		renderFit();
		renderPreview();
		sync();
	}

	function dotName( d, k ) {
		var name = d.p && products[ d.p ] ? products[ d.p ].name : fmt( T.dotN, k + 1 );
		name = name.length > 40 ? name.slice( 0, 39 ) + '…' : name;
		return ( k + 1 ) + ' (' + name + ')';
	}

	function renderFit() {
		var whole = document.querySelector( '.rapm-fit-whole' );
		if ( whole ) { whole.hidden = ! state.src; }

		fitBoxes.forEach( function ( box ) {
			var info  = fitInfo( box ), frame = info.frame, note = box.querySelector( '.rapm-fit-note' );
			var imgs  = [ frame.querySelector( '.rapm-fit-img' ), box.querySelector( '.rapm-fit-ghost' ) ];
			var steps = box.querySelectorAll( '[data-step]' );
			Array.prototype.forEach.call( frame.querySelectorAll( '.rapm-look-pin' ), function ( p ) { p.remove(); } );
			imgs.forEach( function ( im ) {
				im.style.visibility = state.src ? 'visible' : 'hidden';
				if ( state.src && im.getAttribute( 'src' ) !== state.src ) { im.setAttribute( 'src', state.src ); }
			} );
			var g = state.src ? fitGeometry( info ) : null;
			Array.prototype.forEach.call( box.querySelectorAll( '.rapm-fit-controls button' ), function ( b ) { b.disabled = ! g || ! g.axis; } );
			if ( ! g ) { note.textContent = ''; return; }

			imgs.forEach( function ( im ) {
				im.style.width  = g.w + '%';
				im.style.height = g.h + '%';
				im.style.left   = g.left + '%';
				im.style.top    = g.top + '%';
			} );
			var across = 'x' === g.axis;
			steps[0].textContent = across ? T.left : T.up;
			steps[0].setAttribute( 'aria-label', across ? T.leftLabel : T.upLabel );
			steps[1].textContent = across ? T.right : T.down;
			steps[1].setAttribute( 'aria-label', across ? T.rightLabel : T.downLabel );
			frame.classList.toggle( 'is-fixed', ! g.axis );
			frame.setAttribute( 'aria-orientation', across ? 'horizontal' : 'vertical' );
			var now = Math.round( fitNow( info, g ) * 100 );
			frame.setAttribute( 'aria-valuenow', g.axis ? now : 50 );
			frame.setAttribute( 'aria-valuetext', g.axis ? fmt( across ? T.fromLeft : T.fromTop, now ) : T.fitsExactly );

			// Dots: shown where this screen shows them, named when cut off.
			var b = cropBox( info.aspect, info.f ), cut = [];
			state.dots.forEach( function ( d, k ) {
				var x = d.x / 100, y = d.y / 100;
				if ( x < b.x0 + MARGIN || x > b.x1 - MARGIN || y < b.y0 + MARGIN || y > b.y1 - MARGIN ) {
					cut.push( dotName( d, k ) );
					return;
				}
				var pin = el( 'span', 'rapm-look-pin', String( k + 1 ) );
				pin.style.left = ( g.left + x * g.w ) + '%';
				pin.style.top  = ( g.top + y * g.h ) + '%';
				frame.appendChild( pin );
			} );
			note.className = 'rapm-fit-note';
			if ( ! g.axis ) {
				note.textContent = T.fitsExactly;
			} else if ( cut.length ) {
				note.classList.add( 'is-warn' );
				note.textContent = fmt( 1 === cut.length ? T.cutHere : T.cutHereMany, cut.join( ', ' ) );
			} else if ( state.dots.length ) {
				note.classList.add( 'is-ok' );
				note.textContent = T.allShow;
			} else {
				note.textContent = '';
			}
		} );

		// The whole photo, with what each screen shows outlined.
		var ov = $( 'rapm-fit-overview' );
		if ( ov && state.src ) {
			var oimg = ov.querySelector( 'img' );
			if ( oimg.getAttribute( 'src' ) !== state.src ) { oimg.setAttribute( 'src', state.src ); }
			if ( state.w && state.h ) {
				ov.style.aspectRatio = state.w + ' / ' + state.h;
				[ [ '.rapm-fit-out-computer', cropBox( WIDE, state.fc ) ], [ '.rapm-fit-out-phone', cropBox( PHONE, state.fp ) ] ].forEach( function ( pair ) {
					var out = ov.querySelector( pair[0] ), bx = pair[1];
					out.style.left   = bx.x0 * 100 + '%';
					out.style.top    = bx.y0 * 100 + '%';
					out.style.width  = ( bx.x1 - bx.x0 ) * 100 + '%';
					out.style.height = ( bx.y1 - bx.y0 ) * 100 + '%';
				} );
			}
			Array.prototype.forEach.call( ov.querySelectorAll( '.rapm-look-pin' ), function ( p ) { p.remove(); } );
			state.dots.forEach( function ( d, k ) {
				var pin = el( 'span', 'rapm-look-pin', String( k + 1 ) );
				pin.style.left = d.x + '%';
				pin.style.top  = d.y + '%';
				ov.appendChild( pin );
			} );
		}

		// The summary line and the "Cut off on …" note on each dot's row.
		var msg = $( 'rapm-pieces-fit-msg' );
		if ( ! msg ) { return; }
		msg.className = 'rapm-pieces-fit-msg';
		if ( ! state.src || ! state.dots.length ) { msg.textContent = ''; return; }
		var cutAll = cutOff();
		if ( Object.keys( cutAll ).length ) {
			msg.classList.add( 'is-warn' );
			msg.textContent = T.fitWarn;
		} else {
			msg.classList.add( 'is-ok' );
			msg.textContent = T.fitOk;
		}
		Array.prototype.forEach.call( document.querySelectorAll( '.rapm-pieces-row' ), function ( row ) {
			var k = parseInt( row.getAttribute( 'data-k' ), 10 ), rowNote = row.querySelector( '.rapm-pieces-cut' );
			if ( cutAll[ k ] && ! rowNote ) {
				rowNote = el( 'p', 'rapm-pieces-cut', cutAll[ k ] );
				row.querySelector( '.rapm-pieces-actions' ).before( rowNote );
			} else if ( cutAll[ k ] ) {
				rowNote.textContent = cutAll[ k ];
			} else if ( rowNote ) {
				rowNote.remove();
			}
		} );
	}

	fitBoxes.forEach( function ( box ) {
		var frame = box.querySelector( '.rapm-fit-frame' ), area = box.querySelector( '.rapm-fit-area' );
		function showGhost( on ) { area.classList.toggle( 'is-active', on || ( !! fitDrag && fitDrag.box === box ) ); }

		frame.addEventListener( 'pointerdown', function ( e ) {
			var info = fitInfo( box ), g = fitGeometry( info );
			if ( ! g || ! g.axis || ( e.button !== undefined && 0 !== e.button ) ) { return; }
			e.preventDefault();
			// Keeps the drag going when the pointer slips outside the box. Some
			// browsers refuse it for a pointer that has already let go; the
			// drag still works inside the box without it.
			try { frame.setPointerCapture( e.pointerId ); } catch ( err ) {}
			var across = 'x' === g.axis;
			fitDrag = {
				box: box,
				across: across,
				start: across ? e.clientX : e.clientY,
				from: fitNow( info, g ),
				// How far the photo can slide, in pixels, measured now that the box is on screen.
				over: ( g.ratio - 1 ) * ( across ? frame.clientWidth : frame.clientHeight )
			};
			frame.classList.add( 'is-dragging' );
			showGhost( true );
			frame.focus( { preventScroll: true } );
		} );
		frame.addEventListener( 'pointermove', function ( e ) {
			if ( ! fitDrag || fitDrag.box !== box || fitDrag.over < 1 ) { return; }
			var moved = ( fitDrag.across ? e.clientX : e.clientY ) - fitDrag.start;
			fitSet( fitInfo( box ), fitDrag.from - moved / fitDrag.over );
		} );
		function endDrag() {
			if ( ! fitDrag || fitDrag.box !== box ) { return; }
			fitDrag = null;
			frame.classList.remove( 'is-dragging' );
			showGhost( frame.matches( ':hover' ) );
		}
		frame.addEventListener( 'pointerup', endDrag );
		frame.addEventListener( 'pointercancel', endDrag );
		frame.addEventListener( 'pointerenter', function () { showGhost( true ); } );
		frame.addEventListener( 'pointerleave', function () { showGhost( false ); } );
		frame.addEventListener( 'focus', function () { showGhost( frame.matches( ':focus-visible' ) ); } );
		frame.addEventListener( 'blur', function () { showGhost( false ); } );
		frame.addEventListener( 'keydown', function ( e ) {
			var info = fitInfo( box ), g = fitGeometry( info );
			if ( ! g || ! g.axis ) { return; }
			var now = fitNow( info, g ), step = e.shiftKey ? 0.1 : 0.01;
			if ( 'ArrowUp' === e.key || 'ArrowLeft' === e.key ) { fitSet( info, now - step ); }
			else if ( 'ArrowDown' === e.key || 'ArrowRight' === e.key ) { fitSet( info, now + step ); }
			else if ( 'Home' === e.key ) { fitSet( info, 0 ); }
			else if ( 'End' === e.key ) { fitSet( info, 1 ); }
			else { return; }
			e.preventDefault();
		} );
		box.querySelector( '.rapm-fit-controls' ).addEventListener( 'click', function ( e ) {
			var b = e.target.closest( 'button' );
			if ( ! b ) { return; }
			var info = fitInfo( box ), g = fitGeometry( info );
			if ( ! g || ! g.axis ) { return; }
			fitSet( info, b.hasAttribute( 'data-center' ) ? 0.5 : fitNow( info, g ) + parseFloat( b.getAttribute( 'data-step' ) ) );
		} );
	} );

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
		var pvF = pv.classList.contains( 'is-mobile' ) ? state.fp : state.fc;
		img.style.objectPosition = posText( pvF );
		$( 'rapm-look-pv-empty' ).style.display = state.src ? 'none' : '';

		// Dots, placed and numbered the way the website does it: a product
		// that's off the website loses its dot and card, and the rest close up.
		var shown = state.dots.filter( function ( d ) { return d.p && products[ d.p ]; } );
		Array.prototype.forEach.call( pvStage.querySelectorAll( '.rapm-look-dot' ), function ( d ) { d.remove(); } );
		var cw = pvStage.clientWidth, ch = pvStage.clientHeight;
		if ( state.src && state.w && state.h && cw && ch ) {
			var f = pvF, scale = Math.max( cw / state.w, ch / state.h ), dw = state.w * scale, dh = state.h * scale;
			var ox = ( cw - dw ) * f[0], oy = ( ch - dh ) * f[1];
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

	function renderAll() {
		renderStage();
		renderList();
		renderFit();
		renderPreview();
		sync();
	}

	window.RAPM_LookEditor = { validate: validate, state: state };
	sizeText();
	renderAll();
	loadPrices();
} )();
