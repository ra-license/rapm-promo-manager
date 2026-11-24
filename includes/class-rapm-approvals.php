<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Drive drops that just work, with a record and an undo (1.76.0).
 *
 * Phil, Nov 24 2026: "a client needs to know they can drop something in the
 * folder and it'll just work." So a linked folder still goes live on its
 * own by default. What this adds around it:
 *
 * - A record of every picture that goes live from a link: its fingerprint
 *   (SHA-256 of the file as downloaded), the Drive file, when, and how it
 *   was approved ("dropped in the client's folder", "added in Promo
 *   Manager by …", "approved in Promo Manager by …", "on the client's
 *   behalf, by email: …").
 * - The picture it replaced is kept (one back), so it can be put back.
 * - A receipt email each time a linked picture goes live, with an Undo
 *   button that works without signing in. The link is signed, lasts 7
 *   days, works once, and can only put back the picture that was there
 *   before this one.
 * - An optional "Wait for approval" setting per promotion, for folders that
 *   aren't the client's own (designer working folders, intake folders):
 *   new pictures are held, shown on the promotion's screen, and go live
 *   only when someone who can edit the promotion approves them.
 *
 * Meta, per picture ($which = desktop|mobile):
 *   _rapm_image_{which}_approvals    list of approval records (newest last)
 *   _rapm_image_{which}_live_hash    fingerprint of the picture live now
 *   _rapm_image_{which}_previous_id  the attachment it replaced
 *   _rapm_image_{which}_pending_*    a held picture: id, hash, name, file, found_at
 *   _rapm_image_{which}_undo_used    fingerprints whose undo link was used
 * Per promotion:
 *   _rapm_approval_mode              'auto' (default) or 'hold'
 */
class RAPM_Approvals {

	const KEEP          = 25;
	const UNDO_DAYS     = 7;
	const MODE_META     = '_rapm_approval_mode';
	const NONCE         = 'rapm_approval_';

	/** How a picture was approved, in words people read. */
	public static function methods() {
		return array(
			'drop-folder'          => __( 'Added to the client\'s folder (goes live automatically)', 'rapm' ),
			'already-live'         => __( 'Already live when approval records began (1.76.0)', 'rapm' ),
			'added-in-promo-manager' => __( 'Added in Promo Manager', 'rapm' ),
			'approved-in-promo-manager' => __( 'Approved in Promo Manager', 'rapm' ),
			'on-behalf'            => __( 'Approved by R&A on the client\'s behalf', 'rapm' ),
		);
	}

	/** Ways a client can give approval that R&A records for them. */
	public static function on_behalf_ways() {
		return array(
			'email'     => __( 'by email', 'rapm' ),
			'phone'     => __( 'by phone', 'rapm' ),
			'text'      => __( 'by text message', 'rapm' ),
			'ticket'    => __( 'in a help desk ticket', 'rapm' ),
			'ziflow'    => __( 'in ZiFlow', 'rapm' ),
			'in-person' => __( 'in person', 'rapm' ),
		);
	}

	public static function init() {
		add_action( 'admin_post_rapm_approve_pending', array( __CLASS__, 'handle_approve' ) );
		add_action( 'admin_post_rapm_reject_pending', array( __CLASS__, 'handle_reject' ) );
		add_action( 'admin_post_rapm_restore_previous', array( __CLASS__, 'handle_restore' ) );
		// The receipt's Undo link works signed in or not ("no double login").
		add_action( 'admin_post_rapm_undo', array( __CLASS__, 'handle_undo' ) );
		add_action( 'admin_post_nopriv_rapm_undo', array( __CLASS__, 'handle_undo' ) );
	}

	/* ------------------------------------------------------------------
	 * Fingerprints and the record
	 * ---------------------------------------------------------------- */

	public static function fingerprint( $path ) {
		return ( $path && file_exists( $path ) ) ? (string) hash_file( 'sha256', $path ) : '';
	}

	private static function key( $which, $name ) {
		return '_rapm_image_' . ( 'mobile' === $which ? 'mobile' : 'desktop' ) . '_' . $name;
	}

	/** 'auto' (goes live on its own) or 'hold' (waits for approval). */
	public static function mode( $asset_id ) {
		return 'hold' === get_post_meta( $asset_id, self::MODE_META, true ) ? 'hold' : 'auto';
	}

	public static function approvals( $asset_id, $which ) {
		$list = get_post_meta( $asset_id, self::key( $which, 'approvals' ), true );
		return is_array( $list ) ? $list : array();
	}

	public static function is_approved( $asset_id, $which, $hash ) {
		if ( ! $hash ) {
			return false;
		}
		foreach ( self::approvals( $asset_id, $which ) as $a ) {
			if ( isset( $a['hash'] ) && hash_equals( (string) $a['hash'], $hash ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Adds an approval record. $how: method (a key of methods()), via (a key
	 * of on_behalf_ways(), for on-behalf), proof (note or link), by (words,
	 * when nobody is signed in), file_id, name.
	 */
	public static function record( $asset_id, $which, $hash, array $how ) {
		if ( ! $asset_id || ! $hash ) {
			return;
		}
		$user  = wp_get_current_user();
		$list  = self::approvals( $asset_id, $which );
		$entry = array(
			'hash'    => $hash,
			'at'      => time(),
			'user_id' => (int) $user->ID,
			'by'      => $user->ID ? $user->display_name : ( isset( $how['by'] ) ? sanitize_text_field( $how['by'] ) : '' ),
			'method'  => isset( $how['method'] ) && isset( self::methods()[ $how['method'] ] ) ? $how['method'] : 'drop-folder',
			'via'     => isset( $how['via'] ) && isset( self::on_behalf_ways()[ $how['via'] ] ) ? $how['via'] : '',
			'proof'   => isset( $how['proof'] ) ? sanitize_textarea_field( $how['proof'] ) : '',
			'file_id' => isset( $how['file_id'] ) ? sanitize_text_field( $how['file_id'] ) : '',
			'name'    => isset( $how['name'] ) ? sanitize_text_field( $how['name'] ) : '',
		);
		$list[] = $entry;
		if ( count( $list ) > self::KEEP ) {
			$list = array_slice( $list, -self::KEEP );
		}
		update_post_meta( $asset_id, self::key( $which, 'approvals' ), $list );
		if ( 'already-live' !== $entry['method'] && class_exists( 'RAPM_Changes' ) ) {
			RAPM_Changes::event( $asset_id, 'approved', array(
				array(
					'field' => 'image_' . $which,
					'from'  => '',
					'to'    => self::describe( $entry ),
				),
			) );
		}
	}

	/** One line about an approval, e.g. "Approved by R&A on the client's behalf (by email), by Phil: ticket #1639". */
	public static function describe( array $a ) {
		$methods = self::methods();
		$ways    = self::on_behalf_ways();
		$text    = isset( $methods[ $a['method'] ] ) ? $methods[ $a['method'] ] : $a['method'];
		if ( ! empty( $a['via'] ) && isset( $ways[ $a['via'] ] ) ) {
			$text .= ' (' . $ways[ $a['via'] ] . ')';
		}
		if ( ! empty( $a['by'] ) ) {
			/* translators: %s: a person's name */
			$text .= ', ' . sprintf( __( 'by %s', 'rapm' ), $a['by'] );
		}
		if ( ! empty( $a['proof'] ) ) {
			$text .= ': ' . $a['proof'];
		}
		return $text;
	}

	/** The record for the picture that's live now, or null. */
	public static function live_record( $asset_id, $which ) {
		$hash = (string) get_post_meta( $asset_id, self::key( $which, 'live_hash' ), true );
		if ( ! $hash ) {
			return null;
		}
		foreach ( array_reverse( self::approvals( $asset_id, $which ) ) as $a ) {
			if ( isset( $a['hash'] ) && hash_equals( (string) $a['hash'], $hash ) ) {
				return $a;
			}
		}
		return null;
	}

	/* ------------------------------------------------------------------
	 * Swapping pictures, keeping one back
	 * ---------------------------------------------------------------- */

	/**
	 * Puts $new_id live for $which. The picture it replaces is kept as the
	 * previous one (the one before that is deleted, as before 1.76.0), so
	 * Undo and "Restore previous" can put it back. Saving the promotion
	 * clears its pages from the cache (RAPM_Cache).
	 */
	public static function swap_in( $asset_id, $which, $new_id, $hash = '' ) {
		$old_id  = (int) get_post_meta( $asset_id, self::key( $which, 'id' ), true );
		$prev_id = (int) get_post_meta( $asset_id, self::key( $which, 'previous_id' ), true );
		$old_hash = (string) get_post_meta( $asset_id, self::key( $which, 'live_hash' ), true );

		update_post_meta( $asset_id, self::key( $which, 'id' ), (int) $new_id );
		if ( $old_id && $old_id !== (int) $new_id ) {
			if ( $prev_id && $prev_id !== $old_id && $prev_id !== (int) $new_id ) {
				wp_delete_attachment( $prev_id, true );
			}
			update_post_meta( $asset_id, self::key( $which, 'previous_id' ), $old_id );
			update_post_meta( $asset_id, self::key( $which, 'previous_hash' ), $old_hash );
		}
		if ( $hash ) {
			update_post_meta( $asset_id, self::key( $which, 'live_hash' ), $hash );
		} else {
			delete_post_meta( $asset_id, self::key( $which, 'live_hash' ) );
		}
		wp_update_post( array( 'ID' => $asset_id ) );
	}

	public static function previous_id( $asset_id, $which ) {
		$id = (int) get_post_meta( $asset_id, self::key( $which, 'previous_id' ), true );
		return ( $id && get_post( $id ) ) ? $id : 0;
	}

	/**
	 * Puts the previous picture back. The one that was live becomes the
	 * previous one, so a restore can itself be undone. While a promotion is
	 * linked to a folder, the picture that was taken down is also marked so
	 * the hourly check doesn't put it straight back up.
	 */
	public static function restore_previous( $asset_id, $which ) {
		$prev_id = self::previous_id( $asset_id, $which );
		if ( ! $prev_id ) {
			return false;
		}
		$live_hash = (string) get_post_meta( $asset_id, self::key( $which, 'live_hash' ), true );
		$prev_hash = (string) get_post_meta( $asset_id, self::key( $which, 'previous_hash' ), true );
		if ( $live_hash ) {
			self::block( $asset_id, $which, $live_hash );
		}
		self::swap_in( $asset_id, $which, $prev_id, $prev_hash );
		return true;
	}

	/** Fingerprints someone took down: the hourly check won't put them back up. */
	public static function block( $asset_id, $which, $hash ) {
		$list   = (array) get_post_meta( $asset_id, self::key( $which, 'blocked' ), true );
		$list[] = $hash;
		update_post_meta( $asset_id, self::key( $which, 'blocked' ), array_slice( array_values( array_unique( array_filter( $list ) ) ), -self::KEEP ) );
	}

	public static function is_blocked( $asset_id, $which, $hash ) {
		return $hash && in_array( $hash, (array) get_post_meta( $asset_id, self::key( $which, 'blocked' ), true ), true );
	}

	/* ------------------------------------------------------------------
	 * Held pictures ("Wait for approval")
	 * ---------------------------------------------------------------- */

	public static function pending( $asset_id, $which ) {
		$id = (int) get_post_meta( $asset_id, self::key( $which, 'pending_id' ), true );
		if ( ! $id || ! get_post( $id ) ) {
			return null;
		}
		return array(
			'id'       => $id,
			'hash'     => (string) get_post_meta( $asset_id, self::key( $which, 'pending_hash' ), true ),
			'name'     => (string) get_post_meta( $asset_id, self::key( $which, 'pending_name' ), true ),
			'file_id'  => (string) get_post_meta( $asset_id, self::key( $which, 'pending_file' ), true ),
			'found_at' => (int) get_post_meta( $asset_id, self::key( $which, 'pending_found_at' ), true ),
		);
	}

	public static function has_pending( $asset_id ) {
		return (bool) ( self::pending( $asset_id, 'desktop' ) || self::pending( $asset_id, 'mobile' ) );
	}

	/** Holds a picture. Only the newest held picture is kept. Returns true when it's new. */
	public static function hold( $asset_id, $which, $attachment_id, $hash, $name, $file_id ) {
		$current = self::pending( $asset_id, $which );
		if ( $current && $current['hash'] === $hash ) {
			wp_delete_attachment( $attachment_id, true ); // Same file, already held.
			return false;
		}
		self::clear_pending( $asset_id, $which, true );
		update_post_meta( $asset_id, self::key( $which, 'pending_id' ), (int) $attachment_id );
		update_post_meta( $asset_id, self::key( $which, 'pending_hash' ), $hash );
		update_post_meta( $asset_id, self::key( $which, 'pending_name' ), $name );
		update_post_meta( $asset_id, self::key( $which, 'pending_file' ), $file_id );
		update_post_meta( $asset_id, self::key( $which, 'pending_found_at' ), time() );
		return true;
	}

	public static function clear_pending( $asset_id, $which, $delete_attachment ) {
		$current = self::pending( $asset_id, $which );
		if ( $current && $delete_attachment ) {
			wp_delete_attachment( $current['id'], true );
		}
		foreach ( array( 'pending_id', 'pending_hash', 'pending_name', 'pending_file', 'pending_found_at' ) as $k ) {
			delete_post_meta( $asset_id, self::key( $which, $k ) );
		}
	}

	/* ------------------------------------------------------------------
	 * Emails
	 * ---------------------------------------------------------------- */

	/** Who gets receipts: Settings › "Send receipts to", else the site admin. */
	public static function recipients() {
		$raw  = class_exists( 'RAPM_Admin_Settings' ) ? (string) RAPM_Admin_Settings::get( 'receipt_emails' ) : '';
		$list = array_filter( array_map( 'sanitize_email', preg_split( '/[\s,;]+/', $raw ) ), 'is_email' );
		if ( ! $list && get_option( 'admin_email' ) ) {
			$list = array( get_option( 'admin_email' ) );
		}
		return array_values( array_unique( $list ) );
	}

	private static function where( $asset_id ) {
		$kind      = (string) get_post_meta( $asset_id, '_rapm_kind', true );
		$placement = (string) get_post_meta( $asset_id, '_rapm_placement', true );
		return ( class_exists( 'RAPM_Spots' ) && $kind ) ? RAPM_Spots::name( $kind, $placement ? $placement : 'default' ) : '';
	}

	private static function which_words( $which ) {
		return 'mobile' === $which ? __( 'phone picture', 'rapm' ) : __( 'computer picture', 'rapm' );
	}

	public static function edit_url( $asset_id ) {
		return admin_url( 'edit.php?post_type=rapm_asset&page=rapm-add-asset&edit=' . (int) $asset_id );
	}

	/** "A new picture is live", with the Undo link. */
	public static function send_receipt( $asset_id, $which, $hash, $name ) {
		$to = self::recipients();
		if ( ! $to ) {
			return;
		}
		$title = get_the_title( $asset_id );
		$where = self::where( $asset_id );
		$image = wp_get_attachment_url( (int) get_post_meta( $asset_id, self::key( $which, 'id' ), true ) );
		$lines = array(
			/* translators: 1: computer/phone picture, 2: promotion name */
			sprintf( __( 'A new %1$s for "%2$s" is live on %3$s.', 'rapm' ), self::which_words( $which ), $title, home_url( '/' ) ),
			'',
		);
		if ( $where ) {
			/* translators: %s: spot name */
			$lines[] = sprintf( __( 'Where it shows: %s', 'rapm' ), $where );
		}
		if ( $name ) {
			/* translators: %s: file name */
			$lines[] = sprintf( __( 'File: %s', 'rapm' ), $name );
		}
		/* translators: %s: date and time */
		$lines[] = sprintf( __( 'Went live: %s', 'rapm' ), wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) );
		if ( $image ) {
			/* translators: %s: link */
			$lines[] = sprintf( __( 'See the picture: %s', 'rapm' ), $image );
		}
		$lines[] = '';
		if ( self::previous_id( $asset_id, $which ) ) {
			$lines[] = __( 'Not right? This link puts the previous picture back (no sign-in needed, works once, for 7 days):', 'rapm' );
			$lines[] = self::undo_url( $asset_id, $which, $hash );
		} else {
			$lines[] = __( 'This is the first picture for this promotion, so there is nothing earlier to put back. To take it down, reply to this email or open a help desk ticket.', 'rapm' );
		}
		$lines[] = '';
		/* translators: %s: link */
		$lines[] = sprintf( __( 'R&A: manage it here: %s', 'rapm' ), self::edit_url( $asset_id ) );

		wp_mail(
			$to,
			/* translators: 1: site name, 2: promotion name */
			sprintf( __( '[%1$s] New picture live: %2$s', 'rapm' ), get_bloginfo( 'name' ), $title ),
			implode( "\n", $lines )
		);
	}

	/** "A new picture is waiting for approval." */
	public static function send_hold_notice( $asset_id, $which, $name ) {
		$to = self::recipients();
		if ( ! $to ) {
			return;
		}
		$title = get_the_title( $asset_id );
		wp_mail(
			$to,
			/* translators: 1: site name, 2: promotion name */
			sprintf( __( '[%1$s] Waiting for approval: %2$s', 'rapm' ), get_bloginfo( 'name' ), $title ),
			implode(
				"\n",
				array(
					/* translators: 1: computer/phone picture, 2: promotion name, 3: file name */
					sprintf( __( 'A new %1$s for "%2$s" arrived in its folder (%3$s). This promotion is set to wait for approval, so the current picture is still showing.', 'rapm' ), self::which_words( $which ), $title, $name ? $name : __( 'no name', 'rapm' ) ),
					'',
					/* translators: %s: link */
					sprintf( __( 'Look at it and approve or reject it here: %s', 'rapm' ), self::edit_url( $asset_id ) ),
				)
			)
		);
	}

	/* ------------------------------------------------------------------
	 * The signed Undo link
	 * ---------------------------------------------------------------- */

	private static function sign( $asset_id, $which, $hash, $expires ) {
		return hash_hmac( 'sha256', 'rapm-undo|' . (int) $asset_id . '|' . $which . '|' . $hash . '|' . (int) $expires, wp_salt( 'auth' ) );
	}

	public static function undo_url( $asset_id, $which, $hash ) {
		$expires = time() + self::UNDO_DAYS * DAY_IN_SECONDS;
		return add_query_arg(
			array(
				'action' => 'rapm_undo',
				'a'      => (int) $asset_id,
				'w'      => 'mobile' === $which ? 'mobile' : 'desktop',
				'h'      => $hash,
				'e'      => $expires,
				's'      => self::sign( $asset_id, $which, $hash, $expires ),
			),
			admin_url( 'admin-post.php' )
		);
	}

	/**
	 * Checks an Undo link. Returns array( asset_id, which, hash ) or a
	 * WP_Error saying why it can't be used.
	 */
	private static function check_undo( $args ) {
		$asset_id = isset( $args['a'] ) ? absint( $args['a'] ) : 0;
		$which    = isset( $args['w'] ) && 'mobile' === $args['w'] ? 'mobile' : 'desktop';
		$hash     = isset( $args['h'] ) ? preg_replace( '/[^0-9a-f]/', '', strtolower( (string) $args['h'] ) ) : '';
		$expires  = isset( $args['e'] ) ? absint( $args['e'] ) : 0;
		$sig      = isset( $args['s'] ) ? preg_replace( '/[^0-9a-f]/', '', strtolower( (string) $args['s'] ) ) : '';

		if ( ! $asset_id || 64 !== strlen( $hash ) || ! $sig || ! hash_equals( self::sign( $asset_id, $which, $hash, $expires ), $sig ) ) {
			return new WP_Error( 'rapm_undo_bad', __( 'This undo link isn\'t valid. Please contact R&A Marketing.', 'rapm' ) );
		}
		if ( $expires < time() ) {
			return new WP_Error( 'rapm_undo_old', __( 'This undo link has expired (they last 7 days). Please contact R&A Marketing to change the picture.', 'rapm' ) );
		}
		if ( 'rapm_asset' !== get_post_type( $asset_id ) ) {
			return new WP_Error( 'rapm_undo_gone', __( 'This promotion no longer exists.', 'rapm' ) );
		}
		$used = (array) get_post_meta( $asset_id, self::key( $which, 'undo_used' ), true );
		if ( in_array( $hash, $used, true ) ) {
			return new WP_Error( 'rapm_undo_used', __( 'This undo link has already been used.', 'rapm' ) );
		}
		$live = (string) get_post_meta( $asset_id, self::key( $which, 'live_hash' ), true );
		if ( ! $live || ! hash_equals( $live, $hash ) ) {
			return new WP_Error( 'rapm_undo_moved_on', __( 'The picture this link was for is no longer the one showing, so there\'s nothing to undo.', 'rapm' ) );
		}
		if ( ! self::previous_id( $asset_id, $which ) ) {
			return new WP_Error( 'rapm_undo_nothing', __( 'There is no earlier picture to put back.', 'rapm' ) );
		}
		return array( $asset_id, $which, $hash );
	}

	/** GET shows a confirm page; POST (with the same signed link) puts the previous picture back. */
	public static function handle_undo() {
		// phpcs:disable WordPress.Security.NonceVerification -- the signed link is the check.
		$args    = wp_unslash( 'POST' === $_SERVER['REQUEST_METHOD'] ? $_POST : $_GET );
		$checked = self::check_undo( $args );
		$heading = __( 'Undo a new picture', 'rapm' );
		if ( is_wp_error( $checked ) ) {
			wp_die( esc_html( $checked->get_error_message() ), esc_html( $heading ), array( 'response' => 410 ) );
		}
		list( $asset_id, $which, $hash ) = $checked;
		$title = get_the_title( $asset_id );

		if ( 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
			$fields = '';
			foreach ( array( 'action', 'a', 'w', 'h', 'e', 's' ) as $k ) {
				$fields .= '<input type="hidden" name="' . esc_attr( $k ) . '" value="' . esc_attr( isset( $args[ $k ] ) ? $args[ $k ] : '' ) . '">';
			}
			$prev = wp_get_attachment_image( self::previous_id( $asset_id, $which ), 'medium' );
			wp_die(
				'<p>' . esc_html(
					sprintf(
						/* translators: 1: computer/phone picture, 2: promotion name */
						__( 'Put the previous %1$s back on "%2$s"?', 'rapm' ),
						self::which_words( $which ),
						$title
					)
				) . '</p>' . ( $prev ? '<p>' . $prev . '</p>' : '' )
				. '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">' . $fields
				. '<p><button type="submit" class="button button-primary">' . esc_html__( 'Yes, put the previous picture back', 'rapm' ) . '</button></p></form>',
				esc_html( $heading ),
				array( 'response' => 200 )
			);
		}
		// phpcs:enable

		$used   = (array) get_post_meta( $asset_id, self::key( $which, 'undo_used' ), true );
		$used[] = $hash;
		update_post_meta( $asset_id, self::key( $which, 'undo_used' ), array_slice( $used, -self::KEEP ) );

		if ( class_exists( 'RAPM_Changes' ) ) {
			RAPM_Changes::set_route( 'undo-link', __( 'Undo link in a receipt email', 'rapm' ) );
		}
		self::restore_previous( $asset_id, $which );

		$to = self::recipients();
		if ( $to ) {
			wp_mail(
				$to,
				/* translators: 1: site name, 2: promotion name */
				sprintf( __( '[%1$s] Undone: %2$s', 'rapm' ), get_bloginfo( 'name' ), $title ),
				/* translators: 1: computer/phone picture, 2: promotion name, 3: link */
				sprintf( __( "Someone used the undo link: the previous %1\$s for \"%2\$s\" is back up. The picture that was taken down won't be put back by the hourly check.\n\nR&A: %3\$s", 'rapm' ), self::which_words( $which ), $title, self::edit_url( $asset_id ) )
			);
		}
		wp_die(
			'<p>' . esc_html__( 'Done. The previous picture is back up. It can take a few minutes to show everywhere. R&A Marketing has been told.', 'rapm' ) . '</p>',
			esc_html( $heading ),
			array( 'response' => 200 )
		);
	}

	/* ------------------------------------------------------------------
	 * Buttons on the promotion's screen
	 * ---------------------------------------------------------------- */

	private static function guard() {
		$asset_id = isset( $_POST['asset_id'] ) ? absint( $_POST['asset_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification -- checked next.
		$which    = isset( $_POST['which'] ) && 'mobile' === $_POST['which'] ? 'mobile' : 'desktop'; // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! $asset_id || 'rapm_asset' !== get_post_type( $asset_id ) || ! current_user_can( 'edit_post', $asset_id ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'rapm' ) );
		}
		check_admin_referer( self::NONCE . $asset_id );
		return array( $asset_id, $which );
	}

	private static function back( $asset_id, $note ) {
		wp_safe_redirect( add_query_arg( 'rapm_approval', $note, self::edit_url( $asset_id ) ) );
		exit;
	}

	public static function handle_approve() {
		list( $asset_id, $which ) = self::guard();
		$pending = self::pending( $asset_id, $which );
		if ( ! $pending ) {
			self::back( $asset_id, 'gone' );
		}
		// phpcs:disable WordPress.Security.NonceVerification -- guard() checked it.
		$who   = isset( $_POST['who'] ) ? sanitize_key( wp_unslash( $_POST['who'] ) ) : 'me';
		$proof = isset( $_POST['proof'] ) ? trim( sanitize_textarea_field( wp_unslash( $_POST['proof'] ) ) ) : '';
		// phpcs:enable
		$on_behalf = isset( self::on_behalf_ways()[ $who ] );
		if ( $on_behalf && '' === $proof ) {
			self::back( $asset_id, 'need-proof' );
		}
		if ( class_exists( 'RAPM_Changes' ) ) {
			RAPM_Changes::set_route( 'approval' );
		}
		self::record( $asset_id, $which, $pending['hash'], array(
			'method'  => $on_behalf ? 'on-behalf' : 'approved-in-promo-manager',
			'via'     => $on_behalf ? $who : '',
			'proof'   => $proof,
			'file_id' => $pending['file_id'],
			'name'    => $pending['name'],
		) );
		self::clear_pending( $asset_id, $which, false );
		self::swap_in( $asset_id, $which, $pending['id'], $pending['hash'] );
		self::send_receipt( $asset_id, $which, $pending['hash'], $pending['name'] );
		self::back( $asset_id, 'approved' );
	}

	public static function handle_reject() {
		list( $asset_id, $which ) = self::guard();
		$pending = self::pending( $asset_id, $which );
		if ( $pending ) {
			self::block( $asset_id, $which, $pending['hash'] );
			self::clear_pending( $asset_id, $which, true );
			if ( class_exists( 'RAPM_Changes' ) ) {
				RAPM_Changes::event( $asset_id, 'rejected', array(
					array( 'field' => 'image_' . $which, 'from' => '', 'to' => $pending['name'] ),
				) );
			}
		}
		self::back( $asset_id, 'rejected' );
	}

	public static function handle_restore() {
		list( $asset_id, $which ) = self::guard();
		if ( class_exists( 'RAPM_Changes' ) ) {
			RAPM_Changes::set_route( 'restore' );
		}
		self::back( $asset_id, self::restore_previous( $asset_id, $which ) ? 'restored' : 'nothing' );
	}

	/* ------------------------------------------------------------------
	 * On the Add/Edit Promotion screen
	 * ---------------------------------------------------------------- */

	/** The result of Approve, Reject or Restore, at the top of the screen. */
	public static function render_notice() {
		// phpcs:ignore WordPress.Security.NonceVerification -- only picks a message.
		$note  = isset( $_GET['rapm_approval'] ) ? sanitize_key( wp_unslash( $_GET['rapm_approval'] ) ) : '';
		$notes = array(
			'approved'   => array( 'success', __( 'Approved. The new picture is live, and a receipt was sent.', 'rapm' ) ),
			'rejected'   => array( 'success', __( 'Rejected. The current picture stays, and that file won\'t be picked up again.', 'rapm' ) ),
			'restored'   => array( 'success', __( 'The previous picture is back up. The one taken down won\'t be picked up again.', 'rapm' ) ),
			'nothing'    => array( 'warning', __( 'There is no earlier picture to put back.', 'rapm' ) ),
			'gone'       => array( 'warning', __( 'That picture is no longer waiting. Someone may have approved or rejected it already.', 'rapm' ) ),
			'need-proof' => array( 'error', __( 'To approve on the client\'s behalf, add the proof: what they said, or a link to the email or ticket.', 'rapm' ) ),
		);
		if ( isset( $notes[ $note ] ) ) {
			printf( '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>', esc_attr( $notes[ $note ][0] ), esc_html( $notes[ $note ][1] ) );
		}
	}

	/**
	 * Under a link: what's live and how it was approved, Restore previous,
	 * a held picture with Approve and Reject, and (once, under the computer
	 * picture) the choice of what happens when a new picture arrives. The
	 * buttons belong to small forms printed after the main form
	 * (render_forms()), since forms can't sit inside each other.
	 */
	public static function render_link_panel( $asset_id, $which, $show_mode ) {
		if ( $show_mode ) {
			$mode = self::mode( $asset_id );
			echo '<fieldset class="rapm-approval-mode" style="margin:10px 0;"><legend class="screen-reader-text">' . esc_html__( 'When a new picture lands in this folder', 'rapm' ) . '</legend>';
			echo '<p style="margin:0 0 4px;"><strong>' . esc_html__( 'When a new picture lands in this folder:', 'rapm' ) . '</strong></p>';
			echo '<label style="display:block;"><input type="radio" name="rapm_approval_mode" value="auto" ' . checked( 'auto', $mode, false ) . ' /> ' . esc_html__( 'It goes live automatically (the client\'s own folder)', 'rapm' ) . '</label>';
			echo '<label style="display:block;"><input type="radio" name="rapm_approval_mode" value="hold" ' . checked( 'hold', $mode, false ) . ' /> ' . esc_html__( 'It waits for approval here first', 'rapm' ) . '</label>';
			echo '</fieldset>';
			RAPM_Tip::hint(
				__( 'Every new picture that goes live is recorded, and a receipt with an Undo link is emailed.', 'rapm' ),
				__( 'Use "waits for approval" for folders that aren\'t the client\'s own, like a designer\'s working folder. Receipts go to the addresses in Settings › Receipts.', 'rapm' )
			);
		}

		$live = self::live_record( $asset_id, $which );
		if ( $live ) {
			echo '<p class="description">' . esc_html(
				sprintf(
					/* translators: 1: how it was approved, 2: date */
					__( 'Live now: %1$s, %2$s.', 'rapm' ),
					self::describe( $live ),
					wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $live['at'] )
				)
			) . '</p>';
		}
		if ( self::previous_id( $asset_id, $which ) ) {
			echo '<p><button type="submit" class="button" form="rapm-restore-' . esc_attr( $which ) . '">' . esc_html__( 'Restore previous picture', 'rapm' ) . '</button></p>';
		}

		$pending = self::pending( $asset_id, $which );
		if ( ! $pending ) {
			return;
		}
		$form = 'rapm-approve-' . $which;
		echo '<div class="rapm-held" style="margin:10px 0;padding:12px;border:1px solid #dba617;border-radius:6px;background:#fcf9e8;">';
		echo '<p style="margin-top:0;"><strong>' . esc_html__( 'Waiting for approval', 'rapm' ) . '</strong> ' . esc_html(
			sprintf(
				/* translators: 1: file name, 2: time ago */
				__( '%1$s arrived %2$s ago. The current picture is still showing.', 'rapm' ),
				$pending['name'] ? $pending['name'] : __( 'A new picture', 'rapm' ),
				human_time_diff( $pending['found_at'] )
			)
		) . '</p>';
		echo wp_get_attachment_image( $pending['id'], array( 360, 120 ), false, array( 'style' => 'display:block;margin-bottom:8px;border-radius:6px;max-width:100%;height:auto;' ) );
		echo '<p><label for="rapm-who-' . esc_attr( $which ) . '">' . esc_html__( 'Who approved it?', 'rapm' ) . '</label><br />';
		echo '<select id="rapm-who-' . esc_attr( $which ) . '" name="who" form="' . esc_attr( $form ) . '">';
		echo '<option value="me">' . esc_html__( 'I approve it', 'rapm' ) . '</option>';
		foreach ( self::on_behalf_ways() as $key => $words ) {
			/* translators: %s: e.g. "by email" */
			echo '<option value="' . esc_attr( $key ) . '">' . esc_html( sprintf( __( 'The client approved it %s', 'rapm' ), $words ) ) . '</option>';
		}
		echo '</select></p>';
		echo '<p><label for="rapm-proof-' . esc_attr( $which ) . '">' . esc_html__( 'Proof (needed when the client approved it): what they said, or a link to the email or ticket', 'rapm' ) . '</label><br />';
		echo '<textarea id="rapm-proof-' . esc_attr( $which ) . '" name="proof" rows="2" class="large-text" form="' . esc_attr( $form ) . '"></textarea></p>';
		echo '<p><button type="submit" class="button button-primary" form="' . esc_attr( $form ) . '">' . esc_html__( 'Approve and go live', 'rapm' ) . '</button> ';
		echo '<button type="submit" class="button" form="rapm-reject-' . esc_attr( $which ) . '">' . esc_html__( 'Reject', 'rapm' ) . '</button></p>';
		echo '</div>';
	}

	/** The small forms the panel's buttons submit. Printed after the main form. */
	public static function render_forms( $asset_id ) {
		if ( ! $asset_id ) {
			return;
		}
		$actions = array(
			'approve' => 'rapm_approve_pending',
			'reject'  => 'rapm_reject_pending',
			'restore' => 'rapm_restore_previous',
		);
		$nonce = wp_create_nonce( self::NONCE . $asset_id );
		foreach ( array( 'desktop', 'mobile' ) as $which ) {
			foreach ( $actions as $id => $action ) {
				echo '<form id="rapm-' . esc_attr( $id . '-' . $which ) . '" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" hidden>';
				echo '<input type="hidden" name="action" value="' . esc_attr( $action ) . '" />';
				echo '<input type="hidden" name="asset_id" value="' . (int) $asset_id . '" />';
				echo '<input type="hidden" name="which" value="' . esc_attr( $which ) . '" />';
				echo '<input type="hidden" name="_wpnonce" value="' . esc_attr( $nonce ) . '" />';
				echo '</form>';
			}
		}
	}

	/** Counts for the help desk check-in. */
	public static function summary() {
		$ids     = get_posts(
			array(
				'post_type'      => 'rapm_asset',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
					'relation' => 'OR',
					array( 'key' => '_rapm_image_desktop_source', 'value' => 'link' ),
					array( 'key' => '_rapm_image_mobile_source', 'value' => 'link' ),
				),
			)
		);
		$pending = 0;
		$swaps   = 0;
		$since   = time() - DAY_IN_SECONDS;
		foreach ( $ids as $id ) {
			foreach ( array( 'desktop', 'mobile' ) as $which ) {
				if ( self::pending( $id, $which ) ) {
					$pending++;
				}
				foreach ( self::approvals( $id, $which ) as $a ) {
					if ( (int) $a['at'] >= $since && 'already-live' !== $a['method'] ) {
						$swaps++;
					}
				}
			}
		}
		return array(
			'linked_promotions'  => count( $ids ),
			'pending_approvals'  => $pending,
			'linked_changes_24h' => $swaps,
		);
	}
}
