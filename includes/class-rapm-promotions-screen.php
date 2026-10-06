<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The Promotions home screen (1.28.0), built from the mockup Phil approved
 * on 2026-10-05. It replaces All Assets and Sliders as the place clients
 * manage promotions: one section per spot on the site, named in plain
 * words, with large picture cards, a plain status on each card, an on/off
 * switch, and drag (or "Move earlier/later") to change the order.
 *
 * Nothing is removed: the old list, Sliders, Settings and the Training
 * Guide are all still in the menu (1.31.3: Phil wants every screen in the
 * menu for everyone; 1.28.0–1.31.2 had taken them out). The spot tools
 * (+ New spot, codes, renaming) show to everyone too, with no toggle.
 */
class RAPM_Promotions_Screen {

	const PAGE   = 'rapm-promotions';
	const PARENT = 'edit.php?post_type=rapm_asset';
	const NONCE  = 'rapm_promotions';

	/** Spot kinds in the order their sections appear, with the plain word for each and the narrowest card width. */
	const KIND_ORDER = array( 'hero', 'look', 'fold_banner', 'coupon', 'marquee' );
	const CARD_MIN   = array(
		'hero'        => 260,
		'look'        => 260,
		'fold_banner' => 420,
		'coupon'      => 170,
		'marquee'     => 170,
	);

	/**
	 * This screen's own menu item, taken out because "All promotions"
	 * already opens it, and the browser-tab title it needs once it's out.
	 */
	private static function hidden_pages() {
		return array(
			self::PAGE => __( 'Promotions', 'rapm' ),
		);
	}

	/** The old list of every promotion (bulk actions, filters), as a menu item. */
	const LIST_SLUG = 'edit.php?post_type=rapm_asset&rapm_list=1';

	/** Menu order (1.31.3). Anything not listed keeps its place after these. */
	private static function menu_order() {
		return array(
			self::PARENT,
			'rapm-add-asset',
			self::LIST_SLUG,
			'rapm-sliders',
			'rapm-training-guide',
			'rapm-help',
			'rapm-settings',
		);
	}

	public static function add_menu() {
		add_submenu_page(
			self::PARENT,
			__( 'Promotions', 'rapm' ),
			__( 'Promotions', 'rapm' ),
			'edit_posts',
			self::PAGE,
			array( __CLASS__, 'render_page' )
		);
		// A plain link, no screen of its own: WordPress links a submenu
		// slug that isn't a plugin page straight to that address.
		add_submenu_page(
			self::PARENT,
			__( 'All Assets (list)', 'rapm' ),
			__( 'All Assets (list)', 'rapm' ),
			'edit_posts',
			self::LIST_SLUG
		);
	}

	/**
	 * Runs after every screen has registered (priority 999). The first
	 * item stays the old list's address, labelled "All promotions", which
	 * maybe_redirect_list() forwards here, so the top-level "Promotions"
	 * link works without depending on how WordPress links a menu whose
	 * first item is a plugin page.
	 *
	 * Only two duplicates come out: this screen's own item ("All
	 * promotions" opens it) and WordPress's own "Add New", which only ever
	 * forwarded to "Add New Asset" (RAPM_Upload_Handler::maybe_redirect_native_add_new()).
	 */
	public static function tidy_menu() {
		global $submenu;
		remove_submenu_page( self::PARENT, 'post-new.php?post_type=rapm_asset' );
		foreach ( array_keys( self::hidden_pages() ) as $slug ) {
			remove_submenu_page( self::PARENT, $slug );
		}
		if ( empty( $submenu[ self::PARENT ] ) ) {
			return;
		}
		$order = array_flip( self::menu_order() );
		$items = array_values( $submenu[ self::PARENT ] );
		foreach ( $items as $i => $item ) {
			$items[ $i ]['rapm_rank'] = isset( $order[ $item[2] ] ) ? $order[ $item[2] ] * 1000 : 100000 + $i;
		}
		usort(
			$items,
			function ( $a, $b ) {
				return $a['rapm_rank'] - $b['rapm_rank'];
			}
		);
		$sorted = array();
		foreach ( $items as $i => $item ) {
			unset( $item['rapm_rank'] );
			// WordPress keeps submenu items under numeric keys and reads them in key order.
			$sorted[ ( $i + 1 ) * 5 ] = $item;
		}
		$submenu[ self::PARENT ] = $sorted; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	}

	/** Which menu item is highlighted: this screen and editing a promotion use "All promotions", the old list its own item. */
	public static function submenu_file( $submenu_file ) {
		global $plugin_page, $pagenow;
		$pages = self::hidden_pages();
		if ( $plugin_page && isset( $pages[ $plugin_page ] ) ) {
			return self::PARENT;
		}
		if ( 'rapm-add-asset' === $plugin_page && ! empty( $_GET['edit'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return self::PARENT;
		}
		if ( 'edit.php' === $pagenow && ! $plugin_page && isset( $_GET['post_type'] ) && 'rapm_asset' === $_GET['post_type'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return self::LIST_SLUG;
		}
		// Each of our screens marks its own item. Left to WordPress, Help,
		// Settings and the rest opened the menu with nothing marked (seen on
		// staging and on Gates, 1.31.2–1.31.3).
		global $submenu;
		if ( $plugin_page && ! empty( $submenu[ self::PARENT ] ) ) {
			foreach ( $submenu[ self::PARENT ] as $item ) {
				if ( isset( $item[2] ) && $item[2] === $plugin_page ) {
					return $plugin_page;
				}
			}
		}
		return $submenu_file;
	}

	public static function admin_title( $admin_title, $title ) {
		global $plugin_page;
		$pages = self::hidden_pages();
		if ( '' === trim( (string) $title ) && $plugin_page && isset( $pages[ $plugin_page ] ) ) {
			return esc_html( $pages[ $plugin_page ] ) . $admin_title;
		}
		return $admin_title;
	}

	/**
	 * The plain old list (edit.php?post_type=rapm_asset with nothing else
	 * on the address) now opens this screen. Anything more specific (a
	 * search, a filter, a page number, WordPress's own "moved to trash"
	 * return, or &rapm_list=1 from the R&A links) still shows the old list.
	 */
	public static function maybe_redirect_list() {
		global $pagenow;
		if ( 'edit.php' !== $pagenow || ! isset( $_GET['post_type'] ) || 'rapm_asset' !== $_GET['post_type'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		if ( 1 !== count( $_GET ) || 'GET' !== $_SERVER['REQUEST_METHOD'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput
			return;
		}
		wp_safe_redirect( self::url() );
		exit;
	}

	public static function url( $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => self::PAGE ), $args ), admin_url( self::PARENT ) );
	}

	public static function enqueue( $hook ) {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( self::PAGE !== $page ) {
			return;
		}
		wp_enqueue_style( 'rapm-promotions-admin', RAPM_URL . 'assets/css/rapm-promotions-admin.css', array(), RAPM_VERSION );
		wp_enqueue_script( 'rapm-promotions-admin', RAPM_URL . 'assets/js/rapm-promotions-admin.js', array(), RAPM_VERSION, true );
		wp_localize_script(
			'rapm-promotions-admin',
			'RAPM_Promotions',
			array(
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'nonce'        => wp_create_nonce( self::NONCE ),
				'reorderNonce' => wp_create_nonce( 'rapm_reorder_slides' ),
				'text'         => array(
					'saving'      => __( 'Saving…', 'rapm' ),
					'orderSaved'  => __( 'New order saved.', 'rapm' ),
					'saveFailed'  => __( 'That didn’t save. Please try again.', 'rapm' ),
					'confirmTrash' => __( 'Move this promotion to the trash? You can bring it back right after.', 'rapm' ),
					'showEnded'   => __( 'Show %d ended', 'rapm' ),
					'hideEnded'   => __( 'Hide ended', 'rapm' ),
					'nameSaved'   => __( 'Name saved.', 'rapm' ),
					'noResults'   => __( 'No promotions match “%s”.', 'rapm' ),
					'confirmRemove' => __( 'Remove "%s"? It has no promotions and isn\'t on any page.', 'rapm' ),
				),
			)
		);
	}

	/* ------------------------------------------------------------------
	 * Data
	 * ------------------------------------------------------------------ */

	/**
	 * Every spot: each Kind+Placement that has at least one promotion, plus
	 * any placed on a page that has none yet. Sorted slider, banner,
	 * coupons, tiles; "home" first within each.
	 */
	private static function spots() {
		$posts = get_posts(
			array(
				'post_type'      => 'rapm_asset',
				'post_status'    => array( 'publish', 'draft', 'pending', 'future', 'private' ),
				'posts_per_page' => -1,
				'orderby'        => 'menu_order date',
				'order'          => 'ASC',
			)
		);

		$spots = array();
		foreach ( $posts as $post ) {
			$kind      = get_post_meta( $post->ID, '_rapm_kind', true ) ?: 'hero'; // phpcs:ignore
			$placement = get_post_meta( $post->ID, '_rapm_placement', true ) ?: 'default'; // phpcs:ignore
			$key       = $kind . '|' . $placement;
			if ( ! isset( $spots[ $key ] ) ) {
				$spots[ $key ] = array( 'kind' => $kind, 'placement' => $placement, 'posts' => array() );
			}
			$spots[ $key ]['posts'][] = $post;
		}
		// Spots placed on a page, or made with "+ New spot", that have no promotions yet.
		$empty = array_merge( array_keys( RAPM_Spot_Usage::get() ), array_keys( RAPM_Spots::all() ) );
		foreach ( $empty as $key ) {
			if ( 'calendar' === $key || isset( $spots[ $key ] ) || false === strpos( $key, '|' ) ) {
				continue;
			}
			list( $kind, $placement ) = explode( '|', $key, 2 );
			$spots[ $key ] = array( 'kind' => $kind, 'placement' => $placement, 'posts' => array() );
		}

		uasort(
			$spots,
			function ( $a, $b ) {
				$ka = array_search( $a['kind'], self::KIND_ORDER, true );
				$kb = array_search( $b['kind'], self::KIND_ORDER, true );
				$ka = false === $ka ? 99 : $ka;
				$kb = false === $kb ? 99 : $kb;
				if ( $ka !== $kb ) {
					return $ka - $kb;
				}
				if ( 'home' === $a['placement'] || 'home' === $b['placement'] ) {
					return 'home' === $a['placement'] ? -1 : 1;
				}
				return strcmp( $a['placement'], $b['placement'] );
			}
		);
		return $spots;
	}

	/** A spot's plain name (RAPM_Spots::name()). */
	public static function spot_name( $kind, $placement ) {
		return RAPM_Spots::name( $kind, $placement );
	}

	/**
	 * A promotion's state and the two lines under its picture, worked out
	 * in the site's own timezone. Visitors' browsers make the same
	 * start/end decision on the live site (rapm-schedule.js).
	 *
	 * @return array state ('showing'|'upcoming'|'hidden'|'ended'), status, when, soon (ends within 7 days).
	 */
	public static function describe( $post ) {
		$tz    = wp_timezone();
		$now   = new DateTimeImmutable( 'now', $tz );
		$start = (string) get_post_meta( $post->ID, '_rapm_starts_at', true );
		$end   = (string) get_post_meta( $post->ID, '_rapm_ends_at', true );
		$start = $start ? date_create_immutable( $start, $tz ) : false;
		$end   = $end ? date_create_immutable( $end, $tz ) : false;
		$day   = function ( $dt ) {
			return wp_date( 'M j', $dt->getTimestamp() );
		};

		if ( $end && $end < $now ) {
			/* translators: %s: a date like "Oct 12" */
			return array( 'state' => 'ended', 'status' => __( 'Ended', 'rapm' ), 'when' => sprintf( __( 'Ended %s', 'rapm' ), $day( $end ) ), 'soon' => false );
		}
		if ( 'publish' !== $post->post_status ) {
			return array( 'state' => 'hidden', 'status' => __( 'Hidden', 'rapm' ), 'when' => __( 'Not on the website', 'rapm' ), 'soon' => false );
		}
		if ( $start && $start > $now ) {
			return array(
				'state'  => 'upcoming',
				/* translators: %s: a date like "Nov 20" */
				'status' => sprintf( __( 'Starts %s', 'rapm' ), $day( $start ) ),
				/* translators: %s: a date like "Nov 30" */
				'when'   => $end ? sprintf( __( 'Runs until %s', 'rapm' ), $day( $end ) ) : __( 'No end date', 'rapm' ),
				'soon'   => false,
			);
		}

		$d = array( 'state' => 'showing', 'status' => __( 'Showing now', 'rapm' ), 'when' => __( 'No end date', 'rapm' ), 'soon' => false );
		if ( $end ) {
			$left = (int) $now->setTime( 0, 0 )->diff( $end->setTime( 0, 0 ) )->days;
			if ( 0 === $left ) {
				$d['when'] = __( 'Last day today', 'rapm' );
				$d['soon'] = true;
			} elseif ( $left <= 7 ) {
				/* translators: 1: number of days, 2: a date like "Oct 12" */
				$d['when'] = sprintf( _n( 'Ends in %1$d day (%2$s)', 'Ends in %1$d days (%2$s)', $left, 'rapm' ), $left, $day( $end ) );
				$d['soon'] = true;
			} else {
				/* translators: %s: a date like "Oct 31" */
				$d['when'] = sprintf( __( 'Until %s', 'rapm' ), $day( $end ) );
			}
		}
		return $d;
	}

	/** "3 showing now · 1 starting later · 1 hidden" for a spot's posts. */
	public static function count_line( $posts ) {
		$n = array( 'showing' => 0, 'upcoming' => 0, 'hidden' => 0 );
		foreach ( $posts as $post ) {
			$state = self::describe( $post )['state'];
			if ( isset( $n[ $state ] ) ) {
				++$n[ $state ];
			}
		}
		/* translators: %d: how many promotions are on the website right now */
		$parts = array( sprintf( _n( '%d showing now', '%d showing now', $n['showing'], 'rapm' ), $n['showing'] ) );
		if ( $n['upcoming'] ) {
			/* translators: %d: how many promotions have a start date still to come */
			$parts[] = sprintf( _n( '%d starting later', '%d starting later', $n['upcoming'], 'rapm' ), $n['upcoming'] );
		}
		if ( $n['hidden'] ) {
			/* translators: %d: how many promotions are switched off */
			$parts[] = sprintf( _n( '%d hidden', '%d hidden', $n['hidden'], 'rapm' ), $n['hidden'] );
		}
		return implode( ' · ', $parts );
	}

	private static function spot_posts( $kind, $placement ) {
		return get_posts(
			array(
				'post_type'      => 'rapm_asset',
				'post_status'    => array( 'publish', 'draft', 'pending', 'future', 'private' ),
				'posts_per_page' => -1,
				'orderby'        => 'menu_order date',
				'order'          => 'ASC',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
					'relation' => 'AND',
					array( 'key' => '_rapm_kind', 'value' => $kind ),
					array( 'key' => '_rapm_placement', 'value' => $placement ),
				),
			)
		);
	}

	/* ------------------------------------------------------------------
	 * Screen
	 * ------------------------------------------------------------------ */

	public static function render_page() {
		$spots    = self::spots();
		$calendar = RAPM_Spot_Usage::get();
		$calendar = isset( $calendar['calendar'] ) ? $calendar['calendar'] : array();
		?>
		<div class="wrap rapm-promos">
			<div class="rapm-promos-head">
				<div>
					<h1 class="rapm-promos-title"><?php esc_html_e( 'Promotions', 'rapm' ); ?></h1>
					<p class="rapm-promos-sub"><?php esc_html_e( 'The pictures on your website, grouped by where they show.', 'rapm' ); ?></p>
				</div>
				<button type="button" class="button button-primary rapm-new-spot-open"><?php esc_html_e( '+ New spot', 'rapm' ); ?></button>
				<?php if ( $spots ) : ?>
					<div class="rapm-promos-search">
						<label for="rapm-promo-search"><?php esc_html_e( 'Find a promotion', 'rapm' ); ?></label>
						<input type="search" id="rapm-promo-search" placeholder="<?php esc_attr_e( 'Type part of a name', 'rapm' ); ?>" autocomplete="off" />
					</div>
				<?php endif; ?>
			</div>
			<hr class="wp-header-end" />

			<?php self::render_notices(); ?>

			<?php if ( ! $spots ) : ?>
				<div class="rapm-spot rapm-empty">
					<h2><?php esc_html_e( 'No promotions yet', 'rapm' ); ?></h2>
					<p><?php esc_html_e( 'Promotions go in spots: places on your website, like a slider at the top of the home page. Click "+ New spot" at the top to add one. It then shows here with an "Add a picture" button and the code to put on a page.', 'rapm' ); ?></p>
				</div>
			<?php endif; ?>

			<p class="rapm-no-results" hidden></p>

			<?php
			foreach ( $spots as $spot ) {
				self::render_spot( $spot );
			}
			?>

			<div class="rapm-promos-foot">
				<?php if ( $calendar ) : ?>
					<p>
						<?php
						echo esc_html(
							sprintf(
								/* translators: 1: list of pages, 2: how many promotions have both dates */
								__( 'Promotions calendar: on %1$s. It lists every promotion that has both a start and an end date (%2$d right now).', 'rapm' ),
								implode( ', ', wp_list_pluck( $calendar, 'label' ) ),
								RAPM_Calendar::count_scheduled()
							)
						);
						?>
					</p>
				<?php endif; ?>
			</div>

			<div class="rapm-spot rapm-ra-panel">
				<h2><?php esc_html_e( 'Setup', 'rapm' ); ?></h2>
				<p><?php esc_html_e( 'Each section above shows its spot code and a box to rename it. A new spot shows here when you click "+ New spot", when its code is placed on a page, or when a promotion is added with a new spot name.', 'rapm' ); ?></p>
				<ul>
					<li><a href="<?php echo esc_url( admin_url( 'edit.php?post_type=rapm_asset&page=rapm-add-asset' ) ); ?>"><?php esc_html_e( 'Add a promotion to a new spot', 'rapm' ); ?></a></li>
					<li><a href="<?php echo esc_url( admin_url( 'edit.php?post_type=rapm_asset&rapm_list=1' ) ); ?>"><?php esc_html_e( 'The old list of every promotion (bulk actions, filters)', 'rapm' ); ?></a></li>
					<li><a href="<?php echo esc_url( admin_url( 'edit.php?post_type=rapm_asset&page=rapm-sliders' ) ); ?>"><?php esc_html_e( 'The old Sliders screen', 'rapm' ); ?></a></li>
					<?php if ( current_user_can( 'manage_options' ) ) : ?>
						<li><a href="<?php echo esc_url( admin_url( 'edit.php?post_type=rapm_asset&page=rapm-settings' ) ); ?>"><?php esc_html_e( 'Settings (picture sizes, updates, brand color)', 'rapm' ); ?></a></li>
					<?php endif; ?>
				</ul>
				<?php $report = RAPM_Cache::last_report(); ?>
				<h3><?php esc_html_e( 'Last page-cache clear', 'rapm' ); ?></h3>
				<?php if ( $report ) : ?>
					<p>
						<?php echo esc_html( mysql2date( 'M j, g:i:s a', $report['time'] ) ); ?> ·
						<?php
						echo esc_html(
							! empty( $report['urls'] )
								? implode( ', ', $report['urls'] )
								: __( 'no single pages', 'rapm' )
						);
						?>
						<?php if ( ! empty( $report['sitewide'] ) ) : ?>
							· <?php esc_html_e( 'plus the whole site (the spot is in a template or widget)', 'rapm' ); ?>
						<?php endif; ?>
					</p>
					<ul>
						<?php foreach ( (array) $report['results'] as $line ) : ?>
							<li><?php echo esc_html( $line ); ?></li>
						<?php endforeach; ?>
					</ul>
				<?php else : ?>
					<p><?php esc_html_e( 'None yet. It runs when a promotion is switched on or off, saved, reordered or trashed.', 'rapm' ); ?></p>
				<?php endif; ?>
			</div>

			<?php self::render_new_spot_dialog(); ?>
		</div>
		<?php
	}

	/** "+ New spot" (R&A): pick a type, name it for its job. Approved in the 2026-10-05 mockup. */
	private static function render_new_spot_dialog() {
		$examples = array(
			'hero'        => __( 'For example: Living Room slider', 'rapm' ),
			'fold_banner' => __( 'For example: Seasonal feature banner', 'rapm' ),
			'coupon'      => __( 'For example: Clearance coupons', 'rapm' ),
			'marquee'     => __( 'For example: Shop by room tiles', 'rapm' ),
			'look'        => __( 'For example: Home page looks', 'rapm' ),
		);
		?>
		<dialog class="rapm-new-spot" aria-labelledby="rapm-ns-title">
			<form class="rapm-ns-form">
				<h2 id="rapm-ns-title"><?php esc_html_e( 'New spot', 'rapm' ); ?></h2>
				<fieldset class="rapm-ns-types">
					<legend><?php esc_html_e( 'What kind is it?', 'rapm' ); ?></legend>
					<?php foreach ( self::KIND_ORDER as $i => $kind ) : ?>
						<?php $info = RAPM_Slots::kind( $kind ); ?>
						<label class="rapm-ns-type">
							<input type="radio" name="kind" value="<?php echo esc_attr( $kind ); ?>" data-example="<?php echo esc_attr( $examples[ $kind ] ); ?>" data-label="<?php echo esc_attr( $info['label'] ); ?>"<?php checked( 0, $i ); ?> />
							<span class="rapm-ns-shape rapm-ns-shape-<?php echo esc_attr( $kind ); ?>" aria-hidden="true"><i></i><i></i><i></i><i></i></span>
							<strong><?php echo esc_html( $info['label'] ); ?></strong>
							<span><?php echo esc_html( $info['help'] ); ?></span>
						</label>
					<?php endforeach; ?>
				</fieldset>
				<p class="rapm-ns-field">
					<label for="rapm-ns-name"><?php esc_html_e( 'Name it for its job, not its page', 'rapm' ); ?></label>
					<input type="text" id="rapm-ns-name" name="name" maxlength="60" required placeholder="<?php echo esc_attr( $examples['hero'] ); ?>" />
					<span class="rapm-ns-help"><?php esc_html_e( 'A spot can go on more than one page, so a page name stops being true. Good names: "Main slider", "Seasonal feature banner", "Living Room slider".', 'rapm' ); ?></span>
				</p>
				<p class="rapm-ns-preview">
					<span><?php esc_html_e( 'Clients will see', 'rapm' ); ?></span>
					<strong class="rapm-ns-preview-name"></strong> <span class="rapm-type-chip rapm-ns-preview-type"><?php echo esc_html( RAPM_Slots::kind( 'hero' )['label'] ); ?></span>
				</p>
				<p class="rapm-ns-status" aria-live="polite"></p>
				<p class="rapm-ns-actions">
					<button type="button" class="button rapm-ns-cancel"><?php esc_html_e( 'Cancel', 'rapm' ); ?></button>
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Create spot', 'rapm' ); ?></button>
				</p>
			</form>
		</dialog>
		<?php
	}

	private static function render_notices() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['trashed'], $_GET['ids'] ) ) {
			$id   = absint( strtok( sanitize_text_field( wp_unslash( $_GET['ids'] ) ), ',' ) );
			$undo = $id ? wp_nonce_url( admin_url( 'post.php?post=' . $id . '&action=untrash' ), 'untrash-post_' . $id ) : '';
			?>
			<div class="notice notice-success is-dismissible"><p>
				<?php esc_html_e( 'Moved to the trash.', 'rapm' ); ?>
				<?php if ( $undo ) : ?>
					<a href="<?php echo esc_url( $undo ); ?>"><?php esc_html_e( 'Undo', 'rapm' ); ?></a>
				<?php endif; ?>
			</p></div>
			<?php
		} elseif ( isset( $_GET['rapm_spot_created'] ) && false !== strpos( sanitize_text_field( wp_unslash( $_GET['rapm_spot_created'] ) ), '|' ) ) {
			list( $kind, $placement ) = explode( '|', sanitize_text_field( wp_unslash( $_GET['rapm_spot_created'] ) ), 2 );
			$kind      = sanitize_key( $kind );
			$placement = sanitize_title( $placement );
			$name      = RAPM_Spots::name( $kind, $placement );
			?>
			<div class="notice notice-success is-dismissible"><p>
				<strong>
				<?php
				/* translators: %s: the new spot's name */
				echo esc_html( sprintf( __( '"%s" is ready.', 'rapm' ), $name ) );
				?>
				</strong>
				<?php if ( RAPM_Spots::is_carousel( $kind ) ) : ?>
					<?php
					/* translators: %s: the new spot's name */
					echo esc_html( sprintf( __( 'Next, put it on a page: in Elementor, add the Promo Carousel widget and pick "%s" from its Spot list.', 'rapm' ), $name ) );
					?>
				<?php else : ?>
					<?php esc_html_e( 'Next, put it on a page: in Elementor, add a Shortcode widget and paste this code.', 'rapm' ); ?>
				<?php endif; ?>
				<?php esc_html_e( 'Code:', 'rapm' ); ?> <code><?php echo esc_html( RAPM_Spots::shortcode( $kind, $placement ) ); ?></code>
			</p></div>
			<?php
		} elseif ( isset( $_GET['rapm_spot_removed'] ) ) {
			?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Spot removed.', 'rapm' ); ?></p></div>
			<?php
		} elseif ( isset( $_GET['untrashed'] ) ) {
			?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Brought back from the trash.', 'rapm' ); ?></p></div>
			<?php
		}
		// phpcs:enable
	}

	private static function render_spot( $spot ) {
		$kind      = $spot['kind'];
		$placement = $spot['placement'];
		$kind_info = RAPM_Slots::kind( $kind );
		$slot      = RAPM_Slots::get( $kind_info['desktop'] );
		$ratio     = $slot ? (int) $slot['width'] . ' / ' . (int) $slot['height'] : '16 / 9';
		$min       = isset( self::CARD_MIN[ $kind ] ) ? self::CARD_MIN[ $kind ] : 220;
		$name      = self::spot_name( $kind, $placement );
		$used_on   = RAPM_Spot_Usage::for_spot( $kind, $placement );
		$heading   = 'rapm-spot-' . sanitize_html_class( $kind . '-' . $placement );
		$add_url   = add_query_arg(
			array(
				'page'      => 'rapm-add-asset',
				'kind'      => $kind,
				'placement' => $placement,
			),
			admin_url( self::PARENT )
		);

		$live  = array();
		$ended = array();
		foreach ( $spot['posts'] as $post ) {
			$d = self::describe( $post );
			if ( 'ended' === $d['state'] ) {
				$ended[] = array( $post, $d );
			} else {
				$live[] = array( $post, $d );
			}
		}
		?>
		<section class="rapm-spot" aria-labelledby="<?php echo esc_attr( $heading ); ?>" data-kind="<?php echo esc_attr( $kind ); ?>" data-placement="<?php echo esc_attr( $placement ); ?>">
			<div class="rapm-spot-head">
				<div class="rapm-spot-info">
					<div class="rapm-spot-title">
						<h2 id="<?php echo esc_attr( $heading ); ?>" class="rapm-spot-name"><?php echo esc_html( $name ); ?></h2>
						<span class="rapm-type-chip"><?php echo esc_html( RAPM_Spots::type_label( $kind ) ); ?></span>
					</div>
					<p class="rapm-spot-on">
						<?php if ( $used_on ) : ?>
							<?php esc_html_e( 'Shows on:', 'rapm' ); ?>
							<?php
							$links = array();
							foreach ( $used_on as $use ) {
								$links[] = $use['url']
									? '<a href="' . esc_url( $use['url'] ) . '" target="_blank" rel="noopener">' . esc_html( $use['label'] ) . '</a>'
									: esc_html( $use['label'] );
							}
							echo implode( ', ', $links ); // phpcs:ignore WordPress.Security.EscapeOutput -- each part escaped above.
							?>
						<?php else : ?>
							<span class="rapm-spot-unplaced"><?php esc_html_e( 'Not on any page yet. Put its spot code (below) on a page to show it.', 'rapm' ); ?></span>
						<?php endif; ?>
						<?php if ( count( $spot['posts'] ) > 1 ) : ?>
							<span class="rapm-spot-hint"> · <?php echo 'look' === $kind ? esc_html__( 'Drag to change the order of the tabs', 'rapm' ) : esc_html__( 'Drag to change the order they play in', 'rapm' ); ?></span>
						<?php endif; ?>
					</p>
					<div class="rapm-spot-ra">
						<p><?php esc_html_e( 'Spot code:', 'rapm' ); ?> <code><?php echo esc_html( RAPM_Spots::shortcode( $kind, $placement ) ); ?></code></p>
						<form class="rapm-rename">
							<label for="<?php echo esc_attr( $heading ); ?>-name"><?php esc_html_e( 'Spot name', 'rapm' ); ?></label>
							<input type="text" id="<?php echo esc_attr( $heading ); ?>-name" name="name" value="<?php echo esc_attr( $name ); ?>" maxlength="60" />
							<button type="submit" class="button button-small"><?php esc_html_e( 'Save name', 'rapm' ); ?></button>
						</form>
						<?php if ( RAPM_Spots::is_carousel( $kind ) ) : ?>
							<p><?php esc_html_e( 'Main heading (H1) for search engines: set it where the spot is placed, only on pages with no other H1. In Elementor, turn on "Main heading (H1)" in its Promo Carousel widget, or add heading="h1" to its code on that page.', 'rapm' ); ?></p>
						<?php endif; ?>
						<?php if ( RAPM_Spots::removable( $kind, $placement ) ) : ?>
							<button type="button" class="button-link rapm-remove-spot"><?php esc_html_e( 'Remove this spot', 'rapm' ); ?></button>
						<?php endif; ?>
					</div>
				</div>
				<p class="rapm-spot-count"><?php echo esc_html( self::count_line( $spot['posts'] ) ); ?></p>
			</div>

			<?php if ( 'look' === $kind ) : ?>
				<?php self::render_tab_order( $live ); ?>
			<?php endif; ?>

			<div class="rapm-grid" style="<?php echo esc_attr( '--rapm-min:' . $min . 'px;--rapm-ratio:' . $ratio ); ?>">
				<?php
				foreach ( $live as $item ) {
					self::render_card( $item[0], $item[1] );
				}
				foreach ( $ended as $item ) {
					self::render_card( $item[0], $item[1] );
				}
				?>
				<a class="rapm-add" href="<?php echo esc_url( $add_url ); ?>">
					<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" focusable="false"><path d="M12 5v14M5 12h14"/></svg>
					<span><?php echo 'look' === $kind ? esc_html__( 'Add a look', 'rapm' ) : esc_html__( 'Add a picture', 'rapm' ); ?></span>
				</a>
			</div>

			<?php if ( $ended ) : ?>
				<p class="rapm-ended-row">
					<button type="button" class="button rapm-ended-toggle" aria-expanded="false" data-count="<?php echo esc_attr( count( $ended ) ); ?>">
						<?php
						/* translators: %d: number of promotions whose end date has passed */
						echo esc_html( sprintf( __( 'Show %d ended', 'rapm' ), count( $ended ) ) );
						?>
					</button>
				</p>
			<?php endif; ?>
			<p class="rapm-spot-status" aria-live="polite"></p>
		</section>
		<?php
	}

	private static function render_card( $post, $d ) {
		$id         = $post->ID;
		$title      = get_the_title( $post );
		$title      = '' !== $title ? $title : __( '(no name)', 'rapm' );
		$image_id   = (int) get_post_meta( $id, '_rapm_image_desktop_id', true );
		$image      = $image_id ? wp_get_attachment_image_url( $image_id, 'medium_large' ) : '';
		$image      = $image ? $image : ( $image_id ? wp_get_attachment_image_url( $image_id, 'full' ) : '' );
		$edit_url   = admin_url( 'edit.php?post_type=rapm_asset&page=rapm-add-asset&edit=' . $id );
		$copy_url   = wp_nonce_url( admin_url( 'admin-post.php?action=rapm_duplicate_asset&rapm_duplicate=' . $id ), 'rapm_duplicate_' . $id );
		$trash_url  = get_delete_post_link( $id );
		$link_issue = get_post_meta( $id, '_rapm_image_desktop_sync_error', true ) || get_post_meta( $id, '_rapm_image_mobile_sync_error', true );
		$ended      = 'ended' === $d['state'];
		$is_look    = 'look' === get_post_meta( $id, '_rapm_kind', true );
		$tab        = $is_look ? self::tab_label( $post ) : '';
		// Pieces shoppers can see: dots whose product is still published.
		$pieces     = 0;
		if ( $is_look ) {
			foreach ( RAPM_Looks::sanitize_dots( get_post_meta( $id, '_rapm_dots', true ) ) as $dot ) {
				$pieces += RAPM_Looks::product_info( $dot['p'] ) ? 1 : 0;
			}
		}
		?>
		<article class="rapm-card is-<?php echo esc_attr( $d['state'] ); ?>" data-id="<?php echo esc_attr( $id ); ?>" data-title="<?php echo esc_attr( strtolower( $title . ( $tab ? ' ' . $tab : '' ) ) ); ?>"<?php echo $tab ? ' data-tab="' . esc_attr( $tab ) . '"' : ''; ?><?php echo $ended ? ' hidden' : ''; ?>>
			<a class="rapm-card-pic" href="<?php echo esc_url( $edit_url ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: promotion name */ __( 'Edit %s', 'rapm' ), $title ) ); ?>" draggable="false">
				<?php if ( $image ) : ?>
					<img src="<?php echo esc_url( $image ); ?>" alt="" loading="lazy" draggable="false" />
				<?php else : ?>
					<span class="rapm-card-nopic"><?php esc_html_e( 'No picture yet', 'rapm' ); ?></span>
				<?php endif; ?>
			</a>
			<div class="rapm-card-body">
				<div class="rapm-card-top">
					<div>
						<h3 class="rapm-card-title"><?php echo esc_html( $title ); ?></h3>
						<?php if ( $tab ) : ?>
							<p class="rapm-card-tab"><?php esc_html_e( 'Tab:', 'rapm' ); ?> <strong><?php echo esc_html( $tab ); ?></strong> &middot; <?php echo esc_html( sprintf( /* translators: %d: how many products have a dot on the look's photo */ _n( '%d piece', '%d pieces', $pieces, 'rapm' ), $pieces ) ); ?></p>
						<?php endif; ?>
					</div>
					<button type="button" class="rapm-card-more" aria-haspopup="true" aria-expanded="false" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: promotion name */ __( 'More for %s', 'rapm' ), $title ) ); ?>">
						<svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" focusable="false"><circle cx="4" cy="10" r="1.7"/><circle cx="10" cy="10" r="1.7"/><circle cx="16" cy="10" r="1.7"/></svg>
					</button>
					<div class="rapm-card-menu" hidden>
						<a href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Edit', 'rapm' ); ?></a>
						<a href="<?php echo esc_url( $copy_url ); ?>"><?php esc_html_e( 'Make a copy', 'rapm' ); ?></a>
						<button type="button" data-move="-1"><?php esc_html_e( 'Move earlier', 'rapm' ); ?></button>
						<button type="button" data-move="1"><?php esc_html_e( 'Move later', 'rapm' ); ?></button>
						<?php if ( $trash_url ) : ?>
							<a href="<?php echo esc_url( $trash_url ); ?>" class="rapm-card-trash"><?php esc_html_e( 'Move to trash', 'rapm' ); ?></a>
						<?php endif; ?>
					</div>
				</div>
				<div class="rapm-card-status">
					<div class="rapm-card-state">
						<span class="rapm-pill"><span class="rapm-dot" aria-hidden="true"></span><span class="rapm-pill-text"><?php echo esc_html( $d['status'] ); ?></span></span>
						<span class="rapm-when<?php echo $d['soon'] ? ' is-soon' : ''; ?>"><?php echo esc_html( $d['when'] ); ?></span>
					</div>
					<?php if ( $ended ) : ?>
						<a class="button rapm-use-again" href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Use again', 'rapm' ); ?></a>
					<?php else : ?>
						<button type="button" class="rapm-switch" role="switch" aria-checked="<?php echo 'publish' === $post->post_status ? 'true' : 'false'; ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: promotion name */ __( 'Show %s on the website', 'rapm' ), $title ) ); ?>">
							<span class="rapm-switch-track" aria-hidden="true"><span class="rapm-switch-knob"></span></span>
						</button>
					<?php endif; ?>
				</div>
				<?php if ( $link_issue ) : ?>
					<p class="rapm-card-issue"><?php esc_html_e( 'The picture link stopped working. The last picture that worked is still showing.', 'rapm' ); ?></p>
				<?php endif; ?>
			</div>
		</article>
		<?php
	}

	/** A look's tab name (Shop the Look, 1.30.0), or its internal name if it has none. */
	private static function tab_label( $post ) {
		$tab = trim( (string) get_post_meta( $post->ID, '_rapm_tab_label', true ) );
		return '' !== $tab ? $tab : get_the_title( $post );
	}

	/**
	 * "Tabs on the website, in this order:" for a Shop the Look spot (1.30.0),
	 * from the looks showing now, in card order. rapm-promotions-admin.js
	 * redraws it the same way after a switch or a drag, from each card's
	 * data-tab and state.
	 *
	 * @param array $live List of array( $post, describe() ) that haven't ended.
	 */
	private static function render_tab_order( $live ) {
		$tabs = array();
		foreach ( $live as $item ) {
			if ( 'showing' === $item[1]['state'] ) {
				$tabs[] = self::tab_label( $item[0] );
			}
		}
		?>
		<div class="rapm-tab-order" data-none="<?php esc_attr_e( 'None showing. Switch a look on to give this spot a tab.', 'rapm' ); ?>">
			<span class="rapm-tab-order-label"><?php esc_html_e( 'Tabs on the website, in this order:', 'rapm' ); ?></span>
			<?php if ( $tabs ) : ?>
				<ol class="rapm-tab-order-list">
					<?php foreach ( $tabs as $tab ) : ?>
						<li><?php echo esc_html( $tab ); ?></li>
					<?php endforeach; ?>
				</ol>
			<?php else : ?>
				<span class="rapm-tab-order-none"><?php esc_html_e( 'None showing. Switch a look on to give this spot a tab.', 'rapm' ); ?></span>
			<?php endif; ?>
		</div>
		<?php
	}

	/* ------------------------------------------------------------------
	 * AJAX
	 * ------------------------------------------------------------------ */

	/** The on/off switch: on = published (shows on the site), off = draft (doesn't). */
	public static function ajax_toggle() {
		check_ajax_referer( self::NONCE, 'nonce' );
		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		if ( ! $id || 'rapm_asset' !== get_post_type( $id ) || ! current_user_can( 'edit_post', $id ) ) {
			wp_send_json_error( array( 'message' => __( 'Not allowed.', 'rapm' ) ), 403 );
		}
		$on     = ! empty( $_POST['on'] ) && '1' === $_POST['on'];
		$result = wp_update_post(
			array(
				'ID'          => $id,
				'post_status' => $on ? 'publish' : 'draft',
			),
			true
		);
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 500 );
		}

		$post      = get_post( $id );
		$kind      = get_post_meta( $id, '_rapm_kind', true ) ?: 'hero'; // phpcs:ignore
		$placement = get_post_meta( $id, '_rapm_placement', true ) ?: 'default'; // phpcs:ignore
		wp_send_json_success(
			array(
				'card'      => self::describe( $post ),
				'on'        => 'publish' === $post->post_status,
				'countLine' => self::count_line( self::spot_posts( $kind, $placement ) ),
			)
		);
	}

	public static function ajax_rename() {
		check_ajax_referer( self::NONCE, 'nonce' );
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Not allowed.', 'rapm' ) ), 403 );
		}
		$kind      = isset( $_POST['kind'] ) ? sanitize_key( wp_unslash( $_POST['kind'] ) ) : '';
		$placement = isset( $_POST['placement'] ) ? sanitize_title( wp_unslash( $_POST['placement'] ) ) : '';
		$name      = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		if ( ! $kind || ! $placement ) {
			wp_send_json_error( array( 'message' => __( 'Missing spot.', 'rapm' ) ), 400 );
		}
		// Blank goes back to the automatic name.
		RAPM_Spots::update( $kind, $placement, array( 'name' => '' === $name ? null : mb_substr( $name, 0, 60 ) ) );
		wp_send_json_success( array( 'name' => self::spot_name( $kind, $placement ) ) );
	}

	/** "+ New spot": makes the spot and sends the screen to show it with a "ready" message. */
	public static function ajax_create_spot() {
		check_ajax_referer( self::NONCE, 'nonce' );
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Not allowed.', 'rapm' ) ), 403 );
		}
		$kind = isset( $_POST['kind'] ) ? sanitize_key( wp_unslash( $_POST['kind'] ) ) : '';
		$name = isset( $_POST['name'] ) ? mb_substr( sanitize_text_field( wp_unslash( $_POST['name'] ) ), 0, 60 ) : '';
		if ( ! in_array( $kind, self::KIND_ORDER, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Pick what kind of spot it is.', 'rapm' ) ), 400 );
		}
		if ( '' === trim( $name ) ) {
			wp_send_json_error( array( 'message' => __( 'Give the spot a name.', 'rapm' ) ), 400 );
		}
		$placement = RAPM_Spots::create( $kind, $name );
		wp_send_json_success( array( 'url' => self::url( array( 'rapm_spot_created' => $kind . '|' . $placement ) ) ) );
	}

	/** "Remove this spot" (R&A, 1.29.1): only a spot with no promotions that isn't on any page. */
	public static function ajax_remove_spot() {
		check_ajax_referer( self::NONCE, 'nonce' );
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Not allowed.', 'rapm' ) ), 403 );
		}
		$kind      = isset( $_POST['kind'] ) ? sanitize_key( wp_unslash( $_POST['kind'] ) ) : '';
		$placement = isset( $_POST['placement'] ) ? sanitize_title( wp_unslash( $_POST['placement'] ) ) : '';
		if ( ! RAPM_Spots::removable( $kind, $placement ) ) {
			wp_send_json_error( array( 'message' => __( 'This spot still has promotions or is on a page, so it stays.', 'rapm' ) ), 400 );
		}
		RAPM_Spots::remove( $kind, $placement );
		wp_send_json_success( array( 'url' => self::url( array( 'rapm_spot_removed' => 1 ) ) ) );
	}

	/**
	 * WordPress brings a post back from the trash as a draft (since 5.6),
	 * which would quietly switch a promotion off. "Undo" should put it
	 * back exactly as it was.
	 */
	public static function untrash_status( $new_status, $post_id, $previous_status ) {
		return 'rapm_asset' === get_post_type( $post_id ) && $previous_status ? $previous_status : $new_status;
	}
}
