<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * An icon with the words on the picture (1.63.0, from the mockup Phil
 * approved on 2026-10-26, https://claude.ai/artifact/78Xsrqmi9RHQUxsjvddhnr,
 * board 2), the third release of his rule: words on the picture can have
 * Google icons; words on the page can't (see RAPM_Line_Fonts).
 *
 * One icon per promotion, from the same three places as the promotion box
 * canvas (1.44.0, 1.45.0): Google's Material Symbols, Font Awesome Free
 * (Elementor's copy) or a picture from the Media Library. It goes in a
 * fixed spot so it stays tidy on phones:
 *   Slider, Feature banner, Coupon row: before the headline, or in the button.
 *   Reveal panels: above the big word.
 *   Shop the Look: in the button, when the button is on the photo.
 *
 * Stored in _rapm_icon: where ('head', 'btn' or 'above'), f ('ms', 'fas',
 * 'far', 'fab' or 'img'), n (name), d (its drawing, the SVG path), v (its
 * drawing area) or a (the picture's ID). The drawing is saved with the
 * promotion, so pages never load icons from Google or anywhere else. Its
 * size and color follow the words it sits with.
 */
class RAPM_Line_Icon {

	const META = '_rapm_icon';

	/** Where an icon can go, for each kind that can have one. */
	public static function wheres( $kind ) {
		$both = array(
			'head' => __( 'Before the headline', 'rapm' ),
			'btn'  => __( 'In the button', 'rapm' ),
		);
		$map  = array(
			'hero'        => $both,
			'fold_banner' => $both,
			'coupon'      => $both,
			'reveal'      => array( 'above' => __( 'Above the big word', 'rapm' ) ),
			'look'        => array( 'btn' => __( 'In the button on the photo', 'rapm' ) ),
		);
		return isset( $map[ $kind ] ) ? $map[ $kind ] : array();
	}

	public static function applies( $kind ) {
		return (bool) self::wheres( $kind );
	}

	/** A clean icon, or array() for none. The same checks as the box canvas's icons. */
	public static function clean( $raw, $kind ) {
		if ( ! is_array( $raw ) || empty( $raw['w'] ) || ! isset( self::wheres( $kind )[ $raw['w'] ] ) ) {
			return array();
		}
		$f = isset( $raw['f'] ) && in_array( $raw['f'], array( 'ms', 'fas', 'far', 'fab', 'img' ), true ) ? $raw['f'] : '';
		if ( 'img' === $f ) {
			$a = isset( $raw['a'] ) ? absint( $raw['a'] ) : 0;
			return $a && wp_attachment_is_image( $a ) ? array( 'w' => $raw['w'], 'f' => 'img', 'a' => $a ) : array();
		}
		$n = isset( $raw['n'] ) ? preg_replace( '/[^a-z0-9_-]/', '', strtolower( (string) $raw['n'] ) ) : '';
		$d = isset( $raw['d'] ) ? (string) $raw['d'] : '';
		if ( ! $f || '' === $n || '' === $d || strlen( $d ) > 20000 || ! preg_match( '/^[MmLlHhVvCcSsQqTtAaZz0-9.,\-\s]+$/', $d ) ) {
			return array();
		}
		$v = RAPM_Split::MS_BOX;
		if ( 'ms' !== $f && isset( $raw['v'] ) && preg_match( '/^0 0 (\d{1,4}) (\d{1,4})$/', (string) $raw['v'], $vb ) && (int) $vb[1] > 0 && (int) $vb[2] > 0 ) {
			$v = '0 0 ' . (int) $vb[1] . ' ' . (int) $vb[2];
		}
		return array( 'w' => $raw['w'], 'f' => $f, 'n' => $n, 'd' => $d, 'v' => $v );
	}

	public static function get( $asset_id ) {
		$icon = get_post_meta( $asset_id, self::META, true );
		return is_array( $icon ) && ! empty( $icon['w'] ) ? $icon : array();
	}

	/** The icon's markup when it goes at $where, or ''. Sized by the words around it (1em). */
	public static function html( $icon, $where ) {
		if ( ! $icon || $where !== $icon['w'] ) {
			return '';
		}
		$above = 'above' === $where;
		$box   = $above
			? 'display:block;width:0.9em;height:0.9em;margin:0 auto 0.2em;'
			: 'display:inline-block;width:1em;height:1em;vertical-align:-0.14em;margin-right:0.35em;';
		if ( 'img' === $icon['f'] ) {
			$src = wp_get_attachment_image_url( $icon['a'], 'thumbnail' );
			return $src ? '<img class="rapm-li is-img" src="' . esc_url( $src ) . '" alt="" style="' . esc_attr( $box . 'object-fit:contain;' ) . '" loading="lazy" decoding="async" />' : '';
		}
		// Font Awesome Free's icons are CC BY 4.0; its own SVGs carry this credit, so ours do too.
		$credit = 'ms' === $icon['f'] ? '' : '<!-- Font Awesome Free by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free -->';
		return '<span class="rapm-li" aria-hidden="true" style="' . esc_attr( $box ) . '"><svg viewBox="' . esc_attr( $icon['v'] ) . '" focusable="false" style="display:block;width:100%;height:100%;fill:currentColor;">' . $credit . '<path d="' . esc_attr( $icon['d'] ) . '"/></svg></span>';
	}

	/** Saves the form's choice (rapm_icon, JSON from rapm-line-icon.js). */
	public static function save( $asset_id, $kind ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- called from handle_save(), which checks the nonce.
		if ( ! isset( $_POST['rapm_icon'] ) ) {
			return;
		}
		$raw  = json_decode( wp_unslash( (string) $_POST['rapm_icon'] ), true ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- checked piece by piece in clean().
		// phpcs:enable
		$icon = self::clean( $raw, $kind );
		if ( $icon ) {
			update_post_meta( $asset_id, self::META, $icon );
		} else {
			delete_post_meta( $asset_id, self::META );
		}
	}
}
