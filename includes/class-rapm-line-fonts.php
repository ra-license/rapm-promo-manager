<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A font for each line of words on the picture (1.62.0, from the mockup
 * Phil approved on 2026-10-26, https://claude.ai/artifact/78Xsrqmi9RHQUxsjvddhnr,
 * board 2). His rule: "Anything within the image section of the
 * promotional banner needs to have the option to select from the Google
 * Typefaces or icons etc. Anything that exists outside the image pane in
 * the body of the website needs to maintain the elementor fonts."
 *
 * So the kinds whose words sit on the picture (Slider, Feature banner,
 * Coupon row, Reveal panels, and Shop the Look when its words or button
 * are on the photo) pick a font per line: the website's Elementor fonts
 * or any Google Font. Quick links and Tile row put their words on the page
 * under the picture and keep the one Typeface menu of Elementor fonts. The
 * promotion box already picks a font per piece on its canvas (1.47.0).
 *
 * Stored in _rapm_fonts: line => '' (the website's default), 'el:<id>'
 * (an Elementor global font, followed live through its CSS variable) or
 * 'gf:<Family>' (a Google Font, copied to this site by RAPM_Fonts when
 * the promotion is saved). A promotion saved before 1.62.0 has no
 * _rapm_fonts, and its one Typeface (_rapm_text_font) is used for every line.
 */
class RAPM_Line_Fonts {

	const META  = '_rapm_fonts';
	const LINES = array( 'head', 'sub', 'btn' );
	const KINDS = array( 'hero', 'fold_banner', 'coupon', 'reveal', 'look' );

	public static function applies( $kind ) {
		return in_array( $kind, self::KINDS, true );
	}

	/** Each line's choice, with the pre-1.62.0 Typeface as the fallback. */
	public static function get( $asset_id ) {
		$saved = get_post_meta( $asset_id, self::META, true );
		$out   = array();
		if ( is_array( $saved ) ) {
			foreach ( self::LINES as $line ) {
				$out[ $line ] = isset( $saved[ $line ] ) ? self::shape( $saved[ $line ] ) : '';
			}
			return $out;
		}
		$old = (string) get_post_meta( $asset_id, '_rapm_text_font', true );
		foreach ( self::LINES as $line ) {
			$out[ $line ] = '' !== $old ? 'el:' . sanitize_key( $old ) : '';
		}
		return $out;
	}

	/** A stored choice in its expected shape, without the lookups clean() does on save (this runs on every page view). */
	private static function shape( $value ) {
		$value = (string) $value;
		if ( 0 === strpos( $value, 'el:' ) ) {
			return 'el:' . sanitize_key( substr( $value, 3 ) );
		}
		if ( 0 === strpos( $value, 'gf:' ) && preg_match( '/^[A-Za-z0-9 ]+$/', substr( $value, 3 ) ) ) {
			return $value;
		}
		return '';
	}

	/** A choice from the form as it may be stored, or '' (the website's default). */
	public static function clean( $value ) {
		$value = trim( (string) $value );
		if ( 0 === strpos( $value, 'el:' ) ) {
			$id    = sanitize_key( substr( $value, 3 ) );
			$fonts = RAPM_Elementor::global_fonts();
			return ( '' !== $id && ( ! $fonts || isset( $fonts[ $id ] ) ) ) ? 'el:' . $id : '';
		}
		if ( 0 === strpos( $value, 'gf:' ) ) {
			$family = RAPM_Fonts::canonical( substr( $value, 3 ) );
			return '' !== $family ? 'gf:' . $family : '';
		}
		return '';
	}

	/** The font-family declaration for one choice, or ''. */
	public static function css( $value ) {
		if ( 0 === strpos( $value, 'el:' ) ) {
			return RAPM_Elementor::font_family_css( substr( $value, 3 ) );
		}
		if ( 0 === strpos( $value, 'gf:' ) && '' !== RAPM_Fonts::local_url( substr( $value, 3 ) ) ) {
			return 'font-family:' . RAPM_Fonts::css_family( substr( $value, 3 ) ) . ';';
		}
		return '';
	}

	/** ' style="font-family:…"' for one line of a promotion, or ''. */
	public static function style_attr( $fonts, $line ) {
		$css = isset( $fonts[ $line ] ) ? self::css( $fonts[ $line ] ) : '';
		return '' === $css ? '' : ' style="' . esc_attr( $css ) . '"';
	}

	/** The local stylesheet links for the Google Fonts a promotion uses, each once per page. */
	public static function links( $fonts ) {
		$out = '';
		foreach ( array_unique( $fonts ) as $value ) {
			if ( 0 === strpos( $value, 'gf:' ) ) {
				$out .= RAPM_Fonts::link_tag( substr( $value, 3 ) );
			}
		}
		return $out;
	}

	/**
	 * Saves the form's choices (rapm_font_head, _sub, _btn) and copies any
	 * Google Font onto this site. The old one-for-all Typeface is cleared,
	 * since each line now says its own font.
	 */
	public static function save( $asset_id ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- called from handle_save(), which checks the nonce.
		if ( ! isset( $_POST['rapm_font_head'] ) ) {
			return;
		}
		$fonts = array();
		foreach ( self::LINES as $line ) {
			$fonts[ $line ] = isset( $_POST[ 'rapm_font_' . $line ] ) ? self::clean( wp_unslash( $_POST[ 'rapm_font_' . $line ] ) ) : '';
			if ( 0 === strpos( $fonts[ $line ], 'gf:' ) ) {
				RAPM_Fonts::ensure( substr( $fonts[ $line ], 3 ) );
			}
		}
		// phpcs:enable
		update_post_meta( $asset_id, self::META, $fonts );
		update_post_meta( $asset_id, '_rapm_text_font', '' );
	}

	/** What the form's pickers need: the site's fonts (id => [ title, family ]). */
	public static function site_fonts() {
		$titles = RAPM_Elementor::global_fonts();
		$names  = RAPM_Elementor::font_family_map();
		$out    = array();
		foreach ( $titles as $id => $title ) {
			$out[ $id ] = array( $title, isset( $names[ $id ] ) ? $names[ $id ] : '' );
		}
		return $out;
	}
}
