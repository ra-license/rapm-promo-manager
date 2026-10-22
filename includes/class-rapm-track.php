<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Promotion views and clicks in Google Analytics (1.59.0, from the plan
 * Phil approved on 2026-10-22). Off until R&A turns it on in Settings.
 *
 * Each display already marks its promotions with data-rapm-key. Every
 * renderer calls remember() for the promotions it prints; just before the
 * footer scripts, the details GA4 needs (Internal Name, spot, kind and the
 * real products) go to rapm-track.js as one list, keyed by promotion. The
 * script decides in the visitor's browser what was seen and clicked, so it
 * works behind full-page caches the same way the schedule does.
 *
 * GA4 facts this rests on (developers.google.com/analytics, Oct 2026):
 * view_promotion and select_promotion require an items array, and the
 * "Item promotion name" report dimension only comes from items. Promotion
 * fields sent at event level are copied onto each item.
 */
class RAPM_Track {

	/** Most products listed in one event (GA4 allows 200). */
	const MAX_ITEMS = 25;

	/** Promotions printed on this page: id => array( n, s, k, i ). */
	private static $seen = array();

	public static function enabled() {
		return (bool) RAPM_Admin_Settings::get( 'ga_enabled' );
	}

	/** Called by each renderer for a promotion it prints. */
	public static function remember( $asset_id ) {
		$asset_id = (int) $asset_id;
		if ( ! $asset_id || isset( self::$seen[ $asset_id ] ) || ! self::enabled() || self::is_preview() ) {
			return;
		}
		RAPM_Assets::need( 'track' );
		$kind      = (string) get_post_meta( $asset_id, '_rapm_kind', true );
		$placement = (string) get_post_meta( $asset_id, '_rapm_placement', true );
		$name      = html_entity_decode( get_the_title( $asset_id ), ENT_QUOTES, 'UTF-8' );
		self::$seen[ $asset_id ] = array(
			'n' => '' !== $name ? $name : 'rapm-' . $asset_id,
			's' => $kind ? RAPM_Spots::name( $kind, $placement ? $placement : 'default' ) : '',
			'k' => $kind ? RAPM_Spots::type_label( $kind ) : '',
			'i' => self::items( $asset_id ),
		);
	}

	/**
	 * The real products a promotion points to, as array( item_id, item_name )
	 * pairs: its dots, a product link, or a hand-picked list (Phil's pick).
	 * item_id is the SKU, or the product number when there's none (or when
	 * Settings says this site's other GA4 events use product numbers).
	 */
	public static function items( $asset_id ) {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return array();
		}
		$ids = array();
		foreach ( RAPM_Looks::sanitize_dots( get_post_meta( $asset_id, '_rapm_dots', true ) ) as $dot ) {
			$ids[] = (int) $dot['p'];
		}
		$type  = (string) get_post_meta( $asset_id, '_rapm_destination_type', true );
		$value = get_post_meta( $asset_id, '_rapm_destination_value', true );
		if ( 'wc_product' === $type && $value ) {
			$ids[] = (int) $value;
		} elseif ( 'curated' === $type ) {
			$curated = RAPM_Destination::decode_curated_value( $value );
			foreach ( (array) $curated['skus'] as $sku ) {
				$ids[] = (int) wc_get_product_id_by_sku( $sku );
			}
		}
		$use_sku = 'id' !== RAPM_Admin_Settings::get( 'ga_item_id' );
		$items   = array();
		foreach ( array_unique( array_filter( $ids ) ) as $pid ) {
			$info = RAPM_Looks::product_info( $pid );
			if ( ! $info ) {
				continue;
			}
			$items[] = array( $use_sku && '' !== $info['sku'] ? $info['sku'] : (string) $pid, $info['name'], (string) $pid );
			if ( count( $items ) >= self::MAX_ITEMS ) {
				break;
			}
		}
		return $items;
	}

	/**
	 * Before the footer scripts print: hands the list to rapm-track.js.
	 * A promotion that renders after the footer (rare) still sends its
	 * events, named rapm-<id>.
	 */
	public static function print_data() {
		if ( ! self::$seen || ! wp_script_is( 'rapm-track-js', 'enqueued' ) ) {
			return;
		}
		wp_add_inline_script(
			'rapm-track-js',
			'window.RAPM_Track = ' . wp_json_encode(
				array(
					'mode' => self::mode(),
					'data' => (object) self::$seen,
				)
			) . ';',
			'before'
		);
	}

	/** WordPress admin, the Customizer and Elementor's editor preview send nothing. */
	private static function is_preview() {
		return is_admin() || is_customize_preview() || isset( $_GET['elementor-preview'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	public static function mode() {
		$mode = (string) RAPM_Admin_Settings::get( 'ga_mode' );
		return in_array( $mode, array( 'auto', 'gtag', 'gtm' ), true ) ? $mode : 'auto';
	}
}
