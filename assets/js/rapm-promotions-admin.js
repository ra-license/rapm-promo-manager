/**
 * Promotions screen (RAPM_Promotions_Screen): the on/off switches, the
 * "…" menus, drag (and Move earlier/later) to reorder, Find a promotion,
 * Show ended, and the R&A setup details. Plain script, no dependencies.
 */
( function () {
	'use strict';

	var cfg  = window.RAPM_Promotions || {};
	var T    = cfg.text || {};
	var root = document.querySelector( '.rapm-promos' );
	if ( ! root ) { return; }

	var search    = document.getElementById( 'rapm-promo-search' );
	var noResults = root.querySelector( '.rapm-no-results' );

	function post( data ) {
		var fd = new FormData();
		Object.keys( data ).forEach( function ( k ) {
			if ( Array.isArray( data[ k ] ) ) {
				data[ k ].forEach( function ( v ) { fd.append( k + '[]', v ); } );
			} else {
				fd.append( k, data[ k ] );
			}
		} );
		return fetch( cfg.ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' } )
			.then( function ( r ) { return r.json(); } );
	}

	function say( section, message ) {
		var box = section && section.querySelector( '.rapm-spot-status' );
		if ( box ) { box.textContent = message || ''; }
	}

	function sections() {
		return Array.prototype.slice.call( root.querySelectorAll( 'section.rapm-spot[data-kind]' ) );
	}

	function cardsIn( section ) {
		return Array.prototype.slice.call( section.querySelectorAll( '.rapm-grid > .rapm-card' ) );
	}

	/* ---- Which cards show: Find a promotion + Show ended ---------------- */

	function applyVisibility() {
		var q        = search ? search.value.trim().toLowerCase() : '';
		var anyShown = false;
		sections().forEach( function ( section ) {
			var endedOpen = '1' === section.getAttribute( 'data-ended-open' );
			var matches   = 0;
			cardsIn( section ).forEach( function ( card ) {
				var match   = ! q || -1 !== ( card.getAttribute( 'data-title' ) || '' ).indexOf( q );
				var visible = match && ( ! card.classList.contains( 'is-ended' ) || endedOpen || !! q );
				card.hidden = ! visible;
				if ( match ) { matches++; }
			} );
			var add      = section.querySelector( '.rapm-add' );
			var endedRow = section.querySelector( '.rapm-ended-row' );
			if ( add ) { add.hidden = !! q; }
			if ( endedRow ) { endedRow.hidden = !! q; }
			section.hidden = !! q && 0 === matches;
			if ( ! section.hidden ) { anyShown = true; }
		} );
		if ( noResults ) {
			noResults.hidden      = ! q || anyShown;
			noResults.textContent = q ? ( T.noResults || '' ).replace( '%s', search.value.trim() ) : '';
		}
	}

	if ( search ) { search.addEventListener( 'input', applyVisibility ); }

	function toggleEnded( button ) {
		var section = button.closest( '.rapm-spot' );
		var open    = 'true' !== button.getAttribute( 'aria-expanded' );
		section.setAttribute( 'data-ended-open', open ? '1' : '0' );
		button.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		button.textContent = open ? T.hideEnded : ( T.showEnded || '' ).replace( '%d', button.getAttribute( 'data-count' ) );
		applyVisibility();
	}

	/* ---- "…" menus ------------------------------------------------------ */

	function closeMenus( except ) {
		root.querySelectorAll( '.rapm-card-menu' ).forEach( function ( menu ) {
			if ( menu === except ) { return; }
			menu.hidden = true;
			var more = menu.parentNode.querySelector( '.rapm-card-more' );
			if ( more ) { more.setAttribute( 'aria-expanded', 'false' ); }
		} );
	}

	/* ---- On/off switch ---------------------------------------------------- */

	function applyState( card, d ) {
		card.classList.remove( 'is-showing', 'is-upcoming', 'is-hidden', 'is-ended' );
		card.classList.add( 'is-' + d.state );
		card.querySelector( '.rapm-pill-text' ).textContent = d.status;
		var when = card.querySelector( '.rapm-when' );
		when.textContent = d.when;
		when.classList.toggle( 'is-soon', !! d.soon );
	}

	function toggle( sw ) {
		var card    = sw.closest( '.rapm-card' );
		var section = sw.closest( '.rapm-spot' );
		var title   = card.querySelector( '.rapm-card-title' ).textContent;
		var on      = 'true' !== sw.getAttribute( 'aria-checked' );
		sw.setAttribute( 'aria-checked', on ? 'true' : 'false' );
		sw.disabled = true;
		say( section, T.saving );
		post( { action: 'rapm_toggle_asset', nonce: cfg.nonce, id: card.getAttribute( 'data-id' ), on: on ? '1' : '0' } )
			.then( function ( res ) {
				if ( ! res || ! res.success ) { throw new Error( 'save failed' ); }
				sw.disabled = false;
				sw.setAttribute( 'aria-checked', res.data.on ? 'true' : 'false' );
				applyState( card, res.data.card );
				section.querySelector( '.rapm-spot-count' ).textContent = res.data.countLine;
				say( section, title + ': ' + res.data.card.status );
			} )
			.catch( function () {
				sw.disabled = false;
				sw.setAttribute( 'aria-checked', on ? 'false' : 'true' );
				say( section, T.saveFailed );
			} );
	}

	/* ---- Order: drag, or Move earlier / later ------------------------------ */

	function orderOf( section ) {
		return cardsIn( section ).map( function ( c ) { return c.getAttribute( 'data-id' ); } );
	}

	function saveOrder( section ) {
		say( section, T.saving );
		post( { action: 'rapm_reorder_slides', nonce: cfg.reorderNonce, order: orderOf( section ) } )
			.then( function ( res ) { say( section, res && res.success ? T.orderSaved : T.saveFailed ); } )
			.catch( function () { say( section, T.saveFailed ); } );
	}

	function moveCard( card, dir ) {
		var section = card.closest( '.rapm-spot' );
		var shown   = cardsIn( section ).filter( function ( c ) { return ! c.hidden; } );
		var i       = shown.indexOf( card );
		var other   = shown[ i + dir ];
		if ( ! other ) { return; }
		other.parentNode.insertBefore( card, dir < 0 ? other : other.nextSibling );
		saveOrder( section );
		card.querySelector( '.rapm-card-more' ).focus();
	}

	sections().forEach( function ( section ) {
		var cards = cardsIn( section );
		if ( cards.length > 1 ) {
			cards.forEach( function ( card ) { card.setAttribute( 'draggable', 'true' ); } );
		}
	} );

	var dragCard   = null;
	var dragBefore = '';

	root.addEventListener( 'dragstart', function ( e ) {
		var card = e.target.closest ? e.target.closest( '.rapm-card[draggable="true"]' ) : null;
		if ( ! card || card.hidden ) { return; }
		dragCard   = card;
		dragBefore = orderOf( card.closest( '.rapm-spot' ) ).join( ',' );
		card.classList.add( 'is-dragging' );
		closeMenus();
		e.dataTransfer.effectAllowed = 'move';
		try { e.dataTransfer.setData( 'text/plain', card.getAttribute( 'data-id' ) ); } catch ( err ) {}
	} );

	root.addEventListener( 'dragover', function ( e ) {
		if ( ! dragCard ) { return; }
		var grid = dragCard.parentNode;
		var over = e.target.closest ? e.target.closest( '.rapm-card' ) : null;
		if ( e.target.closest && e.target.closest( '.rapm-grid' ) === grid ) { e.preventDefault(); }
		if ( ! over || over === dragCard || over.parentNode !== grid ) { return; }
		var box   = over.getBoundingClientRect();
		var after = e.clientX > box.left + box.width / 2;
		grid.insertBefore( dragCard, after ? over.nextSibling : over );
	} );

	root.addEventListener( 'drop', function ( e ) {
		if ( dragCard ) { e.preventDefault(); }
	} );

	root.addEventListener( 'dragend', function () {
		if ( ! dragCard ) { return; }
		var section = dragCard.closest( '.rapm-spot' );
		dragCard.classList.remove( 'is-dragging' );
		dragCard = null;
		if ( orderOf( section ).join( ',' ) !== dragBefore ) { saveOrder( section ); }
	} );

	/* ---- R&A setup details ------------------------------------------------- */

	function setRa( on ) {
		root.querySelectorAll( '.rapm-ra' ).forEach( function ( el ) { el.hidden = ! on; } );
		var button = root.querySelector( '.rapm-ra-toggle' );
		if ( button ) {
			button.setAttribute( 'aria-expanded', on ? 'true' : 'false' );
			button.textContent = on ? T.hideRa : T.showRa;
		}
		try { window.localStorage.setItem( 'rapmRaDetails', on ? '1' : '0' ); } catch ( err ) {}
	}

	try {
		if ( '1' === window.localStorage.getItem( 'rapmRaDetails' ) ) { setRa( true ); }
	} catch ( err ) {}

	root.addEventListener( 'submit', function ( e ) {
		var form = e.target.closest ? e.target.closest( '.rapm-rename' ) : null;
		if ( ! form ) { return; }
		e.preventDefault();
		var section = form.closest( '.rapm-spot' );
		var input   = form.querySelector( 'input[name="name"]' );
		post( {
			action: 'rapm_rename_spot',
			nonce: cfg.nonce,
			kind: section.getAttribute( 'data-kind' ),
			placement: section.getAttribute( 'data-placement' ),
			name: input.value
		} ).then( function ( res ) {
			if ( ! res || ! res.success ) { throw new Error( 'save failed' ); }
			section.querySelector( '.rapm-spot-name' ).textContent = res.data.name;
			input.value = res.data.name;
			say( section, T.nameSaved );
		} ).catch( function () { say( section, T.saveFailed ); } );
	} );

	/* ---- Clicks ------------------------------------------------------------ */

	document.addEventListener( 'click', function ( e ) {
		var t = e.target;
		if ( ! t.closest || ! root.contains( t ) ) { closeMenus(); return; }

		var more = t.closest( '.rapm-card-more' );
		if ( more ) {
			var menu = more.parentNode.querySelector( '.rapm-card-menu' );
			var open = menu.hidden;
			closeMenus( menu );
			menu.hidden = ! open;
			more.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
			if ( open ) {
				var first = menu.querySelector( 'a, button' );
				if ( first ) { first.focus(); }
			}
			return;
		}

		if ( t.closest( '.rapm-card-trash' ) && ! window.confirm( T.confirmTrash ) ) {
			e.preventDefault();
			return;
		}

		var move = t.closest( '[data-move]' );
		if ( move ) {
			closeMenus();
			moveCard( move.closest( '.rapm-card' ), parseInt( move.getAttribute( 'data-move' ), 10 ) );
			return;
		}

		var sw = t.closest( '.rapm-switch' );
		if ( sw ) { toggle( sw ); return; }

		var ended = t.closest( '.rapm-ended-toggle' );
		if ( ended ) { toggleEnded( ended ); return; }

		var ra = t.closest( '.rapm-ra-toggle' );
		if ( ra ) { setRa( 'true' !== ra.getAttribute( 'aria-expanded' ) ); return; }

		if ( ! t.closest( '.rapm-card-menu' ) ) { closeMenus(); }
	} );

	document.addEventListener( 'keydown', function ( e ) {
		if ( 'Escape' !== e.key ) { return; }
		var open = root.querySelector( '.rapm-card-menu:not([hidden])' );
		if ( ! open ) { return; }
		closeMenus();
		var more = open.parentNode.querySelector( '.rapm-card-more' );
		if ( more ) { more.focus(); }
	} );
} )();
