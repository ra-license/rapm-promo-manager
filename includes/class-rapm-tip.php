<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One-line help with more on request (1.55.0, from the UX review Phil
 * approved on 2026-10-20, https://claude.ai/artifact/BpPqJ5XT1PkMbtsXb8P4mv).
 *
 * Each field keeps one short line; the rest opens under it from a small
 * (i) button, following Adobe Spectrum's contextual help (a quiet info
 * button, a short body, an optional link to more) and NN/G's progressive
 * disclosure. It opens in place rather than floating, so it works the same
 * with a mouse, a keyboard, a screen reader and on a phone. Esc closes it.
 */
class RAPM_Tip {

	private static $n = 0;

	/** Prints the hint line and, when $more is given, its (i) and the text it opens. */
	public static function hint( $short, $more = '', $link = '', $link_text = '' ) {
		$id = 'rapm-tip-' . ( ++self::$n );
		echo '<p class="description rapm-hint">' . esc_html( $short );
		if ( '' !== $more ) {
			echo ' <button type="button" class="rapm-tip-btn" aria-expanded="false" aria-controls="' . esc_attr( $id ) . '" title="' . esc_attr__( 'More about this', 'rapm' ) . '"><span aria-hidden="true">i</span><span class="screen-reader-text">' . esc_html__( 'More about this', 'rapm' ) . '</span></button>';
		}
		echo '</p>';
		if ( '' !== $more ) {
			echo '<div class="rapm-tip" id="' . esc_attr( $id ) . '" hidden><p>' . esc_html( $more ) . '</p>';
			if ( $link ) {
				echo '<p><a href="' . esc_url( $link ) . '">' . esc_html( $link_text ? $link_text : __( 'Learn more', 'rapm' ) ) . '</a></p>';
			}
			echo '</div>';
		}
	}

	/**
	 * An empty screen that teaches (1.55.0, NN/G's empty-state guidance): a
	 * small picture of what goes here, one sentence, and one way to start.
	 * $art is 'split', 'reveal' or 'spots'; $action is ready HTML (a link or
	 * a button) or ''.
	 */
	public static function empty_state( $art, $title, $text, $action = '' ) {
		$arts = array(
			// A room photo beside a promotion box.
			'split'  => '<svg viewBox="0 0 96 64" aria-hidden="true"><rect x="1" y="1" width="94" height="62" rx="4" fill="#f0f0f1" stroke="#c3c4c7"/><rect x="6" y="8" width="52" height="48" rx="2" fill="#c9d6e3"/><rect x="12" y="34" width="34" height="12" rx="4" fill="#5b7fa6"/><rect x="62" y="8" width="28" height="48" rx="2" fill="#f3ead8"/><rect x="66" y="22" width="20" height="5" rx="1" fill="#1d3557"/><rect x="69" y="38" width="14" height="5" rx="2.5" fill="#c8102e"/></svg>',
			// Two colored panels, one opening to a photo.
			'reveal' => '<svg viewBox="0 0 96 64" aria-hidden="true"><rect x="1" y="1" width="94" height="62" rx="4" fill="#f0f0f1" stroke="#c3c4c7"/><rect x="6" y="8" width="41" height="48" rx="2" fill="#1d5a4c"/><rect x="12" y="28" width="29" height="6" rx="1" fill="#fff"/><rect x="49" y="8" width="41" height="48" rx="2" fill="#c9d6e3"/><rect x="55" y="40" width="29" height="10" rx="3" fill="#5b7fa6"/><rect x="55" y="14" width="22" height="5" rx="1" fill="#fff"/></svg>',
			// A page with a slider and a row of tiles.
			'spots'  => '<svg viewBox="0 0 96 64" aria-hidden="true"><rect x="1" y="1" width="94" height="62" rx="4" fill="#f0f0f1" stroke="#c3c4c7"/><rect x="6" y="7" width="84" height="26" rx="2" fill="#c9d6e3"/><rect x="12" y="15" width="30" height="5" rx="1" fill="#1d3557"/><rect x="6" y="38" width="19" height="19" rx="2" fill="#e9b949"/><rect x="28" y="38" width="19" height="19" rx="2" fill="#f3ead8"/><rect x="50" y="38" width="19" height="19" rx="2" fill="#a9c9ec"/><rect x="72" y="38" width="18" height="19" rx="2" fill="#f2a7b6"/></svg>',
		);
		echo '<div class="rapm-empty-state">' . ( isset( $arts[ $art ] ) ? $arts[ $art ] : '' ) . '<div><h3>' . esc_html( $title ) . '</h3><p>' . esc_html( $text ) . '</p>' . $action . '</div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput -- fixed SVG; $action is built and escaped by the caller.
	}

	/** Loads the small style and script on Promo Manager's own admin screens. */
	public static function enqueue() {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 0 !== strpos( $page, 'rapm-' ) ) {
			return;
		}
		wp_enqueue_style( 'rapm-tips', RAPM_URL . 'assets/css/rapm-tips.css', array(), RAPM_VERSION );
		wp_enqueue_script( 'rapm-tips', RAPM_URL . 'assets/js/rapm-tips.js', array(), RAPM_VERSION, true );
	}
}
