<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * [rapm_quick_links placement="category-top"] (1.37.0): a row of wide
 * pictures with a short name under each. It's the "Design Services /
 * Current Promotions / Financing / Visit Us" strip Indian River runs at the
 * top of its category pages, asked for by Phil on 2026-10-08 for Starfine.
 *
 * It's meant for the top of a page, so it never pushes products further
 * down than it has to:
 * - Computers and tablets: one row, every live link side by side. `items`
 *   (1–6, default 4) sets how many fit across; extras start a new row.
 * - Phones (under 768px), picked with `mobile`:
 *   - "compact" (default): one line of small rounded buttons, each with a
 *     round thumbnail, swiped sideways. About 56px tall.
 *   - "swipe": small picture cards in one row, swiped sideways. About 120px.
 *   - "hide": nothing on phones. This is what Indian River does.
 *
 * Unlike the Tile row, it shows straight from the page HTML. Links live when
 * the page is built are printed visible, and the rest are printed `hidden`.
 * rapm-schedule.js then only corrects that in the visitor's browser when a
 * cached page has gone stale (see RAPM_Schedule). The Tile row starts hidden
 * and appears once its script runs, which here would make the products jump
 * down after the page shows, right at the top where shoppers look first.
 */
class RAPM_Quick_Links {

	const MOBILE_MODES = array( 'compact', 'swipe', 'hide' );

	public static function shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'placement' => 'default',
				'items'     => 4,
				'mobile'    => 'compact',
				'label'     => __( 'Quick links', 'rapm' ),
			),
			$atts,
			'rapm_quick_links'
		);

		$placement = sanitize_title( $atts['placement'] );
		$per_row   = max( 1, min( 6, (int) $atts['items'] ) );
		$mobile    = in_array( $atts['mobile'], self::MOBILE_MODES, true ) ? $atts['mobile'] : 'compact';

		$query = new WP_Query(
			array(
				'post_type'      => 'rapm_asset',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'menu_order date',
				'order'          => 'ASC',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
					'relation' => 'AND',
					array(
						'key'   => '_rapm_placement',
						'value' => $placement,
					),
					array(
						'key'   => '_rapm_kind',
						'value' => 'quick_links',
					),
				),
			)
		);

		if ( ! $query->have_posts() ) {
			return '';
		}

		$items    = '';
		$any_live = false;
		foreach ( $query->posts as $post ) {
			$item = self::render_item( $post->ID );
			if ( '' === $item['html'] ) {
				continue;
			}
			$items   .= $item['html'];
			$any_live = $any_live || $item['live'];
		}
		wp_reset_postdata();

		if ( '' === $items ) {
			return '';
		}

		$instance_id = 'rapm-ql-' . $placement . '-' . wp_unique_id();

		$tags = RAPM_Assets::need( 'quick_links' );
		// The row shows straight from the page HTML, so its stylesheet can't
		// wait for the footer, where WordPress puts styles asked for after
		// <head> (a category template, a widget): the pictures would show at
		// full size until it arrived, then jump into the row. Print it right
		// here instead, once; WordPress then skips it in the footer.
		if ( '' === $tags && did_action( 'wp_head' ) ) {
			ob_start();
			wp_print_styles( array( 'rapm-quick-links-css' ) );
			$tags = (string) ob_get_clean();
		}

		ob_start();
		echo $tags; // phpcs:ignore WordPress.Security.EscapeOutput -- core-generated link and script tags.
		?>
		<nav class="rapm-quick-links rapm-ql-m-<?php echo esc_attr( $mobile ); ?> <?php echo esc_attr( $instance_id ); ?>" aria-label="<?php echo esc_attr( $atts['label'] ); ?>" style="--rapm-ql-items:<?php echo (int) $per_row; ?>;" <?php echo $any_live ? '' : 'hidden'; ?>>
			<ul class="rapm-ql-list">
				<?php echo $items; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in render_item(). ?>
			</ul>
		</nav>
		<script>
			( function () {
				var waited = false;
				function init() {
					// The script can arrive late when the row renders late (a
					// popup, a footer template): wait for page load once.
					if ( typeof RAPM_Schedule === 'undefined' ) {
						if ( ! waited ) { waited = true; window.addEventListener( 'load', init ); }
						return;
					}
					var root = document.querySelector( '.<?php echo esc_js( $instance_id ); ?>' );
					if ( ! root ) { return; }
					var items = Array.prototype.slice.call( root.querySelectorAll( '.rapm-ql-item' ) );
					RAPM_Schedule.watch( root, '.rapm-ql-item', function ( active ) {
						items.forEach( function ( el ) { el.hidden = active.indexOf( el ) === -1; } );
						root.hidden = ! active.length;
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
		return ob_get_clean();
	}

	/**
	 * One link. Returns array( 'html' => markup or '' with no usable picture,
	 * 'live' => whether its dates include right now ).
	 */
	private static function render_item( $asset_id ) {
		$headline   = get_post_meta( $asset_id, '_rapm_headline', true );
		$subhead    = get_post_meta( $asset_id, '_rapm_subhead', true );
		$alt        = get_post_meta( $asset_id, '_rapm_alt_text', true );
		$starts_at  = get_post_meta( $asset_id, '_rapm_starts_at', true );
		$ends_at    = get_post_meta( $asset_id, '_rapm_ends_at', true );
		$dest_type  = get_post_meta( $asset_id, '_rapm_destination_type', true );
		$dest_value = get_post_meta( $asset_id, '_rapm_destination_value', true );
		$url        = RAPM_Destination::resolve_url( $dest_type, $dest_value );
		$image_id   = (int) get_post_meta( $asset_id, '_rapm_image_desktop_id', true );
		$text_font  = get_post_meta( $asset_id, '_rapm_text_font', true );

		$img = $image_id ? wp_get_attachment_image(
			$image_id,
			'full',
			false,
			array(
				'class'    => 'rapm-ql-img',
				// The name under the picture already says where the link goes,
				// so a picture with no alt text of its own is decorative.
				'alt'      => $alt ? $alt : '',
				// At the top of a page, so never lazy: it may be the first
				// picture a shopper sees.
				'loading'  => 'eager',
				'decoding' => 'async',
				'sizes'    => '(max-width: 767px) 50vw, (max-width: 1024px) 33vw, 25vw',
			)
		) : '';

		if ( ! $img ) {
			return array( 'html' => '', 'live' => false );
		}

		$live = self::is_live_now( $starts_at, $ends_at );
		$tag  = $url ? 'a' : 'span';

		ob_start();
		?>
		<li class="rapm-ql-item" data-rapm-key="<?php echo (int) $asset_id; ?>" data-rapm-start="<?php echo esc_attr( $starts_at ); ?>" data-rapm-end="<?php echo esc_attr( $ends_at ); ?>" <?php echo $live ? '' : 'hidden'; ?>>
			<<?php echo esc_html( $tag ); ?> class="rapm-ql-link" <?php echo $url ? 'href="' . esc_url( $url ) . '"' : ''; ?>>
				<span class="rapm-ql-image"><?php echo $img; // phpcs:ignore WordPress.Security.EscapeOutput -- wp_get_attachment_image() output. ?></span>
				<?php if ( $headline || $subhead ) : ?>
					<span class="rapm-ql-text" <?php echo $text_font ? 'style="' . esc_attr( RAPM_Elementor::font_family_css( $text_font ) ) . '"' : ''; ?>>
						<?php if ( $headline ) : ?><span class="rapm-ql-title"><?php echo esc_html( $headline ); ?></span><?php endif; ?>
						<?php if ( $subhead ) : ?><span class="rapm-ql-sub"><?php echo esc_html( $subhead ); ?></span><?php endif; ?>
					</span>
				<?php endif; ?>
			</<?php echo esc_html( $tag ); ?>>
		</li>
		<?php
		return array( 'html' => ob_get_clean(), 'live' => $live );
	}

	/**
	 * Whether a link's dates include right now, in the site's time zone. Only
	 * decides the first paint; rapm-schedule.js has the final say in the
	 * visitor's browser, so a cached page never freezes a link on or off.
	 */
	private static function is_live_now( $starts_at, $ends_at ) {
		try {
			$tz  = wp_timezone();
			$now = new DateTimeImmutable( 'now', $tz );
			if ( $starts_at && $now < new DateTimeImmutable( $starts_at, $tz ) ) {
				return false;
			}
			if ( $ends_at && $now > new DateTimeImmutable( $ends_at, $tz ) ) {
				return false;
			}
		} catch ( Exception $e ) {
			return true; // An unreadable date: show it and let the browser decide.
		}
		return true;
	}

	/**
	 * Early <head> loading only; shortcode() loads the files wherever the
	 * row appears (see RAPM_Hero_Carousel::should_load_assets()).
	 */
	public static function should_load_assets() {
		if ( is_singular() ) {
			global $post;
			if ( $post && has_shortcode( $post->post_content, 'rapm_quick_links' ) ) {
				return true;
			}
		}
		return false;
	}

	public static function enqueue() {
		if ( ! self::should_load_assets() ) {
			return;
		}
		RAPM_Assets::need( 'quick_links' );
	}
}
