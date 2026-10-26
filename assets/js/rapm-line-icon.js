/**
 * An icon with the words on the picture (1.63.0, RAPM_Line_Icon).
 *
 * Where it goes is a radio choice; which icon comes from the same three
 * places as the promotion box canvas: Google's Material Symbols (the
 * drawing is fetched from Google here, in the editor, and saved with the
 * promotion), Font Awesome Free (Elementor's own copy) or a picture from
 * the Media Library. The hidden rapm_icon holds the choice as JSON, and
 * the Live Preview shows it beside the words.
 */
( function () {
	var root = document.querySelector( '.rapm-lic' );
	if ( ! root ) {
		return;
	}
	var T = JSON.parse( root.getAttribute( 'data-text' ) || '{}' );
	var input = document.getElementById( 'rapm_icon' );
	var pick = root.querySelector( '.rapm-lic-pick' );
	var grid = root.querySelector( '.rapm-lic-grid' );
	var search = root.querySelector( '.rapm-lic-search' );
	var now = root.querySelector( '.rapm-lic-now-icon' );
	var nowLabel = root.querySelector( '.rapm-lic-now-label' );
	var faBase = root.getAttribute( 'data-fa' ) || '';
	var ICON_URL = 'https://fonts.gstatic.com/s/i/short-term/release/materialsymbolsoutlined/';
	var MS_BOX = '0 -960 960 960';
	// The same first icons as the box canvas: what a furniture store reaches for.
	var STARTERS = [ 'weekend', 'chair', 'bed', 'king_bed', 'table_restaurant', 'chair_alt', 'living', 'dining', 'sell', 'savings', 'percent', 'local_offer', 'local_shipping', 'credit_card', 'redeem', 'verified', 'star', 'workspace_premium', 'calendar_month', 'schedule', 'storefront', 'home', 'favorite', 'celebration' ];
	var FA_STARTERS = [ 'couch', 'bed', 'chair', 'tv', 'lightbulb', 'home', 'store', 'truck', 'shipping-fast', 'tag', 'tags', 'percent', 'percentage', 'dollar-sign', 'credit-card', 'gift', 'star', 'award', 'medal', 'certificate', 'check-circle', 'calendar-alt', 'clock', 'heart', 'thumbs-up', 'phone', 'map-marker-alt' ];
	var FA_WORDS = { sofa: 'couch', loveseat: 'couch', sectional: 'couch', mattress: 'bed', bedroom: 'bed', recliner: 'chair', delivery: 'truck shipping-fast', shipping: 'truck shipping-fast', sale: 'tag tags percent percentage', discount: 'percent percentage tag', deal: 'tag tags', financing: 'credit-card money-check-alt', payment: 'credit-card', price: 'tag dollar-sign', gift: 'gift', award: 'award medal trophy', quality: 'award certificate check-circle', store: 'store store-alt', showroom: 'store', calendar: 'calendar-alt calendar', event: 'calendar-alt' };
	var tab = 'ms';
	var list = null;
	var faList = null;
	var frame = null;
	var icon = null;
	try {
		icon = input.value ? JSON.parse( input.value ) : null;
	} catch ( err ) {
		icon = null;
	}

	function esc( t ) {
		return String( t ).replace( /[&<>"]/g, function ( c ) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[ c ]; } );
	}

	function where() {
		var on = root.querySelector( 'input[name="rapm_icon_where"]:checked' );
		return on ? on.value : '';
	}

	function svg( d, v ) {
		return '<svg viewBox="' + esc( v ) + '" focusable="false" aria-hidden="true"><path d="' + esc( d ) + '"/></svg>';
	}

	// The icon as the website draws it (RAPM_Line_Icon::html()).
	function markup( ic, at ) {
		var box = 'above' === at
			? 'display:block;width:0.9em;height:0.9em;margin:0 auto 0.2em;'
			: 'display:inline-block;width:1em;height:1em;vertical-align:-0.14em;margin-right:0.35em;';
		if ( 'img' === ic.f ) {
			return ic.u ? '<img class="rapm-li rapm-li-pv is-img" src="' + esc( ic.u ) + '" alt="" style="' + box + 'object-fit:contain;">' : '';
		}
		return '<span class="rapm-li rapm-li-pv" aria-hidden="true" style="' + box + '"><svg viewBox="' + esc( ic.v || MS_BOX ) + '" focusable="false" style="display:block;width:100%;height:100%;fill:currentColor;"><path d="' + esc( ic.d ) + '"/></svg></span>';
	}

	// Puts the icon beside the words in the Live Preview (after they're redrawn).
	function paint() {
		document.querySelectorAll( '.rapm-li-pv' ).forEach( function ( n ) { n.remove(); } );
		var at = where();
		if ( ! icon || ! at ) {
			return;
		}
		var html = markup( icon, at );
		var spots = {
			head: [ '#rapm-preview-headline' ],
			btn: [ '#rapm-preview-cta' ],
			above: [ '.rapm-ed-rv .rapm-rv-word' ]
		}[ at ] || [];
		spots.forEach( function ( sel ) {
			document.querySelectorAll( sel ).forEach( function ( el ) {
				el.insertAdjacentHTML( 'above' === at ? 'beforebegin' : 'afterbegin', html );
			} );
		} );
	}

	function store() {
		var at = where();
		if ( icon && at ) {
			icon.w = at;
			input.value = JSON.stringify( icon );
		} else {
			input.value = '';
		}
		now.innerHTML = icon && at ? markup( icon, 'head' ).replace( 'rapm-li-pv', '' ) : '';
		nowLabel.textContent = icon ? T.picked : T.pickOne;
		input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		paint();
	}

	function loadList() {
		if ( list ) {
			return Promise.resolve( list );
		}
		return fetch( root.getAttribute( 'data-icons' ) ).then( function ( r ) { return r.json(); } ).then( function ( j ) {
			list = j.icons;
			return list;
		} );
	}

	// Elementor's Font Awesome Free: { icons: { name: [ width, height, ligatures, unicode, path ] } }.
	function loadFa() {
		if ( faList ) {
			return Promise.resolve( faList );
		}
		var styles = [ [ 'fas', 'solid' ], [ 'far', 'regular' ], [ 'fab', 'brands' ] ];
		return Promise.all( styles.map( function ( s ) {
			return fetch( faBase + s[ 1 ] + '.json' ).then( function ( r ) { return r.ok ? r.json() : { icons: {} }; } ).then( function ( j ) {
				return Object.keys( j.icons || {} ).map( function ( n ) {
					var v = j.icons[ n ];
					return [ n, s[ 0 ], v[ 0 ], v[ 1 ], 'string' === typeof v[ 4 ] ? v[ 4 ] : '' ];
				} ).filter( function ( x ) { return x[ 4 ]; } );
			} );
		} ) ).then( function ( parts ) {
			faList = parts[ 0 ].concat( parts[ 1 ], parts[ 2 ] );
			return faList;
		} );
	}

	function showMs( q ) {
		q = ( q || '' ).trim().toLowerCase();
		var names;
		if ( ! q || ! list ) {
			names = STARTERS;
		} else {
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
			return '<button type="button" class="rapm-cv-ic" data-icon="' + esc( n ) + '" title="' + esc( n.replace( /_/g, ' ' ) ) + '" aria-pressed="' + ( !! icon && 'ms' === icon.f && n === icon.n ) + '"><img src="' + ICON_URL + encodeURIComponent( n ) + '/default/48px.svg" alt="" loading="lazy" width="26" height="26"><span>' + esc( n.replace( /_/g, ' ' ) ) + '</span></button>';
		} ).join( '' ) : '<p class="description">' + esc( T.noIcons ) + '</p>';
	}

	function showFa( q ) {
		q = ( q || '' ).trim().toLowerCase();
		var found = [];
		if ( ! q ) {
			FA_STARTERS.forEach( function ( n ) {
				for ( var i = 0; i < faList.length; i++ ) {
					if ( faList[ i ][ 0 ] === n ) {
						found.push( i );
						return;
					}
				}
			} );
		} else {
			var extra = [];
			Object.keys( FA_WORDS ).forEach( function ( w ) {
				if ( 0 === w.indexOf( q ) ) {
					extra = extra.concat( FA_WORDS[ w ].split( ' ' ) );
				}
			} );
			for ( var i = 0; i < faList.length && found.length < 60; i++ ) {
				if ( -1 !== extra.indexOf( faList[ i ][ 0 ] ) ) {
					found.push( i );
				}
			}
			for ( var j = 0; j < faList.length && found.length < 60; j++ ) {
				if ( -1 === found.indexOf( j ) && faList[ j ][ 0 ].replace( /-/g, ' ' ).indexOf( q ) !== -1 ) {
					found.push( j );
				}
			}
		}
		grid.innerHTML = found.length ? found.map( function ( i ) {
			var f = faList[ i ];
			var name = f[ 0 ].replace( /-/g, ' ' ) + ( 'far' === f[ 1 ] ? ' (outline)' : '' );
			return '<button type="button" class="rapm-cv-ic" data-fa="' + i + '" title="' + esc( name ) + '" aria-pressed="' + ( !! icon && f[ 1 ] === icon.f && f[ 0 ] === icon.n ) + '">' + svg( f[ 4 ], '0 0 ' + f[ 2 ] + ' ' + f[ 3 ] ) + '<span>' + esc( name ) + '</span></button>';
		} ).join( '' ) : '<p class="description">' + esc( T.noIcons ) + '</p>';
	}

	function refresh() {
		if ( 'fa' === tab ) {
			loadFa().then( function () { showFa( search.value ); } );
		} else if ( 'ms' === tab ) {
			loadList().then( function () { showMs( search.value ); } );
		}
	}

	function showTab( which ) {
		tab = which;
		root.querySelectorAll( '[data-tab]' ).forEach( function ( b ) { b.setAttribute( 'aria-pressed', String( b.getAttribute( 'data-tab' ) === which ) ); } );
		search.hidden = 'media' === which;
		grid.hidden = 'media' === which;
		if ( 'media' === which ) {
			pickPicture();
		} else {
			grid.innerHTML = '';
			refresh();
		}
	}

	function pickPicture() {
		if ( ! window.wp || ! window.wp.media ) {
			return;
		}
		if ( ! frame ) {
			frame = window.wp.media( { title: T.pickPicture, button: { text: T.usePicture }, library: { type: 'image' }, multiple: false } );
			frame.on( 'select', function () {
				var a = frame.state().get( 'selection' ).first().toJSON();
				var size = a.sizes && ( a.sizes.thumbnail || a.sizes.medium || a.sizes.full );
				icon = { f: 'img', a: a.id, u: size ? size.url : a.url };
				store();
			} );
			frame.on( 'close', function () { showTab( 'ms' ); } );
		}
		frame.open();
	}

	function addMs( name, btn ) {
		btn.disabled = true;
		fetch( ICON_URL + encodeURIComponent( name ) + '/default/48px.svg' ).then( function ( r ) {
			if ( ! r.ok ) {
				throw new Error( 'icon' );
			}
			return r.text();
		} ).then( function ( text ) {
			var m = text.match( /<path[^>]*\sd="([^"]+)"/ );
			if ( ! m ) {
				throw new Error( 'icon' );
			}
			icon = { f: 'ms', n: name, d: m[ 1 ], v: MS_BOX };
			store();
			refresh();
		} ).catch( function () {
			window.alert( T.iconFailed ); // eslint-disable-line no-alert
		} ).then( function () { btn.disabled = false; } );
	}

	root.addEventListener( 'change', function ( e ) {
		if ( 'rapm_icon_where' === e.target.name ) {
			pick.hidden = ! where();
			if ( where() && ! grid.innerHTML ) {
				refresh();
			}
			store();
		}
	} );
	root.addEventListener( 'click', function ( e ) {
		var b = e.target.closest( 'button' );
		if ( ! b ) {
			return;
		}
		if ( b.hasAttribute( 'data-tab' ) ) {
			showTab( b.getAttribute( 'data-tab' ) );
		} else if ( b.hasAttribute( 'data-icon' ) ) {
			addMs( b.getAttribute( 'data-icon' ), b );
		} else if ( b.hasAttribute( 'data-fa' ) && faList ) {
			var f = faList[ +b.getAttribute( 'data-fa' ) ];
			icon = { f: f[ 1 ], n: f[ 0 ], d: f[ 4 ], v: '0 0 ' + f[ 2 ] + ' ' + f[ 3 ] };
			store();
			refresh();
		}
	} );
	var timer = 0;
	search.addEventListener( 'input', function () {
		clearTimeout( timer );
		timer = setTimeout( refresh, 150 );
	} );

	// The preview redraws the words as they're typed; put the icon back after.
	document.addEventListener( 'input', function () { setTimeout( paint, 0 ); } );
	document.addEventListener( 'change', function ( e ) {
		if ( e.target !== input ) {
			setTimeout( paint, 0 );
		}
	} );
	if ( where() ) {
		refresh();
	}
	paint();
	window.addEventListener( 'load', paint );
}() );
