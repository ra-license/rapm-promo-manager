<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * [rapm_hero] and [rapm_fold_banner] — both display "kinds" share this one
 * carousel/scheduling engine, parameterized by which kind's slot pair
 * (and which placement) to query. Evolves NovaSlider's proven Swiper.js +
 * client-side-schedule approach (already live on kemperhomefurnishings.com)
 * onto the validated rapm_asset data model: every slide now comes from a
 * properly-sized, WebP, size-capped asset with real HTML copy instead of a
 * free-form, unvalidated slide array.
 */
class RAPM_Hero_Carousel {

	public static function shortcode_hero( $atts ) {
		return self::render( 'hero', $atts, 'rapm_hero' );
	}

	public static function shortcode_fold_banner( $atts ) {
		return self::render( 'fold_banner', $atts, 'rapm_fold_banner' );
	}

	private static function render( $kind_key, $atts, $tag ) {
		$defaults = RAPM_Admin_Settings::get();
		$atts     = shortcode_atts(
			array(
				'placement' => 'default',
				'autoplay'  => $defaults['default_autoplay'] ? 'yes' : 'no',
				'speed'     => $defaults['default_autoplay_speed'],
				'nav'       => $defaults['default_nav_style'],
				// Show at most this many slides at once (0 = no limit). The
				// cap is applied in the visitor's browser, after the schedule
				// check, so it works behind a full-page cache (rapm-schedule.js).
				'max'       => '0',
				// heading="h1": this placement is the page's main heading
				// (1.29.1). Set where the spot is placed, not on the spot,
				// because one spot can be on pages that already have an H1.
				'heading'   => 'h2',
			),
			$atts,
			$tag
		);

		$placement = sanitize_title( $atts['placement'] );
		$kind      = RAPM_Slots::kind( $kind_key );
		$slots     = RAPM_Slots::all();

		$query = new WP_Query(
			array(
				'post_type'      => 'rapm_asset',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'menu_order date',
				'order'          => 'ASC',
				'meta_query'     => array(
					'relation' => 'AND',
					array(
						'key'   => '_rapm_placement',
						'value' => $placement,
					),
					array(
						'key'   => '_rapm_kind',
						'value' => $kind_key,
					),
				),
			)
		);

		if ( ! $query->have_posts() ) {
			return '';
		}

		$instance_id  = 'rapm-' . $placement . '-' . wp_unique_id();
		$desktop_slot = $slots[ $kind['desktop'] ];
		$mobile_slot  = $slots[ $kind['mobile'] ];
		// The page's main heading (heading="h1", 1.29.1): the first
		// promotion with words gets an h1, the rest keep h2.
		// rapm-schedule.js moves the h1 to the first one actually
		// showing, since the schedule can hide the first one here.
		$main_heading = 'h1' === strtolower( trim( (string) $atts['heading'] ) );

		ob_start();
		// Loads this carousel's files wherever it renders (popup, template,
		// widget). Prints them inline only if the footer already went out.
		echo RAPM_Assets::need( 'hero' ); // phpcs:ignore WordPress.Security.EscapeOutput -- core-generated link and script tags.
		?>
		<style>
			.<?php echo esc_attr( $instance_id ); ?> { width: 100%; margin: 0 auto; overflow: hidden; position: relative; }
			/* Every slide defaults to the desktop shape — including on phones,
			   for a slide with no dedicated mobile picture, so the fallback
			   image (see render_slide()) fills a box shaped for it instead of
			   being shrunk inside a much taller one meant for a mobile
			   picture that doesn't exist. A slide that DOES have a real
			   mobile picture switches to the mobile shape on phones, same as
			   before. Swiper's autoHeight option (rapm-schedule.js) resizes
			   the carousel to match whichever shape the active slide is
			   actually using. */
			.<?php echo esc_attr( $instance_id ); ?> .rapm-slide { aspect-ratio: <?php echo esc_html( $desktop_slot['width'] . ' / ' . $desktop_slot['height'] ); ?>; }
			/* A tall (9:16) mobile picture at full phone width is about as
			   tall as the whole screen, so with the site header above it the
			   bottom-anchored headline and button landed below the fold. Cap
			   the slide at 75% of the visible screen height; the picture is
			   cover-cropped to fit (it has no baked-in text to lose), and the
			   copy stays on screen. vh first as a fallback for browsers
			   without svh. The picture is pinned to the slide's box so it
			   crops evenly top and bottom instead of just losing its bottom. */
			@media (max-width: 768px) {
				.<?php echo esc_attr( $instance_id ); ?> .rapm-slide.has-mobile-img { aspect-ratio: <?php echo esc_html( $mobile_slot['width'] . ' / ' . $mobile_slot['height'] ); ?>; max-height: 75vh; max-height: 75svh; }
				.<?php echo esc_attr( $instance_id ); ?> .rapm-slide.has-mobile-img picture { position: absolute; inset: 0; }
			}
		</style>
		<?php // rapm-kind-* (1.32.2) lets the CSS fit a feature banner's words on phones. ?>
		<div class="swiper rapm-hero rapm-kind-<?php echo esc_attr( str_replace( '_', '-', $kind_key ) ); ?> <?php echo esc_attr( $instance_id ); ?>" style="display:none;--rapm-pins-accent:<?php echo esc_attr( RAPM_Elementor::resolve_accent_color_css( '#2271b1' ) ); ?>;" data-rapm-carousel<?php echo $main_heading ? ' data-rapm-h1' : ''; ?> data-rapm-pins data-rapm-pins-view="<?php esc_attr_e( 'View product', 'rapm' ); ?>" data-rapm-pins-close="<?php esc_attr_e( 'Close', 'rapm' ); ?>"<?php echo class_exists( 'WooCommerce' ) ? ' data-rapm-store="' . esc_url( rest_url( 'wc/store/v1/products' ) ) . '"' : ''; ?>>
			<div class="swiper-wrapper">
				<?php
				$h1_used = false;
				foreach ( $query->posts as $post ) {
					$heading = $main_heading && ! $h1_used ? 'h1' : 'h2';
					if ( self::render_slide( $post->ID, $heading ) && 'h1' === $heading ) {
						$h1_used = true;
					}
				}
				?>
			</div>
			<?php if ( 'both' === $atts['nav'] || 'arrows' === $atts['nav'] ) : ?>
				<div class="swiper-button-next rapm-nav-btn"></div>
				<div class="swiper-button-prev rapm-nav-btn"></div>
			<?php endif; ?>
			<?php if ( 'both' === $atts['nav'] || 'dots' === $atts['nav'] ) : ?>
				<div class="swiper-pagination"></div>
			<?php endif; ?>
		</div>
		<script>
			( function () {
				var waited = false;
				function init() {
					// The script can arrive after this point when the carousel
					// renders late (a popup, a footer template): wait for the
					// page to finish loading once, then try again.
					if ( typeof RAPM_Schedule === 'undefined' ) {
						if ( ! waited ) { waited = true; window.addEventListener( 'load', init ); }
						return;
					}
					RAPM_Schedule.init( '.<?php echo esc_js( $instance_id ); ?>', {
						loopMinSlides: 2,
						effect: 'slide',
						autoplay: <?php echo 'yes' === $atts['autoplay'] ? 'true' : 'false'; ?>,
						autoplaySpeed: <?php echo (int) $atts['speed']; ?>,
						nav: <?php echo wp_json_encode( $atts['nav'] ); ?>,
						max: <?php echo max( 0, (int) $atts['max'] ); ?>
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

	/**
	 * @param string $heading_tag 'h1' or 'h2' for the big words.
	 * @return bool True if a headline was printed (so an h1 was used up).
	 */
	private static function render_slide( $asset_id, $heading_tag = 'h2' ) {
		$headline     = get_post_meta( $asset_id, '_rapm_headline', true );
		$subhead      = get_post_meta( $asset_id, '_rapm_subhead', true );
		$cta_text     = get_post_meta( $asset_id, '_rapm_cta_text', true );
		$alt          = get_post_meta( $asset_id, '_rapm_alt_text', true );
		$text_align   = get_post_meta( $asset_id, '_rapm_text_align', true ) ?: 'left'; // phpcs:ignore
		$text_color   = get_post_meta( $asset_id, '_rapm_text_color', true ) ?: '#ffffff'; // phpcs:ignore
		$text_style   = get_post_meta( $asset_id, '_rapm_text_style', true ) ?: 'bold'; // phpcs:ignore
		$text_font    = get_post_meta( $asset_id, '_rapm_text_font', true );
		$starts_at    = get_post_meta( $asset_id, '_rapm_starts_at', true );
		$ends_at      = get_post_meta( $asset_id, '_rapm_ends_at', true );
		$dest_type    = get_post_meta( $asset_id, '_rapm_destination_type', true );
		$dest_value   = get_post_meta( $asset_id, '_rapm_destination_value', true );
		$url          = RAPM_Destination::resolve_url( $dest_type, $dest_value );
		$desktop_id   = (int) get_post_meta( $asset_id, '_rapm_image_desktop_id', true );
		$mobile_id    = (int) get_post_meta( $asset_id, '_rapm_image_mobile_id', true );
		$desktop_src  = $desktop_id ? wp_get_attachment_image_url( $desktop_id, 'full' ) : '';
		$mobile_src   = $mobile_id ? wp_get_attachment_image_url( $mobile_id, 'full' ) : '';

		if ( ! $desktop_src ) {
			return false; // No usable image — nothing to show for this asset.
		}
		$heading_tag = 'h1' === $heading_tag ? 'h1' : 'h2';
		?>
		<div class="swiper-slide rapm-slide<?php echo $mobile_src ? ' has-mobile-img' : ''; ?>" data-rapm-start="<?php echo esc_attr( $starts_at ); ?>" data-rapm-end="<?php echo esc_attr( $ends_at ); ?>">
			<picture>
				<?php if ( $mobile_src ) : ?>
					<source media="(max-width: 768px)" srcset="<?php echo esc_url( $mobile_src ); ?>" />
				<?php endif; ?>
				<?php
				// No dedicated mobile picture — the desktop one is the
				// fallback on phones too, per the <picture> spec (there's no
				// standards-compliant way to show nothing instead). But a
				// wide desktop shot force-cropped edge-to-edge into a tall
				// phone frame can cut off whatever the shot is actually of,
				// so this one case shrinks to fit within the frame instead
				// (nothing cropped, letterboxed instead) — a real mobile
				// picture is already cropped exactly for that shape on
				// purpose, so it keeps filling the frame edge-to-edge as
				// normal.
				$img_class = $mobile_src ? '' : ' rapm-fallback-desktop-on-mobile';
				?>
				<img src="<?php echo esc_url( $desktop_src ); ?>" alt="<?php echo esc_attr( $alt ); ?>" loading="lazy" class="rapm-slide-img<?php echo esc_attr( $img_class ); ?>" />
			</picture>
			<?php if ( $headline || $subhead || $cta_text ) : ?>
				<div class="rapm-slide-copy" data-align="<?php echo esc_attr( $text_align ); ?>" data-style="<?php echo esc_attr( $text_style ); ?>" style="color:<?php echo esc_attr( $text_color ); ?>;<?php echo esc_attr( RAPM_Elementor::font_family_css( $text_font ) ); ?>">
					<?php if ( $headline ) : ?><<?php echo $heading_tag; // phpcs:ignore WordPress.Security.EscapeOutput -- only 'h1' or 'h2'. ?> class="rapm-headline"><?php echo esc_html( $headline ); ?></<?php echo $heading_tag; // phpcs:ignore WordPress.Security.EscapeOutput ?>><?php endif; ?>
					<?php if ( $subhead ) : ?><p class="rapm-subhead"><?php echo esc_html( $subhead ); ?></p><?php endif; ?>
					<?php if ( $cta_text ) : ?><span class="rapm-cta-btn"><?php echo esc_html( $cta_text ); ?></span><?php endif; ?>
				</div>
			<?php endif; ?>
			<?php if ( $url ) : ?>
				<a href="<?php echo esc_url( $url ); ?>" class="rapm-slide-link" aria-label="<?php echo esc_attr( $headline ? $headline : $alt ); ?>"></a>
			<?php endif; ?>
			<?php self::render_pins( $asset_id, $url, $cta_text ); ?>
		</div>
		<?php
		echo '<script type="application/ld+json">' . wp_json_encode( self::schema_for_asset( $asset_id, $desktop_src, $url ), JSON_UNESCAPED_SLASHES ) . '</script>'; // phpcs:ignore
		return (bool) $headline;
	}

	/**
	 * 1.35.0: the slide's product dots and "Shop now" button, placed by
	 * Place the Pieces in the form (the same _rapm_dots and _rapm_photo_btn
	 * as Shop the Look, plus mx/my for the phone picture). rapm-pins.js
	 * positions them; a product that's gone or unpublished loses its dot.
	 */
	private static function render_pins( $asset_id, $url, $cta_text ) {
		$n = 0;
		foreach ( RAPM_Looks::sanitize_dots( get_post_meta( $asset_id, '_rapm_dots', true ) ) as $dot ) {
			$info = RAPM_Looks::product_info( $dot['p'] );
			if ( ! $info ) {
				continue;
			}
			$n++;
			?>
			<button type="button" class="rapm-pin-dot" data-pid="<?php echo (int) $dot['p']; ?>" data-x="<?php echo esc_attr( $dot['x'] ); ?>" data-y="<?php echo esc_attr( $dot['y'] ); ?>"<?php echo isset( $dot['mx'] ) ? ' data-mx="' . esc_attr( $dot['mx'] ) . '" data-my="' . esc_attr( $dot['my'] ) . '"' : ''; ?> data-name="<?php echo esc_attr( $info['name'] ); ?>" data-thumb="<?php echo esc_url( $info['thumb'] ); ?>" data-link="<?php echo esc_url( $info['link'] ); ?>" aria-expanded="false" aria-label="<?php echo esc_attr( sprintf( /* translators: 1: the dot's number, 2: product name */ __( '%1$d. %2$s', 'rapm' ), $n, $info['name'] ) ); ?>"><?php echo (int) $n; ?></button>
			<?php
		}
		$btn = RAPM_Looks::sanitize_photo_btn( get_post_meta( $asset_id, '_rapm_photo_btn', true ) );
		if ( $btn && $url ) {
			?>
			<a class="rapm-pin-btn" href="<?php echo esc_url( $url ); ?>" data-x="<?php echo esc_attr( $btn['x'] ); ?>" data-y="<?php echo esc_attr( $btn['y'] ); ?>"<?php echo isset( $btn['mx'] ) ? ' data-mx="' . esc_attr( $btn['mx'] ) . '" data-my="' . esc_attr( $btn['my'] ) . '"' : ''; ?>><?php echo esc_html( RAPM_Looks::photo_btn_text( $cta_text ) ); ?></a>
			<?php
		}
	}

	private static function schema_for_asset( $asset_id, $image_url, $url ) {
		$headline = get_post_meta( $asset_id, '_rapm_headline', true );
		$schema   = array(
			'@context' => 'https://schema.org',
			'@type'    => 'ImageObject',
			'contentUrl' => $image_url,
			'name'       => $headline ? $headline : get_the_title( $asset_id ),
		);
		if ( $url ) {
			$schema['url'] = $url;
		}
		return $schema;
	}

	const SHORTCODE_TAGS = array( 'rapm_hero', 'rapm_fold_banner' );

	/**
	 * Whether to load the files early, in <head>, on this request. Only an
	 * optimization now: render() loads them anyway wherever a carousel
	 * actually appears (RAPM_Assets::need()).
	 *
	 * Elementor's own Shortcode widget, and this plugin's Promo Carousel
	 * widget, save the raw shortcode as the page's plain content, so
	 * has_shortcode() on post_content sees both. The old second check ran
	 * apply_filters( 'the_content' ) here, which rendered the whole page an
	 * extra time on every request and could never find a shortcode tag in
	 * already-rendered output.
	 */
	public static function should_load_assets() {
		$force_load_on = apply_filters( 'rapm_force_load_ids', array() );

		if ( is_singular() ) {
			global $post;
			if ( $post ) {
				if ( in_array( $post->ID, $force_load_on, true ) ) {
					return true;
				}
				foreach ( self::SHORTCODE_TAGS as $tag ) {
					if ( has_shortcode( $post->post_content, $tag ) ) {
						return true;
					}
				}
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
		RAPM_Assets::need( 'hero' );
	}
}
