<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * [rapm_marquee items="4"] — a compact row of square image tiles (a
 * "quick links" strip), not a scrolling ticker. Every tile is forced
 * through the same 1080x1080 slot (RAPM_Slots) regardless of the source
 * photo's own shape, which is exactly what keeps every tile the same
 * size on screen — the original version of this (elsewhere on the site)
 * displayed each image at its own native proportions, so the row visibly
 * varied tile to tile depending on what a client happened to upload.
 *
 * `items` controls how many tiles show at once (1–5, default from
 * Settings). Anything beyond that count is never shown alongside the
 * current set — it pages in as its own screen instead, advanced by a
 * timer and/or the Prev/Next buttons, the same "carousel" expectation as
 * the hero. All paging happens client-side, in JS, same as every other
 * display mode here — see RAPM_Schedule for why (full-page caching).
 */
class RAPM_Marquee {

	public static function shortcode( $atts ) {
		$defaults = RAPM_Admin_Settings::get();
		$atts     = shortcode_atts(
			array(
				'placement' => 'default',
				'items'     => $defaults['default_marquee_items'],
				'autoplay'  => $defaults['default_marquee_autoplay'] ? 'yes' : 'no',
				'speed'     => $defaults['default_marquee_speed'],
			),
			$atts,
			'rapm_marquee'
		);

		$placement = sanitize_title( $atts['placement'] );
		$per_row   = max( 1, min( 5, (int) $atts['items'] ) );

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
						'value' => 'marquee',
					),
				),
			)
		);

		if ( ! $query->have_posts() ) {
			return '';
		}

		$instance_id = 'rapm-marquee-' . $placement . '-' . wp_unique_id();

		ob_start();
		echo RAPM_Assets::need( 'marquee' ); // phpcs:ignore WordPress.Security.EscapeOutput -- core-generated link and script tags.
		?>
		<div class="rapm-marquee-wrap <?php echo esc_attr( $instance_id ); ?>" data-rapm-marquee style="display:none;">
			<button type="button" class="rapm-marquee-arrow rapm-marquee-prev" aria-label="<?php esc_attr_e( 'Previous', 'rapm' ); ?>" hidden>&lsaquo;</button>
			<div class="rapm-marquee" style="--rapm-marquee-items:<?php echo (int) $per_row; ?>;">
				<?php foreach ( $query->posts as $post ) : self::render_tile( $post->ID ); endforeach; ?>
			</div>
			<button type="button" class="rapm-marquee-arrow rapm-marquee-next" aria-label="<?php esc_attr_e( 'Next', 'rapm' ); ?>" hidden>&rsaquo;</button>
		</div>
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
					var root       = document.querySelector( '.<?php echo esc_js( $instance_id ); ?>' );
					var track      = root.querySelector( '.rapm-marquee' );
					var prevBtn    = root.querySelector( '.rapm-marquee-prev' );
					var nextBtn    = root.querySelector( '.rapm-marquee-next' );
					var perPage    = <?php echo (int) $per_row; ?>;
					var autoplayOn = <?php echo 'yes' === $atts['autoplay'] ? 'true' : 'false'; ?>;
					var speed      = <?php echo max( 2000, (int) $atts['speed'] ); ?>;
					var reduceMotion = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

					var pages     = [];
					var pageIndex = 0;
					var timer     = null;

					function chunk( list, size ) {
						var out = [];
						for ( var i = 0; i < list.length; i += size ) {
							out.push( list.slice( i, i + size ) );
						}
						return out;
					}

					function showPage( i ) {
						if ( ! pages.length ) { return; }
						pageIndex = ( i + pages.length ) % pages.length;
						var all = track.querySelectorAll( '.rapm-marquee-tile' );
						Array.prototype.forEach.call( all, function ( el ) { el.style.display = 'none'; } );
						pages[ pageIndex ].forEach( function ( el ) { el.style.display = ''; } );
					}

					function stopTimer() {
						if ( timer ) { clearInterval( timer ); timer = null; }
					}
					function startTimer() {
						stopTimer();
						if ( ! autoplayOn || reduceMotion || pages.length <= 1 ) { return; }
						timer = setInterval( function () { showPage( pageIndex + 1 ); }, speed );
					}

					prevBtn.addEventListener( 'click', function () { showPage( pageIndex - 1 ); startTimer(); } );
					nextBtn.addEventListener( 'click', function () { showPage( pageIndex + 1 ); startTimer(); } );
					root.addEventListener( 'mouseenter', stopTimer );
					root.addEventListener( 'mouseleave', startTimer );

					RAPM_Schedule.watch( root, '.rapm-marquee-tile', function ( active ) {
						pages = chunk( active, perPage );
						pageIndex = 0;
						showPage( 0 );
						var hasMultiplePages = pages.length > 1;
						prevBtn.hidden = ! hasMultiplePages;
						nextBtn.hidden = ! hasMultiplePages;
						startTimer();
						root.style.display = active.length ? '' : 'none';
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
		wp_reset_postdata();
		return ob_get_clean();
	}

	private static function render_tile( $asset_id ) {
		$headline    = get_post_meta( $asset_id, '_rapm_headline', true );
		$subhead     = get_post_meta( $asset_id, '_rapm_subhead', true );
		$alt         = get_post_meta( $asset_id, '_rapm_alt_text', true );
		$starts_at   = get_post_meta( $asset_id, '_rapm_starts_at', true );
		$ends_at     = get_post_meta( $asset_id, '_rapm_ends_at', true );
		$dest_type   = get_post_meta( $asset_id, '_rapm_destination_type', true );
		$dest_value  = get_post_meta( $asset_id, '_rapm_destination_value', true );
		$url         = RAPM_Destination::resolve_url( $dest_type, $dest_value );
		$desktop_id  = (int) get_post_meta( $asset_id, '_rapm_image_desktop_id', true );
		$desktop_src = $desktop_id ? wp_get_attachment_image_url( $desktop_id, 'full' ) : '';
		$text_font   = get_post_meta( $asset_id, '_rapm_text_font', true );

		if ( ! $desktop_src ) {
			return; // No usable image — nothing to show for this tile.
		}

		$tag = $url ? 'a' : 'div';
		?>
		<<?php echo esc_html( $tag ); ?> <?php echo $url ? 'href="' . esc_url( $url ) . '"' : ''; ?> class="rapm-marquee-tile" data-rapm-start="<?php echo esc_attr( $starts_at ); ?>" data-rapm-end="<?php echo esc_attr( $ends_at ); ?>">
			<span class="rapm-marquee-tile-image">
				<img src="<?php echo esc_url( $desktop_src ); ?>" alt="<?php echo esc_attr( $alt ); ?>" loading="lazy" />
			</span>
			<?php if ( $headline || $subhead ) : ?>
				<span class="rapm-marquee-tile-caption" <?php echo $text_font ? 'style="' . esc_attr( RAPM_Elementor::font_family_css( $text_font ) ) . '"' : ''; ?>>
					<?php if ( $headline ) : ?><span class="rapm-marquee-tile-headline"><?php echo esc_html( $headline ); ?></span><?php endif; ?>
					<?php if ( $subhead ) : ?><span class="rapm-marquee-tile-subhead"><?php echo esc_html( $subhead ); ?></span><?php endif; ?>
				</span>
			<?php endif; ?>
		</<?php echo esc_html( $tag ); ?>>
		<?php
	}

	/**
	 * Early <head> loading only; shortcode() loads the files wherever the
	 * row appears (see RAPM_Hero_Carousel::should_load_assets()).
	 */
	public static function should_load_assets() {
		if ( is_singular() ) {
			global $post;
			if ( $post && has_shortcode( $post->post_content, 'rapm_marquee' ) ) {
				return true;
			}
		}
		// No sidebar-widget fallback (removed in 1.26.1): a shortcode in a
		// classic Text widget loads its files when it renders, like anywhere
		// else. The fallback loaded every file on every page of any site with
		// an active Text widget, which hid the 1.26.0 bug and slowed pages.
		return false;
	}

	public static function enqueue() {
		if ( ! self::should_load_assets() ) {
			return;
		}
		RAPM_Assets::need( 'marquee' );
	}
}
