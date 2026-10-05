<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The one place that knows which files each display mode needs, and loads
 * them wherever a promotion actually shows up.
 *
 * Until 1.26.0 each display mode decided in the page <head> whether to load
 * its files, by looking for its shortcode in the current page's own content.
 * Anything rendered outside that content never got them: an Elementor
 * popup (a separate template), a Theme Builder header or footer, a product
 * or category page, or this plugin's own Promo Carousel widget (Elementor
 * saves a widget's rendered HTML as the page's plain content, not the
 * shortcode). Every display starts hidden and only rapm-schedule.js reveals
 * it, so without its files a promotion stayed invisible.
 *
 * Now every handle is registered on every front-end request (cheap: nothing
 * prints until something is enqueued), and each shortcode asks for its
 * files at the moment it renders. WordPress prints styles and scripts
 * enqueued after <head> in the footer. If the footer has already gone out,
 * need() returns the tags instead, so the shortcode prints them right
 * before its own markup.
 */
class RAPM_Assets {

	const SWIPER_VERSION = '11.0';

	private static $calendar_accent_added = false;

	public static function register() {
		if ( wp_style_is( 'rapm-hero-css', 'registered' ) ) {
			return;
		}
		wp_register_style( 'rapm-swiper-css', 'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css', array(), self::SWIPER_VERSION );
		wp_register_style( 'rapm-hero-css', RAPM_URL . 'assets/css/rapm-hero.css', array( 'rapm-swiper-css' ), RAPM_VERSION );
		wp_register_style( 'rapm-marquee-css', RAPM_URL . 'assets/css/rapm-marquee.css', array(), RAPM_VERSION );
		wp_register_style( 'rapm-coupon-book-css', RAPM_URL . 'assets/css/rapm-coupon-book.css', array( 'rapm-hero-css' ), RAPM_VERSION );
		wp_register_style( 'rapm-calendar-css', RAPM_URL . 'assets/css/rapm-calendar.css', array(), RAPM_VERSION );
		wp_register_script( 'rapm-swiper-js', 'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js', array(), self::SWIPER_VERSION, true );
		// Keep our Swiper 11 under its own name, and give back any Swiper the
		// page already had. Both files set window.Swiper, and whichever runs
		// last wins: Elementor's own Swiper 8 (its Image Carousel and the
		// editor preview load it) would otherwise run our carousels, and ours
		// would run Elementor's (it uses window.Swiper when one is already
		// there). rapm-schedule.js uses RAPM_Swiper first. If our file hasn't
		// actually run yet (e.g. an optimizer deferred it), nothing changes
		// and rapm-schedule.js falls back to window.Swiper, as before 1.27.1.
		wp_add_inline_script( 'rapm-swiper-js', 'window.RAPM_SwiperBefore = window.Swiper;', 'before' );
		wp_add_inline_script(
			'rapm-swiper-js',
			'( function () { var before = window.RAPM_SwiperBefore; if ( window.Swiper && window.Swiper !== before ) { window.RAPM_Swiper = window.Swiper; if ( before ) { window.Swiper = before; } } } )();',
			'after'
		);
		// No hard dependency on Swiper: Marquee and Coupon Book use this file
		// without it, and init() already checks for Swiper at run time.
		wp_register_script( 'rapm-schedule-js', RAPM_URL . 'assets/js/rapm-schedule.js', array(), RAPM_VERSION, true );
	}

	/**
	 * Style handles and script handles a display mode needs.
	 */
	private static function handles( $mode ) {
		switch ( $mode ) {
			case 'hero':
				return array( array( 'rapm-hero-css' ), array( 'rapm-swiper-js', 'rapm-schedule-js' ) );
			case 'marquee':
				return array( array( 'rapm-marquee-css' ), array( 'rapm-schedule-js' ) );
			case 'coupon_book':
				return array( array( 'rapm-coupon-book-css' ), array( 'rapm-schedule-js' ) );
			case 'calendar':
				return array( array( 'rapm-calendar-css' ), array() );
		}
		return array( array(), array() );
	}

	/**
	 * Load the files $mode needs. Returns '' when WordPress will print them
	 * itself (in the head or the footer), or the tags to print immediately
	 * when the footer has already been printed.
	 */
	public static function need( $mode ) {
		self::register();
		list( $styles, $scripts ) = self::handles( $mode );

		if ( 'calendar' === $mode && ! self::$calendar_accent_added ) {
			self::$calendar_accent_added = true;
			// Defines the one custom property the calendar stylesheet reads
			// for its accent color. See RAPM_Elementor::resolve_accent_color_css()
			// for the manual-override / Elementor-auto-detect / fallback order.
			// Declared on body, not :root: Elementor defines its
			// --e-global-color-* variables on the kit class it adds to <body>,
			// so a var() declared on :root can never see them.
			wp_add_inline_style(
				'rapm-calendar-css',
				'body{--rapm-calendar-accent:' . RAPM_Elementor::resolve_accent_color_css( '#b5651d' ) . ';}'
			);
		}

		if ( ! did_action( 'wp_print_footer_scripts' ) ) {
			foreach ( $styles as $handle ) {
				wp_enqueue_style( $handle );
			}
			foreach ( $scripts as $handle ) {
				wp_enqueue_script( $handle );
			}
			return '';
		}

		ob_start();
		wp_print_styles( $styles );
		wp_print_scripts( $scripts );
		return (string) ob_get_clean();
	}

	/**
	 * If WP Rocket's "Delay JavaScript execution" is on, it holds scripts
	 * back until the visitor scrolls or clicks. Its exclusion list matches
	 * keywords in a script's address or in an inline script's own text, so
	 * this covers the two files and the small inline start-up scripts each
	 * display prints (they mention RAPM_Schedule, or the calendar's own
	 * "rapm-cal-" id), plus the two around the Swiper file (RAPM_Swiper).
	 * No-op on sites without WP Rocket.
	 */
	public static function exclude_from_rocket_delay( $exclusions ) {
		$exclusions[] = 'swiper-bundle';
		$exclusions[] = 'rapm-schedule';
		$exclusions[] = 'RAPM_Schedule';
		$exclusions[] = 'RAPM_Swiper';
		$exclusions[] = 'rapm-cal-';
		return $exclusions;
	}
}
