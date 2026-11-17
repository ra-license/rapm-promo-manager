<?php
/**
 * Coupons that follow what the shopper looked at (1.70.0).
 *
 * Phil, Nov 17 call: "I wonder if there's a way for the promo manager to see
 * what ... pages ... a customer has been to, and then automatically update
 * the categories for coupons." Google Analytics' cookie can't do it: it only
 * carries an id, and the pages visited stay inside Google. So Promo Manager
 * keeps its own short note in the shopper's browser instead.
 *
 * How it works:
 *   - On a product page, a product category page or a brand page, a few
 *     lines of script add that page's categories (and their parent
 *     categories) and brands to a note in the shopper's browser
 *     (localStorage "rapm_seen"): how many times, and the last day.
 *     Entries older than 30 days drop off; at most 30 are kept.
 *   - Each coupon card carries what it's about, taken from its link: a
 *     product category, a brand, or a product's own categories and brands
 *     (data-rapm-for). A coupon that links to a page, a search or another
 *     website isn't about anything and keeps its place.
 *   - The coupon row reads the note and moves the coupons that match what
 *     the shopper looked at most, and most recently, to the front
 *     (RAPM_Schedule.followInterests in rapm-schedule.js).
 *
 * Safe by default:
 *   - Off until R&A turns it on for a site (Settings › Coupons).
 *   - It only changes the order. Every coupon still shows, nothing about an
 *     offer or its link changes, and a first-time visitor sees the normal
 *     order.
 *   - The note never leaves the browser: it's browser storage, not a
 *     cookie, so it isn't even sent to the website. No name or email.
 *   - Decided in the browser, like the dates, so it keeps working behind
 *     WP Rocket and the CDN (every shopper gets the same cached page).
 *   - When the website runs a consent tool that uses the WP Consent API
 *     (Complianz, CookieYes and others), the note is only kept once the
 *     shopper has allowed "preferences".
 */

defined( 'ABSPATH' ) || exit;

class RAPM_Interests {

	public static function init() {
		add_action( 'wp_footer', array( __CLASS__, 'record' ), 5 );
	}

	/** Whether this website has it turned on. */
	public static function enabled() {
		return class_exists( 'WooCommerce' ) && (bool) RAPM_Admin_Settings::get( 'interests_enabled' );
	}

	private static function is_preview() {
		return is_admin() || is_customize_preview() || isset( $_GET['elementor-preview'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	/** A category with its parent categories, as keys ("c12", "c10"). */
	private static function cat_keys( $term_ids, $with_parents ) {
		$keys = array();
		foreach ( (array) $term_ids as $id ) {
			$id = (int) $id;
			if ( ! $id ) {
				continue;
			}
			$keys[] = 'c' . $id;
			if ( $with_parents ) {
				foreach ( get_ancestors( $id, 'product_cat', 'taxonomy' ) as $parent ) {
					$keys[] = 'c' . (int) $parent;
				}
			}
		}
		return $keys;
	}

	/** A product's brands, as keys ("b34"). */
	private static function brand_keys_for_product( $product_id ) {
		$tax = RAPM_Destination::brand_taxonomy();
		if ( ! $tax || ! taxonomy_exists( $tax ) ) {
			return array();
		}
		$ids = wp_get_post_terms( (int) $product_id, $tax, array( 'fields' => 'ids' ) );
		if ( is_wp_error( $ids ) ) {
			return array();
		}
		return array_map(
			function ( $id ) {
				return 'b' . (int) $id;
			},
			$ids
		);
	}

	/** What the page being shown is about: its categories (with parents) and brands. */
	public static function page_keys() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return array();
		}
		$keys = array();
		$tax  = RAPM_Destination::brand_taxonomy();
		if ( function_exists( 'is_product' ) && is_product() ) {
			$id   = (int) get_queried_object_id();
			$keys = array_merge(
				self::cat_keys( wc_get_product_term_ids( $id, 'product_cat' ), true ),
				self::brand_keys_for_product( $id )
			);
		} elseif ( function_exists( 'is_product_category' ) && is_product_category() ) {
			$keys = self::cat_keys( array( get_queried_object_id() ), true );
		} elseif ( $tax && taxonomy_exists( $tax ) && is_tax( $tax ) ) {
			$keys = array( 'b' . (int) get_queried_object_id() );
		}
		return array_values( array_unique( $keys ) );
	}

	/**
	 * The few lines that add this page to the shopper's note. Printed in the
	 * footer of product, category and brand pages only. The marker comment
	 * keeps WP Rocket from holding it back (RAPM_Assets::exclude_from_rocket_delay).
	 */
	public static function record() {
		if ( ! self::enabled() || self::is_preview() ) {
			return;
		}
		$keys = self::page_keys();
		if ( ! $keys ) {
			return;
		}
		$js = '/* RAPM_Seen */(function(k){try{'
			. 'if(typeof window.wp_has_consent==="function"&&!window.wp_has_consent("preferences")){return;}'
			. 'var s,d=Math.floor(Date.now()/864e5),o={};'
			. 'try{s=JSON.parse(localStorage.getItem("rapm_seen")||"{}");}catch(e){s=null;}'
			. 'if(!s||typeof s!=="object"||Array.isArray(s)){s={};}'
			. 'k.forEach(function(x){var e=s[x];s[x]=[(e&&e[0]||0)+1,d];});'
			. 'Object.keys(s).filter(function(x){return Array.isArray(s[x])&&d-s[x][1]<=30;})'
			. '.sort(function(a,b){return s[b][1]-s[a][1]||s[b][0]-s[a][0];}).slice(0,30)'
			. '.forEach(function(x){o[x]=s[x];});'
			. 'localStorage.setItem("rapm_seen",JSON.stringify(o));'
			. '}catch(e){}})(' . wp_json_encode( $keys ) . ');';
		echo "<script>{$js}</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput -- keys are built from integers only.
	}

	/** What a coupon is about, from its link. Empty when it links to a page, a search or another website. */
	public static function card_keys( $asset_id ) {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return array();
		}
		$type = (string) get_post_meta( $asset_id, '_rapm_destination_type', true );
		$raw  = get_post_meta( $asset_id, '_rapm_destination_value', true );
		if ( 'url' === $type ) {
			return self::url_keys( (string) $raw );
		}
		$value = (int) $raw;
		if ( ! $value ) {
			return array();
		}
		switch ( $type ) {
			case 'wc_category':
				return array( 'c' . $value );
			case 'wc_brand':
				return array( 'b' . $value );
			case 'wc_product':
				return self::product_keys( $value );
		}
		return array();
	}

	/**
	 * A product's own categories and brands, not their parents: a coupon for
	 * one recliner follows shoppers who looked at recliners, not everyone who
	 * looked at anything in Living Room.
	 */
	private static function product_keys( $product_id ) {
		return array_values( array_unique( array_merge( self::cat_keys( wc_get_product_term_ids( $product_id, 'product_cat' ), false ), self::brand_keys_for_product( $product_id ) ) ) );
	}

	/**
	 * 1.70.1: a coupon linked with "A specific link" to this website's own
	 * category, brand or product page counts like one linked with "A product
	 * category", "A specific brand" or "A specific product". Found on staging:
	 * its "15% Off Accent Chairs" coupon links to /living-room/chairs/ as a
	 * typed address, and coupons made before those choices existed are likely
	 * the same. Another website, a page, or a search keeps its place.
	 */
	private static function url_keys( $url ) {
		$url = trim( $url );
		if ( '' === $url ) {
			return array();
		}
		$host = strtolower( preg_replace( '/^www\./i', '', (string) wp_parse_url( $url, PHP_URL_HOST ) ) );
		$home = strtolower( preg_replace( '/^www\./i', '', (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) );
		if ( '' === $host || $host !== $home || '' !== (string) wp_parse_url( $url, PHP_URL_QUERY ) ) {
			return array(); // Another website, or a filtered or search address.
		}
		$path = trim( (string) wp_parse_url( $url, PHP_URL_PATH ), '/' );
		if ( '' === $path ) {
			return array();
		}
		$slug = sanitize_title( basename( $path ) );
		$tax  = RAPM_Destination::brand_taxonomy();
		foreach ( array( 'c' => 'product_cat', 'b' => $tax ) as $prefix => $taxonomy ) {
			if ( ! $taxonomy || ! taxonomy_exists( $taxonomy ) ) {
				continue;
			}
			$term = get_term_by( 'slug', $slug, $taxonomy );
			if ( ! $term ) {
				continue;
			}
			$link = get_term_link( $term );
			// The whole path has to match, so /bedroom/chairs/ isn't taken for /living-room/chairs/.
			if ( ! is_wp_error( $link ) && trim( (string) wp_parse_url( $link, PHP_URL_PATH ), '/' ) === $path ) {
				return array( $prefix . (int) $term->term_id );
			}
		}
		$post_id = url_to_postid( $url );
		if ( $post_id && 'product' === get_post_type( $post_id ) ) {
			return self::product_keys( $post_id );
		}
		return array();
	}

	/** The card's attribute, or '' when it's off or the coupon isn't about anything. */
	public static function attr( $asset_id ) {
		if ( ! self::enabled() ) {
			return '';
		}
		$keys = self::card_keys( $asset_id );
		return $keys ? ' data-rapm-for="' . esc_attr( implode( ' ', $keys ) ) . '"' : '';
	}

	/** The coupon row's switch for the browser script. */
	public static function row_attr() {
		return self::enabled() ? ' data-rapm-follow' : '';
	}

	/** The names behind a coupon's keys, for the form: "Sofas, Flexsteel". */
	public static function names( $asset_id ) {
		$names = array();
		foreach ( self::card_keys( $asset_id ) as $key ) {
			$tax  = 'c' === $key[0] ? 'product_cat' : RAPM_Destination::brand_taxonomy();
			$term = get_term( (int) substr( $key, 1 ), $tax );
			if ( $term && ! is_wp_error( $term ) ) {
				$names[] = $term->name;
			}
		}
		return $names;
	}

	/** The line on a coupon's form under its dates. */
	public static function form_note( $asset_id ) {
		if ( ! self::enabled() ) {
			return current_user_can( 'manage_options' )
				? __( 'Off for this website. Turn it on in Settings › Coupons to move coupons to the front for shoppers who looked at what they\'re for.', 'rapm' )
				: __( 'Off for this website. Ask R&A if you\'d like coupons to move to the front for shoppers who looked at what they\'re for.', 'rapm' );
		}
		$names = $asset_id ? self::names( $asset_id ) : array();
		if ( $names ) {
			/* translators: %s: category and brand names, like "Sofas, Flexsteel" */
			return sprintf( __( 'Moves to the front for shoppers who looked at: %s. It comes from the coupon\'s link.', 'rapm' ), implode( ', ', $names ) );
		}
		return __( 'Link this coupon to a product category, a brand or a product (or to one of their pages on this website), and it moves to the front for shoppers who looked at those. Linked anywhere else, it keeps its place.', 'rapm' );
	}
}
