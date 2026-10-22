<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fetches a promotional image from an outside link — a direct file URL, a
 * Google Drive share link to one file, or a Google Drive FOLDER link — and
 * runs it through the exact same dimension/format/size validation as a
 * direct upload (RAPM_Upload_Handler::validate_convert_sideload()), since
 * that validation is the one thing this plugin exists to guarantee and a
 * link-sourced image should never be held to a looser standard.
 *
 * Folder links exist so a client can change a promotion by just dropping a
 * new picture into a shared folder, under any file name: the newest picture
 * in the folder that fits the slot's shape wins. One folder can feed both
 * the desktop and mobile slot, since each slot only considers pictures of
 * its own shape (wide vs tall).
 */
class RAPM_Link_Source {

	/**
	 * Most candidates a single folder check will download while looking for
	 * one of the right shape — keeps an hourly sync cheap even when a folder
	 * collects a long history of old pictures.
	 */
	const FOLDER_MAX_CANDIDATES = 5;

	/**
	 * Folder file IDs seen during this request, keyed by "desktop"/"mobile".
	 * A brand-new asset has no post ID to store them on yet when its images
	 * are fetched, so the save handler persists them via
	 * remember_folder_state() once the post exists.
	 */
	private static $folder_state = array();

	/**
	 * Rewrites a Google Drive "share" link (drive.google.com/file/d/ID/view
	 * or drive.google.com/open?id=ID) into Drive's direct-download URL.
	 * Any other host passes through unchanged.
	 */
	public static function normalize_url( $url ) {
		$host = wp_parse_url( $url, PHP_URL_HOST );
		if ( ! $host || false === strpos( $host, 'drive.google.com' ) ) {
			return $url;
		}
		$file_id = '';
		if ( preg_match( '#/file/d/([a-zA-Z0-9_-]+)#', $url, $m ) ) {
			$file_id = $m[1];
		} elseif ( preg_match( '#[?&]id=([a-zA-Z0-9_-]+)#', $url, $m ) ) {
			$file_id = $m[1];
		}
		if ( ! $file_id ) {
			return $url;
		}
		return self::drive_download_url( $file_id );
	}

	public static function drive_download_url( $file_id ) {
		return 'https://drive.google.com/uc?export=download&id=' . rawurlencode( $file_id );
	}

	public static function is_drive_url( $url ) {
		$host = wp_parse_url( $url, PHP_URL_HOST );
		return $host && false !== strpos( $host, 'drive.google.com' );
	}

	/**
	 * The folder ID from a Drive folder link (drive.google.com/drive/folders/ID,
	 * with or without /u/0/ or a ?usp=... suffix, or folderview?id=ID), or ''.
	 */
	public static function drive_folder_id( $url ) {
		if ( ! self::is_drive_url( $url ) ) {
			return '';
		}
		if ( preg_match( '#/folders/([a-zA-Z0-9_-]+)#', $url, $m ) ) {
			return $m[1];
		}
		if ( preg_match( '#/(?:embedded)?folderview\?(?:.*&)?id=([a-zA-Z0-9_-]+)#', $url, $m ) ) {
			return $m[1];
		}
		return '';
	}

	public static function is_folder_url( $url ) {
		return '' !== self::drive_folder_id( $url );
	}

	/**
	 * True when a folder check found nothing usable only because the folder
	 * has no picture of the right shape (or none at all) yet — as opposed to
	 * a real problem like the folder not being shared. For the optional
	 * mobile slot that just means "no phone picture yet": phones show the
	 * desktop picture, and a tall picture added later is picked up
	 * automatically, so it must never block saving or email the admin.
	 */
	public static function is_waiting_for_picture( $error ) {
		return is_wp_error( $error ) && in_array( $error->get_error_code(), array( 'rapm_folder_no_match', 'rapm_folder_empty' ), true );
	}

	/**
	 * Downloads $url, validates it against $slot, converts/compresses it,
	 * and sideloads it into the Media Library. Returns the new attachment
	 * ID, or a WP_Error explaining what went wrong — including a
	 * Drive-specific hint, since Drive sometimes serves an HTML "can't
	 * scan this file for viruses" warning page instead of the file itself
	 * for larger files, which downloads successfully but isn't a picture.
	 *
	 * $which ('desktop'/'mobile') is only needed for folder links, to
	 * remember which folder files this slot has already seen.
	 *
	 * $crop_anchor (1.58.2): which part to keep when a one-picture link is a
	 * different shape, picked by the person in the same crop picker an
	 * upload gets. Folder links ignore it: a folder always picks a picture
	 * of the right shape.
	 */
	public static function fetch_and_validate( $url, $slot, $parent_id, $which = 'desktop', $crop_anchor = '' ) {
		$url = esc_url_raw( $url );
		if ( ! $url ) {
			return new WP_Error( 'rapm_bad_url', __( 'That doesn\'t look like a valid web address.', 'rapm' ) );
		}

		$picked = self::resolve_to_tmp( $url, $slot, $parent_id, $which );
		if ( is_wp_error( $picked ) ) {
			return $picked;
		}
		$tmp_path      = $picked['tmp_path'];
		$filename_hint = $picked['name'];

		$result = RAPM_Upload_Handler::validate_convert_sideload( $tmp_path, $filename_hint ?: 'linked-image', $slot, $parent_id, self::is_folder_url( $url ) ? '' : $crop_anchor );

		if ( file_exists( $tmp_path ) ) {
			wp_delete_file( $tmp_path );
		}

		return $result;
	}

	/**
	 * Downloads the picture a link points to (for a folder link, the one
	 * pick_from_folder() chooses) and returns array( 'tmp_path', 'name' ),
	 * or a WP_Error. The caller owns the temp file and must delete it.
	 *
	 * Shared by saving/syncing and by the Add/Edit Asset form's live
	 * preview; the preview passes $remember = false so just looking at a
	 * folder never marks its files as seen.
	 */
	public static function resolve_to_tmp( $url, $slot, $asset_id, $which = 'desktop', $remember = true ) {
		$url = esc_url_raw( $url );
		if ( ! $url ) {
			return new WP_Error( 'rapm_bad_url', __( 'That doesn\'t look like a valid web address.', 'rapm' ) );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';

		if ( self::is_folder_url( $url ) ) {
			return self::pick_from_folder( self::drive_folder_id( $url ), $slot, $asset_id, $which, $remember );
		}

		$is_drive = self::is_drive_url( $url );
		$tmp_path = download_url( self::normalize_url( $url ), 45 );

		if ( is_wp_error( $tmp_path ) ) {
			return self::download_error( $tmp_path );
		}

		if ( ! getimagesize( $tmp_path ) ) {
			wp_delete_file( $tmp_path );
			if ( $is_drive ) {
				return new WP_Error(
					'rapm_not_an_image',
					__( 'That Google Drive link didn\'t return a picture directly. Make sure the file\'s sharing setting is "Anyone with the link," and try again — very large files can also get blocked behind a "can\'t scan for viruses" warning page instead of downloading directly.', 'rapm' )
				);
			}
			return new WP_Error(
				'rapm_not_an_image',
				__( 'That link didn\'t return a picture — double check it\'s a direct link to the image file itself, not a page that shows the image.', 'rapm' )
			);
		}

		return array(
			'tmp_path' => $tmp_path,
			'name'     => basename( (string) wp_parse_url( $url, PHP_URL_PATH ) ),
		);
	}

	/**
	 * Finds the newest picture in a shared Drive folder that fits $slot's
	 * shape, and returns array( 'tmp_path' => ..., 'name' => ... ) for it
	 * (already downloaded), or a WP_Error.
	 *
	 * "Newest" is decided two ways, strongest first: a file this slot has
	 * never seen before (a genuinely new upload always wins, whatever it's
	 * called or dated), then Drive's own last-modified date. Pictures of the
	 * wrong shape are skipped rather than treated as errors — that's what
	 * lets one folder hold both the wide desktop and tall mobile picture.
	 */
	private static function pick_from_folder( $folder_id, $slot, $asset_id, $which, $remember = true ) {
		$entries = self::list_drive_folder( $folder_id );
		if ( is_wp_error( $entries ) ) {
			return $entries;
		}
		if ( ! $entries ) {
			return new WP_Error( 'rapm_folder_empty', __( 'That Google Drive folder doesn\'t have any pictures in it yet. Add a picture to the folder, then try again.', 'rapm' ) );
		}

		$seen = $asset_id ? (array) get_post_meta( $asset_id, '_rapm_image_' . $which . '_folder_seen', true ) : array();
		usort(
			$entries,
			function ( $a, $b ) use ( $seen ) {
				$a_new = ! in_array( $a['id'], $seen, true );
				$b_new = ! in_array( $b['id'], $seen, true );
				if ( $a_new !== $b_new ) {
					return $a_new ? -1 : 1;
				}
				return $b['modified'] <=> $a['modified'];
			}
		);

		if ( $remember ) {
			self::$folder_state[ $which ] = wp_list_pluck( $entries, 'id' );
			if ( $asset_id ) {
				self::remember_folder_state( $asset_id, $which );
			}
		}

		$checked = 0;
		foreach ( $entries as $entry ) {
			if ( $checked >= self::FOLDER_MAX_CANDIDATES ) {
				break;
			}
			$checked++;
			$tmp_path = download_url( self::drive_download_url( $entry['id'] ), 45 );
			if ( is_wp_error( $tmp_path ) ) {
				continue;
			}
			$dims = getimagesize( $tmp_path );
			if ( $dims && RAPM_Slots::aspect_ratio_matches( $slot, $dims[0], $dims[1] ) ) {
				return array(
					'tmp_path' => $tmp_path,
					'name'     => $entry['name'],
				);
			}
			wp_delete_file( $tmp_path );
		}

		return new WP_Error(
			'rapm_folder_no_match',
			sprintf(
				/* translators: 1: slot label, e.g. "Hero — Desktop", 2: width, 3: height */
				__( 'None of the newest pictures in that Google Drive folder are the right shape for "%1$s" (it needs %2$d by %3$d, or the same shape at a bigger size). Add a picture that shape to the folder.', 'rapm' ),
				$slot['label'],
				$slot['width'],
				$slot['height']
			)
		);
	}

	/**
	 * Saves the folder file IDs seen for $which during this request onto the
	 * asset, so the next check can tell a new upload from an old picture.
	 */
	public static function remember_folder_state( $asset_id, $which ) {
		if ( $asset_id && isset( self::$folder_state[ $which ] ) ) {
			update_post_meta( $asset_id, '_rapm_image_' . $which . '_folder_seen', self::$folder_state[ $which ] );
		}
	}

	/**
	 * Lists the picture files in a Drive folder shared as "Anyone with the
	 * link", without any Google login or API key, by reading Drive's own
	 * embeddable folder view (the page Drive serves for embedding a folder
	 * in a website). Returns a list of array( 'id', 'name', 'modified' )
	 * where modified is a Unix timestamp (0 if Drive's date couldn't be
	 * read), or a WP_Error.
	 *
	 * This page isn't an official API, so it could change without notice.
	 * If it does, this returns an error, and the existing sync behavior
	 * applies: the last good picture stays up and the site admin is emailed.
	 */
	public static function list_drive_folder( $folder_id ) {
		$response = wp_remote_get(
			'https://drive.google.com/embeddedfolderview?id=' . rawurlencode( $folder_id ),
			array(
				'timeout' => 20,
				// Drive formats dates in the request's language; pin English
				// so parse_drive_date() below can read them.
				'headers' => array( 'Accept-Language' => 'en-US,en;q=0.8' ),
			)
		);
		if ( is_wp_error( $response ) ) {
			return self::download_error( $response );
		}
		$body = (string) wp_remote_retrieve_body( $response );
		// A private or missing folder answers with a sign-in or 404 page
		// instead, which never contains Drive's folder-view markup.
		if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) || false === strpos( $body, 'flip-embedded' ) ) {
			return new WP_Error( 'rapm_folder_unreadable', __( 'Couldn\'t open that Google Drive folder. Make sure the folder\'s sharing setting is "Anyone with the link," then try again.', 'rapm' ) );
		}

		$entries = array();
		if ( preg_match_all( '#<div class="flip-entry" id="entry-([a-zA-Z0-9_-]+)".*?<a href="([^"]*)".*?<div class="flip-entry-title">([^<]*)</div>.*?<div class="flip-entry-last-modified"><div>([^<]*)</div>#s', $body, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $m ) {
				// Subfolders show up in the list too — only files are pictures.
				if ( false === strpos( $m[2], '/file/d/' ) ) {
					continue;
				}
				$name = html_entity_decode( $m[3], ENT_QUOTES, 'UTF-8' );
				if ( ! preg_match( '/\.(jpe?g|png|webp|gif|bmp|tiff?)$/i', $name ) ) {
					continue;
				}
				$entries[] = array(
					'id'       => $m[1],
					'name'     => $name,
					'modified' => self::parse_drive_date( html_entity_decode( $m[4], ENT_QUOTES, 'UTF-8' ) ),
				);
			}
		}
		return $entries;
	}

	/**
	 * Reads the last-modified text Drive's folder view shows: a time for
	 * today ("8:59 am"), "Sep 10" for this year, or "Sep 10, 2025" / a
	 * numeric date otherwise. Drive shows these in US Pacific time; only the
	 * order between files matters here, so a consistent zone is enough.
	 */
	public static function parse_drive_date( $text ) {
		$text = trim( preg_replace( '/[\x{00A0}\x{202F}\s]+/u', ' ', (string) $text ) );
		if ( '' === $text ) {
			return 0;
		}
		$now = new DateTimeImmutable( 'now', new DateTimeZone( 'America/Los_Angeles' ) );
		if ( preg_match( '/^\d{1,2}:\d{2}(\s?[ap]\.?m\.?)?$/i', $text ) ) {
			$time = strtotime( $now->format( 'Y-m-d' ) . ' ' . str_replace( '.', '', $text ) . ' America/Los_Angeles' );
			return $time ? $time : 0;
		}
		if ( preg_match( '/^[A-Za-z]{3,9} \d{1,2}$/', $text ) ) {
			$text .= ', ' . $now->format( 'Y' );
		}
		$time = strtotime( $text . ' America/Los_Angeles' );
		return $time ? $time : 0;
	}

	private static function download_error( $error ) {
		return new WP_Error(
			'rapm_fetch_failed',
			sprintf(
				/* translators: %s: the underlying download error */
				__( 'Could not download that link: %s', 'rapm' ),
				$error->get_error_message()
			)
		);
	}
}
