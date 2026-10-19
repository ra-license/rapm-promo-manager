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
 *
 * 1.45.0: an icon also has f, where it's from: ms (Google Material
 * Symbols, the default), or fas, far or fab (Font Awesome solid, regular
 * or brands, read from the site's own Elementor), and v, its drawing area
 * ("0 0 width height"), since Font Awesome icons vary in width. k can also
 * be img, a picture from the Media Library (a logo or badge): a is its
 * attachment ID and s its width; u is only its address for this editor,
 * which the server works out again.
 *
 * 1.50.0: the brand kit (RAPM_Brand_Kit) supplies the color choices (the
 * website's Elementor colors, kept linked as cg, then saved colors), text
 * styles (copied onto a piece: font, fw weight, size, color, tt ALL CAPS),
 * logos first under "Your pictures", and templates ("Start from a
 * template", "Save as template…").
 *
 * 1.51.0 (canvas power tools, from the mockup Phil approved on 2026-10-19,
 * https://claude.ai/artifact/QGMZMdpipShtPp8TWk9MMw): the list order is the
 * front-to-back order (last is in front, as it was always drawn); Layers
 * shows it top-first with forward/back, hide (h: kept, not on the website)
 * and lock (l: can't be dragged or nudged). Shift-click picks several
 * pieces: they drag together, and Arrange lines them up or spaces them
 * evenly (one piece lines up with the square). Dragging snaps to the
 * square's middle and edges and to other pieces, with pink guides (hold
 * Alt/Option to skip). Words can have a shadow (sh: 1 soft, 2 strong) and
 * an outline (ol: 1 thin, 2 thick; oc its color, ocg a linked site color).
 * Undo and Redo (Ctrl/Cmd+Z, Shift+Ctrl/Cmd+Z or Ctrl+Y) keep the last 30
 * steps of this visit.
 *
 * 1.47.0: a headline, smaller line or button can have its own font: ff, a
 * Google Fonts family (copied to the website when the promotion is saved,
 * RAPM_Fonts), or fg, one of the site's Elementor brand fonts by ID.
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
	var LIMITS = { headline: [ 3, 20 ], sub: [ 2, 12 ], btn: [ 2, 8 ], icon: [ 4, 40 ], img: [ 5, 100 ] };
	var MS_BOX = '0 -960 960 960';
	// Font Awesome names a furniture store reaches for first (1.45.0).
	var FA_STARTERS = [ 'couch', 'bed', 'chair', 'tv', 'lightbulb', 'home', 'store', 'truck', 'shipping-fast', 'tag', 'tags', 'percent', 'percentage', 'dollar-sign', 'credit-card', 'gift', 'star', 'award', 'medal', 'certificate', 'check-circle', 'calendar-alt', 'clock', 'heart', 'thumbs-up', 'phone', 'map-marker-alt', 'facebook', 'instagram', 'pinterest' ];
	// Font Awesome has names but no search words, so a few shopper words
	// point at the right icons (1.45.0; "sofa" found nothing without this).
	var FA_WORDS = { sofa: 'couch', loveseat: 'couch', sectional: 'couch', mattress: 'bed', bedroom: 'bed', recliner: 'chair', delivery: 'truck shipping-fast', shipping: 'truck shipping-fast', sale: 'tag tags percent percentage', discount: 'percent percentage tag', deal: 'tag tags', financing: 'credit-card money-check-alt', payment: 'credit-card', price: 'tag dollar-sign', gift: 'gift', award: 'award medal trophy', quality: 'award certificate check-circle', location: 'map-marker-alt', store: 'store store-alt', showroom: 'store', calendar: 'calendar-alt calendar', event: 'calendar-alt', social: 'facebook instagram pinterest twitter youtube' };
	var faBase = root.getAttribute( 'data-fa' ) || '';
	var kit = JSON.parse( root.getAttribute( 'data-kit' ) || '{}' );
	kit.site = kit.site || [];
	kit.colors = kit.colors || [];
	kit.styles = kit.styles || [];
	kit.logos = kit.logos || [];
	kit.templates = kit.templates || [];
	// 1.47.0: fonts. brand is the site's Elementor fonts, ID => [ title, family ].
	var brand = JSON.parse( root.getAttribute( 'data-brand' ) || '{}' );
	var gList = null; // [ family, category, weights ] from the bundled list, loaded on first use
	var fontOpen = false;
	var fontQuery = '';
	var fontLoaded = {};
	var faList = null; // [ name, style, width, height, path ] from Elementor's Font Awesome, loaded on first use
	var tab = 'ms';

	var state = parse( field.value );
	// A linked color shows the website's color today, if it was changed in Elementor.
	state.items.forEach( function ( it ) {
		kit.site.forEach( function ( c ) {
			if ( it.cg === c.id ) { it.c = c.hex; }
			if ( it.ocg === c.id ) { it.oc = c.hex; }
		} );
	} );
	var selected = -1;
	// 1.51.0: every picked piece (Shift-click); selected is the one whose
	// settings show. render() keeps the two in step.
	var picked = [];
	// 1.51.0: Undo and Redo. Each step keeps the design before it (s) and,
	// when the step changed the words (a template), the words before (w).
	var undoStack = [];
	var redoStack = [];
	var committed = JSON.stringify( state );
	var lastKey = '';
	var lastAt = 0;
	var pendingWords = null;
	var UNDO_MAX = 30;
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

	// Saves the design to the form and, if it changed, makes it one Undo
	// step. Steps with the same key close together (nudging with the
	// arrows, sliding the size) count as one.
	function save( key ) {
		field.value = JSON.stringify( state );
		if ( field.value !== committed ) {
			var now = Date.now();
			if ( pendingWords || ! key || key !== lastKey || now - lastAt > 1500 ) {
				undoStack.push( { s: committed, w: pendingWords } );
				if ( undoStack.length > UNDO_MAX ) { undoStack.shift(); }
			}
			redoStack = [];
			committed = field.value;
			lastKey = key || '';
			lastAt = now;
		}
		pendingWords = null;
		renderLayers();
		renderHistory();
	}

	function currentWords() {
		return { headline: words( 'headline' ), sub: words( 'sub' ), btn: words( 'btn' ) };
	}

	function setWords( w ) {
		var ids = { headline: 'rapm_headline', sub: 'rapm_subhead', btn: 'rapm_cta_text' };
		Object.keys( ids ).forEach( function ( k ) {
			var el = document.getElementById( ids[ k ] );
			if ( el && undefined !== w[ k ] ) { el.value = w[ k ]; }
		} );
	}

	function stepBack( from, to ) {
		var step = from.pop();
		if ( ! step ) { return; }
		to.push( { s: committed, w: step.w ? currentWords() : null } );
		if ( step.w ) { setWords( step.w ); }
		state = JSON.parse( step.s );
		committed = step.s;
		lastKey = '';
		selected = -1;
		render();
		field.value = committed;
		renderHistory();
	}

	function undo() { stepBack( undoStack, redoStack ); }
	function redo() { stepBack( redoStack, undoStack ); }

	function renderHistory() {
		var u = root.querySelector( '#rapm-cv-undo' ), r = root.querySelector( '#rapm-cv-redo' );
		if ( u ) { u.disabled = ! undoStack.length; }
		if ( r ) { r.disabled = ! redoStack.length; }
	}

	function words( k ) {
		var id = { headline: 'rapm_headline', sub: 'rapm_subhead', btn: 'rapm_cta_text' }[ k ];
		var el = id && document.getElementById( id );
		return el ? el.value.trim() : '';
	}

	function label( it ) {
		if ( 'icon' === it.k ) { return T.icon + ': ' + it.n.replace( /[_-]/g, ' ' ); }
		if ( 'img' === it.k ) { return T.picture + ( it.t ? ': ' + it.t : '' ); }
		return { headline: T.headline, sub: T.sub, btn: T.btn }[ it.k ];
	}

	function svg( d, v ) {
		return '<svg viewBox="' + esc( v || MS_BOX ) + '" aria-hidden="true" focusable="false"><path d="' + d + '"/></svg>';
	}

	function isWords( it ) {
		return 'icon' !== it.k && 'img' !== it.k;
	}

	// 1.51.0: shadow and outline, the same value the website uses
	// (RAPM_Split::text_shadow()). The outline is eight thin shadows.
	function em( v ) {
		v = Math.round( v * 1000 ) / 1000;
		return ( 0 === v ? '0' : v.toFixed( 3 ).replace( /0+$/, '' ).replace( /\.$/, '' ) ) + 'em';
	}

	function textShadow( it ) {
		var parts = [];
		if ( it.ol ) {
			var w = 2 === it.ol ? 0.06 : 0.03;
			for ( var a = 0; a < 8; a++ ) {
				var r = a * Math.PI / 4;
				parts.push( em( Math.cos( r ) * w ) + ' ' + em( Math.sin( r ) * w ) + ' 0 ' + ( it.oc || '#1d3557' ) );
			}
		}
		if ( it.sh ) {
			parts.push( 2 === it.sh ? '0 0.06em 0.2em rgba(0,0,0,.75), 0 0 0.5em rgba(0,0,0,.35)' : '0 0.04em 0.12em rgba(0,0,0,.45)' );
		}
		return parts.join( ', ' );
	}

	/* ---- Fonts (1.47.0) ------------------------------------------------- */

	function fontName( it ) {
		if ( it.ff ) { return it.ff; }
		if ( it.fg && brand[ it.fg ] ) { return brand[ it.fg ][ 0 ] + ' (' + brand[ it.fg ][ 1 ] + ')'; }
		return T.fontDefault;
	}

	function fontFamilyOf( it ) {
		if ( it.ff ) { return it.ff; }
		if ( it.fg && brand[ it.fg ] ) { return brand[ it.fg ][ 1 ]; }
		return '';
	}

	// Loads families from Google for this editor only (the website uses its
	// own copy). With text, only the letters of the sample are fetched.
	function loadFonts( families, text ) {
		families = families.filter( function ( f ) { return f && ! fontLoaded[ f + ( text ? '|s' : '' ) ]; } );
		if ( ! families.length ) { return; }
		families.forEach( function ( f ) { fontLoaded[ f + ( text ? '|s' : '' ) ] = true; } );
		var link = document.createElement( 'link' );
		link.rel = 'stylesheet';
		link.href = 'https://fonts.googleapis.com/css2?' + families.map( function ( f ) {
			return 'family=' + encodeURIComponent( f ).replace( /%20/g, '+' ) + ( text ? '' : ':wght@400;700;800' );
		} ).join( '&' ) + '&display=swap' + ( text ? '&text=' + encodeURIComponent( text ) : '' );
		document.head.appendChild( link );
	}

	function loadGoogleList() {
		if ( gList ) { return Promise.resolve( gList ); }
		return fetch( root.getAttribute( 'data-gfonts' ) ).then( function ( r ) { return r.json(); } ).then( function ( j ) {
			gList = j.fonts;
			return gList;
		} );
	}

	function fontButton( attr, value, label, family, on ) {
		return '<button type="button" class="rapm-cv-font" ' + attr + '="' + esc( value ) + '" aria-pressed="' + on + '">' +
			'<span class="rapm-cv-font-sample" style="font-family:' + ( family ? esc( '"' + family + '", sans-serif' ) : 'inherit' ) + '">' + esc( T.fontSample ) + '</span>' +
			'<span class="rapm-cv-font-name">' + esc( label ) + '</span></button>';
	}

	function renderFontList() {
		var box = selBox.querySelector( '.rapm-cv-fontlist' );
		var it = state.items[ selected ];
		if ( ! box || ! it ) { return; }
		var q = fontQuery.trim().toLowerCase();
		var html = '<p class="rapm-cv-font-group">' + esc( T.fontBrand ) + '</p><div class="rapm-cv-fonts">';
		if ( ! q || T.fontDefault.toLowerCase().indexOf( q ) !== -1 ) {
			html += fontButton( 'data-fdef', '1', T.fontDefault, '', ! it.ff && ! it.fg );
		}
		var brandFamilies = [];
		Object.keys( brand ).forEach( function ( id ) {
			var b = brand[ id ];
			if ( q && ( b[ 0 ] + ' ' + b[ 1 ] ).toLowerCase().indexOf( q ) === -1 ) { return; }
			brandFamilies.push( b[ 1 ] );
			html += fontButton( 'data-fg', id, b[ 0 ] + ' (' + b[ 1 ] + ')', b[ 1 ], it.fg === id );
		} );
		html += '</div><p class="rapm-cv-font-group">' + esc( T.fontMore ) + '</p><div class="rapm-cv-fonts">';
		var shown = [];
		if ( gList ) {
			for ( var i = 0; i < gList.length && shown.length < ( q ? 60 : 40 ); i++ ) {
				if ( ! q || gList[ i ][ 0 ].toLowerCase().indexOf( q ) !== -1 ) { shown.push( gList[ i ][ 0 ] ); }
			}
		}
		html += shown.map( function ( f ) { return fontButton( 'data-ff', f, f, f, it.ff === f ); } ).join( '' );
		html += '</div>';
		if ( gList && ! shown.length ) { html += '<p class="description">' + esc( T.fontNone ) + '</p>'; }
		box.innerHTML = html;
		loadFonts( brandFamilies.concat( shown ), T.fontSample );
	}

	function esc( t ) {
		return String( t ).replace( /[&<>"]/g, function ( c ) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[ c ]; } );
	}

	/* ---- Drawing the design ------------------------------------------- */

	function render() {
		picked = picked.filter( function ( i ) { return i >= 0 && i < state.items.length; } );
		if ( selected >= state.items.length ) { selected = -1; }
		if ( selected < 0 ) { picked = []; } else if ( -1 === picked.indexOf( selected ) ) { picked = [ selected ]; }
		Array.prototype.slice.call( board.querySelectorAll( '.rapm-cv-item' ) ).forEach( function ( n ) { n.remove(); } );
		var style = document.getElementById( 'rapm_text_style' );
		var font = document.getElementById( 'rapm_text_font' );
		board.setAttribute( 'data-style', style ? style.value : 'bold' );
		board.style.fontFamily = font && fonts[ font.value ] ? fonts[ font.value ] : '';
		state.items.forEach( function ( it, i ) {
			var text = isWords( it ) ? words( it.k ) : '';
			var n = document.createElement( 'div' );
			n.className = 'rapm-cv-item rapm-pb-t is-' + it.k + ( -1 !== picked.indexOf( i ) ? ' is-on' : '' ) + ( isWords( it ) && ! text ? ' is-empty' : '' ) + ( it.h ? ' is-hidden' : '' ) + ( it.l ? ' is-locked' : '' );
			n.setAttribute( 'data-i', i );
			if ( it.l ) { n.setAttribute( 'data-locked', T.locked ); }
			place( n, it );
			if ( 'icon' === it.k ) {
				n.innerHTML = svg( it.d, it.v );
			} else if ( 'img' === it.k ) {
				n.innerHTML = '<img src="' + esc( it.u || '' ) + '" alt="" draggable="false">';
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
		renderArrange();
		renderHistory();
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
		} else if ( 'img' !== it.k ) {
			n.style.color = it.c;
		}
		if ( isWords( it ) ) {
			n.style.fontWeight = it.fw ? String( it.fw ) : '';
			n.style.textTransform = it.tt ? 'uppercase' : '';
			var fam = fontFamilyOf( it );
			n.style.fontFamily = fam ? '"' + fam + '", sans-serif' : '';
			if ( fam ) { loadFonts( [ fam ] ); }
			n.style.textShadow = textShadow( it );
		}
	}

	// 1.51.0: top of the list is in front (the last piece), with forward,
	// back, hide and lock for each.
	var LICON = {
		up: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 6l-6 6h4v6h4v-6h4z"/></svg>',
		down: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 18l6-6h-4V6h-4v6H6z"/></svg>',
		eye: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5C6 5 2 12 2 12s4 7 10 7 10-7 10-7-4-7-10-7zm0 11a4 4 0 110-8 4 4 0 010 8z"/></svg>',
		eyeoff: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 4l17 17-1.4 1.4-3.2-3.2A11 11 0 0112 19C6 19 2 12 2 12a18 18 0 014.3-4.9L1.6 5.4zm9 1c6 0 10 7 10 7a18 18 0 01-3 3.8L8.9 5.7A10 10 0 0112 5z"/></svg>',
		lock: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 10V8a6 6 0 1112 0v2h1v12H5V10zm2 0h8V8a4 4 0 10-8 0z"/></svg>',
		unlock: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 10h11v12H5V10h1V8a6 6 0 0111.5-2.4l-1.9.8A4 4 0 008 8z"/></svg>'
	};

	function renderLayers() {
		var box = root.querySelector( '.rapm-cv-layers' );
		var n = state.items.length;
		var rows = [];
		for ( var i = n - 1; i >= 0; i-- ) {
			var it = state.items[ i ];
			var name = esc( label( it ) );
			rows.push( '<div class="rapm-cv-lrow' + ( it.h ? ' is-off' : '' ) + '">' +
				'<button type="button" class="rapm-cv-layer" data-i="' + i + '" aria-pressed="' + ( -1 !== picked.indexOf( i ) ) + '">' +
				( 'icon' === it.k ? '<span class="rapm-cv-layer-ic" style="color:' + esc( it.c ) + '">' + svg( it.d, it.v ) + '</span>' : '' ) +
				( 'img' === it.k ? '<span class="rapm-cv-layer-ic"><img src="' + esc( it.u || '' ) + '" alt=""></span>' : '' ) +
				'<span class="rapm-cv-lname">' + name + '</span></button>' +
				'<span class="rapm-cv-lacts">' +
				'<button type="button" class="rapm-cv-ib" data-fwd="' + i + '"' + ( i === n - 1 ? ' disabled' : '' ) + ' title="' + esc( T.forward ) + '" aria-label="' + esc( T.forward ) + ': ' + name + '">' + LICON.up + '</button>' +
				'<button type="button" class="rapm-cv-ib" data-back="' + i + '"' + ( 0 === i ? ' disabled' : '' ) + ' title="' + esc( T.back ) + '" aria-label="' + esc( T.back ) + ': ' + name + '">' + LICON.down + '</button>' +
				'<button type="button" class="rapm-cv-ib" data-hide="' + i + '" aria-pressed="' + !! it.h + '" title="' + esc( it.h ? T.show : T.hide ) + '" aria-label="' + esc( it.h ? T.show : T.hide ) + ': ' + name + '">' + ( it.h ? LICON.eyeoff : LICON.eye ) + '</button>' +
				'<button type="button" class="rapm-cv-ib" data-lock="' + i + '" aria-pressed="' + !! it.l + '" title="' + esc( it.l ? T.unlock : T.lock ) + '" aria-label="' + esc( it.l ? T.unlock : T.lock ) + ': ' + name + '">' + ( it.l ? LICON.lock : LICON.unlock ) + '</button>' +
				'</span></div>' );
		}
		box.innerHTML = rows.join( '' );
	}

	// Moves piece i one step forward (by 1) or back (by -1); the picked
	// pieces stay picked.
	function moveLayer( i, by ) {
		var j = i + by;
		if ( j < 0 || j >= state.items.length ) { return; }
		var a = state.items[ i ];
		state.items[ i ] = state.items[ j ];
		state.items[ j ] = a;
		var swap = function ( x ) { return x === i ? j : ( x === j ? i : x ); };
		picked = picked.map( swap );
		selected = swap( selected );
		render();
		save();
	}

	/* ---- Arrange (1.51.0) ------------------------------------------------ */

	// Where piece i sits, in percent of the square.
	function bounds( i ) {
		var n = board.querySelector( '.rapm-cv-item[data-i="' + i + '"]' );
		var b = board.getBoundingClientRect();
		if ( ! n || ! b.width ) { return null; }
		var r = n.getBoundingClientRect();
		return { l: ( r.left - b.left ) / b.width * 100, r: ( r.right - b.left ) / b.width * 100, t: ( r.top - b.top ) / b.height * 100, b: ( r.bottom - b.top ) / b.height * 100, w: r.width / b.width * 100, h: r.height / b.height * 100 };
	}

	function movable() {
		return picked.filter( function ( i ) { var it = state.items[ i ]; return it && ! it.h; } );
	}

	function renderArrange() {
		var box = root.querySelector( '.rapm-cv-arrange' );
		if ( ! box ) { return; }
		var live = movable();
		var locked = live.some( function ( i ) { return state.items[ i ].l; } );
		var note = ! live.length ? T.arrangeNone : ( 1 === live.length ? T.arrangeOne : T.arrangeMany.replace( '%d', live.length ) + ( live.length < 3 ? ' ' + T.arrangeThree : '' ) );
		if ( locked ) { note = T.arrangeLocked; }
		box.querySelector( '.rapm-cv-arrnote' ).textContent = note;
		Array.prototype.forEach.call( box.querySelectorAll( '[data-al]' ), function ( b ) { b.disabled = ! live.length || locked; } );
		Array.prototype.forEach.call( box.querySelectorAll( '[data-dist]' ), function ( b ) { b.disabled = live.length < 3 || locked; } );
	}

	function clampAll( list ) {
		list.forEach( function ( i ) {
			var it = state.items[ i ];
			it.x = Math.round( Math.max( 0, Math.min( 100, it.x ) ) * 10 ) / 10;
			it.y = Math.round( Math.max( 0, Math.min( 100, it.y ) ) * 10 ) / 10;
		} );
	}

	function align( how ) {
		var live = movable();
		var bs = live.map( bounds );
		if ( ! live.length || bs.indexOf( null ) !== -1 ) { return; }
		var box = 1 === live.length ? { l: 0, r: 100, t: 0, b: 100 } : {
			l: Math.min.apply( null, bs.map( function ( b ) { return b.l; } ) ),
			r: Math.max.apply( null, bs.map( function ( b ) { return b.r; } ) ),
			t: Math.min.apply( null, bs.map( function ( b ) { return b.t; } ) ),
			b: Math.max.apply( null, bs.map( function ( b ) { return b.b; } ) )
		};
		live.forEach( function ( i, n ) {
			var it = state.items[ i ], b = bs[ n ];
			if ( 'left' === how ) { it.x += box.l - b.l; }
			if ( 'right' === how ) { it.x += box.r - b.r; }
			if ( 'cx' === how ) { it.x += ( box.l + box.r ) / 2 - ( b.l + b.r ) / 2; }
			if ( 'top' === how ) { it.y += box.t - b.t; }
			if ( 'bottom' === how ) { it.y += box.b - b.b; }
			if ( 'cy' === how ) { it.y += ( box.t + box.b ) / 2 - ( b.t + b.b ) / 2; }
		} );
		clampAll( live );
		render();
		save();
	}

	// Equal gaps between the pieces, keeping the first and last in place.
	function distribute( axis ) {
		var live = movable().map( function ( i ) { return { i: i, b: bounds( i ) }; } );
		if ( live.length < 3 || live.some( function ( o ) { return ! o.b; } ) ) { return; }
		var lo = 'x' === axis ? 'l' : 't', hi = 'x' === axis ? 'r' : 'b', size = 'x' === axis ? 'w' : 'h';
		live.sort( function ( a, c ) { return a.b[ lo ] - c.b[ lo ]; } );
		var start = live[ 0 ].b[ lo ];
		var end = Math.max.apply( null, live.map( function ( o ) { return o.b[ hi ]; } ) );
		var total = live.reduce( function ( s, o ) { return s + o.b[ size ]; }, 0 );
		var gap = ( end - start - total ) / ( live.length - 1 );
		var pos = start;
		live.forEach( function ( o ) {
			state.items[ o.i ][ axis ] += pos - o.b[ lo ];
			pos += o.b[ size ] + gap;
		} );
		clampAll( live.map( function ( o ) { return o.i; } ) );
		render();
		save();
		status( gap < 0 ? T.overlap : '' );
	}

	function status( t ) {
		var el = root.querySelector( '#rapm-cv-status' );
		if ( el ) { el.textContent = t; }
	}

	function renderSel() {
		var it = state.items[ selected ];
		if ( ! it ) {
			selBox.innerHTML = '<p class="description">' + esc( T.pick ) + '</p>';
			return;
		}
		var lim = LIMITS[ it.k ];
		var h = '<p><strong>' + esc( label( it ) ) + '</strong></p>';
		if ( picked.length > 1 ) { h += '<p class="description">' + esc( T.pickedMany.replace( '%d', picked.length ) ) + '</p>'; }
		if ( isWords( it ) ) {
			h += '<p class="description">' + esc( T.wordsAbove ) + '</p>';
			h += '<p><span>' + esc( T.font ) + '</span><br><button type="button" class="button rapm-cv-fontbtn" aria-expanded="' + fontOpen + '">' + esc( fontName( it ) ) + '</button></p>';
			h += '<div class="rapm-cv-fontpick"' + ( fontOpen ? '' : ' hidden' ) + '><input type="search" class="rapm-cv-fontsearch regular-text" placeholder="' + esc( T.fontSearch ) + '" value="' + esc( fontQuery ) + '"><div class="rapm-cv-fontlist"></div><p class="description">' + esc( T.fontNote ) + '</p></div>';
		}
		h += '<p><label for="rapm-cv-size">' + esc( T.size ) + '</label><br><input type="range" id="rapm-cv-size" min="' + lim[ 0 ] + '" max="' + lim[ 1 ] + '" step="0.2" value="' + it.s + '"></p>';
		if ( isWords( it ) && kit.styles.length ) {
			h += '<p><label for="rapm-cv-style">' + esc( T.textStyle ) + '</label><br><select id="rapm-cv-style"><option value="">' + esc( T.noStyle ) + '</option>' +
				kit.styles.map( function ( st, i ) { return '<option value="' + i + '">' + esc( st.name || ( 'Style ' + ( i + 1 ) ) ) + '</option>'; } ).join( '' ) + '</select></p>';
		}
		if ( isWords( it ) ) {
			h += '<p><label><input type="checkbox" id="rapm-cv-caps"' + ( it.tt ? ' checked' : '' ) + '> ' + esc( T.caps ) + '</label></p>';
		}
		if ( 'img' !== it.k ) {
			var sw = function ( c, cg, name ) {
				return '<button type="button" class="rapm-cv-sw" data-color="' + esc( c ) + '"' + ( cg ? ' data-cg="' + esc( cg ) + '"' : '' ) + ' style="background:' + esc( c ) + '" aria-label="' + esc( name || c ) + '" title="' + esc( name || c ) + '" aria-pressed="' + ( c === it.c && ( cg || '' ) === ( it.cg || '' ) ) + '"></button>';
			};
			h += '<p><span>' + esc( 'btn' === it.k ? T.btnColor : T.color ) + '</span></p>';
			if ( kit.site.length ) {
				h += '<p class="rapm-cv-swrow"><span class="rapm-cv-swlabel">' + esc( T.siteColors ) + '</span><span class="rapm-cv-swatches">' + kit.site.map( function ( c ) { return sw( c.hex, c.id, c.name ); } ).join( '' ) + '</span></p>';
			}
			var saved = kit.colors.length ? kit.colors.map( function ( c ) { return sw( c.hex, '', c.name ); } ) : SWATCHES.map( function ( c ) { return sw( c, '', c ); } );
			h += '<p class="rapm-cv-swrow"><span class="rapm-cv-swlabel">' + esc( T.savedColors ) + '</span><span class="rapm-cv-swatches">' + saved.join( '' ) +
				'<input type="color" id="rapm-cv-color" value="' + esc( it.c ) + '" aria-label="' + esc( T.otherColor ) + '"></span></p>';
		}
		if ( isWords( it ) ) { h += effectsPanel( it ); }
		if ( ! isWords( it ) ) { h += '<p><button type="button" class="button-link rapm-cv-remove">' + esc( 'img' === it.k ? T.removePic : T.remove ) + '</button></p>'; }
		selBox.innerHTML = h;
		if ( fontOpen && isWords( it ) ) { loadGoogleList().then( renderFontList ); }
	}

	// 1.51.0: shadow and outline for the picked words (all of them).
	function effectsPanel( it ) {
		var seg = function ( attr, now, opts ) {
			return opts.map( function ( o ) {
				return '<button type="button" class="button button-small" ' + attr + '="' + o[ 0 ] + '" aria-pressed="' + ( ( now || 0 ) === o[ 0 ] ) + '">' + esc( o[ 1 ] ) + '</button>';
			} ).join( ' ' );
		};
		var h = '<div class="rapm-cv-fx"><p class="rapm-cv-swlabel">' + esc( T.effects ) + '</p>' +
			'<p class="rapm-cv-fxrow"><span>' + esc( T.shadow ) + '</span><span>' + seg( 'data-sh', it.sh, [ [ 0, T.none ], [ 1, T.soft ], [ 2, T.strong ] ] ) + '</span></p>' +
			'<p class="rapm-cv-fxrow"><span>' + esc( T.outline ) + '</span><span>' + seg( 'data-ol', it.ol, [ [ 0, T.none ], [ 1, T.thin ], [ 2, T.thick ] ] ) + '</span></p>';
		if ( it.ol ) {
			var oc = it.oc || '#1d3557';
			var sw = function ( c, cg, name ) {
				return '<button type="button" class="rapm-cv-sw" data-oc="' + esc( c ) + '"' + ( cg ? ' data-ocg="' + esc( cg ) + '"' : '' ) + ' style="background:' + esc( c ) + '" aria-label="' + esc( name ) + '" title="' + esc( name ) + '" aria-pressed="' + ( c === oc && ( cg || '' ) === ( it.ocg || '' ) ) + '"></button>';
			};
			var plain = kit.colors.map( function ( c ) { return [ c.hex, c.name ]; } ).concat( [ [ '#000000', T.black ], [ '#ffffff', T.white ] ] );
			h += '<p class="rapm-cv-fxrow"><span>' + esc( T.outlineColor ) + '</span><span class="rapm-cv-swatches">' +
				kit.site.map( function ( c ) { return sw( c.hex, c.id, c.name ); } ).join( '' ) +
				plain.map( function ( c ) { return sw( c[ 0 ], '', c[ 1 ] ); } ).join( '' ) +
				'<input type="color" id="rapm-cv-ocolor" value="' + esc( oc ) + '" aria-label="' + esc( T.otherColor ) + '"></span></p>';
		}
		return h + '</div>';
	}

	function pickedWords() {
		return picked.map( function ( i ) { return state.items[ i ]; } ).filter( function ( it ) { return it && isWords( it ); } );
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
		// 1.51.1: the folded Background section shows the current choice.
		var dot = root.querySelector( '.rapm-cv-bgdot' );
		if ( dot ) {
			var usePic = !! ( state.pic && hasPic );
			dot.classList.toggle( 'is-pic', usePic );
			dot.style.background = usePic ? '' : ( state.bg || '#f3ead8' );
		}
	}

	/* ---- Dragging ------------------------------------------------------- */

	/*
	 * 1.51.0: Shift-click picks several; dragging one of the picked pieces
	 * moves them all (locked ones stay). The piece being dragged snaps
	 * when its edge or middle is within 1% of the square's middle or edges
	 * or another piece's, and a pink guide shows where; Alt/Option skips it.
	 */
	var SNAP = 1;
	var drag = null;
	board.addEventListener( 'pointerdown', function ( e ) {
		var n = e.target.closest( '.rapm-cv-item' );
		if ( ! n ) {
			if ( ! e.shiftKey ) { selected = -1; render(); }
			return;
		}
		e.preventDefault();
		var i = +n.getAttribute( 'data-i' );
		if ( e.shiftKey ) {
			togglePick( i );
			return;
		}
		if ( -1 === picked.indexOf( i ) ) { picked = [ i ]; }
		selected = i;
		render();
		stage.focus( { preventScroll: true } );
		status( '' );
		var it = state.items[ i ];
		if ( it.l ) { status( T.lockedMsg ); return; }
		var movers = movable().filter( function ( j ) { return ! state.items[ j ].l; } );
		var r = board.getBoundingClientRect();
		var xs = [ 0, 50, 100 ], ys = [ 0, 50, 100 ];
		state.items.forEach( function ( o, j ) {
			if ( o.h || -1 !== movers.indexOf( j ) ) { return; }
			var b = bounds( j );
			if ( b ) { xs.push( b.l, ( b.l + b.r ) / 2, b.r ); ys.push( b.t, ( b.t + b.b ) / 2, b.b ); }
		} );
		drag = { lead: i, r: r, sx: e.clientX, sy: e.clientY, xs: xs, ys: ys, from: movers.map( function ( j ) { return [ j, state.items[ j ].x, state.items[ j ].y ]; } ) };
		board.setPointerCapture( e.pointerId );
	} );

	function togglePick( i ) {
		var at = picked.indexOf( i );
		if ( -1 === at ) {
			picked.push( i );
			selected = i;
		} else {
			picked.splice( at, 1 );
			selected = picked.length ? picked[ picked.length - 1 ] : -1;
		}
		render();
	}

	function nearest( mine, targets ) {
		var best = null;
		mine.forEach( function ( p ) {
			targets.forEach( function ( t ) {
				var d = t - p;
				if ( Math.abs( d ) < SNAP && ( ! best || Math.abs( d ) < Math.abs( best.d ) ) ) { best = { d: d, t: t }; }
			} );
		} );
		return best;
	}

	function moveBy( dx, dy ) {
		drag.from.forEach( function ( f ) {
			var it = state.items[ f[ 0 ] ];
			it.x = Math.round( Math.max( 0, Math.min( 100, f[ 1 ] + dx ) ) * 10 ) / 10;
			it.y = Math.round( Math.max( 0, Math.min( 100, f[ 2 ] + dy ) ) * 10 ) / 10;
			var n = board.querySelector( '.rapm-cv-item[data-i="' + f[ 0 ] + '"]' );
			if ( n ) { place( n, it ); }
		} );
	}

	board.addEventListener( 'pointermove', function ( e ) {
		if ( ! drag ) { return; }
		var dx = ( e.clientX - drag.sx ) / drag.r.width * 100;
		var dy = ( e.clientY - drag.sy ) / drag.r.height * 100;
		moveBy( dx, dy );
		gv.hidden = gh.hidden = true;
		if ( e.altKey ) { return; }
		var b = bounds( drag.lead );
		if ( ! b ) { return; }
		var bx = nearest( [ b.l, ( b.l + b.r ) / 2, b.r ], drag.xs );
		var by = nearest( [ b.t, ( b.t + b.b ) / 2, b.b ], drag.ys );
		if ( bx || by ) { moveBy( dx + ( bx ? bx.d : 0 ), dy + ( by ? by.d : 0 ) ); }
		if ( bx ) { gv.style.left = bx.t + '%'; gv.hidden = false; }
		if ( by ) { gh.style.top = by.t + '%'; gh.hidden = false; }
	} );
	function stop() {
		if ( ! drag ) { return; }
		drag = null;
		gv.hidden = gh.hidden = true;
		render();
		save();
	}
	board.addEventListener( 'pointerup', stop );
	board.addEventListener( 'pointercancel', stop );

	stage.addEventListener( 'keydown', function ( e ) {
		if ( ! picked.length || ! /^Arrow/.test( e.key ) || e.ctrlKey || e.metaKey ) { return; }
		e.preventDefault();
		var list = movable().filter( function ( i ) { return ! state.items[ i ].l; } );
		if ( ! list.length ) { status( T.lockedMsg ); return; }
		var d = e.shiftKey ? 5 : 1;
		list.forEach( function ( i ) {
			var it = state.items[ i ];
			if ( 'ArrowLeft' === e.key ) { it.x = Math.max( 0, it.x - d ); }
			if ( 'ArrowRight' === e.key ) { it.x = Math.min( 100, it.x + d ); }
			if ( 'ArrowUp' === e.key ) { it.y = Math.max( 0, it.y - d ); }
			if ( 'ArrowDown' === e.key ) { it.y = Math.min( 100, it.y + d ); }
		} );
		render();
		save( 'nudge' );
	} );

	// Undo/Redo and front/back from the keyboard, unless typing in a box
	// (which keeps the browser's own undo for the words).
	document.addEventListener( 'keydown', function ( e ) {
		if ( ! ( e.ctrlKey || e.metaKey ) || e.altKey || null === root.offsetParent ) { return; }
		var t = e.target;
		if ( t && ( /^(INPUT|TEXTAREA|SELECT)$/.test( t.tagName ) || t.isContentEditable ) ) { return; }
		var k = e.key.toLowerCase();
		if ( 'z' === k && ! e.shiftKey ) { e.preventDefault(); undo(); return; }
		if ( ( 'z' === k && e.shiftKey ) || 'y' === k ) { e.preventDefault(); redo(); return; }
		if ( ( ']' === e.key || '[' === e.key ) && selected >= 0 && root.contains( t ) ) {
			e.preventDefault();
			moveLayer( selected, ']' === e.key ? 1 : -1 );
		}
	} );

	/* ---- Icons ---------------------------------------------------------- */

	function loadList() {
		if ( list ) { return Promise.resolve( list ); }
		return fetch( root.getAttribute( 'data-icons' ) ).then( function ( r ) { return r.json(); } ).then( function ( j ) {
			list = j.icons;
			return list;
		} );
	}

	/* Font Awesome (1.45.0): Elementor's own copy (Font Awesome Free 5.15.3,
	   assets/lib/font-awesome/json), so nothing extra is bundled. Each file
	   is { icons: { name: [ width, height, ligatures, unicode, path ] } }. */
	function loadFa() {
		if ( faList ) { return Promise.resolve( faList ); }
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

	function showFa( q ) {
		q = ( q || '' ).trim().toLowerCase();
		var found;
		if ( ! q ) {
			found = FA_STARTERS.map( function ( n ) {
				for ( var i = 0; i < faList.length; i++ ) { if ( faList[ i ][ 0 ] === n ) { return i; } }
				return -1;
			} ).filter( function ( i ) { return i >= 0; } );
		} else {
			found = [];
			var extra = [];
			Object.keys( FA_WORDS ).forEach( function ( w ) {
				if ( 0 === w.indexOf( q ) ) { extra = extra.concat( FA_WORDS[ w ].split( ' ' ) ); }
			} );
			for ( var i = 0; i < faList.length && found.length < 60; i++ ) {
				if ( -1 !== extra.indexOf( faList[ i ][ 0 ] ) ) { found.push( i ); }
			}
			for ( var j = 0; j < faList.length && found.length < 60; j++ ) {
				if ( -1 === found.indexOf( j ) && faList[ j ][ 0 ].replace( /-/g, ' ' ).indexOf( q ) !== -1 ) { found.push( j ); }
			}
		}
		grid.innerHTML = found.length ? found.map( function ( i ) {
			var f = faList[ i ];
			var name = f[ 0 ].replace( /-/g, ' ' ) + ( 'far' === f[ 1 ] ? ' (outline)' : '' );
			return '<button type="button" class="rapm-cv-ic" data-fa="' + i + '" title="' + esc( name ) + '">' + svg( f[ 4 ], '0 0 ' + f[ 2 ] + ' ' + f[ 3 ] ) + '<span>' + esc( name ) + '</span></button>';
		} ).join( '' ) : '<p class="description">' + esc( T.noIcons ) + '</p>';
	}

	function addFa( i ) {
		var f = faList[ i ];
		state.items.push( { k: 'icon', f: f[ 1 ], n: f[ 0 ], d: f[ 4 ], v: '0 0 ' + f[ 2 ] + ' ' + f[ 3 ], x: 50, y: 50, s: 10, c: '#1d3557' } );
		selected = state.items.length - 1;
		render();
		save();
	}

	/* Your pictures (1.45.0): a logo or badge from the Media Library. */
	var frame = null;
	function pickPicture() {
		if ( ! window.wp || ! window.wp.media ) { return; }
		if ( ! frame ) {
			frame = window.wp.media( { title: T.pickPicture, button: { text: T.usePicture }, library: { type: 'image' }, multiple: false } );
			frame.on( 'select', function () {
				var a = frame.state().get( 'selection' ).first().toJSON();
				var size = a.sizes && ( a.sizes.medium_large || a.sizes.large || a.sizes.full );
				state.items.push( { k: 'img', a: a.id, u: size ? size.url : a.url, t: a.title || '', x: 50, y: 50, s: 30 } );
				selected = state.items.length - 1;
				render();
				save();
			} );
		}
		frame.open();
	}

	function showTab( which ) {
		tab = which;
		Array.prototype.forEach.call( root.querySelectorAll( '[data-tab]' ), function ( b ) { b.setAttribute( 'aria-pressed', String( b.getAttribute( 'data-tab' ) === which ) ); } );
		root.querySelector( '.rapm-cv-iconpick' ).hidden = 'media' === which;
		root.querySelector( '.rapm-cv-media' ).hidden = 'media' !== which;
		root.querySelector( '.rapm-cv-src-ms' ).hidden = 'ms' !== which;
		root.querySelector( '.rapm-cv-src-fa' ).hidden = 'fa' !== which;
		if ( 'fa' === which ) {
			grid.innerHTML = '';
			loadFa().then( function () { showFa( search.value ); } );
		} else if ( 'ms' === which ) {
			loadList().then( function () { showIcons( search.value ); } );
		}
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

	function refreshSearch() {
		if ( 'fa' === tab ) {
			loadFa().then( function () { showFa( search.value ); } );
		} else {
			loadList().then( function () { showIcons( search.value ); } );
		}
	}

	var searchTimer = 0;
	search.addEventListener( 'focus', refreshSearch );
	search.addEventListener( 'input', function () {
		clearTimeout( searchTimer );
		searchTimer = setTimeout( refreshSearch, 150 );
	} );

	/* ---- Templates and logos (1.50.0) ------------------------------------- */

	function miniBoard( tpl ) {
		var wrap = document.createElement( 'div' );
		wrap.className = 'rapm-pb-item is-design rapm-cv-mini';
		wrap.style.background = tpl.canvas.bg || '#f3ead8';
		var b = document.createElement( 'div' );
		b.className = 'rapm-pb-board';
		( tpl.canvas.items || [] ).forEach( function ( it ) {
			if ( it.h ) { return; }
			var n = document.createElement( 'div' );
			n.className = 'rapm-pb-t is-' + it.k;
			place( n, it );
			if ( 'icon' === it.k ) { n.innerHTML = svg( it.d, it.v ); }
			else if ( 'img' === it.k ) { n.innerHTML = '<img src="' + esc( it.u || '' ) + '" alt="">'; }
			else { n.textContent = ( tpl.texts || {} )[ { headline: 'headline', sub: 'sub', btn: 'btn' }[ it.k ] ] || ''; }
			b.appendChild( n );
		} );
		wrap.appendChild( b );
		return wrap;
	}

	function toggleTemplates() {
		var box = root.querySelector( '#rapm-cv-tpls' );
		var btn = root.querySelector( '#rapm-cv-tpltoggle' );
		box.hidden = ! box.hidden;
		btn.setAttribute( 'aria-expanded', String( ! box.hidden ) );
		if ( box.hidden ) { return; }
		box.innerHTML = '';
		kit.templates.forEach( function ( tpl, i ) {
			var card = document.createElement( 'div' );
			card.className = 'rapm-cv-tpl';
			card.appendChild( miniBoard( tpl ) );
			var cap = document.createElement( 'div' );
			cap.className = 'rapm-cv-tplcap';
			cap.innerHTML = '<span><b>' + esc( tpl.name ) + '</b><br><small>' + esc( tpl.starter ? T.starter : T.thisStore ) + '</small></span><button type="button" class="button button-small" data-tpl="' + i + '">' + esc( T.useTpl ) + '</button>';
			card.appendChild( cap );
			box.appendChild( card );
		} );
		var link = document.createElement( 'p' );
		link.className = 'rapm-cv-tplmore';
		link.innerHTML = '<a href="' + esc( root.getAttribute( 'data-kit-url' ) || '#' ) + '">' + esc( T.manageKit ) + '</a>';
		box.appendChild( link );
	}

	// Asks on the card itself (no browser pop-up) before replacing the design.
	function askTemplate( b ) {
		var i = b.getAttribute( 'data-tpl' );
		b.closest( '.rapm-cv-tplcap' ).innerHTML = '<span class="rapm-cv-tplask">' + esc( T.tplAsk ) + '</span>' +
			'<span><button type="button" class="button button-small button-primary" data-tplyes="' + i + '">' + esc( T.tplYes ) + '</button> ' +
			'<button type="button" class="button-link" data-tplno="1">' + esc( T.cancel ) + '</button></span>';
	}

	function useTemplate( i ) {
		var tpl = kit.templates[ i ];
		if ( ! tpl ) { return; }
		pendingWords = currentWords(); // so Undo brings the words back too
		state = JSON.parse( JSON.stringify( tpl.canvas ) );
		state.bg = state.bg || '';
		var fields = { rapm_headline: 'headline', rapm_subhead: 'sub', rapm_cta_text: 'btn' };
		Object.keys( fields ).forEach( function ( id ) {
			var el = document.getElementById( id );
			if ( el && tpl.texts && tpl.texts[ fields[ id ] ] ) { el.value = tpl.texts[ fields[ id ] ]; }
		} );
		selected = -1;
		toggleTemplates();
		render();
		save();
	}

	function showSaveForm( on ) {
		root.querySelector( '#rapm-cv-tplform' ).hidden = ! on;
		root.querySelector( '#rapm-cv-savetpl' ).hidden = on;
		root.querySelector( '#rapm-cv-tplmsg' ).textContent = '';
		if ( on ) { root.querySelector( '#rapm-cv-tplname' ).focus(); }
	}

	function saveTemplate() {
		var name = root.querySelector( '#rapm-cv-tplname' ).value;
		var msg = root.querySelector( '#rapm-cv-tplmsg' );
		if ( ! name.trim() ) { root.querySelector( '#rapm-cv-tplname' ).focus(); return; }
		var fd = new FormData();
		fd.append( 'action', 'rapm_brand_kit_add_template' );
		fd.append( 'nonce', root.getAttribute( 'data-kit-nonce' ) );
		fd.append( 'name', name.trim() );
		fd.append( 'canvas', JSON.stringify( state ) );
		fd.append( 'texts', JSON.stringify( { headline: words( 'headline' ), sub: words( 'sub' ), btn: words( 'btn' ) } ) );
		fetch( root.getAttribute( 'data-ajax' ), { method: 'POST', body: fd, credentials: 'same-origin' } ).then( function ( r ) { return r.json(); } ).then( function ( res ) {
			if ( ! res || ! res.success ) { throw new Error( res && res.data && res.data.message ? res.data.message : T.tplFailed ); }
			kit.templates.unshift( res.data.template );
			root.querySelector( '#rapm-cv-tplname' ).value = '';
			showSaveForm( false );
			msg.textContent = T.tplSaved;
			if ( ! root.querySelector( '#rapm-cv-tpls' ).hidden ) { toggleTemplates(); toggleTemplates(); }
		} ).catch( function ( err ) { msg.textContent = err.message || T.tplFailed; } );
	}

	function addLogo( i ) {
		var l = kit.logos[ i ];
		if ( ! l ) { return; }
		state.items.push( { k: 'img', a: l.a, u: l.u, t: l.t, x: 50, y: 50, s: 30 } );
		selected = state.items.length - 1;
		render();
		save();
	}

	function showLogos() {
		var box = root.querySelector( '.rapm-cv-media' );
		if ( ! box || ! kit.logos.length || box.querySelector( '.rapm-cv-logos' ) ) { return; }
		var wrap = document.createElement( 'div' );
		wrap.className = 'rapm-cv-logos';
		wrap.innerHTML = '<p class="rapm-cv-swlabel">' + esc( T.yourLogos ) + '</p>' + kit.logos.map( function ( l, i ) {
			return '<button type="button" class="rapm-cv-ic" data-logo="' + i + '" title="' + esc( l.t ) + '"><img src="' + esc( l.u ) + '" alt=""><span>' + esc( l.t ) + '</span></button>';
		} ).join( '' );
		box.insertBefore( wrap, box.firstChild );
	}

	/* ---- Clicks and changes -------------------------------------------- */

	root.addEventListener( 'click', function ( e ) {
		var b = e.target.closest( 'button' );
		if ( ! b || ! root.contains( b ) ) { return; }
		if ( b.hasAttribute( 'data-icon' ) ) { addIcon( b.getAttribute( 'data-icon' ), b ); return; }
		if ( b.hasAttribute( 'data-fa' ) && faList ) { addFa( +b.getAttribute( 'data-fa' ) ); return; }
		if ( b.hasAttribute( 'data-tab' ) ) { showTab( b.getAttribute( 'data-tab' ) ); return; }
		if ( b.classList.contains( 'rapm-cv-pick' ) ) { pickPicture(); return; }
		if ( b.classList.contains( 'rapm-cv-fontbtn' ) ) { fontOpen = ! fontOpen; renderSel(); return; }
		if ( b.hasAttribute( 'data-ff' ) || b.hasAttribute( 'data-fg' ) || b.hasAttribute( 'data-fdef' ) ) {
			var fi = state.items[ selected ];
			delete fi.ff;
			delete fi.fg;
			if ( b.hasAttribute( 'data-ff' ) ) { fi.ff = b.getAttribute( 'data-ff' ); }
			if ( b.hasAttribute( 'data-fg' ) ) { fi.fg = b.getAttribute( 'data-fg' ); }
			render();
			save();
			return;
		}
		if ( b.classList.contains( 'rapm-cv-layer' ) ) {
			if ( e.shiftKey ) { togglePick( +b.getAttribute( 'data-i' ) ); return; }
			selected = +b.getAttribute( 'data-i' );
			picked = [ selected ];
			render();
			return;
		}
		if ( b.hasAttribute( 'data-fwd' ) ) { moveLayer( +b.getAttribute( 'data-fwd' ), 1 ); return; }
		if ( b.hasAttribute( 'data-back' ) ) { moveLayer( +b.getAttribute( 'data-back' ), -1 ); return; }
		if ( b.hasAttribute( 'data-hide' ) ) {
			var hi = state.items[ +b.getAttribute( 'data-hide' ) ];
			if ( hi.h ) { delete hi.h; } else { hi.h = true; }
			render();
			save();
			status( hi.h ? T.hiddenMsg : '' );
			return;
		}
		if ( b.hasAttribute( 'data-lock' ) ) {
			var li = state.items[ +b.getAttribute( 'data-lock' ) ];
			if ( li.l ) { delete li.l; } else { li.l = true; }
			render();
			save();
			return;
		}
		if ( b.hasAttribute( 'data-al' ) ) { align( b.getAttribute( 'data-al' ) ); return; }
		if ( b.hasAttribute( 'data-dist' ) ) { distribute( b.getAttribute( 'data-dist' ) ); return; }
		if ( 'rapm-cv-undo' === b.id ) { undo(); return; }
		if ( 'rapm-cv-redo' === b.id ) { redo(); return; }
		if ( b.hasAttribute( 'data-sh' ) || b.hasAttribute( 'data-ol' ) ) {
			var key = b.hasAttribute( 'data-sh' ) ? 'sh' : 'ol';
			var v = +b.getAttribute( 'data-' + key );
			pickedWords().forEach( function ( w ) {
				if ( v ) { w[ key ] = v; } else { delete w[ key ]; }
				if ( 'ol' === key && ! v ) { delete w.oc; delete w.ocg; }
				if ( 'ol' === key && v && ! w.oc ) { w.oc = '#1d3557'; }
			} );
			render();
			save();
			return;
		}
		if ( b.hasAttribute( 'data-oc' ) ) {
			pickedWords().forEach( function ( w ) {
				w.oc = b.getAttribute( 'data-oc' );
				if ( b.hasAttribute( 'data-ocg' ) ) { w.ocg = b.getAttribute( 'data-ocg' ); } else { delete w.ocg; }
			} );
			render();
			save();
			return;
		}
		if ( b.hasAttribute( 'data-color' ) ) {
			var ci = state.items[ selected ];
			ci.c = b.getAttribute( 'data-color' );
			// A website color stays linked to Elementor; a saved one is a plain value.
			if ( b.hasAttribute( 'data-cg' ) ) { ci.cg = b.getAttribute( 'data-cg' ); } else { delete ci.cg; }
			render();
			save();
			return;
		}
		if ( b.hasAttribute( 'data-tpl' ) ) { askTemplate( b ); return; }
		if ( b.hasAttribute( 'data-tplyes' ) ) { useTemplate( +b.getAttribute( 'data-tplyes' ) ); return; }
		if ( b.hasAttribute( 'data-tplno' ) ) { toggleTemplates(); toggleTemplates(); return; }
		if ( 'rapm-cv-tpltoggle' === b.id ) { toggleTemplates(); return; }
		if ( 'rapm-cv-savetpl' === b.id ) { showSaveForm( true ); return; }
		if ( 'rapm-cv-tplcancel' === b.id ) { showSaveForm( false ); return; }
		if ( 'rapm-cv-tplsave' === b.id ) { saveTemplate(); return; }
		if ( b.hasAttribute( 'data-logo' ) ) { addLogo( +b.getAttribute( 'data-logo' ) ); return; }
		if ( b.hasAttribute( 'data-bg' ) ) { state.bg = b.getAttribute( 'data-bg' ); state.pic = 0; render(); save(); return; }
		if ( b.hasAttribute( 'data-bgmode' ) ) { state.pic = 'pic' === b.getAttribute( 'data-bgmode' ) ? 1 : 0; render(); save(); return; }
		if ( b.classList.contains( 'rapm-cv-remove' ) ) { state.items.splice( selected, 1 ); selected = -1; render(); save(); }
	} );

	var fontTimer = 0;
	root.addEventListener( 'input', function ( e ) {
		var it = state.items[ selected ];
		if ( e.target.classList.contains( 'rapm-cv-fontsearch' ) ) {
			fontQuery = e.target.value;
			clearTimeout( fontTimer );
			fontTimer = setTimeout( function () { loadGoogleList().then( renderFontList ); }, 150 );
			return;
		}
		if ( 'rapm-cv-size' === e.target.id && it ) {
			it.s = +e.target.value;
			var n = board.querySelector( '.rapm-cv-item[data-i="' + selected + '"]' );
			if ( n ) { place( n, it ); }
			field.value = JSON.stringify( state );
		}
		if ( 'rapm-cv-color' === e.target.id && it ) {
			it.c = e.target.value;
			delete it.cg;
			var m = board.querySelector( '.rapm-cv-item[data-i="' + selected + '"]' );
			if ( m ) { place( m, it ); }
			field.value = JSON.stringify( state );
		}
		if ( 'rapm-cv-ocolor' === e.target.id ) {
			pickedWords().forEach( function ( w ) {
				w.oc = e.target.value;
				delete w.ocg;
				var o = board.querySelector( '.rapm-cv-item[data-i="' + state.items.indexOf( w ) + '"]' );
				if ( o ) { place( o, w ); }
			} );
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
		var si = state.items[ selected ];
		if ( 'rapm-cv-style' === e.target.id && si && '' !== e.target.value ) {
			// A style is copied onto the piece: later changes to the style don't touch it.
			var st = kit.styles[ +e.target.value ];
			delete si.ff;
			delete si.fg;
			delete si.cg;
			if ( st.ff ) { si.ff = st.ff; }
			if ( st.fg ) { si.fg = st.fg; }
			si.fw = st.fw;
			si.s = Math.max( LIMITS[ si.k ][ 0 ], Math.min( LIMITS[ si.k ][ 1 ], st.s ) );
			si.c = st.c;
			si.tt = !! st.tt;
			render();
			save();
			return;
		}
		if ( 'rapm-cv-caps' === e.target.id && si ) {
			si.tt = e.target.checked;
			render();
			save();
			return;
		}
		if ( /^rapm-cv-(size|color|bgcolor|ocolor)$/.test( e.target.id ) ) { render(); save( e.target.id ); }
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
	showLogos();
	render();
	save();
} )();
