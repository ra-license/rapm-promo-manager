<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The Design Center's Asset library (1.48.0, from the mockup Phil approved
 * on 2026-10-16, https://claude.ai/artifact/7QB12FYVVSSB4sNJi67VV6): every
 * promotion of every type in one place, for designers. Clients keep the
 * simple Promotions page.
 *
 * The page prints every promotion's facts once, as JSON
 * (rapm-library.js filters, sorts and draws them in the browser): type,
 * spot, status, dates, each picture's real size against the size its spot
 * needs, file weight, where it comes from (upload, Google Drive or
 * Dropbox, last checked), alt text, the pages it shows on, and when it was
 * created and last saved. "Needs a look" marks a few plain facts (a sync
 * that failed, a picture the wrong size, no alt text, a Slider with no
 * phone picture); 1.49.0 turns these into full pass/fail checks.
 *
 * Bulk actions (on, off, dates, duplicate, move to a spot, trash) and
 * "Replace everywhere" go through admin-ajax, and every ID, date, spot and
 * picture is checked again here.
 */
class RAPM_Library {

	const NONCE = 'rapm_library';

	/** The status buckets the library's chips use (describe()'s states). */
	public static function items() {
		$posts = get_posts(
			array(
				'post_type'      => 'rapm_asset',
				'post_status'    => array( 'publish', 'draft', 'pending', 'future', 'private' ),
				'posts_per_page' => -1,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);
		$out = array();
		foreach ( $posts as $post ) {
			if ( get_post_meta( $post->ID, '_rapm_from_gallery', true ) ) {
				continue; // 1.58.1: a gallery copy that hasn't been saved yet.
			}
			$out[] = self::item( $post );
		}
		return $out;
	}

	/**
	 * A promotion's name as plain text (1.48.1). get_the_title() returns
	 * HTML: WordPress turns " - " into &#8211; and so on. The library's
	 * script escapes text itself, so the encoded form showed up literally
	 * ("Coupon &#8211; Free Delivery"), found on staging.
	 */
	private static function plain_title( $post ) {
		return wp_strip_all_tags( html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' ) );
	}

	/** One picture's facts, or null. */
	private static function picture( $asset_id, $which ) {
		$id = (int) get_post_meta( $asset_id, '_rapm_image_' . $which . '_id', true );
		if ( ! $id ) {
			return null;
		}
		$meta = wp_get_attachment_metadata( $id );
		$file = get_attached_file( $id );
		$src  = get_post_meta( $asset_id, '_rapm_image_' . $which . '_source', true );
		$url  = (string) get_post_meta( $asset_id, '_rapm_image_' . $which . '_url', true );
		$from = __( 'Upload', 'rapm' );
		if ( 'link' === $src ) {
			$host = (string) wp_parse_url( $url, PHP_URL_HOST );
			$from = false !== strpos( $host, 'dropbox' ) ? 'Dropbox' : ( false !== strpos( $host, 'google' ) ? 'Google Drive' : __( 'A link', 'rapm' ) );
		}
		$synced = (int) get_post_meta( $asset_id, '_rapm_image_' . $which . '_synced_at', true );
		return array(
			'id'     => $id,
			'w'      => is_array( $meta ) && ! empty( $meta['width'] ) ? (int) $meta['width'] : 0,
			'h'      => is_array( $meta ) && ! empty( $meta['height'] ) ? (int) $meta['height'] : 0,
			'kb'     => $file && file_exists( $file ) ? (int) round( filesize( $file ) / 1024 ) : 0,
			'file'   => $file ? wp_basename( $file ) : '',
			'thumb'  => (string) wp_get_attachment_image_url( $id, 'medium_large' ),
			'from'   => $from,
			'link'   => 'link' === $src,
			'synced' => $synced ? sprintf( /* translators: %s: e.g. "2 hours" */ __( '%s ago', 'rapm' ), human_time_diff( $synced ) ) : '',
			'error'  => (string) get_post_meta( $asset_id, '_rapm_image_' . $which . '_sync_error', true ),
		);
	}

	/** 1.49.0: an item with its "Ready to go live" checks and verdict. */
	private static function item( $post ) {
		$item           = self::facts( $post );
		$item['checks'] = RAPM_Ready::checks( $item['id'], $item );
		$item['ready']  = RAPM_Ready::verdict( $item['checks'] );
		$item['hasLink'] = '' !== $item['link'];
		return $item;
	}

	private static function facts( $post ) {
		$id        = (int) $post->ID;
		$kind      = get_post_meta( $id, '_rapm_kind', true ) ?: 'hero'; // phpcs:ignore
		$placement = get_post_meta( $id, '_rapm_placement', true ) ?: 'default'; // phpcs:ignore
		$info      = RAPM_Slots::kind_for( $kind, $placement );
		$slot      = RAPM_Slots::get( $info['desktop'] );
		$mslot     = RAPM_Slots::get( $info['mobile'] );
		$d         = RAPM_Promotions_Screen::describe( $post );
		$design    = 'promo_box' === $kind && 'design' === RAPM_Split::box_mode( $id );
		$desk      = self::picture( $id, 'desktop' );
		$phone     = $info['mobile'] !== $info['desktop'] ? self::picture( $id, 'mobile' ) : null;
		$alt       = (string) get_post_meta( $id, '_rapm_alt_text', true );
		$url       = RAPM_Destination::resolve_url( get_post_meta( $id, '_rapm_destination_type', true ), get_post_meta( $id, '_rapm_destination_value', true ) );

		$pages = RAPM_Spot_Usage::for_spot( $kind, $placement );
		if ( 'promo_box' === $kind && RAPM_Split::is_split( $placement ) ) {
			$pages = array_merge( RAPM_Spot_Usage::for_spot( 'hero', $placement ), $pages );
		}
		$pages = array_map( array( 'RAPM_Spot_Usage', 'for_screen' ), $pages ); // 1.51.1: address, not-live note and Edit link too.

		$editor   = (int) get_post_meta( $id, '_edit_last', true );
		$editor   = $editor ? get_the_author_meta( 'display_name', $editor ) : '';
		$check    = '';
		if ( ( $desk && $desk['link'] ) || ( $phone && $phone['link'] ) ) {
			$check = wp_nonce_url( admin_url( 'admin-post.php?action=rapm_check_link_now&asset_id=' . $id ), 'rapm_check_link_now_' . $id );
		}
		$title = self::plain_title( $post );

		return array(
			'id'       => $id,
			'name'     => '' !== $title ? $title : __( '(no name)', 'rapm' ),
			'kind'     => $kind,
			'type'     => RAPM_Spots::type_label( $kind ),
			'spot'     => $kind . '|' . $placement,
			'spotName' => RAPM_Spots::name( $kind, $placement ),
			'state'    => $d['state'],
			'status'   => $d['status'],
			'when'     => $d['when'],
			'on'       => 'publish' === $post->post_status,
			'start'    => (string) get_post_meta( $id, '_rapm_starts_at', true ),
			'end'      => (string) get_post_meta( $id, '_rapm_ends_at', true ),
			'words'    => (string) get_post_meta( $id, '_rapm_headline', true ),
			'design'   => $design,
			'preview'  => $design ? RAPM_Split::card_preview( $id ) : '',
			'need'     => $slot ? ( RAPM_Slots::is_flexible( $slot ) ? sprintf( __( 'up to %d wide', 'rapm' ), (int) $slot['width'] ) : (int) $slot['width'] . '×' . (int) $slot['height'] ) : '',
			'needKb'   => $slot ? (int) $slot['max_kb'] : 0,
			'needPhone' => ( $phone || in_array( $kind, array( 'hero', 'fold_banner' ), true ) ) && $mslot ? (int) $mslot['width'] . '×' . (int) $mslot['height'] : '',
			'desk'     => $desk,
			'phone'    => $phone,
			'alt'      => $alt,
			// 1.54.0: what stays in view when the website trims each picture ('' = the middle); null when it isn't trimmed.
			'focus'    => RAPM_Focal::applies( $kind, $placement ) ? array( 'desk' => RAPM_Focal::get( $id, 'desktop' ), 'phone' => RAPM_Focal::has_phone( $kind ) ? RAPM_Focal::get( $id, 'mobile' ) : null ) : null,
			'link'     => (string) $url,
			'pages'    => $pages,
			'checks'   => array(),
			'created'  => get_the_date( 'M j, Y', $post ),
			'saved'    => get_the_modified_date( 'M j, Y', $post ),
			'editor'   => (string) $editor,
			'edit'     => admin_url( 'edit.php?post_type=rapm_asset&page=rapm-add-asset&edit=' . $id ),
			'check'    => $check,
		);
	}

	/** The page's library tab. */
	public static function render() {
		$items = self::items();
		$spots = array();
		foreach ( RAPM_Spots::known() as $spot ) {
			$spots[ $spot['kind'] . '|' . $spot['placement'] ] = array( 'name' => $spot['name'], 'kind' => $spot['kind'], 'type' => $spot['type'] );
		}
		foreach ( $items as $it ) {
			if ( ! isset( $spots[ $it['spot'] ] ) ) {
				$spots[ $it['spot'] ] = array( 'name' => $it['spotName'], 'kind' => $it['kind'], 'type' => $it['type'] );
			}
		}
		$data = array(
			'items' => $items,
			'spots' => $spots,
			// 1.49.0: stretches of the next 30 days with nothing set to show.
			'gaps'  => RAPM_Ready::spot_gaps( $items, $spots ),
		);
		?>
		<div class="rapm-lib" id="rapm-lib">
			<script type="application/json" id="rapm-library-data"><?php echo wp_json_encode( $data, JSON_HEX_TAG | JSON_HEX_AMP ); ?></script>
			<p class="rapm-lib-summary" id="rapm-lib-summary"></p>
			<div class="rapm-lib-toolbar">
				<label class="screen-reader-text" for="rapm-lib-q"><?php esc_html_e( 'Search promotions', 'rapm' ); ?></label>
				<input type="search" id="rapm-lib-q" placeholder="<?php esc_attr_e( 'Search names, headlines, file names', 'rapm' ); ?>" />
				<label class="screen-reader-text" for="rapm-lib-type"><?php esc_html_e( 'Type', 'rapm' ); ?></label>
				<select id="rapm-lib-type"><option value=""><?php esc_html_e( 'All types', 'rapm' ); ?></option></select>
				<label class="screen-reader-text" for="rapm-lib-spot"><?php esc_html_e( 'Spot', 'rapm' ); ?></label>
				<select id="rapm-lib-spot"><option value=""><?php esc_html_e( 'All spots', 'rapm' ); ?></option></select>
				<label class="screen-reader-text" for="rapm-lib-sort"><?php esc_html_e( 'Sort', 'rapm' ); ?></label>
				<select id="rapm-lib-sort">
					<option value="ending"><?php esc_html_e( 'Ending soonest', 'rapm' ); ?></option>
					<option value="newest"><?php esc_html_e( 'Newest first', 'rapm' ); ?></option>
					<option value="name"><?php esc_html_e( 'Name A–Z', 'rapm' ); ?></option>
					<option value="weight"><?php esc_html_e( 'Heaviest files', 'rapm' ); ?></option>
				</select>
				<button type="button" class="button" id="rapm-lib-links"><?php esc_html_e( 'Check links', 'rapm' ); ?></button>
				<span class="rapm-lib-seg" role="group" aria-label="<?php esc_attr_e( 'View', 'rapm' ); ?>">
					<button type="button" class="button" data-view="grid" aria-pressed="true"><?php esc_html_e( 'Cards', 'rapm' ); ?></button>
					<button type="button" class="button" data-view="table" aria-pressed="false"><?php esc_html_e( 'Table', 'rapm' ); ?></button>
				</span>
			</div>
			<div class="rapm-lib-chips" id="rapm-lib-chips" role="group" aria-label="<?php esc_attr_e( 'Status', 'rapm' ); ?>"></div>
			<?php // 1.49.0: schedule gaps by spot, and the link check. ?>
			<div class="rapm-lib-gaps" id="rapm-lib-gaps" hidden></div>
			<div id="rapm-lib-results" aria-live="polite"></div>
			<?php // Below the cards and pinned to the bottom of the screen, so ticking a box never moves the cards. ?>
			<div class="rapm-lib-bulk" id="rapm-lib-bulk" hidden></div>
			<p class="rapm-lib-status" id="rapm-lib-status" aria-live="polite"></p>
		</div>
		<div class="rapm-lib-drawer-bg" id="rapm-lib-drawer-bg" hidden></div>
		<aside class="rapm-lib-drawer" id="rapm-lib-drawer" hidden role="dialog" aria-modal="true" aria-labelledby="rapm-lib-d-title"></aside>
		<?php
	}

	public static function enqueue() {
		wp_enqueue_media();
		wp_enqueue_script( 'rapm-library', RAPM_URL . 'assets/js/rapm-library.js', array(), RAPM_VERSION, true );
		wp_localize_script(
			'rapm-library',
			'RAPM_Library',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( self::NONCE ),
				'text'    => array(
					'promotions'   => __( 'promotions', 'rapm' ),
					'spots'        => __( 'spots', 'rapm' ),
					'showingNow'   => __( 'showing now', 'rapm' ),
					'needLook'     => __( 'need a look', 'rapm' ),
					'ofPictures'   => __( 'of pictures', 'rapm' ),
					'notEnded'     => __( 'Not ended', 'rapm' ),
					'showing'      => __( 'Showing now', 'rapm' ),
					'upcoming'     => __( 'Scheduled', 'rapm' ),
					'hidden'       => __( 'Turned off', 'rapm' ),
					'ended'        => __( 'Ended', 'rapm' ),
					'flags'        => __( 'Needs a look', 'rapm' ),
					'fails'        => __( 'Problems', 'rapm' ),
					'ready'        => __( 'Ready to go live', 'rapm' ),
					'problem'      => __( 'Problem', 'rapm' ),
					'readyAll'     => __( 'Everything checks out.', 'rapm' ),
					'gapsTitle'    => __( 'Gaps in the schedule (next 30 days)', 'rapm' ),
					'checkingLinks' => __( 'Checking links: %1$d of %2$d…', 'rapm' ),
					'linksDone'    => __( 'Links checked. Refreshing…', 'rapm' ),
					'noLinks'      => __( 'No links to check in this view.', 'rapm' ),
					'readyNote'    => __( 'Checks use what the plugin can measure. Words on a photo and how a design looks still need your eye.', 'rapm' ),
					'all'          => __( 'Everything', 'rapm' ),
					'none'         => __( 'No promotions match. Try "Everything" or clear the search.', 'rapm' ),
					'selected'     => __( '%d selected', 'rapm' ),
					'turnOn'       => __( 'Turn on', 'rapm' ),
					'turnOff'      => __( 'Turn off', 'rapm' ),
					'setDates'     => __( 'Set dates…', 'rapm' ),
					'duplicate'    => __( 'Duplicate', 'rapm' ),
					'move'         => __( 'Move to spot…', 'rapm' ),
					'trash'        => __( 'Move to trash', 'rapm' ),
					'clear'        => __( 'Clear', 'rapm' ),
					'confirm'      => __( 'Confirm', 'rapm' ),
					'cancel'       => __( 'Cancel', 'rapm' ),
					/* translators: 1: an action like "Turn off", 2: how many */
					'ask'          => __( '%1$s %2$d promotion(s)?', 'rapm' ),
					'askMove'      => __( 'Only promotions of the same type as the spot are moved.', 'rapm' ),
					'starts'       => __( 'Starts', 'rapm' ),
					'ends'         => __( 'Ends', 'rapm' ),
					'datesNote'    => __( 'Leave one blank for no start or no end.', 'rapm' ),
					'working'      => __( 'Working…', 'rapm' ),
					/* translators: 1: how many were changed, 2: how many were left alone */
					'doneSkipped'  => __( '%1$d done, %2$d left as they were (a promotion only moves to a spot of its own type). Refreshing…', 'rapm' ),
					'refreshing'   => __( 'Done. Refreshing…', 'rapm' ),
					'failed'       => __( 'That didn\'t work. Please try again.', 'rapm' ),
					'computer'     => __( 'Computer', 'rapm' ),
					'phone'        => __( 'Phone', 'rapm' ),
					'phones'       => __( 'Phones', 'rapm' ),
					'computers'    => __( 'Computers', 'rapm' ),
					'noPhone'      => __( 'No phone picture', 'rapm' ),
					'designed'     => __( 'Designed on the canvas', 'rapm' ),
					'needed'       => __( 'Needed here', 'rapm' ),
					'comesFrom'    => __( 'Comes from', 'rapm' ),
					'lastChecked'  => __( 'last checked', 'rapm' ),
					'checkNow'     => __( 'Check now', 'rapm' ),
					'altText'      => __( 'Alt text', 'rapm' ),
					'noneYet'      => __( 'None yet', 'rapm' ),
					'pictures'     => __( 'Pictures', 'rapm' ),
					'needsLook'    => __( 'Needs a look', 'rapm' ),
					'allGood'      => __( 'Nothing to look at. Full "Ready to go live" checks come in the next update.', 'rapm' ),
					'whereShows'   => __( 'Where it shows', 'rapm' ),
					// 1.54.0.
					'inView'       => __( 'Stays in view', 'rapm' ),
					'middle'       => __( 'The middle (no point set)', 'rapm' ),
					/* translators: 1: percent across, 2: percent down */
					'pointAt'      => __( '%1$s across, %2$s down', 'rapm' ),
					'setPoint'     => __( 'Change it', 'rapm' ),
					'editPage'     => __( 'Edit page', 'rapm' ),
					'notPlaced'    => __( 'Not on any page yet.', 'rapm' ),
					'linksTo'      => __( 'Links to', 'rapm' ),
					'history'      => __( 'History', 'rapm' ),
					'created'      => __( 'Created', 'rapm' ),
					'lastSaved'    => __( 'Last saved', 'rapm' ),
					'by'           => __( 'by', 'rapm' ),
					'edit'         => __( 'Edit', 'rapm' ),
					'replace'      => __( 'Replace picture…', 'rapm' ),
					'replaceTitle' => __( 'Replace "%s" everywhere it\'s used', 'rapm' ),
					'replaceNote'  => __( 'Pick or upload the new picture once. These promotions use the same file:', 'rapm' ),
					'replaceGo'    => __( 'Choose the new picture', 'rapm' ),
					'replaceRule'  => __( 'It must be the size the spot needs and within its file-size limit. For anything else, use the promotion\'s own form, which resizes and converts pictures.', 'rapm' ),
					'pickPicture'  => __( 'The new picture', 'rapm' ),
					'usePicture'   => __( 'Replace it', 'rapm' ),
					'close'        => __( 'Close', 'rapm' ),
					'kb'           => __( 'KB', 'rapm' ),
					'noEnd'        => __( 'No end date', 'rapm' ),
					'select'       => __( 'Select %s', 'rapm' ),
					'selectAll'    => __( 'Select all shown', 'rapm' ),
					'colName'      => __( 'Name', 'rapm' ),
					'colType'      => __( 'Type', 'rapm' ),
					'colSpot'      => __( 'Spot', 'rapm' ),
					'colStatus'    => __( 'Status', 'rapm' ),
					'colDates'     => __( 'Dates', 'rapm' ),
					'colSource'    => __( 'Source', 'rapm' ),
					'missing'      => __( 'missing', 'rapm' ),
				),
			)
		);
	}

	private static function check_request() {
		check_ajax_referer( self::NONCE, 'nonce' );
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Not allowed.', 'rapm' ) ), 403 );
		}
	}

	/** Promotions this user may change, from the posted IDs. */
	private static function posted_ids() {
		$ids = isset( $_POST['ids'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['ids'] ) ) : array(); // phpcs:ignore WordPress.Security.NonceVerification -- checked in check_request().
		return array_values(
			array_filter(
				array_unique( $ids ),
				function ( $id ) {
					return $id && 'rapm_asset' === get_post_type( $id ) && current_user_can( 'edit_post', $id );
				}
			)
		);
	}

	/** A datetime-local value (2026-11-01T09:00), '' for none, or false when it isn't one. */
	private static function clean_date( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return '';
		}
		$dt = DateTime::createFromFormat( 'Y-m-d\TH:i', $value, wp_timezone() );
		return $dt && $dt->format( 'Y-m-d\TH:i' ) === $value ? $value : false;
	}

	public static function ajax_bulk() {
		self::check_request();
		$do  = isset( $_POST['do'] ) ? sanitize_key( wp_unslash( $_POST['do'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$ids = self::posted_ids();
		if ( ! $ids ) {
			wp_send_json_error( array( 'message' => __( 'Pick at least one promotion.', 'rapm' ) ), 400 );
		}
		$done    = 0;
		$skipped = 0;
		switch ( $do ) {
			case 'on':
			case 'off':
				foreach ( $ids as $id ) {
					$r = wp_update_post( array( 'ID' => $id, 'post_status' => 'on' === $do ? 'publish' : 'draft' ), true );
					is_wp_error( $r ) ? $skipped++ : $done++;
				}
				break;
			case 'dates':
				$start = self::clean_date( isset( $_POST['start'] ) ? wp_unslash( $_POST['start'] ) : '' ); // phpcs:ignore WordPress.Security.NonceVerification
				$end   = self::clean_date( isset( $_POST['end'] ) ? wp_unslash( $_POST['end'] ) : '' ); // phpcs:ignore WordPress.Security.NonceVerification
				if ( false === $start || false === $end ) {
					wp_send_json_error( array( 'message' => __( 'Pick the dates with the date boxes.', 'rapm' ) ), 400 );
				}
				if ( $start && $end && $end <= $start ) {
					wp_send_json_error( array( 'message' => __( 'Those dates don\'t work: the end has to come after the start.', 'rapm' ) ), 400 );
				}
				foreach ( $ids as $id ) {
					update_post_meta( $id, '_rapm_starts_at', $start );
					update_post_meta( $id, '_rapm_ends_at', $end );
					RAPM_Cache::queue_asset( $id );
					$done++;
				}
				break;
			case 'duplicate':
				foreach ( $ids as $id ) {
					RAPM_Admin_List::duplicate( $id ) ? $done++ : $skipped++;
				}
				break;
			case 'move':
				$to = isset( $_POST['spot'] ) ? sanitize_text_field( wp_unslash( $_POST['spot'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
				if ( false === strpos( $to, '|' ) ) {
					wp_send_json_error( array( 'message' => __( 'Pick a spot.', 'rapm' ) ), 400 );
				}
				list( $kind, $placement ) = explode( '|', $to, 2 );
				$kind      = sanitize_key( $kind );
				$placement = sanitize_title( $placement );
				foreach ( $ids as $id ) {
					// A promotion only moves to a spot of its own type: its
					// pictures are sized for that type.
					if ( ( get_post_meta( $id, '_rapm_kind', true ) ?: 'hero' ) !== $kind ) { // phpcs:ignore
						$skipped++;
						continue;
					}
					RAPM_Cache::queue_asset( $id );
					update_post_meta( $id, '_rapm_placement', $placement );
					RAPM_Cache::queue_asset( $id );
					$done++;
				}
				break;
			case 'trash':
				foreach ( $ids as $id ) {
					wp_trash_post( $id ) ? $done++ : $skipped++;
				}
				break;
			default:
				wp_send_json_error( array( 'message' => __( 'Unknown action.', 'rapm' ) ), 400 );
		}
		wp_send_json_success( array( 'done' => $done, 'skipped' => $skipped ) );
	}

	/**
	 * "Replace everywhere": puts one Media Library picture in place of
	 * another in the chosen promotions. Only when the new picture is the
	 * size each promotion's spot needs and within its file-size limit, since
	 * this skips the form's resizing and WebP conversion. The promotion's
	 * link source (Drive/Dropbox) is dropped for that picture, since it was
	 * replaced by hand.
	 */
	public static function ajax_replace() {
		self::check_request();
		$old = isset( $_POST['old'] ) ? absint( $_POST['old'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
		$new = isset( $_POST['new'] ) ? absint( $_POST['new'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! $old || ! $new || $old === $new || ! wp_attachment_is_image( $new ) ) {
			wp_send_json_error( array( 'message' => __( 'Pick a different picture from the Media Library.', 'rapm' ) ), 400 );
		}
		$meta = wp_get_attachment_metadata( $new );
		$file = get_attached_file( $new );
		$w    = is_array( $meta ) && ! empty( $meta['width'] ) ? (int) $meta['width'] : 0;
		$h    = is_array( $meta ) && ! empty( $meta['height'] ) ? (int) $meta['height'] : 0;
		$kb   = $file && file_exists( $file ) ? filesize( $file ) / 1024 : 0;
		$done = 0;
		$why  = array();
		foreach ( self::posted_ids() as $id ) {
			$kind      = get_post_meta( $id, '_rapm_kind', true ) ?: 'hero'; // phpcs:ignore
			$placement = get_post_meta( $id, '_rapm_placement', true ) ?: 'default'; // phpcs:ignore
			$info      = RAPM_Slots::kind_for( $kind, $placement );
			foreach ( array( 'desktop', 'mobile' ) as $which ) {
				if ( (int) get_post_meta( $id, '_rapm_image_' . $which . '_id', true ) !== $old ) {
					continue;
				}
				$slot = RAPM_Slots::get( $info[ $which ] );
				if ( ! $slot || ! RAPM_Slots::dimensions_match( $slot, $w, $h ) ) {
					/* translators: 1: promotion name, 2: picture size, 3: needed size */
					$why[] = sprintf( __( '%1$s: the new picture is %2$s, it needs %3$s.', 'rapm' ), self::plain_title( $id ), $w . '×' . $h, $slot ? (int) $slot['width'] . '×' . (int) $slot['height'] : '?' );
					continue;
				}
				if ( $kb > (int) $slot['max_kb'] ) {
					/* translators: 1: promotion name, 2: file size, 3: limit */
					$why[] = sprintf( __( '%1$s: the new picture is %2$d KB, the limit is %3$d KB.', 'rapm' ), self::plain_title( $id ), (int) round( $kb ), (int) $slot['max_kb'] );
					continue;
				}
				update_post_meta( $id, '_rapm_image_' . $which . '_id', $new );
				update_post_meta( $id, '_rapm_image_' . $which . '_source', 'upload' );
				// 1.54.0: a new picture has its own subject, so its point goes back to the middle.
				RAPM_Focal::save( $id, $which, '' );
				foreach ( array( 'url', 'synced_at', 'sync_error', 'waiting' ) as $k ) {
					delete_post_meta( $id, '_rapm_image_' . $which . '_' . $k );
				}
				RAPM_Cache::queue_asset( $id );
				$done++;
			}
		}
		wp_send_json_success( array( 'done' => $done, 'skipped' => $why ) );
	}
}
