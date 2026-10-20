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

	/**
	 * Design Center (1.46.0, Phil: "just add a 'Design Center' above training
	 * guide for these elements. It's accessible to everyone but I would only
	 * let those able to manage it have training on it"). Split sliders, their
	 * room photos and the promotion box canvas live here, so the Promotions
	 * page stays simple for clients. Its how-to is on the page itself, not in
	 * the client Training Guide.
	 */
	const DESIGN_PAGE = 'rapm-design-center';
	const PARENT = 'edit.php?post_type=rapm_asset';
	const NONCE  = 'rapm_promotions';

	/** Spot kinds in the order their sections appear, with the plain word for each and the narrowest card width. */
	const KIND_ORDER = array( 'hero', 'look', 'fold_banner', 'quick_links', 'coupon', 'marquee' );
	const CARD_MIN   = array(
		'hero'        => 260,
		'look'        => 260,
		'fold_banner' => 420,
		'coupon'      => 170,
		'marquee'     => 170,
		'quick_links' => 240,
		'promo_box'   => 200,
		'reveal'      => 220,
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
			self::DESIGN_PAGE,
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
		add_submenu_page(
			self::PARENT,
			__( 'Design Center', 'rapm' ),
			__( 'Design Center', 'rapm' ),
			'edit_posts',
			self::DESIGN_PAGE,
			array( __CLASS__, 'render_design_center' )
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
		// 1.56.0: WordPress names the tab after the menu item, "New promotion", even while editing one.
		if ( 'rapm-add-asset' === $plugin_page && ! empty( $_GET['edit'] ) && ! get_post_meta( absint( $_GET['edit'] ), '_rapm_from_gallery', true ) && 0 === strpos( $admin_title, (string) $title ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return esc_html__( 'Edit promotion', 'rapm' ) . substr( $admin_title, strlen( (string) $title ) );
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

	/** The Design Center's open tab: 'split' (Split sliders), 'library' (Asset library, 1.48.0) or 'brand' (Brand kit, 1.50.0). */
	public static function design_tab() {
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return in_array( $tab, array( 'library', 'brand', 'reveal' ), true ) ? $tab : 'split'; // 1.53.0: Reveal panels.
	}

	/** The Design Center's address (1.46.0), optionally at one slider's section. */
	public static function design_url( $placement = '' ) {
		$url = add_query_arg( array( 'page' => self::DESIGN_PAGE ), admin_url( self::PARENT ) );
		return $placement ? $url . '#rapm-spot-hero-' . sanitize_html_class( $placement ) : $url;
	}

	public static function enqueue( $hook ) {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( self::PAGE !== $page && self::DESIGN_PAGE !== $page ) {
			return;
		}
		if ( self::DESIGN_PAGE === $page && self::design_tab() === 'library' ) {
			RAPM_Library::enqueue();
		}
		if ( self::DESIGN_PAGE === $page && self::design_tab() === 'brand' ) {
			RAPM_Brand_Kit::enqueue();
		}
		// 1.44.1: the live site's styles, for designed promotion box cards.
		wp_enqueue_style( 'rapm-hero-css', RAPM_URL . 'assets/css/rapm-hero.css', array(), RAPM_VERSION );
		wp_enqueue_style( 'rapm-promotions-admin', RAPM_URL . 'assets/css/rapm-promotions-admin.css', array( 'rapm-hero-css' ), RAPM_VERSION );
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
					// 1.52.0: linked groups.
					'goesSaved'   => __( 'Saved. It plays with that promotion now.', 'rapm' ),
					'goesOne'     => __( '%d room photo goes with it', 'rapm' ),
					'goesMany'    => __( '%d room photos go with it', 'rapm' ),
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
		// 1.43.0: a Slider set to split view always shows its promotion box
		// spot, even before it has a promotion, so there's a place to add one.
		foreach ( $spots as $spot ) {
			$box_key = 'promo_box|' . $spot['placement'];
			if ( 'hero' === $spot['kind'] && ! isset( $spots[ $box_key ] ) && RAPM_Split::is_split( $spot['placement'] ) ) {
				$spots[ $box_key ] = array( 'kind' => 'promo_box', 'placement' => $spot['placement'], 'posts' => array() );
			}
		}

		uasort(
			$spots,
			function ( $a, $b ) {
				// A promotion box sorts right after the Slider it sits beside.
				$ka = array_search( 'promo_box' === $a['kind'] ? 'hero' : $a['kind'], self::KIND_ORDER, true );
				$kb = array_search( 'promo_box' === $b['kind'] ? 'hero' : $b['kind'], self::KIND_ORDER, true );
				$ka = false === $ka ? 99 : $ka;
				$kb = false === $kb ? 99 : $kb;
				if ( $ka !== $kb ) {
					return $ka - $kb;
				}
				if ( $a['placement'] === $b['placement'] ) {
					return 'promo_box' === $a['kind'] ? 1 : ( 'promo_box' === $b['kind'] ? -1 : 0 );
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
				<?php RAPM_Tip::empty_state( 'spots', __( 'No promotions yet', 'rapm' ), __( 'Promotions go in spots: places on your website, like a slider at the top of the home page. Make a spot, then add a picture to it.', 'rapm' ), '<button type="button" class="button button-primary rapm-new-spot-open">' . esc_html__( '+ New spot', 'rapm' ) . '</button>' ); ?>
			<?php endif; ?>

			<p class="rapm-no-results" hidden></p>

			<?php
			foreach ( $spots as $spot ) {
				// 1.44.1 (Phil): a split slider and its promotion box are one
				// section, so the box isn't listed again on its own.
				$box_key = 'promo_box|' . $spot['placement'];
				if ( 'promo_box' === $spot['kind'] && isset( $spots[ 'hero|' . $spot['placement'] ] ) && RAPM_Split::is_split( $spot['placement'] ) ) {
					continue;
				}
				// 1.46.0: a split slider is worked on in the Design Center;
				// here it's a short card that links there.
				if ( 'hero' === $spot['kind'] && RAPM_Split::is_split( $spot['placement'] ) ) {
					self::render_split_pointer( $spot, isset( $spots[ $box_key ] ) ? $spots[ $box_key ] : null );
					continue;
				}
				// 1.53.0: Reveal panels are worked on in the Design Center too.
				if ( 'reveal' === $spot['kind'] ) {
					self::render_reveal_pointer( $spot );
					continue;
				}
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
				<p><?php esc_html_e( 'Each section above shows its code for the page and a box to rename it. A new spot shows here when you click "+ New spot", when its code is placed on a page, or when a promotion is added with a new spot name.', 'rapm' ); ?></p>
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
			'quick_links' => __( 'For example: Category quick links', 'rapm' ),
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

	/**
	 * One spot's section. $box (1.44.1) is the promotion box spot of a
	 * Slider set to split view: Phil found the two separate sections hard to
	 * follow ("I need these both housed within the same section"), so the
	 * Slider's section then holds two areas laid out like the website, the
	 * room photos and the promotion box, each with its own cards, order,
	 * count and Add card.
	 */
	private static function render_spot( $spot, $box = null, $where = 'promotions' ) {
		$kind      = $spot['kind'];
		$placement = $spot['placement'];
		$kind_info = RAPM_Slots::kind_for( $kind, $placement );
		$slot      = RAPM_Slots::get( $kind_info['desktop'] );
		$ratio     = $slot ? (int) $slot['width'] . ' / ' . (int) $slot['height'] : '16 / 9';
		$min       = isset( self::CARD_MIN[ $kind ] ) ? self::CARD_MIN[ $kind ] : 220;
		$name      = self::spot_name( $kind, $placement );
		$used_on   = RAPM_Spot_Usage::for_spot( $kind, $placement );
		// 1.43.0: a promotion box in split view shows wherever its Slider does.
		$box_split = 'promo_box' === $kind && RAPM_Split::is_split( $placement );
		if ( $box_split ) {
			$used_on = array_merge( RAPM_Spot_Usage::for_spot( 'hero', $placement ), $used_on );
		}
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
						<span class="rapm-type-chip"><?php echo esc_html( $box ? __( 'Slider · Split view', 'rapm' ) : RAPM_Spots::type_label( $kind ) ); ?></span>
					</div>
					<p class="rapm-spot-on">
						<?php if ( $used_on ) : ?>
							<?php esc_html_e( 'Shows on:', 'rapm' ); ?>
							<?php echo RAPM_Spot_Usage::links_html( $used_on ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in links_html(). ?>
						<?php else : ?>
							<span class="rapm-spot-unplaced"><?php esc_html_e( 'Not on any page yet. Put its spot code (below) on a page to show it.', 'rapm' ); ?></span>
						<?php endif; ?>
						<?php if ( count( $spot['posts'] ) > 1 && ! $box ) : ?>
							<span class="rapm-spot-hint"> · <?php echo 'look' === $kind ? esc_html__( 'Drag to change the order of the tabs', 'rapm' ) : ( in_array( $kind, array( 'quick_links', 'reveal' ), true ) ? esc_html__( 'Drag to change the order they show in', 'rapm' ) : esc_html__( 'Drag to change the order they play in', 'rapm' ) ); ?></span>
						<?php endif; ?>
					</p>
					<div class="rapm-spot-ra">
						<p><?php esc_html_e( 'Code for the page:', 'rapm' ); ?> <code><?php echo esc_html( RAPM_Spots::shortcode( $kind, $placement ) ); ?></code></p>
						<form class="rapm-rename">
							<label for="<?php echo esc_attr( $heading ); ?>-name"><?php esc_html_e( 'Spot name', 'rapm' ); ?></label>
							<input type="text" id="<?php echo esc_attr( $heading ); ?>-name" name="name" value="<?php echo esc_attr( $name ); ?>" maxlength="60" />
							<button type="submit" class="button button-small"><?php esc_html_e( 'Save name', 'rapm' ); ?></button>
						</form>
						<?php if ( 'hero' === $kind && 'design' === $where ) : ?>
							<?php self::render_layout_form( $placement, $heading ); ?>
						<?php elseif ( 'hero' === $kind ) : ?>
							<p>
								<?php esc_html_e( 'Rooms beside a promotion that stays put:', 'rapm' ); ?>
								<a href="<?php echo esc_url( self::design_url() ); ?>"><?php esc_html_e( 'make it a split slider in the Design Center', 'rapm' ); ?></a>.
							</p>
						<?php elseif ( 'promo_box' === $kind ) : ?>
							<p>
								<?php
								echo esc_html(
									$box_split
										/* translators: %s: the slider's name */
										? sprintf( __( 'Shows beside "%s", which is set to split view. The first promotion here that is on and within its dates shows; when none is, the slider goes back to full width.', 'rapm' ), self::spot_name( 'hero', $placement ) )
										/* translators: %s: the slider's name */
										: sprintf( __( 'Shows beside "%s" only when that slider\'s layout is set to Split.', 'rapm' ), self::spot_name( 'hero', $placement ) )
								);
								?>
							</p>
						<?php endif; ?>
						<?php if ( RAPM_Spots::is_carousel( $kind ) ) : ?>
							<p><?php esc_html_e( 'Main heading (H1) for search engines: set it where the spot is placed, only on pages with no other H1. In Elementor, turn on "Main heading (H1)" in its Promo Carousel widget, or add heading="h1" to its code on that page.', 'rapm' ); ?></p>
						<?php endif; ?>
						<?php if ( RAPM_Spots::removable( $kind, $placement ) ) : ?>
							<button type="button" class="button-link rapm-remove-spot"><?php esc_html_e( 'Remove this spot', 'rapm' ); ?></button>
						<?php endif; ?>
					</div>
				</div>
				<?php if ( ! $box ) : ?>
					<p class="rapm-spot-count"><?php echo esc_html( self::count_line( $spot['posts'] ) ); ?></p>
				<?php endif; ?>
			</div>

			<?php if ( 'look' === $kind ) : ?>
				<?php self::render_tab_order( $live ); ?>
			<?php endif; ?>

			<?php if ( $box ) : ?>
				<?php self::render_split_areas( $spot, $box, $live, $ended, $min, $ratio, $add_url ); ?>
			<?php else : ?>

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
					<span><?php echo 'look' === $kind ? esc_html__( 'Add a look', 'rapm' ) : ( 'promo_box' === $kind ? esc_html__( 'Add a promotion', 'rapm' ) : ( 'reveal' === $kind ? esc_html__( 'Add a panel', 'rapm' ) : esc_html__( 'Add a picture', 'rapm' ) ) ); ?></span>
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
			<?php endif; // $box ?>
			<p class="rapm-spot-status" aria-live="polite"></p>
		</section>
		<?php
	}

	/**
	 * A split slider on the Promotions page (1.46.0): its name, where it
	 * shows and what's showing, with a link to work on it in the Design
	 * Center. No cards here, so clients aren't handed the design tools.
	 */
	private static function render_split_pointer( $spot, $box ) {
		$placement = $spot['placement'];
		$heading   = 'rapm-spot-hero-' . sanitize_html_class( $placement );
		$used_on   = RAPM_Spot_Usage::for_spot( 'hero', $placement );
		?>
		<section class="rapm-spot rapm-split-pointer" aria-labelledby="<?php echo esc_attr( $heading ); ?>" data-kind="hero" data-placement="<?php echo esc_attr( $placement ); ?>">
			<div class="rapm-spot-head">
				<div class="rapm-spot-info">
					<div class="rapm-spot-title">
						<h2 id="<?php echo esc_attr( $heading ); ?>" class="rapm-spot-name"><?php echo esc_html( self::spot_name( 'hero', $placement ) ); ?></h2>
						<span class="rapm-type-chip"><?php esc_html_e( 'Slider · Split view', 'rapm' ); ?></span>
					</div>
					<p class="rapm-spot-on">
						<?php if ( $used_on ) : ?>
							<?php esc_html_e( 'Shows on:', 'rapm' ); ?>
							<?php echo RAPM_Spot_Usage::links_html( $used_on ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in links_html(). ?>
						<?php else : ?>
							<span class="rapm-spot-unplaced"><?php esc_html_e( 'Not on any page yet.', 'rapm' ); ?></span>
						<?php endif; ?>
					</p>
					<p>
						<?php
						/* translators: 1: what's showing in the room photos, 2: what's showing in the promotion box */
						echo esc_html( sprintf( __( 'Room photos: %1$s. Promotion box: %2$s.', 'rapm' ), self::count_line( $spot['posts'] ), self::count_line( $box ? $box['posts'] : array() ) ) );
						?>
					</p>
				</div>
				<a class="button button-primary" href="<?php echo esc_url( self::design_url( $placement ) ); ?>"><?php esc_html_e( 'Open in the Design Center', 'rapm' ); ?></a>
			</div>
		</section>
		<?php
	}

	/** The Design Center page (1.46.0). */
	public static function render_design_center() {
		$spots  = self::spots();
		$split  = array();
		$plain  = array();
		foreach ( $spots as $spot ) {
			if ( 'hero' !== $spot['kind'] ) {
				continue;
			}
			if ( RAPM_Split::is_split( $spot['placement'] ) ) {
				$split[] = $spot;
			} else {
				$plain[] = $spot;
			}
		}
		?>
		<div class="wrap rapm-promos rapm-design-center">
			<div class="rapm-promos-head">
				<div>
					<h1 class="rapm-promos-title"><?php esc_html_e( 'Design Center', 'rapm' ); ?></h1>
					<p class="rapm-promos-sub"><?php esc_html_e( 'Advanced tools: split sliders, reveal panels, the asset library and the brand kit. Kept here so the Promotions page stays simple.', 'rapm' ); ?></p>
				</div>
			</div>
			<hr class="wp-header-end" />

			<?php // 1.48.0: tabs, as plain links (1.50.0 adds the Brand kit). ?>
			<nav class="nav-tab-wrapper rapm-dc-tabs" aria-label="<?php esc_attr_e( 'Design Center', 'rapm' ); ?>">
				<a href="<?php echo esc_url( self::design_url() ); ?>" class="nav-tab<?php echo 'split' === self::design_tab() ? ' nav-tab-active' : ''; ?>"<?php echo 'split' === self::design_tab() ? ' aria-current="page"' : ''; ?>><?php esc_html_e( 'Split sliders', 'rapm' ); ?></a>
				<a href="<?php echo esc_url( add_query_arg( 'tab', 'reveal', self::design_url() ) ); ?>" class="nav-tab<?php echo 'reveal' === self::design_tab() ? ' nav-tab-active' : ''; ?>"<?php echo 'reveal' === self::design_tab() ? ' aria-current="page"' : ''; ?>><?php esc_html_e( 'Reveal panels', 'rapm' ); ?></a>
				<a href="<?php echo esc_url( add_query_arg( 'tab', 'library', self::design_url() ) ); ?>" class="nav-tab<?php echo 'library' === self::design_tab() ? ' nav-tab-active' : ''; ?>"<?php echo 'library' === self::design_tab() ? ' aria-current="page"' : ''; ?>><?php esc_html_e( 'Asset library', 'rapm' ); ?></a>
				<a href="<?php echo esc_url( add_query_arg( 'tab', 'brand', self::design_url() ) ); ?>" class="nav-tab<?php echo 'brand' === self::design_tab() ? ' nav-tab-active' : ''; ?>"<?php echo 'brand' === self::design_tab() ? ' aria-current="page"' : ''; ?>><?php esc_html_e( 'Brand kit', 'rapm' ); ?></a>
			</nav>

			<?php if ( 'brand' === self::design_tab() ) : ?>
				<?php RAPM_Brand_Kit::render(); ?>
			</div>
			<?php
			return;
			endif;
			?>

			<?php if ( 'library' === self::design_tab() ) : ?>
				<?php RAPM_Library::render(); ?>
			</div>
			<?php
			return;
			endif;
			?>

			<?php if ( 'reveal' === self::design_tab() ) : ?>
				<?php self::render_reveal_tab( $spots ); ?>
			</div>
			<?php
			return;
			endif;
			?>

			<details class="rapm-dc-how">
				<summary><?php esc_html_e( 'How it works', 'rapm' ); ?></summary>
				<ol>
					<li><?php esc_html_e( 'Pick a slider below and turn on split view. Its section here then holds its Layout (widths, which side the promotion is on, full page or the site\'s width, and what phones show first), its room photos and its promotion box.', 'rapm' ); ?></li>
					<?php /* translators: 1: width, 2: height */ ?>
					<li><?php echo esc_html( sprintf( __( 'Room photos take turns like any slider. On computers they\'re %1$d by %2$d (4 by 3); phone pictures don\'t change. Drag the cards to change their order.', 'rapm' ), (int) RAPM_Slots::get( 'hero_split_desktop' )['width'], (int) RAPM_Slots::get( 'hero_split_desktop' )['height'] ) ); ?></li>
					<li><?php esc_html_e( 'The promotion box shows one promotion at a time: the first one that is on and within its dates. With none, the room photos go full width.', 'rapm' ); ?></li>
					<?php /* translators: 1: width, 2: height */ ?>
					<li><?php echo esc_html( sprintf( __( 'A promotion is either designed here (drag the headline, smaller line, button, Google or Font Awesome icons and your own pictures onto a square, on a color or a picture) or a finished %1$d by %2$d graphic.', 'rapm' ), (int) RAPM_Slots::get( 'promo_box_image' )['width'], (int) RAPM_Slots::get( 'promo_box_image' )['height'] ) ); ?></li>
					<li><?php esc_html_e( 'On the Promotions page a split slider shows as one short card with a link back here.', 'rapm' ); ?></li>
				</ol>
			</details>

			<section class="rapm-spot rapm-dc-make">
				<h2 class="rapm-spot-name"><?php esc_html_e( 'Make a slider split', 'rapm' ); ?></h2>
				<?php if ( $plain ) : ?>
					<form class="rapm-make-split">
						<label for="rapm-make-split-spot"><?php esc_html_e( 'Slider', 'rapm' ); ?></label>
						<select id="rapm-make-split-spot" name="placement">
							<?php foreach ( $plain as $spot ) : ?>
								<option value="<?php echo esc_attr( $spot['placement'] ); ?>"><?php echo esc_html( self::spot_name( 'hero', $spot['placement'] ) ); ?></option>
							<?php endforeach; ?>
						</select>
						<button type="submit" class="button button-primary"><?php esc_html_e( 'Turn on split view', 'rapm' ); ?></button>
					</form>
					<p class="description"><?php esc_html_e( 'Its pictures on computers then need to be 4 by 3; pictures already in it are trimmed to that shape until they\'re replaced. You can turn split view off again in its Layout.', 'rapm' ); ?></p>
				<?php else : ?>
					<p class="description"><?php esc_html_e( 'Every slider is already split. Add a new slider with "+ New spot" on the Promotions page.', 'rapm' ); ?></p>
				<?php endif; ?>
				<p class="rapm-spot-status" aria-live="polite"></p>
			</section>

			<?php if ( ! $split ) : ?>
				<?php RAPM_Tip::empty_state( 'split', __( 'No split sliders yet', 'rapm' ), __( 'A split slider shows room photos on one side and a sale on the other. Pick a slider above and click "Turn on split view" to make one.', 'rapm' ) ); ?>
			<?php endif; ?>
			<?php
			foreach ( $split as $spot ) {
				$box_key = 'promo_box|' . $spot['placement'];
				$box     = isset( $spots[ $box_key ] ) ? $spots[ $box_key ] : array( 'kind' => 'promo_box', 'placement' => $spot['placement'], 'posts' => array() );
				self::render_spot( $spot, $box, 'design' );
			}
			?>
		</div>
		<?php
	}

	/**
	 * Design Center › Reveal panels (1.53.0): every Reveal panels spot,
	 * each with its panel cards (one promotion per panel) and its code, and
	 * a form to make a new one.
	 */
	private static function render_reveal_tab( $spots ) {
		$reveal = array_filter( $spots, function ( $s ) { return 'reveal' === $s['kind']; } );
		?>
		<details class="rapm-dc-how">
			<summary><?php esc_html_e( 'How it works', 'rapm' ); ?></summary>
			<ol>
				<li><?php esc_html_e( 'Each panel is a promotion with its own dates: a color, one big word, a photo underneath, a short line and a link label. Up to four that are on and within their dates show side by side, in the order of the cards.', 'rapm' ); ?></li>
				<li><?php esc_html_e( 'On computers a panel opens when someone points at it or tabs to it: the photo shows, the word moves aside, and the short line and link label appear. Phones stack the panels, and each opens as it reaches the middle of the screen.', 'rapm' ); ?></li>
				<?php /* translators: 1: width, 2: height, 3: width, 4: height */ ?>
				<li><?php echo esc_html( sprintf( __( 'Photos: %1$d by %2$d for computers and %3$d by %4$d for phones.', 'rapm' ), (int) RAPM_Slots::get( 'reveal_desktop' )['width'], (int) RAPM_Slots::get( 'reveal_desktop' )['height'], (int) RAPM_Slots::get( 'reveal_mobile' )['width'], (int) RAPM_Slots::get( 'reveal_mobile' )['height'] ) ); ?></li>
				<li><?php esc_html_e( 'Put its code on a page (an Elementor Shortcode widget works). Add height="640" to make it taller on computers, or phone_height="360" on phones.', 'rapm' ); ?></li>
			</ol>
		</details>

		<section class="rapm-spot rapm-dc-make">
			<h2 class="rapm-spot-name"><?php esc_html_e( 'Make new reveal panels', 'rapm' ); ?></h2>
			<form class="rapm-make-reveal">
				<label for="rapm-make-reveal-name"><?php esc_html_e( 'Name', 'rapm' ); ?></label>
				<input type="text" id="rapm-make-reveal-name" name="name" maxlength="60" placeholder="<?php esc_attr_e( 'For example: Home page reveal', 'rapm' ); ?>" />
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Make it', 'rapm' ); ?></button>
			</form>
			<p class="rapm-spot-status" aria-live="polite"></p>
		</section>

		<?php if ( ! $reveal ) : ?>
			<?php RAPM_Tip::empty_state( 'reveal', __( 'No reveal panels yet', 'rapm' ), __( 'Reveal panels are big colors with one word each. When a shopper points at one, a room photo shows. Type a name above and click "Make it" to start.', 'rapm' ) ); ?>
		<?php endif; ?>
		<?php
		foreach ( $reveal as $spot ) {
			self::render_spot( $spot, null, 'design' );
		}
	}

	/**
	 * Reveal panels on the Promotions page (1.53.0): one short card that
	 * links to the Design Center, like a split slider.
	 */
	private static function render_reveal_pointer( $spot ) {
		$heading = 'rapm-spot-reveal-' . sanitize_html_class( $spot['placement'] );
		$used_on = RAPM_Spot_Usage::for_spot( 'reveal', $spot['placement'] );
		?>
		<section class="rapm-spot rapm-split-pointer" aria-labelledby="<?php echo esc_attr( $heading ); ?>" data-kind="reveal" data-placement="<?php echo esc_attr( $spot['placement'] ); ?>">
			<div class="rapm-spot-head">
				<div class="rapm-spot-info">
					<div class="rapm-spot-title">
						<h2 id="<?php echo esc_attr( $heading ); ?>" class="rapm-spot-name"><?php echo esc_html( self::spot_name( 'reveal', $spot['placement'] ) ); ?></h2>
						<span class="rapm-type-chip"><?php esc_html_e( 'Reveal panels', 'rapm' ); ?></span>
					</div>
					<p class="rapm-spot-on">
						<?php if ( $used_on ) : ?>
							<?php esc_html_e( 'Shows on:', 'rapm' ); ?>
							<?php echo RAPM_Spot_Usage::links_html( $used_on ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in links_html(). ?>
						<?php else : ?>
							<span class="rapm-spot-unplaced"><?php esc_html_e( 'Not on any page yet.', 'rapm' ); ?></span>
						<?php endif; ?>
					</p>
					<p><?php echo esc_html( sprintf( /* translators: %s: e.g. "2 showing now" */ __( 'Panels: %s.', 'rapm' ), self::count_line( $spot['posts'] ) ) ); ?></p>
				</div>
				<a class="button button-primary" href="<?php echo esc_url( add_query_arg( 'tab', 'reveal', self::design_url() ) . '#' . $heading ); ?>"><?php esc_html_e( 'Open in the Design Center', 'rapm' ); ?></a>
			</div>
		</section>
		<?php
	}

	/** Cards, in order: live ones, then ended ones, each as array( post, describe() ). */
	private static function split_posts( $posts ) {
		$live  = array();
		$ended = array();
		foreach ( $posts as $post ) {
			$d = self::describe( $post );
			if ( 'ended' === $d['state'] ) {
				$ended[] = array( $post, $d );
			} else {
				$live[] = array( $post, $d );
			}
		}
		return array( $live, $ended );
	}

	/** One area's cards, Add card and "Show ended" button (1.44.1, split sliders). */
	private static function render_area_grid( $live, $ended, $min, $ratio, $add_url, $add_label, $links = null ) {
		?>
		<div class="rapm-grid" style="<?php echo esc_attr( '--rapm-min:' . $min . 'px;--rapm-ratio:' . $ratio ); ?>">
			<?php
			foreach ( $live as $item ) {
				self::render_card( $item[0], $item[1], $links );
			}
			foreach ( $ended as $item ) {
				self::render_card( $item[0], $item[1], $links );
			}
			?>
			<a class="rapm-add" href="<?php echo esc_url( $add_url ); ?>">
				<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" focusable="false"><path d="M12 5v14M5 12h14"/></svg>
				<span><?php echo esc_html( $add_label ); ?></span>
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
		<?php
	}

	/**
	 * A split slider's two areas (1.44.1), side by side in the same widths
	 * and order as on the website (promotion on the left when the slider is
	 * set that way), stacking on narrow screens. Each area carries its own
	 * kind, so the on/off switch, drag order and count work per area.
	 */
	private static function render_split_areas( $spot, $box, $live, $ended, $min, $ratio, $add_url ) {
		$placement = $spot['placement'];
		$s         = RAPM_Split::settings( $placement );
		$cols      = RAPM_Split::COLS[ $s['cols'] ];
		$room      = RAPM_Slots::get( 'hero_split_desktop' );
		$pic       = RAPM_Slots::get( 'promo_box_image' );
		list( $box_live, $box_ended ) = self::split_posts( $box['posts'] );
		$box_add = add_query_arg(
			array(
				'page'      => 'rapm-add-asset',
				'kind'      => 'promo_box',
				'placement' => $placement,
			),
			admin_url( self::PARENT )
		);
		$room_side = 'left' === $s['side'] ? __( 'right', 'rapm' ) : __( 'left', 'rapm' );
		$box_side  = 'left' === $s['side'] ? __( 'left', 'rapm' ) : __( 'right', 'rapm' );
		// 1.52.0: linked groups. Each promotion gets a color in box order;
		// each room photo says which one it goes with.
		$linked = 'linked' === $s['box'];
		$groups = array();
		// Colors follow the box's own order, so one never changes color
		// when another promotion ends.
		foreach ( $box['posts'] as $n => $bp ) {
			$groups[ $bp->ID ] = array(
				'label' => '' !== get_the_title( $bp ) ? wp_strip_all_tags( html_entity_decode( get_the_title( $bp ), ENT_QUOTES, 'UTF-8' ) ) : __( '(no name)', 'rapm' ),
				'color' => RAPM_Split::GROUP_COLORS[ $n % count( RAPM_Split::GROUP_COLORS ) ],
				'ended' => 'ended' === self::describe( $bp )['state'],
				'count' => 0,
			);
		}
		foreach ( $spot['posts'] as $post ) {
			$g = RAPM_Split::goes_with( $post->ID );
			if ( $g && isset( $groups[ $g ] ) ) {
				$groups[ $g ]['count']++;
			}
		}
		$room_links = $linked ? array( 'type' => 'room', 'groups' => $groups ) : null;
		$box_links  = $linked ? array( 'type' => 'box', 'groups' => $groups ) : null;
		?>
		<div class="rapm-split-areas<?php echo 'left' === $s['side'] ? ' is-promo-left' : ''; ?>" style="<?php echo esc_attr( '--rapm-areas:' . $cols ); ?>">
			<div class="rapm-split-area" data-area="hero">
				<div class="rapm-split-area-head">
					<h3><?php esc_html_e( 'Room photos', 'rapm' ); ?></h3>
					<p class="rapm-spot-count"><?php echo esc_html( self::count_line( $spot['posts'] ) ); ?></p>
				</div>
				<p class="description">
					<?php
					/* translators: 1: left or right, 2: width in pixels, 3: height in pixels */
					echo esc_html( sprintf( __( 'Take turns on the %1$s. Computer pictures are %2$d by %3$d. Drag to change the order.', 'rapm' ), $room_side, (int) $room['width'], (int) $room['height'] ) );
					?>
				</p>
				<?php self::render_area_grid( $live, $ended, $min, $ratio, $add_url, __( 'Add a room photo', 'rapm' ), $room_links ); ?>
			</div>
			<div class="rapm-split-area" data-area="promo_box">
				<div class="rapm-split-area-head">
					<h3><?php esc_html_e( 'Promotion box', 'rapm' ); ?></h3>
					<p class="rapm-spot-count"><?php echo esc_html( self::count_line( $box['posts'] ) ); ?></p>
				</div>
				<p class="description">
					<?php
					$how = array(
						/* translators: %s: left or right */
						'still'  => __( 'On the %s. Shows the first one that is on and within its dates (set how it changes in the Layout above).', 'rapm' ),
						/* translators: %s: left or right */
						'linked' => __( 'On the %s. Each one plays with the room photos that go with it, in this order, then the next.', 'rapm' ),
						/* translators: %s: left or right */
						'timer'  => __( 'On the %s. Takes turns, moving to the next one every few room photos, in this order.', 'rapm' ),
					);
					echo esc_html( sprintf( $how[ $s['box'] ], $box_side ) . ' ' );
					/* translators: 1: width in pixels, 2: height in pixels */
					echo esc_html( sprintf( __( 'With none showing, the room photos go full width. Design it here, or use a finished %1$d by %2$d graphic.', 'rapm' ), (int) $pic['width'], (int) $pic['height'] ) );
					?>
				</p>
				<?php self::render_area_grid( $box_live, $box_ended, self::CARD_MIN['promo_box'], (int) $pic['width'] . ' / ' . (int) $pic['height'], $box_add, __( 'Add a promotion', 'rapm' ), $box_links ); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Split view settings for a Slider spot (1.43.0, from the mockup Phil
	 * approved on 2026-10-16). Saved with rapm_save_layout; the screen
	 * reloads after, since split changes the picture size the slider asks
	 * for and adds the promotion box spot below it.
	 */
	private static function render_layout_form( $placement, $heading ) {
		$s    = RAPM_Split::settings( $placement );
		$hd   = RAPM_Slots::get( 'hero_split_desktop' );
		$cols = array(
			'60-40' => __( '60 / 40 (rooms larger)', 'rapm' ),
			'50-50' => __( '50 / 50', 'rapm' ),
			'67-33' => __( '2 / 1 (promotion narrower)', 'rapm' ),
		);
		?>
		<form class="rapm-layout">
			<fieldset class="rapm-layout-row">
				<legend><?php esc_html_e( 'Layout', 'rapm' ); ?></legend>
				<label><input type="radio" name="split" value="0"<?php checked( ! $s['split'] ); ?> /> <?php esc_html_e( 'Full width: one rotating slider', 'rapm' ); ?></label>
				<label><input type="radio" name="split" value="1"<?php checked( $s['split'] ); ?> /> <?php esc_html_e( 'Split: rooms rotate, a promotion box stays beside them', 'rapm' ); ?></label>
			</fieldset>
			<div class="rapm-layout-split"<?php echo $s['split'] ? '' : ' hidden'; ?>>
				<label><?php esc_html_e( 'Widths', 'rapm' ); ?>
					<select name="cols">
						<?php foreach ( $cols as $value => $label ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>"<?php selected( $s['cols'], $value ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label><?php esc_html_e( 'Promotion on the', 'rapm' ); ?>
					<select name="side">
						<option value="right"<?php selected( $s['side'], 'right' ); ?>><?php esc_html_e( 'Right', 'rapm' ); ?></option>
						<option value="left"<?php selected( $s['side'], 'left' ); ?>><?php esc_html_e( 'Left', 'rapm' ); ?></option>
					</select>
				</label>
				<label><?php esc_html_e( 'Width', 'rapm' ); ?>
					<select name="width">
						<option value="full"<?php selected( $s['width'], 'full' ); ?>><?php esc_html_e( 'Full page, edge to edge', 'rapm' ); ?></option>
						<option value="content"<?php selected( $s['width'], 'content' ); ?>><?php esc_html_e( 'The site\'s page width', 'rapm' ); ?></option>
					</select>
				</label>
				<label><?php esc_html_e( 'On phones, show first', 'rapm' ); ?>
					<select name="phone">
						<option value="promo"<?php selected( $s['phone'], 'promo' ); ?>><?php esc_html_e( 'The promotion', 'rapm' ); ?></option>
						<option value="room"<?php selected( $s['phone'], 'room' ); ?>><?php esc_html_e( 'The room photos', 'rapm' ); ?></option>
					</select>
				</label>
				<?php // 1.52.0: how the promotion box changes. ?>
				<fieldset class="rapm-layout-box">
					<legend><?php esc_html_e( 'Promotion box', 'rapm' ); ?></legend>
					<label><input type="radio" name="box" value="linked"<?php checked( $s['box'], 'linked' ); ?> /> <strong><?php esc_html_e( 'Changes with its room photos', 'rapm' ); ?></strong> <span class="description"><?php esc_html_e( 'Each promotion plays with the room photos that go with it (set on each photo below), then the next one.', 'rapm' ); ?></span></label>
					<label><input type="radio" name="box" value="timer"<?php checked( $s['box'], 'timer' ); ?> /> <strong><?php esc_html_e( 'Rotates on its own', 'rapm' ); ?></strong> <span class="description"><?php esc_html_e( 'Moves to the next promotion every', 'rapm' ); ?>
						<select name="every" aria-label="<?php esc_attr_e( 'Room photos per promotion', 'rapm' ); ?>">
							<?php foreach ( array( 2, 3, 4 ) as $n ) : ?>
								<option value="<?php echo (int) $n; ?>"<?php selected( $s['every'], $n ); ?>><?php echo esc_html( sprintf( /* translators: %d: number of room photos */ __( '%d room photos', 'rapm' ), $n ) ); ?></option>
							<?php endforeach; ?>
						</select></span></label>
					<label><input type="radio" name="box" value="still"<?php checked( $s['box'], 'still' ); ?> /> <strong><?php esc_html_e( 'Stays put', 'rapm' ); ?></strong> <span class="description"><?php esc_html_e( 'Shows the first promotion that is on and within its dates.', 'rapm' ); ?></span></label>
				</fieldset>
				<p class="description">
					<?php
					/* translators: 1: width in pixels, 2: height in pixels */
					echo esc_html( sprintf( __( 'In split view the room photos on computers are shaped 4 by 3: %1$d by %2$d pixels. Pictures already in this slider are trimmed to that shape, so swap them for 4 by 3 photos to show the whole room. Phone pictures stay the same.', 'rapm' ), (int) $hd['width'], (int) $hd['height'] ) );
					?>
				</p>
			</div>
			<button type="submit" class="button button-small"><?php esc_html_e( 'Save layout', 'rapm' ); ?></button>
		</form>
		<?php
	}

	public static function ajax_save_layout() {
		check_ajax_referer( self::NONCE, 'nonce' );
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Not allowed.', 'rapm' ) ), 403 );
		}
		$placement = isset( $_POST['placement'] ) ? sanitize_title( wp_unslash( $_POST['placement'] ) ) : '';
		if ( ! $placement ) {
			wp_send_json_error( array( 'message' => __( 'Missing spot.', 'rapm' ) ), 400 );
		}
		$split = ! empty( $_POST['split'] ) && '1' === $_POST['split'];
		$cols  = isset( $_POST['cols'] ) ? sanitize_key( wp_unslash( $_POST['cols'] ) ) : '';
		$side  = isset( $_POST['side'] ) && 'left' === $_POST['side'] ? 'left' : 'right';
		$phone = isset( $_POST['phone'] ) && 'room' === $_POST['phone'] ? 'room' : 'promo';
		$width = isset( $_POST['width'] ) && 'content' === $_POST['width'] ? 'content' : 'full';
		// Defaults aren't stored, so a spot left at them follows any later change of default.
		$fields = array(
			'split'       => $split ? 1 : null,
			'split_cols'  => isset( RAPM_Split::COLS[ $cols ] ) && '60-40' !== $cols ? $cols : null,
			'split_side'  => 'left' === $side ? 'left' : null,
			'split_phone' => 'room' === $phone ? 'room' : null,
			'split_width' => 'content' === $width ? 'content' : null,
		);
		// 1.52.0: only the Layout form sends these; "Turn on split view" leaves them.
		if ( isset( $_POST['box'] ) ) {
			$box                   = sanitize_key( wp_unslash( $_POST['box'] ) );
			$every                 = isset( $_POST['every'] ) ? (int) $_POST['every'] : 3;
			$fields['split_box']   = in_array( $box, array( 'linked', 'timer' ), true ) ? $box : null;
			$fields['split_every'] = in_array( $every, array( 2, 4 ), true ) ? $every : null;
		}
		RAPM_Spots::update( 'hero', $placement, $fields );
		RAPM_Cache::queue_spot( 'hero|' . $placement );
		wp_send_json_success( array( 'reload' => true ) );
	}

	private static function render_card( $post, $d, $links = null ) {
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
		<?php
		// 1.52.0: a split slider's linked groups (colors and "Goes with").
		$goes  = $links && 'room' === $links['type'] ? RAPM_Split::goes_with( $id ) : 0;
		$goes  = $goes && isset( $links['groups'][ $goes ] ) ? $goes : 0;
		$group = $links && 'box' === $links['type'] && isset( $links['groups'][ $id ] ) ? $links['groups'][ $id ] : null;
		$color = $group ? $group['color'] : ( $goes ? $links['groups'][ $goes ]['color'] : ( $links && 'room' === $links['type'] ? '#8c8f94' : '' ) );
		?>
		<article class="rapm-card is-<?php echo esc_attr( $d['state'] ); ?><?php echo $color ? ' has-group' : ''; ?>"<?php echo $color ? ' style="' . esc_attr( '--rapm-group:' . $color ) . '"' : ''; ?> data-id="<?php echo esc_attr( $id ); ?>" data-title="<?php echo esc_attr( strtolower( $title . ( $tab ? ' ' . $tab : '' ) ) ); ?>"<?php echo $tab ? ' data-tab="' . esc_attr( $tab ) . '"' : ''; ?><?php echo $ended ? ' hidden' : ''; ?>>
			<a class="rapm-card-pic" href="<?php echo esc_url( $edit_url ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: promotion name */ __( 'Edit %s', 'rapm' ), $title ) ); ?>" draggable="false">
				<?php $design = 'promo_box' === get_post_meta( $id, '_rapm_kind', true ) ? RAPM_Split::card_preview( $id ) : ''; ?>
				<?php if ( 'reveal' === get_post_meta( $id, '_rapm_kind', true ) ) : // 1.53.0: the panel closed, and its photo. ?>
					<span class="rapm-card-reveal">
						<span class="rapm-card-reveal-word" style="<?php echo esc_attr( 'background:' . RAPM_Reveal::color_css( $id ) . ';color:' . ( get_post_meta( $id, '_rapm_text_color', true ) ?: '#ffffff' ) ); // phpcs:ignore ?>"><?php echo esc_html( get_post_meta( $id, '_rapm_headline', true ) ); ?></span>
						<?php if ( $image ) : ?><img src="<?php echo esc_url( $image ); ?>" alt="" loading="lazy" draggable="false" /><?php else : ?><span class="rapm-card-nopic"><?php esc_html_e( 'No photo yet', 'rapm' ); ?></span><?php endif; ?>
					</span>
				<?php elseif ( $design ) : // 1.44.1: a designed promotion box shows its design. ?>
					<?php echo $design; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in RAPM_Split::render_design(). ?>
				<?php elseif ( $image ) : ?>
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
				<?php if ( $links && 'room' === $links['type'] && ! $ended ) : ?>
					<?php if ( $goes && $links['groups'][ $goes ]['ended'] ) : ?>
						<p class="rapm-card-issue rapm-goes-ended"><?php esc_html_e( 'Not playing: the promotion it goes with has ended. Pick another one below.', 'rapm' ); ?></p>
					<?php endif; ?>
					<label class="rapm-goes"><span><?php esc_html_e( 'Goes with', 'rapm' ); ?></span>
						<select data-goes="<?php echo esc_attr( $id ); ?>">
							<option value="0" data-color="#8c8f94"<?php selected( $goes, 0 ); ?>><?php esc_html_e( 'Any promotion', 'rapm' ); ?></option>
							<?php foreach ( $links['groups'] as $gid => $g ) : ?>
								<option value="<?php echo (int) $gid; ?>" data-color="<?php echo esc_attr( $g['color'] ); ?>"<?php selected( $goes, $gid ); ?>><?php echo esc_html( $g['label'] . ( $g['ended'] ? ' ' . __( '(ended)', 'rapm' ) : '' ) ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
				<?php endif; ?>
				<?php if ( $group ) : ?>
					<p class="rapm-goes-count" data-group="<?php echo esc_attr( $id ); ?>">
						<?php echo esc_html( sprintf( /* translators: %d: number of room photos */ _n( '%d room photo goes with it', '%d room photos go with it', $group['count'], 'rapm' ), $group['count'] ) ); ?>
					</p>
					<?php if ( $ended && $group['count'] ) : ?>
						<p class="rapm-card-issue"><?php echo esc_html( sprintf( /* translators: %d: number of room photos */ _n( '%d room photo that goes with it stopped playing.', '%d room photos that go with it stopped playing.', $group['count'], 'rapm' ), $group['count'] ) ); ?></p>
					<?php endif; ?>
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
	/**
	 * 1.52.0: which promotion box promotion a room photo goes with (0 for
	 * "Any promotion"). Both must be in the same split slider.
	 */
	public static function ajax_goes_with() {
		check_ajax_referer( self::NONCE, 'nonce' );
		$id  = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$box = isset( $_POST['box'] ) ? absint( $_POST['box'] ) : 0;
		if ( ! $id || 'rapm_asset' !== get_post_type( $id ) || 'hero' !== get_post_meta( $id, '_rapm_kind', true ) || ! current_user_can( 'edit_post', $id ) ) {
			wp_send_json_error( array( 'message' => __( 'Not allowed.', 'rapm' ) ), 403 );
		}
		$placement = (string) get_post_meta( $id, '_rapm_placement', true );
		if ( $box && ( 'rapm_asset' !== get_post_type( $box ) || 'promo_box' !== get_post_meta( $box, '_rapm_kind', true ) || get_post_meta( $box, '_rapm_placement', true ) !== $placement ) ) {
			wp_send_json_error( array( 'message' => __( 'That promotion isn\'t in this slider.', 'rapm' ) ), 400 );
		}
		if ( $box ) {
			update_post_meta( $id, '_rapm_goes_with', $box );
		} else {
			delete_post_meta( $id, '_rapm_goes_with' );
		}
		RAPM_Cache::queue_spot( 'hero|' . $placement );
		wp_send_json_success();
	}

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
		if ( ! in_array( $kind, self::KIND_ORDER, true ) && 'reveal' !== $kind ) { // 1.53.0: made from the Design Center.
			wp_send_json_error( array( 'message' => __( 'Pick what kind of spot it is.', 'rapm' ) ), 400 );
		}
		if ( '' === trim( $name ) ) {
			wp_send_json_error( array( 'message' => __( 'Give the spot a name.', 'rapm' ) ), 400 );
		}
		$placement = RAPM_Spots::create( $kind, $name );
		if ( 'reveal' === $kind ) {
			wp_send_json_success( array( 'url' => add_query_arg( 'tab', 'reveal', self::design_url() ) . '#rapm-spot-reveal-' . $placement ) );
		}
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
