<?php
/**
 * Store time (1.69.0).
 *
 * Start and end times are typed in the promotion form as the store's own
 * clock ("Oct 31, 6:00 PM") and saved without a time zone (the form's
 * datetime-local field). Before 1.69.0 they went to the page as typed, and
 * each shopper's browser read them in its own time zone. Found Oct 14,
 * checking Kenna's Anniversary Sale at Kemper: a sale set to end at 6:00 PM
 * ended at 6 PM Eastern for an Eastern shopper, 7 PM Eastern for one in
 * Chicago and 9 PM Eastern for one in Los Angeles.
 *
 * Now each time goes to the page with the store's offset for that date
 * ("2026-10-31T18:00:00-04:00"), so every browser ends it at the same
 * moment. The browser still makes the decision (rapm-schedule.js), which
 * keeps working behind WP Rocket and the CDN. Daylight saving is handled,
 * because the offset is worked out for each date.
 *
 * Safe by default: a website still on WordPress's out-of-the-box UTC has
 * probably never had its time zone set, and 6 PM UTC is 2 PM Eastern. On
 * such a website times go out as before (each shopper's own clock), and
 * the form tells administrators to set the time zone in Settings › General.
 */

defined( 'ABSPATH' ) || exit;

class RAPM_Store_Time {

	/** Whether this website has a store time zone set (anything but plain UTC). */
	public static function is_set() {
		$name = wp_timezone_string();
		return ! in_array( $name, array( 'UTC', '+00:00', 'Etc/UTC', 'UTC+0', '' ), true );
	}

	/**
	 * A saved start or end time as it goes to the page: with the store's
	 * offset when the time zone is set, as saved otherwise. Unreadable
	 * values go out unchanged (the browser treats those as before).
	 */
	public static function attr( $local ) {
		$local = trim( (string) $local );
		if ( '' === $local || ! self::is_set() ) {
			return $local;
		}
		try {
			$dt = new DateTimeImmutable( $local, wp_timezone() );
		} catch ( Exception $e ) {
			return $local;
		}
		return $dt->format( 'Y-m-d\TH:i:sP' );
	}

	/** The store's time zone in words: "Eastern", "Central", … or its abbreviation. */
	public static function name() {
		try {
			$abbr = ( new DateTimeImmutable( 'now', wp_timezone() ) )->format( 'T' );
		} catch ( Exception $e ) {
			return '';
		}
		$us = array(
			'EST'  => __( 'Eastern', 'rapm' ),
			'EDT'  => __( 'Eastern', 'rapm' ),
			'CST'  => __( 'Central', 'rapm' ),
			'CDT'  => __( 'Central', 'rapm' ),
			'MST'  => __( 'Mountain', 'rapm' ),
			'MDT'  => __( 'Mountain', 'rapm' ),
			'PST'  => __( 'Pacific', 'rapm' ),
			'PDT'  => __( 'Pacific', 'rapm' ),
			'AKST' => __( 'Alaska', 'rapm' ),
			'AKDT' => __( 'Alaska', 'rapm' ),
			'HST'  => __( 'Hawaii', 'rapm' ),
		);
		return isset( $us[ $abbr ] ) ? $us[ $abbr ] : $abbr;
	}

	/** The line under the date fields in the promotion form. */
	public static function form_note() {
		if ( self::is_set() ) {
			/* translators: %s: a time zone name like "Eastern" */
			return sprintf( __( 'Times are the store’s time (%s), for every shopper.', 'rapm' ), self::name() );
		}
		return current_user_can( 'manage_options' )
			? __( 'This website’s time zone isn’t set, so each shopper’s own clock decides when it starts and stops. Set the store’s time zone in Settings › General.', 'rapm' )
			: __( 'This website’s time zone isn’t set, so each shopper’s own clock decides when it starts and stops. Ask R&A to set it.', 'rapm' );
	}
}
