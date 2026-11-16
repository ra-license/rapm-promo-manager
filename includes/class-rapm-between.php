<?php
/**
 * Between-sales slides (1.69.0).
 *
 * Kenna Walls (Kemper), Sep 23 and again on the Anniversary Sale ticket
 * (#1594): "between sales when no promotion is running, I'd like to have a
 * default image and text to reappear." Before 1.69.0 a slide with no dates
 * always showed, so the regular "Make Yourself at Home" slide took turns
 * with the sale graphics instead of stepping aside.
 *
 * A Slider or Feature banner promotion can be ticked "Between sales". It
 * shows only while nothing else in its spot is showing, steps aside when a
 * sale (or any other promotion there) starts, and comes back when the last
 * one ends. Ad servers call these house ads (Google Ad Manager): lowest
 * priority, shown only when nothing else fills the spot. Decision 8,
 * option A, in the Approved Art Pipeline brief.
 *
 * Slides without the tick work exactly as before. The decision is made in
 * the shopper's browser like the dates (rapm-schedule.js), so it works
 * behind WP Rocket and the CDN. The checks here are for R&A's screens and
 * for which picture loads first.
 */

defined( 'ABSPATH' ) || exit;

class RAPM_Between {

	const META = '_rapm_between_sales';

	/** The kinds that rotate in one spot, where "between sales" means something. */
	public static function applies( $kind ) {
		return in_array( $kind, array( 'hero', 'fold_banner' ), true );
	}

	public static function is_on( $post_id ) {
		return '1' === (string) get_post_meta( $post_id, self::META, true );
	}

	/** Saves the form's checkbox (only sent by Slider and Feature banner forms). */
	public static function save( $post_id, $kind ) {
		if ( ! self::applies( $kind ) ) {
			delete_post_meta( $post_id, self::META );
			return;
		}
		if ( ! empty( $_POST['rapm_between_sales'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification -- the form's own nonce is checked by the caller.
			update_post_meta( $post_id, self::META, '1' );
		} else {
			delete_post_meta( $post_id, self::META );
		}
	}

	/** Whether a promotion is on the website right now by its own switch and dates. */
	public static function live_by_dates( $post ) {
		return 'publish' === $post->post_status && RAPM_Quick_Links::is_live_now(
			get_post_meta( $post->ID, '_rapm_starts_at', true ),
			get_post_meta( $post->ID, '_rapm_ends_at', true )
		);
	}

	/** Whether any promotion in this list that isn't between-sales is showing now. */
	public static function sale_live_in( $posts ) {
		foreach ( $posts as $post ) {
			if ( ! self::is_on( $post->ID ) && self::live_by_dates( $post ) ) {
				return true;
			}
		}
		return false;
	}

	/** The published promotions in a spot, remembered for the request. */
	private static function spot( $kind, $placement ) {
		static $cache = array();
		$key = $kind . '|' . $placement;
		if ( ! isset( $cache[ $key ] ) ) {
			$cache[ $key ] = get_posts(
				array(
					'post_type'      => 'rapm_asset',
					'post_status'    => 'publish',
					'posts_per_page' => -1,
					'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
						'relation' => 'AND',
						array( 'key' => '_rapm_kind', 'value' => $kind ),
						array( 'key' => '_rapm_placement', 'value' => $placement ),
					),
				)
			);
		}
		return $cache[ $key ];
	}

	/**
	 * For a between-sales promotion that's on by its own dates: false when
	 * it's showing, or when it's waiting, the time it comes back (the
	 * latest end of what's showing instead), 0 when one of those has no end.
	 *
	 * @return false|int
	 */
	public static function waiting( $post ) {
		if ( ! self::is_on( $post->ID ) || ! self::live_by_dates( $post ) ) {
			return false;
		}
		$kind      = (string) get_post_meta( $post->ID, '_rapm_kind', true );
		$placement = (string) get_post_meta( $post->ID, '_rapm_placement', true );
		$back      = false;
		foreach ( self::spot( $kind, $placement ) as $other ) {
			if ( (int) $other->ID === (int) $post->ID || self::is_on( $other->ID ) || ! self::live_by_dates( $other ) ) {
				continue;
			}
			$end = (string) get_post_meta( $other->ID, '_rapm_ends_at', true );
			if ( '' === $end ) {
				return 0; // Something with no end date is showing: back when that's switched off.
			}
			try {
				$t = ( new DateTimeImmutable( $end, wp_timezone() ) )->getTimestamp();
			} catch ( Exception $e ) {
				return 0;
			}
			$back = false === $back ? $t : max( $back, $t );
		}
		return $back;
	}

	/**
	 * "This spot will be empty after Nov 2": every promotion still to show
	 * here has an end date and none is between-sales, so once the last one
	 * ends the spot disappears from the page. Returns that end time, or
	 * false when something keeps the spot filled.
	 *
	 * @return false|int
	 */
	public static function empty_after( $posts ) {
		$last = false;
		$now  = time();
		foreach ( $posts as $post ) {
			if ( 'publish' !== $post->post_status ) {
				continue;
			}
			$end = (string) get_post_meta( $post->ID, '_rapm_ends_at', true );
			if ( self::is_on( $post->ID ) || '' === $end ) {
				return false;
			}
			try {
				$t = ( new DateTimeImmutable( $end, wp_timezone() ) )->getTimestamp();
			} catch ( Exception $e ) {
				return false;
			}
			if ( $t > $now ) {
				$last = false === $last ? $t : max( $last, $t );
			}
		}
		return $last;
	}
}
