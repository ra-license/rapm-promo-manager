<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Split view (1.43.0, from the mockup Phil approved on 2026-10-16, for
 * Frizzell after the call with Ashley): a Slider's room photos rotate on
 * one side while a promotion box stays put on the other, like Frizzell's
 * current home page.
 *
 * Phil's picks:
 * - Widths 60/40 by default; 50/50 and 2/1 also available.
 * - On phones the promotion shows first (a spot can switch to room first).
 * - The promotion can be words laid over a picture, or a finished graphic.
 * - Room photos are 4:3 in split view (hero_split_desktop), so the room is
 *   larger; the shop bar stays under the room.
 *
 * The layout is a setting on the Slider's spot ('rapm_spots' option:
 * split, split_cols, split_side, split_phone). The promotions in the box
 * are their own kind, promo_box, with the same placement as the Slider,
 * so they get the usual dates, on/off switch and order on the Promotions
 * screen. The box shows the first promotion that's live right now, decided
 * in the visitor's browser like every other schedule (rapm-schedule.js),
 * so a cached page still changes on time. With nothing live, the box hides
 * and the Slider goes back to full width.
 */
class RAPM_Split {

	/** Saved value => columns (room first). */
	const COLS = array(
		'60-40' => '3fr 2fr',
		'50-50' => '1fr 1fr',
		'67-33' => '2fr 1fr',
	);

	/** A Slider spot's split settings, with defaults filled in. */
	public static function settings( $placement ) {
		$spot = RAPM_Spots::get( 'hero', $placement );
		$cols = isset( $spot['split_cols'] ) && isset( self::COLS[ $spot['split_cols'] ] ) ? $spot['split_cols'] : '60-40';
		return array(
			'split' => ! empty( $spot['split'] ),
			'cols'  => $cols,
			'side'  => isset( $spot['split_side'] ) && 'left' === $spot['split_side'] ? 'left' : 'right',
			'phone' => isset( $spot['split_phone'] ) && 'room' === $spot['split_phone'] ? 'room' : 'promo',
			// 1.43.1 (Phil, after the staging test): the room and the box
			// fill the page's width by default, not the theme's narrower
			// page width. 'content' keeps it inside, for a spot placed in a
			// column.
			'width' => isset( $spot['split_width'] ) && 'content' === $spot['split_width'] ? 'content' : 'full',
		);
	}

	public static function is_split( $placement ) {
		$s = self::settings( sanitize_title( $placement ) );
		return $s['split'];
	}

	/** Called at the end of RAPM_Hero_Carousel::render() for a Slider. */
	public static function wrap( $placement, $carousel_html ) {
		$s = self::settings( $placement );
		if ( ! $s['split'] || '' === $carousel_html ) {
			return $carousel_html;
		}
		$box = self::render_box( $placement );
		if ( '' === $box ) {
			return $carousel_html;
		}
		$cols = self::COLS[ $s['cols'] ];
		if ( 'left' === $s['side'] ) {
			$cols = implode( ' ', array_reverse( explode( ' ', $cols ) ) );
		}
		$slot    = RAPM_Slots::get( 'promo_box_image' );
		$id      = 'rapm-split-' . wp_unique_id();
		$classes = 'rapm-split rapm-split-promo-' . $s['side'] . ( 'room' === $s['phone'] ? ' rapm-split-room-first' : '' ) . ( 'full' === $s['width'] ? ' is-full' : '' );
		ob_start();
		?>
		<div class="<?php echo esc_attr( $classes ); ?>" id="<?php echo esc_attr( $id ); ?>" style="--rapm-split-cols:<?php echo esc_attr( $cols ); ?>;--rapm-pb-ratio:<?php echo esc_attr( (int) $slot['width'] . ' / ' . (int) $slot['height'] ); ?>;">
			<div class="rapm-split-main"><?php echo $carousel_html; // phpcs:ignore WordPress.Security.EscapeOutput -- built and escaped by RAPM_Hero_Carousel. ?></div>
			<div class="rapm-split-side"><?php echo $box; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in render_box(). ?></div>
		</div>
		<?php self::print_script( $id ); ?>
		<?php
		return ob_get_clean();
	}

	/** [rapm_promo_box placement="..."]: the box on its own, anywhere. */
	public static function shortcode( $atts ) {
		$atts      = shortcode_atts( array( 'placement' => 'default' ), $atts, 'rapm_promo_box' );
		$placement = sanitize_title( $atts['placement'] );
		$box       = self::render_box( $placement );
		if ( '' === $box ) {
			return '';
		}
		$slot = RAPM_Slots::get( 'promo_box_image' );
		$id   = 'rapm-split-' . wp_unique_id();
		ob_start();
		echo RAPM_Assets::need( 'hero' ); // phpcs:ignore WordPress.Security.EscapeOutput -- core-generated link and script tags.
		?>
		<div class="rapm-pb-solo" id="<?php echo esc_attr( $id ); ?>" style="aspect-ratio:<?php echo esc_attr( (int) $slot['width'] . ' / ' . (int) $slot['height'] ); ?>;"><?php echo $box; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in render_box(). ?></div>
		<?php
		self::print_script( $id );
		return ob_get_clean();
	}

	/** The published promotion box promotions for a placement, in their set order. */
	private static function posts( $placement ) {
		$query = new WP_Query(
			array(
				'post_type'      => 'rapm_asset',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'menu_order date',
				'order'          => 'ASC',
				'no_found_rows'  => true,
				'meta_query'     => array(
					'relation' => 'AND',
					array(
						'key'   => '_rapm_placement',
						'value' => $placement,
					),
					array(
						'key'   => '_rapm_kind',
						'value' => 'promo_box',
					),
				),
			)
		);
		return $query->posts;
	}

	/**
	 * Every promotion for the box, each hidden until the browser decides
	 * which one is live. '' when there are none with a picture.
	 */
	private static function render_box( $placement ) {
		$items = '';
		$eager = true;
		foreach ( self::posts( $placement ) as $post ) {
			$items .= self::render_item( $post->ID, $eager );
		}
		if ( '' === $items ) {
			return '';
		}
		return '<div class="rapm-pb" data-rapm-pb>' . $items . '</div>';
	}

	private static function render_item( $asset_id, &$eager ) {
		$image_id = (int) get_post_meta( $asset_id, '_rapm_image_desktop_id', true );
		$src      = $image_id ? wp_get_attachment_image_url( $image_id, 'full' ) : '';
		if ( ! $src ) {
			return '';
		}
		$headline   = get_post_meta( $asset_id, '_rapm_headline', true );
		$subhead    = get_post_meta( $asset_id, '_rapm_subhead', true );
		$cta_text   = get_post_meta( $asset_id, '_rapm_cta_text', true );
		$alt        = get_post_meta( $asset_id, '_rapm_alt_text', true );
		$text_align = get_post_meta( $asset_id, '_rapm_text_align', true ) ?: 'left'; // phpcs:ignore
		$text_color = get_post_meta( $asset_id, '_rapm_text_color', true ) ?: '#ffffff'; // phpcs:ignore
		$text_style = get_post_meta( $asset_id, '_rapm_text_style', true ) ?: 'bold'; // phpcs:ignore
		$text_font  = get_post_meta( $asset_id, '_rapm_text_font', true );
		$starts_at  = get_post_meta( $asset_id, '_rapm_starts_at', true );
		$ends_at    = get_post_meta( $asset_id, '_rapm_ends_at', true );
		$url        = RAPM_Destination::resolve_url( get_post_meta( $asset_id, '_rapm_destination_type', true ), get_post_meta( $asset_id, '_rapm_destination_value', true ) );
		$has_words  = $headline || $subhead || $cta_text;
		// The box sits beside the first thing on the page, so the first
		// live picture loads right away (same as the Slider's, 1.40.1).
		$first = $eager && RAPM_Quick_Links::is_live_now( $starts_at, $ends_at );
		if ( $first ) {
			$eager = false;
		}
		$load_attrs = $first ? 'loading="eager" fetchpriority="high" data-no-lazy="1"' : 'loading="lazy"';
		// A finished graphic (no words over it) is shown whole, with a
		// blurred copy of itself behind any gap, so its own words are never
		// cut off whatever the box's shape. Words over a picture fill the
		// box instead, like a slide.
		$style = $has_words ? '' : ' style="--rapm-blur:url(&quot;' . esc_url( $src ) . '&quot;);"';
		ob_start();
		?>
		<div class="rapm-pb-item<?php echo $has_words ? '' : ' is-picture-only'; ?>" data-rapm-key="<?php echo (int) $asset_id; ?>" data-rapm-start="<?php echo esc_attr( $starts_at ); ?>" data-rapm-end="<?php echo esc_attr( $ends_at ); ?>" hidden<?php echo $style; // phpcs:ignore WordPress.Security.EscapeOutput -- URL escaped above. ?>>
			<img class="rapm-pb-img" src="<?php echo esc_url( $src ); ?>" alt="<?php echo esc_attr( $alt ); ?>" <?php echo $load_attrs; // phpcs:ignore WordPress.Security.EscapeOutput -- fixed attribute text. ?> />
			<?php if ( $has_words ) : ?>
				<div class="rapm-slide-copy" data-align="<?php echo esc_attr( $text_align ); ?>" data-style="<?php echo esc_attr( $text_style ); ?>" style="color:<?php echo esc_attr( $text_color ); ?>;<?php echo esc_attr( RAPM_Elementor::font_family_css( $text_font ) ); ?>">
					<?php if ( $headline ) : ?><h2 class="rapm-headline"><?php echo esc_html( $headline ); ?></h2><?php endif; ?>
					<?php if ( $subhead ) : ?><p class="rapm-subhead"><?php echo esc_html( $subhead ); ?></p><?php endif; ?>
					<?php if ( $cta_text ) : ?><span class="rapm-cta-btn"><?php echo esc_html( $cta_text ); ?></span><?php endif; ?>
				</div>
			<?php endif; ?>
			<?php if ( $url ) : ?>
				<a href="<?php echo esc_url( $url ); ?>" class="rapm-slide-link" aria-label="<?php echo esc_attr( $headline ? $headline : $alt ); ?>"></a>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Shows the first live promotion and hides the rest, checking again
	 * every minute (RAPM_Schedule.watch). With none live, the box hides and
	 * the Slider takes the full width; Swiper is told to measure again.
	 */
	private static function print_script( $id ) {
		?>
		<script>
			( function () {
				var waited = false;
				function init() {
					if ( typeof RAPM_Schedule === 'undefined' ) {
						if ( ! waited ) { waited = true; window.addEventListener( 'load', init ); }
						return;
					}
					var wrap = document.getElementById( <?php echo wp_json_encode( $id ); ?> );
					var box = wrap && wrap.querySelector( '[data-rapm-pb]' );
					if ( ! box ) { return; }
					// Full page width: the page's width without the scrollbar,
					// which 100vw would include (as in rapm-looks.js).
					if ( wrap.classList.contains( 'is-full' ) ) {
						var fullWidth = function () { wrap.style.setProperty( '--rapm-split-vw', document.documentElement.clientWidth + 'px' ); };
						fullWidth();
						window.addEventListener( 'resize', fullWidth );
					}
					RAPM_Schedule.watch( box, '.rapm-pb-item', function ( active ) {
						Array.prototype.forEach.call( box.querySelectorAll( '.rapm-pb-item' ), function ( item ) {
							item.hidden = item !== active[ 0 ];
						} );
						wrap.classList.toggle( 'rapm-split-empty', ! active.length );
						window.dispatchEvent( new Event( 'resize' ) );
					} );
				}
				if ( document.readyState === 'loading' ) {
					document.addEventListener( 'DOMContentLoaded', init );
				} else {
					init();
				}
			} )();
		</script>
		<?php
	}
}
