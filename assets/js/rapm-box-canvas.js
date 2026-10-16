/**
 * Promotion box canvas (1.44.0, from the mockup Phil approved on
 * 2026-10-16, https://claude.ai/artifact/Xwyizh61GsuHzZLbaqgvNz): drag the
 * headline, smaller line, button and Google Material Symbols icons anywhere
 * on a square design area; click one to change its size and color.
 *
 * The words come from the form's own Headline, Smaller line and Button Text
 * boxes. The layout is kept in the hidden rapm_canvas field as JSON:
 *   { bg: '#hex' | '', pic: 1 | 0, items: [ { k, x, y, s, c, n?, d? } ] }
 * k is headline, sub, btn or icon; x and y are the middle of the piece as a
 * percent of the square; s is its size as a percent of the square's width;
 * c is its color (the button's background). An icon also has n (its name)
 * and d (its drawing, the SVG path, fetched from Google when it's picked so
 * the website never has to load anything from Google). The server checks
 * every value again (RAPM_Split::sanitize_canvas()).
 */
( function () {
	var root = document.getElementById( 'rapm-canvas' );
	if ( ! root ) { return; }

	var T = JSON.parse( root.getAttribute( 'data-text' ) || '{}' );
	var field = document.getElementById( 'rapm_canvas' );
	var stage = root.querySelector( '.rapm-cv-stage' );
	var board = root.querySelector( '.rapm-cv-board' );
	var bgImg = root.querySelector( '.rapm-cv-bgimg' );
	var selBox = root.querySelector( '.rapm-cv-sel' );
	var grid = root.querySelector( '.rapm-cv-icons' );
	var search = root.querySelector( '.rapm-cv-search' );
	var gv = root.querySelector( '.rapm-cv-guide.v' );
	var gh = root.querySelector( '.rapm-cv-guide.h' );
	var fonts = JSON.parse( root.getAttribute( 'data-fonts' ) || '{}' );
	var ICON_URL = 'https://fonts.gstatic.com/s/i/short-term/release/materialsymbolsoutlined/';
	var SWATCHES = [ '#ffffff', '#1d3557', '#c8102e', '#e9b949', '#2f2f2f', '#f3ead8' ];
	var STARTERS = [ 'weekend', 'chair', 'bed', 'king_bed', 'table_restaurant', 'chair_alt', 'living', 'dining', 'sell', 'savings', 'percent', 'local_offer', 'local_shipping', 'credit_card', 'redeem', 'verified', 'star', 'workspace_premium', 'calendar_month', 'schedule', 'storefront', 'home', 'favorite', 'celebration' ];
	var LIMITS = { headline: [ 3, 20 ], sub: [ 2, 12 ], btn: [ 2, 8 ], icon: [ 4, 40 ] };

	var state = parse( field.value );
	var selected = -1;
	var list = null; // the icon names and search words, loaded on first use

	function parse( raw ) {
		var s;
		try { s = JSON.parse( raw || '' ); } catch ( e ) { s = null; }
		if ( ! s || ! Array.isArray( s.items ) || ! s.items.length ) {
			// A new design starts with the three words, centered and stacked.
			s = { bg: '#f3ead8', pic: 0, items: [
				{ k: 'headline', x: 50, y: 36, s: 10, c: '#1d3557' },
				{ k: 'sub', x: 50, y: 54, s: 5, c: '#1d3557' },
				{ k: 'btn', x: 50, y: 76, s: 3.6, c: '#c8102e' }
			] };
		}
		s.bg = s.bg || '';
		return s;
	}

	function save() {
		field.value = JSON.stringify( state );
		renderLayers();
	}

	function words( k ) {
		var id = { headline: 'rapm_headline', sub: 'rapm_subhead', btn: 'rapm_cta_text' }[ k ];
		var el = id && document.getElementById( id );
		return el ? el.value.trim() : '';
	}

	function label( it ) {
		if ( 'icon' === it.k ) { return T.icon + ': ' + it.n.replace( /_/g, ' ' ); }
		return { headline: T.headline, sub: T.sub, btn: T.btn }[ it.k ];
	}

	function svg( d ) {
		return '<svg viewBox="0 -960 960 960" aria-hidden="true" focusable="false"><path d="' + d + '"/></svg>';
	}

	function esc( t ) {
		return String( t ).replace( /[&<>"]/g, function ( c ) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[ c ]; } );
	}

	/* ---- Drawing the design ------------------------------------------- */

	function render() {
		Array.prototype.slice.call( board.querySelectorAll( '.rapm-cv-item' ) ).forEach( function ( n ) { n.remove(); } );
		var style = document.getElementById( 'rapm_text_style' );
		var font = document.getElementById( 'rapm_text_font' );
		board.setAttribute( 'data-style', style ? style.value : 'bold' );
		board.style.fontFamily = font && fonts[ font.value ] ? fonts[ font.value ] : '';
		state.items.forEach( function ( it, i ) {
			var text = 'icon' === it.k ? '' : words( it.k );
			var n = document.createElement( 'div' );
			n.className = 'rapm-cv-item rapm-pb-t is-' + it.k + ( i === selected ? ' is-on' : '' ) + ( 'icon' !== it.k && ! text ? ' is-empty' : '' );
			n.setAttribute( 'data-i', i );
			place( n, it );
			if ( 'icon' === it.k ) {
				n.innerHTML = svg( it.d );
			} else {
				n.textContent = text || T.empty.replace( '%s', label( it ) );
			}
			board.appendChild( n );
		} );
		var picUrl = root.getAttribute( 'data-picture' ) || '';
		var usePic = state.pic && picUrl;
		stage.style.background = usePic ? '#111' : ( state.bg || '#f3ead8' );
		bgImg.hidden = ! usePic;
		if ( usePic && bgImg.getAttribute( 'src' ) !== picUrl ) { bgImg.src = picUrl; }
		renderBg();
		renderSel();
		renderLayers();
	}

	function place( n, it ) {
		n.style.left = it.x + '%';
		n.style.top = it.y + '%';
		// --s is the size as a share of the square, as on the site
		// (rapm-hero.css), so the same minimum sizes apply here too.
		n.style.setProperty( '--s', it.s );
		if ( 'btn' === it.k ) {
			n.style.background = it.c;
			n.style.color = '#fff';
		} else {
			n.style.color = it.c;
		}
	}

	function renderLayers() {
		var box = root.querySelector( '.rapm-cv-layers' );
		box.innerHTML = state.items.map( function ( it, i ) {
			return '<button type="button" class="rapm-cv-layer" data-i="' + i + '" aria-pressed="' + ( i === selected ) + '">' +
				( 'icon' === it.k ? '<span class="rapm-cv-layer-ic" style="color:' + esc( it.c ) + '">' + svg( it.d ) + '</span>' : '' ) +
				'<span>' + esc( label( it ) ) + '</span><span class="rapm-cv-xy">' + Math.round( it.x ) + ', ' + Math.round( it.y ) + '</span></button>';
		} ).join( '' );
	}

	function renderSel() {
		var it = state.items[ selected ];
		if ( ! it ) {
			selBox.innerHTML = '<p class="description">' + esc( T.pick ) + '</p>';
			return;
		}
		var lim = LIMITS[ it.k ];
		var h = '<p><strong>' + esc( label( it ) ) + '</strong></p>';
		if ( 'icon' !== it.k ) { h += '<p class="description">' + esc( T.wordsAbove ) + '</p>'; }
		h += '<p><label for="rapm-cv-size">' + esc( T.size ) + '</label><br><input type="range" id="rapm-cv-size" min="' + lim[ 0 ] + '" max="' + lim[ 1 ] + '" step="0.2" value="' + it.s + '"></p>';
		h += '<p><span>' + esc( 'btn' === it.k ? T.btnColor : T.color ) + '</span><br><span class="rapm-cv-swatches">' + SWATCHES.map( function ( c ) {
			return '<button type="button" class="rapm-cv-sw" data-color="' + c + '" style="background:' + c + '" aria-label="' + esc( c ) + '" aria-pressed="' + ( c === it.c ) + '"></button>';
		} ).join( '' ) + '<input type="color" id="rapm-cv-color" value="' + esc( it.c ) + '" aria-label="' + esc( T.otherColor ) + '"></span></p>';
		if ( 'icon' === it.k ) { h += '<p><button type="button" class="button-link rapm-cv-remove">' + esc( T.remove ) + '</button></p>'; }
		selBox.innerHTML = h;
	}

	function renderBg() {
		var hasPic = !! root.getAttribute( 'data-picture' );
		var picBtn = root.querySelector( '[data-bgmode="pic"]' );
		picBtn.disabled = ! hasPic;
		picBtn.setAttribute( 'aria-pressed', String( !! ( state.pic && hasPic ) ) );
		root.querySelector( '[data-bgmode="color"]' ).setAttribute( 'aria-pressed', String( ! ( state.pic && hasPic ) ) );
		root.querySelector( '.rapm-cv-bgcolors' ).hidden = !! ( state.pic && hasPic );
		root.querySelector( '.rapm-cv-nopic' ).hidden = hasPic;
		Array.prototype.forEach.call( root.querySelectorAll( '[data-bg]' ), function ( b ) { b.setAttribute( 'aria-pressed', String( b.getAttribute( 'data-bg' ) === state.bg ) ); } );
		root.querySelector( '#rapm-cv-bgcolor' ).value = /^#[0-9a-f]{6}$/i.test( state.bg ) ? state.bg : '#f3ead8';
	}

	/* ---- Dragging ------------------------------------------------------- */

	var drag = null;
	board.addEventListener( 'pointerdown', function ( e ) {
		var n = e.target.closest( '.rapm-cv-item' );
		if ( ! n ) { selected = -1; render(); return; }
		e.preventDefault();
		selected = +n.getAttribute( 'data-i' );
		var it = state.items[ selected ];
		var r = board.getBoundingClientRect();
		drag = { it: it, r: r, dx: e.clientX - ( r.left + it.x / 100 * r.width ), dy: e.clientY - ( r.top + it.y / 100 * r.height ) };
		board.setPointerCapture( e.pointerId );
		render();
		stage.focus( { preventScroll: true } );
	} );
	board.addEventListener( 'pointermove', function ( e ) {
		if ( ! drag ) { return; }
		var x = ( e.clientX - drag.dx - drag.r.left ) / drag.r.width * 100;
		var y = ( e.clientY - drag.dy - drag.r.top ) / drag.r.height * 100;
		x = Math.max( 0, Math.min( 100, x ) );
		y = Math.max( 0, Math.min( 100, y ) );
		var sx = Math.abs( x - 50 ) < 1.5, sy = Math.abs( y - 50 ) < 1.5;
		if ( sx ) { x = 50; }
		if ( sy ) { y = 50; }
		gv.hidden = ! sx;
		gh.hidden = ! sy;
		drag.it.x = Math.round( x * 10 ) / 10;
		drag.it.y = Math.round( y * 10 ) / 10;
		var n = board.querySelector( '.rapm-cv-item[data-i="' + selected + '"]' );
		if ( n ) { place( n, drag.it ); }
	} );
	function stop() {
		if ( ! drag ) { return; }
		drag = null;
		gv.hidden = gh.hidden = true;
		save();
	}
	board.addEventListener( 'pointerup', stop );
	board.addEventListener( 'pointercancel', stop );

	stage.addEventListener( 'keydown', function ( e ) {
		var it = state.items[ selected ];
		if ( ! it || ! /^Arrow/.test( e.key ) ) { return; }
		e.preventDefault();
		var d = e.shiftKey ? 5 : 1;
		if ( 'ArrowLeft' === e.key ) { it.x = Math.max( 0, it.x - d ); }
		if ( 'ArrowRight' === e.key ) { it.x = Math.min( 100, it.x + d ); }
		if ( 'ArrowUp' === e.key ) { it.y = Math.max( 0, it.y - d ); }
		if ( 'ArrowDown' === e.key ) { it.y = Math.min( 100, it.y + d ); }
		render();
		save();
	} );

	/* ---- Icons ---------------------------------------------------------- */

	function loadList() {
		if ( list ) { return Promise.resolve( list ); }
		return fetch( root.getAttribute( 'data-icons' ) ).then( function ( r ) { return r.json(); } ).then( function ( j ) {
			list = j.icons;
			return list;
		} );
	}

	function showIcons( q ) {
		q = ( q || '' ).trim().toLowerCase();
		var names;
		if ( ! q || ! list ) {
			names = STARTERS;
		} else {
			// Icons whose name has the word come first, then ones tagged
			// with it; both keep Google's most-used-first order.
			var byName = [], byTag = [];
			for ( var i = 0; i < list.length && byName.length < 60; i++ ) {
				if ( list[ i ][ 0 ].replace( /_/g, ' ' ).indexOf( q ) !== -1 ) {
					byName.push( list[ i ][ 0 ] );
				} else if ( byTag.length < 60 && ( ' ' + list[ i ][ 1 ] ).indexOf( ' ' + q ) !== -1 ) {
					byTag.push( list[ i ][ 0 ] );
				}
			}
			names = byName.concat( byTag ).slice( 0, 60 );
		}
		grid.innerHTML = names.length ? names.map( function ( n ) {
			return '<button type="button" class="rapm-cv-ic" data-icon="' + esc( n ) + '" title="' + esc( n.replace( /_/g, ' ' ) ) + '"><img src="' + ICON_URL + encodeURIComponent( n ) + '/default/48px.svg" alt="" loading="lazy" width="26" height="26"><span>' + esc( n.replace( /_/g, ' ' ) ) + '</span></button>';
		} ).join( '' ) : '<p class="description">' + esc( T.noIcons ) + '</p>';
	}

	function addIcon( name, btn ) {
		btn.disabled = true;
		fetch( ICON_URL + encodeURIComponent( name ) + '/default/48px.svg' ).then( function ( r ) {
			if ( ! r.ok ) { throw new Error( 'icon' ); }
			return r.text();
		} ).then( function ( text ) {
			var m = text.match( /<path[^>]*\sd="([^"]+)"/ );
			if ( ! m ) { throw new Error( 'icon' ); }
			state.items.push( { k: 'icon', n: name, d: m[ 1 ], x: 50, y: 50, s: 10, c: '#1d3557' } );
			selected = state.items.length - 1;
			render();
			save();
		} ).catch( function () {
			window.alert( T.iconFailed ); // eslint-disable-line no-alert
		} ).then( function () { btn.disabled = false; } );
	}

	var searchTimer = 0;
	search.addEventListener( 'focus', function () { loadList().then( function () { showIcons( search.value ); } ); } );
	search.addEventListener( 'input', function () {
		clearTimeout( searchTimer );
		searchTimer = setTimeout( function () { loadList().then( function () { showIcons( search.value ); } ); }, 150 );
	} );

	/* ---- Clicks and changes -------------------------------------------- */

	root.addEventListener( 'click', function ( e ) {
		var b = e.target.closest( 'button' );
		if ( ! b || ! root.contains( b ) ) { return; }
		if ( b.hasAttribute( 'data-icon' ) ) { addIcon( b.getAttribute( 'data-icon' ), b ); return; }
		if ( b.classList.contains( 'rapm-cv-layer' ) ) { selected = +b.getAttribute( 'data-i' ); render(); return; }
		if ( b.hasAttribute( 'data-color' ) ) { state.items[ selected ].c = b.getAttribute( 'data-color' ); render(); save(); return; }
		if ( b.hasAttribute( 'data-bg' ) ) { state.bg = b.getAttribute( 'data-bg' ); state.pic = 0; render(); save(); return; }
		if ( b.hasAttribute( 'data-bgmode' ) ) { state.pic = 'pic' === b.getAttribute( 'data-bgmode' ) ? 1 : 0; render(); save(); return; }
		if ( b.classList.contains( 'rapm-cv-remove' ) ) { state.items.splice( selected, 1 ); selected = -1; render(); save(); }
	} );

	root.addEventListener( 'input', function ( e ) {
		var it = state.items[ selected ];
		if ( 'rapm-cv-size' === e.target.id && it ) {
			it.s = +e.target.value;
			var n = board.querySelector( '.rapm-cv-item[data-i="' + selected + '"]' );
			if ( n ) { place( n, it ); }
			field.value = JSON.stringify( state );
		}
		if ( 'rapm-cv-color' === e.target.id && it ) {
			it.c = e.target.value;
			var m = board.querySelector( '.rapm-cv-item[data-i="' + selected + '"]' );
			if ( m ) { place( m, it ); }
			field.value = JSON.stringify( state );
		}
		if ( 'rapm-cv-bgcolor' === e.target.id ) {
			state.bg = e.target.value;
			state.pic = 0;
			stage.style.background = state.bg;
			field.value = JSON.stringify( state );
		}
	} );
	root.addEventListener( 'change', function ( e ) {
		if ( /^rapm-cv-(size|color|bgcolor)$/.test( e.target.id ) ) { render(); save(); }
	} );

	// The words, typeface and style come from the form's own fields.
	[ 'rapm_headline', 'rapm_subhead', 'rapm_cta_text', 'rapm_text_font', 'rapm_text_style' ].forEach( function ( id ) {
		var el = document.getElementById( id );
		if ( el ) { el.addEventListener( 'input', render ); el.addEventListener( 'change', render ); }
	} );

	// A picture chosen in step 1 can be the background, before it's saved.
	var file = document.getElementById( 'rapm_image_desktop' );
	if ( file ) {
		file.addEventListener( 'change', function () {
			if ( file.files && file.files[ 0 ] ) {
				root.setAttribute( 'data-picture', URL.createObjectURL( file.files[ 0 ] ) );
				state.pic = 1;
				render();
				save();
			}
		} );
	}

	showIcons( '' );
	render();
	save();
} )();
