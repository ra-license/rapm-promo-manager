<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * "Ready to go live" checks (1.49.0, Design Center roadmap step 3, from
 * the "Quick checks" Phil approved in the Asset library mockup on
 * 2026-10-16). Each promotion gets a list of checks, each 'fail' (it won't
 * look or work right), 'warn' (worth fixing), 'info' (something to know)
 * or 'pass'. Each spot gets a schedule check: gaps in the next 30 days
 * when nothing is set to show.
 *
 * Only facts the plugin can actually measure are checked; anything that
 * needs a human eye (words over a photo, for one) says so instead of
 * guessing. Link checks are run on request ("Check links" in the
 * library), never on page load, and remembered on the promotion.
 */
class RAPM_Ready {

	const LINK_META = '_rapm_link_check';

	/** Phone width the size checks use (an iPhone 12–16 is 390, smaller ones 375). */
	const PHONE_PX = 375;

	/* ---- Colors ----------------------------------------------------------- */

	private static function luminance( $hex ) {
		$hex = ltrim( (string) $hex, '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		if ( ! preg_match( '/^[0-9a-f]{6}$/i', $hex ) ) {
			return null;
		}
		$c = array();
		foreach ( array( 0, 2, 4 ) as $i ) {
			$v   = hexdec( substr( $hex, $i, 2 ) ) / 255;
			$c[] = $v <= 0.03928 ? $v / 12.92 : pow( ( $v + 0.055 ) / 1.055, 2.4 );
		}
		return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
	}

	/** WCAG contrast ratio between two hex colors, or null. */
	public static function contrast( $a, $b ) {
		$la = self::luminance( $a );
		$lb = self::luminance( $b );
		if ( null === $la || null === $lb ) {
			return null;
		}
		return ( max( $la, $lb ) + 0.05 ) / ( min( $la, $lb ) + 0.05 );
	}

	/* ---- One promotion ---------------------------------------------------- */

	/**
	 * @param int   $id   Promotion ID.
	 * @param array $item The library's facts for it (RAPM_Library::item()).
	 * @return array List of array( 'level', 'text' ).
	 */
	public static function checks( $id, $item ) {
		$out  = array();
		$kind = $item['kind'];
		$add  = function ( $level, $text ) use ( &$out ) {
			$out[] = array( 'level' => $level, 'text' => $text );
		};

		// Turned off but dated to show: easy to miss.
		if ( ! $item['on'] && 'ended' !== $item['state'] && $item['end'] ) {
			$add( 'warn', __( 'Turned off, so it won\'t show on its dates. Turn it on if it should.', 'rapm' ) );
		}

		// Pictures: size, weight, link sync.
		$info  = RAPM_Slots::kind_for( $kind, (string) get_post_meta( $id, '_rapm_placement', true ) ?: 'default' ); // phpcs:ignore
		$pairs = array( 'desktop' => $item['desk'], 'mobile' => $item['phone'] );
		foreach ( $pairs as $which => $pic ) {
			$label = 'desktop' === $which ? __( 'Computer picture', 'rapm' ) : __( 'Phone picture', 'rapm' );
			if ( ! $pic ) {
				continue;
			}
			if ( $pic['error'] ) {
				/* translators: 1: Computer picture / Phone picture */
				$add( 'fail', sprintf( __( '%s: its Google Drive or Dropbox link failed. The last good copy is still showing.', 'rapm' ), $label ) );
			}
			$slot = RAPM_Slots::get( $info[ $which ] );
			if ( $slot && $pic['w'] ) {
				if ( RAPM_Slots::dimensions_match( $slot, $pic['w'], $pic['h'] ) ) {
					/* translators: 1: Computer picture / Phone picture, 2: size */
					$add( 'pass', sprintf( __( '%1$s is the right size (%2$s).', 'rapm' ), $label, $pic['w'] . '×' . $pic['h'] ) );
				} elseif ( RAPM_Slots::is_flexible( $slot ) ) {
					/* translators: 1: Computer picture / Phone picture, 2: width */
					$add( 'warn', sprintf( __( '%1$s is %2$d wide; at least %3$d wide is needed to look sharp.', 'rapm' ), $label, $pic['w'], (int) ( isset( $slot['min_width'] ) ? $slot['min_width'] : 0 ) ) );
				} elseif ( $pic['w'] < $slot['width'] * 0.98 && RAPM_Slots::aspect_ratio_matches( $slot, $pic['w'], $pic['h'] ) ) {
					/* translators: 1: Computer picture / Phone picture, 2: size, 3: needed size */
					$add( 'warn', sprintf( __( '%1$s is %2$s, smaller than the %3$s needed, so it will look soft on sharp screens.', 'rapm' ), $label, $pic['w'] . '×' . $pic['h'], (int) $slot['width'] . '×' . (int) $slot['height'] ) );
				} else {
					/* translators: 1: Computer picture / Phone picture, 2: size, 3: needed size */
					$add( 'fail', sprintf( __( '%1$s is %2$s, a different shape from the %3$s this spot needs, so it will be trimmed.', 'rapm' ), $label, $pic['w'] . '×' . $pic['h'], (int) $slot['width'] . '×' . (int) $slot['height'] ) );
				}
				if ( $pic['kb'] && $pic['kb'] > (int) $slot['max_kb'] ) {
					/* translators: 1: Computer picture / Phone picture, 2: file size, 3: limit */
					$add( 'warn', sprintf( __( '%1$s is %2$d KB; under %3$d KB keeps the page fast.', 'rapm' ), $label, $pic['kb'], (int) $slot['max_kb'] ) );
				}
			}
		}
		if ( ! $item['design'] && ! $item['desk'] ) {
			$add( 'fail', __( 'No picture, so it won\'t show anywhere.', 'rapm' ) );
		}
		if ( in_array( $kind, array( 'hero', 'fold_banner' ), true ) && ! $item['phone'] ) {
			$add( 'warn', __( 'No phone picture: phones show the computer picture fitted inside a tall box.', 'rapm' ) );
		}

		// Alt text: screen readers and search.
		if ( ! $item['design'] ) {
			if ( '' === trim( $item['alt'] ) ) {
				$add( 'warn', __( 'No "What\'s in the picture" text, so screen readers say nothing for it.', 'rapm' ) );
			} else {
				$add( 'pass', __( 'Has "What\'s in the picture" text for screen readers.', 'rapm' ) );
			}
		}

		// A designed promotion box: readability, size on phones, edges.
		if ( $item['design'] ) {
			foreach ( self::design_checks( $id ) as $c ) {
				$add( $c[0], $c[1] );
			}
		}

		// Product dots and the photo button near a phone's bottom edge,
		// where Safari's toolbar can cover them.
		if ( in_array( $kind, array( 'hero', 'fold_banner' ), true ) && $item['phone'] ) {
			$low = 0;
			foreach ( RAPM_Looks::sanitize_dots( get_post_meta( $id, '_rapm_dots', true ) ) as $dot ) {
				$my = isset( $dot['my'] ) ? $dot['my'] : $dot['y'];
				if ( $my > 92 ) {
					$low++;
				}
			}
			$btn = RAPM_Looks::sanitize_photo_btn( get_post_meta( $id, '_rapm_photo_btn', true ) );
			if ( $btn && ( isset( $btn['my'] ) ? $btn['my'] : $btn['y'] ) > 90 ) {
				$low++;
			}
			if ( $low ) {
				$add( 'warn', __( 'A product dot or the button sits at the very bottom of the phone picture, where the phone\'s browser bar can cover it.', 'rapm' ) );
			}
		}

		// Where it links.
		$linked_kinds = array( 'hero', 'fold_banner', 'promo_box', 'coupon', 'quick_links', 'marquee' );
		if ( '' === $item['link'] ) {
			if ( in_array( $kind, $linked_kinds, true ) ) {
				$add( 'info', __( 'Doesn\'t link anywhere, so clicking it does nothing.', 'rapm' ) );
			}
		} else {
			$check = get_post_meta( $id, self::LINK_META, true );
			if ( ! is_array( $check ) || ! isset( $check['url'] ) || $check['url'] !== $item['link'] ) {
				$add( 'info', __( 'Its link hasn\'t been checked yet. Use "Check links".', 'rapm' ) );
			} elseif ( $check['code'] >= 200 && $check['code'] < 400 ) {
				$add( 'pass', __( 'Its link works.', 'rapm' ) );
			} elseif ( in_array( (int) $check['code'], array( 401, 403, 429, 503 ), true ) || 0 === (int) $check['code'] ) {
				/* translators: %d: HTTP status */
				$add( 'info', sprintf( __( 'Its link couldn\'t be checked from the server (answer %d). Open it yourself to be sure.', 'rapm' ), (int) $check['code'] ) );
			} else {
				/* translators: %d: HTTP status, like 404 */
				$add( 'fail', sprintf( __( 'Its link goes to a page that doesn\'t work (%d).', 'rapm' ), (int) $check['code'] ) );
			}
		}
		return $out;
	}

	/**
	 * Checks for a promotion box designed on the canvas. Sizes use the
	 * same rules as the website (rapm-hero.css): a share of the square's
	 * width with a floor in pixels; on a phone the square is the screen's
	 * width. Widths of words are estimated from their length, so the edge
	 * check says "may".
	 */
	private static function design_checks( $id ) {
		$out    = array();
		$canvas = RAPM_Split::sanitize_canvas( get_post_meta( $id, '_rapm_canvas', true ) );
		$texts  = array(
			'headline' => (string) get_post_meta( $id, '_rapm_headline', true ),
			'sub'      => (string) get_post_meta( $id, '_rapm_subhead', true ),
			'btn'      => (string) get_post_meta( $id, '_rapm_cta_text', true ),
		);
		$names  = array(
			'headline' => __( 'Headline', 'rapm' ),
			'sub'      => __( 'Smaller line', 'rapm' ),
			'btn'      => __( 'Button', 'rapm' ),
			'icon'     => __( 'An icon', 'rapm' ),
			'img'      => __( 'A picture', 'rapm' ),
		);
		$floor  = array( 'headline' => 18, 'sub' => 13, 'btn' => 11, 'icon' => 16 );
		$unit   = self::PHONE_PX / 100;
		$bg     = $canvas['pic'] ? '' : ( $canvas['bg'] ? $canvas['bg'] : '#f3ead8' );
		$words  = 0;
		$edge   = array();
		foreach ( $canvas['items'] as $it ) {
			$k = $it['k'];
			if ( isset( $texts[ $k ] ) ) {
				if ( '' === trim( $texts[ $k ] ) ) {
					continue;
				}
				$words++;
				// Readability (WCAG AA: 4.5 to 1, or 3 to 1 for big words).
				$fg    = 'btn' === $k ? '#ffffff' : $it['c'];
				$back  = 'btn' === $k ? $it['c'] : $bg;
				$ratio = $back ? self::contrast( $fg, $back ) : null;
				$px    = max( $floor[ $k ], $it['s'] * $unit );
				$need  = ( 'headline' === $k || $px >= 24 ) ? 3 : 4.5;
				if ( null !== $ratio && $ratio < $need ) {
					/* translators: 1: Headline / Smaller line / Button, 2: contrast like 2.1, 3: needed like 4.5 */
					$out[] = array( 'fail', sprintf( __( '%1$s is hard to read: its color against what\'s behind it is %2$s to 1; at least %3$s to 1 is needed.', 'rapm' ), $names[ $k ], number_format_i18n( $ratio, 1 ), number_format_i18n( $need, 1 ) ) );
				}
				// Small on phones.
				if ( 'sub' === $k && $px < 14 ) {
					/* translators: %d: pixels */
					$out[] = array( 'warn', sprintf( __( 'Smaller line is only about %dpx on a phone; make it a little bigger.', 'rapm' ), (int) round( $px ) ) );
				}
				// May run past the edge: estimated width and height as a share of the square.
				$len = function_exists( 'mb_strlen' ) ? mb_strlen( $texts[ $k ] ) : strlen( $texts[ $k ] );
				$w   = 'btn' === $k ? $len * $it['s'] * 0.68 + $it['s'] * 2.8 : $len * $it['s'] * ( 'headline' === $k ? 0.62 : 0.5 );
				$lines = 'btn' === $k ? 1 : max( 1, (int) ceil( $w / 96 ) );
				$w   = min( $w, 96 );
				$h   = 'btn' === $k ? $it['s'] * 2.6 : $lines * $it['s'] * 1.15;
			} elseif ( 'icon' === $k ) {
				$w = $h = $it['s'];
			} elseif ( 'img' === $k ) {
				$meta = wp_get_attachment_metadata( $it['a'] );
				$w    = $it['s'];
				$h    = is_array( $meta ) && ! empty( $meta['width'] ) ? $it['s'] * $meta['height'] / $meta['width'] : $it['s'];
				if ( '' === trim( (string) get_post_meta( $it['a'], '_wp_attachment_image_alt', true ) ) ) {
					$out[] = array( 'warn', __( 'A picture on the design has no alt text in the Media Library.', 'rapm' ) );
				}
			} else {
				continue;
			}
			if ( $it['x'] - $w / 2 < 1 || $it['x'] + $w / 2 > 99 || $it['y'] - $h / 2 < 1 || $it['y'] + $h / 2 > 99 ) {
				$edge[ $k ] = $names[ $k ];
			}
		}
		if ( $edge ) {
			/* translators: %s: list of pieces */
			$out[] = array( 'warn', sprintf( __( 'May run past the edge of the square: %s. Check it in the design.', 'rapm' ), implode( ', ', array_unique( $edge ) ) ) );
		}
		if ( ! $words ) {
			$out[] = array( 'warn', __( 'The design has no words: type a headline so shoppers (and screen readers) know what it\'s about.', 'rapm' ) );
		}
		if ( $canvas['pic'] ) {
			$out[] = array( 'info', __( 'The words are on a picture, so how easy they are to read can\'t be measured. Check it by eye.', 'rapm' ) );
		} elseif ( $words && ! array_filter( $out, function ( $c ) { return 'fail' === $c[0]; } ) ) {
			$out[] = array( 'pass', __( 'Words are easy to read against the background.', 'rapm' ) );
		}
		return $out;
	}

	/** 'fail', 'warn' or 'ready' for a list of checks. */
	public static function verdict( $checks ) {
		$levels = wp_list_pluck( $checks, 'level' );
		if ( in_array( 'fail', $levels, true ) ) {
			return 'fail';
		}
		return in_array( 'warn', $levels, true ) ? 'warn' : 'ready';
	}

	/* ---- Spots: gaps in the schedule -------------------------------------- */

	/**
	 * For each spot that's on a page or has promotions: stretches of the
	 * next 30 days with nothing set to show. Returns spot key => list of
	 * plain-language problems.
	 */
	public static function spot_gaps( $items, $spots ) {
		$tz    = wp_timezone();
		$now   = time();
		$until = $now + 30 * DAY_IN_SECONDS;
		$by    = array();
		foreach ( $items as $it ) {
			if ( ! $it['on'] ) {
				continue;
			}
			$s = $it['start'] ? date_create( $it['start'], $tz ) : false;
			$e = $it['end'] ? date_create( $it['end'], $tz ) : false;
			$by[ $it['spot'] ][] = array( $s ? $s->getTimestamp() : 0, $e ? $e->getTimestamp() : PHP_INT_MAX );
		}
		$out = array();
		foreach ( $spots as $key => $spot ) {
			list( $kind, $placement ) = explode( '|', $key, 2 );
			$placed = (bool) RAPM_Spot_Usage::for_spot( $kind, $placement );
			if ( 'promo_box' === $kind && RAPM_Split::is_split( $placement ) ) {
				$placed = true;
			}
			if ( ! $placed ) {
				continue; // Not on any page: nothing for shoppers to miss.
			}
			$list = isset( $by[ $key ] ) ? $by[ $key ] : array();
			if ( ! $list ) {
				$out[ $key ][] = 'promo_box' === $kind
					? __( 'Nothing turned on in this promotion box, so its slider shows full width.', 'rapm' )
					: __( 'Nothing turned on, so this spot is empty on its pages.', 'rapm' );
				continue;
			}
			usort( $list, function ( $a, $b ) { return $a[0] <=> $b[0]; } );
			$cursor = $now;
			foreach ( $list as $range ) {
				if ( $range[1] <= $cursor ) {
					continue;
				}
				if ( $range[0] > $cursor + HOUR_IN_SECONDS && $cursor < $until ) {
					$out[ $key ][] = self::gap_text( $cursor, min( $range[0], $until ), $kind );
				}
				$cursor = max( $cursor, $range[1] );
				if ( $cursor >= $until ) {
					break;
				}
			}
			if ( $cursor < $until ) {
				$out[ $key ][] = self::gap_text( $cursor, null, $kind );
			}
		}
		return $out;
	}

	private static function gap_text( $from, $to, $kind ) {
		$fmt  = function ( $ts ) {
			return wp_date( 'M j, g:i a', $ts );
		};
		$what = 'promo_box' === $kind ? __( 'the slider goes full width', 'rapm' ) : __( 'nothing shows', 'rapm' );
		if ( null === $to ) {
			/* translators: 1: a date and time, 2: what happens */
			return sprintf( __( 'From %1$s on, %2$s: nothing is scheduled after that.', 'rapm' ), $fmt( $from ), $what );
		}
		/* translators: 1: a date and time, 2: a date and time, 3: what happens */
		return sprintf( __( 'From %1$s to %2$s, %3$s.', 'rapm' ), $fmt( $from ), $fmt( $to ), $what );
	}

	/* ---- Link checks ------------------------------------------------------ */

	/**
	 * Checks up to 8 promotions' links per request and remembers each
	 * answer on the promotion. A HEAD request first, then GET when a server
	 * refuses HEAD. Answers a firewall gives a server (401, 403, 429, 503)
	 * are reported as "couldn't check", not as broken.
	 */
	public static function ajax_check_links() {
		check_ajax_referer( RAPM_Library::NONCE, 'nonce' );
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Not allowed.', 'rapm' ) ), 403 );
		}
		$ids  = isset( $_POST['ids'] ) ? array_slice( array_map( 'absint', (array) wp_unslash( $_POST['ids'] ) ), 0, 8 ) : array();
		$done = array();
		foreach ( $ids as $id ) {
			if ( ! $id || 'rapm_asset' !== get_post_type( $id ) || ! current_user_can( 'edit_post', $id ) ) {
				continue;
			}
			$url = RAPM_Destination::resolve_url( get_post_meta( $id, '_rapm_destination_type', true ), get_post_meta( $id, '_rapm_destination_value', true ) );
			if ( ! $url ) {
				continue;
			}
			$args = array( 'timeout' => 8, 'redirection' => 5, 'user-agent' => 'RA Promo Manager link check (' . home_url( '/' ) . ')' );
			$res  = wp_safe_remote_head( $url, $args );
			$code = is_wp_error( $res ) ? 0 : (int) wp_remote_retrieve_response_code( $res );
			if ( in_array( $code, array( 0, 405, 501 ), true ) ) {
				$res  = wp_safe_remote_get( $url, $args + array( 'limit_response_size' => 65536 ) );
				$code = is_wp_error( $res ) ? 0 : (int) wp_remote_retrieve_response_code( $res );
			}
			update_post_meta( $id, self::LINK_META, array( 'url' => $url, 'code' => $code, 'at' => time() ) );
			$done[ $id ] = $code;
		}
		wp_send_json_success( array( 'checked' => $done ) );
	}
}
