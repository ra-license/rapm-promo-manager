/**
 * Brand kit tab of the Design Center (1.50.0).
 *
 * Fills in RAPM_Brand_Kit::render() from the #rapm-kit-data JSON and saves
 * the whole kit (colors, styles, logos, this store's templates) through
 * rapm_brand_kit_save a moment after each change. The website's Elementor
 * colors are shown, never changed: the kit doesn't write to Elementor.
 *
 * "Pick colors from a picture" works in this browser only: the picture is
 * drawn on a small canvas and grouped into 8 colors (k-means, started from
 * colors far apart so a small sofa isn't swallowed by a large wall); each
 * mood keeps the 5 that fit it best.
 */
( function () {
	'use strict';

	var root = document.getElementById( 'rapm-kit' );
	var cfg = window.RAPM_BrandKit;
	if ( ! root || ! cfg ) { return; }
	var T = cfg.text;
	var data = JSON.parse( document.getElementById( 'rapm-kit-data' ).textContent || '{}' );
	var kit = {
		colors: data.colors || [],
		styles: data.styles || [],
		logos: data.logos || [],
		templates: ( data.templates || [] ).filter( function ( t ) { return ! t.starter; } ),
	};
	var starters = ( data.templates || [] ).filter( function ( t ) { return t.starter; } );
	var brandFonts = data.fonts || {};
	var statusEl = document.getElementById( 'rapm-kit-status' );
	var gList = null;
	var fontLoaded = {};
	var picked = [];
	var mood = 'colorful';
	var saveTimer = null;

	function esc( t ) {
		return String( t ).replace( /[&<>"]/g, function ( c ) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[ c ]; } );
	}

	function newId() {
		return Math.random().toString( 36 ).slice( 2, 12 ).padEnd( 8, '0' );
	}

	/* ---- Saving ------------------------------------------------------- */

	function payload() {
		return {
			colors: kit.colors,
			styles: kit.styles,
			logos: kit.logos.map( function ( l ) { return l.a; } ),
			templates: kit.templates.map( function ( t ) { return { id: t.id, name: t.name, canvas: t.canvas, texts: t.texts, at: t.at }; } ),
		};
	}

	function queueSave() {
		statusEl.textContent = T.saving;
		clearTimeout( saveTimer );
		saveTimer = setTimeout( saveNow, 600 );
	}

	function saveNow() {
		var fd = new FormData();
		fd.append( 'action', 'rapm_brand_kit_save' );
		fd.append( 'nonce', cfg.nonce );
		fd.append( 'kit', JSON.stringify( payload() ) );
		fetch( cfg.ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' } ).then( function ( r ) { return r.json(); } ).then( function ( res ) {
			if ( ! res || ! res.success ) { throw new Error( res && res.data && res.data.message ? res.data.message : T.failed ); }
			statusEl.textContent = T.saved;
		} ).catch( function ( err ) { statusEl.textContent = err.message || T.failed; } );
	}

	/* ---- Colors ------------------------------------------------------- */

	function renderColors() {
		var site = document.getElementById( 'rapm-kit-site' );
		site.innerHTML = ( data.site || [] ).length ? data.site.map( function ( c ) {
			return '<div class="rapm-kit-sw"><span class="chip" style="background:' + esc( c.hex ) + '"><span class="lk">' + esc( T.linked ) + '</span></span>' + esc( c.name ) + '<code>' + esc( c.hex ) + '</code></div>';
		} ).join( '' ) : '<p class="description">' + esc( T.noSite ) + '</p>';

		var saved = document.getElementById( 'rapm-kit-colors' );
		saved.innerHTML = kit.colors.length ? kit.colors.map( function ( c, i ) {
			return '<div class="rapm-kit-sw"><span class="chip" style="background:' + esc( c.hex ) + '"></span>' +
				'<input type="text" data-cname="' + i + '" value="' + esc( c.name ) + '" maxlength="40" aria-label="' + esc( c.hex ) + '">' +
				'<code>' + esc( c.hex ) + '</code><button type="button" class="button-link button-link-delete" data-cdel="' + i + '">' + esc( T.remove ) + '</button></div>';
		} ).join( '' ) : '<p class="description">' + esc( T.noSaved ) + '</p>';
	}

	function addColor( hex, name ) {
		if ( kit.colors.length >= 40 ) { return; }
		kit.colors.push( { hex: hex.toLowerCase(), name: name || '' } );
	}

	/* ---- Colors from a picture ---------------------------------------- */

	function hsv( hex ) {
		var r = parseInt( hex.substr( 1, 2 ), 16 ) / 255, g = parseInt( hex.substr( 3, 2 ), 16 ) / 255, b = parseInt( hex.substr( 5, 2 ), 16 ) / 255;
		var mx = Math.max( r, g, b ), mn = Math.min( r, g, b );
		return { s: 0 === mx ? 0 : ( mx - mn ) / mx, v: mx };
	}

	function cluster( px, k ) {
		var dist = function ( a, b ) { return ( a[ 0 ] - b[ 0 ] ) * ( a[ 0 ] - b[ 0 ] ) + ( a[ 1 ] - b[ 1 ] ) * ( a[ 1 ] - b[ 1 ] ) + ( a[ 2 ] - b[ 2 ] ) * ( a[ 2 ] - b[ 2 ] ); };
		var cent = [ px[ Math.floor( px.length / 2 ) ].slice() ];
		while ( cent.length < k ) {
			var best = null, bd = -1;
			px.forEach( function ( p ) {
				var m = Math.min.apply( null, cent.map( function ( c ) { return dist( p, c ); } ) );
				if ( m > bd ) { bd = m; best = p; }
			} );
			cent.push( best.slice() );
		}
		for ( var it = 0; it < 12; it++ ) {
			var sums = cent.map( function () { return [ 0, 0, 0, 0 ]; } );
			px.forEach( function ( p ) {
				var b = 0, bd2 = Infinity;
				cent.forEach( function ( c, j ) { var d = dist( p, c ); if ( d < bd2 ) { bd2 = d; b = j; } } );
				sums[ b ][ 0 ] += p[ 0 ]; sums[ b ][ 1 ] += p[ 1 ]; sums[ b ][ 2 ] += p[ 2 ]; sums[ b ][ 3 ]++;
			} );
			cent = cent.map( function ( c, j ) { return sums[ j ][ 3 ] ? [ sums[ j ][ 0 ] / sums[ j ][ 3 ], sums[ j ][ 1 ] / sums[ j ][ 3 ], sums[ j ][ 2 ] / sums[ j ][ 3 ] ] : c; } );
		}
		// Near-repeats (a plain picture has fewer than 8 colors) are dropped.
		var kept = [];
		cent.forEach( function ( c ) {
			if ( kept.every( function ( k2 ) { return dist( c, k2 ) > 600; } ) ) { kept.push( c ); }
		} );
		return kept.map( function ( c ) { return '#' + c.map( function ( v ) { return ( '0' + Math.round( v ).toString( 16 ) ).slice( -2 ); } ).join( '' ); } );
	}

	var MOODS = {
		colorful: function ( a, b ) { return hsv( b ).s - hsv( a ).s; },
		muted: function ( a, b ) { return hsv( a ).s - hsv( b ).s; },
		deep: function ( a, b ) { return hsv( a ).v - hsv( b ).v; },
		light: function ( a, b ) { return hsv( b ).v - hsv( a ).v; },
	};

	function renderPicked() {
		var five = picked.slice().sort( MOODS[ mood ] ).slice( 0, 5 );
		document.getElementById( 'rapm-kit-moods' ).innerHTML = Object.keys( MOODS ).map( function ( m ) {
			return '<button type="button" class="button button-small" data-mood="' + m + '" aria-pressed="' + ( m === mood ) + '">' + esc( T[ m ] ) + '</button>';
		} ).join( ' ' );
		document.getElementById( 'rapm-kit-picked' ).innerHTML = five.map( function ( h ) {
			return '<div class="rapm-kit-sw"><span class="chip" style="background:' + h + '"></span><code>' + h + '</code></div>';
		} ).join( '' );
		document.getElementById( 'rapm-kit-savepicked' ).setAttribute( 'data-hex', five.join( ',' ) );
	}

	function usePicture( url ) {
		var img = new Image();
		img.crossOrigin = 'anonymous';
		img.onload = function () {
			var cv = document.getElementById( 'rapm-kit-canvas' );
			var ctx = cv.getContext( '2d' );
			var r = Math.min( cv.width / img.width, cv.height / img.height );
			ctx.clearRect( 0, 0, cv.width, cv.height );
			ctx.drawImage( img, ( cv.width - img.width * r ) / 2, ( cv.height - img.height * r ) / 2, img.width * r, img.height * r );
			var d;
			try {
				d = ctx.getImageData( 0, 0, cv.width, cv.height ).data;
			} catch ( e ) {
				statusEl.textContent = T.picFailed;
				return;
			}
			var px = [];
			for ( var i = 0; i < d.length; i += 4 * 5 ) {
				if ( d[ i + 3 ] > 200 ) { px.push( [ d[ i ], d[ i + 1 ], d[ i + 2 ] ] ); }
			}
			if ( px.length < 8 ) { statusEl.textContent = T.picFailed; return; }
			picked = cluster( px, 8 );
			document.getElementById( 'rapm-kit-picker' ).hidden = false;
			renderPicked();
		};
		img.onerror = function () { statusEl.textContent = T.picFailed; };
		img.src = url;
	}

	/* ---- Text styles -------------------------------------------------- */

	function loadFont( fam ) {
		if ( ! fam || fontLoaded[ fam ] ) { return; }
		fontLoaded[ fam ] = true;
		var link = document.createElement( 'link' );
		link.rel = 'stylesheet';
		link.href = 'https://fonts.googleapis.com/css2?family=' + encodeURIComponent( fam ).replace( /%20/g, '+' ) + ':wght@400;500;600;700;800;900&display=swap';
		document.head.appendChild( link );
	}

	function familyOf( st ) {
		if ( st.ff ) { return st.ff; }
		if ( st.fg && brandFonts[ st.fg ] ) { return brandFonts[ st.fg ][ 1 ]; }
		return '';
	}

	function previewCss( st ) {
		var fam = familyOf( st );
		return ( fam ? "font-family:'" + esc( fam ) + "',sans-serif;" : '' ) + 'font-weight:' + st.fw + ';color:' + esc( st.c ) + ';' + ( st.tt ? 'text-transform:uppercase;' : '' );
	}

	function renderStyles() {
		var box = document.getElementById( 'rapm-kit-styles' );
		if ( ! kit.styles.length ) { box.innerHTML = '<p class="description">' + esc( T.noStyles ) + '</p>'; return; }
		box.innerHTML = kit.styles.map( function ( st, i ) {
			loadFont( familyOf( st ) );
			var fontSel = '<select data-sf="' + i + '"><option value="">' + esc( T.siteDefault ) + '</option>' + Object.keys( brandFonts ).map( function ( id ) {
				return '<option value="fg:' + esc( id ) + '"' + ( st.fg === id ? ' selected' : '' ) + '>' + esc( brandFonts[ id ][ 0 ] + ' (' + brandFonts[ id ][ 1 ] + ')' ) + '</option>';
			} ).join( '' ) + ( st.ff ? '<option value="ff" selected>' + esc( st.ff ) + '</option>' : '' ) + '</select>';
			return '<div class="rapm-kit-style">' +
				'<div class="preview" style="' + previewCss( st ) + '">' + esc( T.sample ) + '</div>' +
				'<div class="fields">' +
				'<label>' + esc( T.styleName ) + ' <input type="text" data-sn="' + i + '" value="' + esc( st.name ) + '" maxlength="40"></label>' +
				'<label>' + esc( T.font ) + ' ' + fontSel + '</label>' +
				'<label><input type="text" data-sg="' + i + '" list="rapm-kit-gfonts" placeholder="' + esc( T.googleFont ) + '" value="' + esc( st.ff || '' ) + '"></label>' +
				'<label>' + esc( T.weight ) + ' <select data-sw="' + i + '">' + [ 400, 500, 600, 700, 800, 900 ].map( function ( w ) { return '<option' + ( w === st.fw ? ' selected' : '' ) + '>' + w + '</option>'; } ).join( '' ) + '</select></label>' +
				'<label>' + esc( T.size ) + ' <input type="number" data-ss="' + i + '" min="2" max="20" step="0.5" value="' + esc( st.s ) + '"></label>' +
				'<label>' + esc( T.color ) + ' <input type="color" data-sc="' + i + '" value="' + esc( st.c ) + '"></label>' +
				'<label><input type="checkbox" data-st="' + i + '"' + ( st.tt ? ' checked' : '' ) + '> ' + esc( T.caps ) + '</label>' +
				'<button type="button" class="button-link button-link-delete" data-sdel="' + i + '">' + esc( T.remove ) + '</button>' +
				'</div></div>';
		} ).join( '' ) + '<datalist id="rapm-kit-gfonts"></datalist>';
		fillFontList();
	}

	function fillFontList() {
		var dl = document.getElementById( 'rapm-kit-gfonts' );
		if ( ! dl ) { return; }
		var go = function () { dl.innerHTML = gList.map( function ( f ) { return '<option value="' + esc( f[ 0 ] ) + '">'; } ).join( '' ); };
		if ( gList ) { go(); return; }
		fetch( cfg.gfonts ).then( function ( r ) { return r.json(); } ).then( function ( j ) { gList = j.fonts || []; go(); } ).catch( function () {} );
	}

	/* ---- Logos -------------------------------------------------------- */

	function renderLogos() {
		var box = document.getElementById( 'rapm-kit-logos' );
		box.innerHTML = kit.logos.length ? kit.logos.map( function ( l, i ) {
			return '<div class="rapm-kit-logo"><img src="' + esc( l.u ) + '" alt=""><span>' + esc( l.t ) + '</span><button type="button" class="button-link button-link-delete" data-ldel="' + i + '">' + esc( T.remove ) + '</button></div>';
		} ).join( '' ) : '<p class="description">' + esc( T.noLogos ) + '</p>';
	}

	function pickLogos() {
		var frame = wp.media( { title: T.pickLogos, button: { text: T.addLogos }, library: { type: 'image' }, multiple: true } );
		frame.on( 'select', function () {
			frame.state().get( 'selection' ).each( function ( m ) {
				var a = m.toJSON();
				if ( kit.logos.length >= 30 || kit.logos.some( function ( l ) { return l.a === a.id; } ) ) { return; }
				var size = a.sizes && ( a.sizes.medium || a.sizes.full );
				kit.logos.push( { a: a.id, u: size ? size.url : a.url, t: a.title || '' } );
			} );
			renderLogos();
			queueSave();
		} );
		frame.open();
	}

	/* ---- Templates ---------------------------------------------------- */

	function mini( tpl ) {
		var c = tpl.canvas || {};
		var h = '<div class="rapm-pb-item is-design rapm-cv-mini" style="background:' + esc( c.bg || '#f3ead8' ) + '"><div class="rapm-pb-board">';
		( c.items || [] ).forEach( function ( it ) {
			var pos = 'left:' + it.x + '%;top:' + it.y + '%;--s:' + it.s + ';';
			if ( 'btn' === it.k ) { pos += 'background:' + esc( it.c ) + ';color:#fff;'; } else if ( 'img' !== it.k ) { pos += 'color:' + esc( it.c ) + ';'; }
			if ( it.fw ) { pos += 'font-weight:' + it.fw + ';'; }
			if ( it.tt ) { pos += 'text-transform:uppercase;'; }
			var fam = familyOf( it );
			if ( fam ) { loadFont( fam ); pos += "font-family:'" + esc( fam ) + "',sans-serif;"; }
			var inner;
			if ( 'icon' === it.k ) { inner = '<svg viewBox="' + esc( it.v || '0 -960 960 960' ) + '" aria-hidden="true"><path d="' + esc( it.d || '' ) + '"/></svg>'; }
			else if ( 'img' === it.k ) { inner = '<img src="' + esc( it.u || '' ) + '" alt="">'; }
			else { inner = esc( ( tpl.texts || {} )[ it.k ] || '' ); }
			h += '<div class="rapm-pb-t is-' + esc( it.k ) + '" style="' + pos + '">' + inner + '</div>';
		} );
		return h + '</div></div>';
	}

	function renderTemplates() {
		var box = document.getElementById( 'rapm-kit-tpls' );
		var cards = kit.templates.map( function ( t, i ) {
			return '<div class="rapm-cv-tpl">' + mini( t ) + '<div class="rapm-cv-tplcap"><span><input type="text" data-tname="' + i + '" value="' + esc( t.name ) + '" maxlength="60" aria-label="' + esc( T.rename ) + '"><br><small>' + esc( T.thisStore ) + '</small></span>' +
				'<span><button type="button" class="button-link button-link-delete" data-tdel="' + i + '">' + esc( T.remove ) + '</button></span></div></div>';
		} );
		starters.forEach( function ( t ) {
			cards.push( '<div class="rapm-cv-tpl">' + mini( t ) + '<div class="rapm-cv-tplcap"><span><b>' + esc( t.name ) + '</b><br><small>' + esc( T.starter ) + '</small></span></div></div>' );
		} );
		box.innerHTML = ( kit.templates.length ? '' : '<p class="description" style="grid-column:1/-1">' + esc( T.noTpls ) + '</p>' ) + cards.join( '' );
	}

	/* ---- Events ------------------------------------------------------- */

	root.addEventListener( 'click', function ( e ) {
		var b = e.target.closest( 'button' );
		if ( ! b ) { return; }
		var a;
		if ( 'rapm-kit-addcolor' === b.id ) {
			var nameEl = document.getElementById( 'rapm-kit-newname' );
			addColor( document.getElementById( 'rapm-kit-newhex' ).value, nameEl.value.trim() );
			nameEl.value = '';
			renderColors();
			queueSave();
		} else if ( null !== ( a = b.getAttribute( 'data-cdel' ) ) ) {
			kit.colors.splice( +a, 1 );
			renderColors();
			queueSave();
		} else if ( 'rapm-kit-pickpic' === b.id ) {
			var frame = wp.media( { title: T.pickPic, button: { text: T.usePic }, library: { type: 'image' }, multiple: false } );
			frame.on( 'select', function () {
				var m = frame.state().get( 'selection' ).first().toJSON();
				var size = m.sizes && ( m.sizes.medium || m.sizes.full );
				usePicture( size ? size.url : m.url );
			} );
			frame.open();
		} else if ( null !== ( a = b.getAttribute( 'data-mood' ) ) ) {
			mood = a;
			renderPicked();
		} else if ( 'rapm-kit-savepicked' === b.id ) {
			( b.getAttribute( 'data-hex' ) || '' ).split( ',' ).filter( Boolean ).forEach( function ( h, i ) { addColor( h, T.fromPic + ' ' + ( i + 1 ) ); } );
			renderColors();
			queueSave();
		} else if ( 'rapm-kit-addstyle' === b.id ) {
			if ( kit.styles.length >= 30 ) { return; }
			kit.styles.push( { id: newId(), name: T.newStyle, fw: 700, s: 8, c: ( data.site && data.site[ 0 ] && data.site[ 0 ].hex ) || '#1d3557', tt: false } );
			renderStyles();
			queueSave();
		} else if ( null !== ( a = b.getAttribute( 'data-sdel' ) ) ) {
			kit.styles.splice( +a, 1 );
			renderStyles();
			queueSave();
		} else if ( 'rapm-kit-addlogos' === b.id ) {
			pickLogos();
		} else if ( null !== ( a = b.getAttribute( 'data-ldel' ) ) ) {
			kit.logos.splice( +a, 1 );
			renderLogos();
			queueSave();
		} else if ( null !== ( a = b.getAttribute( 'data-tdel' ) ) ) {
			// Asks on the card itself, not with a browser pop-up.
			b.parentNode.innerHTML = '<span class="rapm-cv-tplask">' + esc( T.removeAsk ) + '</span> <button type="button" class="button button-small" data-tyes="' + a + '">' + esc( T.remove ) + '</button> <button type="button" class="button-link" data-tno="1">' + esc( T.cancel ) + '</button>';
		} else if ( null !== ( a = b.getAttribute( 'data-tyes' ) ) ) {
			kit.templates.splice( +a, 1 );
			renderTemplates();
			queueSave();
		} else if ( b.hasAttribute( 'data-tno' ) ) {
			renderTemplates();
		}
	} );

	// Typing updates the preview right away; the list is redrawn on change.
	root.addEventListener( 'input', function ( e ) {
		var t = e.target, a;
		if ( null !== ( a = t.getAttribute( 'data-cname' ) ) ) {
			kit.colors[ +a ].name = t.value;
			queueSave();
		} else if ( null !== ( a = t.getAttribute( 'data-tname' ) ) ) {
			if ( t.value.trim() ) {
				kit.templates[ +a ].name = t.value.trim();
				queueSave();
			}
		} else if ( null !== ( a = t.getAttribute( 'data-sn' ) ) ) {
			kit.styles[ +a ].name = t.value;
			queueSave();
		} else if ( null !== ( a = t.getAttribute( 'data-sc' ) ) ) {
			kit.styles[ +a ].c = t.value;
			t.closest( '.rapm-kit-style' ).querySelector( '.preview' ).style.color = t.value;
			queueSave();
		}
	} );

	root.addEventListener( 'change', function ( e ) {
		var t = e.target, a, st;
		if ( null !== ( a = t.getAttribute( 'data-sf' ) ) ) {
			st = kit.styles[ +a ];
			if ( 'ff' === t.value ) { return; }
			delete st.ff;
			delete st.fg;
			if ( 0 === t.value.indexOf( 'fg:' ) ) { st.fg = t.value.slice( 3 ); }
		} else if ( null !== ( a = t.getAttribute( 'data-sg' ) ) ) {
			st = kit.styles[ +a ];
			var want = t.value.trim().toLowerCase();
			var hit = gList && gList.filter( function ( f ) { return f[ 0 ].toLowerCase() === want; } )[ 0 ];
			delete st.ff;
			if ( hit ) { st.ff = hit[ 0 ]; delete st.fg; }
		} else if ( null !== ( a = t.getAttribute( 'data-sw' ) ) ) {
			kit.styles[ +a ].fw = +t.value;
		} else if ( null !== ( a = t.getAttribute( 'data-ss' ) ) ) {
			kit.styles[ +a ].s = Math.max( 2, Math.min( 20, +t.value || 8 ) );
		} else if ( null !== ( a = t.getAttribute( 'data-st' ) ) ) {
			kit.styles[ +a ].tt = t.checked;
		} else {
			return;
		}
		renderStyles();
		queueSave();
	} );

	renderColors();
	renderStyles();
	renderLogos();
	renderTemplates();
}() );
