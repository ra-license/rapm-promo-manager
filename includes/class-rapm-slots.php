<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The registry of image "slots" this plugin enforces — each one a
 * placement (e.g. the desktop hero) with its own required dimensions,
 * format, and max file size. Registered via a filter so a specific client
 * site can add its own slots with zero core changes, matching how
 * CEC_Elementor registers its widget category. Numeric overrides (a client
 * needing a different size than the agency default) live in this plugin's
 * settings option, merged over the filter-registered defaults — the label
 * and format stay code-defined so a site can't accidentally break a
 * slot's contract by fat-fingering a settings field.
 */
class RAPM_Slots {

	public static function defaults() {
		return array(
			'hero_desktop'        => array(
				'label'                  => __( 'Slider — Desktop', 'rapm' ),
				'width'                  => 1920,
				'height'                 => 600,
				'aspect_ratio_tolerance' => 0.02,
				'max_kb'                 => 300,
				'format'                 => 'webp',
			),
			'hero_mobile'         => array(
				'label'                  => __( 'Slider — Mobile', 'rapm' ),
				'width'                  => 1080,
				'height'                 => 1920,
				'aspect_ratio_tolerance' => 0.02,
				'max_kb'                 => 300,
				'format'                 => 'webp',
			),
			// A shorter, lower-key strip meant to sit near the fold rather
			// than dominate the top of the page — no established industry
			// dimension convention exists for this (checked directly), so
			// these numbers are a starting point, not a standard; adjust
			// freely under Settings if a client's own testing says
			// otherwise. Kept far smaller than the hero's file-size budget
			// since it's proportionally a much smaller image.
			'fold_banner_desktop' => array(
				'label'                  => __( 'Feature banner — Desktop', 'rapm' ),
				'width'                  => 1920,
				'height'                 => 300,
				'aspect_ratio_tolerance' => 0.02,
				'max_kb'                 => 200,
				'format'                 => 'webp',
			),
			// A shorter mobile crop than the same 6.4:1 desktop ratio would
			// give (1080x170) — kept taller for text legibility on a small
			// screen, a deliberate judgment call given no standard exists.
			'fold_banner_mobile'  => array(
				'label'                  => __( 'Feature banner — Mobile', 'rapm' ),
				'width'                  => 1080,
				'height'                 => 400,
				'aspect_ratio_tolerance' => 0.02,
				'max_kb'                 => 200,
				'format'                 => 'webp',
			),
			// A portrait card shown in a horizontal scrolling strip
			// (the coupon book) rather than full-bleed — same shape on
			// both desktop and mobile since it's always displayed at a
			// small, fixed card size, not a full-width hero. No
			// established industry convention exists for this either
			// (same situation as fold_banner above), so this is a
			// starting point, adjustable under Settings.
			'coupon_card'         => array(
				'label'                  => __( 'Coupon Card', 'rapm' ),
				'width'                  => 600,
				'height'                 => 750,
				'aspect_ratio_tolerance' => 0.02,
				'max_kb'                 => 150,
				'format'                 => 'webp',
			),
			// A square tile shown small in a row (the marquee). Deliberately
			// matches Instagram's standard square post size (1080x1080) —
			// a recognizable, easy-to-export target for clients who already
			// think in social-media proportions, and it crops predictably
			// regardless of source photo shape (the exact problem that made
			// the original tile row look inconsistent — every image was a
			// different aspect ratio, forced into the same small box).
			'marquee_tile'        => array(
				'label'                  => __( 'Tile', 'rapm' ),
				'width'                  => 1080,
				'height'                 => 1080,
				'aspect_ratio_tolerance' => 0.02,
				'max_kb'                 => 200,
				'format'                 => 'webp',
			),
			// Shop the Look (1.30.0): one room photo per look, any shape.
			// 'flexible' means no required shape: the photo is shown
			// cover-cropped to fill a wide hero on computers and a 4:3 box on
			// phones, and "What each screen shows" (dragged per screen, 1.36.0) picks the crop.
			// Wider than 'width' is scaled down (keeping its shape), narrower
			// than 'min_width' is turned down. 2400 covers a 1920px screen
			// with room to spare; 800 is a floor so a thumbnail-sized photo
			// can't end up stretched across a page. Height is only used for
			// the card shape on the Promotions screen (3:2, a common room
			// photo shape). Both numbers are judgment calls; the width and
			// the file size can be changed in Settings like any slot's.
			'look_photo'          => array(
				'label'                  => __( 'Shop the Look — Room photo', 'rapm' ),
				'width'                  => 2400,
				'height'                 => 1600,
				'min_width'              => 800,
				'aspect_ratio_tolerance' => 0.02,
				'max_kb'                 => 450,
				'format'                 => 'webp',
				'flexible'               => true,
			),
		);
	}

	/** A slot that takes any shape of picture (Shop the Look's room photo). */
	public static function is_flexible( $slot ) {
		return is_array( $slot ) && ! empty( $slot['flexible'] );
	}

	/**
	 * Labels are the plain type names clients see (1.29.0, approved by
	 * Phil): Slider, Feature banner, Coupon row, Tile row. The keys stay
	 * hero / fold_banner / coupon / marquee, the codes used in shortcodes
	 * and saved promotions.
	 *
	 * Which two slots (desktop/mobile) apply for each asset "kind" — the
	 * one other place, besides here, that would need updating to add a
	 * third kind later.
	 */
	public static function kinds() {
		return array(
			'hero'        => array(
				'label'                 => __( 'Slider', 'rapm' ),
				'help'                  => __( 'Big rotating pictures, usually at the top of a page.', 'rapm' ),
				'desktop'               => 'hero_desktop',
				'mobile'                => 'hero_mobile',
				'shortcode'             => 'rapm_hero',
				'has_elementor_widget'  => true,
			),
			'fold_banner' => array(
				'label'                 => __( 'Feature banner', 'rapm' ),
				'help'                  => __( 'A wide strip that features one thing, lower on a page.', 'rapm' ),
				'desktop'               => 'fold_banner_desktop',
				'mobile'                => 'fold_banner_mobile',
				'shortcode'             => 'rapm_fold_banner',
				'has_elementor_widget'  => true,
			),
			// A single card slot reused for both desktop/mobile — same
			// shape either way since it's always shown at a small, fixed
			// card size (see coupon_card above), not a full-width hero.
			'coupon'      => array(
				'label'                 => __( 'Coupon row', 'rapm' ),
				'help'                  => __( 'Coupon cards in a row people can scroll.', 'rapm' ),
				'desktop'               => 'coupon_card',
				'mobile'                => 'coupon_card',
				'shortcode'             => 'rapm_coupon_book',
				'has_elementor_widget'  => false,
			),
			// A single square tile slot reused for both desktop/mobile,
			// same reasoning as coupon_card above — always shown small, in
			// a fixed-count row, never full-bleed.
			'marquee'     => array(
				'label'                 => __( 'Tile row', 'rapm' ),
				'help'                  => __( 'Small square tiles in a row.', 'rapm' ),
				'desktop'               => 'marquee_tile',
				'mobile'                => 'marquee_tile',
				'shortcode'             => 'rapm_marquee',
				'has_elementor_widget'  => false,
			),
			// Shop the Look (1.30.0, from the mockup Phil approved on
			// 2026-10-06): a wide room photo with a bar of tabs under it, one
			// tab per look. One photo for every screen, so 'mobile' reuses the
			// same slot and the form shows a single picture box.
			'look'        => array(
				'label'                 => __( 'Shop the Look', 'rapm' ),
				'help'                  => __( 'Room photos with tabs under them. Each tab is one room or collection.', 'rapm' ),
				'desktop'               => 'look_photo',
				'mobile'                => 'look_photo',
				'shortcode'             => 'rapm_looks',
				'has_elementor_widget'  => false,
			),
		);
	}

	public static function kind( $kind_key ) {
		$kinds = self::kinds();
		return isset( $kinds[ $kind_key ] ) ? $kinds[ $kind_key ] : $kinds['hero'];
	}

	/**
	 * Every registered slot, with this site's numeric overrides (width,
	 * height, max_kb) merged in from Settings. label/format always come
	 * from code, never from the overrides, so a stored override can't
	 * silently rename a slot or change what format it requires.
	 */
	public static function all() {
		$slots     = apply_filters( 'rapm_slots', self::defaults() );
		$overrides = RAPM_Admin_Settings::get( 'slot_overrides' );
		if ( ! is_array( $overrides ) ) {
			$overrides = array();
		}

		foreach ( $slots as $key => $slot ) {
			if ( empty( $overrides[ $key ] ) || ! is_array( $overrides[ $key ] ) ) {
				continue;
			}
			foreach ( array( 'width', 'height', 'max_kb', 'aspect_ratio_tolerance' ) as $field ) {
				if ( isset( $overrides[ $key ][ $field ] ) && '' !== $overrides[ $key ][ $field ] ) {
					$slots[ $key ][ $field ] = $overrides[ $key ][ $field ];
				}
			}
		}

		return $slots;
	}

	public static function get( $key ) {
		$slots = self::all();
		return isset( $slots[ $key ] ) ? $slots[ $key ] : null;
	}

	/**
	 * Checks a pixel width/height against a slot's required dimensions,
	 * allowing its aspect_ratio_tolerance as a small margin rather than
	 * demanding an exact pixel match — client exports are rarely
	 * pixel-perfect, and demanding they be generates support tickets for
	 * no real benefit over "close enough to not visibly distort."
	 */
	public static function dimensions_match( $slot, $width, $height ) {
		if ( ! $slot ) {
			return false;
		}
		if ( self::is_flexible( $slot ) ) {
			// Any shape; only too narrow or wider than needed is "off".
			$min = isset( $slot['min_width'] ) ? (int) $slot['min_width'] : 0;
			return $width >= $min && $width <= (int) $slot['width'];
		}
		$tolerance = isset( $slot['aspect_ratio_tolerance'] ) ? (float) $slot['aspect_ratio_tolerance'] : 0;
		$w_diff    = abs( $width - $slot['width'] ) / $slot['width'];
		$h_diff    = abs( $height - $slot['height'] ) / $slot['height'];
		return $w_diff <= $tolerance && $h_diff <= $tolerance;
	}

	/**
	 * True if an upload is the right *shape* for a slot (its width:height
	 * ratio is close to the slot's own, within the same
	 * aspect_ratio_tolerance) even though its absolute pixel dimensions
	 * don't match dimensions_match(). This is the one case
	 * RAPM_Upload_Handler auto-resizes rather than rejecting — e.g. a
	 * 3840x1200 export of a 1920x600 hero slot is the exact same 3.2:1
	 * shape, just at 2x resolution, so scaling it down is a pure,
	 * lossless fit with no cropping and nothing to distort. A materially
	 * different shape (a square photo dropped into a wide hero slot)
	 * fails this too, and stays a hard rejection — fitting that requires
	 * either cropping or squashing, and either one risks cutting off or
	 * warping whatever the photo is actually of, which needs a human
	 * decision, not an automatic one.
	 */
	public static function aspect_ratio_matches( $slot, $width, $height ) {
		if ( ! $slot || ! $width || ! $height ) {
			return false;
		}
		if ( self::is_flexible( $slot ) ) {
			return true; // Every shape fits; see the 'look_photo' slot.
		}
		$tolerance    = isset( $slot['aspect_ratio_tolerance'] ) ? (float) $slot['aspect_ratio_tolerance'] : 0;
		$target_ratio = $slot['width'] / $slot['height'];
		$actual_ratio = $width / $height;
		return abs( $actual_ratio - $target_ratio ) / $target_ratio <= $tolerance;
	}
}
