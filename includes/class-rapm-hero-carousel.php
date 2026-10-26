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
		// 1.43.0: a Slider set to split view uses 4:3 computer pictures.
		$kind      = RAPM_Slots::kind_for( $kind_key, $placement );
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
		// 1.41.1: a picture-only phone slide's full height, and the least
		// it may shrink to (80%), as a share of the carousel's width.
		$full_pct     = round( $mobile_slot['height'] / max( 1, $mobile_slot['width'] ) * 100, 4 );
		$floor_pct    = round( $full_pct * 0.8, 4 );
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
				/* 1.41.1: a phone picture with nothing laid over it (no
				   headline, subheading or button) usually has its words
				   baked in, like Kemper's sale graphics. Trimming it to the
				   cap cut those words off on real iPhones: Safari's bars
				   leave about 697 of an iPhone 16's 852px, so 75svh was
				   523px against the picture's 645. Chrome's device view has
				   no bars, which is why it looked fine there. So these show
				   the whole picture, fitted inside with a blurred copy of
				   itself filling the sides (rapm-hero.css, rapm-schedule.js).
				   The box keeps the capped height, but never less than 80%
				   of the picture's full height, so a short phone gets a
				   taller banner instead of a tiny picture. The height is set
				   as padding, because a percentage there is a share of the
				   carousel's width; a plain percentage first for browsers
				   without min()/max(). */
				.<?php echo esc_attr( $instance_id ); ?> .rapm-slide.has-mobile-img.is-picture-only { aspect-ratio: auto; max-height: none; height: 0; padding-top: <?php echo esc_html( $full_pct ); ?>%; padding-top: min( <?php echo esc_html( $full_pct ); ?>%, max( 75vh, <?php echo esc_html( $floor_pct ); ?>% ) ); padding-top: min( <?php echo esc_html( $full_pct ); ?>%, max( 75svh, <?php echo esc_html( $floor_pct ); ?>% ) ); }
			}
		</style>
		<?php // rapm-kind-* (1.32.2) lets the CSS fit a feature banner's words on phones. ?>
		<div class="swiper rapm-hero rapm-kind-<?php echo esc_attr( str_replace( '_', '-', $kind_key ) ); ?> <?php echo esc_attr( $instance_id ); ?>" style="display:none;--rapm-pins-accent:<?php echo esc_attr( RAPM_Elementor::resolve_accent_color_css( '#2271b1' ) ); ?>;" data-rapm-carousel<?php echo $main_heading ? ' data-rapm-h1' : ''; ?> data-rapm-pins data-rapm-pins-view="<?php esc_attr_e( 'View product', 'rapm' ); ?>" data-rapm-pins-close="<?php esc_attr_e( 'Close', 'rapm' ); ?>"<?php echo class_exists( 'WooCommerce' ) ? ' data-rapm-store="' . esc_url( rest_url( 'wc/store/v1/products' ) ) . '"' : ''; ?>>
			<div class="swiper-wrapper">
				<?php
				$h1_used = false;
				// 1.40.1: a Slider's first picture is usually the first thing
				// on the page, so it loads right away (see render_slide()).
				$eager = 'hero' === $kind_key;
				foreach ( $query->posts as $post ) {
					$heading = $main_heading && ! $h1_used ? 'h1' : 'h2';
					if ( self::render_slide( $post->ID, $heading, $eager ) && 'h1' === $heading ) {
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
		<?php self::render_shelf( $query->posts ); ?>
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
		// 1.43.0: beside its promotion box, when the spot is set to split.
		return 'hero' === $kind_key && class_exists( 'RAPM_Split' ) ? RAPM_Split::wrap( $placement, ob_get_clean() ) : ob_get_clean();
	}

	/**
	 * @param string $heading_tag 'h1' or 'h2' for the big words.
	 * @return bool True if a headline was printed (so an h1 was used up).
	 */
	/**
	 * One slide. $eager (passed by reference) is true for a Slider until a
	 * slide that's live right now is printed: that one's picture loads
	 * first. A slide outside its dates is still printed (rapm-schedule.js
	 * decides in the browser), but the browser removes it, so it shouldn't
	 * take the first load.
	 */
	private static function render_slide( $asset_id, $heading_tag = 'h2', &$eager = false ) {
		RAPM_Track::remember( $asset_id ); // 1.59.0: its details for Google Analytics, when that's on.
		$headline     = get_post_meta( $asset_id, '_rapm_headline', true );
		$subhead      = get_post_meta( $asset_id, '_rapm_subhead', true );
		$cta_text     = get_post_meta( $asset_id, '_rapm_cta_text', true );
		$alt          = get_post_meta( $asset_id, '_rapm_alt_text', true );
		$text_align   = get_post_meta( $asset_id, '_rapm_text_align', true ) ?: 'left'; // phpcs:ignore
		$text_color   = get_post_meta( $asset_id, '_rapm_text_color', true ) ?: '#ffffff'; // phpcs:ignore
		$text_style   = get_post_meta( $asset_id, '_rapm_text_style', true ) ?: 'bold'; // phpcs:ignore
		$text_font    = get_post_meta( $asset_id, '_rapm_text_font', true );
		$fonts        = RAPM_Line_Fonts::get( $asset_id ); // 1.62.0: a font for each line on the picture.
		$icon         = RAPM_Line_Icon::get( $asset_id ); // 1.63.0: an icon with the words.
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
		// 1.41.1: nothing laid over the phone picture, so phones show all
		// of it (see the .is-picture-only rule in render()).
		$picture_only = $mobile_src && ! $headline && ! $subhead && ! $cta_text;
		// 1.52.0: the promotion box promotion this room photo goes with (split sliders).
		$goes = RAPM_Split::goes_with( $asset_id );
		// 1.54.0: what stays in view when the picture is trimmed (split view).
		$focus_vars = RAPM_Focal::style_vars( $asset_id );
		?>
		<div class="swiper-slide rapm-slide<?php echo $mobile_src ? ' has-mobile-img' : ''; ?><?php echo $picture_only ? ' is-picture-only' : ''; ?>" data-rapm-key="<?php echo (int) $asset_id; ?>" data-rapm-start="<?php echo esc_attr( $starts_at ); ?>" data-rapm-end="<?php echo esc_attr( $ends_at ); ?>"<?php echo $goes ? ' data-rapm-goes="' . (int) $goes . '"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput -- an integer. ?><?php echo $focus_vars ? ' style="' . esc_attr( $focus_vars ) . '"' : ''; ?>>
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
				// 1.40.1: the first live Slider picture loads right away with
				// high priority, and data-no-lazy="1" keeps WP Rocket's
				// LazyLoad (and other lazy-loaders that honor it) from
				// swapping in a placeholder. On Kemper that placeholder
				// delayed the hero and threw off the product dots. The other
				// slides stay lazy.
				$first      = $eager && RAPM_Quick_Links::is_live_now( $starts_at, $ends_at );
				$load_attrs = $first ? 'loading="eager" fetchpriority="high" data-no-lazy="1"' : 'loading="lazy"';
				if ( $first ) {
					$eager = false;
				}
				?>
				<img src="<?php echo esc_url( $desktop_src ); ?>" alt="<?php echo esc_attr( $alt ); ?>" <?php echo $load_attrs; // phpcs:ignore WordPress.Security.EscapeOutput -- fixed attribute text. ?> class="rapm-slide-img<?php echo esc_attr( $img_class ); ?>" />
			</picture>
			<?php if ( $headline || $subhead || $cta_text ) : ?>
				<div class="rapm-slide-copy" data-align="<?php echo esc_attr( $text_align ); ?>"<?php echo RAPM_Place::attrs( $asset_id ); // phpcs:ignore WordPress.Security.EscapeOutput -- 1.64.0; escaped in attrs(). ?> data-style="<?php echo esc_attr( $text_style ); ?>" style="color:<?php echo esc_attr( $text_color ); ?>;<?php echo esc_attr( RAPM_Elementor::font_family_css( $text_font ) ); ?>">
					<?php echo RAPM_Line_Fonts::links( $fonts ); // phpcs:ignore WordPress.Security.EscapeOutput -- built with esc_url(). ?>
					<?php if ( $headline ) : ?><<?php echo $heading_tag; // phpcs:ignore WordPress.Security.EscapeOutput -- only 'h1' or 'h2'. ?> class="rapm-headline"<?php echo RAPM_Line_Fonts::style_attr( $fonts, 'head' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in style_attr(). ?>><?php echo RAPM_Line_Icon::html( $icon, 'head' ) . esc_html( $headline ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in html(). ?></<?php echo $heading_tag; // phpcs:ignore WordPress.Security.EscapeOutput ?>><?php endif; ?>
					<?php if ( $subhead ) : ?><p class="rapm-subhead"<?php echo RAPM_Line_Fonts::style_attr( $fonts, 'sub' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in style_attr(). ?>><?php echo esc_html( $subhead ); ?></p><?php endif; ?>
					<?php if ( $cta_text ) : ?><span class="rapm-cta-btn"<?php echo RAPM_Line_Fonts::style_attr( $fonts, 'btn' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in style_attr(). ?>><?php echo RAPM_Line_Icon::html( $icon, 'btn' ) . esc_html( $cta_text ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in html(). ?></span><?php endif; ?>
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
		foreach ( self::pins_for( $asset_id ) as $pin ) {
			$dot  = $pin['dot'];
			$info = $pin['info'];
			$n    = $pin['n'];
			?>
			<button type="button" class="rapm-pin-dot" data-pid="<?php echo (int) $dot['p']; ?>" data-x="<?php echo esc_attr( $dot['x'] ); ?>" data-y="<?php echo esc_attr( $dot['y'] ); ?>"<?php echo isset( $dot['mx'] ) ? ' data-mx="' . esc_attr( $dot['mx'] ) . '" data-my="' . esc_attr( $dot['my'] ) . '"' : ''; ?> data-name="<?php echo esc_attr( $info['name'] ); ?>" data-thumb="<?php echo esc_url( $info['thumb'] ); ?>" data-link="<?php echo esc_url( $info['link'] ); ?>" aria-expanded="false" aria-label="<?php echo esc_attr( sprintf( /* translators: 1: the dot's number, 2: product name */ __( '%1$d. %2$s', 'rapm' ), $n, $info['name'] ) ); ?>"><?php echo (int) $n; ?></button>
			<?php
		}
		$btn = RAPM_Looks::sanitize_photo_btn( get_post_meta( $asset_id, '_rapm_photo_btn', true ) );
		if ( $btn && $url ) {
			?>
			<a class="rapm-pin-btn" href="<?php echo esc_url( $url ); ?>" data-x="<?php echo esc_attr( $btn['x'] ); ?>" data-y="<?php echo esc_attr( $btn['y'] ); ?>"<?php echo isset( $btn['mx'] ) ? ' data-mx="' . esc_attr( $btn['mx'] ) . '" data-my="' . esc_attr( $btn['my'] ) . '"' : ''; ?><?php foreach ( array( 'ax', 'ay', 'max', 'may' ) as $k ) { echo isset( $btn[ $k ] ) ? ' data-' . $k . '="' . esc_attr( $btn[ $k ] ) . '"' : ''; } // phpcs:ignore WordPress.Security.EscapeOutput -- 1.64.0: lined up; fixed key, escaped value. ?><?php echo RAPM_Line_Fonts::style_attr( RAPM_Line_Fonts::get( $asset_id ), 'btn' ); // phpcs:ignore WordPress.Security.EscapeOutput -- 1.62.0: the placed button is on the picture; escaped in style_attr(). ?>><?php echo RAPM_Line_Icon::html( RAPM_Line_Icon::get( $asset_id ), 'btn' ) . esc_html( RAPM_Looks::photo_btn_text( $cta_text ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- 1.63.0; escaped in html(). ?></a>
			<?php
		}
	}

	/**
	 * A slide's dots whose products are on the website, numbered in order,
	 * each as array( 'n', 'dot', 'info' ). Shared by the dots on the slide
	 * and the shop bar under the carousel, so their numbers always match.
	 * Remembered for the request, so the products are looked up once.
	 */
	private static function pins_for( $asset_id ) {
		static $cache = array();
		$asset_id = (int) $asset_id;
		if ( ! isset( $cache[ $asset_id ] ) ) {
			$pins = array();
			foreach ( RAPM_Looks::sanitize_dots( get_post_meta( $asset_id, '_rapm_dots', true ) ) as $dot ) {
				$info = RAPM_Looks::product_info( $dot['p'] );
				if ( $info ) {
					$pins[] = array( 'n' => count( $pins ) + 1, 'dot' => $dot, 'info' => $info );
				}
			}
			$cache[ $asset_id ] = $pins;
		}
		return $cache[ $asset_id ];
	}

	/**
	 * 1.41.0: the shop bar under a Slider or Feature banner, like the one
	 * under Shop the Look's photo: the slide's button, and a numbered card
	 * for each product it has a dot on (picture, name, then live price and
	 * stock from rapm-pins.js). Phil, on Kemper and Gates: "I want that shop
	 * bar below both types of hero sections if there are dots."
	 *
	 * Printed only when at least one slide has dots. Every slide gets a
	 * panel (a slide without dots shows just its button), all stacked in
	 * one grid cell: the bar keeps the height of its tallest panel, so
	 * nothing below it moves when the slider changes slides.
	 * rapm-pins.js shows the panel of the slide on screen and hides the
	 * bar while the carousel itself is hidden (no live slides).
	 */
	private static function render_shelf( $posts ) {
		$panels   = array();
		$any_pins = false;
		foreach ( $posts as $post ) {
			$id   = (int) $post->ID;
			$pins = self::pins_for( $id );
			$url  = RAPM_Destination::resolve_url( get_post_meta( $id, '_rapm_destination_type', true ), get_post_meta( $id, '_rapm_destination_value', true ) );
			$any_pins = $any_pins || ! empty( $pins );
			$panels[] = array(
				'id'   => $id,
				'pins' => $pins,
				'url'  => $url,
				'text' => RAPM_Looks::photo_btn_text( get_post_meta( $id, '_rapm_cta_text', true ) ),
			);
		}
		if ( ! $any_pins ) {
			return;
		}
		?>
		<div class="rapm-pins-shelf" data-rapm-shelf hidden style="--rapm-pins-accent:<?php echo esc_attr( RAPM_Elementor::resolve_accent_color_css( '#2271b1' ) ); ?>;">
			<div class="rapm-pins-shelf-in">
				<?php foreach ( $panels as $panel ) : ?>
					<div class="rapm-pins-panel<?php echo $panel['pins'] ? '' : ' no-cards'; ?>" data-rapm-key="<?php echo (int) $panel['id']; ?>">
						<div class="rapm-pins-shelf-side">
							<?php if ( $panel['url'] ) : ?>
								<a class="rapm-pins-shelf-btn" href="<?php echo esc_url( $panel['url'] ); ?>"><?php echo esc_html( $panel['text'] ); ?></a>
							<?php endif; ?>
						</div>
						<?php if ( $panel['pins'] ) : ?>
							<div class="rapm-pins-cards-wrap">
								<ul class="rapm-pins-cards" aria-label="<?php esc_attr_e( 'Products in this picture', 'rapm' ); ?>">
									<?php foreach ( $panel['pins'] as $pin ) : ?>
										<li class="rapm-pins-card" data-pid="<?php echo (int) $pin['dot']['p']; ?>">
											<a class="rapm-pins-card-img" href="<?php echo esc_url( $pin['info']['link'] ); ?>" tabindex="-1" aria-hidden="true">
												<?php if ( $pin['info']['thumb'] ) : ?>
													<img src="<?php echo esc_url( $pin['info']['thumb'] ); ?>" alt="" loading="lazy" width="84" height="84" />
												<?php endif; ?>
												<span class="rapm-pins-num"><?php echo (int) $pin['n']; ?></span>
											</a>
											<div class="rapm-pins-card-meta">
												<a class="rapm-pins-card-name" href="<?php echo esc_url( $pin['info']['link'] ); ?>"><?php echo esc_html( $pin['info']['name'] ); ?></a>
												<span class="rapm-pins-card-price"></span>
												<span class="rapm-pins-card-stock"></span>
											</div>
										</li>
									<?php endforeach; ?>
								</ul>
								<div class="rapm-scroll-dots" aria-hidden="true" hidden></div>
							</div>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
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

	const SHORTCODE_TAGS = array( 'rapm_hero', 'rapm_fold_banner', 'rapm_promo_box' );

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
