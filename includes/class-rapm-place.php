<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Where the words sit on the picture (1.64.0, board 3 of the mockup Phil
 * approved on 2026-10-26, https://claude.ai/artifact/78Xsrqmi9RHQUxsjvddhnr):
 * "There should also be alignment tools for elements."
 *
 * For Slider, Feature banner and Coupon row the words used to sit in one
 * strip along the bottom of the picture; only their alignment (left,
 * center, right) could change. Now they can also go at the top or in the
 * middle, and phones can have their own places. Across is still
 * _rapm_text_align (computers); the rest is _rapm_place:
 *   y  'top', 'middle' or 'bottom' (computers; bottom is the old place)
 *   pa phones' across, or '' for the same as computers
 *   py phones' up and down, or '' for the same as computers
 * rapm-hero.css reads them as data-y, data-align-phone and data-y-phone
 * on .rapm-slide-copy. The Shop button's own alignment is kept with the
 * button (RAPM_Looks::sanitize_photo_btn()).
 */
class RAPM_Place {

	const META  = '_rapm_place';
	const KINDS = array( 'hero', 'fold_banner', 'coupon' );
	const X     = array( 'left', 'center', 'right' );
	const Y     = array( 'top', 'middle', 'bottom' );

	public static function applies( $kind ) {
		return in_array( $kind, self::KINDS, true );
	}

	/** The saved places, with every key there. */
	public static function get( $asset_id ) {
		$saved = get_post_meta( $asset_id, self::META, true );
		$saved = is_array( $saved ) ? $saved : array();
		return array(
			'y'  => isset( $saved['y'] ) && in_array( $saved['y'], self::Y, true ) ? $saved['y'] : 'bottom',
			'pa' => isset( $saved['pa'] ) && in_array( $saved['pa'], self::X, true ) ? $saved['pa'] : '',
			'py' => isset( $saved['py'] ) && in_array( $saved['py'], self::Y, true ) ? $saved['py'] : '',
		);
	}

	/** The words box's data attributes, after data-align. '' when nothing differs from the old layout. */
	public static function attrs( $asset_id ) {
		$p   = self::get( $asset_id );
		$out = '';
		if ( 'bottom' !== $p['y'] ) {
			$out .= ' data-y="' . esc_attr( $p['y'] ) . '"';
		}
		if ( '' !== $p['pa'] ) {
			$out .= ' data-align-phone="' . esc_attr( $p['pa'] ) . '"';
		}
		if ( '' !== $p['py'] ) {
			$out .= ' data-y-phone="' . esc_attr( $p['py'] ) . '"';
		}
		return $out;
	}

	/** Saves the form's choices (rapm_place_y, _pa, _py). Across for computers is rapm_text_align, saved as before. */
	public static function save( $asset_id ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- called from handle_save(), which checks the nonce.
		if ( ! isset( $_POST['rapm_place_y'] ) ) {
			return;
		}
		$y  = sanitize_key( wp_unslash( $_POST['rapm_place_y'] ) );
		$pa = isset( $_POST['rapm_place_pa'] ) ? sanitize_key( wp_unslash( $_POST['rapm_place_pa'] ) ) : '';
		$py = isset( $_POST['rapm_place_py'] ) ? sanitize_key( wp_unslash( $_POST['rapm_place_py'] ) ) : '';
		// phpcs:enable
		$place = array(
			'y'  => in_array( $y, self::Y, true ) ? $y : 'bottom',
			'pa' => in_array( $pa, self::X, true ) ? $pa : '',
			'py' => in_array( $py, self::Y, true ) ? $py : '',
		);
		if ( 'bottom' === $place['y'] && '' === $place['pa'] && '' === $place['py'] ) {
			delete_post_meta( $asset_id, self::META );
		} else {
			update_post_meta( $asset_id, self::META, $place );
		}
	}
}
