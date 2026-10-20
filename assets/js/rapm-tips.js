/**
 * (i) buttons (1.55.0, RAPM_Tip): open and close the help under a field.
 * Esc closes the open one and returns focus to its button.
 */
( function () {
	'use strict';
	document.addEventListener( 'click', function ( e ) {
		var b = e.target.closest ? e.target.closest( '.rapm-tip-btn' ) : null;
		if ( ! b ) { return; }
		e.preventDefault();
		var tip = document.getElementById( b.getAttribute( 'aria-controls' ) );
		if ( ! tip ) { return; }
		var open = 'true' !== b.getAttribute( 'aria-expanded' );
		b.setAttribute( 'aria-expanded', String( open ) );
		tip.hidden = ! open;
	} );
	document.addEventListener( 'keydown', function ( e ) {
		if ( 'Escape' !== e.key ) { return; }
		var tip = e.target.closest ? e.target.closest( '.rapm-tip' ) : null;
		var b = tip ? document.querySelector( '[aria-controls="' + tip.id + '"]' ) : ( e.target.classList && e.target.classList.contains( 'rapm-tip-btn' ) ? e.target : null );
		if ( ! b || 'true' !== b.getAttribute( 'aria-expanded' ) ) { return; }
		b.setAttribute( 'aria-expanded', 'false' );
		document.getElementById( b.getAttribute( 'aria-controls' ) ).hidden = true;
		b.focus();
	} );
}() );
