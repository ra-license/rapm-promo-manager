<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Keeps a link-sourced image (RAPM_Link_Source) up to date automatically —
 * re-checks every linked slot on a recurring schedule and swaps in the new
 * picture only when the source file actually changed, so a stable source
 * doesn't clutter the Media Library with an identical attachment every
 * run. A failed check never touches the last-known-good picture; it only
 * records what went wrong (surfaced in the admin list, see
 * RAPM_Admin_List) and, on the first failure, emails the site admin —
 * since a silently-broken live-synced promotion, invisible until someone
 * happens to check, is exactly the failure mode this plugin exists to
 * prevent.
 */
class RAPM_Sync {

	const HOOK = 'rapm_sync_linked_images';

	public static function schedule() {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time(), apply_filters( 'rapm_sync_interval', 'hourly' ), self::HOOK );
		}
	}

	public static function unschedule() {
		wp_clear_scheduled_hook( self::HOOK );
	}

	public static function run() {
		$kinds = RAPM_Slots::kinds();
		$slots = RAPM_Slots::all();

		foreach ( array( 'desktop', 'mobile' ) as $which ) {
			$assets = get_posts(
				array(
					'post_type'      => 'rapm_asset',
					'post_status'    => 'publish',
					'posts_per_page' => -1,
					'fields'         => 'ids',
					'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
						array(
							'key'   => '_rapm_image_' . $which . '_source',
							'value' => 'link',
						),
					),
				)
			);

			foreach ( $assets as $asset_id ) {
				self::sync_one( $asset_id, $which, $kinds, $slots );
			}
		}
	}

	/**
	 * Checks one asset's linked pictures right away, instead of waiting for
	 * the hourly run — behind the "Check link now" button on the Add/Edit
	 * Asset screen, so a swap in a Drive folder can be shown live.
	 */
	public static function sync_asset( $asset_id ) {
		$kinds = RAPM_Slots::kinds();
		$slots = RAPM_Slots::all();
		foreach ( array( 'desktop', 'mobile' ) as $which ) {
			if ( 'link' === get_post_meta( $asset_id, '_rapm_image_' . $which . '_source', true ) ) {
				self::sync_one( $asset_id, $which, $kinds, $slots );
			}
		}
	}

	public static function handle_check_now() {
		$asset_id = isset( $_GET['asset_id'] ) ? absint( $_GET['asset_id'] ) : 0;
		if ( ! $asset_id || ! current_user_can( 'edit_post', $asset_id ) || 'rapm_asset' !== get_post_type( $asset_id ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'rapm' ) );
		}
		check_admin_referer( 'rapm_check_link_now_' . $asset_id );
		$before = array(
			(int) get_post_meta( $asset_id, '_rapm_image_desktop_id', true ),
			(int) get_post_meta( $asset_id, '_rapm_image_mobile_id', true ),
		);
		self::sync_asset( $asset_id );
		$after   = array(
			(int) get_post_meta( $asset_id, '_rapm_image_desktop_id', true ),
			(int) get_post_meta( $asset_id, '_rapm_image_mobile_id', true ),
		);
		$outcome = $before !== $after ? 'updated' : 'same';
		wp_safe_redirect( admin_url( 'edit.php?post_type=rapm_asset&page=rapm-add-asset&edit=' . $asset_id . '&rapm_checked=' . $outcome ) );
		exit;
	}

	private static function sync_one( $asset_id, $which, $kinds, $slots ) {
		$url = get_post_meta( $asset_id, '_rapm_image_' . $which . '_url', true );
		if ( ! $url ) {
			return;
		}
		$kind_key = get_post_meta( $asset_id, '_rapm_kind', true ) ?: 'hero'; // phpcs:ignore
		// 1.43.0: per spot, so a split Slider's pictures are checked at 4:3.
		$kind     = RAPM_Slots::kind_for( isset( $kinds[ $kind_key ] ) ? $kind_key : 'hero', get_post_meta( $asset_id, '_rapm_placement', true ) ?: 'default' ); // phpcs:ignore
		$slot     = $slots[ $kind[ $which ] ];
		$old_id   = (int) get_post_meta( $asset_id, '_rapm_image_' . $which . '_id', true );

		// 1.58.2: a one-picture link of a different shape is trimmed the way the person picked.
		$anchor = (string) get_post_meta( $asset_id, '_rapm_image_' . $which . '_crop_anchor', true );
		$result = RAPM_Link_Source::fetch_validate_fingerprinted( $url, $slot, $asset_id, $which, $anchor );

		// 1.72.0: a coupon whose row changed card shape keeps its linked
		// picture if it's another coupon shape: the page trims it around
		// "What stays in view", and "Ready to go live" says so. No false
		// "link failed" email for a shape R&A chose on purpose.
		if ( 'coupon' === $kind_key && '' === $anchor && is_wp_error( $result ) && 'rapm_wrong_dimensions' === $result->get_error_code() ) {
			foreach ( RAPM_Spots::COUPON_SHAPES as $shape ) {
				if ( $shape['slot'] !== $kind[ $which ] && isset( $slots[ $shape['slot'] ] ) ) {
					$retry = RAPM_Link_Source::fetch_validate_fingerprinted( $url, $slots[ $shape['slot'] ], $asset_id, $which, '' );
					if ( ! is_wp_error( $retry ) ) {
						$result = $retry;
						break;
					}
				}
			}
		}

		if ( 'mobile' === $which && RAPM_Link_Source::is_folder_url( $url ) && RAPM_Link_Source::is_waiting_for_picture( $result ) ) {
			// No tall picture in the folder (yet) — not a failure; phones
			// keep showing whatever they showed before (the desktop picture
			// if there's never been a mobile one). No email.
			update_post_meta( $asset_id, '_rapm_image_' . $which . '_waiting', 1 );
			update_post_meta( $asset_id, '_rapm_image_' . $which . '_synced_at', current_time( 'mysql' ) );
			delete_post_meta( $asset_id, '_rapm_image_' . $which . '_sync_error' );
			return;
		}
		delete_post_meta( $asset_id, '_rapm_image_' . $which . '_waiting' );

		if ( is_wp_error( $result ) ) {
			$had_error_already = (bool) get_post_meta( $asset_id, '_rapm_image_' . $which . '_sync_error', true );
			update_post_meta( $asset_id, '_rapm_image_' . $which . '_sync_error', $result->get_error_message() );
			if ( ! $had_error_already ) {
				self::notify_failure( $asset_id, $which, $result->get_error_message() );
			}
			return;
		}

		$new_id    = $result['attachment_id'];
		$hash      = $result['hash'];
		$unchanged = $old_id && self::files_match( $old_id, $new_id );

		if ( $unchanged ) {
			wp_delete_attachment( $new_id, true );
			// 1.76.0: a picture that was live before approval records began
			// counts as approved, so nothing changes for it.
			if ( $hash && ! RAPM_Approvals::live_record( $asset_id, $which ) ) {
				RAPM_Approvals::record( $asset_id, $which, $hash, array( 'method' => 'already-live', 'by' => __( 'Promo Manager', 'rapm' ), 'file_id' => $result['file_id'], 'name' => $result['name'] ) );
				update_post_meta( $asset_id, '_rapm_image_' . $which . '_live_hash', $hash );
			}
		} elseif ( RAPM_Approvals::is_blocked( $asset_id, $which, $hash ) ) {
			// Someone took this picture down (Undo, Restore previous or
			// Reject): it stays down until a different file arrives.
			wp_delete_attachment( $new_id, true );
		} elseif ( 'hold' === RAPM_Approvals::mode( $asset_id ) && ! RAPM_Approvals::is_approved( $asset_id, $which, $hash ) ) {
			// 1.76.0 "Wait for approval": held, the live picture stays.
			if ( RAPM_Approvals::hold( $asset_id, $which, $new_id, $hash, $result['name'], $result['file_id'] ) ) {
				RAPM_Approvals::send_hold_notice( $asset_id, $which, $result['name'] );
			}
		} else {
			// Goes live: dropped in the client's own folder (the default),
			// or approved earlier. Recorded, the old picture kept for Undo,
			// and a receipt sent. Saving the promotion clears the cache for
			// the pages it shows on (RAPM_Cache, 1.28.0).
			RAPM_Changes::set_route( 'drive-sync', __( 'Hourly link check', 'rapm' ) );
			if ( ! RAPM_Approvals::is_approved( $asset_id, $which, $hash ) ) {
				RAPM_Approvals::record( $asset_id, $which, $hash, array( 'method' => 'drop-folder', 'by' => __( 'Client\'s folder', 'rapm' ), 'file_id' => $result['file_id'], 'name' => $result['name'] ) );
			}
			RAPM_Approvals::clear_pending( $asset_id, $which, true );
			RAPM_Approvals::swap_in( $asset_id, $which, $new_id, $hash );
			RAPM_Approvals::send_receipt( $asset_id, $which, $hash, $result['name'] );
		}

		update_post_meta( $asset_id, '_rapm_image_' . $which . '_synced_at', current_time( 'mysql' ) );
		delete_post_meta( $asset_id, '_rapm_image_' . $which . '_sync_error' );
	}

	private static function files_match( $attachment_id_a, $attachment_id_b ) {
		$path_a = get_attached_file( $attachment_id_a );
		$path_b = get_attached_file( $attachment_id_b );
		if ( ! $path_a || ! $path_b || ! file_exists( $path_a ) || ! file_exists( $path_b ) ) {
			return false;
		}
		return md5_file( $path_a ) === md5_file( $path_b );
	}

	private static function notify_failure( $asset_id, $which, $message ) {
		$admin_email = get_option( 'admin_email' );
		if ( ! $admin_email ) {
			return;
		}
		$title    = get_the_title( $asset_id );
		$edit_url = admin_url( 'edit.php?post_type=rapm_asset&page=rapm-add-asset&edit=' . $asset_id );
		wp_mail(
			$admin_email,
			sprintf(
				/* translators: %s: site name */
				__( '[%s] A linked promotion picture stopped updating', 'rapm' ),
				get_bloginfo( 'name' )
			),
			sprintf(
				/* translators: 1: desktop/mobile, 2: asset name, 3: error message, 4: edit link */
				__( "The %1\$s picture for \"%2\$s\" could not be updated from its link:\n\n%3\$s\n\nThe last picture that worked is still showing — nothing is broken on the live site. Fix the link here:\n%4\$s", 'rapm' ),
				'desktop' === $which ? __( 'desktop', 'rapm' ) : __( 'mobile', 'rapm' ),
				$title,
				$message,
				$edit_url
			)
		);
	}
}
