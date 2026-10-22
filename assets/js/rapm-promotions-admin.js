/**
 * Promotions screen (RAPM_Promotions_Screen): the on/off switches, the
 * "…" menus, drag (and Move earlier/later) to reorder, Find a promotion,
 * Show ended, renaming and adding spots. Plain script, no dependencies.
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
			// A split slider (1.44.1) has an Add card and ended row per area.
			section.querySelectorAll( '.rapm-add, .rapm-ended-row' ).forEach( function ( el ) { el.hidden = !! q; } );
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

	/* ---- Shop the Look: "Tabs on the website, in this order" (1.30.0) ---- */

	// Same rule as RAPM_Promotions_Screen::render_tab_order(): the looks
	// showing now, in card order, by tab name.
	function refreshTabOrder( section ) {
		var box = section && section.querySelector( '.rapm-tab-order' );
		if ( ! box ) { return; }
		var tabs = cardsIn( section )
			.filter( function ( c ) { return c.classList.contains( 'is-showing' ) && c.getAttribute( 'data-tab' ); } )
			.map( function ( c ) { return c.getAttribute( 'data-tab' ); } );
		var label = box.querySelector( '.rapm-tab-order-label' );
		Array.prototype.slice.call( box.children ).forEach( function ( el ) {
			if ( el !== label ) { box.removeChild( el ); }
		} );
		if ( ! tabs.length ) {
			var none = document.createElement( 'span' );
			none.className   = 'rapm-tab-order-none';
			none.textContent = box.getAttribute( 'data-none' ) || '';
			box.appendChild( none );
			return;
		}
		var list = document.createElement( 'ol' );
		list.className = 'rapm-tab-order-list';
		tabs.forEach( function ( tab ) {
			var li = document.createElement( 'li' );
			li.textContent = tab;
			list.appendChild( li );
		} );
		box.appendChild( list );
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
				refreshTabOrder( section );
				// The count of the card's own area in a split slider (1.44.1).
				( sw.closest( '.rapm-split-area' ) || section ).querySelector( '.rapm-spot-count' ).textContent = res.data.countLine;
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
		refreshTabOrder( section );
		say( section, T.saving );
		post( { action: 'rapm_reorder_slides', nonce: cfg.reorderNonce, order: orderOf( section ) } )
			.then( function ( res ) { say( section, res && res.success ? T.orderSaved : T.saveFailed ); } )
			.catch( function () { say( section, T.saveFailed ); } );
	}

	function moveCard( card, dir ) {
		var section = card.closest( '.rapm-spot' );
		// Only within its own grid: a split slider (1.44.1) has two.
		var shown   = Array.prototype.slice.call( card.parentNode.children ).filter( function ( c ) { return c.classList.contains( 'rapm-card' ) && ! c.hidden; } );
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

	/* ---- Split view layout (1.43.0) ----------------------------------------- */

	root.addEventListener( 'change', function ( e ) {
		var form = e.target.closest ? e.target.closest( '.rapm-layout' ) : null;
		if ( ! form || 'split' !== e.target.name ) { return; }
		form.querySelector( '.rapm-layout-split' ).hidden = '1' !== e.target.value;
	} );

	root.addEventListener( 'submit', function ( e ) {
		var form = e.target.closest ? e.target.closest( '.rapm-layout' ) : null;
		if ( ! form ) { return; }
		e.preventDefault();
		var section = form.closest( '.rapm-spot' );
		var picked  = form.querySelector( 'input[name="split"]:checked' );
		var button  = form.querySelector( 'button[type="submit"]' );
		button.disabled = true;
		say( section, T.saving );
		post( {
			action: 'rapm_save_layout',
			nonce: cfg.nonce,
			placement: section.getAttribute( 'data-placement' ),
			split: picked ? picked.value : '0',
			cols: form.querySelector( 'select[name="cols"]' ).value,
			side: form.querySelector( 'select[name="side"]' ).value,
			phone: form.querySelector( 'select[name="phone"]' ).value,
			width: form.querySelector( 'select[name="width"]' ).value,
			// 1.52.0: how the promotion box changes.
			box: ( form.querySelector( 'input[name="box"]:checked' ) || { value: 'still' } ).value,
			every: form.querySelector( 'select[name="every"]' ).value
		} ).then( function ( res ) {
			if ( ! res || ! res.success ) { throw new Error( 'save failed' ); }
			// Split changes the picture size the slider asks for and adds
			// its promotion box below, so show the screen fresh.
			window.location.reload();
		} ).catch( function () {
			button.disabled = false;
			say( section, T.saveFailed );
		} );
	} );

	/* ---- Linked groups: "Goes with" on a room photo (1.52.0) ---------------- */

	root.addEventListener( 'change', function ( e ) {
		var sel = e.target;
		if ( ! sel.hasAttribute || ! sel.hasAttribute( 'data-goes' ) ) { return; }
		var card    = sel.closest( '.rapm-card' );
		var section = sel.closest( '.rapm-spot' );
		var opt     = sel.options[ sel.selectedIndex ];
		sel.disabled = true;
		say( section, T.saving );
		post( { action: 'rapm_goes_with', nonce: cfg.nonce, id: sel.getAttribute( 'data-goes' ), box: sel.value } ).then( function ( res ) {
			if ( ! res || ! res.success ) { throw new Error( 'save failed' ); }
			card.style.setProperty( '--rapm-group', opt.getAttribute( 'data-color' ) );
			var issue = card.querySelector( '.rapm-goes-ended' );
			if ( issue ) { issue.remove(); }
			// Recount each promotion's photos from the menus.
			Array.prototype.forEach.call( section.querySelectorAll( '[data-group]' ), function ( p ) {
				var n = section.querySelectorAll( 'select[data-goes]' );
				var count = Array.prototype.filter.call( n, function ( s ) { return s.value === p.getAttribute( 'data-group' ); } ).length;
				p.textContent = ( 1 === count ? T.goesOne : T.goesMany ).replace( '%d', count );
			} );
			say( section, T.goesSaved );
		} ).catch( function () {
			say( section, T.saveFailed );
		} ).then( function () { sel.disabled = false; } );
	} );

	/* ---- Design Center: "Make new reveal panels" (1.53.0) -------------------- */

	root.addEventListener( 'submit', function ( e ) {
		var form = e.target.closest ? e.target.closest( '.rapm-make-reveal' ) : null;
		if ( ! form ) { return; }
		e.preventDefault();
		var section = form.closest( '.rapm-spot' );
		var name    = form.querySelector( 'input[name="name"]' );
		if ( ! name.value.trim() ) { name.focus(); return; }
		say( section, T.saving );
		post( { action: 'rapm_create_spot', nonce: cfg.nonce, kind: 'reveal', name: name.value.trim() } ).then( function ( res ) {
			if ( ! res || ! res.success ) { throw new Error( res && res.data && res.data.message ? res.data.message : '' ); }
			window.location.href = res.data.url; // 1.58.1: opens the new spot's first panel.
		} ).catch( function ( err ) { say( section, err.message || T.saveFailed ); } );
	} );

	/* ---- Design Center: a new split slider (1.58.1) ------------------------- */

	root.addEventListener( 'submit', function ( e ) {
		var form = e.target.closest ? e.target.closest( '.rapm-make-new-split' ) : null;
		if ( ! form ) { return; }
		e.preventDefault();
		var section = form.closest( '.rapm-spot' );
		var name    = form.querySelector( 'input[name="name"]' );
		if ( ! name.value.trim() ) { name.focus(); return; }
		say( section, T.saving );
		post( { action: 'rapm_create_spot', nonce: cfg.nonce, kind: 'hero', split: '1', name: name.value.trim() } ).then( function ( res ) {
			if ( ! res || ! res.success ) { throw new Error( res && res.data && res.data.message ? res.data.message : '' ); }
			window.location.href = res.data.url; // Opens its first room photo.
		} ).catch( function ( err ) { say( section, err.message || T.saveFailed ); } );
	} );

	/* ---- Design Center: "Turn on split view" (1.46.0) ----------------------- */

	root.addEventListener( 'submit', function ( e ) {
		var form = e.target.closest ? e.target.closest( '.rapm-make-split' ) : null;
		if ( ! form ) { return; }
		e.preventDefault();
		var section = form.closest( '.rapm-spot' );
		var button  = form.querySelector( 'button[type="submit"]' );
		var place   = form.querySelector( 'select[name="placement"]' ).value;
		button.disabled = true;
		say( section, T.saving );
		post( { action: 'rapm_save_layout', nonce: cfg.nonce, placement: place, split: '1', cols: '60-40', side: 'right', phone: 'promo', width: 'full' } ).then( function ( res ) {
			if ( ! res || ! res.success ) { throw new Error( 'save failed' ); }
			window.location.hash = 'rapm-spot-hero-' + place;
			window.location.reload();
		} ).catch( function () {
			button.disabled = false;
			say( section, T.saveFailed );
		} );
	} );

	/* ---- "Remove this spot" (1.29.1) ---------------------------------------- */

	root.addEventListener( 'click', function ( e ) {
		var button = e.target.closest ? e.target.closest( '.rapm-remove-spot' ) : null;
		if ( ! button ) { return; }
		var section = button.closest( '.rapm-spot' );
		var name    = section.querySelector( '.rapm-spot-name' ).textContent;
		if ( ! window.confirm( ( T.confirmRemove || '' ).replace( '%s', name ) ) ) { return; }
		button.disabled = true;
		say( section, T.saving );
		post( {
			action: 'rapm_remove_spot',
			nonce: cfg.nonce,
			kind: section.getAttribute( 'data-kind' ),
			placement: section.getAttribute( 'data-placement' )
		} ).then( function ( res ) {
			if ( ! res || ! res.success ) {
				button.disabled = false;
				say( section, res && res.data && res.data.message ? res.data.message : T.saveFailed );
				return;
			}
			window.location.href = res.data.url;
		} ).catch( function () {
			button.disabled = false;
			say( section, T.saveFailed );
		} );
	} );

	/* ---- "+ New spot" (1.29.0) ---------------------------------------------- */

	var dialog = root.querySelector( '.rapm-new-spot' );
	if ( dialog ) {
		var form        = dialog.querySelector( '.rapm-ns-form' );
		var nameInput   = form.querySelector( 'input[name="name"]' );
		var previewName = form.querySelector( '.rapm-ns-preview-name' );
		var previewType = form.querySelector( '.rapm-ns-preview-type' );
		var status      = form.querySelector( '.rapm-ns-status' );

		var refresh = function () {
			var picked = form.querySelector( 'input[name="kind"]:checked' );
			nameInput.placeholder   = picked.getAttribute( 'data-example' );
			previewType.textContent = picked.getAttribute( 'data-label' );
			previewName.textContent = nameInput.value.trim() || picked.getAttribute( 'data-example' ).replace( /^[^:]*:\s*/, '' );
		};
		form.addEventListener( 'change', refresh );
		nameInput.addEventListener( 'input', refresh );
		refresh();

		// 1.55.0: every "+ New spot" button (the header's and the empty page's).
		root.addEventListener( 'click', function ( e ) {
			if ( ! e.target.closest || ! e.target.closest( '.rapm-new-spot-open' ) ) { return; }
			status.textContent = '';
			status.classList.remove( 'is-error' );
			if ( dialog.showModal ) { dialog.showModal(); } else { dialog.setAttribute( 'open', '' ); }
			nameInput.focus();
		} );
		form.querySelector( '.rapm-ns-cancel' ).addEventListener( 'click', function () {
			if ( dialog.close ) { dialog.close(); } else { dialog.removeAttribute( 'open' ); }
		} );
		var setStatus = function ( text, isError ) {
			status.textContent = text;
			status.classList.toggle( 'is-error', !! isError );
		};
		form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			setStatus( T.saving );
			post( {
				action: 'rapm_create_spot',
				nonce: cfg.nonce,
				kind: form.querySelector( 'input[name="kind"]:checked' ).value,
				name: nameInput.value
			} ).then( function ( res ) {
				if ( ! res || ! res.success ) {
					setStatus( res && res.data && res.data.message ? res.data.message : T.saveFailed, true );
					return;
				}
				window.location.href = res.data.url;
			} ).catch( function () { setStatus( T.saveFailed, true ); } );
		} );
	}

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
