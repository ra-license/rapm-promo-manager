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
	// 1.58.2: set by the form's link check when a linked picture would be turned down.
	function linkIsBad( which ) {
		var el = $( 'rapm_image_' + which + '_url' );
		return viaLink( which ) && !! el && '1' === el.getAttribute( 'data-rapm-bad' );
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

	// 1.59.1: a picture this website can't take is caught when it's chosen,
	// with what to do, instead of failing with no message after Save
	// (a file over the server's limit used to arrive empty, or blank the page).
	var fileBad = { desktop: false, mobile: false };
	var OK_TYPES = [ 'jpg', 'jpeg', 'png', 'webp', 'gif' ];
	// 1.59.3: a picture made ready in the browser (smaller, or changed from
	// a type the website can't use) before it's sent.
	var made = { desktop: '', mobile: '' };      // the green note
	var busy = { desktop: false, mobile: false }; // still working on it
	var stillBig = { desktop: 0, mobile: 0 };     // couldn't get it small enough
	function mb( bytes ) {
		return bytes < 1048576 ? Math.max( 1, Math.round( bytes / 1024 ) ) + ' KB' : ( bytes / 1048576 ).toFixed( 1 ).replace( /\.0$/, '' ) + ' MB';
	}
	function fileProblem( which ) {
		var file = chosenFile( which );
		if ( ! file || busy[ which ] ) {
			return '';
		}
		var up0 = cfg.upload || {};
		if ( stillBig[ which ] ) {
			return fmt( T.fileStillBig, file.name, mb( stillBig[ which ] ), up0.maxText );
		}
		var ext = ( /\.([a-z0-9]+)$/i.exec( file.name ) || [ '', '' ] )[ 1 ].toLowerCase();
		var type = String( file.type || '' ).toLowerCase();
		if ( /heic|heif/.test( type ) || 'heic' === ext || 'heif' === ext ) {
			return fmt( T.fileHeic, file.name );
		}
		if ( ! ( /^image\/(jpeg|png|webp|gif)$/.test( type ) || ( ! type && OK_TYPES.indexOf( ext ) !== -1 ) ) ) {
			return fmt( T.fileType, file.name, ext ? ext.toUpperCase() : ( type || '?' ) );
		}
		var up = cfg.upload || {};
		if ( up.max && file.size > up.max ) {
			return fmt( T.fileBig, file.name, mb( file.size ), up.maxText );
		}
		var other = chosenFile( 'desktop' === which ? 'mobile' : 'desktop' );
		if ( up.post && other && file.size + other.size > up.post * 0.95 ) {
			return fmt( T.fileBoth, mb( file.size + other.size ), mb( up.post ) );
		}
		return '';
	}
	function showFileCheck( which ) {
		var box = $( 'rapm-' + which + '-file-check' );
		var file = chosenFile( which );
		var problem = fileProblem( which );
		fileBad[ which ] = !! problem;
		if ( ! box ) {
			return;
		}
		box.hidden = ! file;
		box.className = 'rapm-file-check' + ( problem ? ' is-bad' : ( busy[ which ] ? ' is-busy' : ' is-ok' ) );
		box.textContent = problem || ( busy[ which ] && file ? fmt( T.fileWorking, file.name ) : ( made[ which ] || ( file ? file.name + ' · ' + mb( file.size ) : '' ) ) );
	}
	function fileIsBad( which ) {
		return ! viaLink( which ) && fileBad[ which ];
	}

	// 1.59.3: shrink a picture to the spot's size and save it as WebP (a JPG
	// in Safari, which can't make WebP; the website makes it WebP on save),
	// when it's over this website's upload limit, bigger than the spot needs,
	// or a type the website can't use but this browser can open (like an
	// iPhone HEIC in Safari). It only scales down, never crops: a picture of
	// a different shape keeps its shape, so "Pick the part to keep" still
	// decides what's trimmed. GIFs are left alone (it would stop animation).
	function prepare( which, input ) {
		var file = chosenFile( which );
		if ( ! file || ! window.DataTransfer || ! document.createElement( 'canvas' ).toBlob ) {
			return;
		}
		var ext = ( /\.([a-z0-9]+)$/i.exec( file.name ) || [ '', '' ] )[ 1 ].toLowerCase();
		var type = String( file.type || '' ).toLowerCase();
		if ( 'image/gif' === type || 'gif' === ext ) {
			return;
		}
		var okType = /^image\/(jpeg|png|webp)$/.test( type ) || ( ! type && [ 'jpg', 'jpeg', 'png', 'webp' ].indexOf( ext ) !== -1 );
		var slot = ( cfg.slots || {} )[ which ];
		var up = cfg.upload || {};
		var limit = up.max ? up.max * 0.95 : 0;
		busy[ which ] = true;
		showFileCheck( which );
		update();
		var url = URL.createObjectURL( file );
		var img = new Image();
		function finish() {
			busy[ which ] = false;
			showFileCheck( which );
			update();
		}
		img.onerror = function () {
			URL.revokeObjectURL( url );
			finish(); // the browser can't open it: the type note explains.
		};
		img.onload = function () {
			URL.revokeObjectURL( url );
			var w = img.naturalWidth, h = img.naturalHeight, scale = 1;
			if ( slot && slot.w && w && h ) {
				// Any-shape spots (Shop the Look) fit the width; the others keep
				// enough to cover the spot, so a trim choice still has room.
				scale = slot.flex ? Math.min( 1, slot.w / w ) : Math.min( 1, Math.max( slot.w / w, slot.h / h ) );
			}
			if ( okType && scale >= 1 && ! ( limit && file.size > limit ) ) {
				finish();
				return;
			}
			var cw = Math.max( 1, Math.round( w * scale ) ), ch = Math.max( 1, Math.round( h * scale ) );
			var canvas = document.createElement( 'canvas' );
			canvas.width = cw;
			canvas.height = ch;
			var ctx = canvas.getContext( '2d' );
			var qualities = [ 0.86, 0.8, 0.72, 0.64 ];
			function attempt( i, as ) {
				ctx.clearRect( 0, 0, cw, ch );
				if ( 'image/jpeg' === as ) {
					ctx.fillStyle = '#ffffff'; // JPG has no see-through parts.
					ctx.fillRect( 0, 0, cw, ch );
				}
				ctx.drawImage( img, 0, 0, cw, ch );
				canvas.toBlob( function ( blob ) {
					if ( blob && 'image/webp' === as && 'image/webp' !== blob.type ) {
						attempt( i, 'image/jpeg' ); // Safari makes a PNG when asked for WebP.
						return;
					}
					if ( blob && limit && blob.size > limit && i < qualities.length - 1 ) {
						attempt( i + 1, as );
						return;
					}
					done( blob, as );
				}, as, qualities[ i ] );
			}
			function done( blob, as ) {
				if ( ! blob || ( limit && blob.size > limit ) ) {
					stillBig[ which ] = blob ? blob.size : file.size;
					finish();
					return;
				}
				var label = 'image/webp' === as ? 'WebP' : 'JPG';
				var made1 = fmt( okType ? T.fileShrunk : T.fileConverted, file.name, mb( file.size ), mb( blob.size ), label, cw + ' × ' + ch );
				try {
					var dt = new DataTransfer();
					dt.items.add( new File( [ blob ], file.name.replace( /\.[^.]+$/, '' ) + ( 'image/webp' === as ? '.webp' : '.jpg' ), { type: as } ) );
					input.rapmMade = true;
					input.files = dt.files;
				} catch ( e ) {
					finish(); // can't swap the file in this browser: the size note explains.
					return;
				}
				busy[ which ] = false;
				made[ which ] = made1;
				// Let the preview, size checks and trim picker use the new picture.
				input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
			}
			attempt( 0, 'image/webp' );
		};
		img.src = url;
	}

	// A chosen file's size, read in the browser, for the checks.
	[ 'desktop', 'mobile' ].forEach( function ( which ) {
		var input = $( 'rapm_image_' + which );
		if ( ! input ) {
			return;
		}
		input.addEventListener( 'change', function () {
			if ( input.rapmMade ) {
				input.rapmMade = false; // our own swap (1.59.3): keep its note.
			} else {
				made[ which ] = '';
				stillBig[ which ] = 0;
				prepare( which, input );
			}
			showFileCheck( 'desktop' );
			showFileCheck( 'mobile' );
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
		if ( cfg.images && ( ( hasPicture( 'desktop' ) && linkIsBad( 'desktop' ) ) || linkIsBad( 'mobile' ) ) ) {
			out.push( T.aWorkingPic ); // 1.58.2: the link check found a problem.
		}
		if ( cfg.images && ( fileIsBad( 'desktop' ) || fileIsBad( 'mobile' ) ) ) {
			out.push( T.aWorkingFile ); // 1.59.1: a chosen picture this website can't take.
		}
		if ( cfg.images && ( busy.desktop || busy.mobile ) ) {
			out.push( T.aPicReady ); // 1.59.3: still being made ready.
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
		chip.querySelector( '.rapm-ed-sr' ).textContent = ' ' + ( 'done' === state ? T.done : ( 'needed' === state ? T.needed : ( 'optional' === state ? T.optional : T.notYet ) ) );
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
		if ( 'reveal' === cfg.kind && ! val( 'rapm_headline' ) ) {
			add( 'fail', T.noWord, T.addOne, wordsHref );
		} else if ( cfg.quick && ! val( 'rapm_headline' ) ) {
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

	// 1.58.2: says when it will show, from its start date, instead of a
	// general rule that read as if nothing would show yet.
	function readyLine() {
		var start = val( 'rapm_starts_at' );
		return start && new Date( start ) > new Date() ? T.canSaveLater : T.canSaveNow;
	}

	function update() {
		var need = missing();
		var pictureNeeds = need.filter( function ( m ) {
			return m !== T.aQuickName;
		} );
		setChip( 'picture', pictureNeeds.length ? 'needed' : 'done' );
		setChip( 'pieces', piecesDone() ? 'done' : 'optional' ); // 1.58.1: dots are optional.
		setChip( 'words', cfg.quick && ! val( 'rapm_headline' ) ? 'needed' : ( wordsDone() ? 'done' : 'not-yet' ) );
		setChip( 'link', linkDone() ? 'done' : 'not-yet' );
		setChip( 'dates', val( 'rapm_starts_at' ) ? 'done' : 'not-yet' );
		note.textContent = need.length ? fmt( T.toSave, joinList( need ) ) : readyLine();
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

	/* ---- The live preview beside the form (1.58.1: every kind) ---------- */

	var card = $( 'rapm-ed-preview-card' );
	function heading( text, note ) {
		var h = document.createElement( 'h2' );
		h.textContent = text;
		card.appendChild( h );
		var p = document.createElement( 'p' );
		p.className = 'description';
		p.textContent = note;
		card.appendChild( p );
		return p;
	}
	function toggles( pairs, onPick ) {
		var row = document.createElement( 'p' );
		row.className = 'rapm-ed-toggles';
		pairs.forEach( function ( pair, i ) {
			var b = document.createElement( 'button' );
			b.type = 'button';
			b.className = 'button button-small rapm-preview-toggle-btn';
			b.textContent = pair[ 1 ];
			b.setAttribute( 'aria-pressed', 0 === i ? 'true' : 'false' );
			b.addEventListener( 'click', function () {
				row.querySelectorAll( 'button' ).forEach( function ( o ) { o.setAttribute( 'aria-pressed', o === b ? 'true' : 'false' ); } );
				onPick( pair[ 0 ] );
			} );
			row.appendChild( b );
		} );
		card.appendChild( row );
	}

	if ( card && 'promo_box' === cfg.kind ) {
		// The box: a live copy of the design square, with the website's styles.
		var pvNote = heading( T.pvTitle, T.pvBox );
		var mirror = document.createElement( 'div' );
		mirror.className = 'rapm-pb-item is-design rapm-cv-mini rapm-ed-boxmirror';
		mirror.setAttribute( 'aria-hidden', 'true' );
		card.appendChild( mirror );
		var stage = document.querySelector( '#rapm-canvas .rapm-cv-stage' );
		var copy = function () {
			var design = 'design' === radio( 'rapm_box_mode' );
			mirror.hidden = ! design || ! stage;
			pvNote.textContent = design ? T.pvBox : T.pvGraphic;
			if ( ! design || ! stage ) { return; }
			mirror.innerHTML = stage.innerHTML;
			mirror.style.background = stage.style.background;
			mirror.querySelectorAll( '.rapm-cv-guide' ).forEach( function ( g ) { g.remove(); } );
			mirror.querySelectorAll( '[tabindex]' ).forEach( function ( n ) { n.removeAttribute( 'tabindex' ); } );
			mirror.querySelectorAll( '.is-sel, .is-picked' ).forEach( function ( n ) { n.classList.remove( 'is-sel', 'is-picked' ); } );
		};
		var timer = 0;
		if ( stage && window.MutationObserver ) {
			new MutationObserver( function () {
				window.clearTimeout( timer );
				timer = window.setTimeout( copy, 60 );
			} ).observe( stage, { subtree: true, childList: true, attributes: true, characterData: true } );
		}
		form.addEventListener( 'change', copy );
		copy();
	} else if ( card && 'reveal' === cfg.kind ) {
		// A Reveal panel, drawn with the website's own styles (rapm-reveal.css).
		heading( T.pvTitle, T.pvReveal );
		var state = { open: false, phone: false };
		var urls = { desktop: cfg.src ? cfg.src.desktop : '', mobile: cfg.src ? cfg.src.mobile : '' };
		var wrap = document.createElement( 'div' );
		wrap.className = 'rapm-ed-rvwrap';
		wrap.innerHTML = '<div class="rapm-rv rapm-ed-rv" style="--rv-n:1"><div class="rapm-rv-panel"><span class="rapm-rv-color"></span><span class="rapm-rv-photo"><img alt=""></span><span class="rapm-rv-words"><span class="rapm-rv-word"></span><span class="rapm-rv-more"><span class="rapm-rv-line"></span><span class="rapm-rv-go"></span></span></span></div></div>';
		var panel = wrap.querySelector( '.rapm-rv-panel' );
		var img = wrap.querySelector( 'img' );
		toggles( [ [ 'closed', T.closed ], [ 'open', T.open ] ], function ( v ) { state.open = 'open' === v; draw(); } );
		toggles( [ [ 'computer', T.computer ], [ 'phone', T.phone ] ], function ( v ) { state.phone = 'phone' === v; draw(); } );
		card.appendChild( wrap );
		[ 'desktop', 'mobile' ].forEach( function ( which ) {
			var input = $( 'rapm_image_' + which );
			if ( input ) {
				input.addEventListener( 'change', function () {
					var f = chosenFile( which );
					urls[ which ] = f ? URL.createObjectURL( f ) : ( cfg.src ? cfg.src[ which ] : '' );
					draw();
				} );
			}
		} );
		var draw = function () {
			var src = state.phone && urls.mobile ? urls.mobile : urls.desktop;
			panel.style.setProperty( '--rv-color', val( 'rapm_panel_color' ) || '#1d3557' );
			panel.style.color = val( 'rapm_text_color' ) || '#ffffff';
			panel.classList.toggle( 'is-on', state.open );
			panel.classList.toggle( 'is-side', 'side' === radio( 'rapm_word_dir' ) && ! state.phone );
			panel.classList.toggle( 'no-photo', ! src );
			wrap.classList.toggle( 'is-phone', state.phone );
			img.hidden = ! src;
			if ( src && img.getAttribute( 'src' ) !== src ) { img.src = src; }
			var focus = val( state.phone ? 'rapm_focus_mobile' : 'rapm_focus_desktop' );
			img.style.objectPosition = focus || '50% 50%';
			wrap.querySelector( '.rapm-rv-word' ).textContent = val( 'rapm_headline' ) || '…';
			wrap.querySelector( '.rapm-rv-line' ).textContent = val( 'rapm_subhead' );
			var go = wrap.querySelector( '.rapm-rv-go' );
			go.textContent = val( 'rapm_cta_text' ) ? val( 'rapm_cta_text' ) + ' →' : '';
			go.hidden = ! val( 'rapm_cta_text' );
		};
		form.addEventListener( 'input', draw );
		form.addEventListener( 'change', draw );
		window.setInterval( draw, 1500 ); // The focal point and linked colors change without events.
		draw();
	} else if ( card ) {
		var preview = $( 'rapm-ed-preview' );
		if ( preview ) {
			card.appendChild( preview );
		}
	}

	/* ---- One-screen kinds: "More options" -------------------------------- */

	if ( cfg.simple ) {

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
