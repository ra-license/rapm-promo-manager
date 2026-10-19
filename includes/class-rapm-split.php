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

	/** Google Material Symbols' drawing area. */
	const MS_BOX = '0 -960 960 960';

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

	/**
	 * The canvas for the form's editor (1.45.0): sanitized, plus each
	 * picture's address and name, which the editor shows but never saves.
	 */
	public static function canvas_for_editor( $raw ) {
		$canvas = self::sanitize_canvas( is_array( $raw ) ? $raw : array() );
		foreach ( $canvas['items'] as $i => $it ) {
			if ( 'img' === $it['k'] ) {
				$canvas['items'][ $i ]['u'] = (string) wp_get_attachment_image_url( $it['a'], 'medium_large' );
				$canvas['items'][ $i ]['t'] = wp_strip_all_tags( html_entity_decode( get_the_title( $it['a'] ), ENT_QUOTES, 'UTF-8' ) ); // Plain text: the editor escapes it (1.48.1).
			}
		}
		return $canvas;
	}

	/** 1.47.0: the Google families a canvas uses. */
	public static function canvas_fonts( $canvas ) {
		$fonts = array();
		foreach ( isset( $canvas['items'] ) ? $canvas['items'] : array() as $it ) {
			if ( ! empty( $it['ff'] ) ) {
				$fonts[ $it['ff'] ] = true;
			}
		}
		return array_keys( $fonts );
	}

	/** 1.44.0: 'design' (made on the canvas) or 'graphic' (a finished picture, as before). */
	public static function box_mode( $asset_id ) {
		return 'design' === get_post_meta( $asset_id, '_rapm_box_mode', true ) ? 'design' : 'graphic';
	}

	/**
	 * The canvas layout (1.44.0), checked value by value: the browser sends
	 * it as JSON (rapm-box-canvas.js), so nothing in it is trusted. Returns
	 * array( 'bg' => '#hex' or '', 'pic' => bool, 'items' => list ). Each
	 * item is array( 'k', 'x', 'y', 's', 'c' ) plus 'n' and 'd' for an icon.
	 * The headline, smaller line and button can each appear once; up to 12
	 * icons. An icon's drawing must be plain SVG path data (letters for the
	 * path commands, numbers, commas, dots, minus signs and spaces), so it
	 * can't carry markup or script.
	 */
	public static function sanitize_canvas( $raw ) {
		$raw   = is_array( $raw ) ? $raw : array();
		$bg    = isset( $raw['bg'] ) ? sanitize_hex_color( (string) $raw['bg'] ) : '';
		$out   = array(
			'bg'    => $bg ? $bg : '',
			'pic'   => ! empty( $raw['pic'] ),
			'items' => array(),
		);
		$seen   = array();
		$icons  = 0;
		$images = 0;
		$items = isset( $raw['items'] ) && is_array( $raw['items'] ) ? $raw['items'] : array();
		foreach ( $items as $it ) {
			if ( ! is_array( $it ) || empty( $it['k'] ) || ! in_array( $it['k'], array( 'headline', 'sub', 'btn', 'icon', 'img' ), true ) ) {
				continue;
			}
			$k     = $it['k'];
			$limit = array( 'headline' => array( 3, 20 ), 'sub' => array( 2, 12 ), 'btn' => array( 2, 8 ), 'icon' => array( 4, 40 ), 'img' => array( 5, 100 ) );
			$color = isset( $it['c'] ) ? sanitize_hex_color( (string) $it['c'] ) : '';
			// 1.50.0: a website (Elementor) color stays linked: cg is its
			// global color ID, c its value now (the fallback, and what the
			// readability check measures).
			$cg    = isset( $it['cg'] ) ? sanitize_key( (string) $it['cg'] ) : '';
			$item  = array(
				'k' => $k,
				'x' => round( max( 0, min( 100, isset( $it['x'] ) ? (float) $it['x'] : 50 ) ), 1 ),
				'y' => round( max( 0, min( 100, isset( $it['y'] ) ? (float) $it['y'] : 50 ) ), 1 ),
				's' => round( max( $limit[ $k ][0], min( $limit[ $k ][1], isset( $it['s'] ) ? (float) $it['s'] : $limit[ $k ][0] ) ), 1 ),
				'c' => $color ? $color : '#1d3557',
			);
			if ( $cg && 'img' !== $k ) {
				$item['cg'] = $cg;
			}
			if ( 'icon' === $k ) {
				$name = isset( $it['n'] ) ? preg_replace( '/[^a-z0-9_-]/', '', strtolower( (string) $it['n'] ) ) : '';
				$d    = isset( $it['d'] ) ? (string) $it['d'] : '';
				if ( '' === $name || '' === $d || strlen( $d ) > 20000 || ! preg_match( '/^[MmLlHhVvCcSsQqTtAaZz0-9.,\-\s]+$/', $d ) || ++$icons > 12 ) {
					continue;
				}
				$item['n'] = $name;
				$item['d'] = $d;
				// 1.45.0: where it's from, and its drawing area (Font
				// Awesome icons vary in width: "0 0 640 512" for a couch).
				$item['f'] = isset( $it['f'] ) && in_array( $it['f'], array( 'fas', 'far', 'fab' ), true ) ? $it['f'] : 'ms';
				$item['v'] = self::MS_BOX;
				if ( 'ms' !== $item['f'] && isset( $it['v'] ) && preg_match( '/^0 0 (\d{1,4}) (\d{1,4})$/', (string) $it['v'], $vb ) && (int) $vb[1] > 0 && (int) $vb[2] > 0 ) {
					$item['v'] = '0 0 ' . (int) $vb[1] . ' ' . (int) $vb[2];
				}
			} elseif ( 'img' === $k ) {
				// 1.45.0: a Media Library picture (a logo or badge), by ID only.
				$a = isset( $it['a'] ) ? absint( $it['a'] ) : 0;
				if ( ! $a || ! wp_attachment_is_image( $a ) || ++$images > 6 ) {
					continue;
				}
				$item['a'] = $a;
				unset( $item['c'] );
			} elseif ( isset( $seen[ $k ] ) ) {
				continue;
			} else {
				$seen[ $k ] = true;
				// 1.47.0: its own font, a Google family (ff, must be in the
				// bundled list) or one of the site's Elementor brand fonts
				// (fg, its global font ID). Neither means the promotion's
				// Typeface, as before.
				$ff = isset( $it['ff'] ) ? RAPM_Fonts::canonical( $it['ff'] ) : '';
				$fg = isset( $it['fg'] ) ? sanitize_key( (string) $it['fg'] ) : '';
				if ( $ff ) {
					$item['ff'] = $ff;
				} elseif ( $fg ) {
					$item['fg'] = $fg;
				}
				// 1.50.0 (brand kit text styles): weight and ALL CAPS.
				if ( isset( $it['fw'] ) && in_array( (int) $it['fw'], array( 400, 500, 600, 700, 800, 900 ), true ) ) {
					$item['fw'] = (int) $it['fw'];
				}
				if ( ! empty( $it['tt'] ) ) {
					$item['tt'] = true;
				}
			}
			$out['items'][] = $item;
		}
		return $out;
	}

	/**
	 * A promotion made on the canvas (1.44.0): its background (a color, or
	 * its picture cover-cropped to fill the box), and a square design area
	 * fitted inside the box holding the words and icons where they were
	 * placed. Sizes are a share of the square's width (cqw), with a floor
	 * so words stay readable on phones; the whole square shows on every
	 * screen, so the design looks the same everywhere, only smaller.
	 */
	private static function render_design( $asset_id, $src, $url, $load_attrs ) {
		$canvas   = self::sanitize_canvas( get_post_meta( $asset_id, '_rapm_canvas', true ) );
		$use_pic  = $canvas['pic'] && $src;
		$bg       = $use_pic ? '#111111' : ( $canvas['bg'] ? $canvas['bg'] : '#f3ead8' );
		$texts    = array(
			'headline' => get_post_meta( $asset_id, '_rapm_headline', true ),
			'sub'      => get_post_meta( $asset_id, '_rapm_subhead', true ),
			'btn'      => get_post_meta( $asset_id, '_rapm_cta_text', true ),
		);
		$style    = get_post_meta( $asset_id, '_rapm_text_style', true ) ?: 'bold'; // phpcs:ignore
		$font     = get_post_meta( $asset_id, '_rapm_text_font', true );
		$alt      = get_post_meta( $asset_id, '_rapm_alt_text', true );
		$headline = $texts['headline'];
		ob_start();
		?>
		<?php if ( $use_pic ) : ?>
			<img class="rapm-pb-img" src="<?php echo esc_url( $src ); ?>" alt="" <?php echo $load_attrs; // phpcs:ignore WordPress.Security.EscapeOutput -- fixed attribute text. ?> />
		<?php endif; ?>
		<div class="rapm-pb-board" data-style="<?php echo esc_attr( $style ); ?>" style="<?php echo esc_attr( RAPM_Elementor::font_family_css( $font ) ); ?>">
			<?php
			foreach ( $canvas['items'] as $it ) {
				$pos = 'left:' . $it['x'] . '%;top:' . $it['y'] . '%;--s:' . $it['s'] . ';';
				// 1.50.0: a linked website color follows Elementor's global color.
				$col = ! empty( $it['cg'] ) ? 'var(--e-global-color-' . $it['cg'] . ', ' . $it['c'] . ')' : ( isset( $it['c'] ) ? $it['c'] : '' );
				if ( ! empty( $it['fw'] ) ) {
					$pos .= 'font-weight:' . (int) $it['fw'] . ';';
				}
				if ( ! empty( $it['tt'] ) ) {
					$pos .= 'text-transform:uppercase;';
				}
				if ( 'icon' === $it['k'] ) {
					// Font Awesome Free's icons are CC BY 4.0; its own SVGs
					// carry this credit, so ours do too.
					$credit = 'ms' === $it['f'] ? '' : '<!-- Font Awesome Free by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free -->';
					echo '<span class="rapm-pb-t is-icon" style="' . esc_attr( $pos . 'color:' . $col ) . '" aria-hidden="true"><svg viewBox="' . esc_attr( $it['v'] ) . '" focusable="false">' . $credit . '<path d="' . esc_attr( $it['d'] ) . '"/></svg></span>'; // phpcs:ignore WordPress.Security.EscapeOutput -- fixed comment text.
					continue;
				}
				if ( 'img' === $it['k'] ) {
					$img = wp_get_attachment_image_url( $it['a'], 'medium_large' );
					if ( $img ) {
						$img_alt = get_post_meta( $it['a'], '_wp_attachment_image_alt', true );
						echo '<img class="rapm-pb-t is-img" src="' . esc_url( $img ) . '" alt="' . esc_attr( $img_alt ) . '" style="' . esc_attr( $pos ) . '" loading="lazy" decoding="async" />';
					}
					continue;
				}
				$text = $texts[ $it['k'] ];
				if ( '' === (string) $text ) {
					continue;
				}
				// 1.47.0: the piece's own font, from this site's copy.
				if ( ! empty( $it['ff'] ) && RAPM_Fonts::local_url( $it['ff'] ) ) {
					echo RAPM_Fonts::link_tag( $it['ff'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- built with esc_url().
					$pos .= 'font-family:' . RAPM_Fonts::css_family( $it['ff'] ) . ';';
				} elseif ( ! empty( $it['fg'] ) ) {
					$pos .= RAPM_Elementor::font_family_css( $it['fg'] );
				}
				if ( 'headline' === $it['k'] ) {
					echo '<h2 class="rapm-pb-t is-headline" style="' . esc_attr( $pos . 'color:' . $col ) . '">' . esc_html( $text ) . '</h2>';
				} elseif ( 'sub' === $it['k'] ) {
					echo '<p class="rapm-pb-t is-sub" style="' . esc_attr( $pos . 'color:' . $col ) . '">' . esc_html( $text ) . '</p>';
				} else {
					echo '<span class="rapm-pb-t is-btn" style="' . esc_attr( $pos . 'background:' . $col ) . '">' . esc_html( $text ) . '</span>';
				}
			}
			?>
		</div>
		<?php if ( $url ) : ?>
			<a href="<?php echo esc_url( $url ); ?>" class="rapm-slide-link" aria-label="<?php echo esc_attr( $headline ? $headline : $alt ); ?>"></a>
		<?php endif; ?>
		<?php
		return array( ob_get_clean(), $bg );
	}

	/**
	 * A small copy of a designed promotion for its card on the Promotions
	 * screen (1.44.1): the same markup and styles as the website, without
	 * the link. '' for a finished graphic (its card shows the picture).
	 */
	public static function card_preview( $asset_id ) {
		if ( 'design' !== self::box_mode( $asset_id ) ) {
			return '';
		}
		$image_id = (int) get_post_meta( $asset_id, '_rapm_image_desktop_id', true );
		$src      = $image_id ? wp_get_attachment_image_url( $image_id, 'large' ) : '';
		list( $inner, $bg ) = self::render_design( $asset_id, $src, '', 'loading="lazy"' );
		return '<span class="rapm-pb-item is-design rapm-card-design" style="' . esc_attr( 'background:' . $bg ) . '">' . $inner . '</span>';
	}

	private static function render_item( $asset_id, &$eager ) {
		$image_id = (int) get_post_meta( $asset_id, '_rapm_image_desktop_id', true );
		$src      = $image_id ? wp_get_attachment_image_url( $image_id, 'full' ) : '';
		$design   = 'design' === self::box_mode( $asset_id );
		// A finished graphic needs its picture; a design can be words and
		// icons on a plain color.
		if ( ! $src && ! $design ) {
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
		if ( $design ) {
			list( $inner, $bg ) = self::render_design( $asset_id, $src, $url, $load_attrs );
			return '<div class="rapm-pb-item is-design" data-rapm-key="' . (int) $asset_id . '" data-rapm-start="' . esc_attr( $starts_at ) . '" data-rapm-end="' . esc_attr( $ends_at ) . '" hidden style="' . esc_attr( 'background:' . $bg ) . '">' . $inner . '</div>';
		}
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
					var isFull = wrap.classList.contains( 'is-full' );
					var main = wrap.querySelector( '.rapm-split-main' );
					// 1.43.2 (Phil, on staging at 1903px wide): a 4:3 room
					// in a wide column ran past the bottom of the screen. On
					// computers the room is kept within the screen below
					// where the block starts (the header), when it starts in
					// the first screen, but never less than half as tall as
					// its column is wide. rapm-hero.css trims the photos
					// evenly top and bottom to that height.
					var size = function () {
						if ( isFull ) { wrap.style.setProperty( '--rapm-split-vw', document.documentElement.clientWidth + 'px' ); }
						var top = wrap.getBoundingClientRect().top + ( window.pageYOffset || document.documentElement.scrollTop || 0 );
						var screenH = window.innerHeight;
						var room = screenH - ( top < screenH * 0.6 ? top : 0 );
						wrap.style.setProperty( '--rapm-split-maxh', Math.round( Math.max( room, main.clientWidth / 2 ) ) + 'px' );
					};
					size();
					var sizing = 0;
					window.addEventListener( 'resize', function () {
						if ( sizing ) { return; }
						sizing = 1;
						window.requestAnimationFrame( function () { size(); sizing = 0; } );
					} );
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
