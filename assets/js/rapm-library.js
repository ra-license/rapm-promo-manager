/**
 * Design Center › Asset library (1.48.0, from the mockup Phil approved on
 * 2026-10-16, https://claude.ai/artifact/7QB12FYVVSSB4sNJi67VV6).
 *
 * Draws the promotions RAPM_Library::render() printed as JSON: summary,
 * status chips, search, type/spot filters, sort, cards or a table, a
 * details panel, bulk actions (each asks first) and "Replace everywhere".
 * Changes go to admin-ajax (rapm_library_bulk, rapm_library_replace),
 * which checks everything again; the page then reloads so every fact is
 * fresh. The chosen view, chip and sort are remembered in this browser.
 */
( function () {
	var root = document.getElementById( 'rapm-lib' );
	var dataEl = document.getElementById( 'rapm-library-data' );
	if ( ! root || ! dataEl ) { return; }

	var cfg = window.RAPM_Library || {};
	var T = cfg.text || {};
	var data = JSON.parse( dataEl.textContent || '{}' );
	var items = data.items || [];
	var spots = data.spots || {};
	var sel = {};
	var openId = 0;
	var $ = function ( id ) { return document.getElementById( id ); };
	var pref = {};
	try { pref = JSON.parse( window.localStorage.getItem( 'rapmLibrary' ) || '{}' ) || {}; } catch ( e ) { pref = {}; }
	var view = pref.view || 'grid';
	var status = pref.status || 'live';

	function remember() {
		try { window.localStorage.setItem( 'rapmLibrary', JSON.stringify( { view: view, status: status, sort: $( 'rapm-lib-sort' ).value } ) ); } catch ( e ) {}
	}

	function esc( t ) {
		return String( null == t ? '' : t ).replace( /[&<>"]/g, function ( c ) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[ c ]; } );
	}

	function fmt( s, a ) { return String( s || '' ).replace( '%s', a ); }

	function weight( it ) { return ( it.desk ? it.desk.kb : 0 ) + ( it.phone ? it.phone.kb : 0 ); }

	function selCount() { return Object.keys( sel ).length; }

	// 1.49.0: the checks that matter most, for badges ("fail" first).
	function issues( it ) {
		return it.checks.filter( function ( c ) { return 'fail' === c.level; } ).concat( it.checks.filter( function ( c ) { return 'warn' === c.level; } ) );
	}

	/* ---- Filters ---------------------------------------------------------- */

	var types = {};
	items.forEach( function ( it ) { types[ it.kind ] = it.type; } );
	Object.keys( types ).forEach( function ( k ) { $( 'rapm-lib-type' ).insertAdjacentHTML( 'beforeend', '<option value="' + esc( k ) + '">' + esc( types[ k ] ) + '</option>' ); } );
	Object.keys( spots ).forEach( function ( k ) { $( 'rapm-lib-spot' ).insertAdjacentHTML( 'beforeend', '<option value="' + esc( k ) + '">' + esc( spots[ k ].name + ' (' + spots[ k ].type + ')' ) + '</option>' ); } );
	if ( pref.sort ) { $( 'rapm-lib-sort' ).value = pref.sort; }

	function filtered() {
		var q = $( 'rapm-lib-q' ).value.trim().toLowerCase();
		var ft = $( 'rapm-lib-type' ).value;
		var fs = $( 'rapm-lib-spot' ).value;
		var list = items.filter( function ( it ) {
			if ( 'live' === status && 'ended' === it.state ) { return false; }
			if ( 'flags' === status && 'warn' !== it.ready ) { return false; }
			if ( 'fails' === status && 'fail' !== it.ready ) { return false; }
			if ( [ 'showing', 'upcoming', 'hidden', 'ended' ].indexOf( status ) !== -1 && it.state !== status ) { return false; }
			if ( ft && it.kind !== ft ) { return false; }
			if ( fs && it.spot !== fs ) { return false; }
			if ( q ) {
				var hay = [ it.name, it.words, it.spotName, it.desk ? it.desk.file : '', it.phone ? it.phone.file : '', it.alt ].join( ' ' ).toLowerCase();
				if ( hay.indexOf( q ) === -1 ) { return false; }
			}
			return true;
		} );
		var s = $( 'rapm-lib-sort' ).value;
		list.sort( function ( a, b ) {
			if ( 'name' === s ) { return a.name.localeCompare( b.name ); }
			if ( 'newest' === s ) { return b.id - a.id; }
			if ( 'weight' === s ) { return weight( b ) - weight( a ); }
			return ( a.end || '9999' ).localeCompare( b.end || '9999' );
		} );
		return list;
	}

	/* ---- Drawing ---------------------------------------------------------- */

	function renderChips() {
		var c = { showing: 0, upcoming: 0, hidden: 0, ended: 0 };
		var flags = 0;
		var fails = 0;
		var kb = 0;
		items.forEach( function ( it ) {
			c[ it.state ] = ( c[ it.state ] || 0 ) + 1;
			// Ended promotions don't count toward problems: they're done.
			if ( 'ended' !== it.state ) {
				if ( 'fail' === it.ready ) { fails++; }
				if ( 'warn' === it.ready ) { flags++; }
			}
			kb += weight( it );
		} );
		var opts = [ [ 'live', T.notEnded, items.length - c.ended ], [ 'fails', T.fails, fails ], [ 'flags', T.flags, flags ], [ 'showing', T.showing, c.showing ], [ 'upcoming', T.upcoming, c.upcoming ], [ 'hidden', T.hidden, c.hidden ], [ 'ended', T.ended, c.ended ], [ 'all', T.all, items.length ] ];
		$( 'rapm-lib-chips' ).innerHTML = opts.map( function ( o ) {
			return '<button type="button" class="rapm-lib-chip" data-status="' + o[ 0 ] + '" aria-pressed="' + ( status === o[ 0 ] ) + '">' + esc( o[ 1 ] ) + ' <span>' + o[ 2 ] + '</span></button>';
		} ).join( '' );
		$( 'rapm-lib-summary' ).innerHTML = '<b>' + items.length + '</b> ' + esc( T.promotions ) + ' · <b>' + Object.keys( spots ).length + '</b> ' + esc( T.spots ) + ' · <b>' + c.showing + '</b> ' + esc( T.showingNow ) + ' · <b>' + fails + '</b> ' + esc( T.fails.toLowerCase() ) + ' · <b>' + flags + '</b> ' + esc( T.needLook ) + ' · <b>' + ( kb / 1024 ).toFixed( 1 ) + ' MB</b> ' + esc( T.ofPictures );
	}

	// 1.54.0: a picture's focal point, with a link to change it on its form.
	function focusRow( label, point, edit ) {
		var v = point ? T.pointAt.replace( '%1$s', point.split( ' ' )[ 0 ] ).replace( '%2$s', point.split( ' ' )[ 1 ] ) : T.middle;
		return '<dt>' + esc( T.inView + ' (' + label.toLowerCase() + ')' ) + '</dt><dd>' + esc( v ) + ' · <a href="' + esc( edit ) + '">' + esc( T.setPoint ) + '</a></dd>';
	}

	function pic( it ) {
		if ( it.design && it.preview ) { return '<span class="rapm-lib-thumb is-design">' + it.preview + '</span>'; }
		var src = it.desk && it.desk.thumb;
		return src ? '<span class="rapm-lib-thumb"><img src="' + esc( src ) + '" alt="" loading="lazy"></span>' : '<span class="rapm-lib-thumb is-empty"></span>';
	}

	function fileLine( it ) {
		if ( it.design ) { return T.designed; }
		var s = it.desk ? it.desk.w + '×' + it.desk.h + ' · ' + it.desk.kb + ' ' + T.kb : '–';
		if ( it.phone ) { s += ' / ' + T.phone.toLowerCase() + ' ' + it.phone.kb + ' ' + T.kb; }
		return s + ( it.desk ? ' · ' + it.desk.from : '' );
	}

	function renderGaps() {
		var gaps = data.gaps || {};
		var keys = Object.keys( gaps );
		var box = $( 'rapm-lib-gaps' );
		box.hidden = ! keys.length;
		if ( ! keys.length ) { return; }
		box.innerHTML = '<h3>' + esc( T.gapsTitle ) + '</h3><ul>' + keys.map( function ( k ) {
			var name = spots[ k ] ? spots[ k ].name : k;
			return gaps[ k ].map( function ( g ) { return '<li><b>' + esc( name ) + ':</b> ' + esc( g ) + '</li>'; } ).join( '' );
		} ).join( '' ) + '</ul>';
	}

	function checkLinks() {
		var ids = filtered().filter( function ( it ) { return it.hasLink; } ).map( function ( it ) { return it.id; } );
		if ( ! ids.length ) { say( T.noLinks ); return; }
		var total = ids.length;
		var done = 0;
		var btn = $( 'rapm-lib-links' );
		btn.disabled = true;
		( function next() {
			var batch = ids.splice( 0, 8 );
			if ( ! batch.length ) {
				say( T.linksDone );
				setTimeout( function () { window.location.reload(); }, 600 );
				return;
			}
			say( T.checkingLinks.replace( '%1$d', done ).replace( '%2$d', total ) );
			post( { action: 'rapm_check_links', ids: batch } ).then( function ( res ) {
				if ( ! res || ! res.success ) { throw new Error( T.failed ); }
				done += batch.length;
				next();
			} ).catch( function () { btn.disabled = false; say( T.failed ); } );
		} )();
	}

	function render() {
		renderChips();
		var list = filtered();
		var box = $( 'rapm-lib-results' );
		if ( ! list.length ) {
			box.innerHTML = '<p class="rapm-lib-empty">' + esc( T.none ) + '</p>';
			renderBulk();
			return;
		}
		if ( 'grid' === view ) {
			box.innerHTML = '<div class="rapm-lib-grid">' + list.map( function ( it ) {
				return '<article class="rapm-lib-card' + ( sel[ it.id ] ? ' is-on' : '' ) + ( 'ended' === it.state ? ' is-ended' : '' ) + '" data-id="' + it.id + '" tabindex="0" aria-label="' + esc( it.name ) + '">' +
					'<div class="rapm-lib-pic">' + pic( it ) + '<input type="checkbox" class="rapm-lib-sel" aria-label="' + esc( fmt( T.select, it.name ) ) + '"' + ( sel[ it.id ] ? ' checked' : '' ) + '>' +
					( 'ready' !== it.ready && 'ended' !== it.state ? '<span class="rapm-lib-flag is-' + it.ready + '" title="' + esc( issues( it )[ 0 ].text ) + '">' + esc( 'fail' === it.ready ? T.problem : T.flags ) + ( issues( it ).length > 1 ? ' · ' + issues( it ).length : '' ) + '</span>' : '' ) + '</div>' +
					'<div class="rapm-lib-body"><div class="rapm-lib-name">' + esc( it.name ) + '</div>' +
					'<div class="rapm-lib-meta"><span class="rapm-lib-type">' + esc( it.type ) + '</span><span>' + esc( it.spotName ) + '</span></div>' +
					'<div class="rapm-lib-meta"><span class="rapm-lib-pill is-' + it.state + '">' + esc( it.status ) + '</span><span>' + esc( it.when ) + '</span></div>' +
					'<div class="rapm-lib-files">' + esc( fileLine( it ) ) + '</div></div></article>';
			} ).join( '' ) + '</div>';
		} else {
			box.innerHTML = '<div class="rapm-lib-table-wrap"><table class="widefat striped rapm-lib-table"><thead><tr><td class="check-column"><input type="checkbox" id="rapm-lib-all" aria-label="' + esc( T.selectAll ) + '"></td><th></th><th>' + esc( T.colName ) + '</th><th>' + esc( T.colType ) + '</th><th>' + esc( T.colSpot ) + '</th><th>' + esc( T.colStatus ) + '</th><th>' + esc( T.colDates ) + '</th><th>' + esc( T.computer ) + '</th><th>' + esc( T.phone ) + '</th><th>' + esc( T.colSource ) + '</th><th>' + esc( T.altText ) + '</th></tr></thead><tbody>' +
				list.map( function ( it ) {
					return '<tr data-id="' + it.id + '"' + ( sel[ it.id ] ? ' class="is-on"' : '' ) + '><td class="check-column"><input type="checkbox" class="rapm-lib-sel" aria-label="' + esc( fmt( T.select, it.name ) ) + '"' + ( sel[ it.id ] ? ' checked' : '' ) + '></td>' +
						'<td>' + pic( it ) + '</td><td><a href="#" class="rapm-lib-open"><strong>' + esc( it.name ) + '</strong></a>' + ( 'ready' !== it.ready && 'ended' !== it.state ? '<br><span class="rapm-lib-flagtext is-' + it.ready + '">' + esc( issues( it )[ 0 ].text ) + ( issues( it ).length > 1 ? ' (+' + ( issues( it ).length - 1 ) + ')' : '' ) + '</span>' : '' ) + '</td>' +
						'<td>' + esc( it.type ) + '</td><td>' + esc( it.spotName ) + '</td><td><span class="rapm-lib-pill is-' + it.state + '">' + esc( it.status ) + '</span></td>' +
						'<td class="rapm-lib-num">' + esc( ( it.start || '–' ).replace( 'T', ' ' ) ) + ' → ' + esc( ( it.end || '–' ).replace( 'T', ' ' ) ) + '</td>' +
						'<td class="rapm-lib-num">' + ( it.design ? esc( T.designed ) : ( it.desk ? it.desk.w + '×' + it.desk.h + ' · ' + it.desk.kb + ' ' + esc( T.kb ) : '–' ) ) + '</td>' +
						'<td class="rapm-lib-num">' + ( it.phone ? it.phone.w + '×' + it.phone.h + ' · ' + it.phone.kb + ' ' + esc( T.kb ) : '–' ) + '</td>' +
						'<td>' + esc( it.desk ? it.desk.from : '' ) + '</td><td>' + ( it.alt ? '✓' : '<span class="rapm-lib-flagtext">' + esc( T.missing ) + '</span>' ) + '</td></tr>';
				} ).join( '' ) + '</tbody></table></div>';
		}
		renderBulk();
	}

	function bulkButtons() {
		return '<b>' + esc( fmt( T.selected, '' ).replace( '%d', selCount() ) ) + '</b>' +
			'<button type="button" class="button" data-bulk="on">' + esc( T.turnOn ) + '</button>' +
			'<button type="button" class="button" data-bulk="off">' + esc( T.turnOff ) + '</button>' +
			'<button type="button" class="button" data-bulk="dates">' + esc( T.setDates ) + '</button>' +
			'<button type="button" class="button" data-bulk="duplicate">' + esc( T.duplicate ) + '</button>' +
			'<button type="button" class="button" data-bulk="move">' + esc( T.move ) + '</button>' +
			'<button type="button" class="button rapm-lib-danger" data-bulk="trash">' + esc( T.trash ) + '</button>' +
			'<button type="button" class="button-link" id="rapm-lib-clear">' + esc( T.clear ) + '</button>';
	}

	function renderBulk() {
		var b = $( 'rapm-lib-bulk' );
		b.hidden = ! selCount();
		if ( selCount() && ! b.querySelector( '#rapm-lib-yes' ) ) { b.innerHTML = bulkButtons(); }
	}

	function askBulk( action ) {
		var names = { on: T.turnOn, off: T.turnOff, dates: T.setDates.replace( '…', '' ), duplicate: T.duplicate, move: T.move.replace( '…', '' ), trash: T.trash };
		var h = '<b>' + esc( T.ask.replace( '%1$s', names[ action ] ).replace( '%2$d', selCount() ) ) + '</b>';
		if ( 'dates' === action ) {
			h += ' <label>' + esc( T.starts ) + ' <input type="datetime-local" id="rapm-lib-start"></label> <label>' + esc( T.ends ) + ' <input type="datetime-local" id="rapm-lib-end"></label> <span class="description">' + esc( T.datesNote ) + '</span>';
		}
		if ( 'move' === action ) {
			h += ' <select id="rapm-lib-to">' + Object.keys( spots ).map( function ( k ) { return '<option value="' + esc( k ) + '">' + esc( spots[ k ].name + ' (' + spots[ k ].type + ')' ) + '</option>'; } ).join( '' ) + '</select> <span class="description">' + esc( T.askMove ) + '</span>';
		}
		h += ' <button type="button" class="button button-primary" id="rapm-lib-yes" data-do="' + action + '">' + esc( T.confirm ) + '</button> <button type="button" class="button" id="rapm-lib-no">' + esc( T.cancel ) + '</button>';
		$( 'rapm-lib-bulk' ).innerHTML = h;
	}

	function post( data ) {
		var fd = new FormData();
		Object.keys( data ).forEach( function ( k ) {
			if ( Array.isArray( data[ k ] ) ) {
				data[ k ].forEach( function ( v ) { fd.append( k + '[]', v ); } );
			} else {
				fd.append( k, data[ k ] );
			}
		} );
		fd.append( 'nonce', cfg.nonce );
		return fetch( cfg.ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' } ).then( function ( r ) { return r.json(); } );
	}

	function say( t ) { $( 'rapm-lib-status' ).textContent = t || ''; }

	function runBulk( action, ids ) {
		var data = { action: 'rapm_library_bulk', do: action, ids: ids };
		if ( 'dates' === action ) { data.start = $( 'rapm-lib-start' ).value; data.end = $( 'rapm-lib-end' ).value; }
		if ( 'move' === action ) { data.spot = $( 'rapm-lib-to' ).value; }
		say( T.working );
		return post( data ).then( function ( res ) {
			if ( ! res || ! res.success ) { throw new Error( res && res.data && res.data.message ? res.data.message : T.failed ); }
			// Say what happened (and what was left alone) before refreshing.
			var msg = res.data.skipped ? T.doneSkipped.replace( '%1$d', res.data.done ).replace( '%2$d', res.data.skipped ) : T.refreshing;
			say( msg );
			if ( openId && $( 'rapm-lib-dstatus' ) ) { $( 'rapm-lib-dstatus' ).textContent = msg; }
			setTimeout( function () { window.location.reload(); }, res.data.skipped ? 3500 : 600 );
		} ).catch( function ( err ) {
			say( err.message || T.failed );
			if ( openId && $( 'rapm-lib-dstatus' ) ) { $( 'rapm-lib-dstatus' ).textContent = err.message || T.failed; }
		} );
	}

	/* ---- Details panel ---------------------------------------------------- */

	function sameFile( id ) {
		return items.filter( function ( it ) { return ( it.desk && it.desk.id === id ) || ( it.phone && it.phone.id === id ); } );
	}

	function openDrawer( id ) {
		var it = items.filter( function ( x ) { return x.id === id; } )[ 0 ];
		if ( ! it ) { return; }
		openId = id;
		var picRow = function ( label, p, need ) {
			if ( ! p ) { return '<dt>' + esc( label ) + '</dt><dd>–</dd>'; }
			return '<dt>' + esc( label ) + '</dt><dd>' + esc( p.file ) + ' · ' + p.w + '×' + p.h + ' · ' + p.kb + ' ' + esc( T.kb ) + ( need ? ' <span class="description">(' + esc( T.needed ) + ': ' + esc( need ) + ')</span>' : '' ) + '</dd>';
		};
		var src = it.desk ? it.desk.from + ( it.desk.synced ? ' · ' + T.lastChecked + ' ' + it.desk.synced : '' ) : '–';
		var replaceable = it.desk && ! it.design;
		var users = replaceable ? sameFile( it.desk.id ) : [];
		var h = '<div class="rapm-lib-dhead"><div><p class="rapm-lib-eyebrow">' + esc( it.type + ' · ' + it.spotName ) + '</p><h2 id="rapm-lib-d-title">' + esc( it.name ) + '</h2>' +
			'<p class="rapm-lib-meta"><span class="rapm-lib-pill is-' + it.state + '">' + esc( it.status ) + '</span><span>' + esc( it.when ) + '</span></p></div>' +
			'<button type="button" class="rapm-lib-close" id="rapm-lib-close" aria-label="' + esc( T.close ) + '">×</button></div>' +
			'<div class="rapm-lib-preview"><figure>' + ( it.design ? pic( it ) : ( it.desk ? '<img src="' + esc( it.desk.thumb ) + '" alt="">' : '' ) ) + '<figcaption>' + esc( T.computers ) + '</figcaption></figure>' +
			( it.phone ? '<figure class="is-phone"><img src="' + esc( it.phone.thumb ) + '" alt=""><figcaption>' + esc( T.phones ) + '</figcaption></figure>' : '' ) + '</div>' +
			'<p class="rapm-lib-actions"><a class="button button-primary" href="' + esc( it.edit ) + '">' + esc( T.edit ) + '</a>' +
			'<button type="button" class="button" data-one="duplicate">' + esc( T.duplicate ) + '</button>' +
			( replaceable ? '<button type="button" class="button" id="rapm-lib-replace">' + esc( T.replace ) + '</button>' : '' ) +
			'<button type="button" class="button" data-one="' + ( it.on ? 'off' : 'on' ) + '">' + esc( it.on ? T.turnOff : T.turnOn ) + '</button></p>';
		if ( replaceable ) {
			h += '<div class="rapm-lib-replace" id="rapm-lib-replace-box" hidden><p><b>' + esc( fmt( T.replaceTitle, it.desk.file ) ) + '</b></p><p class="description">' + esc( T.replaceNote ) + '</p>' +
				users.map( function ( u ) { return '<label><input type="checkbox" class="rapm-lib-rep" value="' + u.id + '" checked> ' + esc( u.name + ' (' + u.spotName + ')' ) + '</label>'; } ).join( '' ) +
				'<p class="description">' + esc( T.replaceRule ) + '</p><p><button type="button" class="button button-primary" id="rapm-lib-replace-go">' + esc( T.replaceGo ) + '</button></p></div>';
		}
		h += '<section><h3>' + esc( T.pictures ) + '</h3><dl class="rapm-lib-kv">' +
			( it.design ? '<dt>' + esc( T.computer ) + '</dt><dd>' + esc( T.designed ) + '</dd>' : picRow( T.computer, it.desk, it.need ) ) +
			( it.needPhone || it.phone ? picRow( T.phone, it.phone, it.needPhone ) : '' ) +
			'<dt>' + esc( T.comesFrom ) + '</dt><dd>' + esc( src ) + ( it.check ? ' <a class="button button-small" href="' + esc( it.check ) + '">' + esc( T.checkNow ) + '</a>' : '' ) + '</dd>' +
			'<dt>' + esc( T.altText ) + '</dt><dd>' + ( it.alt ? esc( it.alt ) : '<span class="rapm-lib-flagtext">' + esc( T.noneYet ) + '</span>' ) + '</dd>' +
			( it.focus ? focusRow( T.computer, it.focus.desk, it.edit ) + ( null !== it.focus.phone && undefined !== it.focus.phone ? focusRow( T.phone, it.focus.phone, it.edit ) : '' ) : '' ) +
			'</dl></section>';
		// 1.49.0: Ready to go live, problems first, then things to know, then passes.
		var order = { fail: 0, warn: 1, info: 2, pass: 3 };
		var sorted = it.checks.slice().sort( function ( a, b ) { return order[ a.level ] - order[ b.level ]; } );
		h += '<section><h3>' + esc( T.ready ) + ' <span class="rapm-lib-verdict is-' + it.ready + '">' + esc( 'fail' === it.ready ? T.problem : ( 'warn' === it.ready ? T.flags : T.readyAll ) ) + '</span></h3>' +
			'<ul class="rapm-lib-checks">' + sorted.map( function ( c ) { return '<li class="is-' + c.level + '"><span class="rapm-lib-dot" aria-hidden="true"></span><span>' + esc( c.text ) + '</span></li>'; } ).join( '' ) + '</ul>' +
			'<p class="description">' + esc( T.readyNote ) + '</p></section>';
		h += '<section><h3>' + esc( T.whereShows ) + '</h3>' + ( it.pages.length ? '<ul>' + it.pages.map( function ( p ) {
			return '<li>' + ( p.url ? '<a href="' + esc( p.url ) + '" target="_blank" rel="noopener">' + esc( p.label ) + '</a>' : esc( p.label ) ) +
				( p.path ? ' <code>' + esc( p.path ) + '</code>' : '' ) +
				( p.note ? ' <span class="description">(' + esc( p.note ) + ')</span>' : '' ) +
				( p.edit ? ' · <a href="' + esc( p.edit ) + '">' + esc( T.editPage ) + '</a>' : '' ) + '</li>';
		} ).join( '' ) + '</ul>' : '<p class="description">' + esc( T.notPlaced ) + '</p>' ) +
			( it.link ? '<p class="description">' + esc( T.linksTo ) + ': ' + esc( it.link ) + '</p>' : '' ) + '</section>';
		h += '<section><h3>' + esc( T.history ) + '</h3><p>' + esc( T.lastSaved ) + ' ' + esc( it.saved ) + ( it.editor ? ' ' + esc( T.by ) + ' ' + esc( it.editor ) : '' ) + '<br>' + esc( T.created ) + ' ' + esc( it.created ) + '</p></section>';
		h += '<p class="rapm-lib-status" id="rapm-lib-dstatus" aria-live="polite"></p>';
		var d = $( 'rapm-lib-drawer' );
		d.innerHTML = h;
		d.hidden = false;
		$( 'rapm-lib-drawer-bg' ).hidden = false;
		$( 'rapm-lib-close' ).focus();
	}

	function closeDrawer() {
		$( 'rapm-lib-drawer' ).hidden = true;
		$( 'rapm-lib-drawer-bg' ).hidden = true;
		var c = root.querySelector( '[data-id="' + openId + '"]' );
		openId = 0;
		if ( c ) { c.focus(); }
	}

	var frame = null;
	function replacePicture() {
		var it = items.filter( function ( x ) { return x.id === openId; } )[ 0 ];
		var ids = Array.prototype.map.call( document.querySelectorAll( '.rapm-lib-rep:checked' ), function ( c ) { return c.value; } );
		if ( ! it || ! ids.length || ! window.wp || ! window.wp.media ) { return; }
		frame = window.wp.media( { title: T.pickPicture, button: { text: T.usePicture }, library: { type: 'image' }, multiple: false } );
		frame.on( 'select', function () {
			var a = frame.state().get( 'selection' ).first().toJSON();
			$( 'rapm-lib-dstatus' ).textContent = T.working;
			post( { action: 'rapm_library_replace', old: it.desk.id, new: a.id, ids: ids } ).then( function ( res ) {
				if ( ! res || ! res.success ) { throw new Error( res && res.data && res.data.message ? res.data.message : T.failed ); }
				// Pictures that didn't fit a promotion are listed, with why.
				if ( res.data.skipped && res.data.skipped.length ) {
					$( 'rapm-lib-dstatus' ).textContent = res.data.skipped.join( ' ' );
					if ( ! res.data.done ) { return; }
					setTimeout( function () { window.location.reload(); }, 5000 );
					return;
				}
				$( 'rapm-lib-dstatus' ).textContent = T.refreshing;
				setTimeout( function () { window.location.reload(); }, 600 );
			} ).catch( function ( err ) { $( 'rapm-lib-dstatus' ).textContent = err.message || T.failed; } );
		} );
		frame.open();
	}

	/* ---- Events ----------------------------------------------------------- */

	[ 'rapm-lib-q', 'rapm-lib-type', 'rapm-lib-spot', 'rapm-lib-sort' ].forEach( function ( id ) { $( id ).addEventListener( 'input', function () { remember(); render(); } ); } );

	document.addEventListener( 'click', function ( e ) {
		var t = e.target;
		var v = t.closest( '[data-view]' );
		if ( v && root.contains( v ) ) {
			view = v.getAttribute( 'data-view' );
			Array.prototype.forEach.call( root.querySelectorAll( '[data-view]' ), function ( b ) { b.setAttribute( 'aria-pressed', String( b === v ) ); } );
			remember();
			render();
			return;
		}
		var chip = t.closest( '[data-status]' );
		if ( chip && root.contains( chip ) ) { status = chip.getAttribute( 'data-status' ); remember(); render(); return; }
		if ( 'rapm-lib-all' === t.id ) {
			filtered().forEach( function ( it ) { if ( t.checked ) { sel[ it.id ] = true; } else { delete sel[ it.id ]; } } );
			render();
			return;
		}
		if ( t.classList.contains( 'rapm-lib-sel' ) ) {
			var id = +t.closest( '[data-id]' ).getAttribute( 'data-id' );
			if ( t.checked ) { sel[ id ] = true; } else { delete sel[ id ]; }
			render();
			return;
		}
		var bulk = t.closest( '[data-bulk]' );
		if ( bulk ) { askBulk( bulk.getAttribute( 'data-bulk' ) ); return; }
		if ( 'rapm-lib-yes' === t.id ) { runBulk( t.getAttribute( 'data-do' ), Object.keys( sel ) ); return; }
		if ( 'rapm-lib-no' === t.id ) { $( 'rapm-lib-bulk' ).innerHTML = bulkButtons(); return; }
		if ( 'rapm-lib-clear' === t.id ) { sel = {}; render(); return; }
		var one = t.closest( '[data-one]' );
		if ( one && openId ) {
			$( 'rapm-lib-dstatus' ).textContent = T.working;
			runBulk( one.getAttribute( 'data-one' ), [ openId ] );
			return;
		}
		if ( 'rapm-lib-close' === t.id || 'rapm-lib-drawer-bg' === t.id ) { closeDrawer(); return; }
		if ( 'rapm-lib-replace' === t.id ) { var r = $( 'rapm-lib-replace-box' ); r.hidden = ! r.hidden; return; }
		if ( 'rapm-lib-replace-go' === t.id ) { replacePicture(); return; }
		if ( 'rapm-lib-links' === t.id ) { checkLinks(); return; }
		var open = t.closest( '.rapm-lib-card, .rapm-lib-open' );
		if ( open && ! t.closest( 'input' ) ) {
			e.preventDefault();
			openDrawer( +open.closest( '[data-id]' ).getAttribute( 'data-id' ) );
		}
	} );

	document.addEventListener( 'keydown', function ( e ) {
		if ( 'Escape' === e.key && openId ) { closeDrawer(); }
		if ( 'Enter' === e.key && e.target.classList && e.target.classList.contains( 'rapm-lib-card' ) ) { openDrawer( +e.target.getAttribute( 'data-id' ) ); }
	} );

	Array.prototype.forEach.call( root.querySelectorAll( '[data-view]' ), function ( b ) { b.setAttribute( 'aria-pressed', String( b.getAttribute( 'data-view' ) === view ) ); } );
	renderGaps();
	render();
} )();
