/**
 * The one-screen promotion editor (1.57.0, from the mockup Phil approved on
 * 2026-10-22, https://claude.ai/artifact/EL93dNCBz3ip4BvrMDAFgc).
 *
 * - The bar at the top: one chip per part (done, needed, not yet) that
 *   jumps to it, a line saying what's still needed, and Save.
 * - Save checks the same things the server needs before anything is sent,
 *   so a picture chosen on the form is never lost to a rejected save.
 * - One-screen kinds (RAPM_Upload_Handler: $simple): the live preview moves
 *   beside the form and stays in view, with "Ready to go live" checks under
 *   it. Only what the browser can know is checked here; links are checked
 *   after saving (RAPM_Ready).
 * - "More options" folds away line-up, color, style, typeface and "picture
 *   already shows the sale?"; it starts open when one was changed.
 */
( function () {
	'use strict';

	var bar = document.getElementById( 'rapm-ed-bar' );
	if ( ! bar ) {
		return;
	}
	var T    = window.RAPM_Editor_Text || {};
	var cfg  = JSON.parse( bar.getAttribute( 'data-cfg' ) || '{}' );
	var form = bar.closest( 'form' );
	var note = document.getElementById( 'rapm-ed-note' );
	var dims = { desktop: cfg.dims ? cfg.dims.desktop : null, mobile: cfg.dims ? cfg.dims.mobile : null };
	var tried = false;

	function $( id ) {
		return document.getElementById( id );
	}
	function val( id ) {
		var el = $( id );
		return el ? String( el.value || '' ).trim() : '';
	}
	function radio( name ) {
		var r = form.querySelector( 'input[name="' + name + '"]:checked' );
		return r ? r.value : '';
	}
	function fmt( s ) {
		var args = Array.prototype.slice.call( arguments, 1 );
		var i = 0;
		return String( s ).replace( /%(\d)\$s|%s/g, function ( m, n ) {
			return n ? args[ n - 1 ] : args[ i++ ];
		} );
	}
	function size( d ) {
		return d.w + ' × ' + d.h;
	}

	function boxDesign() {
		return 'design' === radio( 'rapm_box_mode' );
	}
	function viaLink( which ) {
		return 'link' === radio( 'rapm_image_' + which + '_source' );
	}
	function chosenFile( which ) {
		var el = $( 'rapm_image_' + which );
		return el && el.files && el.files[ 0 ] ? el.files[ 0 ] : null;
	}
	function hasPicture( which ) {
		if ( 'desktop' === which && cfg.box && boxDesign() ) {
			return true;
		}
		if ( viaLink( which ) ) {
			return !! val( 'rapm_image_' + which + '_url' ) || !! ( cfg.has && cfg.has[ which ] );
		}
		return !! chosenFile( which ) || !! ( cfg.has && cfg.has[ which ] );
	}

	// A chosen file's size, read in the browser, for the checks.
	[ 'desktop', 'mobile' ].forEach( function ( which ) {
		var input = $( 'rapm_image_' + which );
		if ( ! input ) {
			return;
		}
		input.addEventListener( 'change', function () {
			var file = chosenFile( which );
			if ( ! file ) {
				dims[ which ] = cfg.dims ? cfg.dims[ which ] : null;
				update();
				return;
			}
			var url = URL.createObjectURL( file );
			var img = new Image();
			img.onload = function () {
				dims[ which ] = { w: img.naturalWidth, h: img.naturalHeight };
				URL.revokeObjectURL( url );
				update();
			};
			img.src = url;
		} );
	} );

	/* ---- What each part needs ------------------------------------------ */

	function missing() {
		var out = [];
		if ( ! val( 'rapm_title' ) ) {
			out.push( T.aName );
		}
		if ( cfg.look && ! val( 'rapm_tab_label' ) ) {
			out.push( T.aTab );
		}
		if ( cfg.images && ! hasPicture( 'desktop' ) ) {
			out.push( cfg.look ? T.aPhoto : T.aPicture );
		}
		if ( cfg.quick && ! val( 'rapm_headline' ) ) {
			out.push( T.aQuickName );
		}
		return out;
	}

	function wordsDone() {
		return 'image' === radio( 'rapm_text_mode' ) || !! val( 'rapm_headline' );
	}

	function linkDone() {
		var type = val( 'rapm_dest_type' );
		if ( 'url' === type ) {
			return !! val( 'rapm_dest_url' );
		}
		if ( 'search' === type ) {
			return !! val( 'rapm_dest_search_words' );
		}
		if ( 'curated' === type ) {
			return !! val( 'rapm_curated_skus' );
		}
		return !! val( 'rapm_dest_value' );
	}

	function piecesDone() {
		try {
			var dots = JSON.parse( val( 'rapm_dots' ) || '[]' );
			var btn  = JSON.parse( val( 'rapm_photo_btn' ) || '{}' );
			return ( dots && dots.length > 0 ) || !! ( btn && btn.on );
		} catch ( e ) {
			return false;
		}
	}

	/* ---- The bar --------------------------------------------------------- */

	function setChip( key, state ) {
		var chip = bar.querySelector( '[data-chip="' + key + '"]' );
		if ( ! chip ) {
			return;
		}
		chip.setAttribute( 'data-state', state );
		chip.querySelector( '.rapm-ed-sr' ).textContent = ' ' + ( 'done' === state ? T.done : ( 'needed' === state ? T.needed : T.notYet ) );
	}

	/* ---- Ready to go live (one-screen kinds) ----------------------------- */

	function checks() {
		var list = [];
		function add( lvl, text, fix, href ) {
			list.push( { lvl: lvl, text: text, fix: fix || '', href: href || '' } );
		}
		var first = '#rapm-step-1';
		var slots = cfg.slots || {};
		if ( ! hasPicture( 'desktop' ) ) {
			add( 'fail', T.noPic, T.addOne, first );
		} else if ( viaLink( 'desktop' ) && ! chosenFile( 'desktop' ) && ! dims.desktop ) {
			add( 'info', T.picLink );
		} else if ( dims.desktop && slots.desktop && ! slots.desktop.flex ) {
			var d = dims.desktop, s = slots.desktop;
			if ( Math.abs( d.w / d.h - s.w / s.h ) / ( s.w / s.h ) > s.tol ) {
				add( 'warn', fmt( T.picShape, size( d ), size( s ) ), '', first );
			} else if ( d.w < s.w ) {
				add( 'warn', fmt( T.picSmall, size( d ), size( s ) ) );
			} else {
				add( 'pass', fmt( T.picRight, size( s ) ) );
			}
		}
		if ( cfg.phone ) {
			if ( ! hasPicture( 'mobile' ) ) {
				add( 'warn', T.noPhone, T.addOne, first );
			} else if ( dims.mobile && slots.mobile && ! slots.mobile.flex ) {
				var pd = dims.mobile, ps = slots.mobile;
				if ( Math.abs( pd.w / pd.h - ps.w / ps.h ) / ( ps.w / ps.h ) > ps.tol ) {
					add( 'warn', fmt( T.phoneShape, size( pd ), size( ps ) ), '', first );
				}
			}
		}
		if ( hasPicture( 'desktop' ) && $( 'rapm_alt_text' ) && ! val( 'rapm_alt_text' ) ) {
			add( 'warn', T.noAlt, T.addThem, first );
		}
		var words = bar.querySelector( '[data-chip="words"]' );
		var wordsHref = words ? words.getAttribute( 'href' ) : '';
		if ( cfg.quick && ! val( 'rapm_headline' ) ) {
			add( 'fail', T.noQuick, T.addOne, wordsHref );
		} else if ( ! wordsDone() ) {
			add( 'info', T.noHead, T.addOne, wordsHref );
		} else {
			add( 'pass', T.headOk );
		}
		var link = bar.querySelector( '[data-chip="link"]' );
		if ( linkDone() ) {
			add( 'pass', T.linkOk );
		} else {
			add( 'info', T.noLink, T.pickOne, link ? link.getAttribute( 'href' ) : '' );
		}
		var dates = bar.querySelector( '[data-chip="dates"]' );
		if ( ! val( 'rapm_starts_at' ) ) {
			add( 'info', T.noStart, T.setDates, dates ? dates.getAttribute( 'href' ) : '' );
		} else if ( ! val( 'rapm_ends_at' ) ) {
			add( 'info', T.noEnd );
		} else {
			add( 'pass', T.datesOk );
		}
		var order = { fail: 0, warn: 1, info: 2, pass: 3 };
		list.sort( function ( a, b ) {
			return order[ a.lvl ] - order[ b.lvl ];
		} );
		return list;
	}

	var listEl = $( 'rapm-ed-checks' );
	var sumEl  = $( 'rapm-ed-ready-sum' );
	var lastChecks = '';

	function drawChecks() {
		if ( ! listEl ) {
			return;
		}
		var list = checks();
		var key  = JSON.stringify( list );
		if ( key === lastChecks ) {
			return; // Nothing changed: keep the list still for screen readers.
		}
		lastChecks = key;
		var marks  = { fail: '!', warn: '!', info: 'i', pass: '✓' };
		var labels = { fail: T.lvlFail, warn: T.lvlWarn, info: T.lvlInfo, pass: T.lvlPass };
		listEl.innerHTML = '';
		list.forEach( function ( c ) {
			var li = document.createElement( 'li' );
			li.className = 'is-' + c.lvl;
			var badge = document.createElement( 'span' );
			badge.className = 'rapm-ed-badge';
			badge.setAttribute( 'aria-hidden', 'true' );
			badge.textContent = marks[ c.lvl ];
			var text = document.createElement( 'span' );
			var sr = document.createElement( 'span' );
			sr.className = 'screen-reader-text';
			sr.textContent = labels[ c.lvl ] + ' ';
			text.appendChild( sr );
			text.appendChild( document.createTextNode( c.text + ' ' ) );
			if ( c.fix && c.href ) {
				var a = document.createElement( 'a' );
				a.href = c.href;
				a.textContent = c.fix;
				text.appendChild( a );
			}
			li.appendChild( badge );
			li.appendChild( text );
			listEl.appendChild( li );
		} );
		var fails = list.filter( function ( c ) {
			return 'fail' === c.lvl;
		} ).length;
		var warns = list.filter( function ( c ) {
			return 'warn' === c.lvl;
		} ).length;
		sumEl.textContent = fails ? T.sumFail : ( warns ? T.sumWarn : T.sumPass );
	}

	/* ---- Update everything ------------------------------------------------ */

	function joinList( items ) {
		if ( items.length < 2 ) {
			return items.join( '' );
		}
		return items.slice( 0, -1 ).join( ', ' ) + ' ' + T.and + ' ' + items[ items.length - 1 ];
	}

	function update() {
		var need = missing();
		var pictureNeeds = need.filter( function ( m ) {
			return m !== T.aQuickName;
		} );
		setChip( 'picture', pictureNeeds.length ? 'needed' : 'done' );
		setChip( 'pieces', piecesDone() ? 'done' : 'not-yet' );
		setChip( 'words', cfg.quick && ! val( 'rapm_headline' ) ? 'needed' : ( wordsDone() ? 'done' : 'not-yet' ) );
		setChip( 'link', linkDone() ? 'done' : 'not-yet' );
		setChip( 'dates', val( 'rapm_starts_at' ) ? 'done' : 'not-yet' );
		note.textContent = need.length ? fmt( T.toSave, joinList( need ) ) : T.canSave;
		note.classList.toggle( 'is-error', tried && need.length > 0 );
		bar.classList.toggle( 'is-blocked', need.length > 0 );
		drawChecks();
	}

	form.addEventListener( 'input', update );
	form.addEventListener( 'change', update );
	// The pieces editor and the link pickers set hidden fields without events.
	window.setInterval( update, 1500 );

	// Save: check first, so nothing chosen on the form is lost.
	form.addEventListener( 'submit', function ( e ) {
		tried = true;
		var need = missing();
		var piecesOk = ! window.RAPM_LookEditor || ! $( 'rapm_dots' ) || window.RAPM_LookEditor.validate();
		if ( need.length || ! piecesOk ) {
			e.preventDefault();
			update();
			if ( need.length ) {
				var target = $( 'rapm-step-1' );
				if ( cfg.quick && 1 === need.length && need[ 0 ] === T.aQuickName ) {
					target = $( 'rapm_headline' );
				}
				if ( target ) {
					target.scrollIntoView( { behavior: 'smooth', block: 'start' } );
				}
			}
		}
	} );

	/* ---- One-screen kinds: preview beside the form, "More options" ------ */

	if ( cfg.simple ) {
		var preview = $( 'rapm-ed-preview' );
		var card    = $( 'rapm-ed-preview-card' );
		if ( preview && card ) {
			card.appendChild( preview );
		}

		var rows = Array.prototype.slice.call( form.querySelectorAll( 'tr.rapm-more' ) );
		if ( rows.length ) {
			var table = rows[ rows.length - 1 ].closest( 'table' );
			var btn = document.createElement( 'button' );
			btn.type = 'button';
			btn.className = 'button-link rapm-ed-more';
			var open = !! cfg.more;
			function setMore( on ) {
				open = on;
				rows.forEach( function ( r ) {
					r.classList.toggle( 'rapm-more-hidden', ! on );
				} );
				btn.setAttribute( 'aria-expanded', on ? 'true' : 'false' );
				btn.textContent = ( on ? '▾ ' : '▸ ' ) + ( on ? T.fewer : T.more );
			}
			btn.addEventListener( 'click', function () {
				setMore( ! open );
			} );
			table.parentNode.insertBefore( btn, table.nextSibling );
			setMore( open );
		}
	}

	update();
}() );
