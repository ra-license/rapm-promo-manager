<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * [rapm_coupon_book] — a horizontal, scroll-snapping row of coupon cards.
 * Plain CSS/JS, no slider library (unlike the hero carousel's deliberate
 * Swiper exception) — a native scroll-snap row works fine for a row of
 * cards and needs nothing extra loaded. Reuses .rapm-slide-copy and its
 * text-styling rules from rapm-hero.css for the card's own text overlay,
 * so Text Alignment/Color/Style behave identically to every other
 * display mode rather than needing their own separate rules.
 */
class RAPM_Coupon_Book {

	public static function shortcode( $atts ) {
		$atts = shortcode_atts(
			array( 'placement' => 'default' ),
			$atts,
			'rapm_coupon_book'
		);

		$placement = sanitize_title( $atts['placement'] );

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
						'value' => 'coupon',
					),
				),
			)
		);

		if ( ! $query->have_posts() ) {
			return '';
		}

		$instance_id = 'rapm-coupons-' . $placement . '-' . wp_unique_id();

		ob_start();
		echo RAPM_Assets::need( 'coupon_book' ); // phpcs:ignore WordPress.Security.EscapeOutput -- core-generated link and script tags.
		?>
		<div class="rapm-coupon-book <?php echo esc_attr( $instance_id ); ?>" data-rapm-coupon-book style="display:none;">
			<?php foreach ( $query->posts as $post ) : self::render_card( $post->ID ); endforeach; ?>
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
					var root = document.querySelector( '.<?php echo esc_js( $instance_id ); ?>' );

					RAPM_Schedule.watch( root, '.rapm-coupon-card', function ( active ) {
						var all = root.querySelectorAll( '.rapm-coupon-card' );
						Array.prototype.forEach.call( all, function ( el ) { el.style.display = 'none'; } );
						active.forEach( function ( el ) { el.style.display = ''; } );
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

	private static function render_card( $asset_id ) {
		$headline    = get_post_meta( $asset_id, '_rapm_headline', true );
		$subhead     = get_post_meta( $asset_id, '_rapm_subhead', true );
		$cta_text    = get_post_meta( $asset_id, '_rapm_cta_text', true );
		$alt         = get_post_meta( $asset_id, '_rapm_alt_text', true );
		$starts_at   = get_post_meta( $asset_id, '_rapm_starts_at', true );
		$ends_at     = get_post_meta( $asset_id, '_rapm_ends_at', true );
		$text_align  = get_post_meta( $asset_id, '_rapm_text_align', true ) ?: 'left'; // phpcs:ignore
		$text_color  = get_post_meta( $asset_id, '_rapm_text_color', true ) ?: '#ffffff'; // phpcs:ignore
		$text_style  = get_post_meta( $asset_id, '_rapm_text_style', true ) ?: 'bold'; // phpcs:ignore
		$text_font   = get_post_meta( $asset_id, '_rapm_text_font', true );
		$dest_type   = get_post_meta( $asset_id, '_rapm_destination_type', true );
		$dest_value  = get_post_meta( $asset_id, '_rapm_destination_value', true );
		$url         = RAPM_Destination::resolve_url( $dest_type, $dest_value );
		$desktop_id  = (int) get_post_meta( $asset_id, '_rapm_image_desktop_id', true );
		$mobile_id   = (int) get_post_meta( $asset_id, '_rapm_image_mobile_id', true );
		$desktop_src = $desktop_id ? wp_get_attachment_image_url( $desktop_id, 'full' ) : '';
		$mobile_src  = $mobile_id ? wp_get_attachment_image_url( $mobile_id, 'full' ) : '';

		if ( ! $desktop_src ) {
			return; // No usable image — nothing to show for this card.
		}

		$tag = $url ? 'a' : 'div';
		?>
		<<?php echo esc_html( $tag ); ?> <?php echo $url ? 'href="' . esc_url( $url ) . '"' : ''; ?> class="rapm-coupon-card" data-rapm-start="<?php echo esc_attr( $starts_at ); ?>" data-rapm-end="<?php echo esc_attr( $ends_at ); ?>">
			<picture>
				<?php if ( $mobile_src ) : ?>
					<source media="(max-width: 480px)" srcset="<?php echo esc_url( $mobile_src ); ?>" />
				<?php endif; ?>
				<img src="<?php echo esc_url( $desktop_src ); ?>" alt="<?php echo esc_attr( $alt ); ?>" loading="lazy" />
			</picture>
			<?php if ( $headline || $subhead || $cta_text ) : ?>
				<div class="rapm-slide-copy" data-align="<?php echo esc_attr( $text_align ); ?>" data-style="<?php echo esc_attr( $text_style ); ?>" style="color:<?php echo esc_attr( $text_color ); ?>;<?php echo esc_attr( RAPM_Elementor::font_family_css( $text_font ) ); ?>">
					<?php if ( $headline ) : ?><h3 class="rapm-headline"><?php echo esc_html( $headline ); ?></h3><?php endif; ?>
					<?php if ( $subhead ) : ?><p class="rapm-subhead"><?php echo esc_html( $subhead ); ?></p><?php endif; ?>
					<?php if ( $cta_text ) : ?><span class="rapm-cta-btn"><?php echo esc_html( $cta_text ); ?></span><?php endif; ?>
				</div>
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
			if ( $post && has_shortcode( $post->post_content, 'rapm_coupon_book' ) ) {
				return true;
			}
		}
		return is_active_widget( false, false, 'text', true ) || is_active_widget( false, false, 'shortcode', true );
	}

	public static function enqueue() {
		if ( ! self::should_load_assets() ) {
			return;
		}
		RAPM_Assets::need( 'coupon_book' );
	}
}
