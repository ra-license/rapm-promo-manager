<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Spots (1.29.0): each place on the site where promotions show, by its
 * Kind + Placement pair (e.g. hero|home), with the name R&A gives it and
 * its settings. Approved by Phil on 2026-10-05:
 * - A spot is named for its job ("Main slider", "Living Room slider"),
 *   not its page, because one spot can go on several pages.
 * - Any number of spots of the same type can exist. R&A adds them with
 *   "+ New spot" on the Promotions screen.
 * - A spot with no promotions that isn't on any page can be removed
 *   (removable(), 1.29.1).
 *
 * The main heading (H1) is not a spot setting: one spot can sit on pages
 * that already have an H1 and pages that don't, so since 1.29.1 it is set
 * where the spot is placed (the Promo Carousel widget's "Main heading"
 * switch, or heading="h1" in the shortcode).
 *
 * Stored in one option, 'rapm_spots': 'kind|placement' => array( 'name',
 * 'h1' ). A spot also exists without an entry here, as soon as a promotion
 * uses it or a page carries its code; its name is then made from its
 * Kind and Placement.
 */
class RAPM_Spots {

	const OPTION = 'rapm_spots';

	/** 1.28.0 kept renames here; read once and folded into OPTION. */
	const OLD_NAMES_OPTION = 'rapm_spot_names';

	public static function all() {
		$spots = get_option( self::OPTION, null );
		if ( ! is_array( $spots ) ) {
			$spots = array();
			$old   = get_option( self::OLD_NAMES_OPTION, array() );
			if ( is_array( $old ) ) {
				foreach ( $old as $key => $name ) {
					$spots[ $key ] = array( 'name' => (string) $name );
				}
			}
			update_option( self::OPTION, $spots, false );
		}
		return $spots;
	}

	public static function get( $kind, $placement ) {
		$all = self::all();
		$key = $kind . '|' . $placement;
		return isset( $all[ $key ] ) && is_array( $all[ $key ] ) ? $all[ $key ] : array();
	}

	public static function update( $kind, $placement, $changes ) {
		$all  = self::all();
		$key  = $kind . '|' . $placement;
		$spot = isset( $all[ $key ] ) && is_array( $all[ $key ] ) ? $all[ $key ] : array();
		foreach ( $changes as $field => $value ) {
			if ( null === $value || '' === $value ) {
				unset( $spot[ $field ] );
			} else {
				$spot[ $field ] = $value;
			}
		}
		$all[ $key ] = $spot;
		update_option( self::OPTION, $all, false );
	}

	/** The type in plain words: Slider, Feature banner, Coupon row, Tile row. */
	public static function type_label( $kind ) {
		$info = RAPM_Slots::kind( $kind );
		return $info['label'];
	}

	/** The name clients see: the one R&A gave it, or one made from its type and placement ("Home page slider"). */
	public static function name( $kind, $placement ) {
		$spot = self::get( $kind, $placement );
		if ( ! empty( $spot['name'] ) ) {
			return $spot['name'];
		}
		$nouns = array(
			'hero'        => __( 'slider', 'rapm' ),
			'fold_banner' => __( 'banner', 'rapm' ),
			'coupon'      => __( 'coupons', 'rapm' ),
			'marquee'     => __( 'tiles', 'rapm' ),
			'look'        => __( 'looks', 'rapm' ),
		);
		if ( 'home' === $placement ) {
			$where = __( 'Home page', 'rapm' );
		} elseif ( 'default' === $placement ) {
			$where = __( 'Main', 'rapm' );
		} else {
			$where = ucwords( str_replace( array( '-', '_' ), ' ', $placement ) );
		}
		$noun = isset( $nouns[ $kind ] ) ? $nouns[ $kind ] : __( 'promotions', 'rapm' );
		// A code that already ends in the type's word ("home-page-looks",
		// "living-room-slider", "clearance-coupon") would read twice
		// ("Home Page Looks looks"), found on staging in 1.30.0. Singular or
		// plural counts as the same word.
		$parts = explode( '-', str_replace( '_', '-', $placement ) );
		$last  = strtolower( (string) end( $parts ) );
		$word  = strtolower( $noun );
		if ( $last === $word || $last . 's' === $word || $last === $word . 's' ) {
			return $where;
		}
		/* translators: 1: where on the site, e.g. "Home page"; 2: what it is, e.g. "slider" */
		return sprintf( __( '%1$s %2$s', 'rapm' ), $where, $noun );
	}

	public static function shortcode( $kind, $placement ) {
		$info = RAPM_Slots::kind( $kind );
		return 'default' === $placement ? '[' . $info['shortcode'] . ']' : '[' . $info['shortcode'] . ' placement="' . $placement . '"]';
	}

	/** Sliders and feature banners: the two types the Promo Carousel widget shows, and the only ones with a single headline. */
	public static function is_carousel( $kind ) {
		return in_array( $kind, array( 'hero', 'fold_banner' ), true );
	}

	/**
	 * Every spot that exists in any way: named here, used by a promotion,
	 * or placed on a page. Sorted by name.
	 *
	 * @param string $kind Only this type, or '' for all.
	 * @return array List of array( 'kind', 'placement', 'name', 'type' ).
	 */
	public static function known( $kind = '' ) {
		$keys = array();
		foreach ( array_keys( self::all() ) as $key ) {
			$keys[ $key ] = true;
		}
		$posts = get_posts(
			array(
				'post_type'      => 'rapm_asset',
				'post_status'    => array( 'publish', 'draft', 'pending', 'future', 'private' ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);
		foreach ( $posts as $id ) {
			$k = get_post_meta( $id, '_rapm_kind', true ) ?: 'hero'; // phpcs:ignore
			$p = get_post_meta( $id, '_rapm_placement', true ) ?: 'default'; // phpcs:ignore
			$keys[ $k . '|' . $p ] = true;
		}
		foreach ( array_keys( RAPM_Spot_Usage::get() ) as $key ) {
			if ( 'calendar' !== $key ) {
				$keys[ $key ] = true;
			}
		}

		$list = array();
		foreach ( array_keys( $keys ) as $key ) {
			if ( false === strpos( $key, '|' ) ) {
				continue;
			}
			list( $k, $p ) = explode( '|', $key, 2 );
			if ( $kind && $kind !== $k ) {
				continue;
			}
			$list[] = array(
				'kind'      => $k,
				'placement' => $p,
				'name'      => self::name( $k, $p ),
				'type'      => self::type_label( $k ),
			);
		}
		usort(
			$list,
			function ( $a, $b ) {
				return strcasecmp( $a['name'], $b['name'] );
			}
		);
		return $list;
	}

	public static function exists( $kind, $placement ) {
		foreach ( self::known( $kind ) as $spot ) {
			if ( $spot['placement'] === $placement ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Only a spot that would vanish anyway can be removed: one that only
	 * exists because it was named here, with no promotions (not counting
	 * the trash) and no page, template or widget carrying its code. Its
	 * promotions or placements would keep it on the screen otherwise.
	 */
	public static function removable( $kind, $placement ) {
		$all = self::all();
		if ( ! isset( $all[ $kind . '|' . $placement ] ) ) {
			return false;
		}
		if ( RAPM_Spot_Usage::for_spot( $kind, $placement ) ) {
			return false;
		}
		$posts = get_posts(
			array(
				'post_type'      => 'rapm_asset',
				'post_status'    => array( 'publish', 'draft', 'pending', 'future', 'private' ),
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
					'relation' => 'AND',
					array( 'key' => '_rapm_kind', 'value' => $kind ),
					array( 'key' => '_rapm_placement', 'value' => $placement ),
				),
			)
		);
		return empty( $posts );
	}

	public static function remove( $kind, $placement ) {
		$all = self::all();
		unset( $all[ $kind . '|' . $placement ] );
		update_option( self::OPTION, $all, false );
	}

	/**
	 * Makes a new spot named $name. Its placement code is made from the
	 * name ("Living Room slider" → living-room-slider), with -2, -3… added
	 * if that type already has one by that code.
	 *
	 * @return string The new placement code.
	 */
	public static function create( $kind, $name ) {
		$base = sanitize_title( $name );
		$base = '' !== $base ? $base : 'spot';
		$code = $base;
		$i    = 2;
		while ( self::exists( $kind, $code ) ) {
			$code = $base . '-' . $i;
			++$i;
		}
		self::update( $kind, $code, array( 'name' => $name ) );
		return $code;
	}
}
