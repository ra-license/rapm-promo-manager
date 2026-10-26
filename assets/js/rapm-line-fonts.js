/**
 * A font for each line of words on the picture (1.62.0, RAPM_Line_Fonts).
 *
 * Each line (headline, smaller line, button) has a hidden input holding ''
 * (the website's default), 'el:<id>' (an Elementor font) or 'gf:<Family>'
 * (a Google Font) and a button that opens one shared list: the website's
 * fonts, then Google Fonts from the bundled list, searchable. Picking one
 * updates the Live Preview. Google Fonts load from Google here, in the
 * editor only; the website uses the copy made when the promotion is saved.
 */
( function () {
	var root = document.querySelector( '.rapm-lf' );
	if ( ! root ) {
		return;
	}
	var site = JSON.parse( root.getAttribute( 'data-site' ) || '{}' );
	var T = JSON.parse( root.getAttribute( 'data-text' ) || '{}' );
	var kind = root.getAttribute( 'data-kind' );
	var pick = root.querySelector( '.rapm-lf-pick' );
	var list = pick.querySelector( '.rapm-lf-list' );
	var search = pick.querySelector( '.rapm-lf-search' );
	var all = pick.querySelector( '.rapm-lf-all' );
	var gList = null;
	var loaded = {};
	var open = null; // The line whose list is open.

	// Where each line shows in the Live Preview, per kind.
	var PREVIEW = {
		head: '#rapm-preview-headline, .rapm-ed-rv .rapm-rv-word',
		sub: '#rapm-preview-subhead, .rapm-ed-rv .rapm-rv-line',
		btn: '#rapm-preview-cta, .rapm-ed-rv .rapm-rv-go'
	};

	function esc( t ) {
		return String( t ).replace( /[&<>"]/g, function ( c ) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[ c ]; } );
	}

	function input( line ) {
		return document.getElementById( 'rapm_font_' + line );
	}

	function family( value ) {
		if ( 0 === value.indexOf( 'gf:' ) ) {
			return value.slice( 3 );
		}
		if ( 0 === value.indexOf( 'el:' ) && site[ value.slice( 3 ) ] ) {
			return site[ value.slice( 3 ) ][ 1 ];
		}
		return '';
	}

	function name( value ) {
		if ( 0 === value.indexOf( 'gf:' ) ) {
			return value.slice( 3 );
		}
		if ( 0 === value.indexOf( 'el:' ) && site[ value.slice( 3 ) ] ) {
			return site[ value.slice( 3 ) ][ 0 ];
		}
		return T.siteDefault;
	}

	function css( fam ) {
		return fam ? '"' + fam + '", sans-serif' : '';
	}

	// Loads families from Google for this editor. With text, only those letters.
	function load( families, text ) {
		families = families.filter( function ( f ) { return f && ! loaded[ f + ( text ? '|s' : '' ) ]; } );
		if ( ! families.length ) {
			return;
		}
		families.forEach( function ( f ) { loaded[ f + ( text ? '|s' : '' ) ] = true; } );
		var link = document.createElement( 'link' );
		link.rel = 'stylesheet';
		link.href = 'https://fonts.googleapis.com/css2?' + families.map( function ( f ) {
			return 'family=' + encodeURIComponent( f ).replace( /%20/g, '+' ) + ( text ? '' : ':wght@400;700;800' );
		} ).join( '&' ) + '&display=swap' + ( text ? '&text=' + encodeURIComponent( text ) : '' );
		document.head.appendChild( link );
	}

	function googleList() {
		if ( gList ) {
			return Promise.resolve( gList );
		}
		return fetch( root.getAttribute( 'data-gfonts' ) ).then( function ( r ) { return r.json(); } ).then( function ( j ) {
			gList = j.fonts;
			return gList;
		} );
	}

	// The line's button and its words in the Live Preview.
	function show( line ) {
		var value = input( line ).value;
		var fam = family( value );
		var btn = root.querySelector( '.rapm-lf-line[data-line="' + line + '"] .rapm-lf-btn' );
		btn.textContent = name( value );
		btn.style.fontFamily = css( fam );
		if ( 0 === value.indexOf( 'gf:' ) ) {
			load( [ fam ] );
		}
		if ( 'look' !== kind ) { // A look's preview shows its words under the photo, in the website's font.
			document.querySelectorAll( PREVIEW[ line ] ).forEach( function ( el ) { el.style.fontFamily = css( fam ); } );
		}
	}

	function option( value, label, fam, on ) {
		return '<button type="button" class="rapm-lf-font" data-value="' + esc( value ) + '" aria-pressed="' + on + '">' +
			'<span class="rapm-lf-sample" style="font-family:' + esc( css( fam ) || 'inherit' ) + '">' + esc( T.sample ) + '</span>' +
			'<span class="rapm-lf-name">' + esc( label ) + '</span></button>';
	}

	function render() {
		if ( ! open ) {
			return;
		}
		var current = input( open ).value;
		var q = search.value.trim().toLowerCase();
		var html = '<p class="rapm-lf-group">' + esc( T.siteGroup ) + '</p><div class="rapm-lf-fonts">';
		var siteFams = [];
		if ( ! q || T.siteDefault.toLowerCase().indexOf( q ) !== -1 ) {
			html += option( '', T.siteDefault, '', '' === current );
		}
		Object.keys( site ).forEach( function ( id ) {
			var s = site[ id ];
			if ( q && ( s[ 0 ] + ' ' + s[ 1 ] ).toLowerCase().indexOf( q ) === -1 ) {
				return;
			}
			siteFams.push( s[ 1 ] );
			html += option( 'el:' + id, s[ 1 ] ? s[ 0 ] + ' (' + s[ 1 ] + ')' : s[ 0 ], s[ 1 ], 'el:' + id === current );
		} );
		html += '</div><p class="rapm-lf-group">' + esc( T.googleGroup ) + ( gList ? ' · ' + gList.length.toLocaleString() : '' ) + '</p><div class="rapm-lf-fonts">';
		var shown = [];
		if ( gList ) {
			for ( var i = 0; i < gList.length && shown.length < ( q ? 60 : 40 ); i++ ) {
				if ( ! q || gList[ i ][ 0 ].toLowerCase().indexOf( q ) !== -1 ) {
					shown.push( gList[ i ][ 0 ] );
				}
			}
		}
		html += shown.map( function ( f ) { return option( 'gf:' + f, f, f, 'gf:' + f === current ); } ).join( '' );
		html += '</div>';
		if ( gList && ! shown.length ) {
			html += '<p class="description">' + esc( T.none ) + '</p>';
		}
		list.innerHTML = html;
		load( siteFams.concat( shown ), T.sample );
		all.textContent = T.useAll.replace( '%s', name( current ) );
	}

	function close() {
		if ( open ) {
			root.querySelector( '.rapm-lf-line[data-line="' + open + '"] .rapm-lf-btn' ).setAttribute( 'aria-expanded', 'false' );
		}
		open = null;
		pick.hidden = true;
	}

	function toggle( line ) {
		if ( open === line ) {
			close();
			return;
		}
		close();
		open = line;
		var row = root.querySelector( '.rapm-lf-line[data-line="' + line + '"]' );
		row.querySelector( '.rapm-lf-btn' ).setAttribute( 'aria-expanded', 'true' );
		row.after( pick );
		pick.hidden = false;
		search.value = '';
		render();
		googleList().then( render );
		search.focus();
	}

	function set( line, value ) {
		var el = input( line );
		el.value = value;
		el.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		show( line );
	}

	root.addEventListener( 'click', function ( e ) {
		var b = e.target.closest( 'button' );
		if ( ! b ) {
			return;
		}
		if ( b.classList.contains( 'rapm-lf-btn' ) ) {
			toggle( b.closest( '.rapm-lf-line' ).getAttribute( 'data-line' ) );
		} else if ( b.classList.contains( 'rapm-lf-font' ) && open ) {
			set( open, b.getAttribute( 'data-value' ) );
			render();
		} else if ( b === all && open ) {
			var value = input( open ).value;
			root.querySelectorAll( '.rapm-lf-line' ).forEach( function ( row ) { set( row.getAttribute( 'data-line' ), value ); } );
			render();
		}
	} );

	var timer = 0;
	search.addEventListener( 'input', function () {
		clearTimeout( timer );
		timer = setTimeout( render, 150 );
	} );
	pick.addEventListener( 'keydown', function ( e ) {
		if ( 'Escape' === e.key && open ) {
			var line = open;
			close();
			root.querySelector( '.rapm-lf-line[data-line="' + line + '"] .rapm-lf-btn' ).focus();
		}
	} );

	function showAll() {
		root.querySelectorAll( '.rapm-lf-line' ).forEach( function ( row ) { show( row.getAttribute( 'data-line' ) ); } );
	}
	showAll();
	// The Reveal panel preview is drawn after this runs; set its fonts once it's there.
	window.addEventListener( 'load', showAll );
}() );
