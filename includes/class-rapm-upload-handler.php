<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The validated "Add/Edit Asset" admin form — the actual replacement for
 * dropping a raw file into the Media Library. Every image upload here is
 * dimension-checked against its slot BEFORE anything is accepted (a wrong
 * crop is rejected with the exact numbers so it can be fixed), then
 * auto-converted to WebP and compressed toward the slot's size target so
 * the person uploading never has to know what WebP is.
 */
class RAPM_Upload_Handler {

	const NONCE_ACTION = 'rapm_save_asset';

	public static function add_menu() {
		add_submenu_page(
			'edit.php?post_type=rapm_asset',
			__( 'New promotion', 'rapm' ),
			__( 'New promotion', 'rapm' ),
			'edit_posts',
			'rapm-add-asset',
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Loads the same CSS the live carousel uses, only on this one admin
	 * screen, so the on-page preview (image + text overlay together) is a
	 * pixel-accurate WYSIWYG of what actually ships — not a separate
	 * approximation that could itself mislead someone into thinking a
	 * clash is fine when it isn't.
	 */
	public static function enqueue_admin_assets() {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'rapm-add-asset' !== $page ) {
			return;
		}
		wp_enqueue_style( 'rapm-hero-css', RAPM_URL . 'assets/css/rapm-hero.css', array(), RAPM_VERSION );
		// 1.56.0: the "New promotion" gallery and the kind line on the form.
		wp_enqueue_style( 'rapm-gallery-css', RAPM_URL . 'assets/css/rapm-gallery.css', array(), RAPM_VERSION );
		// 1.57.0: the one-screen editor (top bar, pinned preview, Ready checks).
		wp_enqueue_style( 'rapm-editor-css', RAPM_URL . 'assets/css/rapm-editor.css', array(), RAPM_VERSION );
		// 1.58.1: the Reveal panel preview uses the website's own styles.
		wp_enqueue_style( 'rapm-reveal-css', RAPM_URL . 'assets/css/rapm-reveal.css', array(), RAPM_VERSION );
		wp_enqueue_script( 'rapm-editor-js', RAPM_URL . 'assets/js/rapm-editor.js', array(), RAPM_VERSION, true );
		wp_enqueue_script( 'rapm-line-fonts-js', RAPM_URL . 'assets/js/rapm-line-fonts.js', array(), RAPM_VERSION, true ); // 1.62.0.
		wp_localize_script(
			'rapm-editor-js',
			'RAPM_Editor_Text',
			array(
				'done'       => __( '(done)', 'rapm' ),
				'needed'     => __( '(needed to save)', 'rapm' ),
				'notYet'     => __( '(not yet)', 'rapm' ),
				'canSaveNow'   => __( 'Ready to save. It shows as soon as you save.', 'rapm' ),
				'canSaveLater' => __( 'Ready to save. It starts showing on its start date.', 'rapm' ),
				/* translators: %s: what's missing, like "a name and a computer picture" */
				'toSave'     => __( 'To save, add %s.', 'rapm' ),
				'and'        => __( 'and', 'rapm' ),
				'aName'      => __( 'a name', 'rapm' ),
				'aPicture'   => __( 'a computer picture', 'rapm' ),
				'aPhoto'     => __( 'a room photo', 'rapm' ),
				'aWorkingPic' => __( 'a picture link that works (see the red note under it)', 'rapm' ),
				// 1.59.1: a chosen picture this website can't take.
				'aWorkingFile' => __( 'a picture this website can take (see the red note under it)', 'rapm' ),
				/* translators: 1: file name, 2: its size, 3: the most this website takes, like "8 MB" */
				'fileBig'      => __( '%1$s is %2$s. This website takes pictures up to %3$s. Save it as a JPG (most programs: Export › JPG, quality 80) and choose it again.', 'rapm' ),
				/* translators: %s: file name */
				'fileHeic'     => __( '%s is an iPhone photo format websites can\'t show. Save it as a JPG first. On an iPhone: Settings › Camera › Formats › Most Compatible.', 'rapm' ),
				/* translators: 1: file name, 2: its type, like "TIFF" */
				'fileType'     => __( '%1$s is a %2$s file. Use a JPG, PNG, WebP or GIF picture.', 'rapm' ),
				/* translators: 1: both pictures' size together, 2: the most this website takes at once */
				'fileBoth'     => __( 'Together the pictures are %1$s. This website can take %2$s at once. Choose smaller pictures.', 'rapm' ),
				// 1.59.3: a picture made ready in the browser before it's sent.
				/* translators: 1: file name, 2: its size before, 3: size after, 4: "WebP" or "JPG", 5: width × height */
				'fileShrunk'    => __( 'Made smaller for this website: %1$s, %2$s → %3$s %4$s, %5$s. Nothing was cut off.', 'rapm' ),
				/* translators: same as above, for a picture changed from a type the website can't use */
				'fileConverted' => __( 'Changed for this website: %1$s (%2$s) → %3$s %4$s, %5$s. Nothing was cut off.', 'rapm' ),
				/* translators: 1: file name, 2: its size after shrinking, 3: the most this website takes */
				'fileStillBig'  => __( '%1$s is still %2$s after making it smaller, so it would look poor. Use a smaller or simpler picture. This website takes up to %3$s.', 'rapm' ),
				/* translators: %s: file name */
				'fileWorking'   => __( 'Getting %s ready for this website…', 'rapm' ),
				'aPicReady'     => __( 'a moment while the picture gets ready', 'rapm' ),
				'aTab'       => __( 'a tab name', 'rapm' ),
				'aQuickName' => __( 'a name under the picture', 'rapm' ),
				'more'       => __( 'More options: line-up, color, style, typeface', 'rapm' ),
				'fewer'      => __( 'Fewer options', 'rapm' ),
				'sumFail'    => __( 'Something stops it from showing.', 'rapm' ),
				'sumWarn'    => __( 'It can go live. A few things could be better.', 'rapm' ),
				'sumPass'    => __( 'Looks ready.', 'rapm' ),
				'lvlFail'    => __( 'Problem:', 'rapm' ),
				'lvlWarn'    => __( 'Needs a look:', 'rapm' ),
				'lvlInfo'    => __( 'Note:', 'rapm' ),
				'lvlPass'    => __( 'Ready:', 'rapm' ),
				'noPic'      => __( 'No picture yet, so it can\'t show anywhere.', 'rapm' ),
				'addOne'     => __( 'Add one', 'rapm' ),
				/* translators: %s: a size like 1920 × 600 */
				'picRight'   => __( 'Computer picture is the right size (%s).', 'rapm' ),
				/* translators: 1: the picture's size, 2: the size needed */
				'picSmall'   => __( 'Computer picture is %1$s, smaller than the %2$s needed, so it may look soft.', 'rapm' ),
				/* translators: 1: the picture's size, 2: the size needed */
				'picShape'   => __( 'Computer picture is %1$s, a different shape from %2$s. Pick which part to keep under the picture.', 'rapm' ),
				'picLink'    => __( 'The picture comes from a link. It is checked when you save.', 'rapm' ),
				/* translators: 1: the picture's size, 2: the size needed */
				'phoneShape' => __( 'Phone picture is %1$s, a different shape from %2$s. Pick which part to keep under the picture.', 'rapm' ),
				'noPhone'    => __( 'No phone picture: phones show the computer picture fitted inside a tall box.', 'rapm' ),
				'noAlt'      => __( 'No "What\'s in the picture" words, so screen readers say nothing.', 'rapm' ),
				'addThem'    => __( 'Add them', 'rapm' ),
				'noHead'     => __( 'No headline: shoppers see only the picture.', 'rapm' ),
				'noQuick'    => __( 'No name under the picture. It is needed to save.', 'rapm' ),
				'headOk'     => __( 'Has words for shoppers.', 'rapm' ),
				'noLink'     => __( 'No link yet, so clicking it does nothing.', 'rapm' ),
				'pickOne'    => __( 'Pick one', 'rapm' ),
				'linkOk'     => __( 'Link is set.', 'rapm' ),
				'noStart'    => __( 'No start date, so it shows as soon as you save.', 'rapm' ),
				'setDates'   => __( 'Set dates', 'rapm' ),
				'noEnd'      => __( 'No end date, so it keeps showing until you turn it off.', 'rapm' ),
				'datesOk'    => __( 'Has a start and an end date.', 'rapm' ),
				'optional'   => __( '(optional)', 'rapm' ),
				'noWord'     => __( 'No big word, so the panel doesn\'t show.', 'rapm' ),
				'pvTitle'    => __( 'Live preview', 'rapm' ),
				'pvBox'      => __( 'The box as shoppers see it. It changes as you work on the design.', 'rapm' ),
				'pvGraphic'  => __( 'A finished graphic shows whole, as you chose it above.', 'rapm' ),
				'pvReveal'   => __( 'The panel as shoppers see it. Your website\'s fonts show on the real page.', 'rapm' ),
				'closed'     => __( 'Closed', 'rapm' ),
				'open'       => __( 'Open', 'rapm' ),
				'computer'   => __( 'Computer', 'rapm' ),
				'phone'      => __( 'Phone', 'rapm' ),
			)
		);
		// 1.35.0: dots and the button in a banner's Live Preview.
		wp_enqueue_style( 'rapm-pins-css', RAPM_URL . 'assets/css/rapm-pins.css', array( 'rapm-hero-css' ), RAPM_VERSION );
		// Shop the Look's preview (1.30.0) uses the live site's own styles too.
		wp_enqueue_style( 'rapm-looks-css', RAPM_URL . 'assets/css/rapm-looks.css', array(), RAPM_VERSION );
		// Shop the Look's form (1.31.0): Place the Pieces, the photo checks and
		// the preview. Does nothing on other types' forms.
		wp_enqueue_style( 'rapm-look-editor-css', RAPM_URL . 'assets/css/rapm-look-editor.css', array( 'rapm-looks-css' ), RAPM_VERSION );
		wp_enqueue_script( 'rapm-look-editor-js', RAPM_URL . 'assets/js/rapm-look-editor.js', array(), RAPM_VERSION, true );
		// 1.44.0: the promotion box canvas. Does nothing on other types' forms.
		wp_enqueue_style( 'rapm-box-canvas-css', RAPM_URL . 'assets/css/rapm-box-canvas.css', array( 'rapm-hero-css' ), RAPM_VERSION );
		wp_enqueue_script( 'rapm-box-canvas-js', RAPM_URL . 'assets/js/rapm-box-canvas.js', array(), RAPM_VERSION, true );
		// 1.54.0: "What stays in view" (focal points). Does nothing without a picker on the form.
		wp_enqueue_script( 'rapm-focal-js', RAPM_URL . 'assets/js/rapm-focal.js', array(), RAPM_VERSION, true );
		wp_localize_script(
			'rapm-focal-js',
			'RAPM_Focal_Text',
			array(
				'nothing' => __( 'Nothing trimmed', 'rapm' ),
				/* translators: %d: percent */
				'width'   => __( 'Keeps %d%% of the width', 'rapm' ),
				/* translators: %d: percent */
				'height'  => __( 'Keeps %d%% of the height', 'rapm' ),
				/* translators: 1: percent across, 2: percent down */
				'at'      => __( 'Kept in view: %1$s across, %2$s down', 'rapm' ),
			)
		);
		// 1.45.0: "Your pictures" opens WordPress's own Media Library.
		wp_enqueue_media();
	}

	/**
	 * Redirects the native "Add New" screen (which, with no 'thumbnail'/
	 * 'editor' support declared, is just a bare title field) to this
	 * validated form instead — the native screen has no useful path to
	 * actually create a working asset.
	 */
	public static function maybe_redirect_native_add_new() {
		global $pagenow;
		if ( 'post-new.php' === $pagenow && isset( $_GET['post_type'] ) && 'rapm_asset' === $_GET['post_type'] ) {
			wp_safe_redirect( admin_url( 'edit.php?post_type=rapm_asset&page=rapm-add-asset' ) );
			exit;
		}
	}

	/**
	 * Same problem, the "Edit" side: WordPress's own default row action
	 * and clickable title on Promo Manager > All Assets both point at
	 * post.php?action=edit — the native post-edit screen, which for this
	 * post type is just as bare (no image, destination, or schedule
	 * fields live there; every real field is on this plugin's own form).
	 * Without this, the only correct way to edit an existing asset was
	 * the small "Edit" button in the dedicated column added in
	 * class-rapm-admin-list.php — leaving two differently-behaving "Edit"
	 * links on the very same row, one of which silently went nowhere
	 * useful. Redirect the native one to the real form, exactly like
	 * maybe_redirect_native_add_new() already does for "Add New."
	 */
	public static function maybe_redirect_native_edit() {
		global $pagenow;
		if ( 'post.php' !== $pagenow || ! isset( $_GET['action'], $_GET['post'] ) || 'edit' !== $_GET['action'] ) {
			return;
		}
		$post_id = absint( $_GET['post'] );
		if ( $post_id && 'rapm_asset' === get_post_type( $post_id ) ) {
			wp_safe_redirect( admin_url( 'edit.php?post_type=rapm_asset&page=rapm-add-asset&edit=' . $post_id ) );
			exit;
		}
	}

	public static function render_page() {
		// 1.56.0: "New promotion" starts with the gallery (RAPM_Gallery).
		if ( RAPM_Gallery::wanted() ) {
			RAPM_Gallery::render();
			return;
		}
		$asset_id = isset( $_GET['edit'] ) ? absint( $_GET['edit'] ) : 0;
		$is_edit  = $asset_id && 'rapm_asset' === get_post_type( $asset_id );

		// 1.58.1: what was typed before a save the server turned down, so it
		// isn't lost (self::fail()). Pictures have to be chosen again.
		$restore = array();
		if ( isset( $_GET['rapm_restore'] ) ) {
			$restore = get_transient( 'rapm_restore_' . get_current_user_id() );
			$restore = is_array( $restore ) ? $restore : array();
			delete_transient( 'rapm_restore_' . get_current_user_id() );
		}
		$m = function ( $key, $default = '' ) use ( $asset_id, $is_edit, $restore ) {
			$field = 'rapm_' . preg_replace( '/^_rapm_/', '', $key );
			$alias = array( 'rapm_destination_type' => 'rapm_dest_type', 'rapm_destination_value' => 'rapm_dest_value' );
			$field = isset( $alias[ $field ] ) ? $alias[ $field ] : $field;
			if ( isset( $restore[ $field ] ) && is_scalar( $restore[ $field ] ) ) {
				$value = (string) $restore[ $field ];
				if ( in_array( $field, array( 'rapm_canvas', 'rapm_dots', 'rapm_photo_btn' ), true ) ) {
					$json = json_decode( $value, true );
					return is_array( $json ) ? $json : $default;
				}
				return $value;
			}
			if ( ! $is_edit ) {
				return $default;
			}
			$val = get_post_meta( $asset_id, $key, true );
			return '' === $val ? $default : $val;
		};

		$title       = isset( $restore['rapm_title'] ) ? (string) $restore['rapm_title'] : ( $is_edit ? get_the_title( $asset_id ) : '' );
		// A new asset's placement can arrive pre-filled via the URL (the
		// "Add Slide" link from the Sliders dashboard), same idea as the
		// existing ?kind= pre-fill just below.
		$placement_default = ( ! $is_edit && isset( $_GET['placement'] ) ) ? sanitize_title( wp_unslash( $_GET['placement'] ) ) : 'default';
		// 1.58.1: a new spot from the gallery, named when this is first saved.
		$new_spot_name = isset( $_GET['rapm_new_spot_name'] ) ? mb_substr( sanitize_text_field( wp_unslash( $_GET['rapm_new_spot_name'] ) ), 0, 60 ) : '';
		$placement   = $m( '_rapm_placement', $placement_default ?: 'default' );
		$dest_type   = $m( '_rapm_destination_type', 'url' );
		$dest_value  = $m( '_rapm_destination_value' );
		$img_desktop    = (int) $m( '_rapm_image_desktop_id' );
		$img_mobile     = (int) $m( '_rapm_image_mobile_id' );
		$desktop_source = $m( '_rapm_image_desktop_source', 'upload' );
		$desktop_url    = $m( '_rapm_image_desktop_url' );
		$desktop_synced = $m( '_rapm_image_desktop_synced_at' );
		$desktop_error  = $m( '_rapm_image_desktop_sync_error' );
		$mobile_source  = $m( '_rapm_image_mobile_source', 'upload' );
		$mobile_url     = $m( '_rapm_image_mobile_url' );
		$mobile_synced  = $m( '_rapm_image_mobile_synced_at' );
		$mobile_error   = $m( '_rapm_image_mobile_sync_error' );
		$mobile_waiting = (bool) $m( '_rapm_image_mobile_waiting' );
		$slots       = RAPM_Slots::all();
		$kinds       = RAPM_Slots::kinds();

		// Explicitly stored, not inferred from whether the text fields are
		// empty — a brand-new asset and an existing one where "already on
		// the image" was deliberately chosen both have empty fields, and
		// need to be told apart to restore the right choice on re-edit.
		$text_mode = $m( '_rapm_text_mode', 'type' );

		// The kind can come from the URL (switching it before any file is
		// chosen, so the right dimensions show immediately) or from the
		// asset being edited; defaults to 'hero'.
		$kind_key = ! $is_edit && isset( $_GET['kind'] ) ? sanitize_key( wp_unslash( $_GET['kind'] ) ) : $m( '_rapm_kind', 'hero' ); // 1.56.0: a saved one keeps its kind.
		if ( ! isset( $kinds[ $kind_key ] ) ) {
			$kind_key = 'hero';
		}
		// 1.43.0: a Slider set to split view asks for 4:3 computer pictures.
		$kind         = RAPM_Slots::kind_for( $kind_key, $placement );
		$has_images   = (bool) $kind['desktop']; // false for text-only kinds (Marquee)
		// Shop the Look (1.30.0): one room photo of any shape, a tab name,
		// and what each screen shows; words show under the photo, not on it.
		$is_look      = 'look' === $kind_key;
		// 1.35.0: Slider and Feature banner promotions get Place the Pieces
		// too: product dots and the photo button, placed on the desktop and
		// the phone picture separately.
		$has_pins     = in_array( $kind_key, array( 'hero', 'fold_banner' ), true );
		// Quick links (1.37.0): the words go under the picture in the site's
		// own colors, so only the name, the smaller line and the typeface
		// apply. The other rows are hidden rather than removed, since the
		// form's live preview script reads them.
		$is_quick     = 'quick_links' === $kind_key;
		$quick_hide   = $is_quick ? ' style="display:none;"' : '';
		// Promotion box (1.43.0): one picture for every screen too, but
		// with all the word settings of a slide.
		$is_box       = 'promo_box' === $kind_key;
		// Reveal panels (1.53.0): a color, one big word, a photo underneath.
		$is_reveal    = 'reveal' === $kind_key;
		// 1.54.0: pictures the website trims get "What stays in view".
		$focal        = RAPM_Focal::applies( $kind_key, $placement );
		// 1.57.0: kinds edited on one screen with the preview and Ready
		// checks pinned beside the form. Shop the Look, the promotion box and
		// Reveal panels keep their own previews (dragging on the picture),
		// and get the top bar only.
		$simple       = in_array( $kind_key, array( 'hero', 'fold_banner', 'coupon', 'marquee', 'quick_links' ), true );
		// 1.44.0: how a promotion box is made. 'design' is the canvas (words
		// and icons placed by hand); 'graphic' is a finished picture. A new
		// one starts as a design; one saved before 1.44.0 stays a graphic.
		$box_mode     = $is_edit ? RAPM_Split::box_mode( $asset_id ) : 'design';
		$tab_label    = $m( '_rapm_tab_label' );
		$focus        = RAPM_Looks::sanitize_focus( $m( '_rapm_focus', 'center center' ) );
		// 1.36.0: phones have their own position; a look saved before has none and uses the computer one.
		$focus_phone  = $is_edit ? RAPM_Looks::phone_focus( $asset_id, $focus ) : $focus;
		// 1.31.0: the numbered dots (product + position on the photo).
		$dots         = ( $is_look || $has_pins ) ? RAPM_Looks::sanitize_dots( $m( '_rapm_dots', array() ) ) : array();
		// 1.33.0: the optional button on the photo.
		$photo_btn    = ( $is_look || $has_pins ) ? RAPM_Looks::sanitize_photo_btn( $m( '_rapm_photo_btn', array() ) ) : array();
		// 1.39.0: the headline and smaller line on the photo (text="overlay"), on unless turned off.
		$photo_words  = 'off' !== $m( '_rapm_photo_words' );
		// Step numbers. Shop the Look adds "Place the Pieces" as step 2
		// (1.31.0, from the editor mockup Phil approved on 2026-10-06); every
		// other type keeps its four steps.
		$step         = ( $is_look || $has_pins )
			? array( 'basics' => 1, 'pieces' => 2, 'message' => 3, 'link' => 4, 'review' => 5 )
			: array( 'basics' => 1, 'message' => 2, 'link' => 3, 'review' => 4 );
		$desktop_slot = $has_images ? $slots[ $kind['desktop'] ] : null;
		$mobile_slot  = $has_images ? $slots[ $kind['mobile'] ] : null;

		// sanitize_key() is for slugs (lowercase alphanumeric + dashes only)
		// and would silently mangle a real sentence — every space, capital
		// letter, and punctuation mark stripped out. sanitize_text_field()
		// is the correct one for actual human-readable text. No separate
		// url-decode needed here: PHP already decodes $_GET values (fail()
		// below only needs to encode once, when building the redirect URL).
		$error_message = isset( $_GET['rapm_error'] ) ? sanitize_text_field( wp_unslash( $_GET['rapm_error'] ) ) : '';
		?>
		<div class="wrap">
			<?php if ( $is_box || $is_reveal || ( 'hero' === $kind_key && RAPM_Split::is_split( $placement ) ) ) : // 1.46.0: split sliders (and 1.53.0 reveal panels) are worked on in the Design Center. ?>
				<p style="margin:8px 0 0;"><a href="<?php echo esc_url( RAPM_Promotions_Screen::design_url( $placement ) ); ?>">&larr; <?php esc_html_e( 'Design Center', 'rapm' ); ?></a></p>
			<?php else : ?>
				<p style="margin:8px 0 0;"><a href="<?php echo esc_url( RAPM_Promotions_Screen::url() ); ?>">&larr; <?php esc_html_e( 'All promotions', 'rapm' ); ?></a></p>
			<?php endif; ?>
			<h1>
				<?php echo $is_edit && ! get_post_meta( $asset_id, '_rapm_from_gallery', true ) ? esc_html__( 'Edit promotion', 'rapm' ) : esc_html__( 'New promotion', 'rapm' ); // 1.56.0: a gallery copy isn't saved yet. ?>
				<?php if ( ! $is_edit ) : ?>
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=rapm_asset&page=rapm-training-guide' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Training Guide', 'rapm' ); ?></a>
				<?php endif; ?>
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=rapm_asset&page=rapm-help' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Help & FAQ', 'rapm' ); ?></a>
			</h1>
			<style>
				.rapm-dest-picker-results { position: relative; }
				.rapm-dest-picker-list { position: absolute; z-index: 10; margin: 0; padding: 4px 0; list-style: none; background: #fff; border: 1px solid #ccd0d4; box-shadow: 0 2px 6px rgba(0,0,0,.15); max-width: 25em; max-height: 16em; overflow-y: auto; }
				.rapm-dest-picker-list li { padding: 6px 10px; cursor: pointer; }
				.rapm-dest-picker-list li:hover { background: #f0f0f1; }
				#rapm-dest-picker-current { font-weight: 600; }
			</style>

			<?php if ( $error_message ) : ?>
				<div class="notice notice-error"><p><?php echo esc_html( $error_message ); ?><?php if ( $restore ) : ?> <?php esc_html_e( 'What you typed is still here. Pictures need to be chosen again.', 'rapm' ); ?><?php endif; ?></p></div>
			<?php endif; ?>

			<?php if ( isset( $_GET['rapm_new_spot'] ) ) : ?>
				<?php
				$first = array(
					'reveal'      => __( 'Add its first panel.', 'rapm' ),
					'look'        => __( 'Add its first look.', 'rapm' ),
					'hero'        => RAPM_Split::is_split( $placement ) ? __( 'Add its first room photo.', 'rapm' ) : __( 'Add its first picture.', 'rapm' ),
					'quick_links' => __( 'Add its first link.', 'rapm' ),
				);
				?>
				<div class="notice notice-success"><p>
					<?php /* translators: %s: the new spot's name */ ?>
					<strong><?php echo esc_html( sprintf( __( '"%s" is ready.', 'rapm' ), RAPM_Spots::name( $kind_key, $placement ) ) ); ?></strong>
					<?php echo esc_html( isset( $first[ $kind_key ] ) ? $first[ $kind_key ] : __( 'Add its first promotion.', 'rapm' ) ); ?>
					<?php esc_html_e( 'It shows on the website once R&A puts its code on a page (see Setup for R&A at the bottom).', 'rapm' ); ?>
				</p></div>
			<?php endif; ?>

			<?php if ( isset( $_GET['rapm_checked'] ) && ! $desktop_error && ! $mobile_error ) : ?>
				<div class="notice notice-success is-dismissible"><p>
					<?php if ( 'updated' === $_GET['rapm_checked'] ) : ?>
						<strong><?php esc_html_e( 'Updated.', 'rapm' ); ?></strong> <?php esc_html_e( 'A new picture was found at the link and is now live.', 'rapm' ); ?>
					<?php else : ?>
						<strong><?php esc_html_e( 'Checked.', 'rapm' ); ?></strong> <?php esc_html_e( 'The link still has the same picture, so nothing needed to change.', 'rapm' ); ?>
					<?php endif; ?>
				</p></div>
			<?php endif; ?>

			<?php if ( isset( $_GET['rapm_saved'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><strong><?php esc_html_e( 'Saved.', 'rapm' ); ?></strong> <?php esc_html_e( 'If its start date has arrived, it\'s already on every page this spot is on.', 'rapm' ); ?></p></div>
			<?php endif; ?>

			<?php
			// 1.56.0: the kind is picked in the gallery; the old "Type of
			// Promotion" dropdown is gone, and a saved promotion keeps its kind.
			RAPM_Gallery::kind_line( $kind_key, $placement, $is_edit );
			?>
			<?php if ( $is_edit && isset( $_GET['rapm_copied'] ) && get_post_meta( $asset_id, '_rapm_from_gallery', true ) ) : ?>
				<?php $copied_from = get_the_title( absint( $_GET['rapm_copied'] ) ); ?>
				<div class="notice notice-info"><p>
					<?php /* translators: %s: the promotion it was copied from */ ?>
					<strong><?php echo esc_html( sprintf( __( 'This is a copy of "%s".', 'rapm' ), $copied_from ) ); ?></strong>
					<?php esc_html_e( 'Change what you like. It goes on the website when you save it.', 'rapm' ); ?>
					<?php if ( 'ended' === get_post_meta( $asset_id, '_rapm_from_gallery', true ) ) : ?>
						<?php esc_html_e( 'Its old dates had ended, so set new ones in the last step.', 'rapm' ); ?>
					<?php endif; ?>
				</p></div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
				<input type="hidden" name="action" value="rapm_save_asset" />
				<input type="hidden" name="asset_id" value="<?php echo esc_attr( $asset_id ); ?>" />
				<input type="hidden" name="rapm_kind" value="<?php echo esc_attr( $kind_key ); ?>" />
				<?php wp_nonce_field( self::NONCE_ACTION, 'rapm_nonce' ); ?>

				<?php
				// 1.57.0 (mockup Phil approved on 2026-10-22,
				// https://claude.ai/artifact/EL93dNCBz3ip4BvrMDAFgc): one screen
				// instead of the step-by-step wizard, with a bar that stays at the
				// top: a checklist that jumps to each part, and Save.
				$ed_dims = function ( $id ) {
					$meta = $id ? wp_get_attachment_metadata( $id ) : array();
					return ! empty( $meta['width'] ) ? array( 'w' => (int) $meta['width'], 'h' => (int) $meta['height'] ) : null;
				};
				$ed_slot = function ( $slot ) {
					return $slot ? array( 'w' => (int) $slot['width'], 'h' => (int) $slot['height'], 'tol' => (float) ( isset( $slot['aspect_ratio_tolerance'] ) ? $slot['aspect_ratio_tolerance'] : 0.02 ), 'flex' => RAPM_Slots::is_flexible( $slot ) ) : null;
				};
				self::render_editor_bar(
					$step,
					$is_edit && ! get_post_meta( $asset_id, '_rapm_from_gallery', true ),
					array(
						'simple' => $simple,
						'kind'   => $kind_key,
						'src'    => array(
							'desktop' => $img_desktop ? (string) wp_get_attachment_image_url( $img_desktop, 'large' ) : '',
							'mobile'  => $img_mobile ? (string) wp_get_attachment_image_url( $img_mobile, 'large' ) : '',
						),
						'images' => $has_images,
						'look'   => $is_look,
						'quick'  => $is_quick,
						'box'    => $is_box,
						'phone'  => $has_images && ! $is_box && $kind['desktop'] !== $kind['mobile'],
						'slots'  => array( 'desktop' => $ed_slot( $desktop_slot ), 'mobile' => $ed_slot( $mobile_slot ) ),
						'has'    => array( 'desktop' => (bool) $img_desktop, 'mobile' => (bool) $img_mobile ),
						'dims'   => array( 'desktop' => $ed_dims( $img_desktop ), 'mobile' => $ed_dims( $img_mobile ) ),
						// 1.59.1: what this server takes, so a picture that's too big is caught when it's chosen.
						'upload' => array( 'max' => (int) wp_max_upload_size(), 'post' => (int) wp_convert_hr_to_bytes( (string) ini_get( 'post_max_size' ) ), 'maxText' => size_format( wp_max_upload_size() ) ),
						// "More options" starts open when one of them was changed.
						'more'   => 'image' === $text_mode || 'left' !== $m( '_rapm_text_align', 'left' ) || '#ffffff' !== strtolower( (string) $m( '_rapm_text_color', '#ffffff' ) ) || 'bold' !== $m( '_rapm_text_style', 'bold' ) || '' !== (string) $m( '_rapm_text_font' ) || ( $is_edit && RAPM_Line_Fonts::applies( $kind_key ) && array_filter( RAPM_Line_Fonts::get( $asset_id ) ) ),
					)
				);
				?>
				<div class="rapm-ed-layout has-side"><div class="rapm-ed-main">
				<style>
					.rapm-step-heading { color: #8c8f94; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; margin: 0 0 6px; }
				</style>

				<div class="rapm-step" id="rapm-step-1" data-step="1">
				<h2 class="rapm-step-heading">1. <?php esc_html_e( 'Name and picture', 'rapm' ); ?></h2>
				<table class="form-table">
					<tr>
						<th><label for="rapm_title"><?php esc_html_e( 'Name', 'rapm' ); ?></label></th>
						<td><input type="text" id="rapm_title" name="rapm_title" class="regular-text" value="<?php echo esc_attr( $title ); ?>" />
							<?php RAPM_Tip::hint( __( 'Only you see this name. Shoppers don\'t.', 'rapm' ) ); ?>
						</td>
					</tr>
					<tr>
						<th><label for="rapm_placement"><?php esc_html_e( 'Where it shows', 'rapm' ); ?></label></th>
						<td>
							<?php
							// Spots by name (1.29.0) instead of a typed code, so a typo
							// can't quietly start a new spot nobody placed on a page.
							$spot_choices = RAPM_Spots::known( $kind_key );
							$in_list      = false;
							// 1.58.1: a new spot from the gallery has no saved name yet.
							$spot_saved   = RAPM_Spots::get( $kind_key, $placement );
							$pending_name = $new_spot_name && empty( $spot_saved['name'] ) ? $new_spot_name : '';
							foreach ( $spot_choices as $i => $choice ) {
								if ( $choice['placement'] === $placement ) {
									$in_list = true;
									if ( $pending_name ) {
										$spot_choices[ $i ]['name'] = $pending_name;
									}
								}
							}
							if ( ! $in_list ) {
								array_unshift(
									$spot_choices,
									array(
										'placement' => $placement,
										'name'      => $new_spot_name ? $new_spot_name : RAPM_Spots::name( $kind_key, $placement ),
									)
								);
							}
							?>
							<select id="rapm_placement" name="rapm_placement">
								<?php foreach ( $spot_choices as $choice ) : ?>
									<option value="<?php echo esc_attr( $choice['placement'] ); ?>" <?php selected( $placement, $choice['placement'] ); ?>><?php echo esc_html( $choice['name'] ); ?></option>
								<?php endforeach; ?>
							</select>
							<?php if ( $pending_name ) : ?>
								<input type="hidden" name="rapm_new_spot_name" value="<?php echo esc_attr( $new_spot_name ); ?>" />
								<input type="hidden" name="rapm_new_spot_code" value="<?php echo esc_attr( $placement ); ?>" />
							<?php endif; ?>
							<?php RAPM_Tip::hint( __( 'The spot on your website where it shows.', 'rapm' ), __( 'Each spot is on the Promotions page, with the pages it is on. To make a new one, use "Add a spot" at the bottom of that page.', 'rapm' ) ); ?>
							<p class="description" id="rapm-placement-summary"></p>
						</td>
					</tr>
					<?php if ( $is_look ) : ?>
					<tr>
						<th><label for="rapm_tab_label"><?php esc_html_e( 'Tab name', 'rapm' ); ?></label></th>
						<td>
							<input type="text" id="rapm_tab_label" name="rapm_tab_label" class="regular-text" maxlength="24" value="<?php echo esc_attr( $tab_label ); ?>" placeholder="<?php esc_attr_e( 'e.g. Living Room', 'rapm' ); ?>" />
							<?php RAPM_Tip::hint( __( 'The word shoppers click under the photo, like "Living Room".', 'rapm' ), __( 'Keep it short so all the tabs fit. A collection name works too, like "Stanton 338".', 'rapm' ) ); ?>
						</td>
					</tr>
					<?php endif; ?>
				</table>
				<script>
					( function () {
						var placementInput = document.getElementById( 'rapm_placement' );
						var preview         = document.getElementById( 'rapm-shortcode-preview' );
						var summaryEl       = document.getElementById( 'rapm-placement-summary' );
						var shortcodeTag    = <?php echo wp_json_encode( $kind['shortcode'] ); ?>;
						var kindKey         = <?php echo wp_json_encode( $kind_key ); ?>;
						var assetId         = <?php echo (int) $asset_id; ?>;
						var ajaxUrl         = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
						var summaryNonce    = <?php echo wp_json_encode( wp_create_nonce( 'rapm_placement_summary' ) ); ?>;
						var debounceTimer;

						function slugify( value ) {
							return value.toLowerCase().trim().replace( /[^a-z0-9]+/g, '-' ).replace( /^-+|-+$/g, '' );
						}

						function fetchSummary( slug ) {
							clearTimeout( debounceTimer );
							debounceTimer = setTimeout( function () {
								var url = ajaxUrl + '?action=rapm_placement_summary&nonce=' + encodeURIComponent( summaryNonce )
									+ '&kind=' + encodeURIComponent( kindKey ) + '&placement=' + encodeURIComponent( slug )
									+ '&exclude_id=' + encodeURIComponent( assetId );
								fetch( url ).then( function ( r ) { return r.json(); } ).then( function ( res ) {
									if ( ! res.success ) { return; }
									var count = res.data.count;
									if ( 0 === count ) {
										summaryEl.textContent = <?php echo wp_json_encode( $is_look ? __( 'This spot is empty, so this will be its first tab.', 'rapm' ) : ( $is_box ? __( 'This box is empty, so this is the one that will show.', 'rapm' ) : __( 'This spot is empty, so this will be its first picture.', 'rapm' ) ) ); ?>;
										return;
									}
									var names = res.data.titles.map( function ( t ) { return '"' + t + '"'; } ).join( ', ' );
									if ( count > res.data.titles.length ) {
										names += ' +' + ( count - res.data.titles.length ) + <?php echo wp_json_encode( __( ' more', 'rapm' ) ); ?>;
									}
									var lead = 1 === count
										? <?php echo wp_json_encode( __( '1 other promotion already uses this spot', 'rapm' ) ); ?>
										: count + <?php echo wp_json_encode( __( ' other promotions already use this spot', 'rapm' ) ); ?>;
									summaryEl.textContent = lead + ' (' + names + <?php echo wp_json_encode( $is_look ? __( '). Each one is its own tab.', 'rapm' ) : ( $is_box ? __( '). The box shows one at a time.', 'rapm' ) : __( '). They all take turns.', 'rapm' ) ) ); ?>;
								} );
							}, 300 );
						}

						function updatePreview() {
							var slug = slugify( placementInput.value ) || 'default';
							preview.textContent = 'default' === slug ? '[' + shortcodeTag + ']' : '[' + shortcodeTag + ' placement="' + slug + '"]';
							fetchSummary( slug );
						}
						placementInput.addEventListener( 'input', updatePreview );
						placementInput.addEventListener( 'change', updatePreview );
						updatePreview();
					} )();
				</script>

				<?php if ( $has_images && $is_look ) : ?>
				<h2><?php esc_html_e( 'Room Photo', 'rapm' ); ?></h2>
				<?php RAPM_Tip::hint( __( 'One photo of a whole room. Wide photos look best.', 'rapm' ), __( 'Any shape works. Phones use this same photo, so you don\'t need a phone picture.', 'rapm' ) ); ?>
				<?php elseif ( $has_images ) : ?>
				<h2><?php esc_html_e( 'Images', 'rapm' ); ?></h2>
				<?php RAPM_Tip::hint( __( 'Use a JPG, PNG, WebP or GIF picture.', 'rapm' ), __( 'We make it the right size and turn it into a fast web picture for you. If a picture can\'t be used, we say why as soon as you choose it.', 'rapm' ) ); // 1.59.2: "any picture file" contradicted the limits shown under each field. ?>
				<?php if ( $is_box ) : ?>
				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'How is this promotion made?', 'rapm' ); ?></th>
						<td>
							<label style="display:block;margin-bottom:8px;">
								<input type="radio" name="rapm_box_mode" value="design" <?php checked( 'design', $box_mode ); ?> />
								<?php esc_html_e( 'Design it here: place the words and icons yourself under Words below, on a color or a picture', 'rapm' ); ?>
							</label>
							<label style="display:block;">
								<input type="radio" name="rapm_box_mode" value="graphic" <?php checked( 'graphic', $box_mode ); ?> />
								<?php esc_html_e( 'A finished graphic, with its words already on it', 'rapm' ); ?>
							</label>
							<p class="description rapm-box-mode-note" data-mode="design"<?php echo 'design' === $box_mode ? '' : ' hidden'; ?>><?php esc_html_e( 'A picture is optional. You can pick a color under Words instead.', 'rapm' ); ?></p>
							<p class="description rapm-box-mode-note" data-mode="graphic"<?php echo 'graphic' === $box_mode ? '' : ' hidden'; ?>><?php esc_html_e( 'The whole picture always shows. Make it square: 1200 by 1200.', 'rapm' ); ?></p>
						</td>
					</tr>
				</table>
				<?php elseif ( $is_reveal ) : ?>
				<?php RAPM_Tip::hint( __( 'The photo that shows when the panel opens.', 'rapm' ), __( 'Computer photo: 1600 by 1600 (square). Phone photo: 1200 by 1000. The edges get trimmed to fit, so set "What stays in view" below each photo.', 'rapm' ) ); ?>
				<?php elseif ( $is_quick ) : ?>
				<?php RAPM_Tip::hint( __( 'One picture for every screen.', 'rapm' ), __( 'Phones show it small, as a round picture or a little card. Set "What stays in view" below it.', 'rapm' ) ); ?>
				<?php else : ?>
				<?php RAPM_Tip::hint( __( 'Add a computer picture and a phone picture. Each screen shows the right one.', 'rapm' ), __( 'You never need two promotions for one sale. Computers show the computer picture, and phones show the phone picture.', 'rapm' ) ); ?>
				<?php endif; ?>
				<?php endif; ?>
				<?php if ( $has_images ) : ?>
				<table class="form-table">
					<tr>
						<th><label><?php echo $is_look ? esc_html__( 'Room photo', 'rapm' ) : ( $is_box ? esc_html__( 'Promotion picture', 'rapm' ) : esc_html__( 'Computer picture', 'rapm' ) ); ?></label></th>
						<td>
							<?php if ( $img_desktop ) : ?>
								<?php echo wp_get_attachment_image( $img_desktop, array( 240, 75 ), false, array( 'style' => 'display:block;margin-bottom:8px;border-radius:6px;object-fit:cover;' ) ); ?>
							<?php endif; ?>

							<label class="rapm-source-choice"><input type="radio" name="rapm_image_desktop_source" class="rapm-source-radio" data-target="desktop" value="upload" <?php checked( 'upload', $desktop_source ); ?> /> <?php esc_html_e( 'Upload a file', 'rapm' ); ?></label>
							<label class="rapm-source-choice"><input type="radio" name="rapm_image_desktop_source" class="rapm-source-radio" data-target="desktop" value="link" <?php checked( 'link', $desktop_source ); ?> /> <?php esc_html_e( 'Use a link', 'rapm' ); ?></label>

							<div id="rapm-desktop-upload-row" style="margin-top:8px;<?php echo 'link' === $desktop_source ? 'display:none;' : ''; ?>">
								<input type="file" id="rapm_image_desktop" name="rapm_image_desktop" accept="image/*" />
								<?php // 1.59.1: the limit up front, and what the browser finds when a picture is chosen (rapm-editor.js). ?>
								<p class="description rapm-file-limit"><?php echo esc_html( self::file_limit_text() ); ?></p>
								<p class="rapm-file-check" id="rapm-desktop-file-check" aria-live="polite" hidden></p>
							</div>
							<div id="rapm-desktop-link-row" style="margin-top:8px;<?php echo 'link' === $desktop_source ? '' : 'display:none;'; ?>">
								<input type="url" id="rapm_image_desktop_url" name="rapm_image_desktop_url" class="regular-text" value="<?php echo esc_attr( $desktop_url ); ?>" placeholder="https://…" />
								<?php RAPM_Tip::hint( __( 'Paste a Google Drive link, or a link to a picture. Set sharing to "Anyone with the link."', 'rapm' ), __( 'It can be a folder or one picture.', 'rapm' ) ); ?>
								<?php if ( $is_look ) : ?>
								<?php RAPM_Tip::hint( __( 'With a folder, the newest picture is used.', 'rapm' ), __( 'To change the photo, add a new picture to the folder. Any file name works. We check every hour, or click "Check link now".', 'rapm' ) ); ?>
								<?php else : ?>
								<?php RAPM_Tip::hint( __( 'With a folder, the newest picture of the right shape is used.', 'rapm' ), __( 'To change it, add a new picture to the folder. Any file name works. You can use the same folder for both pictures: wide ones go to computers and tall ones to phones. We check every hour, or click "Check link now".', 'rapm' ) ); ?>
								<?php endif; ?>
								<p class="description rapm-link-status" id="rapm-desktop-link-status" aria-live="polite"></p>
								<?php if ( $is_edit && 'link' === $desktop_source ) : ?>
									<p><a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=rapm_check_link_now&asset_id=' . $asset_id ), 'rapm_check_link_now_' . $asset_id ) ); ?>"><?php esc_html_e( 'Check link now', 'rapm' ); ?></a></p>
								<?php endif; ?>
								<?php if ( $desktop_error ) : ?>
									<p class="description" style="color:#b32d2e;"><?php echo esc_html( sprintf( __( 'Couldn\'t update from this link: %s The last picture that worked still shows, so nothing is broken.', 'rapm' ), $desktop_error ) ); ?></p>
								<?php elseif ( $desktop_synced ) : ?>
									<p class="description"><?php echo esc_html( sprintf( __( 'Last checked: %s ago', 'rapm' ), human_time_diff( strtotime( $desktop_synced ) ) ) ); ?></p>
								<?php endif; ?>
							</div>
							<?php // 1.58.2: shared by uploads and one-picture links. ?>
							<?php if ( ! $is_look ) : ?>
							<div id="rapm-desktop-crop-picker" class="rapm-crop-picker" style="display:none;">
								<p class="description"><strong><?php esc_html_e( 'This picture is a different shape.', 'rapm' ); ?></strong> <?php esc_html_e( 'Pick the part to keep, or use a different picture.', 'rapm' ); ?></p>
								<div class="rapm-crop-layout">
									<div class="rapm-crop-preview"><img id="rapm-desktop-crop-preview-img" alt="" /></div>
									<div class="rapm-crop-anchors" role="group" aria-label="<?php esc_attr_e( 'Which part of the picture to keep', 'rapm' ); ?>">
										<button type="button" data-anchor="left top" aria-label="<?php esc_attr_e( 'Top left', 'rapm' ); ?>"></button>
										<button type="button" data-anchor="center top" aria-label="<?php esc_attr_e( 'Top center', 'rapm' ); ?>"></button>
										<button type="button" data-anchor="right top" aria-label="<?php esc_attr_e( 'Top right', 'rapm' ); ?>"></button>
										<button type="button" data-anchor="left center" aria-label="<?php esc_attr_e( 'Middle left', 'rapm' ); ?>"></button>
										<button type="button" data-anchor="center center" class="is-selected" aria-label="<?php esc_attr_e( 'Center', 'rapm' ); ?>"></button>
										<button type="button" data-anchor="right center" aria-label="<?php esc_attr_e( 'Middle right', 'rapm' ); ?>"></button>
										<button type="button" data-anchor="left bottom" aria-label="<?php esc_attr_e( 'Bottom left', 'rapm' ); ?>"></button>
										<button type="button" data-anchor="center bottom" aria-label="<?php esc_attr_e( 'Bottom center', 'rapm' ); ?>"></button>
										<button type="button" data-anchor="right bottom" aria-label="<?php esc_attr_e( 'Bottom right', 'rapm' ); ?>"></button>
									</div>
								</div>
							</div>
							<input type="hidden" id="rapm_image_desktop_crop_anchor" name="rapm_image_desktop_crop_anchor" value="<?php echo esc_attr( 'link' === $desktop_source ? $m( '_rapm_image_desktop_crop_anchor' ) : '' ); ?>" />
							<?php endif; ?>

							<?php if ( $is_look ) : ?>
							<p class="description"><?php echo esc_html( sprintf( __( 'Photos 2000 pixels wide or more look best. Bigger ones are made smaller for you (up to %1$d). It must be at least %2$d pixels wide.', 'rapm' ), $desktop_slot['width'], isset( $desktop_slot['min_width'] ) ? (int) $desktop_slot['min_width'] : 0 ) ); ?></p>
							<p class="description rapm-look-size-note" id="rapm-look-size-note" aria-live="polite"></p>
							<?php else : ?>
							<p class="description"><?php echo esc_html( sprintf( __( 'Size: %1$d by %2$d pixels (width by height). A bigger picture with the same shape is made smaller for you. If the shape is different, we show you what to fix.', 'rapm' ), $desktop_slot['width'], $desktop_slot['height'] ) ); ?></p>
							<?php endif; ?>
							<?php if ( $focal ) : ?>
								<?php RAPM_Focal::render_picker( $kind_key, 'desktop', $img_desktop ? (string) wp_get_attachment_image_url( $img_desktop, 'large' ) : '', $is_edit ? RAPM_Focal::get( $asset_id, 'desktop' ) : '' ); ?>
							<?php endif; ?>
						</td>
					</tr>
					<?php if ( ! $is_look ) : ?>
					<tr<?php echo ( $is_quick || $is_box ) ? ' style="display:none;"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput -- fixed string; Quick links and the promotion box use one picture everywhere. ?>>
						<th><label><?php esc_html_e( 'Phone picture', 'rapm' ); ?></label></th>
						<td>
							<?php if ( $img_mobile ) : ?>
								<?php echo wp_get_attachment_image( $img_mobile, array( 120, 213 ), false, array( 'style' => 'display:block;margin-bottom:8px;border-radius:6px;object-fit:cover;' ) ); ?>
							<?php endif; ?>

							<label class="rapm-source-choice"><input type="radio" name="rapm_image_mobile_source" class="rapm-source-radio" data-target="mobile" value="upload" <?php checked( 'upload', $mobile_source ); ?> /> <?php esc_html_e( 'Upload a file', 'rapm' ); ?></label>
							<label class="rapm-source-choice"><input type="radio" name="rapm_image_mobile_source" class="rapm-source-radio" data-target="mobile" value="link" <?php checked( 'link', $mobile_source ); ?> /> <?php esc_html_e( 'Use a link', 'rapm' ); ?></label>

							<div id="rapm-mobile-upload-row" style="margin-top:8px;<?php echo 'link' === $mobile_source ? 'display:none;' : ''; ?>">
								<input type="file" id="rapm_image_mobile" name="rapm_image_mobile" accept="image/*" />
								<?php // 1.59.1: the limit up front, and what the browser finds when a picture is chosen (rapm-editor.js). ?>
								<p class="description rapm-file-limit"><?php echo esc_html( self::file_limit_text() ); ?></p>
								<p class="rapm-file-check" id="rapm-mobile-file-check" aria-live="polite" hidden></p>
							</div>
							<div id="rapm-mobile-link-row" style="margin-top:8px;<?php echo 'link' === $mobile_source ? '' : 'display:none;'; ?>">
								<input type="url" id="rapm_image_mobile_url" name="rapm_image_mobile_url" class="regular-text" value="<?php echo esc_attr( $mobile_url ); ?>" placeholder="https://…" />
								<?php RAPM_Tip::hint( __( 'Paste a Google Drive link, or a link to a picture. Set sharing to "Anyone with the link."', 'rapm' ), __( 'It can be the same folder as the computer picture. The newest tall picture in it is used here. We check every hour.', 'rapm' ) ); ?>
								<p class="description rapm-link-status" id="rapm-mobile-link-status" aria-live="polite"></p>
								<?php if ( $is_edit && 'link' === $mobile_source && 'link' !== $desktop_source ) : ?>
									<p><a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=rapm_check_link_now&asset_id=' . $asset_id ), 'rapm_check_link_now_' . $asset_id ) ); ?>"><?php esc_html_e( 'Check link now', 'rapm' ); ?></a></p>
								<?php endif; ?>
								<?php if ( $mobile_waiting && ! $mobile_error ) : ?>
									<p class="description"><?php esc_html_e( 'This folder has no tall picture yet, so phones show the computer picture. Add a tall picture to the folder and we\'ll use it.', 'rapm' ); ?></p>
								<?php endif; ?>
								<?php if ( $mobile_error ) : ?>
									<p class="description" style="color:#b32d2e;"><?php echo esc_html( sprintf( __( 'Couldn\'t update from this link: %s The last picture that worked still shows, so nothing is broken.', 'rapm' ), $mobile_error ) ); ?></p>
								<?php elseif ( $mobile_synced ) : ?>
									<p class="description"><?php echo esc_html( sprintf( __( 'Last checked: %s ago', 'rapm' ), human_time_diff( strtotime( $mobile_synced ) ) ) ); ?></p>
								<?php endif; ?>
							</div>
							<?php // 1.58.2: shared by uploads and one-picture links. ?>
							<div id="rapm-mobile-crop-picker" class="rapm-crop-picker" style="display:none;">
								<p class="description"><strong><?php esc_html_e( 'This picture is a different shape.', 'rapm' ); ?></strong> <?php esc_html_e( 'Pick the part to keep, or use a different picture.', 'rapm' ); ?></p>
								<div class="rapm-crop-layout">
									<div class="rapm-crop-preview"><img id="rapm-mobile-crop-preview-img" alt="" /></div>
									<div class="rapm-crop-anchors" role="group" aria-label="<?php esc_attr_e( 'Which part of the picture to keep', 'rapm' ); ?>">
										<button type="button" data-anchor="left top" aria-label="<?php esc_attr_e( 'Top left', 'rapm' ); ?>"></button>
										<button type="button" data-anchor="center top" aria-label="<?php esc_attr_e( 'Top center', 'rapm' ); ?>"></button>
										<button type="button" data-anchor="right top" aria-label="<?php esc_attr_e( 'Top right', 'rapm' ); ?>"></button>
										<button type="button" data-anchor="left center" aria-label="<?php esc_attr_e( 'Middle left', 'rapm' ); ?>"></button>
										<button type="button" data-anchor="center center" class="is-selected" aria-label="<?php esc_attr_e( 'Center', 'rapm' ); ?>"></button>
										<button type="button" data-anchor="right center" aria-label="<?php esc_attr_e( 'Middle right', 'rapm' ); ?>"></button>
										<button type="button" data-anchor="left bottom" aria-label="<?php esc_attr_e( 'Bottom left', 'rapm' ); ?>"></button>
										<button type="button" data-anchor="center bottom" aria-label="<?php esc_attr_e( 'Bottom center', 'rapm' ); ?>"></button>
										<button type="button" data-anchor="right bottom" aria-label="<?php esc_attr_e( 'Bottom right', 'rapm' ); ?>"></button>
									</div>
								</div>
							</div>
							<input type="hidden" id="rapm_image_mobile_crop_anchor" name="rapm_image_mobile_crop_anchor" value="<?php echo esc_attr( 'link' === $mobile_source ? $m( '_rapm_image_mobile_crop_anchor' ) : '' ); ?>" />

							<p class="description"><?php echo esc_html( sprintf( __( 'The picture for phones. Size: exactly %1$d by %2$d pixels.', 'rapm' ), $mobile_slot['width'], $mobile_slot['height'] ) ); ?></p>
							<?php if ( $focal && RAPM_Focal::has_phone( $kind_key ) ) : ?>
								<?php RAPM_Focal::render_picker( $kind_key, 'mobile', $img_mobile ? (string) wp_get_attachment_image_url( $img_mobile, 'large' ) : '', $is_edit ? RAPM_Focal::get( $asset_id, 'mobile' ) : '' ); ?>
							<?php endif; ?>
						</td>
					</tr>
					<?php endif; // ! $is_look ?>
					<tr>
						<th><label for="rapm_alt_text"><?php esc_html_e( 'What\'s in the Picture', 'rapm' ); ?></label></th>
						<td><input type="text" id="rapm_alt_text" name="rapm_alt_text" class="regular-text" value="<?php echo esc_attr( $m( '_rapm_alt_text' ) ); ?>" placeholder="<?php esc_attr_e( 'e.g. Living room with cream sectional and walnut coffee table', 'rapm' ); ?>" />
							<?php RAPM_Tip::hint( __( 'Say what the picture shows, like "Gray sofa in a bright living room."', 'rapm' ), __( 'Don\'t put the sale here; that goes in the words. This helps people who use screen readers, and helps search engines find the picture.', 'rapm' ) ); ?>
						</td>
					</tr>
				</table>
				<style>
					.rapm-source-choice { font-size: 13px; margin-right: 16px; font-weight: normal; }
					.rapm-crop-picker { margin-top: 10px; padding: 10px 12px; background: #fff8e5; border-left: 4px solid #dba617; max-width: 420px; }
					.rapm-crop-layout { display: flex; gap: 14px; align-items: center; margin-top: 8px; }
					.rapm-crop-preview { flex: none; width: 110px; height: 110px; border-radius: 4px; overflow: hidden; background: #333; }
					.rapm-crop-preview img { width: 100%; height: 100%; object-fit: cover; display: block; }
					.rapm-crop-anchors { display: grid; grid-template-columns: repeat(3, 26px); grid-template-rows: repeat(3, 26px); gap: 4px; }
					.rapm-crop-anchors button { width: 26px; height: 26px; padding: 0; border: 1px solid #c3c4c7; border-radius: 3px; background: #fff; cursor: pointer; }
					.rapm-crop-anchors button:hover { border-color: #2271b1; }
					.rapm-crop-anchors button.is-selected { background: #2271b1; border-color: #2271b1; }
					/* 1.54.0: What stays in view. */
					.rapm-focal { margin-top: 14px; padding: 12px; background: #f6f7f7; border: 1px solid #dcdcde; border-radius: 6px; max-width: 900px; }
					.rapm-focal[hidden] { display: none; }
					.rapm-focal-head { margin: 0 0 8px; }
					.rapm-focal-row { display: grid; grid-template-columns: minmax(0, 320px) minmax(0, 1fr); gap: 16px; align-items: start; }
					@media (max-width: 900px) { .rapm-focal-row { grid-template-columns: minmax(0, 1fr); } }
					.rapm-focal-photo { position: relative; cursor: crosshair; touch-action: none; user-select: none; line-height: 0; border-radius: 4px; overflow: hidden; box-shadow: 0 0 0 1px #c3c4c7; }
					.rapm-focal-photo:focus-visible { outline: 2px solid #2271b1; outline-offset: 2px; }
					.rapm-focal-photo img { width: 100%; height: auto; display: block; pointer-events: none; }
					.rapm-focal-dot { position: absolute; width: 24px; height: 24px; margin: -12px 0 0 -12px; border-radius: 50%; border: 3px solid #fff; box-shadow: 0 0 0 2px #e0338a, 0 2px 8px rgba(0,0,0,.4); background: rgba(224,51,138,.35); pointer-events: none; }
					.rapm-focal-pvs { display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end; }
					.rapm-focal-pv { margin: 0; display: grid; gap: 4px; font-size: 12px; color: #50575e; }
					.rapm-focal-pv b { color: #1d2327; font-weight: 600; }
					.rapm-focal-box { display: block; background-size: cover; background-repeat: no-repeat; border-radius: 3px; box-shadow: 0 0 0 1px #c3c4c7; }
					.rapm-focal-box.is-round { border-radius: 50%; }
				</style>
				<script>
					( function () {
						function syncImageSource( target ) {
							var checked = document.querySelector( 'input[name="rapm_image_' + target + '_source"]:checked' );
							var val = checked ? checked.value : 'upload';
							document.getElementById( 'rapm-' + target + '-upload-row' ).style.display = 'link' === val ? 'none' : '';
							document.getElementById( 'rapm-' + target + '-link-row' ).style.display = 'link' === val ? '' : 'none';
						}
						document.querySelectorAll( '.rapm-source-radio' ).forEach( function ( radio ) {
							radio.addEventListener( 'change', function () {
								var target = this.getAttribute( 'data-target' );
								syncImageSource( target );
								// 1.58.2: the crop choice belongs to the picture it was made for.
								var picker = document.getElementById( 'rapm-' + target + '-crop-picker' );
								var anchor = document.getElementById( 'rapm_image_' + target + '_crop_anchor' );
								if ( picker ) { picker.style.display = 'none'; }
								if ( anchor ) { anchor.value = ''; }
							} );
						} );
					} )();
				</script>
				<?php endif; // $has_images ?>
				</div><!-- .rapm-step[data-step="1"] -->

				<?php if ( $is_look || $has_pins ) : ?>
				<div class="rapm-step" id="rapm-step-<?php echo (int) $step['pieces']; ?>" data-step="<?php echo (int) $step['pieces']; ?>">
				<h2 class="rapm-step-heading"><?php echo (int) $step['pieces']; ?>. <?php esc_html_e( 'Place the pieces', 'rapm' ); ?></h2>
				<?php self::render_look_pieces( $img_desktop, $dots, $focus, $photo_btn, $has_pins ? $img_mobile : 0, $has_pins, $focus_phone, $photo_words ); ?>
				</div><!-- .rapm-step: Place the Pieces -->
				<?php endif; ?>

				<div class="rapm-step" id="rapm-step-<?php echo (int) $step['message']; ?>" data-step="<?php echo (int) $step['message']; ?>">
				<h2 class="rapm-step-heading"><?php echo (int) $step['message']; ?>. <?php esc_html_e( 'Words', 'rapm' ); ?></h2>
				<?php if ( $is_look ) : ?>
				<h2><?php esc_html_e( 'Words Under the Tabs', 'rapm' ); ?></h2>
				<?php RAPM_Tip::hint( __( 'These words show under the tabs, in your website\'s fonts and colors.', 'rapm' ), __( 'On some pages the headline and smaller line go on the photo, and the button goes under it. Nothing is added to the picture file.', 'rapm' ) ); ?>
				<?php elseif ( $is_box ) : ?>
				<p class="description rapm-box-mode-note" data-mode="graphic"<?php echo 'graphic' === $box_mode ? '' : ' hidden'; ?>><?php esc_html_e( 'This is a finished graphic, so there\'s nothing to type. To type words and add icons, choose "Design it here" in step 1.', 'rapm' ); ?></p>
				<?php elseif ( $is_reveal ) : ?>
				<h2><?php esc_html_e( 'The Panel', 'rapm' ); ?></h2>
				<?php RAPM_Tip::hint( __( 'Closed, the panel shows its color and big word. Open, it shows the photo, the short line and the link label.', 'rapm' ), __( 'It opens when someone points at it on a computer, or scrolls to it on a phone.', 'rapm' ) ); ?>
				<?php elseif ( $has_images ) : ?>
				<h2><?php esc_html_e( 'Sale Text', 'rapm' ); ?></h2>
				<table class="form-table">
					<tr class="rapm-more">
						<th><?php esc_html_e( 'Does your picture already show the price, sale, or date on it?', 'rapm' ); ?></th>
						<td>
							<label style="display:block;margin-bottom:8px;">
								<input type="radio" name="rapm_text_mode" id="rapm_text_mode_type" value="type" <?php checked( 'type', $text_mode ); ?> />
								<?php esc_html_e( 'No — I\'ll type it below (recommended)', 'rapm' ); ?>
							</label>
							<label style="display:block;">
								<input type="radio" name="rapm_text_mode" id="rapm_text_mode_image" value="image" <?php checked( 'image', $text_mode ); ?> />
								<?php esc_html_e( 'Yes — it\'s already printed on the picture, I don\'t need to type anything below', 'rapm' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'Pick the one that\'s true. If the words are on the picture and typed here, they show twice.', 'rapm' ); ?></p>
						</td>
					</tr>
				</table>
				<?php else : ?>
				<h2><?php esc_html_e( 'Text', 'rapm' ); ?></h2>
				<p class="description"><?php esc_html_e( 'This kind has no picture. Just type the words below.', 'rapm' ); ?></p>
				<?php endif; ?>

				<div id="rapm-text-fields" <?php echo ( $is_box ? 'graphic' === $box_mode : 'image' === $text_mode ) ? 'style="display:none;"' : ''; ?>>
					<table class="form-table">
						<tr>
							<th><label for="rapm_headline"><?php echo $is_quick ? esc_html__( 'Name under the picture', 'rapm' ) : ( $is_reveal ? esc_html__( 'Big word', 'rapm' ) : esc_html__( 'Headline', 'rapm' ) ); ?></label></th>
							<td><input type="text" id="rapm_headline" name="rapm_headline" class="regular-text" value="<?php echo esc_attr( $m( '_rapm_headline' ) ); ?>"<?php echo $is_reveal ? ' maxlength="14"' : ''; ?> placeholder="<?php echo $is_look ? esc_attr__( 'e.g. Stanton 338 Living Room', 'rapm' ) : ( $is_quick ? esc_attr__( 'e.g. Design Services', 'rapm' ) : ( $is_reveal ? esc_attr__( 'e.g. Living', 'rapm' ) : esc_attr__( 'e.g. Labor Day Sale', 'rapm' ) ) ); ?>" />
							<?php if ( $is_reveal ) : ?><p class="description"><?php esc_html_e( 'One short word, up to 14 letters. It shows in big capitals.', 'rapm' ); ?></p><?php endif; ?></td>
						</tr>
						<tr>
							<th><label for="rapm_subhead"><?php echo $is_reveal ? esc_html__( 'Short line (shows with the photo)', 'rapm' ) : esc_html__( 'Smaller line under the headline', 'rapm' ); ?></label></th>
							<td><input type="text" id="rapm_subhead" name="rapm_subhead" class="regular-text" value="<?php echo esc_attr( $m( '_rapm_subhead' ) ); ?>" placeholder="<?php echo $is_look ? esc_attr__( 'e.g. A deep gray sectional with its matching ottoman.', 'rapm' ) : ( $is_quick ? esc_attr__( 'Optional, e.g. Free in-store or in-home consultations', 'rapm' ) : esc_attr__( 'e.g. Up to 30% off sofas and sectionals', 'rapm' ) ); ?>" /></td>
						</tr>
						<tr<?php echo $quick_hide; // phpcs:ignore WordPress.Security.EscapeOutput -- fixed string. ?>>
							<th><label for="rapm_cta_text"><?php echo $is_reveal ? esc_html__( 'Link label (shows with the photo)', 'rapm' ) : esc_html__( 'Button Text', 'rapm' ); ?></label></th>
							<td><input type="text" id="rapm_cta_text" name="rapm_cta_text" value="<?php echo esc_attr( $m( '_rapm_cta_text' ) ); ?>" placeholder="<?php echo $is_look ? esc_attr__( 'e.g. Shop all Living Room', 'rapm' ) : esc_attr__( 'e.g. Shop Now', 'rapm' ); ?>" /></td>
						</tr>
						<?php if ( $is_look ) : // 1.40.0. ?>
						<tr>
							<th><label for="rapm_shelf_text"><?php esc_html_e( 'Words above the button (optional)', 'rapm' ); ?></label></th>
							<td>
								<textarea id="rapm_shelf_text" name="rapm_shelf_text" class="large-text" rows="2" maxlength="300" placeholder="<?php esc_attr_e( 'e.g. Every piece is in our Grants Pass showroom. Ask about free delivery.', 'rapm' ); ?>"><?php echo esc_textarea( $m( '_rapm_shelf_text' ) ); ?></textarea>
								<?php RAPM_Tip::hint( __( 'Shows above the button, beside the products. Leave it blank for just the button.', 'rapm' ), __( 'Use it for something the headline doesn\'t say, like an offer, delivery or financing. It never goes on the photo.', 'rapm' ) ); ?>
							</td>
						</tr>
						<?php endif; ?>
						<?php if ( ! $is_look ) : // Alignment, color, style and typeface are for words printed over a picture. ?>
						<tr class="rapm-more"<?php echo ( $is_quick || $is_box || $is_reveal ) ? ' style="display:none;"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput -- fixed string; the promotion box places and colors each piece on its canvas. ?>>
							<th><label for="rapm_text_align"><?php esc_html_e( 'Text Alignment', 'rapm' ); ?></label></th>
							<td>
								<select id="rapm_text_align" name="rapm_text_align">
									<option value="left" <?php selected( $m( '_rapm_text_align', 'left' ), 'left' ); ?>><?php esc_html_e( 'Left', 'rapm' ); ?></option>
									<option value="center" <?php selected( $m( '_rapm_text_align', 'left' ), 'center' ); ?>><?php esc_html_e( 'Center', 'rapm' ); ?></option>
									<option value="right" <?php selected( $m( '_rapm_text_align', 'left' ), 'right' ); ?>><?php esc_html_e( 'Right', 'rapm' ); ?></option>
								</select>
							</td>
						</tr>
						<tr class="rapm-more"<?php echo ( $is_quick || $is_box ) ? ' style="display:none;"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput -- fixed string. ?>>
							<th><label for="rapm_text_color"><?php echo $is_reveal ? esc_html__( 'Word color', 'rapm' ) : esc_html__( 'Text Color', 'rapm' ); ?></label></th>
							<td>
								<input type="color" id="rapm_text_color" name="rapm_text_color" value="<?php echo esc_attr( $m( '_rapm_text_color', '#ffffff' ) ); ?>" style="height:32px;width:60px;padding:2px;vertical-align:middle;" />
								<p class="description"><?php echo $is_reveal ? esc_html__( 'For the big word, short line and link label. White is easiest to read on most colors.', 'rapm' ) : esc_html__( 'For the headline and smaller line. The button stays white so it\'s easy to read.', 'rapm' ); ?></p>
							</td>
						</tr>
						<?php if ( $is_reveal ) : // 1.53.0. ?>
							<?php
							$panel_color = sanitize_hex_color( (string) $m( '_rapm_panel_color' ) );
							$panel_color = $panel_color ? $panel_color : '#1d5a4c';
							$panel_cg    = sanitize_key( (string) $m( '_rapm_panel_cg' ) );
							$site_colors = RAPM_Brand_Kit::site_colors();
							$kit_colors  = RAPM_Brand_Kit::get()['colors'];
							?>
							<tr>
								<th><label for="rapm_panel_color"><?php esc_html_e( 'Panel color', 'rapm' ); ?></label></th>
								<td>
									<style>.rapm-panel-sw{width:28px;height:28px;border-radius:50%;border:2px solid #fff;box-shadow:0 0 0 1px #c3c4c7;padding:0;cursor:pointer}.rapm-panel-sw[aria-pressed="true"]{box-shadow:0 0 0 2px #2271b1}</style>
									<input type="hidden" id="rapm_panel_cg" name="rapm_panel_cg" value="<?php echo esc_attr( $panel_cg ); ?>" />
									<p class="rapm-panel-swatches" style="display:flex;flex-wrap:wrap;gap:6px;align-items:center;margin:0;">
										<?php foreach ( $site_colors as $c ) : ?>
											<button type="button" class="rapm-panel-sw" data-hex="<?php echo esc_attr( $c['hex'] ); ?>" data-cg="<?php echo esc_attr( $c['id'] ); ?>" title="<?php echo esc_attr( $c['name'] . ' ' . __( '(website color, linked)', 'rapm' ) ); ?>" aria-label="<?php echo esc_attr( $c['name'] ); ?>" aria-pressed="<?php echo $panel_cg === $c['id'] ? 'true' : 'false'; ?>" style="<?php echo esc_attr( 'background:' . $c['hex'] ); ?>"></button>
										<?php endforeach; ?>
										<?php foreach ( $kit_colors as $c ) : ?>
											<button type="button" class="rapm-panel-sw" data-hex="<?php echo esc_attr( $c['hex'] ); ?>" data-cg="" title="<?php echo esc_attr( $c['name'] ? $c['name'] : $c['hex'] ); ?>" aria-label="<?php echo esc_attr( $c['name'] ? $c['name'] : $c['hex'] ); ?>" aria-pressed="<?php echo ( ! $panel_cg && $panel_color === $c['hex'] ) ? 'true' : 'false'; ?>" style="<?php echo esc_attr( 'background:' . $c['hex'] ); ?>"></button>
										<?php endforeach; ?>
										<input type="color" id="rapm_panel_color" name="rapm_panel_color" value="<?php echo esc_attr( $panel_color ); ?>" aria-label="<?php esc_attr_e( 'Any other color', 'rapm' ); ?>" style="height:32px;width:60px;padding:2px;" />
									</p>
									<?php RAPM_Tip::hint( __( 'Website colors come first, and stay linked.', 'rapm' ), __( 'If you change a website color in Elementor, the panel changes too. Next come the brand kit\'s saved colors, or pick any color.', 'rapm' ) ); ?>
									<script>
										( function () {
											var hex = document.getElementById( 'rapm_panel_color' );
											var cg  = document.getElementById( 'rapm_panel_cg' );
											var sws = document.querySelectorAll( '.rapm-panel-sw' );
											function mark( b ) { Array.prototype.forEach.call( sws, function ( s ) { s.setAttribute( 'aria-pressed', s === b ? 'true' : 'false' ); } ); }
											Array.prototype.forEach.call( sws, function ( b ) {
												b.addEventListener( 'click', function () { hex.value = b.getAttribute( 'data-hex' ); cg.value = b.getAttribute( 'data-cg' ); mark( b ); } );
											} );
											hex.addEventListener( 'input', function () { cg.value = ''; mark( null ); } );
										} )();
									</script>
								</td>
							</tr>
							<tr>
								<th><?php esc_html_e( 'Big word reads', 'rapm' ); ?></th>
								<td>
									<label style="margin-right:14px;"><input type="radio" name="rapm_word_dir" value="across" <?php checked( 'side' !== $m( '_rapm_word_dir' ) ); ?> /> <?php esc_html_e( 'Across', 'rapm' ); ?></label>
									<label><input type="radio" name="rapm_word_dir" value="side" <?php checked( 'side' === $m( '_rapm_word_dir' ) ); ?> /> <?php esc_html_e( 'Up the side', 'rapm' ); ?></label>
									<p class="description"><?php esc_html_e( 'Up the side fits a longer word in a narrow panel. Phones always show it across.', 'rapm' ); ?></p>
								</td>
							</tr>
						<?php endif; ?>
						<tr class="rapm-more"<?php echo ( $is_quick || $is_reveal ) ? ' style="display:none;"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput -- fixed string. ?>>
							<th><label for="rapm_text_style"><?php esc_html_e( 'Text Style', 'rapm' ); ?></label></th>
							<td>
								<select id="rapm_text_style" name="rapm_text_style">
									<option value="bold" <?php selected( $m( '_rapm_text_style', 'bold' ), 'bold' ); ?>><?php esc_html_e( 'Bold (default)', 'rapm' ); ?></option>
									<option value="elegant" <?php selected( $m( '_rapm_text_style', 'bold' ), 'elegant' ); ?>><?php esc_html_e( 'Elegant', 'rapm' ); ?></option>
									<option value="minimal" <?php selected( $m( '_rapm_text_style', 'bold' ), 'minimal' ); ?>><?php esc_html_e( 'Minimal', 'rapm' ); ?></option>
								</select>
							</td>
						</tr>
						<?php $site_fonts = RAPM_Elementor::global_fonts(); ?>
						<?php if ( $site_fonts && ! RAPM_Line_Fonts::applies( $kind_key ) ) : // 1.62.0: words on the picture pick a font per line below; words on the page keep this. ?>
							<tr class="rapm-more"<?php echo $is_box ? ' style="display:none;"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput -- fixed string; 1.47.0: the promotion box picks a font per piece on its canvas. ?>>
								<th><label for="rapm_text_font"><?php esc_html_e( 'Typeface', 'rapm' ); ?></label></th>
								<td>
									<select id="rapm_text_font" name="rapm_text_font">
										<option value=""><?php esc_html_e( 'Site Default', 'rapm' ); ?></option>
										<?php foreach ( $site_fonts as $font_id => $font_title ) : ?>
											<option value="<?php echo esc_attr( $font_id ); ?>" <?php selected( $m( '_rapm_text_font' ), $font_id ); ?>><?php echo esc_html( $font_title ); ?></option>
										<?php endforeach; ?>
									</select>
									<?php RAPM_Tip::hint( __( 'These are your website\'s fonts from Elementor.', 'rapm' ), __( 'Picking one keeps the words matching the rest of the site, even if the font changes later. Find them in Elementor, under Site Settings, Global Fonts.', 'rapm' ) ); ?>
								</td>
							</tr>
						<?php endif; ?>
						<?php endif; // ! $is_look ?>
						<?php if ( RAPM_Line_Fonts::applies( $kind_key ) ) : ?>
							<?php
							$line_fonts = $is_edit ? RAPM_Line_Fonts::get( $asset_id ) : array_fill_keys( RAPM_Line_Fonts::LINES, '' );
							foreach ( RAPM_Line_Fonts::LINES as $line ) {
								if ( isset( $restore[ 'rapm_font_' . $line ] ) ) { // A refused save keeps what was picked.
									$line_fonts[ $line ] = RAPM_Line_Fonts::clean( $restore[ 'rapm_font_' . $line ] );
								}
							}
							self::render_line_fonts( $kind_key, $line_fonts );
							?>
						<?php endif; ?>
					</table>
				</div>
				<?php if ( $is_box ) : ?>
					<?php self::render_box_canvas( $box_mode, $img_desktop, $m( '_rapm_canvas', array() ) ); ?>
				<?php endif; ?>
				<?php if ( $has_images && ! $is_look && ! $is_box && ! $is_reveal ) : ?>
				<script>
					( function () {
						var typeFields = document.getElementById( 'rapm-text-fields' );
						var modeType   = document.getElementById( 'rapm_text_mode_type' );
						var modeImage  = document.getElementById( 'rapm_text_mode_image' );
						function sync() {
							typeFields.style.display = modeType.checked ? '' : 'none';
						}
						modeType.addEventListener( 'change', sync );
						modeImage.addEventListener( 'change', sync );
					} )();
				</script>
				<?php endif; ?>
				</div><!-- .rapm-step: Your Message -->

				<div class="rapm-step" id="rapm-step-<?php echo (int) $step['link']; ?>" data-step="<?php echo (int) $step['link']; ?>">
				<h2 class="rapm-step-heading"><?php echo (int) $step['link']; ?>. <?php esc_html_e( 'Link', 'rapm' ); ?></h2>
				<?php if ( $is_look ) : ?>
				<h2><?php esc_html_e( 'Where the Button Goes', 'rapm' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Where the button goes, often a whole room, like the Living Room page. For no button, leave the button words blank under Words.', 'rapm' ); ?></p>
				<?php else : ?>
				<h2><?php esc_html_e( 'Where It Goes When Clicked', 'rapm' ); ?></h2>
				<?php endif; ?>
				<?php $curated = RAPM_Destination::decode_curated_value( 'curated' === $dest_type ? $dest_value : '' ); ?>
				<table class="form-table">
					<tr>
						<th><label for="rapm_dest_type"><?php esc_html_e( 'Send visitors to...', 'rapm' ); ?></label></th>
						<td>
							<select id="rapm_dest_type" name="rapm_dest_type">
								<?php foreach ( RAPM_Destination::types() as $type => $label ) : ?>
									<option value="<?php echo esc_attr( $type ); ?>" <?php selected( $dest_type, $type ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'Not sure? Pick "A specific link". It\'s a normal web address, like the one at the top of your browser.', 'rapm' ); ?></p>
						</td>
					</tr>
					<tr class="rapm-dest-row" data-for="url">
						<th><label for="rapm_dest_url"><?php esc_html_e( 'Web address', 'rapm' ); ?></label></th>
						<td><input type="url" id="rapm_dest_url" class="regular-text rapm-dest-input" data-dest-type="url" value="<?php echo 'url' === $dest_type ? esc_attr( $dest_value ) : ''; ?>" placeholder="https://…" />
							<p class="description"><?php esc_html_e( 'Paste the whole address. It starts with https://', 'rapm' ); ?></p>
						</td>
					</tr>
					<tr class="rapm-dest-row" data-for="post,wc_product,wc_category,wc_brand">
						<th><label for="rapm_dest_picker" id="rapm-dest-picker-label"><?php esc_html_e( 'Start typing to search', 'rapm' ); ?></label></th>
						<td>
							<input type="text" id="rapm_dest_picker" class="regular-text" autocomplete="off" placeholder="<?php esc_attr_e( 'Start typing a name…', 'rapm' ); ?>" />
							<div id="rapm-dest-picker-results" class="rapm-dest-picker-results"></div>
							<p class="description" id="rapm-dest-picker-current"></p>
						</td>
					</tr>
					<tr class="rapm-dest-row" data-for="search">
						<th><label for="rapm_dest_search_words"><?php esc_html_e( 'Search words', 'rapm' ); ?></label></th>
						<td><input type="text" id="rapm_dest_search_words" class="regular-text rapm-dest-input" data-dest-type="search" value="<?php echo 'search' === $dest_type ? esc_attr( $dest_value ) : ''; ?>" placeholder="<?php esc_attr_e( 'e.g. leather sectional', 'rapm' ); ?>" />
							<p class="description"><?php esc_html_e( 'The words a shopper would type in your site\'s search box.', 'rapm' ); ?></p>
						</td>
					</tr>
					<tr class="rapm-dest-row" data-for="curated">
						<th><label for="rapm_curated_picker"><?php esc_html_e( 'Specific products', 'rapm' ); ?></label></th>
						<td>
							<input type="text" id="rapm_curated_picker" class="regular-text" autocomplete="off" placeholder="<?php esc_attr_e( 'Search by product name or SKU…', 'rapm' ); ?>" />
							<div id="rapm-curated-picker-results" class="rapm-dest-picker-results"></div>
							<p class="description"><?php esc_html_e( 'Click a result to add it. They show first, in the order you add them.', 'rapm' ); ?></p>
							<ul id="rapm-curated-selected-list" style="list-style:none;margin:10px 0 0;padding:0;"></ul>
							<textarea id="rapm_curated_skus" name="rapm_curated_skus" rows="3" class="large-text" style="display:none;"><?php echo esc_textarea( implode( "\n", $curated['skus'] ) ); ?></textarea>

							<?php RAPM_Tip::hint( __( 'Lots of SKUs? Upload a spreadsheet (.csv or .xlsx) with a column named "SKU".', 'rapm' ), __( 'If some SKUs start with a zero, set that column to Text in Excel first. If not, Excel may drop the zero.', 'rapm' ) ); ?>
							<input type="file" id="rapm_curated_csv" accept=".csv,.xlsx" />
							<p class="description" id="rapm-curated-csv-status"></p>
							<p style="margin-top:16px;">
								<label for="rapm_curated_fallback_type"><strong><?php esc_html_e( 'Then fill in the rest of the page with...', 'rapm' ); ?></strong></label><br />
								<select id="rapm_curated_fallback_type" name="rapm_curated_fallback_type">
									<option value="none" <?php selected( $curated['fallback_type'], 'none' ); ?>><?php esc_html_e( 'Nothing else — just the SKUs above', 'rapm' ); ?></option>
									<option value="search" <?php selected( $curated['fallback_type'], 'search' ); ?>><?php esc_html_e( 'Search results for some words', 'rapm' ); ?></option>
									<option value="category" <?php selected( $curated['fallback_type'], 'category' ); ?>><?php esc_html_e( 'A product category', 'rapm' ); ?></option>
									<option value="brand" <?php selected( $curated['fallback_type'], 'brand' ); ?>><?php esc_html_e( 'A brand', 'rapm' ); ?></option>
								</select>
							</p>
							<p>
								<input type="text" id="rapm_curated_fallback_value" name="rapm_curated_fallback_value" class="regular-text" value="<?php echo esc_attr( $curated['fallback_value'] ); ?>" placeholder="<?php esc_attr_e( 'e.g. Living Room, or Ashley Furniture, or leather sofa — type it exactly as it appears on the site', 'rapm' ); ?>" />
							</p>
						</td>
					</tr>
				</table>
				<?php
				// 1.57.0 fix: this is the field that's saved. Since 1.3.0 it
				// started empty for a web address or search words, and was only
				// filled when the link was typed, so saving a promotion without
				// retyping its link erased it. A hand-picked list saves its own
				// fields instead.
				?>
				<input type="hidden" id="rapm_dest_value" name="rapm_dest_value" value="<?php echo 'curated' === $dest_type ? '' : esc_attr( $dest_value ); ?>" />
				<script>
					( function () {
						var destType     = document.getElementById( 'rapm_dest_type' );
						var rows         = document.querySelectorAll( '.rapm-dest-row' );
						var hiddenValue  = document.getElementById( 'rapm_dest_value' );
						var picker       = document.getElementById( 'rapm_dest_picker' );
						var pickerLabel  = document.getElementById( 'rapm-dest-picker-label' );
						var resultsBox   = document.getElementById( 'rapm-dest-picker-results' );
						var currentLabel = document.getElementById( 'rapm-dest-picker-current' );
						var ajaxUrl      = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
						var searchNonce  = <?php echo wp_json_encode( wp_create_nonce( 'rapm_search_destination' ) ); ?>;
						var pickerTypes  = [ 'post', 'wc_product', 'wc_category', 'wc_brand' ];
						var debounceTimer;
						var PICKER_TEXT = {
							post:        { label: <?php echo wp_json_encode( __( 'Start typing a page or post name', 'rapm' ) ); ?>, placeholder: <?php echo wp_json_encode( __( 'Start typing a name…', 'rapm' ) ); ?> },
							wc_product:  { label: <?php echo wp_json_encode( __( 'Search by product name or SKU', 'rapm' ) ); ?>, placeholder: <?php echo wp_json_encode( __( 'Search by product name or SKU…', 'rapm' ) ); ?> },
							wc_category: { label: <?php echo wp_json_encode( __( 'Start typing a category name', 'rapm' ) ); ?>, placeholder: <?php echo wp_json_encode( __( 'Start typing a name…', 'rapm' ) ); ?> },
							wc_brand:    { label: <?php echo wp_json_encode( __( 'Start typing a brand name', 'rapm' ) ); ?>, placeholder: <?php echo wp_json_encode( __( 'Start typing a name…', 'rapm' ) ); ?> }
						};

						function syncRows() {
							var current = destType.value;
							rows.forEach( function ( row ) {
								var allowed = row.getAttribute( 'data-for' ).split( ',' );
								row.style.display = allowed.indexOf( current ) === -1 ? 'none' : '';
							} );
							var text = PICKER_TEXT[ current ];
							if ( text ) {
								pickerLabel.textContent = text.label;
								picker.setAttribute( 'placeholder', text.placeholder );
							}
						}

						// The plain url/search-words fields and the picker all
						// share one hidden field, since only one is ever visible
						// (and thus meaningful) for a given destination type.
						document.querySelectorAll( '.rapm-dest-input' ).forEach( function ( el ) {
							el.addEventListener( 'input', function () {
								if ( this.getAttribute( 'data-dest-type' ) === destType.value ) {
									hiddenValue.value = this.value;
								}
							} );
						} );
						destType.addEventListener( 'change', function () {
							syncRows();
							var matching = document.querySelector( '.rapm-dest-input[data-dest-type="' + destType.value + '"]' );
							hiddenValue.value = matching ? matching.value : '';
							currentLabel.textContent = '';
							picker.value = '';
							resultsBox.innerHTML = '';
						} );
						syncRows();

						function renderResults( results ) {
							resultsBox.innerHTML = '';
							if ( ! results.length ) {
								return;
							}
							var list = document.createElement( 'ul' );
							list.className = 'rapm-dest-picker-list';
							results.forEach( function ( item ) {
								var li = document.createElement( 'li' );
								li.textContent = item.label;
								li.addEventListener( 'click', function () {
									hiddenValue.value = item.id;
									picker.value = item.label;
									currentLabel.textContent = <?php echo wp_json_encode( __( 'Selected: ', 'rapm' ) ); ?> + item.label;
									resultsBox.innerHTML = '';
								} );
								list.appendChild( li );
							} );
							resultsBox.appendChild( list );
						}

						picker.addEventListener( 'input', function () {
							var term = this.value.trim();
							clearTimeout( debounceTimer );
							if ( term.length < 2 ) {
								resultsBox.innerHTML = '';
								return;
							}
							debounceTimer = setTimeout( function () {
								var url = ajaxUrl + '?action=rapm_search_destination&nonce=' + encodeURIComponent( searchNonce )
									+ '&type=' + encodeURIComponent( destType.value ) + '&term=' + encodeURIComponent( term );
								fetch( url ).then( function ( r ) { return r.json(); } ).then( function ( res ) {
									if ( res.success ) {
										renderResults( res.data.results );
									}
								} );
							}, 300 );
						} );

						// On load, if editing an asset that already points at a
						// picker-based destination, resolve its ID back to a
						// readable name — only the ID is stored.
						if ( pickerTypes.indexOf( destType.value ) !== -1 && hiddenValue.value ) {
							var resolveUrl = ajaxUrl + '?action=rapm_search_destination&nonce=' + encodeURIComponent( searchNonce )
								+ '&type=' + encodeURIComponent( destType.value ) + '&resolve_id=' + encodeURIComponent( hiddenValue.value );
							fetch( resolveUrl ).then( function ( r ) { return r.json(); } ).then( function ( res ) {
								if ( res.success && res.data.label ) {
									currentLabel.textContent = <?php echo wp_json_encode( __( 'Currently: ', 'rapm' ) ); ?> + res.data.label;
								}
							} );
						}
					} )();
				</script>

				<style>
					.rapm-curated-chip { display: flex; align-items: center; justify-content: space-between; gap: 10px; background: #f0f0f1; border: 1px solid #dcdcde; border-radius: 4px; padding: 6px 10px; margin-bottom: 6px; font-size: 13px; max-width: 420px; }
					.rapm-curated-chip.is-unresolved { border-color: #d63638; }
					.rapm-curated-chip button { background: none; border: none; color: #b32d2e; cursor: pointer; font-size: 15px; line-height: 1; padding: 0 2px; }
				</style>
				<script>
					( function () {
						var picker      = document.getElementById( 'rapm_curated_picker' );
						var resultsBox  = document.getElementById( 'rapm-curated-picker-results' );
						var listEl      = document.getElementById( 'rapm-curated-selected-list' );
						var skusField   = document.getElementById( 'rapm_curated_skus' );
						var ajaxUrl     = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
						var searchNonce = <?php echo wp_json_encode( wp_create_nonce( 'rapm_search_destination' ) ); ?>;
						var selected    = []; // [{ sku, label, found }]
						var debounceTimer;

						function renderChips() {
							listEl.innerHTML = '';
							selected.forEach( function ( item, index ) {
								var li = document.createElement( 'li' );
								li.className = 'rapm-curated-chip' + ( item.found === false ? ' is-unresolved' : '' );
								var span = document.createElement( 'span' );
								span.textContent = item.label;
								var btn = document.createElement( 'button' );
								btn.type = 'button';
								btn.setAttribute( 'aria-label', <?php echo wp_json_encode( __( 'Remove', 'rapm' ) ); ?> );
								btn.textContent = '\u00d7';
								btn.addEventListener( 'click', function () {
									selected.splice( index, 1 );
									renderChips();
								} );
								li.appendChild( span );
								li.appendChild( btn );
								listEl.appendChild( li );
							} );
							skusField.value = selected.map( function ( item ) { return item.sku; } ).join( '\n' );
						}

						function addProduct( sku, label, found ) {
							if ( ! sku || selected.some( function ( item ) { return item.sku === sku; } ) ) {
								return;
							}
							selected.push( { sku: sku, label: label, found: found } );
							renderChips();
						}

						picker.addEventListener( 'input', function () {
							var term = this.value.trim();
							clearTimeout( debounceTimer );
							if ( term.length < 2 ) {
								resultsBox.innerHTML = '';
								return;
							}
							debounceTimer = setTimeout( function () {
								var url = ajaxUrl + '?action=rapm_search_destination&nonce=' + encodeURIComponent( searchNonce )
									+ '&type=wc_product&term=' + encodeURIComponent( term );
								fetch( url ).then( function ( r ) { return r.json(); } ).then( function ( res ) {
									resultsBox.innerHTML = '';
									if ( ! res.success || ! res.data.results.length ) {
										return;
									}
									var list = document.createElement( 'ul' );
									list.className = 'rapm-dest-picker-list';
									res.data.results.forEach( function ( item ) {
										if ( ! item.sku ) {
											return;
										}
										var li = document.createElement( 'li' );
										li.textContent = item.label;
										li.addEventListener( 'click', function () {
											addProduct( item.sku, item.label, true );
											picker.value = '';
											resultsBox.innerHTML = '';
										} );
										list.appendChild( li );
									} );
									resultsBox.appendChild( list );
								} );
							}, 300 );
						} );

						var existingSkus = skusField.value.split( '\n' ).map( function ( s ) { return s.trim(); } ).filter( Boolean );
						if ( existingSkus.length ) {
							var resolveUrl = ajaxUrl + '?action=rapm_search_destination&nonce=' + encodeURIComponent( searchNonce )
								+ '&type=wc_product&resolve_skus=' + encodeURIComponent( existingSkus.join( ',' ) );
							fetch( resolveUrl ).then( function ( r ) { return r.json(); } ).then( function ( res ) {
								if ( res.success ) {
									res.data.results.forEach( function ( item ) {
										addProduct( item.sku, item.found ? item.label : ( <?php echo wp_json_encode( __( 'SKU: ', 'rapm' ) ); ?> + item.label ), item.found );
									} );
								}
							} );
						}

						// Optional bulk path: a client's own spreadsheet of SKUs,
						// with a "SKU" column, instead of searching one at a time.
						var csvInput  = document.getElementById( 'rapm_curated_csv' );
						var csvStatus = document.getElementById( 'rapm-curated-csv-status' );
						csvInput.addEventListener( 'change', function () {
							if ( ! this.files || ! this.files[0] ) { return; }
							var formData = new FormData();
							formData.append( 'action', 'rapm_import_skus' );
							formData.append( 'nonce', searchNonce );
							formData.append( 'file', this.files[0] );
							csvStatus.textContent = <?php echo wp_json_encode( __( 'Reading file…', 'rapm' ) ); ?>;
							fetch( ajaxUrl, { method: 'POST', body: formData } ).then( function ( r ) { return r.json(); } ).then( function ( res ) {
								csvInput.value = '';
								if ( ! res.success ) {
									csvStatus.textContent = res.data && res.data.message ? res.data.message : <?php echo wp_json_encode( __( 'Something went wrong reading that file.', 'rapm' ) ); ?>;
									return;
								}
								res.data.found.forEach( function ( item ) { addProduct( item.sku, item.label, true ); } );

								var parts = [];
								if ( res.data.found.length ) {
									parts.push( res.data.found.length + <?php echo wp_json_encode( ' ' . __( 'added.', 'rapm' ) ); ?> );
								}
								if ( res.data.not_found.length ) {
									parts.push( res.data.not_found.length + <?php echo wp_json_encode( ' ' . __( "weren't found on this site.", 'rapm' ) ); ?> );
								}
								if ( res.data.truncated ) {
									parts.push( <?php echo wp_json_encode( __( 'Only the first 2,000 rows were read — split larger files into smaller ones.', 'rapm' ) ); ?> );
								}
								csvStatus.textContent = parts.join( ' ' );

								if ( res.data.not_found.length ) {
									var link = document.createElement( 'a' );
									link.href = '#';
									link.textContent = <?php echo wp_json_encode( __( 'Download the list of SKUs that weren\'t found', 'rapm' ) ); ?>;
									link.style.marginLeft = '6px';
									link.addEventListener( 'click', function ( e ) {
										e.preventDefault();
										var csvContent = 'SKU\n' + res.data.not_found.join( '\n' );
										var blob = new Blob( [ csvContent ], { type: 'text/csv' } );
										var url = URL.createObjectURL( blob );
										var a = document.createElement( 'a' );
										a.href = url;
										a.download = 'skus-not-found.csv';
										document.body.appendChild( a );
										a.click();
										document.body.removeChild( a );
										URL.revokeObjectURL( url );
									} );
									csvStatus.appendChild( link );
								}
							} ).catch( function () {
								csvStatus.textContent = <?php echo wp_json_encode( __( 'Something went wrong reading that file.', 'rapm' ) ); ?>;
							} );
						} );
					} )();
				</script>

				</div><!-- .rapm-step: Where It Links -->

				<div class="rapm-step" id="rapm-step-<?php echo (int) $step['review']; ?>" data-step="<?php echo (int) $step['review']; ?>">
				<h2 class="rapm-step-heading"><?php echo (int) $step['review']; ?>. <?php esc_html_e( 'Dates', 'rapm' ); ?></h2>

				<?php if ( $is_look ) : ?>
					<div id="rapm-ed-preview"><?php self::render_look_preview(); ?></div>
				<?php elseif ( $has_images ) : ?>
				<?php if ( $is_reveal ) : // 1.53.0: the slider preview doesn't show a reveal panel. ?>
				<style>#rapm-live-preview-head, #rapm-preview-wrap, .rapm-preview-toggle-btn { display: none; }</style>
				<p class="description"><?php esc_html_e( 'Up to four panels show side by side, in the order of their cards in the Design Center. Only panels that are on and in their dates show.', 'rapm' ); ?></p>
				<?php endif; ?>
				<?php if ( $is_box ) : // 1.44.0: the canvas in step 2 is the box's preview. ?>
				<style>#rapm-live-preview-head, #rapm-preview-wrap { display: none; }</style>
				<p class="description"><?php esc_html_e( 'The box shows the first promotion that is on and in its dates. A finished graphic always shows whole.', 'rapm' ); ?></p>
				<?php endif; ?>
				<div id="rapm-ed-preview"><?php // 1.57.0: moved beside the form for one-screen kinds (rapm-editor.js). ?>
				<div id="rapm-live-preview-head">
				<h2><?php esc_html_e( 'Live Preview', 'rapm' ); ?></h2>
				<p class="description"><?php esc_html_e( 'This is what shoppers will see. It changes as you type. If something looks wrong, fix it before you save.', 'rapm' ); ?></p>
				</div>
				<p<?php echo $is_box ? ' style="display:none;"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput -- fixed string; the promotion box has one picture. ?>>
					<button type="button" class="button button-small rapm-preview-toggle-btn" id="rapm-preview-toggle-desktop" aria-pressed="true"><?php esc_html_e( 'Computer', 'rapm' ); ?></button>
					<button type="button" class="button button-small rapm-preview-toggle-btn" id="rapm-preview-toggle-mobile" aria-pressed="false"><?php esc_html_e( 'Phone', 'rapm' ); ?></button>
				</p>
				<div id="rapm-preview-wrap" style="max-width:600px;margin-bottom:24px;transition:max-width .2s;">
					<div id="rapm-preview" class="rapm-hero" style="aspect-ratio:<?php echo esc_attr( $desktop_slot['width'] . '/' . $desktop_slot['height'] ); ?>;background:#333;">
						<div class="rapm-slide" style="width:100%;height:100%;">
							<img id="rapm-preview-img" src="<?php echo $img_desktop ? esc_url( wp_get_attachment_image_url( $img_desktop, 'full' ) ) : ''; ?>" alt="" style="width:100%;height:100%;object-fit:cover;display:<?php echo $img_desktop ? 'block' : 'none'; ?>;" />
							<?php
							$preview_font_map    = RAPM_Elementor::font_family_map();
							$preview_font_family = isset( $preview_font_map[ $m( '_rapm_text_font' ) ] ) ? $preview_font_map[ $m( '_rapm_text_font' ) ] : '';
							?>
							<div class="rapm-slide-copy" id="rapm-preview-copy" data-align="<?php echo esc_attr( $m( '_rapm_text_align', 'left' ) ); ?>" data-style="<?php echo esc_attr( $m( '_rapm_text_style', 'bold' ) ); ?>" style="color:<?php echo esc_attr( $m( '_rapm_text_color', '#ffffff' ) ); ?>;<?php echo $preview_font_family ? 'font-family:' . esc_attr( $preview_font_family ) . ';' : ''; ?>">
								<h2 class="rapm-headline" id="rapm-preview-headline"></h2>
								<p class="rapm-subhead" id="rapm-preview-subhead"></p>
								<span class="rapm-cta-btn" id="rapm-preview-cta"></span>
							</div>
						</div>
					</div>
					<p class="description" id="rapm-preview-empty" style="<?php echo $img_desktop ? 'display:none;' : ''; ?>"><?php esc_html_e( 'Choose a computer picture above to see it here.', 'rapm' ); ?></p>
					<p class="description" id="rapm-preview-no-mobile" style="display:none;color:#b32d2e;"><?php esc_html_e( 'No phone picture yet. Phones show the computer picture until you add one.', 'rapm' ); ?></p>
				</div>
				</div><!-- #rapm-ed-preview -->
				<style>
					.rapm-preview-toggle-btn[aria-pressed="true"] { background: #2271b1; border-color: #2271b1; color: #fff; }
				</style>
				<script>
					( function () {
						var headlineInput = document.getElementById( 'rapm_headline' );
						var subheadInput  = document.getElementById( 'rapm_subhead' );
						var ctaInput      = document.getElementById( 'rapm_cta_text' );
						var alignInput    = document.getElementById( 'rapm_text_align' );
						var colorInput    = document.getElementById( 'rapm_text_color' );
						var styleInput    = document.getElementById( 'rapm_text_style' );
						var desktopFile   = document.getElementById( 'rapm_image_desktop' );
						var mobileFile    = document.getElementById( 'rapm_image_mobile' );
						var desktopUrlInput = document.getElementById( 'rapm_image_desktop_url' );
						var mobileUrlInput  = document.getElementById( 'rapm_image_mobile_url' );
						var previewWrap   = document.getElementById( 'rapm-preview-wrap' );
						var previewBox    = document.getElementById( 'rapm-preview' );
						var previewImg    = document.getElementById( 'rapm-preview-img' );
						var previewCopy   = document.getElementById( 'rapm-preview-copy' );
						var previewEmpty  = document.getElementById( 'rapm-preview-empty' );
						var previewNoMobile = document.getElementById( 'rapm-preview-no-mobile' );
						var toggleDesktop = document.getElementById( 'rapm-preview-toggle-desktop' );
						var toggleMobile  = document.getElementById( 'rapm-preview-toggle-mobile' );

						var desktopRatio = <?php echo wp_json_encode( $desktop_slot['width'] . '/' . $desktop_slot['height'] ); ?>;
						var mobileRatio  = <?php echo wp_json_encode( $mobile_slot['width'] . '/' . $mobile_slot['height'] ); ?>;
						// Read from PHP directly, not previewImg.src — when the
						// src attribute is empty, the DOM resolves .src to the
						// current page's own URL instead of '', which would
						// wrongly count as "an image is set."
						var desktopSrc   = <?php echo $img_desktop ? wp_json_encode( esc_url_raw( wp_get_attachment_image_url( $img_desktop, 'full' ) ) ) : "''"; ?>;
						var mobileSrc    = <?php echo $img_mobile ? wp_json_encode( esc_url_raw( wp_get_attachment_image_url( $img_mobile, 'full' ) ) ) : "''"; ?>;
						var mode         = 'desktop';

						function setText( el, value ) {
							el.textContent = value;
							el.style.display = value ? '' : 'none';
						}
						var fontInput   = document.getElementById( 'rapm_text_font' );
						var fontFamilyMap = <?php echo wp_json_encode( RAPM_Elementor::font_family_map() ); ?>;

						function updateCopy() {
							setText( document.getElementById( 'rapm-preview-headline' ), headlineInput.value );
							setText( document.getElementById( 'rapm-preview-subhead' ), subheadInput.value );
							setText( document.getElementById( 'rapm-preview-cta' ), ctaInput.value );
							previewCopy.setAttribute( 'data-align', alignInput.value );
							previewCopy.setAttribute( 'data-style', styleInput.value );
							previewCopy.style.color = colorInput.value;
							previewCopy.style.fontFamily = ( fontInput && fontFamilyMap[ fontInput.value ] ) ? fontFamilyMap[ fontInput.value ] : '';
						}
						[ headlineInput, subheadInput, ctaInput ].forEach( function ( el ) {
							el.addEventListener( 'input', updateCopy );
						} );
						[ alignInput, styleInput ].forEach( function ( el ) {
							el.addEventListener( 'change', updateCopy );
						} );
						if ( fontInput ) {
							fontInput.addEventListener( 'change', updateCopy );
						}
						colorInput.addEventListener( 'input', updateCopy );
						updateCopy();

						function updateImageDisplay() {
							var isFallback = 'mobile' === mode && ! mobileSrc && !! desktopSrc;
							var showingSrc = 'mobile' === mode && mobileSrc ? mobileSrc : desktopSrc;
							previewImg.src = showingSrc;
							previewImg.style.display = showingSrc ? 'block' : 'none';
							// Matches the live site: a dedicated mobile picture
							// fills the frame edge-to-edge (it was cropped
							// exactly for this shape on purpose); the desktop
							// picture used as a fallback shrinks to fit instead,
							// so it's never cropped down to just its center.
							previewImg.style.objectFit = isFallback ? 'contain' : 'cover';
							// 1.58.2: shows the part picked in the crop picker.
							var anchorEl = document.getElementById( 'rapm_image_' + ( 'mobile' === mode && mobileSrc ? 'mobile' : 'desktop' ) + '_crop_anchor' );
							previewImg.style.objectPosition = anchorEl && anchorEl.value ? anchorEl.value : '';
							previewEmpty.style.display = showingSrc ? 'none' : '';
							previewNoMobile.style.display = isFallback ? '' : 'none';
						}

						function applyMode() {
							previewBox.style.aspectRatio = 'mobile' === mode ? mobileRatio : desktopRatio;
							previewWrap.style.maxWidth = 'mobile' === mode ? '260px' : '600px';
							toggleDesktop.setAttribute( 'aria-pressed', 'desktop' === mode ? 'true' : 'false' );
							toggleMobile.setAttribute( 'aria-pressed', 'mobile' === mode ? 'true' : 'false' );
							updateImageDisplay();
						}
						toggleDesktop.addEventListener( 'click', function () { mode = 'desktop'; applyMode(); } );
						toggleMobile.addEventListener( 'click', function () { mode = 'mobile'; applyMode(); } );

						// Optional crop-anchor picker: only appears when an
						// uploaded picture is a genuinely different SHAPE than
						// the slot needs (not just a different resolution — that
						// case already resizes automatically, server-side, with
						// nothing cropped). Defaults to "center" the moment it
						// appears, so submitting without touching it still
						// works — picking a different anchor is optional
						// fine-tuning, not a requirement.
						// 1.58.2: a one-picture link of a different shape gets the
						// same picker (cropPickers[ which ].show( src )), instead
						// of only a "will be turned down" note.
						var cropPickers = {};
						function setupCropPicker( target, slotWidth, slotHeight, tolerance ) {
							var fileInput  = document.getElementById( 'rapm_image_' + target );
							var picker     = document.getElementById( 'rapm-' + target + '-crop-picker' );
							var previewImg = document.getElementById( 'rapm-' + target + '-crop-preview-img' );
							var anchorField = document.getElementById( 'rapm_image_' + target + '_crop_anchor' );
							if ( ! fileInput || ! picker ) { return; }
							var buttons = picker.querySelectorAll( '.rapm-crop-anchors button' );

							function selectAnchor( anchor ) {
								anchorField.value = anchor;
								previewImg.style.objectPosition = anchor;
								updateImageDisplay();
								Array.prototype.forEach.call( buttons, function ( btn ) {
									btn.classList.toggle( 'is-selected', btn.getAttribute( 'data-anchor' ) === anchor );
								} );
							}
							Array.prototype.forEach.call( buttons, function ( btn ) {
								btn.addEventListener( 'click', function () { selectAnchor( btn.getAttribute( 'data-anchor' ) ); } );
							} );
							cropPickers[ target ] = {
								show: function ( src ) {
									previewImg.src = src;
									selectAnchor( anchorField.value || 'center center' );
									picker.style.display = '';
								},
								hide: function () {
									picker.style.display = 'none';
									anchorField.value = '';
									updateImageDisplay();
								}
							};

							fileInput.addEventListener( 'change', function () {
								picker.style.display = 'none';
								anchorField.value = '';
								if ( ! this.files || ! this.files[0] ) { return; }
								var url = URL.createObjectURL( this.files[0] );
								var probe = new Image();
								probe.onload = function () {
									var targetRatio = slotWidth / slotHeight;
									var actualRatio = probe.naturalWidth / probe.naturalHeight;
									var withinTolerance = Math.abs( actualRatio - targetRatio ) / targetRatio <= tolerance;
									if ( withinTolerance ) { return; } // same shape (or exact match) — no picker needed
									previewImg.src = url;
									selectAnchor( 'center center' );
									picker.style.display = '';
								};
								probe.src = url;
							} );
						}
						setupCropPicker( 'desktop', <?php echo (int) $desktop_slot['width']; ?>, <?php echo (int) $desktop_slot['height']; ?>, <?php echo wp_json_encode( (float) ( $desktop_slot['aspect_ratio_tolerance'] ?? 0.02 ) ); ?> );
						<?php if ( $mobile_slot ) : ?>
						setupCropPicker( 'mobile', <?php echo (int) $mobile_slot['width']; ?>, <?php echo (int) $mobile_slot['height']; ?>, <?php echo wp_json_encode( (float) ( $mobile_slot['aspect_ratio_tolerance'] ?? 0.02 ) ); ?> );
						<?php endif; ?>

						desktopFile.addEventListener( 'change', function () {
							if ( ! this.files || ! this.files[0] ) { return; }
							desktopSrc = URL.createObjectURL( this.files[0] );
							updateImageDisplay();
						} );
						if ( mobileFile ) {
							mobileFile.addEventListener( 'change', function () {
								if ( ! this.files || ! this.files[0] ) { return; }
								mobileSrc = URL.createObjectURL( this.files[0] );
								updateImageDisplay();
							} );
						}
						// Link-sourced images: ask the server for the picture the
						// link really resolves to. Pasting the URL straight into
						// the preview only ever worked for direct image links —
						// a Drive folder link or Drive share link is a web page,
						// not an image, so it showed as a broken picture.
						var linkPreviewNonce = <?php echo wp_json_encode( wp_create_nonce( 'rapm_preview_link' ) ); ?>;
						var linkPreviewAjax  = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
						function setupLinkPreview( input, which ) {
							if ( ! input ) { return; }
							var status = document.getElementById( 'rapm-' + which + '-link-status' );
							var timer = null, seq = 0;
							function show( text, isError ) {
								// 1.58.2: the top bar's checklist reads this, so a link
								// that would be turned down isn't marked done.
								input.setAttribute( 'data-rapm-bad', isError ? '1' : '' );
								if ( ! status ) { return; }
								status.textContent = text;
								status.style.color = isError ? '#b32d2e' : '';
							}
							function check( keepCrop ) {
								var url = input.value.trim();
								var mine = ++seq;
								if ( ! keepCrop && cropPickers[ which ] ) { cropPickers[ which ].hide(); }
								if ( ! /^https?:\/\/\S+$/i.test( url ) ) { show( '', false ); return; }
								show( <?php echo wp_json_encode( __( 'Checking the link…', 'rapm' ) ); ?>, false );
								var body = new FormData();
								body.append( 'action', 'rapm_preview_link' );
								body.append( 'nonce', linkPreviewNonce );
								body.append( 'url', url );
								body.append( 'which', which );
								body.append( 'kind', <?php echo wp_json_encode( $kind_key ); ?> );
								body.append( 'placement', <?php echo wp_json_encode( $placement ); ?> );
								body.append( 'asset_id', <?php echo (int) $asset_id; ?> );
								fetch( linkPreviewAjax, { method: 'POST', body: body, credentials: 'same-origin' } )
									.then( function ( r ) { return r.json(); } )
									.then( function ( res ) {
										if ( mine !== seq ) { return; } // a newer link was typed since
										if ( ! res || ! res.success ) {
											show( ( res && res.data && res.data.message ) || <?php echo wp_json_encode( __( 'Couldn\'t check that link.', 'rapm' ) ); ?>, ! ( res && res.data && res.data.soft ) );
											if ( res && res.data && res.data.soft ) { mobileSrc = ''; updateImageDisplay(); }
											return;
										}
										var d = res.data;
										if ( 'mobile' === which ) { mobileSrc = d.src; } else { desktopSrc = d.src; }
										updateImageDisplay();
										var msg = d.is_folder
											? <?php echo wp_json_encode( __( 'Using the newest picture in the folder that fits: %s', 'rapm' ) ); ?>.replace( '%s', d.name )
											: <?php echo wp_json_encode( __( 'Found the picture: %s', 'rapm' ) ); ?>.replace( '%s', d.name || url );
										var canCrop = ! d.fits && ! d.is_folder && cropPickers[ which ];
										if ( canCrop ) {
											cropPickers[ which ].show( d.src );
											msg += ' ' + <?php echo wp_json_encode( __( 'It is a different shape, so pick the part to keep below.', 'rapm' ) ); ?>;
										} else if ( ! d.fits ) {
											msg += ' ' + <?php echo wp_json_encode( __( '(This picture is a different shape than needed, so it will be turned down when you save.)', 'rapm' ) ); ?>;
										}
										show( msg, ! d.fits && ! canCrop );
									} )
									.catch( function () {
										if ( mine === seq ) { show( <?php echo wp_json_encode( __( 'Couldn\'t check that link.', 'rapm' ) ); ?>, true ); }
									} );
							}
							input.addEventListener( 'input', function () {
								clearTimeout( timer );
								timer = setTimeout( function () { check( false ); }, 700 );
							} );
							// 1.58.2: a saved link with a crop choice shows that choice again.
							var savedAnchor = document.getElementById( 'rapm_image_' + which + '_crop_anchor' );
							if ( savedAnchor && savedAnchor.value && input.value.trim() ) { check( true ); }
						}
						setupLinkPreview( desktopUrlInput, 'desktop' );
						setupLinkPreview( mobileUrlInput, 'mobile' );
					} )();
				</script>
				<?php endif; // $has_images ?>

				<h2><?php esc_html_e( 'When It Should Show', 'rapm' ); ?></h2>
				<table class="form-table">
					<tr>
						<th><label for="rapm_starts_at"><?php esc_html_e( 'Start showing on', 'rapm' ); ?></label></th>
						<td><input type="datetime-local" id="rapm_starts_at" name="rapm_starts_at" value="<?php echo esc_attr( $m( '_rapm_starts_at' ) ); ?>" />
							<p class="description"><?php esc_html_e( 'Leave blank to start right away.', 'rapm' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="rapm_ends_at"><?php esc_html_e( 'Stop showing on', 'rapm' ); ?></label></th>
						<td><input type="datetime-local" id="rapm_ends_at" name="rapm_ends_at" value="<?php echo esc_attr( $m( '_rapm_ends_at' ) ); ?>" />
							<?php RAPM_Tip::hint( __( 'Leave blank to keep it on until you change it.', 'rapm' ), __( 'If you set an end time, it turns off right on time, by itself.', 'rapm' ) ); ?>
						</td>
					</tr>
				</table>

				<?php
				$rapm_on_calendar = $m( '_rapm_starts_at' ) && $m( '_rapm_ends_at' );
				?>
				<div class="rapm-calendar-indicator <?php echo $rapm_on_calendar ? 'is-on' : 'is-off'; ?>" id="rapm-calendar-indicator">
					<p id="rapm-calendar-indicator-text">
						<?php if ( $rapm_on_calendar ) : ?>
							<?php esc_html_e( 'This will show up on your Promotions Calendar.', 'rapm' ); ?>
						<?php else : ?>
							<?php esc_html_e( "This will NOT show up on the Promotions Calendar — it needs both a start date and an end date above. That's fine if you don't use the calendar.", 'rapm' ); ?>
						<?php endif; ?>
					</p>
				</div>
				<style>
					.rapm-calendar-indicator { max-width: 500px; margin: -6px 0 16px; padding: 8px 12px; border-radius: 4px; font-size: 12.5px; }
					.rapm-calendar-indicator p { margin: 0; }
					.rapm-calendar-indicator.is-on { background: #edfaef; color: #00450c; }
					.rapm-calendar-indicator.is-off { background: #f6f7f7; color: #646970; }
				</style>
				<script>
					( function () {
						var startInput = document.getElementById( 'rapm_starts_at' );
						var endInput   = document.getElementById( 'rapm_ends_at' );
						var box        = document.getElementById( 'rapm-calendar-indicator' );
						var text       = document.getElementById( 'rapm-calendar-indicator-text' );
						var ON_TEXT    = <?php echo wp_json_encode( __( 'This will show up on your Promotions Calendar.', 'rapm' ) ); ?>;
						var OFF_TEXT   = <?php echo wp_json_encode( __( "This will NOT show up on the Promotions Calendar — it needs both a start date and an end date above. That's fine if you don't use the calendar.", 'rapm' ) ); ?>;

						function update() {
							var onCalendar = !! ( startInput.value && endInput.value );
							box.classList.toggle( 'is-on', onCalendar );
							box.classList.toggle( 'is-off', ! onCalendar );
							text.textContent = onCalendar ? ON_TEXT : OFF_TEXT;
						}
						startInput.addEventListener( 'input', update );
						endInput.addEventListener( 'input', update );
					} )();
				</script>

				<div class="rapm-ed-setup">
					<h3><?php esc_html_e( 'Setup for R&A', 'rapm' ); ?></h3>
					<p><?php esc_html_e( 'Code for the page:', 'rapm' ); ?> <code id="rapm-shortcode-preview"></code></p>
				</div>
				<?php submit_button( $is_edit ? __( 'Save promotion', 'rapm' ) : __( 'Create promotion', 'rapm' ) ); ?>
				</div><!-- .rapm-step: Review & Schedule -->
				</div><!-- .rapm-ed-main -->
				<aside class="rapm-ed-side" id="rapm-ed-side" aria-label="<?php esc_attr_e( 'Preview and checks', 'rapm' ); ?>">
					<div class="rapm-ed-card" id="rapm-ed-preview-card"></div>
					<div class="rapm-ed-card" id="rapm-ed-ready">
						<h2><?php esc_html_e( 'Ready to go live', 'rapm' ); ?></h2>
						<p class="rapm-ed-ready-sum" id="rapm-ed-ready-sum"></p>
						<ul class="rapm-ed-checks" id="rapm-ed-checks"></ul>
						<p class="description"><?php esc_html_e( 'To check that links work, open Design Center › Asset library and click "Check links".', 'rapm' ); ?></p>
					</div>
				</aside>
				</div><!-- .rapm-ed-layout -->

			</form>
		</div>
		<?php
	}

	/**
	 * The bar at the top of the promotion form (1.57.0): a checklist that
	 * jumps to each part, what's still needed, and Save. rapm-editor.js
	 * fills in the checks; $cfg tells it the kind's needs.
	 */
	private static function render_editor_bar( $step, $is_edit, $cfg ) {
		$chips = array(
			'picture' => array( $step['basics'], __( 'Picture', 'rapm' ) ),
		);
		if ( isset( $step['pieces'] ) ) {
			$chips['pieces'] = array( $step['pieces'], __( 'Pieces', 'rapm' ) );
		}
		$chips['words'] = array( $step['message'], __( 'Words', 'rapm' ) );
		$chips['link']  = array( $step['link'], __( 'Link', 'rapm' ) );
		$chips['dates'] = array( $step['review'], __( 'Dates', 'rapm' ) );
		?>
		<div class="rapm-ed-bar" id="rapm-ed-bar" data-cfg="<?php echo esc_attr( wp_json_encode( $cfg ) ); ?>">
			<div class="rapm-ed-bar-in">
				<nav class="rapm-ed-chips" aria-label="<?php esc_attr_e( 'Parts of this promotion', 'rapm' ); ?>">
					<?php foreach ( $chips as $key => $chip ) : ?>
						<a class="rapm-ed-chip" href="#rapm-step-<?php echo (int) $chip[0]; ?>" data-chip="<?php echo esc_attr( $key ); ?>"><span class="rapm-ed-dot" aria-hidden="true"></span><?php echo esc_html( $chip[1] ); ?><span class="screen-reader-text rapm-ed-sr"></span></a>
					<?php endforeach; ?>
				</nav>
				<span class="rapm-ed-note" id="rapm-ed-note" aria-live="polite"></span>
				<button type="submit" class="button button-primary rapm-ed-save"><?php echo $is_edit ? esc_html__( 'Save promotion', 'rapm' ) : esc_html__( 'Create promotion', 'rapm' ); ?></button>
			</div>
		</div>
		<?php
	}

	public static function handle_save() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'rapm' ) );
		}
		check_admin_referer( self::NONCE_ACTION, 'rapm_nonce' );

		$asset_id = isset( $_POST['asset_id'] ) ? absint( $_POST['asset_id'] ) : 0;
		$is_edit  = $asset_id && 'rapm_asset' === get_post_type( $asset_id );

		// 1.56.0: a new one goes back to its own kind's form (without the
		// kind, it would open the gallery; before 1.56.0, a Slider form).
		$back = $is_edit
			? admin_url( 'edit.php?post_type=rapm_asset&page=rapm-add-asset&edit=' . $asset_id )
			: add_query_arg(
				array(
					'kind'      => isset( $_POST['rapm_kind'] ) ? sanitize_key( wp_unslash( $_POST['rapm_kind'] ) ) : 'hero',
					'placement' => isset( $_POST['rapm_placement'] ) ? sanitize_title( wp_unslash( $_POST['rapm_placement'] ) ) : 'default',
				),
				admin_url( 'edit.php?post_type=rapm_asset&page=rapm-add-asset' )
			);

		$title = isset( $_POST['rapm_title'] ) ? sanitize_text_field( wp_unslash( $_POST['rapm_title'] ) ) : '';
		if ( '' === $title ) {
			self::fail( $back, __( 'Please enter an internal name for this asset.', 'rapm' ) );
		}

		$kinds    = RAPM_Slots::kinds();
		$kind_key = isset( $_POST['rapm_kind'] ) ? sanitize_key( wp_unslash( $_POST['rapm_kind'] ) ) : 'hero';
		if ( ! isset( $kinds[ $kind_key ] ) ) {
			$kind_key = 'hero';
		}
		// 1.56.0: a saved promotion keeps its kind (Phil's pick).
		$saved_kind = $is_edit ? get_post_meta( $asset_id, '_rapm_kind', true ) : '';
		if ( $saved_kind && isset( $kinds[ $saved_kind ] ) ) {
			$kind_key = $saved_kind;
		}
		$kind       = RAPM_Slots::kind_for( $kind_key, isset( $_POST['rapm_placement'] ) ? sanitize_title( wp_unslash( $_POST['rapm_placement'] ) ) : 'default' );
		$has_images = (bool) $kind['desktop'];
		$is_look    = 'look' === $kind_key;
		$has_pins   = in_array( $kind_key, array( 'hero', 'fold_banner' ), true );
		// 1.44.0: a promotion box is designed here or is a finished graphic.
		$is_box     = 'promo_box' === $kind_key;
		$box_mode   = $is_box && isset( $_POST['rapm_box_mode'] ) && 'design' === $_POST['rapm_box_mode'] ? 'design' : 'graphic';

		// Shop the Look (1.30.0): the tab name is what shoppers click, so a
		// look can't be saved without one.
		$tab_label = isset( $_POST['rapm_tab_label'] ) ? mb_substr( sanitize_text_field( wp_unslash( $_POST['rapm_tab_label'] ) ), 0, 24 ) : '';
		if ( $is_look && '' === trim( $tab_label ) ) {
			self::fail( $back, __( 'Please type a tab name for this look, like "Living Room" or "Stanton 338".', 'rapm' ) );
		}

		// Quick links (1.37.0): the name under the picture is what shoppers
		// read and what screen readers announce for the link.
		if ( 'quick_links' === $kind_key ) {
			$quick_name = isset( $_POST['rapm_headline'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['rapm_headline'] ) ) ) : '';
			if ( '' === $quick_name ) {
				self::fail( $back, __( 'Please type the name that goes under the picture, like "Design Services" or "Financing".', 'rapm' ) );
			}
		}

		// Validate + convert images BEFORE touching the post itself, so a
		// bad upload never leaves a half-saved asset behind.
		$slots          = RAPM_Slots::all();
		$new_desktop_id = null;
		$new_mobile_id  = null;
		$desktop_source = 'upload';
		$mobile_source  = 'upload';
		$mobile_waiting = false;
		$desktop_url    = '';
		$mobile_url     = '';

		if ( $has_images ) {
			$desktop_source = isset( $_POST['rapm_image_desktop_source'] ) && 'link' === $_POST['rapm_image_desktop_source'] ? 'link' : 'upload';
			$mobile_source  = isset( $_POST['rapm_image_mobile_source'] ) && 'link' === $_POST['rapm_image_mobile_source'] ? 'link' : 'upload';

			if ( 'link' === $desktop_source ) {
				$desktop_url = isset( $_POST['rapm_image_desktop_url'] ) ? esc_url_raw( wp_unslash( $_POST['rapm_image_desktop_url'] ) ) : '';
				if ( $desktop_url ) {
					$desktop_crop_anchor = isset( $_POST['rapm_image_desktop_crop_anchor'] ) ? sanitize_text_field( wp_unslash( $_POST['rapm_image_desktop_crop_anchor'] ) ) : '';
					$result              = RAPM_Link_Source::fetch_and_validate( $desktop_url, $slots[ $kind['desktop'] ], $asset_id ?: 0, 'desktop', $desktop_crop_anchor );
					if ( is_wp_error( $result ) ) {
						self::fail( $back, $result->get_error_message() );
					}
					$new_desktop_id = $result;
				}
			} elseif ( self::upload_problem( 'rapm_image_desktop' ) ) {
				self::fail( $back, self::upload_problem( 'rapm_image_desktop' ) ); // 1.59.1: it never arrived; say why.
			} elseif ( ! empty( $_FILES['rapm_image_desktop']['tmp_name'] ) ) {
				$desktop_crop_anchor = isset( $_POST['rapm_image_desktop_crop_anchor'] ) ? sanitize_text_field( wp_unslash( $_POST['rapm_image_desktop_crop_anchor'] ) ) : '';
				$result               = self::process_upload( $_FILES['rapm_image_desktop'], $slots[ $kind['desktop'] ], $asset_id ?: 0, $desktop_crop_anchor );
				if ( is_wp_error( $result ) ) {
					self::fail( $back, $result->get_error_message() );
				}
				$new_desktop_id = $result;
			}

			if ( 'link' === $mobile_source ) {
				$mobile_url = isset( $_POST['rapm_image_mobile_url'] ) ? esc_url_raw( wp_unslash( $_POST['rapm_image_mobile_url'] ) ) : '';
				if ( $mobile_url ) {
					$mobile_crop_anchor = isset( $_POST['rapm_image_mobile_crop_anchor'] ) ? sanitize_text_field( wp_unslash( $_POST['rapm_image_mobile_crop_anchor'] ) ) : '';
					$result             = RAPM_Link_Source::fetch_and_validate( $mobile_url, $slots[ $kind['mobile'] ], $asset_id ?: 0, 'mobile', $mobile_crop_anchor );
					if ( RAPM_Link_Source::is_folder_url( $mobile_url ) && RAPM_Link_Source::is_waiting_for_picture( $result ) ) {
						// No tall picture in the folder yet — fine, the mobile
						// picture is optional (see is_waiting_for_picture()).
						$mobile_waiting = true;
					} elseif ( is_wp_error( $result ) ) {
						self::fail( $back, $result->get_error_message() );
					} else {
						$new_mobile_id = $result;
					}
				}
			} elseif ( self::upload_problem( 'rapm_image_mobile' ) ) {
				self::fail( $back, self::upload_problem( 'rapm_image_mobile' ) ); // 1.59.1: it never arrived; say why.
			} elseif ( ! empty( $_FILES['rapm_image_mobile']['tmp_name'] ) ) {
				$mobile_crop_anchor = isset( $_POST['rapm_image_mobile_crop_anchor'] ) ? sanitize_text_field( wp_unslash( $_POST['rapm_image_mobile_crop_anchor'] ) ) : '';
				$result              = self::process_upload( $_FILES['rapm_image_mobile'], $slots[ $kind['mobile'] ], $asset_id ?: 0, $mobile_crop_anchor );
				if ( is_wp_error( $result ) ) {
					self::fail( $back, $result->get_error_message() );
				}
				$new_mobile_id = $result;
			}

			// A desktop image is the one truly required piece — without it
			// the carousel has nothing to show and silently skips this
			// asset entirely (see RAPM_Hero_Carousel::render_slide()).
			// Catch that here with a clear message rather than letting
			// someone publish an asset that will never actually appear
			// anywhere, with no error to explain why.
			$has_desktop_image = $new_desktop_id || ( $is_edit && get_post_meta( $asset_id, '_rapm_image_desktop_id', true ) );
			// A promotion box designed here can be words and icons on a color.
			if ( ! $has_desktop_image && ! ( $is_box && 'design' === $box_mode ) ) {
				if ( $is_look ) {
					$desktop_required_msg = 'link' === $desktop_source
						? __( 'Please paste a link to the room photo. A look needs one to show anywhere.', 'rapm' )
						: __( 'Please upload the room photo. A look needs one to show anywhere.', 'rapm' );
				} else {
					$desktop_required_msg = 'link' === $desktop_source
						? __( 'Please paste a link to the computer picture. The promotion can\'t show without it.', 'rapm' )
						: __( 'Please add the computer picture. The promotion can\'t show without it.', 'rapm' );
				}
				self::fail( $back, $desktop_required_msg );
			}
		} else {
			// Text-only kind — no current kind is image-less, but a site
			// could register one via the rapm_slots/kinds filters, so this
			// stays as a safety net: text is what's required instead.
			$headline_check = isset( $_POST['rapm_headline'] ) ? trim( wp_unslash( $_POST['rapm_headline'] ) ) : '';
			if ( '' === $headline_check ) {
				self::fail( $back, __( 'Please enter the text — it\'s required for this promotion to actually display anywhere.', 'rapm' ) );
			}
		}

		if ( $is_edit ) {
			$update = array( 'ID' => $asset_id, 'post_title' => $title );
			// 1.56.0: a copy made in the gallery is a hidden draft until its first save.
			if ( get_post_meta( $asset_id, '_rapm_from_gallery', true ) ) {
				$update['post_status'] = 'publish';
				delete_post_meta( $asset_id, '_rapm_from_gallery' );
			}
			wp_update_post( $update );
		} else {
			$asset_id = wp_insert_post(
				array(
					'post_title'  => $title,
					'post_type'   => 'rapm_asset',
					'post_status' => 'publish',
				)
			);
			if ( is_wp_error( $asset_id ) || ! $asset_id ) {
				self::fail( $back, __( 'Something went wrong creating this asset. Please try again.', 'rapm' ) );
			}
			// Images were uploaded (and parented to post 0) before this post
			// existed to parent them to — re-parent now so they don't sit in
			// the Media Library looking unattached.
			foreach ( array( $new_desktop_id, $new_mobile_id ) as $attachment_id ) {
				if ( $attachment_id ) {
					wp_update_post( array( 'ID' => $attachment_id, 'post_parent' => $asset_id ) );
				}
			}
			// Same timing problem for folder links: which folder files were
			// already seen can only be stored now that the post exists.
			RAPM_Link_Source::remember_folder_state( $asset_id, 'desktop' );
			RAPM_Link_Source::remember_folder_state( $asset_id, 'mobile' );
		}

		if ( null !== $new_desktop_id ) {
			update_post_meta( $asset_id, '_rapm_image_desktop_id', $new_desktop_id );
		}
		if ( null !== $new_mobile_id ) {
			update_post_meta( $asset_id, '_rapm_image_mobile_id', $new_mobile_id );
		}

		update_post_meta( $asset_id, '_rapm_image_desktop_source', $desktop_source );
		if ( 'link' === $desktop_source && null !== $new_desktop_id ) {
			update_post_meta( $asset_id, '_rapm_image_desktop_url', $desktop_url );
			self::remember_crop( $asset_id, 'desktop', $desktop_url, isset( $desktop_crop_anchor ) ? $desktop_crop_anchor : '' );
			update_post_meta( $asset_id, '_rapm_image_desktop_synced_at', current_time( 'mysql' ) );
			delete_post_meta( $asset_id, '_rapm_image_desktop_sync_error' );
		} elseif ( 'upload' === $desktop_source ) {
			delete_post_meta( $asset_id, '_rapm_image_desktop_url' );
			delete_post_meta( $asset_id, '_rapm_image_desktop_crop_anchor' );
			delete_post_meta( $asset_id, '_rapm_image_desktop_synced_at' );
			delete_post_meta( $asset_id, '_rapm_image_desktop_sync_error' );
		}

		update_post_meta( $asset_id, '_rapm_image_mobile_source', $mobile_source );
		if ( 'link' === $mobile_source && ( null !== $new_mobile_id || $mobile_waiting ) ) {
			update_post_meta( $asset_id, '_rapm_image_mobile_url', $mobile_url );
			self::remember_crop( $asset_id, 'mobile', $mobile_url, isset( $mobile_crop_anchor ) ? $mobile_crop_anchor : '' );
			update_post_meta( $asset_id, '_rapm_image_mobile_synced_at', current_time( 'mysql' ) );
			delete_post_meta( $asset_id, '_rapm_image_mobile_sync_error' );
			if ( $mobile_waiting ) {
				update_post_meta( $asset_id, '_rapm_image_mobile_waiting', 1 );
			} else {
				delete_post_meta( $asset_id, '_rapm_image_mobile_waiting' );
			}
		} elseif ( 'upload' === $mobile_source ) {
			delete_post_meta( $asset_id, '_rapm_image_mobile_waiting' );
			delete_post_meta( $asset_id, '_rapm_image_mobile_url' );
			delete_post_meta( $asset_id, '_rapm_image_mobile_crop_anchor' );
			delete_post_meta( $asset_id, '_rapm_image_mobile_synced_at' );
			delete_post_meta( $asset_id, '_rapm_image_mobile_sync_error' );
		}

		update_post_meta( $asset_id, '_rapm_kind', $kind_key );

		$text_mode = isset( $_POST['rapm_text_mode'] ) && 'image' === $_POST['rapm_text_mode'] ? 'image' : 'type';
		if ( $is_box ) {
			// A finished graphic has its words on it, so none are typed.
			$text_mode = 'graphic' === $box_mode ? 'image' : 'type';
			update_post_meta( $asset_id, '_rapm_box_mode', $box_mode );
			if ( 'design' === $box_mode ) {
				$posted_canvas = isset( $_POST['rapm_canvas'] ) ? json_decode( wp_unslash( $_POST['rapm_canvas'] ), true ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- sanitized by sanitize_canvas().
				$clean_canvas  = RAPM_Split::sanitize_canvas( is_array( $posted_canvas ) ? $posted_canvas : array() );
				update_post_meta( $asset_id, '_rapm_canvas', $clean_canvas );
				// 1.47.0: copy any Google font it uses onto this site, once.
				foreach ( RAPM_Split::canvas_fonts( $clean_canvas ) as $family ) {
					RAPM_Fonts::ensure( $family );
				}
			}
		}
		update_post_meta( $asset_id, '_rapm_text_mode', $text_mode );

		$meta_fields = array(
			'rapm_placement'    => 'sanitize_title',
			'rapm_alt_text'     => 'sanitize_text_field',
			'rapm_headline'     => 'sanitize_text_field',
			'rapm_subhead'      => 'sanitize_text_field',
			'rapm_cta_text'     => 'sanitize_text_field',
			'rapm_shelf_text'   => 'sanitize_textarea_field', // 1.40.0, looks only (the only form that sends it).
			'rapm_text_align'   => array( __CLASS__, 'sanitize_text_align' ),
			'rapm_text_color'   => array( __CLASS__, 'sanitize_text_color' ),
			'rapm_text_style'   => array( __CLASS__, 'sanitize_text_style' ),
			'rapm_text_font'    => array( __CLASS__, 'sanitize_text_font' ),
			'rapm_starts_at'    => 'sanitize_text_field',
			'rapm_ends_at'      => 'sanitize_text_field',
		);
		foreach ( $meta_fields as $field => $sanitizer ) {
			// The text fields are only hidden with CSS when "already on the
			// image" is chosen — the browser still submits whatever value
			// was left in them. Force them empty here rather than trust
			// that, so switching to "already on the image" can't leave a
			// stale headline/subhead/CTA saved underneath it.
			if ( 'image' === $text_mode && in_array( $field, array( 'rapm_headline', 'rapm_subhead', 'rapm_cta_text' ), true ) ) {
				update_post_meta( $asset_id, '_' . $field, '' );
				continue;
			}
			if ( isset( $_POST[ $field ] ) ) {
				update_post_meta( $asset_id, '_' . $field, call_user_func( $sanitizer, wp_unslash( $_POST[ $field ] ) ) );
			}
		}
		// 1.62.0: a font for each line of words on the picture.
		if ( RAPM_Line_Fonts::applies( $kind_key ) ) {
			RAPM_Line_Fonts::save( $asset_id );
		}
		// 1.58.1: a new spot started in the gallery gets its name now.
		$new_spot_name = isset( $_POST['rapm_new_spot_name'] ) ? mb_substr( sanitize_text_field( wp_unslash( $_POST['rapm_new_spot_name'] ) ), 0, 60 ) : '';
		$new_spot_code = isset( $_POST['rapm_new_spot_code'] ) ? sanitize_title( wp_unslash( $_POST['rapm_new_spot_code'] ) ) : '';
		$placed_in     = (string) get_post_meta( $asset_id, '_rapm_placement', true );
		if ( '' !== trim( $new_spot_name ) && $new_spot_code === $placed_in ) {
			$existing = RAPM_Spots::get( $kind_key, $placed_in );
			if ( empty( $existing['name'] ) ) {
				RAPM_Spots::update( $kind_key, $placed_in, array( 'name' => $new_spot_name ) );
			}
		}
		$dest_type = isset( $_POST['rapm_dest_type'] ) ? sanitize_key( wp_unslash( $_POST['rapm_dest_type'] ) ) : 'url';
		update_post_meta( $asset_id, '_rapm_destination_type', $dest_type );

		switch ( $dest_type ) {
			case 'url':
				$dest_value = isset( $_POST['rapm_dest_value'] ) ? esc_url_raw( wp_unslash( $_POST['rapm_dest_value'] ) ) : '';
				break;
			case 'search':
				$dest_value = isset( $_POST['rapm_dest_value'] ) ? sanitize_text_field( wp_unslash( $_POST['rapm_dest_value'] ) ) : '';
				break;
			case 'post':
			case 'wc_product':
			case 'wc_category':
			case 'wc_brand':
				$dest_value = isset( $_POST['rapm_dest_value'] ) ? absint( $_POST['rapm_dest_value'] ) : 0;
				break;
			case 'curated':
				$dest_value = RAPM_Destination::build_curated_value(
					isset( $_POST['rapm_curated_skus'] ) ? wp_unslash( $_POST['rapm_curated_skus'] ) : '',
					isset( $_POST['rapm_curated_fallback_type'] ) ? sanitize_key( wp_unslash( $_POST['rapm_curated_fallback_type'] ) ) : 'none',
					isset( $_POST['rapm_curated_fallback_value'] ) ? wp_unslash( $_POST['rapm_curated_fallback_value'] ) : ''
				);
				break;
			default:
				$dest_value = '';
		}
		update_post_meta( $asset_id, '_rapm_destination_value', $dest_value );
		// 1.33.1: a hand-picked list needs the results page; make sure it exists.
		if ( 'curated' === $dest_type ) {
			RAPM_Curated_Results::ensure_page();
		}
		if ( $is_look || $has_pins ) {
			// 1.31.0: the numbered dots, as array( p => product ID, x, y => 0-100 % of the photo ).
			// 1.35.0: on a Slider or Feature banner also mx, my for the phone picture.
			$posted_dots = isset( $_POST['rapm_dots'] ) ? json_decode( wp_unslash( $_POST['rapm_dots'] ), true ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- sanitized by sanitize_dots().
			update_post_meta( $asset_id, '_rapm_dots', RAPM_Looks::sanitize_dots( is_array( $posted_dots ) ? $posted_dots : array(), true ) );
			// 1.33.0: the photo button, as array( on, x, y ), or nothing when it's off.
			$posted_btn = isset( $_POST['rapm_photo_btn'] ) ? json_decode( wp_unslash( $_POST['rapm_photo_btn'] ), true ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- sanitized by sanitize_photo_btn().
			$photo_btn  = RAPM_Looks::sanitize_photo_btn( $posted_btn );
			if ( $photo_btn ) {
				update_post_meta( $asset_id, '_rapm_photo_btn', $photo_btn );
			} else {
				delete_post_meta( $asset_id, '_rapm_photo_btn' );
			}
		}
		if ( $is_look ) {
			// 1.39.0: the headline and smaller line on the photo; saved only when turned off.
			if ( isset( $_POST['rapm_photo_words'] ) ) {
				if ( 'off' === $_POST['rapm_photo_words'] ) {
					update_post_meta( $asset_id, '_rapm_photo_words', 'off' );
				} else {
					delete_post_meta( $asset_id, '_rapm_photo_words' );
				}
			}
			update_post_meta( $asset_id, '_rapm_tab_label', $tab_label );
			update_post_meta( $asset_id, '_rapm_focus', RAPM_Looks::sanitize_focus( isset( $_POST['rapm_focus'] ) ? sanitize_text_field( wp_unslash( $_POST['rapm_focus'] ) ) : '' ) );
			update_post_meta( $asset_id, '_rapm_focus_phone', RAPM_Looks::sanitize_focus( isset( $_POST['rapm_focus_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['rapm_focus_phone'] ) ) : '' ) );
		}
		// 1.54.0: what stays in view, for each picture the website trims.
		if ( RAPM_Focal::applies( $kind_key, (string) get_post_meta( $asset_id, '_rapm_placement', true ) ) ) {
			foreach ( array( 'desktop', 'mobile' ) as $which ) {
				if ( isset( $_POST[ 'rapm_focus_' . $which ] ) ) {
					RAPM_Focal::save( $asset_id, $which, sanitize_text_field( wp_unslash( $_POST[ 'rapm_focus_' . $which ] ) ) );
				}
			}
		}
		if ( 'reveal' === $kind_key ) {
			// 1.53.0: the panel's color (a linked website color, or a fixed one) and the word's direction.
			$panel_color = isset( $_POST['rapm_panel_color'] ) ? sanitize_hex_color( wp_unslash( $_POST['rapm_panel_color'] ) ) : '';
			update_post_meta( $asset_id, '_rapm_panel_color', $panel_color ? $panel_color : '#1d5a4c' );
			$panel_cg = isset( $_POST['rapm_panel_cg'] ) ? sanitize_key( wp_unslash( $_POST['rapm_panel_cg'] ) ) : '';
			if ( $panel_cg ) {
				update_post_meta( $asset_id, '_rapm_panel_cg', $panel_cg );
			} else {
				delete_post_meta( $asset_id, '_rapm_panel_cg' );
			}
			update_post_meta( $asset_id, '_rapm_word_dir', isset( $_POST['rapm_word_dir'] ) && 'side' === $_POST['rapm_word_dir'] ? 'side' : 'across' );
		}
		if ( empty( get_post_meta( $asset_id, '_rapm_placement', true ) ) ) {
			update_post_meta( $asset_id, '_rapm_placement', 'default' );
		}

		// 1.58.1: room photos of a split slider, promotion boxes and reveal
		// panels are worked on in the Design Center, so saving goes back there.
		$saved_place = (string) get_post_meta( $asset_id, '_rapm_placement', true );
		if ( 'promo_box' === $kind_key || 'reveal' === $kind_key || ( 'hero' === $kind_key && RAPM_Split::is_split( $saved_place ) ) ) {
			$dc = RAPM_Promotions_Screen::design_url();
			if ( 'reveal' === $kind_key ) {
				$dc = add_query_arg( 'tab', 'reveal', $dc );
			}
			$dc = add_query_arg( 'rapm_saved_asset', $asset_id, $dc );
			wp_safe_redirect( $dc . '#rapm-spot-' . ( 'reveal' === $kind_key ? 'reveal' : 'hero' ) . '-' . sanitize_html_class( $saved_place ) );
			exit;
		}
		wp_safe_redirect( admin_url( 'edit.php?post_type=rapm_asset&page=rapm-add-asset&edit=' . $asset_id . '&rapm_saved=1' ) );
		exit;
	}

	/**
	 * Validates one uploaded file against a slot's required dimensions
	 * (hard reject — a wrong crop needs a human, not software, to fix),
	 * then converts it to WebP/compresses it and sideloads it into the
	 * Media Library. Returns the new attachment ID, or a WP_Error with a
	 * message stating the slot's requirement against what was actually
	 * uploaded.
	 */
	/**
	 * 1.58.2: keeps the crop choice for a one-picture link, so the hourly
	 * check trims a changed picture the same way. Folders and pictures of
	 * the right shape keep none.
	 */
	private static function remember_crop( $asset_id, $which, $url, $anchor ) {
		if ( $anchor && ! RAPM_Link_Source::is_folder_url( $url ) ) {
			update_post_meta( $asset_id, '_rapm_image_' . $which . '_crop_anchor', $anchor );
		} else {
			delete_post_meta( $asset_id, '_rapm_image_' . $which . '_crop_anchor' );
		}
	}

	private static function process_upload( $file, $slot, $parent_id, $crop_anchor = '' ) {
		if ( ! empty( $file['error'] ) && UPLOAD_ERR_OK !== $file['error'] ) {
			return new WP_Error( 'rapm_upload_error', __( 'The file failed to upload — please try again.', 'rapm' ) );
		}
		return self::validate_convert_sideload( $file['tmp_name'], $file['name'], $slot, $parent_id, $crop_anchor );
	}

	/**
	 * The core validation/conversion/sideload pipeline — starting from any
	 * image file already sitting on disk, regardless of how it got there.
	 * process_upload() (a posted file) and RAPM_Link_Source (a downloaded
	 * URL) both funnel through this one method, so a link-sourced image is
	 * never held to a looser standard than a directly uploaded one.
	 *
	 * The actual file type is read from the file's own bytes
	 * (getimagesize()'s mime), not trusted from $original_filename's
	 * extension — a downloaded URL often has no useful filename at all
	 * (e.g. a Google Drive export link), so extension-sniffing would
	 * reject perfectly good images.
	 */
	public static function validate_convert_sideload( $tmp_path, $original_filename, $slot, $parent_id, $crop_anchor = '' ) {
		$dims = getimagesize( $tmp_path );
		if ( ! $dims ) {
			return new WP_Error( 'rapm_unreadable', self::type_message( $original_filename, __( 'Could not read that image file.', 'rapm' ) ) );
		}
		list( $width, $height ) = $dims;
		$mime = isset( $dims['mime'] ) ? $dims['mime'] : '';

		if ( ! in_array( $mime, array( 'image/jpeg', 'image/png', 'image/webp', 'image/gif' ), true ) ) {
			return new WP_Error( 'rapm_not_image', self::type_message( $original_filename, __( 'That file doesn\'t look like an image.', 'rapm' ) ) );
		}

		$was_resized = false;

		if ( RAPM_Slots::is_flexible( $slot ) ) {
			// Shop the Look's room photo (1.30.0): any shape is fine. Too
			// narrow is turned down; wider than the slot is scaled down,
			// keeping its shape, so nothing is cropped or stretched.
			$min_width = isset( $slot['min_width'] ) ? (int) $slot['min_width'] : 0;
			if ( $width < $min_width ) {
				return new WP_Error(
					'rapm_too_small',
					sprintf(
						/* translators: 1: the photo's width, 2: the smallest width allowed */
						__( 'This photo is %1$d pixels wide. A room photo needs to be at least %2$d pixels wide, and 2000 or more looks best. Please use a bigger photo.', 'rapm' ),
						$width,
						$min_width
					)
				);
			}
			if ( $width > (int) $slot['width'] ) {
				if ( ! RAPM_Webp_Converter::is_available() ) {
					return new WP_Error( 'rapm_no_webp_support', __( 'This server can\'t auto-resize or auto-convert images (no Imagick or GD support found). Please crop or re-export this to the exact size and try again.', 'rapm' ) );
				}
				$target_height = max( 1, (int) round( $height * (int) $slot['width'] / $width ) );
				$resized       = RAPM_Webp_Converter::resize_to( $tmp_path, (int) $slot['width'], $target_height );
				if ( is_wp_error( $resized ) ) {
					return $resized;
				}
				$tmp_path    = $resized;
				$was_resized = true;
			}
		} elseif ( ! RAPM_Slots::dimensions_match( $slot, $width, $height ) ) {
			if ( ! RAPM_Slots::aspect_ratio_matches( $slot, $width, $height ) ) {
				$valid_anchors = array( 'left top', 'center top', 'right top', 'left center', 'center center', 'right center', 'left bottom', 'center bottom', 'right bottom' );
				if ( $crop_anchor && in_array( $crop_anchor, $valid_anchors, true ) ) {
					// A genuinely different shape — only cropped when the
					// person uploading explicitly picked which part to
					// keep via the crop-anchor picker. No anchor means no
					// crop: falls through to the same hard rejection as
					// before, unchanged.
					if ( ! RAPM_Webp_Converter::is_available() ) {
						return new WP_Error( 'rapm_no_webp_support', __( 'This server can\'t auto-crop or auto-convert images (no Imagick or GD support found). Please crop or re-export this to the exact size and try again.', 'rapm' ) );
					}
					$cropped = RAPM_Webp_Converter::crop_to( $tmp_path, $slot['width'], $slot['height'], $crop_anchor );
					if ( is_wp_error( $cropped ) ) {
						return $cropped;
					}
					$tmp_path    = $cropped;
					$was_resized = true;
				} else {
					return new WP_Error(
						'rapm_wrong_dimensions',
						sprintf(
							/* translators: 1: required width, 2: required height, 3: actual width, 4: actual height */
							__( 'This needs to be %1$dx%2$d px. The file you uploaded is %3$dx%4$d px — please crop or re-export it to the right size and try again.', 'rapm' ),
							$slot['width'],
							$slot['height'],
							$width,
							$height
						)
					);
				}
			} else {
				// Same shape, just a different resolution (e.g. a 2x export) —
				// a plain scale is a safe, lossless fit with nothing to crop
				// or distort, so this is auto-corrected rather than rejected.
				if ( ! RAPM_Webp_Converter::is_available() ) {
					return new WP_Error( 'rapm_no_webp_support', __( 'This server can\'t auto-resize or auto-convert images (no Imagick or GD support found). Please crop or re-export this to the exact size and try again.', 'rapm' ) );
				}
				$resized = RAPM_Webp_Converter::resize_to( $tmp_path, $slot['width'], $slot['height'] );
				if ( is_wp_error( $resized ) ) {
					return $resized;
				}
				$tmp_path    = $resized;
				$was_resized = true;
			}
		}

		$already_webp_and_small = ! $was_resized && 'image/webp' === $mime && ( filesize( $tmp_path ) / 1024 ) <= $slot['max_kb'];
		$base_name              = sanitize_file_name( pathinfo( $original_filename, PATHINFO_FILENAME ) ) ?: 'rapm-image'; // phpcs:ignore

		if ( $already_webp_and_small ) {
			$upload_path = $tmp_path;
			$upload_name = wp_unique_filename( wp_upload_dir()['path'], $base_name . '.webp' );
		} else {
			if ( ! RAPM_Webp_Converter::is_available() ) {
				return new WP_Error( 'rapm_no_webp_support', __( 'This server can\'t auto-convert images to WebP (no Imagick or GD WebP support found). Please upload a .webp file directly, or ask your host to enable WebP support.', 'rapm' ) );
			}
			$converted = RAPM_Webp_Converter::convert( $tmp_path, $slot['max_kb'] );
			if ( $was_resized && file_exists( $tmp_path ) ) {
				wp_delete_file( $tmp_path ); // The resize step's own intermediate file — convert() has already read it into a fresh output file above.
			}
			if ( is_wp_error( $converted ) ) {
				return $converted;
			}
			$upload_path = $converted['path'];
			$upload_name = wp_unique_filename( wp_upload_dir()['path'], $base_name . '.webp' );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$sideload = array(
			'name'     => $upload_name,
			'tmp_name' => $upload_path,
			'type'     => 'image/webp',
			'size'     => filesize( $upload_path ),
		);

		$attachment_id = media_handle_sideload( $sideload, $parent_id );

		if ( ! $already_webp_and_small && file_exists( $upload_path ) ) {
			wp_delete_file( $upload_path ); // media_handle_sideload() copies it in; the temp original is no longer needed.
		}

		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}

		return $attachment_id;
	}

	/**
	 * Shop the Look, step "Place the Pieces" (1.31.0, from the editor mockup
	 * Phil approved on 2026-10-06): click the photo to add a numbered dot,
	 * drag to move it, pick its product; plus "What each screen shows"
	 * (1.36.0, from the drag mockup Phil approved on 2026-10-08): drag the
	 * photo inside a computer-shaped and a phone-shaped box, like moving a
	 * photo in a Canva frame, with a whole-photo view of both. It replaced
	 * the nine-spot "Which part to keep" grid, which kept cutting rooms off.
	 * assets/js/rapm-look-editor.js runs all of it, and the look's photo
	 * checks and preview, from the settings printed here.
	 */
	/**
	 * The promotion box canvas (1.44.0, from the mockup Phil approved on
	 * 2026-10-16): a square design area to drag the words and Google icons
	 * around, the background, an icon search, and the selected piece's size
	 * and color. rapm-box-canvas.js runs it and keeps the hidden rapm_canvas
	 * field up to date. The design area uses the live site's own styles
	 * (rapm-hero.css), so it shows exactly what ships.
	 */
	/** 1.47.0: the site's Elementor brand fonts for the canvas picker, as ID => array( title, family ). */
	/**
	 * 1.62.0: the Fonts row for kinds whose words are on the picture, one
	 * picker per line: the website's Elementor fonts or any Google Font
	 * (RAPM_Line_Fonts). rapm-line-fonts.js opens the list, searches the
	 * bundled Google Fonts list and updates the Live Preview.
	 */
	private static function render_line_fonts( $kind_key, $fonts ) {
		$labels = 'reveal' === $kind_key
			? array( 'head' => __( 'Big word', 'rapm' ), 'sub' => __( 'Short line', 'rapm' ), 'btn' => __( 'Link label', 'rapm' ) )
			: array( 'head' => __( 'Headline', 'rapm' ), 'sub' => __( 'Smaller line', 'rapm' ), 'btn' => __( 'Button', 'rapm' ) );
		$site   = RAPM_Line_Fonts::site_fonts();
		$text   = array(
			'siteDefault' => __( 'Website default', 'rapm' ),
			'siteGroup'   => __( 'Your website\'s fonts (Elementor)', 'rapm' ),
			'googleGroup' => __( 'Google Fonts', 'rapm' ),
			'search'      => __( 'Find a Google Font, like Playfair', 'rapm' ),
			'none'        => __( 'No font matches. Try part of a name, like "play" or "sans".', 'rapm' ),
			/* translators: %s: a font's name */
			'useAll'      => __( 'Use %s for every line', 'rapm' ),
			'sample'      => __( 'Big Sale 30% Off', 'rapm' ),
		);
		?>
		<tr class="rapm-more rapm-lf-row">
			<th><?php esc_html_e( 'Fonts', 'rapm' ); ?></th>
			<td>
				<div class="rapm-lf" data-gfonts="<?php echo esc_url( RAPM_URL . 'assets/data/google-fonts.json?ver=' . RAPM_VERSION ); ?>" data-site="<?php echo esc_attr( wp_json_encode( (object) $site ) ); ?>" data-text="<?php echo esc_attr( wp_json_encode( $text ) ); ?>" data-kind="<?php echo esc_attr( $kind_key ); ?>">
					<?php foreach ( $labels as $line => $label ) : ?>
						<div class="rapm-lf-line" data-line="<?php echo esc_attr( $line ); ?>">
							<span class="rapm-lf-label" id="rapm-lf-label-<?php echo esc_attr( $line ); ?>"><?php echo esc_html( $label ); ?></span>
							<input type="hidden" name="rapm_font_<?php echo esc_attr( $line ); ?>" id="rapm_font_<?php echo esc_attr( $line ); ?>" value="<?php echo esc_attr( isset( $fonts[ $line ] ) ? $fonts[ $line ] : '' ); ?>" />
							<button type="button" class="button rapm-lf-btn" aria-expanded="false" aria-describedby="rapm-lf-label-<?php echo esc_attr( $line ); ?>"><?php esc_html_e( 'Website default', 'rapm' ); ?></button>
						</div>
					<?php endforeach; ?>
					<div class="rapm-lf-pick" hidden>
						<input type="search" class="rapm-lf-search regular-text" placeholder="<?php echo esc_attr( $text['search'] ); ?>" aria-label="<?php echo esc_attr( $text['search'] ); ?>" />
						<div class="rapm-lf-list"></div>
						<button type="button" class="button-link rapm-lf-all"></button>
					</div>
				</div>
				<?php
				RAPM_Tip::hint(
					'look' === $kind_key ? __( 'Used when the words and button are on the room photo. Under the photo they use your website\'s font.', 'rapm' ) : __( 'Words on the picture can use your website\'s fonts or any Google Font.', 'rapm' ),
					__( 'A Google Font is copied to your website when you save, so visitors never load it from Google. Words on the page, outside the picture, always use your website\'s fonts.', 'rapm' )
				);
				?>
			</td>
		</tr>
		<?php
	}

	private static function brand_fonts() {
		$titles   = RAPM_Elementor::global_fonts();
		$families = RAPM_Elementor::font_family_map();
		$out      = array();
		foreach ( $titles as $id => $title ) {
			if ( ! empty( $families[ $id ] ) ) {
				$out[ $id ] = array( $title, $families[ $id ] );
			}
		}
		return $out;
	}

	private static function render_box_canvas( $box_mode, $image_id, $canvas ) {
		// 1.56.0: a new promotion started from a gallery template (RAPM_Gallery::handle_start()).
		$start_tpl = empty( $_GET['edit'] ) && isset( $_GET['rapm_tpl'] ) ? sanitize_key( wp_unslash( $_GET['rapm_tpl'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$canvas  = RAPM_Split::canvas_for_editor( $canvas );
		$picture = $image_id ? wp_get_attachment_image_url( $image_id, 'large' ) : '';
		// 1.45.0: Font Awesome from the site's own Elementor, when it has it.
		$fa_dir  = WP_PLUGIN_DIR . '/elementor/assets/lib/font-awesome/json/';
		$fa_url  = file_exists( $fa_dir . 'solid.json' ) ? plugins_url( 'elementor/assets/lib/font-awesome/json/' ) : '';
		$text    = array(
			'headline'   => __( 'Headline', 'rapm' ),
			'sub'        => __( 'Smaller line', 'rapm' ),
			'btn'        => __( 'Button', 'rapm' ),
			'icon'       => __( 'Icon', 'rapm' ),
			/* translators: %s: Headline, Smaller line or Button */
			'empty'      => __( '(%s: type it above)', 'rapm' ),
			'pick'       => __( 'Click something on the design to change its size or color.', 'rapm' ),
			'wordsAbove' => __( 'Change its words in the boxes above. Leave a box blank to leave it off.', 'rapm' ),
			'size'       => __( 'Size', 'rapm' ),
			'color'      => __( 'Color', 'rapm' ),
			'btnColor'   => __( 'Button color', 'rapm' ),
			'otherColor' => __( 'Any other color', 'rapm' ),
			'remove'     => __( 'Remove this icon', 'rapm' ),
			'noIcons'    => __( 'No icons match. Try a simpler word, like sofa, bed, truck or sale.', 'rapm' ),
			'iconFailed' => __( 'That icon couldn\'t be loaded from Google just now. Please try again.', 'rapm' ),
			'picture'     => __( 'Picture', 'rapm' ),
			'removePic'   => __( 'Remove this picture', 'rapm' ),
			'pickPicture' => __( 'Pick a picture, like a logo or a badge', 'rapm' ),
			'usePicture'  => __( 'Put it on the design', 'rapm' ),
			'siteColors'  => __( 'Website colors (linked)', 'rapm' ),
			'savedColors' => __( 'Saved colors', 'rapm' ),
			'textStyle'   => __( 'Text style', 'rapm' ),
			'noStyle'     => __( 'No style', 'rapm' ),
			'styleHint'   => __( 'A style sets the font, weight, size, color and letter case at once. Manage styles in Design Center › Brand kit.', 'rapm' ),
			'caps'        => __( 'ALL CAPS', 'rapm' ),
			'useTpl'      => __( 'Use', 'rapm' ),
			'tplAsk'      => __( 'Replace this design, and the headline, smaller line and button words, with the template?', 'rapm' ),
			'tplName'     => __( 'Template name', 'rapm' ),
			'tplYes'      => __( 'Yes, replace it', 'rapm' ),
			'tplSave'     => __( 'Save template', 'rapm' ),
			'cancel'      => __( 'Cancel', 'rapm' ),
			'tplSaved'    => __( 'Saved as a template.', 'rapm' ),
			'tplFailed'   => __( 'The template didn\'t save. Please try again.', 'rapm' ),
			'starter'     => __( 'R&A starter', 'rapm' ),
			'thisStore'   => __( 'This store', 'rapm' ),
			'yourLogos'   => __( 'From your brand kit', 'rapm' ),
			'manageKit'   => __( 'Manage the brand kit', 'rapm' ),
			'font'        => __( 'Font', 'rapm' ),
			'fontDefault' => __( 'Site default', 'rapm' ),
			'fontSearch'  => __( 'Search Google Fonts', 'rapm' ),
			'fontBrand'   => __( 'This site\'s brand fonts', 'rapm' ),
			'fontMore'    => __( 'Google Fonts', 'rapm' ),
			'fontNone'    => __( 'No font matches. Try part of a name, like "play" or "sans".', 'rapm' ),
			'fontNote'    => __( 'A Google font is copied to this website when you save, so visitors never load it from Google.', 'rapm' ),
			'fontSample'  => __( 'Big Sale 30% Off', 'rapm' ),
			// 1.51.0: canvas power tools.
			'forward'      => __( 'Bring forward', 'rapm' ),
			'back'         => __( 'Send back', 'rapm' ),
			'hide'         => __( 'Hide', 'rapm' ),
			'show'         => __( 'Hidden: click to show', 'rapm' ),
			'lock'         => __( 'Lock', 'rapm' ),
			'unlock'       => __( 'Locked: click to unlock', 'rapm' ),
			'locked'       => __( 'locked', 'rapm' ),
			'lockedMsg'    => __( 'That piece is locked. Unlock it under "On the design" to move it.', 'rapm' ),
			'hiddenMsg'    => __( 'Hidden: it stays in the design but won\'t show on the website.', 'rapm' ),
			'arrangeNone'  => __( 'Pick a piece on the square or in the list.', 'rapm' ),
			'arrangeOne'   => __( 'One piece picked: it lines up with the square. Shift-click more to line them up with each other.', 'rapm' ),
			/* translators: %d: number of pieces */
			'arrangeMany'  => __( '%d pieces picked: they line up with each other.', 'rapm' ),
			'arrangeThree' => __( 'Pick 3 or more to space them evenly.', 'rapm' ),
			'arrangeLocked' => __( 'A picked piece is locked. Unlock it to line it up.', 'rapm' ),
			'overlap'      => __( 'They overlap, so there isn\'t room for gaps between them.', 'rapm' ),
			/* translators: %d: number of pieces */
			'pickedMany'   => __( '%d pieces picked. Text effects change all the picked words; drag one to move them together.', 'rapm' ),
			'effects'      => __( 'Text effects', 'rapm' ),
			'shadow'       => __( 'Shadow', 'rapm' ),
			'outline'      => __( 'Outline', 'rapm' ),
			'outlineColor' => __( 'Outline color', 'rapm' ),
			'none'         => __( 'None', 'rapm' ),
			'soft'         => __( 'Soft', 'rapm' ),
			'strong'       => __( 'Strong', 'rapm' ),
			'thin'         => __( 'Thin', 'rapm' ),
			'thick'        => __( 'Thick', 'rapm' ),
			'black'        => __( 'Black', 'rapm' ),
			'white'        => __( 'White', 'rapm' ),
		);
		// 1.50.0: the brand kit (website colors, saved colors, styles, logos, templates).
		$kit = RAPM_Brand_Kit::for_browser();
		$bgs = array();
		foreach ( $kit['site'] as $c ) {
			$bgs[ $c['hex'] ] = $c['name'];
		}
		foreach ( $kit['colors'] as $c ) {
			$bgs[ $c['hex'] ] = $c['name'];
		}
		$bgs += array( '#f3ead8' => __( 'Cream', 'rapm' ), '#ffffff' => __( 'White', 'rapm' ), '#2f2f2f' => __( 'Charcoal', 'rapm' ) );
		?>
		<div id="rapm-canvas" class="rapm-cv"<?php echo 'design' === $box_mode ? '' : ' hidden'; ?> data-icons="<?php echo esc_url( RAPM_URL . 'assets/data/material-symbols.json?ver=' . RAPM_VERSION ); ?>" data-picture="<?php echo esc_url( $picture ? $picture : '' ); ?>" data-fa="<?php echo esc_url( $fa_url ); ?>" data-gfonts="<?php echo esc_url( RAPM_URL . 'assets/data/google-fonts.json?ver=' . RAPM_VERSION ); ?>" data-kit="<?php echo esc_attr( wp_json_encode( $kit ) ); ?>" data-kit-nonce="<?php echo esc_attr( wp_create_nonce( RAPM_Brand_Kit::NONCE ) ); ?>" data-ajax="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" data-kit-url="<?php echo esc_url( add_query_arg( 'tab', 'brand', RAPM_Promotions_Screen::design_url() ) ); ?>" data-brand="<?php echo esc_attr( wp_json_encode( self::brand_fonts() ) ); ?>" data-fonts="<?php echo esc_attr( wp_json_encode( RAPM_Elementor::font_family_map() ) ); ?>" data-text="<?php echo esc_attr( wp_json_encode( $text ) ); ?>"<?php echo $start_tpl ? ' data-start-tpl="' . esc_attr( $start_tpl ) . '"' : ''; ?>>
			<input type="hidden" id="rapm_canvas" name="rapm_canvas" value="<?php echo esc_attr( $canvas['items'] ? wp_json_encode( $canvas ) : '' ); ?>" />
			<h2><?php esc_html_e( 'Place the Words and Icons', 'rapm' ); ?></h2>
			<?php RAPM_Tip::hint( __( 'Drag things on the square to move them. Click one to change it.', 'rapm' ), __( 'Things snap into line with the middle, the edges and each other. Hold Alt (Option on a Mac) to place freely. Shift-click to pick more than one. Arrow keys move what you picked a little; hold Shift to move it more.', 'rapm' ) ); ?>
			<?php // 1.50.0: templates from the brand kit. ?>
			<div class="rapm-cv-tplbar">
				<button type="button" class="button" id="rapm-cv-tpltoggle" aria-expanded="false"><?php esc_html_e( 'Start from a template', 'rapm' ); ?></button>
				<button type="button" class="button" id="rapm-cv-savetpl"><?php esc_html_e( 'Save as template…', 'rapm' ); ?></button>
				<span class="rapm-cv-tplform" id="rapm-cv-tplform" hidden>
					<input type="text" id="rapm-cv-tplname" maxlength="60" placeholder="<?php esc_attr_e( 'Template name, like Fall sale', 'rapm' ); ?>" aria-label="<?php esc_attr_e( 'Template name', 'rapm' ); ?>" />
					<button type="button" class="button button-primary" id="rapm-cv-tplsave"><?php esc_html_e( 'Save template', 'rapm' ); ?></button>
					<button type="button" class="button-link" id="rapm-cv-tplcancel"><?php esc_html_e( 'Cancel', 'rapm' ); ?></button>
				</span>
				<span class="rapm-cv-tplmsg" id="rapm-cv-tplmsg" aria-live="polite"></span>
			</div>
			<div class="rapm-cv-tpls" id="rapm-cv-tpls" hidden></div>
			<div class="rapm-cv-layout">
				<div class="rapm-cv-main">
					<?php // 1.51.0: Undo and Redo. ?>
					<p class="rapm-cv-toolbar">
						<button type="button" class="button" id="rapm-cv-undo" disabled title="<?php esc_attr_e( 'Undo (Ctrl+Z or ⌘Z)', 'rapm' ); ?>"><?php esc_html_e( 'Undo', 'rapm' ); ?></button>
						<button type="button" class="button" id="rapm-cv-redo" disabled title="<?php esc_attr_e( 'Redo (Shift+Ctrl+Z or Shift+⌘Z)', 'rapm' ); ?>"><?php esc_html_e( 'Redo', 'rapm' ); ?></button>
						<span class="rapm-cv-status" id="rapm-cv-status" aria-live="polite"></span>
					</p>
					<div class="rapm-cv-stage rapm-pb-item is-design" tabindex="0" aria-label="<?php esc_attr_e( 'Promotion design', 'rapm' ); ?>">
						<img class="rapm-cv-bgimg" alt="" hidden />
						<div class="rapm-cv-board rapm-pb-board">
							<span class="rapm-cv-guide v" hidden></span>
							<span class="rapm-cv-guide h" hidden></span>
						</div>
					</div>
					<p class="description"><?php esc_html_e( 'This square shows on every screen, in the middle of the box. On wider boxes, the background fills the sides.', 'rapm' ); ?></p>
				</div>
				<div class="rapm-cv-side">
					<?php // 1.51.1: what you use most first; Background (open on a new design) and Add an icon or picture fold away. ?>
					<div class="rapm-cv-panel">
						<h3><?php esc_html_e( 'On the design', 'rapm' ); ?></h3>
						<?php RAPM_Tip::hint( __( 'The top of the list is in front. Use the arrows to move a piece forward or back.', 'rapm' ), __( 'Shortcut: Ctrl+] and Ctrl+[ (⌘ on a Mac).', 'rapm' ) ); ?>
						<div class="rapm-cv-layers"></div>
						<div class="rapm-cv-arrange">
							<p class="rapm-cv-swlabel"><?php esc_html_e( 'Arrange', 'rapm' ); ?></p>
							<p class="rapm-cv-arrbtns">
								<?php
								$arrange = array(
									array( 'al', 'left', __( 'Line up left edges', 'rapm' ), 'M3 2h2v20H3zM7 6h12v4H7zM7 14h8v4H7z' ),
									array( 'al', 'cx', __( 'Line up centers across', 'rapm' ), 'M11 2h2v20h-2zM5 6h14v4H5zM7 14h10v4H7z' ),
									array( 'al', 'right', __( 'Line up right edges', 'rapm' ), 'M19 2h2v20h-2zM5 6h12v4H5zM9 14h8v4H9z' ),
									array( 'al', 'top', __( 'Line up tops', 'rapm' ), 'M2 3h20v2H2zM6 7h4v12H6zM14 7h4v8h-4z' ),
									array( 'al', 'cy', __( 'Line up middles', 'rapm' ), 'M2 11h20v2H2zM6 5h4v14H6zM14 7h4v10h-4z' ),
									array( 'al', 'bottom', __( 'Line up bottoms', 'rapm' ), 'M2 19h20v2H2zM6 5h4v12H6zM14 9h4v8h-4z' ),
									array( 'dist', 'x', __( 'Space evenly across (3 or more)', 'rapm' ), 'M2 3h2v18H2zM20 3h2v18h-2zM9 7h6v10H9z' ),
									array( 'dist', 'y', __( 'Space evenly up and down (3 or more)', 'rapm' ), 'M3 2h18v2H3zM3 20h18v2H3zM7 9h10v6H7z' ),
								);
								foreach ( $arrange as $a ) :
									?>
									<button type="button" class="rapm-cv-ib" data-<?php echo esc_attr( $a[0] ); ?>="<?php echo esc_attr( $a[1] ); ?>" title="<?php echo esc_attr( $a[2] ); ?>" aria-label="<?php echo esc_attr( $a[2] ); ?>" disabled><svg viewBox="0 0 24 24" aria-hidden="true"><path d="<?php echo esc_attr( $a[3] ); ?>"/></svg></button>
								<?php endforeach; ?>
							</p>
							<p class="description rapm-cv-arrnote"></p>
						</div>
						<div class="rapm-cv-sel"></div>
					</div>
					<details class="rapm-cv-panel"<?php echo $canvas['items'] ? '' : ' open'; ?>>
						<summary><span class="rapm-cv-sumtitle"><?php esc_html_e( 'Background', 'rapm' ); ?></span> <span class="rapm-cv-bgdot" aria-hidden="true"></span></summary>
						<p class="rapm-cv-seg">
							<button type="button" class="button" data-bgmode="color" aria-pressed="true"><?php esc_html_e( 'A color', 'rapm' ); ?></button>
							<button type="button" class="button" data-bgmode="pic" aria-pressed="false"><?php esc_html_e( 'The picture from step 1', 'rapm' ); ?></button>
						</p>
						<p class="rapm-cv-bgcolors">
							<?php foreach ( $bgs as $c => $c_name ) : ?>
								<button type="button" class="rapm-cv-sw" data-bg="<?php echo esc_attr( $c ); ?>" style="background:<?php echo esc_attr( $c ); ?>" aria-label="<?php echo esc_attr( $c_name ); ?>" title="<?php echo esc_attr( $c_name ); ?>" aria-pressed="false"></button>
							<?php endforeach; ?>
							<input type="color" id="rapm-cv-bgcolor" value="#f3ead8" aria-label="<?php esc_attr_e( 'Any other background color', 'rapm' ); ?>" />
						</p>
						<p class="description rapm-cv-nopic"><?php esc_html_e( 'To use a picture as the background, upload one in step 1. It fills the whole box.', 'rapm' ); ?></p>
					</details>
					<details class="rapm-cv-panel">
						<summary><span class="rapm-cv-sumtitle"><?php esc_html_e( 'Add an icon or picture', 'rapm' ); ?></span></summary>
						<p class="rapm-cv-seg rapm-cv-tabs">
							<button type="button" class="button" data-tab="ms" aria-pressed="true"><?php esc_html_e( 'Google icons', 'rapm' ); ?></button>
							<?php if ( $fa_url ) : ?>
								<button type="button" class="button" data-tab="fa" aria-pressed="false"><?php esc_html_e( 'Font Awesome', 'rapm' ); ?></button>
							<?php endif; ?>
							<button type="button" class="button" data-tab="media" aria-pressed="false"><?php esc_html_e( 'Your pictures', 'rapm' ); ?></button>
						</p>
						<div class="rapm-cv-iconpick">
							<input type="search" class="rapm-cv-search regular-text" placeholder="<?php esc_attr_e( 'Search, like sofa, bed, truck or sale', 'rapm' ); ?>" aria-label="<?php esc_attr_e( 'Search icons', 'rapm' ); ?>" />
							<div class="rapm-cv-icons"></div>
						</div>
						<p class="description rapm-cv-src-ms"><?php esc_html_e( 'Google\'s free Material Symbols, 3,929 icons. A picked icon is saved with this promotion, so your website never loads anything from Google for it.', 'rapm' ); ?></p>
						<p class="description rapm-cv-src-fa" hidden><?php esc_html_e( 'The Font Awesome icons that come with Elementor (Font Awesome Free, 1,616 icons, including social media logos). A picked icon is saved with this promotion.', 'rapm' ); ?></p>
						<div class="rapm-cv-media" hidden>
							<p><button type="button" class="button rapm-cv-pick"><?php esc_html_e( 'Choose from the Media Library', 'rapm' ); ?></button></p>
							<p class="description"><?php esc_html_e( 'A logo or badge, like a "Made in USA" seal. A PNG or SVG with a see-through background looks best.', 'rapm' ); ?></p>
						</div>
					</details>
				</div>
			</div>
		</div>
		<script>
			// Design it here / A finished graphic (1.44.0): shows what each one needs.
			( function () {
				var radios = document.querySelectorAll( 'input[name="rapm_box_mode"]' );
				function sync() {
					var picked = document.querySelector( 'input[name="rapm_box_mode"]:checked' );
					var mode = picked ? picked.value : 'graphic';
					Array.prototype.forEach.call( document.querySelectorAll( '.rapm-box-mode-note' ), function ( n ) { n.hidden = n.getAttribute( 'data-mode' ) !== mode; } );
					document.getElementById( 'rapm-canvas' ).hidden = 'design' !== mode;
					document.getElementById( 'rapm-text-fields' ).style.display = 'design' === mode ? '' : 'none';
				}
				Array.prototype.forEach.call( radios, function ( r ) { r.addEventListener( 'change', sync ); } );
			} )();
		</script>
		<?php
	}

	private static function render_look_pieces( $image_id, $dots, $focus, $photo_btn = array(), $mobile_id = 0, $banner = false, $focus_phone = '', $photo_words = true ) {
		$focus_phone = '' !== $focus_phone ? $focus_phone : $focus;
		?>
		<?php if ( $banner ) : ?>
			<h2><?php esc_html_e( 'Add product dots and a "Shop now" button (optional)', 'rapm' ); ?></h2>
			<?php RAPM_Tip::hint( __( 'Shoppers tap a dot to see the product, its price and stock.', 'rapm' ), __( 'The same products show as numbered cards under the slider. Put dots on the computer picture and the phone picture. Phone dots start where you put them on the computer picture, so check both.', 'rapm' ) ); ?>
			<p class="rapm-pieces-views" role="group" aria-label="<?php esc_attr_e( 'Which picture to place them on', 'rapm' ); ?>">
				<button type="button" class="button rapm-pieces-view is-selected" data-view="desktop" aria-pressed="true"><?php esc_html_e( 'Computer picture', 'rapm' ); ?></button>
				<button type="button" class="button rapm-pieces-view" data-view="mobile" aria-pressed="false"><?php esc_html_e( 'Phone picture', 'rapm' ); ?></button>
				<span class="rapm-pieces-view-note" id="rapm-pieces-view-note"></span>
			</p>
		<?php else : ?>
		<h2><?php esc_html_e( 'Put a numbered dot on each piece you sell', 'rapm' ); ?></h2>
		<?php endif; ?>
		<ol class="rapm-pieces-howto">
			<li><?php esc_html_e( 'Click the photo on a piece to add a dot.', 'rapm' ); ?></li>
			<li><?php esc_html_e( 'Drag a dot to move it.', 'rapm' ); ?></li>
			<li><?php esc_html_e( 'Pick the product for each dot.', 'rapm' ); ?></li>
		</ol>
		<?php if ( ! class_exists( 'WooCommerce' ) ) : ?>
			<p class="description rapm-pieces-nowoo"><?php esc_html_e( 'Dots need WooCommerce, which isn\'t turned on here, so you can\'t add dots yet.', 'rapm' ); ?></p>
		<?php endif; ?>
		<div class="rapm-pieces" id="rapm-pieces">
			<div class="rapm-pieces-photo">
				<div class="rapm-pieces-stage" id="rapm-pieces-stage" hidden><img alt="" draggable="false" /></div>
				<div class="rapm-pieces-empty" id="rapm-pieces-empty"><?php echo $banner ? esc_html__( 'Choose the computer picture above, and it shows here for placing dots.', 'rapm' ) : esc_html__( 'Choose the room photo above, and it shows here for placing dots.', 'rapm' ); ?></div>
				<p class="description"><?php esc_html_e( 'Each dot\'s number matches its row in "Dots and products." Shoppers see the same numbers on the product cards.', 'rapm' ); ?> <button type="button" class="button-link" id="rapm-pieces-add"><?php esc_html_e( 'Add a dot in the middle', 'rapm' ); ?></button> <?php esc_html_e( 'and move it with the arrow keys.', 'rapm' ); ?></p>
			</div>
			<div class="rapm-pieces-side">
				<?php if ( ! $banner ) : ?>
				<div class="rapm-pieces-btn-box">
					<?php // 1.39.0. The hidden "off" is sent when the box is unticked; a ticked box's "on" comes after it and wins. ?>
					<input type="hidden" name="rapm_photo_words" value="off" />
					<label><input type="checkbox" id="rapm-photo-words-on" name="rapm_photo_words" value="on" <?php checked( $photo_words ); ?> /> <?php esc_html_e( 'Show the headline and smaller line on the photo', 'rapm' ); ?></label>
					<?php RAPM_Tip::hint( __( 'Turn this off if the picture already has words on it, like an ad.', 'rapm' ), __( 'It only matters where the page puts words on the photo. Phones still show the words under the photo.', 'rapm' ) ); ?>
				</div>
				<?php endif; ?>
				<div class="rapm-pieces-btn-box">
					<label><input type="checkbox" id="rapm-photo-btn-on" <?php checked( ! empty( $photo_btn ) ); ?> /> <?php esc_html_e( 'Show a "Shop now" button on the photo', 'rapm' ); ?></label>
					<p class="description"><?php esc_html_e( 'Drag the button to a clear spot on the photo. It uses this look\'s button words (or "Shop now") and goes to its link from step 4.', 'rapm' ); ?></p>
				</div>
				<div class="rapm-pieces-head"><h3><?php esc_html_e( 'Dots and products', 'rapm' ); ?></h3><span class="rapm-pieces-count" id="rapm-pieces-count"></span></div>
				<ol class="rapm-pieces-list" id="rapm-pieces-list"></ol>
			</div>
		</div>
		<?php if ( ! $banner ) : ?>
		<div class="rapm-fit" id="rapm-fit">
			<h3><?php esc_html_e( 'What each screen shows', 'rapm' ); ?></h3>
			<p class="description"><?php esc_html_e( 'Drag the photo inside each box, like in Canva. The faded part gets cut off. Computers show a wide strip; phones show a squarer part.', 'rapm' ); ?></p>
			<div class="rapm-fit-boxes">
				<?php
				$fit_boxes = array(
					'computer' => __( 'On computers', 'rapm' ),
					'phone'    => __( 'On phones', 'rapm' ),
				);
				foreach ( $fit_boxes as $device => $fit_label ) :
					?>
					<div class="rapm-fit-box rapm-fit-<?php echo esc_attr( $device ); ?>" data-device="<?php echo esc_attr( $device ); ?>">
						<span class="rapm-fit-label" id="rapm-fit-label-<?php echo esc_attr( $device ); ?>"><?php echo esc_html( $fit_label ); ?></span>
						<div class="rapm-fit-area">
							<img class="rapm-fit-ghost" alt="" draggable="false" />
							<div class="rapm-fit-frame" tabindex="0" role="slider" aria-labelledby="rapm-fit-label-<?php echo esc_attr( $device ); ?>" aria-valuemin="0" aria-valuemax="100" aria-valuenow="50">
								<img class="rapm-fit-img" alt="" draggable="false" />
							</div>
						</div>
						<div class="rapm-fit-controls">
							<button type="button" class="button button-small" data-step="-0.05"></button>
							<button type="button" class="button button-small" data-step="0.05"></button>
							<button type="button" class="button-link" data-center="1"><?php esc_html_e( 'Center', 'rapm' ); ?></button>
						</div>
						<p class="rapm-fit-note" aria-live="polite"></p>
					</div>
				<?php endforeach; ?>
			</div>
			<div class="rapm-fit-whole">
				<h4><?php esc_html_e( 'The whole photo', 'rapm' ); ?></h4>
				<div class="rapm-fit-overview" id="rapm-fit-overview">
					<img alt="" draggable="false" />
					<span class="rapm-fit-out rapm-fit-out-computer"></span>
					<span class="rapm-fit-out rapm-fit-out-phone"></span>
				</div>
				<ul class="rapm-fit-legend">
					<li><span class="rapm-fit-swatch rapm-fit-swatch-computer"></span><?php esc_html_e( 'Computers show inside the blue box', 'rapm' ); ?></li>
					<li><span class="rapm-fit-swatch rapm-fit-swatch-phone"></span><?php esc_html_e( 'Phones show inside the red dashed box', 'rapm' ); ?></li>
				</ul>
			</div>
			<p class="rapm-pieces-fit-msg" id="rapm-pieces-fit-msg" aria-live="polite"></p>
		</div>
		<?php endif; ?>
		<input type="hidden" id="rapm_focus" name="rapm_focus" value="<?php echo esc_attr( $focus ); ?>" />
		<input type="hidden" id="rapm_focus_phone" name="rapm_focus_phone" value="<?php echo esc_attr( $focus_phone ); ?>" />
		<input type="hidden" id="rapm_dots" name="rapm_dots" value="<?php echo esc_attr( wp_json_encode( $dots ) ); ?>" />
		<input type="hidden" id="rapm_photo_btn" name="rapm_photo_btn" value="<?php echo esc_attr( wp_json_encode( $photo_btn ) ); ?>" />
		<script>
			window.RAPM_LookEditorConfig = <?php echo wp_json_encode( self::look_editor_config( $image_id, $dots, $focus, $photo_btn, $mobile_id, $banner, $focus_phone ) ); ?>;
		</script>
		<?php
	}

	/** A saved picture's address and size for the editor (1.35.0), or empty values. */
	private static function photo_info( $image_id ) {
		$meta = $image_id ? wp_get_attachment_metadata( $image_id ) : array();
		return array(
			'src'    => $image_id ? (string) wp_get_attachment_image_url( $image_id, 'full' ) : '',
			'width'  => is_array( $meta ) && ! empty( $meta['width'] ) ? (int) $meta['width'] : 0,
			'height' => is_array( $meta ) && ! empty( $meta['height'] ) ? (int) $meta['height'] : 0,
		);
	}

	/** Everything rapm-look-editor.js needs, including the words it shows (translatable here). */
	private static function look_editor_config( $image_id, $dots, $focus, $photo_btn = array(), $mobile_id = 0, $banner = false, $focus_phone = '' ) {
		$meta     = $image_id ? wp_get_attachment_metadata( $image_id ) : array();
		$slot     = RAPM_Slots::get( 'look_photo' );
		$products = array();
		foreach ( $dots as $dot ) {
			$info = RAPM_Looks::product_info( $dot['p'] );
			if ( $info ) {
				$products[ $dot['p'] ] = $info;
			}
		}
		return array(
			'src'       => $image_id ? (string) wp_get_attachment_image_url( $image_id, 'full' ) : '',
			'width'     => is_array( $meta ) && ! empty( $meta['width'] ) ? (int) $meta['width'] : 0,
			'height'    => is_array( $meta ) && ! empty( $meta['height'] ) ? (int) $meta['height'] : 0,
			'focus'     => RAPM_Looks::focus_fraction( $focus ),
			'focusPhone' => RAPM_Looks::focus_fraction( '' !== $focus_phone ? $focus_phone : $focus ),
			'minWidth'  => isset( $slot['min_width'] ) ? (int) $slot['min_width'] : 0,
			'maxWidth'  => (int) $slot['width'],
			'maxDots'   => RAPM_Looks::MAX_DOTS,
			'dots'      => $dots,
			'products'  => $products,
			'store'     => class_exists( 'WooCommerce' ) ? esc_url_raw( rest_url( 'wc/store/v1/products' ) ) : '',
			'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
			'linkNonce' => wp_create_nonce( 'rapm_preview_link' ),
			'photoBtn'  => $photo_btn,
			// 1.35.0: a Slider or Feature banner places dots on both pictures.
			'mode'      => $banner ? 'banner' : 'look',
			'accent'    => RAPM_Elementor::resolve_accent_color_css( '#2271b1' ),
			'mobile'    => $banner ? self::photo_info( $mobile_id ) : null,
			'text'      => array(
				'sizeBad'       => __( 'This photo is %1$d pixels wide. It needs to be at least %2$d, so it would be turned down when you save.', 'rapm' ),
				'sizeBlurry'    => __( 'This photo is %d pixels wide. It works, but it will look blurry across a big screen. 2000 or more looks best.', 'rapm' ),
				'sizeSoft'      => __( 'This photo is %d pixels wide. It works, but it will look a little soft across a big screen.', 'rapm' ),
				'sizeBig'       => __( 'This photo is %1$d pixels wide. Big enough; it will be made %2$d wide when you save.', 'rapm' ),
				'sizeOk'        => __( 'This photo is %d pixels wide. Big enough for a full-width photo.', 'rapm' ),
				'checking'      => __( 'Checking the link…', 'rapm' ),
				'foundFolder'   => __( 'Using the newest picture in the folder: %s', 'rapm' ),
				'found'         => __( 'Found the picture: %s', 'rapm' ),
				'linkFailed'    => __( 'Couldn\'t check that link.', 'rapm' ),
				'tabEmpty'      => __( '(tab name)', 'rapm' ),
				'listEmpty'     => __( 'No dots yet. Click the photo on a piece you sell, like a sofa or a table.', 'rapm' ),
				'oneDot'        => __( '1 dot', 'rapm' ),
				'manyDots'      => __( '%d dots', 'rapm' ),
				'dotLabel'      => __( 'Dot %1$d: %2$s. Use the arrow keys to move it.', 'rapm' ),
				'dotEmpty'      => __( 'Dot %d: no product yet. Use the arrow keys to move it.', 'rapm' ),
				'search'        => __( 'Search by product name or SKU…', 'rapm' ),
				'searchLabel'   => __( 'Product for dot %d', 'rapm' ),
				'searching'     => __( 'Searching…', 'rapm' ),
				'noMatch'       => __( 'Nothing matches "%s". Try fewer words, or the SKU.', 'rapm' ),
				'more'          => __( 'Showing %1$d of %2$d. Keep typing to narrow it down.', 'rapm' ),
				'searchFailed'  => __( 'Couldn\'t search the products just now. Please try again.', 'rapm' ),
				'sku'           => __( 'SKU %s', 'rapm' ),
				'noPrice'       => __( 'No price shown online', 'rapm' ),
				'change'        => __( 'Change product', 'rapm' ),
				'moveUp'        => __( 'Move up', 'rapm' ),
				'moveDown'      => __( 'Move down', 'rapm' ),
				'remove'        => __( 'Remove dot', 'rapm' ),
				'cutComputers'  => __( 'Cut off on computers', 'rapm' ),
				'cutPhones'     => __( 'Cut off on phones', 'rapm' ),
				'cutBoth'       => __( 'Cut off on computers and phones', 'rapm' ),
				'fitOk'         => __( 'Every dot shows on computers and phones.', 'rapm' ),
				'fitWarn'       => __( 'Some dots are cut off (see the list). Drag the photo in that screen\'s box, or move those dots.', 'rapm' ),
				'up'            => __( '▲ Up', 'rapm' ),
				'down'          => __( '▼ Down', 'rapm' ),
				'left'          => __( '◀ Left', 'rapm' ),
				'right'         => __( 'Right ▶', 'rapm' ),
				'upLabel'       => __( 'Show a little more of the top', 'rapm' ),
				'downLabel'     => __( 'Show a little more of the bottom', 'rapm' ),
				'leftLabel'     => __( 'Show a little more of the left', 'rapm' ),
				'rightLabel'    => __( 'Show a little more of the right', 'rapm' ),
				'fromTop'       => __( '%d percent of the way toward the bottom', 'rapm' ),
				'fromLeft'      => __( '%d percent of the way toward the right', 'rapm' ),
				'fitsExactly'   => __( 'This photo is exactly this shape, so all of it shows. Nothing to move.', 'rapm' ),
				'allShow'       => __( 'All dots show here.', 'rapm' ),
				'cutHere'       => __( 'Cut off here: %s. Shoppers on this screen won\'t see that dot.', 'rapm' ),
				'cutHereMany'   => __( 'Cut off here: %s. Shoppers on this screen won\'t see those dots.', 'rapm' ),
				'dotN'          => __( 'dot %d', 'rapm' ),
				'needProduct'   => __( 'Pick a product for every dot, or remove the dots without one. Missing: dot %s.', 'rapm' ),
				'tooMany'       => __( 'Up to %d dots can be added.', 'rapm' ),
				'noMobile'      => __( 'No phone picture yet, so phones show the computer picture and its dots.', 'rapm' ),
				'btnDefault'    => __( 'Shop now', 'rapm' ),
				'btnLabel'      => __( 'The button on the photo. Drag it, or use the arrow keys to move it.', 'rapm' ),
				'added'         => __( 'Dot %d added. Now pick its product.', 'rapm' ),
				'removed'       => __( 'Dot removed. The others were renumbered.', 'rapm' ),
				'picked'        => __( 'Dot %1$d is now %2$s.', 'rapm' ),
				'noProduct'     => __( 'No product picked yet', 'rapm' ),
				'gone'          => __( 'This product isn\'t on the website right now (it may be a draft or deleted), so shoppers don\'t see this dot. Pick another product or remove the dot.', 'rapm' ),
			),
		);
	}

	/**
	 * Shop the Look (1.30.0), step "Review & Schedule": the look as shoppers
	 * see it, drawn with the site's rapm-looks.css (photo, dots, tab, words,
	 * product cards with live prices). rapm-look-editor.js fills it in.
	 */
	private static function render_look_preview() {
		?>
		<h2><?php esc_html_e( 'Live Preview', 'rapm' ); ?></h2>
		<p class="description"><?php esc_html_e( 'This is what shoppers see: the photo, dots, tab and words. Prices and stock come from your store. Your website\'s fonts show on the real page.', 'rapm' ); ?></p>
		<p>
			<button type="button" class="button button-small rapm-preview-toggle-btn" id="rapm-look-pv-desktop" aria-pressed="true"><?php esc_html_e( 'Computer', 'rapm' ); ?></button>
			<button type="button" class="button button-small rapm-preview-toggle-btn" id="rapm-look-pv-mobile" aria-pressed="false"><?php esc_html_e( 'Phone', 'rapm' ); ?></button>
		</p>
		<div class="rapm-look-pv" id="rapm-look-pv">
			<div class="rapm-looks" style="--rapm-looks-accent:<?php echo esc_attr( RAPM_Elementor::resolve_accent_color_css( '#2271b1' ) ); ?>;">
				<div class="rapm-looks-stage" id="rapm-look-pv-stage">
					<div class="rapm-look-photo is-active"><img id="rapm-look-pv-img" alt="" /></div>
				</div>
				<div class="rapm-looks-bar">
					<div class="rapm-looks-tabs"><span class="rapm-looks-tab" aria-selected="true"><span class="rapm-looks-track"></span><span id="rapm-look-pv-tab"></span></span></div>
				</div>
				<div class="rapm-looks-shelf">
					<div class="rapm-look-text" id="rapm-look-pv-text">
						<div class="rapm-look-intro">
							<p class="rapm-look-eyebrow"><?php esc_html_e( 'Shop the look', 'rapm' ); ?> <span>&middot; <span id="rapm-look-pv-tab2"></span></span></p>
							<h2 class="rapm-look-title" id="rapm-look-pv-title"></h2>
							<p class="rapm-look-blurb" id="rapm-look-pv-blurb"></p>
							<p class="rapm-look-note" id="rapm-look-pv-note"></p>
							<span class="rapm-look-btn" id="rapm-look-pv-btn"></span>
						</div>
						<ol class="rapm-look-cards" id="rapm-look-pv-cards"></ol>
					</div>
				</div>
			</div>
			<p class="description" id="rapm-look-pv-empty"><?php esc_html_e( 'Choose a room photo above to see it here.', 'rapm' ); ?></p>
		</div>
		<?php
	}

	public static function sanitize_text_align( $value ) {
		return in_array( $value, array( 'left', 'center', 'right' ), true ) ? $value : 'left';
	}

	public static function sanitize_text_style( $value ) {
		return in_array( $value, array( 'bold', 'elegant', 'minimal' ), true ) ? $value : 'bold';
	}

	public static function sanitize_text_color( $value ) {
		$color = sanitize_hex_color( $value );
		return $color ? $color : '#ffffff';
	}

	/**
	 * Whitelisted against whatever Elementor global fonts actually exist
	 * on this site right now, not just any string the form happened to
	 * post — a stale/tampered value can't silently reference a font id
	 * that no longer exists.
	 */
	public static function sanitize_text_font( $value ) {
		$available = RAPM_Elementor::global_fonts();
		return isset( $available[ $value ] ) ? $value : '';
	}

	/**
	 * Backs the Add/Edit Asset form's live "who else shares this spot"
	 * summary — turns the abstract idea of a shared Placement into a
	 * concrete list of the other real promotions (by name, on this actual
	 * site) that would rotate together with this one, since prose
	 * explanations of what Placement does have repeatedly not landed on
	 * their own.
	 */
	/**
	 * Live preview for "Use a link": fetches the picture the pasted link
	 * really points to (for a Drive folder, the one that would be chosen)
	 * and returns it as a data URL, since neither a folder link nor a Drive
	 * share link is itself an image a browser can show. Nothing is saved.
	 */
	public static function ajax_preview_link() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Not allowed.', 'rapm' ) ), 403 );
		}
		check_ajax_referer( 'rapm_preview_link', 'nonce' );

		$url      = isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : '';
		$which    = isset( $_POST['which'] ) && 'mobile' === $_POST['which'] ? 'mobile' : 'desktop';
		$kind     = RAPM_Slots::kind_for( isset( $_POST['kind'] ) ? sanitize_key( wp_unslash( $_POST['kind'] ) ) : 'hero', isset( $_POST['placement'] ) ? sanitize_title( wp_unslash( $_POST['placement'] ) ) : 'default' );
		$asset_id = isset( $_POST['asset_id'] ) ? absint( $_POST['asset_id'] ) : 0;
		$slots    = RAPM_Slots::all();
		if ( ! $url || empty( $kind[ $which ] ) || ! isset( $slots[ $kind[ $which ] ] ) ) {
			wp_send_json_error( array( 'message' => __( 'That doesn\'t look like a valid web address.', 'rapm' ) ) );
		}
		$slot = $slots[ $kind[ $which ] ];

		$picked = RAPM_Link_Source::resolve_to_tmp( $url, $slot, $asset_id, $which, false );
		if ( 'mobile' === $which && RAPM_Link_Source::is_folder_url( $url ) && RAPM_Link_Source::is_waiting_for_picture( $picked ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'There\'s no tall picture in this folder yet. That\'s fine: phones will show the desktop picture, and a tall picture added to the folder later is picked up automatically.', 'rapm' ),
					'soft'    => true,
				)
			);
		}
		if ( is_wp_error( $picked ) ) {
			wp_send_json_error( array( 'message' => $picked->get_error_message() ) );
		}
		$dims = getimagesize( $picked['tmp_path'] );
		$data = file_get_contents( $picked['tmp_path'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		wp_delete_file( $picked['tmp_path'] );
		if ( ! $dims || false === $data ) {
			wp_send_json_error( array( 'message' => __( 'That link didn\'t return a picture.', 'rapm' ) ) );
		}

		wp_send_json_success(
			array(
				'src'       => 'data:' . $dims['mime'] . ';base64,' . base64_encode( $data ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
				'name'      => $picked['name'],
				'width'     => (int) $dims[0],
				'height'    => (int) $dims[1],
				'is_folder' => RAPM_Link_Source::is_folder_url( $url ),
				'fits'      => RAPM_Slots::aspect_ratio_matches( $slot, $dims[0], $dims[1] ),
			)
		);
	}

	public static function ajax_placement_summary() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Not allowed.', 'rapm' ) ), 403 );
		}
		check_ajax_referer( 'rapm_placement_summary', 'nonce' );

		$kind_key   = isset( $_GET['kind'] ) ? sanitize_key( wp_unslash( $_GET['kind'] ) ) : 'hero';
		$placement  = isset( $_GET['placement'] ) ? sanitize_title( wp_unslash( $_GET['placement'] ) ) : '';
		$exclude_id = isset( $_GET['exclude_id'] ) ? absint( $_GET['exclude_id'] ) : 0;

		if ( '' === $placement ) {
			$placement = 'default';
		}

		$args = array(
			'post_type'      => 'rapm_asset',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'menu_order date',
			'order'          => 'ASC',
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
				'relation' => 'AND',
				array( 'key' => '_rapm_kind', 'value' => $kind_key ),
				array( 'key' => '_rapm_placement', 'value' => $placement ),
			),
			'fields'         => 'ids',
		);
		if ( $exclude_id ) {
			$args['post__not_in'] = array( $exclude_id );
		}

		$query  = new WP_Query( $args );
		$titles = array_map( 'get_the_title', array_slice( $query->posts, 0, 5 ) );

		wp_send_json_success(
			array(
				'count'  => count( $query->posts ),
				'titles' => array_values( $titles ),
			)
		);
	}

	/**
	 * 1.59.1: "JPG, PNG, WebP or GIF · up to 8 MB on this website", under
	 * each picture field. The limit is this server's own (the smaller of
	 * PHP's upload_max_filesize and post_max_size).
	 */
	public static function file_limit_text() {
		/* translators: %s: the most this website takes, like "8 MB" */
		return sprintf( __( 'JPG, PNG, WebP or GIF · up to %s on this website · bigger JPG, PNG and WebP pictures are made smaller for you', 'rapm' ), size_format( wp_max_upload_size() ) );
	}

	/**
	 * 1.59.1: why a chosen picture never arrived, or ''. A picture over the
	 * server's limit reaches PHP with an error and no file, and until now
	 * that read as "no picture chosen": a new promotion asked for a picture
	 * it had been given, and an edit kept the old picture and said Saved.
	 */
	private static function upload_problem( $field ) {
		$error = isset( $_FILES[ $field ]['error'] ) ? (int) $_FILES[ $field ]['error'] : UPLOAD_ERR_NO_FILE; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked in handle_save().
		$name  = isset( $_FILES[ $field ]['name'] ) ? sanitize_file_name( wp_unslash( $_FILES[ $field ]['name'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		switch ( $error ) {
			case UPLOAD_ERR_OK:
			case UPLOAD_ERR_NO_FILE:
				return '';
			case UPLOAD_ERR_INI_SIZE:
			case UPLOAD_ERR_FORM_SIZE:
				/* translators: 1: file name, 2: the most this website takes */
				return sprintf( __( '%1$s is bigger than this website takes (%2$s), so it wasn\'t saved. Save it as a JPG (most programs: Export › JPG, quality 80) and choose it again.', 'rapm' ), $name, size_format( wp_max_upload_size() ) );
			case UPLOAD_ERR_PARTIAL:
				/* translators: %s: file name */
				return sprintf( __( '%s didn\'t finish uploading. Please try again.', 'rapm' ), $name );
			default:
				return __( 'This website couldn\'t store the picture because of a server setting. Please let R&A Marketing know.', 'rapm' );
		}
	}

	/** 1.59.1: names a file type we can't use, with what to do, or gives $fallback. */
	private static function type_message( $filename, $fallback ) {
		$name = sanitize_file_name( wp_basename( (string) $filename ) );
		$ext  = strtolower( (string) pathinfo( $name, PATHINFO_EXTENSION ) );
		if ( in_array( $ext, array( 'heic', 'heif' ), true ) ) {
			/* translators: %s: file name */
			return sprintf( __( '%s is an iPhone photo format websites can\'t show. Save it as a JPG first. On an iPhone: Settings › Camera › Formats › Most Compatible.', 'rapm' ), $name );
		}
		if ( $ext && ! in_array( $ext, array( 'jpg', 'jpeg', 'png', 'webp', 'gif' ), true ) ) {
			/* translators: 1: file name, 2: its type, like "TIFF" */
			return sprintf( __( '%1$s is a %2$s file. Use a JPG, PNG, WebP or GIF picture.', 'rapm' ), $name, strtoupper( $ext ) );
		}
		return $fallback;
	}

	/**
	 * 1.59.1: a promotion form bigger than the server takes as a whole
	 * (post_max_size) reaches WordPress with nothing in it, so it used to
	 * end on a blank page. Send it back to the form with what happened.
	 * PHP has already thrown the typed fields away, so they can't be kept.
	 */
	public static function catch_oversized_post() {
		if ( 'admin-post.php' !== basename( isset( $_SERVER['SCRIPT_NAME'] ) ? (string) $_SERVER['SCRIPT_NAME'] : '' ) || 'POST' !== ( isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : '' ) || ! empty( $_POST ) || ! empty( $_FILES ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput
			return;
		}
		$sent  = isset( $_SERVER['CONTENT_LENGTH'] ) ? (int) $_SERVER['CONTENT_LENGTH'] : 0;
		$limit = (int) wp_convert_hr_to_bytes( (string) ini_get( 'post_max_size' ) );
		$back  = (string) wp_get_referer();
		if ( ! $limit || $sent <= $limit || false === strpos( $back, 'page=rapm-add-asset' ) || ! current_user_can( 'edit_posts' ) ) {
			return;
		}
		/* translators: 1: the most this website takes at once, like "8 MB" */
		$message = sprintf( __( 'The pictures were too big for this website to receive (more than %s together), so nothing was saved, and what you typed couldn\'t be kept. Choose smaller pictures and fill it in again.', 'rapm' ), size_format( $limit ) );
		wp_safe_redirect( add_query_arg( 'rapm_error', rawurlencode( $message ), remove_query_arg( array( 'rapm_error', 'rapm_restore' ), $back ) ) );
		exit;
	}

	private static function fail( $back_url, $message ) {
		// 1.58.1: keep what was typed for 10 minutes, for this person only.
		$keep = array();
		foreach ( wp_unslash( $_POST ) as $key => $value ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked in handle_save().
			if ( 0 === strpos( $key, 'rapm_' ) && 'rapm_nonce' !== $key && is_scalar( $value ) ) {
				$keep[ $key ] = $value;
			}
		}
		set_transient( 'rapm_restore_' . get_current_user_id(), $keep, 10 * MINUTE_IN_SECONDS );
		$back_url = add_query_arg( 'rapm_restore', 1, $back_url );
		wp_safe_redirect( add_query_arg( 'rapm_error', rawurlencode( $message ), $back_url ) );
		exit;
	}
}
