<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RAPM_Elementor {

	public static function register_category( $elements_manager ) {
		if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
			return;
		}
		$elements_manager->add_category(
			'rapm-promo',
			array(
				'title' => __( 'Promo Manager', 'rapm' ),
				'icon'  => 'fa fa-plug',
			)
		);
	}

	public static function register_widgets( $widgets_manager ) {
		if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
			return;
		}
		require_once RAPM_DIR . 'includes/elementor/class-widget-hero-carousel.php';
		$widgets_manager->register( new RAPM_Widget_Hero_Carousel() );
	}

	/**
	 * The site's own Elementor Global Fonts (Site Settings > Global Fonts —
	 * the 4 built-in slots plus any custom-named ones an editor added),
	 * keyed by their stable _id ('primary'/'secondary'/'text'/'accent' for
	 * the built-ins, an auto-generated id for custom ones) and valued by
	 * their human-readable title — exactly the name shown in Elementor's
	 * own editor, so an account manager recognizes it. Lets a promotion's
	 * text use the same typeface as the rest of the site instead of a
	 * fixed preset, and stay in sync automatically if that font is ever
	 * changed in Elementor (see RAPM_Hero_Carousel — the chosen id is
	 * rendered as a CSS var() reference, never a resolved font name).
	 *
	 * Empty on any site without Elementor active, or one where Elementor
	 * is active but no kit has ever been touched.
	 */
	public static function global_fonts() {
		if ( ! did_action( 'elementor/loaded' ) || ! class_exists( '\Elementor\Plugin' ) ) {
			return array();
		}
		$kits_manager = \Elementor\Plugin::$instance->kits_manager;
		if ( ! $kits_manager ) {
			return array();
		}
		$kit = $kits_manager->get_active_kit_for_frontend();
		if ( ! $kit ) {
			return array();
		}

		$entries = array_merge(
			(array) $kit->get_settings( 'system_typography' ),
			(array) $kit->get_settings( 'custom_typography' )
		);

		$fonts = array();
		foreach ( $entries as $entry ) {
			if ( ! empty( $entry['_id'] ) && ! empty( $entry['title'] ) ) {
				$fonts[ $entry['_id'] ] = $entry['title'];
			}
		}
		return $fonts;
	}

	/**
	 * The literal font-family name behind each global font id (e.g.
	 * 'primary' => 'Roboto'). Only for the Add/Edit Asset form's own Live
	 * Preview, which renders inside wp-admin — Elementor's
	 * --e-global-typography-*-font-family CSS variables are only ever
	 * declared on the real front end (scoped to body.elementor-kit-{id}),
	 * so they don't exist for a preview box inside wp-admin to read. The
	 * actual public-facing render (RAPM_Hero_Carousel etc.) always uses
	 * the var() reference from font_family_css() instead, never this, so
	 * it keeps auto-syncing with Elementor rather than freezing a name.
	 */
	public static function font_family_map() {
		if ( ! did_action( 'elementor/loaded' ) || ! class_exists( '\Elementor\Plugin' ) ) {
			return array();
		}
		$kits_manager = \Elementor\Plugin::$instance->kits_manager;
		if ( ! $kits_manager ) {
			return array();
		}
		$kit = $kits_manager->get_active_kit_for_frontend();
		if ( ! $kit ) {
			return array();
		}

		$entries = array_merge(
			(array) $kit->get_settings( 'system_typography' ),
			(array) $kit->get_settings( 'custom_typography' )
		);

		$map = array();
		foreach ( $entries as $entry ) {
			if ( ! empty( $entry['_id'] ) && ! empty( $entry['typography_font_family'] ) ) {
				$map[ $entry['_id'] ] = $entry['typography_font_family'];
			}
		}
		return $map;
	}

	/**
	 * A `font-family` CSS declaration referencing an Elementor global
	 * font's own live CSS variable — never a resolved/hardcoded font
	 * name, so it keeps tracking whatever that font is set to in
	 * Elementor even if it's changed later. Empty string if no font_id
	 * given (falls back to whatever the surrounding CSS already sets).
	 */
	public static function font_family_css( $font_id ) {
		if ( ! $font_id ) {
			return '';
		}
		return 'font-family:var(--e-global-typography-' . sanitize_html_class( $font_id ) . '-font-family, inherit);';
	}

	/**
	 * The site's own Elementor Global Colors (Site Settings > Global
	 * Colors — the 4 built-in slots plus any custom-named ones an editor
	 * added), keyed by _id and valued by their human-readable title.
	 * Same shape and same reasoning as global_fonts() above. Empty on any
	 * site without Elementor active, or one where no kit has been
	 * touched.
	 */
	public static function global_colors() {
		if ( ! did_action( 'elementor/loaded' ) || ! class_exists( '\Elementor\Plugin' ) ) {
			return array();
		}
		$kits_manager = \Elementor\Plugin::$instance->kits_manager;
		if ( ! $kits_manager ) {
			return array();
		}
		$kit = $kits_manager->get_active_kit_for_frontend();
		if ( ! $kit ) {
			return array();
		}

		$entries = array_merge(
			(array) $kit->get_settings( 'system_colors' ),
			(array) $kit->get_settings( 'custom_colors' )
		);

		$colors = array();
		foreach ( $entries as $entry ) {
			if ( ! empty( $entry['_id'] ) && ! empty( $entry['title'] ) ) {
				$colors[ $entry['_id'] ] = $entry['title'];
			}
		}
		return $colors;
	}

	/**
	 * The literal hex value behind each global color id — for admin-side
	 * previews only (Settings' "detected" hint), same reasoning as
	 * font_family_map(): Elementor's --e-global-color-* variables are
	 * only ever declared on the real front end.
	 */
	public static function color_value_map() {
		if ( ! did_action( 'elementor/loaded' ) || ! class_exists( '\Elementor\Plugin' ) ) {
			return array();
		}
		$kits_manager = \Elementor\Plugin::$instance->kits_manager;
		if ( ! $kits_manager ) {
			return array();
		}
		$kit = $kits_manager->get_active_kit_for_frontend();
		if ( ! $kit ) {
			return array();
		}

		$entries = array_merge(
			(array) $kit->get_settings( 'system_colors' ),
			(array) $kit->get_settings( 'custom_colors' )
		);

		$map = array();
		foreach ( $entries as $entry ) {
			if ( ! empty( $entry['_id'] ) && ! empty( $entry['color'] ) ) {
				$map[ $entry['_id'] ] = $entry['color'];
			}
		}
		return $map;
	}

	/**
	 * The one value every front-end accent color in this plugin (the
	 * Promotions Calendar, and Shop the Look's buttons, tour line and dots)
	 * should resolve to, in priority order. 1.34.0 adds 1b: an Elementor
	 * global color picked by name under Settings > Brand Color (any of
	 * them, custom ones too, e.g. Gates' navy), used as a live var() so it
	 * follows Elementor. Before it, only Primary/Accent/Secondary were
	 * tried, and on Gates that meant Elementor's default gray Secondary.
	 * 1) an explicit hex typed under Promo Manager > Settings — a
	 *    deliberate human choice always wins, since Elementor's "Accent"
	 *    or "Primary" slot isn't guaranteed to be the color a site
	 *    actually wants used here;
	 * 2) the first of Elementor's Global "Primary", "Accent", "Secondary"
	 *    colors that white text is readable on (see auto_accent_id()) —
	 *    referenced via a live var() (not a resolved hex), so it keeps
	 *    tracking Elementor if that color is ever changed;
	 * 3) $fallback, on any site with none of those.
	 *
	 * The var() only resolves where Elementor defines it: on the kit class
	 * it adds to <body>, never :root — so callers must declare the result
	 * on body or deeper (see RAPM_Calendar::enqueue()).
	 */
	public static function resolve_accent_color_css( $fallback = '#b5651d' ) {
		$manual = trim( (string) RAPM_Admin_Settings::get( 'accent_color' ) );
		if ( $manual ) {
			return $manual;
		}
		$picked = (string) RAPM_Admin_Settings::get( 'accent_global' );
		if ( '' !== $picked ) {
			$values = self::color_value_map();
			if ( isset( $values[ $picked ] ) ) {
				return 'var(--e-global-color-' . sanitize_html_class( $picked ) . ', ' . $values[ $picked ] . ')';
			}
		}
		$auto_id = self::auto_accent_id();
		if ( $auto_id ) {
			// 1.34.0: the color's own value as the var()'s fallback, so it's
			// right even on a page where Elementor doesn't load its colors.
			$values = self::color_value_map();
			return 'var(--e-global-color-' . sanitize_html_class( $auto_id ) . ', ' . ( isset( $values[ $auto_id ] ) ? $values[ $auto_id ] : $fallback ) . ')';
		}
		return $fallback;
	}

	/**
	 * Which Elementor Global color the calendar picks up automatically, or
	 * '' if none qualifies. The calendar puts white text on this color, so
	 * a color only qualifies if white text on it meets WCAG AA contrast
	 * (4.5:1) — many kits use "Accent" for a light neutral (e.g. #ECEDED),
	 * which would leave the bars' text unreadable. Primary is tried first
	 * since it's the brand color on most kits.
	 */
	public static function auto_accent_id() {
		$values = self::color_value_map();
		foreach ( array( 'primary', 'accent', 'secondary' ) as $id ) {
			if ( ! empty( $values[ $id ] ) && self::contrast_with_white( $values[ $id ] ) >= 4.5 ) {
				return $id;
			}
		}
		return '';
	}

	/**
	 * WCAG contrast ratio between white and a #rgb / #rrggbb color, or 0
	 * for anything that isn't a plain hex value.
	 */
	public static function contrast_with_white( $hex ) {
		$hex = ltrim( trim( (string) $hex ), '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		if ( ! preg_match( '/^[0-9a-fA-F]{6}$/', $hex ) ) {
			return 0;
		}
		$lum = 0;
		foreach ( array( 0.2126, 0.7152, 0.0722 ) as $i => $weight ) {
			$c    = hexdec( substr( $hex, $i * 2, 2 ) ) / 255;
			$c    = $c <= 0.03928 ? $c / 12.92 : pow( ( $c + 0.055 ) / 1.055, 2.4 );
			$lum += $weight * $c;
		}
		return 1.05 / ( $lum + 0.05 );
	}
}
