<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Google Fonts for the promotion box canvas (1.47.0, from the mockup Phil
 * approved on 2026-10-16: "brand fonts first, then search"; "Only the fonts
 * used on live promotions load on the website, from the client's own
 * server").
 *
 * The family list is bundled (assets/data/google-fonts.json, Google's
 * open-source families with a Latin character set, from
 * fonts.google.com/metadata/fonts). When a promotion using a family is
 * saved, its Latin and Latin-extended woff2 files are copied once into
 * uploads/rapm-fonts/<family>/ with a small stylesheet, and the website
 * links that local stylesheet. Visitors' browsers never contact Google.
 * If the copy fails, the words fall back to the site's own font.
 */
class RAPM_Fonts {

	const OPTION = 'rapm_local_fonts';
	const DIR    = 'rapm-fonts';

	/** Weights copied for every family (those it has): enough for words, smaller line and button. */
	const WEIGHTS = array( 400, 500, 600, 700, 800, 900 );

	/** family => array( category, weights[] ). */
	public static function families() {
		static $list = null;
		if ( null === $list ) {
			$list = array();
			$file = RAPM_DIR . 'assets/data/google-fonts.json';
			$data = file_exists( $file ) ? json_decode( (string) file_get_contents( $file ), true ) : null; // phpcs:ignore WordPress.WP.AlternativeFunctions
			if ( is_array( $data ) && ! empty( $data['fonts'] ) ) {
				foreach ( $data['fonts'] as $f ) {
					$list[ $f[0] ] = array( $f[1], array_map( 'intval', explode( ',', $f[2] ) ) );
				}
			}
		}
		return $list;
	}

	/** The family's exact name if it's in the list, or ''. */
	public static function canonical( $family ) {
		$family = trim( (string) $family );
		$all    = self::families();
		return isset( $all[ $family ] ) ? $family : '';
	}

	public static function slug( $family ) {
		return sanitize_title( $family );
	}

	/** font-family value with a fallback that matches the family's style. */
	public static function css_family( $family ) {
		$all      = self::families();
		$category = isset( $all[ $family ] ) ? $all[ $family ][0] : '';
		$fallback = array(
			'Serif'       => 'Georgia, "Times New Roman", serif',
			'Handwriting' => 'cursive',
			'Monospace'   => 'ui-monospace, Menlo, monospace',
		);
		return '"' . str_replace( '"', '', $family ) . '", ' . ( isset( $fallback[ $category ] ) ? $fallback[ $category ] : 'system-ui, -apple-system, "Segoe UI", sans-serif' );
	}

	/** The local stylesheet's address, or '' when the family hasn't been copied. */
	public static function local_url( $family ) {
		$saved = get_option( self::OPTION, array() );
		$slug  = self::slug( $family );
		if ( empty( $saved[ $slug ]['css'] ) ) {
			return '';
		}
		$up = wp_upload_dir();
		return trailingslashit( $up['baseurl'] ) . self::DIR . '/' . $slug . '/' . $saved[ $slug ]['css'];
	}

	/**
	 * Copies a family to this site, once. Returns true when its local
	 * stylesheet is ready. Called when a promotion that uses it is saved.
	 */
	public static function ensure( $family ) {
		$family = self::canonical( $family );
		if ( '' === $family ) {
			return false;
		}
		$slug  = self::slug( $family );
		$saved = get_option( self::OPTION, array() );
		$up    = wp_upload_dir();
		$dir   = trailingslashit( $up['basedir'] ) . self::DIR . '/' . $slug . '/';
		if ( ! empty( $saved[ $slug ]['css'] ) && file_exists( $dir . $saved[ $slug ]['css'] ) ) {
			return true;
		}

		$all     = self::families();
		$weights = array_values( array_intersect( self::WEIGHTS, $all[ $family ][1] ) );
		if ( ! $weights ) {
			$weights = array( $all[ $family ][1][0] );
		}
		$url = 'https://fonts.googleapis.com/css2?family=' . rawurlencode( $family ) . ':wght@' . implode( ';', $weights ) . '&display=swap';
		// A current browser's user agent, so Google answers with woff2 files.
		$res = wp_remote_get( $url, array( 'timeout' => 15, 'user-agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36' ) );
		if ( is_wp_error( $res ) || 200 !== (int) wp_remote_retrieve_response_code( $res ) ) {
			return false;
		}
		$css = (string) wp_remote_retrieve_body( $res );

		// Keep the Latin and Latin-extended blocks: what English (and most
		// Western) promotions use. Each block follows a /* subset */ comment.
		if ( ! preg_match_all( '#/\*\s*(latin-ext|latin)\s*\*/\s*(@font-face\s*\{[^}]*\})#', $css, $blocks, PREG_SET_ORDER ) ) {
			return false;
		}
		if ( ! wp_mkdir_p( $dir ) ) {
			return false;
		}
		$out = '/* ' . $family . ', from Google Fonts (open source). Copied to this site by RA Promo Manager so visitors never load it from Google. */' . "\n";
		foreach ( $blocks as $block ) {
			if ( ! preg_match( '#url\((https://fonts\.gstatic\.com/[^)\s]+\.woff2)\)#', $block[2], $m ) ) {
				continue;
			}
			$file = sanitize_file_name( basename( wp_parse_url( $m[1], PHP_URL_PATH ) ) );
			if ( ! file_exists( $dir . $file ) ) {
				$font = wp_remote_get( $m[1], array( 'timeout' => 20 ) );
				$body = is_wp_error( $font ) ? '' : (string) wp_remote_retrieve_body( $font );
				// A real woff2 starts with "wOF2"; anything else, or one over 2MB, is skipped.
				if ( 200 !== (int) wp_remote_retrieve_response_code( $font ) || 'wOF2' !== substr( $body, 0, 4 ) || strlen( $body ) > 2 * MB_IN_BYTES ) {
					continue;
				}
				file_put_contents( $dir . $file, $body ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			}
			$out .= '/* ' . $block[1] . ' */' . "\n" . str_replace( $m[1], $file, $block[2] ) . "\n";
		}
		if ( false === strpos( $out, '@font-face' ) ) {
			return false;
		}
		$css_file = $slug . '.css';
		file_put_contents( $dir . $css_file, $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$saved[ $slug ] = array(
			'family' => $family,
			'css'    => $css_file,
			'copied' => time(),
		);
		update_option( self::OPTION, $saved, false );
		return true;
	}

	/** A family's <link>, printed once per page; '' when it isn't on this site. */
	public static function link_tag( $family ) {
		static $printed = array();
		$url = self::local_url( $family );
		if ( '' === $url || isset( $printed[ $url ] ) ) {
			return '';
		}
		$printed[ $url ] = true;
		return '<link rel="stylesheet" href="' . esc_url( $url ) . '" />'; // phpcs:ignore WordPress.WP.EnqueuedResources -- printed where the promotion renders, like the plugin's other late-loaded styles.
	}
}
