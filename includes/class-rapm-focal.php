<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Focal points (1.54.0, from the mockup Phil approved on 2026-10-20,
 * https://claude.ai/artifact/ExuxeBqAPTtZQi63AMX4W8): what has to stay in
 * view when a picture is trimmed to fit a space.
 *
 * Each picture of a promotion can have one point, "X% Y%" (0–100):
 * _rapm_focus_desktop for the computer picture and _rapm_focus_mobile for
 * the phone picture. Only the website's trimming uses it, as CSS
 * object-position through --rapm-fp / --rapm-fp-m; the file itself is never
 * re-cropped, so it can be moved any time and nothing is lost. A picture
 * with no point keeps its middle, as before 1.54.0. With object-position
 * the point is always inside the trimmed area, near the same side.
 *
 * Measured against the website's real sizes (mockup), trims are big for
 * Reveal panels two across (58% of the height kept on a 1920 screen) and
 * Quick links' round phone thumbnails (45% of the width), small for split
 * slider room photos (up to about 13%), and about 2% elsewhere.
 *
 * Shop the Look keeps its own "What each screen shows" (_rapm_focus).
 */
class RAPM_Focal {

	/** Kinds whose pictures get trimmed, and so get a point. A Slider only in split view. */
	public static function applies( $kind, $placement ) {
		if ( 'hero' === $kind ) {
			return class_exists( 'RAPM_Split' ) && RAPM_Split::is_split( $placement );
		}
		return in_array( $kind, array( 'reveal', 'promo_box', 'quick_links' ), true );
	}

	/**
	 * Whether the kind's phone picture is trimmed too. A split slider's
	 * phone pictures show at their own shape, so only Reveal panels.
	 */
	public static function has_phone( $kind ) {
		return 'reveal' === $kind;
	}

	/** The saved point ("X% Y%"), or '' for the middle. */
	public static function get( $asset_id, $which ) {
		$raw = trim( (string) get_post_meta( $asset_id, 'mobile' === $which ? '_rapm_focus_mobile' : '_rapm_focus_desktop', true ) );
		return '' === $raw ? '' : self::clean( $raw );
	}

	/** "X% Y%" with one decimal each, or '' when it's the middle or not a point. */
	public static function clean( $value ) {
		if ( ! preg_match( '/^(\d{1,3}(?:\.\d+)?)% (\d{1,3}(?:\.\d+)?)%$/', trim( (string) $value ), $m ) ) {
			return '';
		}
		$x = round( min( 100, max( 0, (float) $m[1] ) ), 1 );
		$y = round( min( 100, max( 0, (float) $m[2] ) ), 1 );
		return ( 50.0 === $x && 50.0 === $y ) ? '' : $x . '% ' . $y . '%';
	}

	/** Saves (or clears) a picture's point. */
	public static function save( $asset_id, $which, $value ) {
		$key   = 'mobile' === $which ? '_rapm_focus_mobile' : '_rapm_focus_desktop';
		$point = self::clean( $value );
		if ( $point ) {
			update_post_meta( $asset_id, $key, $point );
		} else {
			delete_post_meta( $asset_id, $key );
		}
	}

	/** CSS custom properties for a promotion's wrapper: '' when both are the middle. */
	public static function style_vars( $asset_id ) {
		$out = '';
		$d   = self::get( $asset_id, 'desktop' );
		$m   = self::get( $asset_id, 'mobile' );
		if ( $d ) {
			$out .= '--rapm-fp:' . $d . ';';
		}
		if ( $m ) {
			$out .= '--rapm-fp-m:' . $m . ';';
		}
		return $out;
	}

	/**
	 * The shapes each kind's picture is trimmed to on the website, for the
	 * form's live previews: list of array( label, width, height, round? ).
	 * Typical screens, from the mockup's measurements.
	 */
	public static function shapes( $kind, $which ) {
		$s = array(
			'reveal'      => array(
				'desktop' => array( array( __( '2 panels, 1920 screen', 'rapm' ), 960, 560 ), array( __( '2 panels, 1440 laptop', 'rapm' ), 720, 560 ), array( __( '3 panels', 'rapm' ), 640, 560 ), array( __( '4 panels', 'rapm' ), 480, 560 ) ),
				'mobile'  => array( array( __( 'Phone panel', 'rapm' ), 375, 320 ) ),
			),
			'hero'        => array(
				'desktop' => array( array( __( '1920 screen, tall header', 'rapm' ), 1152, 750 ), array( __( 'Short window', 'rapm' ), 864, 560 ) ),
			),
			'promo_box'   => array(
				'desktop' => array( array( __( 'Beside a room, 1920', 'rapm' ), 768, 750 ), array( __( 'Beside a room, 1440', 'rapm' ), 576, 560 ), array( __( 'On its own, wide', 'rapm' ), 900, 600 ) ),
			),
			'quick_links' => array(
				'desktop' => array( array( __( 'Computer', 'rapm' ), 440, 200 ), array( __( 'Phone, round', 'rapm' ), 40, 40, true ), array( __( 'Phone, card', 'rapm' ), 120, 90 ) ),
			),
		);
		return isset( $s[ $kind ][ $which ] ) ? $s[ $kind ][ $which ] : array();
	}

	/**
	 * The "What stays in view" box under a picture on the promotion form:
	 * the picture with a point to drag, and every shape it's trimmed to,
	 * updated live (rapm-focal.js). $src may be '' for a new promotion; the
	 * box then waits for a chosen file.
	 */
	public static function render_picker( $kind, $which, $src, $value ) {
		$value = self::clean( $value );
		$xy    = $value ? array_map( 'floatval', explode( ' ', str_replace( '%', '', $value ) ) ) : array( 50, 50 );
		?>
		<div class="rapm-focal" data-which="<?php echo esc_attr( $which ); ?>" data-shapes="<?php echo esc_attr( wp_json_encode( self::shapes( $kind, $which ) ) ); ?>"<?php echo $src ? '' : ' hidden'; ?>>
			<input type="hidden" name="rapm_focus_<?php echo esc_attr( $which ); ?>" value="<?php echo esc_attr( $xy[0] . '% ' . $xy[1] . '%' ); ?>" />
			<p class="rapm-focal-head"><strong><?php esc_html_e( 'What stays in view', 'rapm' ); ?></strong> <span class="description"><?php esc_html_e( 'Drag the pink point onto what matters, like the sofa. Where the website trims this picture to fit, that part stays in view.', 'rapm' ); ?></span></p>
			<div class="rapm-focal-row">
				<div class="rapm-focal-photo" tabindex="0" role="slider" aria-label="<?php esc_attr_e( 'What stays in view. Arrow keys move the point.', 'rapm' ); ?>">
					<img src="<?php echo esc_url( $src ); ?>" alt="" draggable="false" />
					<span class="rapm-focal-dot" aria-hidden="true"></span>
				</div>
				<div class="rapm-focal-pvs" aria-label="<?php esc_attr_e( 'How it\'s trimmed on the website', 'rapm' ); ?>"></div>
			</div>
			<p><button type="button" class="button button-small rapm-focal-reset"><?php esc_html_e( 'Back to the middle', 'rapm' ); ?></button> <span class="rapm-focal-kv description"></span></p>
		</div>
		<?php
	}
}
