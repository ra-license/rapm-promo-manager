<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * "New promotion" starts with pictures (1.56.0, from the mockup Phil
 * approved on 2026-10-20, https://claude.ai/artifact/BnaNa1sN7k5vNofGG5c26J).
 * Recommendation 5 of the UX review: Canva's template-first start.
 *
 * 1. What do you want to make? One card per kind with a small drawing of
 *    where it sits on a page. Kinds this website already has a spot for
 *    come first ("On this website now"), the rest after.
 * 2. Where will it show? (the kind's spots, or a new one) and Start from:
 *    blank, a brand-kit starter or template (promotion boxes, the only
 *    kind with templates), or a copy of one made before (up to 6, newest
 *    first, ended ones too).
 * 3. Start opens the promotion form already set up.
 *
 * The form's old "Type of Promotion" dropdown is gone. A link that already
 * knows the kind (a spot's "+ Add" on the Promotions page) skips the
 * gallery. A saved promotion keeps its kind (Phil's pick): each kind needs
 * different picture sizes.
 *
 * A copy is made as soon as Start is clicked (RAPM_Admin_List::duplicate(),
 * so every setting comes along), as a hidden draft marked _rapm_from_gallery.
 * Its first save from the form puts it on the website like any new one.
 */
class RAPM_Gallery {

	/** The order of the cards within each group. */
	const ORDER = array( 'hero', 'promo_box', 'fold_banner', 'quick_links', 'reveal', 'coupon', 'marquee', 'look' );

	const COPIES = 6;

	/** True when "New promotion" should show the gallery instead of the form. */
	public static function wanted() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( ! empty( $_GET['edit'] ) ) {
			return false;
		}
		return ! isset( $_GET['kind'] ) || isset( $_GET['rapm_gallery'] );
		// phpcs:enable
	}

	/** The gallery, or with a kind, its "Where will it show? / Start from" step. */
	public static function url( $kind = '', $args = array() ) {
		$base = array(
			'post_type' => 'rapm_asset',
			'page'      => 'rapm-add-asset',
		);
		if ( $kind ) {
			$base['kind']         = $kind;
			$base['rapm_gallery'] = 1;
		}
		return add_query_arg( array_merge( $base, $args ), admin_url( 'edit.php' ) );
	}

	/** The promotion form for a new promotion of this kind. */
	private static function form_url( $kind, $placement, $args = array() ) {
		return add_query_arg(
			array_merge(
				array(
					'post_type' => 'rapm_asset',
					'page'      => 'rapm-add-asset',
					'kind'      => $kind,
					'placement' => $placement,
				),
				$args
			),
			admin_url( 'edit.php' )
		);
	}

	/** The picture sizes in plain words, from the slots (what the old dropdown showed). */
	public static function size_text( $kind_key ) {
		$kinds = RAPM_Slots::kinds();
		$slots = RAPM_Slots::all();
		$info  = $kinds[ $kind_key ];
		if ( ! $info['desktop'] ) {
			return __( 'No picture needed', 'rapm' );
		}
		$d = $slots[ $info['desktop'] ];
		if ( RAPM_Slots::is_flexible( $d ) ) {
			/* translators: %d: widest picture in pixels */
			return sprintf( __( 'One room photo, any shape, up to %d wide', 'rapm' ), $d['width'] );
		}
		if ( $info['desktop'] === $info['mobile'] ) {
			/* translators: 1: width, 2: height */
			$text = sprintf( __( '%1$d × %2$d, one for every screen', 'rapm' ), $d['width'], $d['height'] );
		} else {
			$p = $slots[ $info['mobile'] ];
			/* translators: 1: computer width, 2: computer height, 3: phone width, 4: phone height */
			$text = sprintf( __( 'Computer %1$d × %2$d, phone %3$d × %4$d', 'rapm' ), $d['width'], $d['height'], $p['width'], $p['height'] );
		}
		if ( 'hero' === $kind_key && isset( $slots['hero_split_desktop'] ) ) {
			/* translators: 1: width, 2: height */
			$text .= ' ' . sprintf( __( '(beside a promotion box: %1$d × %2$d)', 'rapm' ), $slots['hero_split_desktop']['width'], $slots['hero_split_desktop']['height'] );
		}
		if ( 'promo_box' === $kind_key ) {
			$text .= ' ' . __( '(or design it with words and colors)', 'rapm' );
		}
		return $text;
	}

	/**
	 * The spots a new promotion of this kind can go in, as placement =>
	 * name. A promotion box sits beside a Slider set to split view, so its
	 * spots are those sliders.
	 */
	public static function spots_for( $kind ) {
		$out = array();
		if ( 'promo_box' === $kind ) {
			foreach ( RAPM_Spots::known( 'hero' ) as $s ) {
				if ( RAPM_Split::is_split( $s['placement'] ) ) {
					$out[ $s['placement'] ] = RAPM_Spots::name( 'promo_box', $s['placement'] );
				}
			}
			return $out;
		}
		foreach ( RAPM_Spots::known( $kind ) as $s ) {
			$out[ $s['placement'] ] = $s['name'];
		}
		return $out;
	}

	/** Up to six promotions of this kind to copy, newest first, ended ones too. */
	private static function copies( $kind ) {
		return get_posts(
			array(
				'post_type'      => 'rapm_asset',
				'post_status'    => array( 'publish', 'draft', 'pending', 'future', 'private' ),
				'posts_per_page' => self::COPIES,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'no_found_rows'  => true,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
					'relation' => 'AND',
					array(
						'key'   => '_rapm_kind',
						'value' => $kind,
					),
					array(
						'key'     => '_rapm_from_gallery',
						'compare' => 'NOT EXISTS',
					),
				),
			)
		);
	}

	/**
	 * A small drawing of a page with this kind on it (the mockup's). Light
	 * gray is the rest of the page; blue is the promotion.
	 */
	public static function picture( $kind, $label = true ) {
		$parts = array(
			'hero'        => '<rect x="12" y="30" width="216" height="62" fill="#2271b1"/><rect x="28" y="46" width="80" height="9" rx="2" fill="#fff"/><rect x="28" y="60" width="56" height="5" rx="2" fill="#c5d9ed"/><rect x="28" y="72" width="34" height="10" rx="2" fill="#fff"/><circle cx="112" cy="86" r="2" fill="#fff"/><circle cx="120" cy="86" r="2" fill="#9ec2e6"/><circle cx="128" cy="86" r="2" fill="#9ec2e6"/><rect x="24" y="102" width="56" height="30" rx="2" fill="#f0f0f1"/><rect x="92" y="102" width="56" height="30" rx="2" fill="#f0f0f1"/><rect x="160" y="102" width="56" height="30" rx="2" fill="#f0f0f1"/>',
			'promo_box'   => '<rect x="12" y="30" width="136" height="76" fill="#c5d9ed"/><path d="M30 92 L60 66 L84 84 L104 72 L132 92 Z" fill="#9ec2e6"/><rect x="148" y="30" width="80" height="76" fill="#2271b1"/><rect x="160" y="50" width="56" height="9" rx="2" fill="#fff"/><rect x="166" y="64" width="44" height="5" rx="2" fill="#c5d9ed"/><rect x="171" y="78" width="34" height="10" rx="2" fill="#fff"/><rect x="24" y="114" width="190" height="5" rx="2" fill="#f0f0f1"/><rect x="24" y="124" width="140" height="5" rx="2" fill="#f0f0f1"/>',
			'fold_banner' => '<rect x="24" y="34" width="56" height="34" rx="2" fill="#f0f0f1"/><rect x="92" y="34" width="56" height="34" rx="2" fill="#f0f0f1"/><rect x="160" y="34" width="56" height="34" rx="2" fill="#f0f0f1"/><rect x="12" y="78" width="216" height="30" fill="#2271b1"/><rect x="28" y="88" width="90" height="8" rx="2" fill="#fff"/><rect x="176" y="87" width="38" height="11" rx="2" fill="#fff"/><rect x="24" y="118" width="190" height="5" rx="2" fill="#f0f0f1"/><rect x="24" y="128" width="150" height="5" rx="2" fill="#f0f0f1"/>',
			'quick_links' => '<rect x="24" y="34" width="100" height="6" rx="2" fill="#a7aaad"/><rect x="22" y="50" width="44" height="24" rx="2" fill="#2271b1"/><rect x="74" y="50" width="44" height="24" rx="2" fill="#2271b1"/><rect x="126" y="50" width="44" height="24" rx="2" fill="#2271b1"/><rect x="178" y="50" width="44" height="24" rx="2" fill="#2271b1"/><rect x="30" y="79" width="28" height="4" rx="2" fill="#2271b1"/><rect x="82" y="79" width="28" height="4" rx="2" fill="#2271b1"/><rect x="134" y="79" width="28" height="4" rx="2" fill="#2271b1"/><rect x="186" y="79" width="28" height="4" rx="2" fill="#2271b1"/><rect x="24" y="94" width="40" height="36" rx="2" fill="#f0f0f1"/><rect x="72" y="94" width="40" height="36" rx="2" fill="#f0f0f1"/><rect x="120" y="94" width="40" height="36" rx="2" fill="#f0f0f1"/><rect x="168" y="94" width="40" height="36" rx="2" fill="#f0f0f1"/>',
			'reveal'      => '<rect x="12" y="30" width="72" height="80" fill="#135e96"/><rect x="84" y="30" width="72" height="80" fill="#c5d9ed"/><path d="M90 104 L112 80 L128 94 L140 86 L156 104 Z" fill="#9ec2e6"/><rect x="156" y="30" width="72" height="80" fill="#2271b1"/><rect x="26" y="64" width="44" height="11" rx="2" fill="#fff"/><rect x="92" y="42" width="40" height="10" rx="2" fill="#fff"/><rect x="170" y="64" width="44" height="11" rx="2" fill="#fff"/><rect x="24" y="118" width="190" height="5" rx="2" fill="#f0f0f1"/><rect x="24" y="128" width="140" height="5" rx="2" fill="#f0f0f1"/>',
			'coupon'      => '<rect x="24" y="34" width="90" height="6" rx="2" fill="#a7aaad"/><rect x="22" y="48" width="40" height="52" rx="3" fill="#fff" stroke="#2271b1" stroke-width="2" stroke-dasharray="4 3"/><rect x="72" y="48" width="40" height="52" rx="3" fill="#fff" stroke="#2271b1" stroke-width="2" stroke-dasharray="4 3"/><rect x="122" y="48" width="40" height="52" rx="3" fill="#fff" stroke="#2271b1" stroke-width="2" stroke-dasharray="4 3"/><rect x="172" y="48" width="40" height="52" rx="3" fill="#fff" stroke="#2271b1" stroke-width="2" stroke-dasharray="4 3"/><rect x="30" y="60" width="24" height="9" rx="2" fill="#2271b1"/><rect x="80" y="60" width="24" height="9" rx="2" fill="#2271b1"/><rect x="130" y="60" width="24" height="9" rx="2" fill="#2271b1"/><rect x="180" y="60" width="24" height="9" rx="2" fill="#2271b1"/><rect x="30" y="76" width="24" height="4" rx="2" fill="#9ec2e6"/><rect x="80" y="76" width="24" height="4" rx="2" fill="#9ec2e6"/><rect x="130" y="76" width="24" height="4" rx="2" fill="#9ec2e6"/><rect x="180" y="76" width="24" height="4" rx="2" fill="#9ec2e6"/><path d="M214 70 L220 74 L214 78" fill="none" stroke="#50575e" stroke-width="2"/><rect x="24" y="112" width="190" height="5" rx="2" fill="#f0f0f1"/><rect x="24" y="122" width="150" height="5" rx="2" fill="#f0f0f1"/>',
			'marquee'     => '<rect x="24" y="34" width="190" height="5" rx="2" fill="#f0f0f1"/><rect x="24" y="44" width="150" height="5" rx="2" fill="#f0f0f1"/><rect x="22" y="60" width="34" height="34" rx="2" fill="#2271b1"/><rect x="62" y="60" width="34" height="34" rx="2" fill="#135e96"/><rect x="102" y="60" width="34" height="34" rx="2" fill="#2271b1"/><rect x="142" y="60" width="34" height="34" rx="2" fill="#135e96"/><rect x="182" y="60" width="34" height="34" rx="2" fill="#2271b1"/><rect x="24" y="106" width="56" height="26" rx="2" fill="#f0f0f1"/><rect x="92" y="106" width="56" height="26" rx="2" fill="#f0f0f1"/><rect x="160" y="106" width="56" height="26" rx="2" fill="#f0f0f1"/>',
			'look'        => '<rect x="24" y="32" width="192" height="72" fill="#c5d9ed"/><path d="M40 100 L80 66 L110 88 L140 72 L200 100 Z" fill="#9ec2e6"/><circle cx="78" cy="74" r="6" fill="#fff" stroke="#2271b1" stroke-width="2"/><circle cx="150" cy="82" r="6" fill="#fff" stroke="#2271b1" stroke-width="2"/><rect x="24" y="108" width="62" height="14" fill="#2271b1"/><rect x="89" y="108" width="62" height="14" fill="#f0f0f1"/><rect x="154" y="108" width="62" height="14" fill="#f0f0f1"/><rect x="36" y="113" width="38" height="4" rx="2" fill="#fff"/><rect x="101" y="113" width="38" height="4" rx="2" fill="#a7aaad"/><rect x="166" y="113" width="38" height="4" rx="2" fill="#a7aaad"/>',
		);
		$alts  = array(
			'hero'        => __( 'A slider across the top of a page', 'rapm' ),
			'promo_box'   => __( 'A room photo with a promotion box beside it', 'rapm' ),
			'fold_banner' => __( 'A wide banner strip in the middle of a page', 'rapm' ),
			'quick_links' => __( 'A row of wide pictures with names under them', 'rapm' ),
			'reveal'      => __( 'Three color panels, one open to a room photo', 'rapm' ),
			'coupon'      => __( 'A row of coupon cards', 'rapm' ),
			'marquee'     => __( 'A row of square tiles', 'rapm' ),
			'look'        => __( 'A room photo with dots and tabs under it', 'rapm' ),
		);
		$kind  = isset( $parts[ $kind ] ) ? $kind : 'hero';
		$a11y  = $label ? 'role="img" aria-label="' . esc_attr( $alts[ $kind ] ) . '"' : 'aria-hidden="true" focusable="false"';
		return '<svg class="rapm-gal-svg" viewBox="0 0 240 150" style="display:block;width:100%;height:auto" ' . $a11y . '><rect x="12" y="10" width="216" height="130" rx="4" fill="#fff" stroke="#dcdcde"/><rect x="20" y="17" width="28" height="6" rx="2" fill="#a7aaad"/><rect x="150" y="18" width="16" height="4" rx="2" fill="#dcdcde"/><rect x="170" y="18" width="16" height="4" rx="2" fill="#dcdcde"/><rect x="190" y="18" width="16" height="4" rx="2" fill="#dcdcde"/>' . $parts[ $kind ] . '</svg>';
	}

	/** Where a kind is made, for the start step. */
	private static function made_in( $kind ) {
		return in_array( $kind, array( 'promo_box', 'reveal' ), true )
			? __( 'The Design Center opens it after you save.', 'rapm' )
			: __( 'The promotion form, in four short steps.', 'rapm' );
	}

	/** "Home page slider, Sofas page slider +2 more". */
	private static function spots_text( $names ) {
		$names = array_values( $names );
		$shown = array_slice( $names, 0, 2 );
		$text  = implode( ', ', $shown );
		if ( count( $names ) > 2 ) {
			/* translators: %d: how many more spots */
			$text .= ' ' . sprintf( __( '+%d more', 'rapm' ), count( $names ) - 2 );
		}
		return $text;
	}

	public static function render() {
		$kinds = RAPM_Slots::kinds();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$kind  = isset( $_GET['kind'] ) ? sanitize_key( wp_unslash( $_GET['kind'] ) ) : '';
		$kind  = isset( $kinds[ $kind ] ) ? $kind : '';
		echo '<div class="wrap rapm-gal">';
		if ( $kind ) {
			self::render_start( $kind );
		} else {
			self::render_cards();
		}
		echo '</div>';
	}

	private static function render_cards() {
		$kinds = RAPM_Slots::kinds();
		$used  = array();
		$more  = array();
		foreach ( self::ORDER as $key ) {
			if ( ! isset( $kinds[ $key ] ) ) {
				continue;
			}
			$spots = self::spots_for( $key );
			if ( $spots ) {
				$used[ $key ] = $spots;
			} else {
				$more[ $key ] = array();
			}
		}
		?>
		<p class="rapm-gal-back"><a href="<?php echo esc_url( RAPM_Promotions_Screen::url() ); ?>">&larr; <?php esc_html_e( 'All promotions', 'rapm' ); ?></a></p>
		<h1><?php esc_html_e( 'New promotion', 'rapm' ); ?></h1>
		<p class="rapm-gal-lead"><?php esc_html_e( 'What do you want to make? Pick one to see how to start.', 'rapm' ); ?></p>
		<?php
		$groups = array(
			array( __( 'On this website now', 'rapm' ), $used ),
			array( $used ? __( 'More kinds you can add', 'rapm' ) : __( 'Pick a kind', 'rapm' ), $more ),
		);
		foreach ( $groups as $group ) {
			if ( ! $group[1] ) {
				continue;
			}
			?>
			<h2 class="rapm-gal-group"><?php echo esc_html( $group[0] ); ?></h2>
			<ul class="rapm-gal-grid">
				<?php foreach ( $group[1] as $key => $spots ) : ?>
					<li>
						<a class="rapm-gal-card" href="<?php echo esc_url( self::url( $key ) ); ?>">
							<span class="rapm-gal-pic"><?php echo self::picture( $key, false ); // phpcs:ignore WordPress.Security.EscapeOutput -- fixed markup. ?></span>
							<span class="rapm-gal-body">
								<span class="rapm-gal-name"><?php echo esc_html( $kinds[ $key ]['label'] ); ?></span>
								<span class="rapm-gal-line"><?php echo esc_html( $kinds[ $key ]['help'] ); ?></span>
								<?php if ( $spots ) : ?>
									<?php /* translators: %s: spot names */ ?>
									<span class="rapm-gal-on is-used"><?php echo esc_html( sprintf( __( 'On: %s', 'rapm' ), self::spots_text( $spots ) ) ); ?></span>
								<?php else : ?>
									<span class="rapm-gal-on"><?php esc_html_e( 'Not on a page yet', 'rapm' ); ?></span>
								<?php endif; ?>
							</span>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
			<?php
		}
	}

	private static function render_start( $kind ) {
		$kinds  = RAPM_Slots::kinds();
		$info   = $kinds[ $kind ];
		$spots  = self::spots_for( $kind );
		$is_box = 'promo_box' === $kind;
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$error  = isset( $_GET['rapm_error'] ) ? sanitize_text_field( wp_unslash( $_GET['rapm_error'] ) ) : '';
		$pick   = isset( $_GET['placement'] ) ? sanitize_title( wp_unslash( $_GET['placement'] ) ) : '';
		// phpcs:enable
		if ( ! isset( $spots[ $pick ] ) ) {
			$pick = $spots ? (string) key( $spots ) : 'new';
		}
		$tpls   = array();
		if ( $is_box ) {
			$browser = RAPM_Brand_Kit::for_browser();
			$tpls    = $browser['templates'];
		}
		$copies = self::copies( $kind );
		$can_start = ! ( $is_box && ! $spots );
		?>
		<p class="rapm-gal-back"><a href="<?php echo esc_url( self::url() ); ?>">&larr; <?php esc_html_e( 'All kinds', 'rapm' ); ?></a></p>
		<?php /* translators: %s: kind, like "Slider" */ ?>
		<h1><?php echo esc_html( sprintf( __( 'New %s', 'rapm' ), strtolower( $info['label'] ) ) ); ?></h1>
		<?php if ( $error ) : ?>
			<div class="notice notice-error"><p><?php echo esc_html( $error ); ?></p></div>
		<?php endif; ?>
		<div class="rapm-gal-start">
			<aside class="rapm-gal-about">
				<span class="rapm-gal-pic"><?php echo self::picture( $kind ); // phpcs:ignore WordPress.Security.EscapeOutput -- fixed markup. ?></span>
				<div class="rapm-gal-body">
					<span class="rapm-gal-name"><?php echo esc_html( $info['label'] ); ?></span>
					<span class="rapm-gal-line"><?php echo esc_html( $info['help'] ); ?></span>
					<span class="rapm-gal-fact"><strong><?php esc_html_e( 'Picture size:', 'rapm' ); ?></strong> <?php echo esc_html( self::size_text( $kind ) ); ?></span>
					<span class="rapm-gal-fact"><strong><?php esc_html_e( 'Made in:', 'rapm' ); ?></strong> <?php echo esc_html( self::made_in( $kind ) ); ?></span>
				</div>
			</aside>
			<form class="rapm-gal-steps" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="rapm_gallery_start" />
				<input type="hidden" name="kind" value="<?php echo esc_attr( $kind ); ?>" />
				<?php wp_nonce_field( 'rapm_gallery_start', 'rapm_nonce' ); ?>

				<fieldset class="rapm-gal-sec">
					<legend><?php esc_html_e( '1. Where will it show?', 'rapm' ); ?></legend>
					<?php if ( $is_box && ! $spots ) : ?>
						<p class="rapm-gal-help"><?php esc_html_e( 'A promotion box sits beside a slider that is split in two. No slider is split yet.', 'rapm' ); ?></p>
						<p><a class="button" href="<?php echo esc_url( RAPM_Promotions_Screen::design_url() ); ?>"><?php esc_html_e( 'Split a slider in the Design Center', 'rapm' ); ?></a></p>
					<?php else : ?>
						<p class="rapm-gal-help"><?php esc_html_e( 'A spot is a place on the website. You can change this later.', 'rapm' ); ?></p>
						<div class="rapm-gal-list">
							<?php foreach ( $spots as $placement => $name ) : ?>
								<?php
								$on    = RAPM_Spot_Usage::for_spot( 'promo_box' === $kind ? 'hero' : $kind, $placement );
								$pages = count( $on );
								?>
								<label class="rapm-gal-opt is-row">
									<input type="radio" name="spot" value="<?php echo esc_attr( $placement ); ?>" <?php checked( $pick, $placement ); ?> />
									<span>
										<span class="rapm-gal-optname"><?php echo esc_html( $name ); ?></span>
										<?php /* translators: %d: number of pages */ ?>
										<span class="rapm-gal-optsub"><?php echo esc_html( $pages ? sprintf( _n( 'On %d page', 'On %d pages', $pages, 'rapm' ), $pages ) : __( 'Not on a page yet', 'rapm' ) ); ?></span>
									</span>
								</label>
							<?php endforeach; ?>
							<?php if ( ! $is_box ) : ?>
								<label class="rapm-gal-opt is-row">
									<input type="radio" name="spot" value="new" <?php checked( $pick, 'new' ); ?> />
									<span>
										<span class="rapm-gal-optname"><?php esc_html_e( 'A new spot', 'rapm' ); ?></span>
										<span class="rapm-gal-optsub"><?php esc_html_e( 'You get the code to put on a page. Nothing shows until it is there.', 'rapm' ); ?></span>
										<span class="rapm-gal-newname">
											<span id="rapm-gal-spotname-l"><?php esc_html_e( 'Name it for its job:', 'rapm' ); ?></span>
											<input type="text" id="rapm-gal-spotname" aria-labelledby="rapm-gal-spotname-l" name="spot_name" maxlength="60" placeholder="<?php /* translators: %s: kind, like "slider" */ echo esc_attr( sprintf( __( 'e.g. Living Room %s', 'rapm' ), strtolower( $info['label'] ) ) ); ?>" />
										</span>
									</span>
								</label>
							<?php endif; ?>
						</div>
					<?php endif; ?>
				</fieldset>

				<?php if ( $can_start ) : ?>
				<fieldset class="rapm-gal-sec">
					<legend><?php esc_html_e( '2. Start from', 'rapm' ); ?></legend>
					<p class="rapm-gal-help">
						<?php
						if ( $is_box ) {
							esc_html_e( 'Start blank, from a starter or template, or copy one you made before.', 'rapm' );
						} elseif ( $copies ) {
							esc_html_e( 'Start blank, or copy one you made before and change it.', 'rapm' );
						} else {
							esc_html_e( 'Start blank. Next time you can copy this one.', 'rapm' );
						}
						?>
					</p>
					<div class="rapm-gal-tiles">
						<label class="rapm-gal-opt is-tile">
							<input type="radio" name="start" value="blank" checked />
							<span class="rapm-gal-tilepic is-blank" aria-hidden="true">+</span>
							<span class="rapm-gal-optname"><?php esc_html_e( 'Blank', 'rapm' ); ?></span>
							<span class="rapm-gal-optsub"><?php esc_html_e( 'Start from nothing', 'rapm' ); ?></span>
						</label>
						<?php foreach ( $tpls as $tpl ) : ?>
							<label class="rapm-gal-opt is-tile">
								<input type="radio" name="start" value="<?php echo esc_attr( 'tpl:' . $tpl['id'] ); ?>" />
								<span class="rapm-gal-tilepic"><?php echo self::template_preview( $tpl ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in board_html(). ?></span>
								<span class="rapm-gal-optname"><?php echo esc_html( $tpl['name'] ); ?></span>
								<span class="rapm-gal-optsub"><?php echo esc_html( ! empty( $tpl['starter'] ) ? __( 'R&A starter', 'rapm' ) : __( 'Your template', 'rapm' ) ); ?></span>
							</label>
						<?php endforeach; ?>
						<?php foreach ( $copies as $post ) : ?>
							<?php $d = RAPM_Promotions_Screen::describe( $post ); ?>
							<label class="rapm-gal-opt is-tile">
								<input type="radio" name="start" value="<?php echo esc_attr( 'copy:' . $post->ID ); ?>" />
								<span class="rapm-gal-tilepic"><?php echo self::copy_preview( $post->ID, $kind ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside. ?></span>
								<?php /* translators: %s: the promotion's name */ ?>
								<span class="rapm-gal-optname"><?php echo esc_html( sprintf( __( 'Copy: %s', 'rapm' ), $post->post_title ) ); ?></span>
								<span class="rapm-gal-optsub"><?php echo esc_html( 'showing' === $d['state'] ? $d['status'] : $d['when'] ); ?></span>
							</label>
						<?php endforeach; ?>
					</div>
					<?php if ( $is_box ) : ?>
						<p class="rapm-gal-help is-after"><?php esc_html_e( 'Starters use your website\'s own colors. Save your own in the Design Center, under Brand kit.', 'rapm' ); ?></p>
					<?php endif; ?>
				</fieldset>

				<p class="rapm-gal-go">
					<button type="submit" class="button button-primary button-hero"><?php esc_html_e( 'Start', 'rapm' ); ?></button>
					<span class="rapm-gal-help"><?php esc_html_e( 'Nothing goes on the website until you save it.', 'rapm' ); ?></span>
				</p>
				<?php endif; ?>
			</form>
		</div>
		<script>
			( function () {
				// Typing a new spot's name picks "A new spot".
				var name = document.getElementById( 'rapm-gal-spotname' );
				if ( ! name ) { return; }
				name.addEventListener( 'input', function () {
					var r = document.querySelector( 'input[name="spot"][value="new"]' );
					if ( r ) { r.checked = true; }
				} );
			}() );
		</script>
		<?php
	}

	/** A template as a small square, drawn with the website's own promotion box styles. */
	private static function template_preview( $tpl ) {
		$canvas = RAPM_Split::sanitize_canvas( isset( $tpl['canvas'] ) ? $tpl['canvas'] : array() );
		$bg     = $canvas['bg'] ? $canvas['bg'] : '#f3ead8';
		$texts  = isset( $tpl['texts'] ) && is_array( $tpl['texts'] ) ? $tpl['texts'] : array();
		return '<span class="rapm-pb-item is-design rapm-cv-mini" style="' . esc_attr( 'background:' . $bg ) . '">' . RAPM_Split::board_html( $canvas, $texts ) . '</span>';
	}

	/** A promotion to copy: its designed box, its picture, or the kind's drawing. */
	private static function copy_preview( $asset_id, $kind ) {
		if ( 'promo_box' === $kind ) {
			$design = RAPM_Split::card_preview( $asset_id );
			if ( $design ) {
				return str_replace( 'rapm-card-design', 'rapm-card-design rapm-cv-mini', $design );
			}
		}
		$image_id = (int) get_post_meta( $asset_id, '_rapm_image_desktop_id', true );
		$src      = $image_id ? wp_get_attachment_image_url( $image_id, 'medium' ) : '';
		if ( $src ) {
			return '<img src="' . esc_url( $src ) . '" alt="" loading="lazy" />';
		}
		return self::picture( $kind, false );
	}

	/** Start: makes the spot or the copy if asked, then opens the form. */
	public static function handle_start() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'rapm' ) );
		}
		check_admin_referer( 'rapm_gallery_start', 'rapm_nonce' );
		$kinds = RAPM_Slots::kinds();
		$kind  = isset( $_POST['kind'] ) ? sanitize_key( wp_unslash( $_POST['kind'] ) ) : '';
		if ( ! isset( $kinds[ $kind ] ) ) {
			wp_safe_redirect( self::url() );
			exit;
		}
		$spot  = isset( $_POST['spot'] ) ? sanitize_title( wp_unslash( $_POST['spot'] ) ) : '';
		$start = isset( $_POST['start'] ) ? sanitize_text_field( wp_unslash( $_POST['start'] ) ) : 'blank';
		$spots = self::spots_for( $kind );
		$fail  = function ( $message ) use ( $kind, $spot ) {
			wp_safe_redirect( self::url( $kind, array( 'placement' => $spot, 'rapm_error' => rawurlencode( $message ) ) ) );
			exit;
		};

		if ( 'new' === $spot && 'promo_box' !== $kind ) {
			$name = isset( $_POST['spot_name'] ) ? mb_substr( sanitize_text_field( wp_unslash( $_POST['spot_name'] ) ), 0, 60 ) : '';
			if ( '' === trim( $name ) ) {
				$fail( __( 'Give the new spot a name, like "Living Room slider".', 'rapm' ) );
			}
			$placement = RAPM_Spots::create( $kind, $name );
		} elseif ( isset( $spots[ $spot ] ) ) {
			$placement = $spot;
		} else {
			$fail( __( 'Pick where it will show.', 'rapm' ) );
		}

		if ( 0 === strpos( $start, 'copy:' ) ) {
			$source = absint( substr( $start, 5 ) );
			if ( ! $source || 'rapm_asset' !== get_post_type( $source ) || get_post_meta( $source, '_rapm_kind', true ) !== $kind || ! current_user_can( 'edit_post', $source ) ) {
				$fail( __( 'That promotion can\'t be copied. Pick another one.', 'rapm' ) );
			}
			$new_id = RAPM_Admin_List::duplicate( $source );
			if ( ! $new_id ) {
				$fail( __( 'The copy could not be made. Please try again.', 'rapm' ) );
			}
			$moved = get_post_meta( $source, '_rapm_placement', true ) !== $placement;
			update_post_meta( $new_id, '_rapm_placement', $placement );
			if ( $moved ) {
				delete_post_meta( $new_id, '_rapm_goes_with' ); // 1.52.0: it went with a box in the old spot.
			}
			// Old dates that already ended would hide it at once; it gets new ones.
			$ended = 'ended' === RAPM_Promotions_Screen::describe( get_post( $source ) )['state'];
			if ( $ended ) {
				delete_post_meta( $new_id, '_rapm_starts_at' );
				delete_post_meta( $new_id, '_rapm_ends_at' );
			}
			update_post_meta( $new_id, '_rapm_from_gallery', $ended ? 'ended' : '1' );
			wp_safe_redirect( admin_url( 'edit.php?post_type=rapm_asset&page=rapm-add-asset&edit=' . $new_id . '&rapm_copied=' . $source ) );
			exit;
		}

		$args = array();
		if ( 'promo_box' === $kind && 0 === strpos( $start, 'tpl:' ) && preg_match( '/^[a-z0-9-]{6,30}$/', substr( $start, 4 ) ) ) {
			$args['rapm_tpl'] = substr( $start, 4 );
		}
		wp_safe_redirect( self::form_url( $kind, $placement, $args ) );
		exit;
	}

	/**
	 * The promotion form's top line, in place of the old dropdown: the
	 * kind's drawing and name, with "Change" back to the gallery on a new
	 * promotion. A saved one keeps its kind.
	 */
	public static function kind_line( $kind, $placement, $is_edit ) {
		$kinds = RAPM_Slots::kinds();
		?>
		<div class="rapm-kind-line">
			<span class="rapm-kind-pic"><?php echo self::picture( $kind, false ); // phpcs:ignore WordPress.Security.EscapeOutput -- fixed markup. ?></span>
			<span class="rapm-kind-text">
				<strong><?php echo esc_html( $kinds[ $kind ]['label'] ); ?></strong>
				<span class="rapm-kind-size"><?php echo esc_html( self::size_text( $kind ) ); ?></span>
			</span>
			<?php if ( $is_edit ) : ?>
				<?php RAPM_Tip::hint( __( 'A saved promotion keeps its kind.', 'rapm' ), __( 'Each kind needs different picture sizes. To make another kind, start a New promotion.', 'rapm' ) ); ?>
			<?php else : ?>
				<a class="rapm-kind-change" href="<?php echo esc_url( self::url( $kind, array( 'placement' => $placement ) ) ); ?>"><?php esc_html_e( 'Change', 'rapm' ); ?></a>
			<?php endif; ?>
		</div>
		<?php
	}
}
