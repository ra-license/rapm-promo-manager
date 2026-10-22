<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One-time tips (1.58.0, from the mockup Phil approved on 2026-10-22,
 * https://claude.ai/artifact/6v3FWRnQcgyAYP6Tuy4Mw1). Step 4 of the UX review:
 * help in the moment, not a tour.
 *
 * - One short tip, pointing at the thing it's about, the first time a person
 *   reaches it. At most one per page view; the rest wait for a later visit.
 * - "Got it" closes that tip for good; "Don't show tips" turns them all off
 *   for that person. Both are remembered per login (user meta), so a new
 *   staff member still sees them.
 * - Help & FAQ lists every tip with whether it was seen, with "Show all
 *   tips again" and "Turn tips off/on".
 * - On for everyone at first. The longer help stays behind (i) (RAPM_Tip).
 */
class RAPM_First_Tips {

	const SEEN = 'rapm_tips_seen';
	const OFF  = 'rapm_tips_off';

	/**
	 * Every tip: where it is, its words, and the element it points at
	 * (rapm-first-tips.js waits until that element is on screen).
	 */
	public static function all() {
		return array(
			'gallery' => array(
				'where'  => __( 'New promotion', 'rapm' ),
				'title'  => __( 'Pick a picture of what you want to make', 'rapm' ),
				'text'   => __( 'Each card shows where it sits on a page. The ones already on your website come first.', 'rapm' ),
				'anchor' => '.rapm-gal-grid .rapm-gal-card',
			),
			'editor'  => array(
				'where'  => __( 'Making a promotion', 'rapm' ),
				'title'  => __( 'Check marks show what is done', 'rapm' ),
				'text'   => __( 'Fill the parts in any order. Save tells you what is still missing.', 'rapm' ),
				'anchor' => '#rapm-ed-bar .rapm-ed-chips',
			),
			'pieces'  => array(
				'where'  => __( 'Place the pieces', 'rapm' ),
				'title'  => __( 'Click a sofa or table to add a dot', 'rapm' ),
				'text'   => __( 'Then pick the product it is. Shoppers tap the dot to see its price.', 'rapm' ),
				'anchor' => '#rapm-pieces-stage:not([hidden])',
			),
			'focal'   => array(
				'where'  => __( 'What stays in view', 'rapm' ),
				'title'  => __( 'Drag the pink point onto what matters', 'rapm' ),
				'text'   => __( 'When the website trims the picture to fit, that part stays in view.', 'rapm' ),
				'anchor' => '.rapm-focal:not([hidden]) .rapm-focal-photo',
			),
			'canvas'  => array(
				'where'  => __( 'Designing a promotion box', 'rapm' ),
				'title'  => __( 'Drag words and icons anywhere', 'rapm' ),
				'text'   => __( 'Click one to change it. Ctrl+Z (⌘+Z on a Mac) undoes.', 'rapm' ),
				'anchor' => '#rapm-canvas:not([hidden]) .rapm-cv-board',
			),
			'promos'  => array(
				'where'  => __( 'Promotions page', 'rapm' ),
				'title'  => __( 'Each box is a place on your website', 'rapm' ),
				'text'   => __( 'Drag the pictures to change the order they play. "+ Add" puts a new one in this place.', 'rapm' ),
				'anchor' => 'section.rapm-spot[data-kind] h2',
			),
		);
	}

	public static function seen( $user_id = 0 ) {
		$seen = get_user_meta( $user_id ? $user_id : get_current_user_id(), self::SEEN, true );
		return is_array( $seen ) ? $seen : array();
	}

	public static function is_off( $user_id = 0 ) {
		return (bool) get_user_meta( $user_id ? $user_id : get_current_user_id(), self::OFF, true );
	}

	/** On Promo Manager screens: the tips this person hasn't seen, for rapm-first-tips.js. */
	public static function enqueue() {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 0 !== strpos( $page, 'rapm-' ) || ! current_user_can( 'edit_posts' ) || self::is_off() ) {
			return;
		}
		$seen = self::seen();
		$tips = array();
		foreach ( self::all() as $id => $tip ) {
			if ( ! in_array( $id, $seen, true ) ) {
				$tips[] = array( 'id' => $id, 'title' => $tip['title'], 'text' => $tip['text'], 'anchor' => $tip['anchor'] );
			}
		}
		if ( ! $tips ) {
			return;
		}
		wp_enqueue_style( 'rapm-first-tips-css', RAPM_URL . 'assets/css/rapm-first-tips.css', array(), RAPM_VERSION );
		wp_enqueue_script( 'rapm-first-tips-js', RAPM_URL . 'assets/js/rapm-first-tips.js', array(), RAPM_VERSION, true );
		wp_localize_script(
			'rapm-first-tips-js',
			'RAPM_First_Tips',
			array(
				'tips'  => $tips,
				'ajax'  => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'rapm_first_tips' ),
				'text'  => array(
					'gotIt' => __( 'Got it', 'rapm' ),
					'off'   => __( 'Don\'t show tips', 'rapm' ),
					'label' => __( 'Tip', 'rapm' ),
				),
			)
		);
	}

	/** "Got it" (one tip) or "Don't show tips" (all), from rapm-first-tips.js. */
	public static function ajax() {
		check_ajax_referer( 'rapm_first_tips', 'nonce' );
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( null, 403 );
		}
		$user = get_current_user_id();
		$do   = isset( $_POST['do'] ) ? sanitize_key( wp_unslash( $_POST['do'] ) ) : '';
		if ( 'off' === $do ) {
			update_user_meta( $user, self::OFF, 1 );
		} else {
			$id = isset( $_POST['tip'] ) ? sanitize_key( wp_unslash( $_POST['tip'] ) ) : '';
			if ( isset( self::all()[ $id ] ) ) {
				$seen   = self::seen( $user );
				$seen[] = $id;
				update_user_meta( $user, self::SEEN, array_values( array_unique( $seen ) ) );
			}
		}
		wp_send_json_success();
	}

	/** Help & FAQ's buttons: show all tips again, or turn them off or on. */
	public static function handle_settings() {
		check_admin_referer( 'rapm_first_tips_settings' );
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'rapm' ) );
		}
		$user = get_current_user_id();
		$do   = isset( $_POST['do'] ) ? sanitize_key( wp_unslash( $_POST['do'] ) ) : '';
		if ( 'reset' === $do ) {
			delete_user_meta( $user, self::SEEN );
			delete_user_meta( $user, self::OFF );
		} elseif ( 'off' === $do ) {
			update_user_meta( $user, self::OFF, 1 );
		} elseif ( 'on' === $do ) {
			delete_user_meta( $user, self::OFF );
		}
		wp_safe_redirect( add_query_arg( 'rapm_tips', $do, admin_url( 'edit.php?post_type=rapm_asset&page=rapm-help' ) ) . '#rapm-tips' );
		exit;
	}

	/** The "Tips" section on Help & FAQ. */
	public static function render_help_section() {
		$seen = self::seen();
		$off  = self::is_off();
		?>
		<h2 id="rapm-tips"><?php esc_html_e( 'Tips', 'rapm' ); ?></h2>
		<?php if ( isset( $_GET['rapm_tips'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
			<div class="notice notice-success inline"><p><?php esc_html_e( 'Saved.', 'rapm' ); ?></p></div>
		<?php endif; ?>
		<p><?php echo $off ? esc_html__( 'Tips are off for you. Turn them back on any time.', 'rapm' ) : esc_html__( 'Each tip shows once, the first time you reach that part. They are remembered for your login only.', 'rapm' ); ?></p>
		<ul class="rapm-tips-list">
			<?php foreach ( self::all() as $id => $tip ) : ?>
				<?php $was = in_array( $id, $seen, true ); ?>
				<li><span class="rapm-tips-mark<?php echo $was ? ' is-seen' : ''; ?>" aria-hidden="true"><?php echo $was ? '✓' : '·'; ?></span> <strong><?php echo esc_html( $tip['where'] ); ?>:</strong> <?php echo esc_html( $tip['title'] ); ?> <span class="description">(<?php echo $was ? esc_html__( 'seen', 'rapm' ) : esc_html__( 'not seen yet', 'rapm' ); ?>)</span></li>
			<?php endforeach; ?>
		</ul>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="rapm-tips-actions">
			<input type="hidden" name="action" value="rapm_first_tips_settings" />
			<?php wp_nonce_field( 'rapm_first_tips_settings' ); ?>
			<button type="submit" name="do" value="reset" class="button button-primary"><?php esc_html_e( 'Show all tips again', 'rapm' ); ?></button>
			<?php if ( $off ) : ?>
				<button type="submit" name="do" value="on" class="button"><?php esc_html_e( 'Turn tips on', 'rapm' ); ?></button>
			<?php else : ?>
				<button type="submit" name="do" value="off" class="button"><?php esc_html_e( 'Turn tips off', 'rapm' ); ?></button>
			<?php endif; ?>
		</form>
		<style>
			.rapm-tips-list { list-style: none; padding-left: 0 !important; }
			.rapm-tips-mark { display: inline-flex; align-items: center; justify-content: center; width: 20px; height: 20px; border-radius: 50%; background: #8c8f94; color: #fff; font-size: 12px; vertical-align: -3px; }
			.rapm-tips-mark.is-seen { background: #00a32a; }
			.rapm-tips-actions { display: flex; flex-wrap: wrap; gap: 10px; }
		</style>
		<?php
	}
}
