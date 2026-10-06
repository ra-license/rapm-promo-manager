<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * [rapm_looks placement="home"] — Shop the Look (1.30.0), from the mockup
 * Phil approved on 2026-10-06: a full-width room photo, a thin bar of tabs
 * under it (one tab per look, named by the client: a room like "Living
 * Room" or a collection like "Stanton 338"), and the look's headline,
 * smaller line and button below. The tabs play as a tour that stops as
 * soon as a shopper picks one.
 *
 * Each look is one promotion of the 'look' kind, so it has the usual on/off
 * switch, start and end dates, and drag-to-reorder (the tab order).
 *
 * 1.31.0 adds the numbered dots (_rapm_dots: product + position on the
 * photo, placed by hand in the form's "Place the Pieces" step) and a card
 * for each piece under the look's words. Names, photos and links are
 * printed here; price and stock are read from WooCommerce's Store API in
 * the visitor's browser (rapm-looks.js), so they're never stale behind a
 * full-page cache and a price change never needs the look saved again.
 * Add to cart comes in 1.32.0.
 *
 * Like every display here, all looks are printed with their dates and
 * rapm-schedule.js's watch() decides in the visitor's browser which are
 * live, so it stays right behind a full-page cache. The photos and the
 * text are printed as two groups (the tab bar sits between them on the
 * page) and matched by data-rapm-key; rapm-looks.js builds the tabs from
 * whichever looks are live.
 */
class RAPM_Looks {

	/** Milliseconds each look shows during the tour. */
	const DEFAULT_SPEED = 8000;

	/** The nine "Which part to keep" positions, as CSS object-position values. */
	const FOCUS_POINTS = array( 'left top', 'center top', 'right top', 'left center', 'center center', 'right center', 'left bottom', 'center bottom', 'right bottom' );

	/** Most dots one look can have (1.31.0): enough for a whole room, few enough to stay readable. */
	const MAX_DOTS = 12;

	public static function sanitize_focus( $value ) {
		return in_array( $value, self::FOCUS_POINTS, true ) ? $value : 'center center';
	}

	/**
	 * The dots as a clean list of array( 'p' => product ID, 'x', 'y' => 0-100,
	 * one decimal ), at most MAX_DOTS. With $check_products (when saving),
	 * dots whose product isn't a WooCommerce product are dropped.
	 */
	public static function sanitize_dots( $dots, $check_products = false ) {
		$clean = array();
		if ( ! is_array( $dots ) ) {
			return $clean;
		}
		foreach ( $dots as $dot ) {
			if ( ! is_array( $dot ) || empty( $dot['p'] ) ) {
				continue;
			}
			$pid = absint( $dot['p'] );
			if ( ! $pid || ( $check_products && 'product' !== get_post_type( $pid ) ) ) {
				continue;
			}
			$clean[] = array(
				'p' => $pid,
				'x' => round( min( 100, max( 0, (float) ( isset( $dot['x'] ) ? $dot['x'] : 50 ) ) ), 1 ),
				'y' => round( min( 100, max( 0, (float) ( isset( $dot['y'] ) ? $dot['y'] : 50 ) ) ), 1 ),
			);
			if ( count( $clean ) >= self::MAX_DOTS ) {
				break;
			}
		}
		return $clean;
	}

	/**
	 * A product's name, SKU, small photo and link, from WordPress itself (no
	 * WooCommerce calls, so it works wherever posts work). Null when the
	 * product is gone or not published, so its dot and card don't show.
	 */
	public static function product_info( $pid ) {
		$pid = absint( $pid );
		if ( ! $pid || 'product' !== get_post_type( $pid ) || 'publish' !== get_post_status( $pid ) ) {
			return null;
		}
		return array(
			'name'  => html_entity_decode( get_the_title( $pid ), ENT_QUOTES, 'UTF-8' ),
			'sku'   => (string) get_post_meta( $pid, '_sku', true ),
			'thumb' => (string) get_the_post_thumbnail_url( $pid, 'woocommerce_thumbnail' ),
			'link'  => (string) get_permalink( $pid ),
		);
	}

	/** "center bottom" as fractions for the dot math: array( 0.5, 1 ). */
	public static function focus_fraction( $focus ) {
		$map   = array( 'left' => 0, 'top' => 0, 'center' => 0.5, 'right' => 1, 'bottom' => 1 );
		$parts = explode( ' ', self::sanitize_focus( $focus ) );
		return array( $map[ $parts[0] ], $map[ $parts[1] ] );
	}

	public static function shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'placement' => 'default',
				'autoplay'  => 'yes',
				'speed'     => self::DEFAULT_SPEED,
			),
			$atts,
			'rapm_looks'
		);

		$placement = sanitize_title( $atts['placement'] );
		$query     = new WP_Query(
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
						'value' => 'look',
					),
				),
			)
		);

		$looks = array();
		foreach ( $query->posts as $post ) {
			$look = self::look_data( $post );
			if ( $look ) {
				$looks[] = $look;
			}
		}
		wp_reset_postdata();
		if ( ! $looks ) {
			return '';
		}

		$instance_id = 'rapm-looks-' . $placement . '-' . wp_unique_id();
		$speed       = max( 3000, (int) $atts['speed'] );
		$accent      = RAPM_Elementor::resolve_accent_color_css( '#2271b1' );
		// Price and stock come from WooCommerce's Store API in the browser (1.31.0).
		$store       = class_exists( 'WooCommerce' ) ? rest_url( 'wc/store/v1/products' ) : '';

		ob_start();
		// Loads the files wherever the looks render (popup, template,
		// widget). Prints them inline only if the footer already went out.
		echo RAPM_Assets::need( 'looks' ); // phpcs:ignore WordPress.Security.EscapeOutput -- core-generated link and script tags.
		?>
		<section class="rapm-looks <?php echo esc_attr( $instance_id ); ?>" id="<?php echo esc_attr( $instance_id ); ?>" style="display:none;--rapm-looks-accent:<?php echo esc_attr( $accent ); ?>;--rapm-looks-speed:<?php echo (int) $speed; ?>ms;" data-rapm-looks<?php echo $store ? ' data-rapm-store="' . esc_url( $store ) . '"' : ''; ?> aria-roledescription="carousel" aria-label="<?php esc_attr_e( 'Shop the look', 'rapm' ); ?>">
			<div class="rapm-looks-stage">
				<?php foreach ( $looks as $i => $look ) : ?>
					<div class="rapm-look-photo" id="<?php echo esc_attr( $instance_id . '-photo-' . $look['id'] ); ?>" data-rapm-key="<?php echo esc_attr( $look['id'] ); ?>" data-rapm-start="<?php echo esc_attr( $look['start'] ); ?>" data-rapm-end="<?php echo esc_attr( $look['end'] ); ?>" data-tab="<?php echo esc_attr( $look['tab'] ); ?>" data-w="<?php echo (int) $look['width']; ?>" data-h="<?php echo (int) $look['height']; ?>" data-fx="<?php echo esc_attr( $look['fx'] ); ?>" data-fy="<?php echo esc_attr( $look['fy'] ); ?>" role="tabpanel" aria-roledescription="<?php esc_attr_e( 'slide', 'rapm' ); ?>" aria-label="<?php echo esc_attr( $look['tab'] ); ?>">
						<?php
						echo wp_get_attachment_image( // phpcs:ignore WordPress.Security.EscapeOutput -- core-escaped image tag.
							$look['image_id'],
							'full',
							false,
							array(
								'class'    => 'rapm-look-img',
								'alt'      => $look['alt'],
								'sizes'    => '100vw',
								'loading'  => 0 === $i ? 'eager' : 'lazy',
								'decoding' => 'async',
								'style'    => 'object-position:' . $look['focus'] . ';',
							)
						);
						?>
						<?php foreach ( $look['pieces'] as $k => $piece ) : ?>
							<button type="button" class="rapm-look-dot" data-k="<?php echo (int) $k; ?>" data-pid="<?php echo (int) $piece['p']; ?>" data-x="<?php echo esc_attr( $piece['x'] ); ?>" data-y="<?php echo esc_attr( $piece['y'] ); ?>" aria-expanded="false" aria-label="<?php echo esc_attr( sprintf( /* translators: 1: the dot's number, 2: product name */ __( '%1$d. %2$s', 'rapm' ), $k + 1, $piece['name'] ) ); ?>"><?php echo (int) ( $k + 1 ); ?></button>
						<?php endforeach; ?>
					</div>
				<?php endforeach; ?>
				<div class="rapm-looks-arrows">
					<button type="button" class="rapm-looks-prev" aria-label="<?php esc_attr_e( 'Previous look', 'rapm' ); ?>" hidden>
						<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true" focusable="false"><path d="m15 5-7 7 7 7"/></svg>
					</button>
					<button type="button" class="rapm-looks-next" aria-label="<?php esc_attr_e( 'Next look', 'rapm' ); ?>" hidden>
						<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true" focusable="false"><path d="m9 5 7 7-7 7"/></svg>
					</button>
				</div>
			</div>
			<div class="rapm-looks-bar">
				<div class="rapm-looks-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Choose a look', 'rapm' ); ?>"></div>
				<button type="button" class="rapm-looks-tour" hidden></button>
			</div>
			<div class="rapm-looks-shelf" aria-live="off">
				<?php foreach ( $looks as $look ) : ?>
					<div class="rapm-look-text<?php echo $look['pieces'] ? ' has-cards' : ''; ?>" id="<?php echo esc_attr( $instance_id . '-text-' . $look['id'] ); ?>" data-rapm-key="<?php echo esc_attr( $look['id'] ); ?>" hidden>
						<div class="rapm-look-intro">
							<p class="rapm-look-eyebrow"><?php esc_html_e( 'Shop the look', 'rapm' ); ?> <span>&middot; <?php echo esc_html( $look['tab'] ); ?></span></p>
							<?php if ( $look['headline'] ) : ?>
								<h2 class="rapm-look-title"><?php echo esc_html( $look['headline'] ); ?></h2>
							<?php endif; ?>
							<?php if ( $look['subhead'] ) : ?>
								<p class="rapm-look-blurb"><?php echo esc_html( $look['subhead'] ); ?></p>
							<?php endif; ?>
							<?php if ( $look['cta'] && $look['url'] ) : ?>
								<a class="rapm-look-btn" href="<?php echo esc_url( $look['url'] ); ?>"><?php echo esc_html( $look['cta'] ); ?></a>
							<?php endif; ?>
						</div>
						<?php if ( $look['pieces'] ) : ?>
							<ol class="rapm-look-cards">
								<?php foreach ( $look['pieces'] as $k => $piece ) : ?>
									<li class="rapm-look-card" data-k="<?php echo (int) $k; ?>" data-pid="<?php echo (int) $piece['p']; ?>">
										<a class="rapm-look-card-img" href="<?php echo esc_url( $piece['link'] ); ?>" tabindex="-1" aria-hidden="true">
											<?php if ( $piece['thumb'] ) : ?>
												<img src="<?php echo esc_url( $piece['thumb'] ); ?>" alt="" loading="lazy" decoding="async" />
											<?php endif; ?>
											<span class="rapm-look-num"><?php echo (int) ( $k + 1 ); ?></span>
										</a>
										<div class="rapm-look-card-meta">
											<a class="rapm-look-card-name" href="<?php echo esc_url( $piece['link'] ); ?>"><?php echo esc_html( $piece['name'] ); ?></a>
											<span class="rapm-look-price"></span>
											<span class="rapm-look-stock"></span>
										</div>
									</li>
								<?php endforeach; ?>
							</ol>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>
		</section>
		<script>
			( function () {
				var waited = false;
				function init() {
					// The scripts can arrive after this point when the looks
					// render late (a popup, a footer template): wait for the
					// page to finish loading once, then try again.
					if ( typeof RAPM_Looks === 'undefined' || typeof RAPM_Schedule === 'undefined' ) {
						if ( ! waited ) { waited = true; window.addEventListener( 'load', init ); }
						return;
					}
					RAPM_Looks.init( document.getElementById( <?php echo wp_json_encode( $instance_id ); ?> ), {
						autoplay: <?php echo 'yes' === $atts['autoplay'] ? 'true' : 'false'; ?>,
						text: <?php echo wp_json_encode( self::js_text() ); ?>
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

	/** One look's fields, or null if it has no photo (nothing to show). */
	private static function look_data( $post ) {
		$id       = $post->ID;
		$image_id = (int) get_post_meta( $id, '_rapm_image_desktop_id', true );
		if ( ! $image_id || ! wp_get_attachment_image_url( $image_id, 'full' ) ) {
			return null;
		}
		$tab    = trim( (string) get_post_meta( $id, '_rapm_tab_label', true ) );
		$meta   = wp_get_attachment_metadata( $image_id );
		$focus  = self::sanitize_focus( get_post_meta( $id, '_rapm_focus', true ) );
		$fxy    = self::focus_fraction( $focus );
		$pieces = array();
		foreach ( self::sanitize_dots( get_post_meta( $id, '_rapm_dots', true ) ) as $dot ) {
			$info = self::product_info( $dot['p'] );
			if ( $info ) { // A product that's gone or unpublished just loses its dot and card.
				$pieces[] = array_merge( $dot, $info );
			}
		}
		return array(
			'id'       => $id,
			'image_id' => $image_id,
			'width'    => is_array( $meta ) && ! empty( $meta['width'] ) ? (int) $meta['width'] : 0,
			'height'   => is_array( $meta ) && ! empty( $meta['height'] ) ? (int) $meta['height'] : 0,
			'fx'       => $fxy[0],
			'fy'       => $fxy[1],
			'pieces'   => $pieces,
			'tab'      => '' !== $tab ? $tab : get_the_title( $post ),
			'focus'    => $focus,
			'alt'      => (string) get_post_meta( $id, '_rapm_alt_text', true ),
			'headline' => (string) get_post_meta( $id, '_rapm_headline', true ),
			'subhead'  => (string) get_post_meta( $id, '_rapm_subhead', true ),
			'cta'      => (string) get_post_meta( $id, '_rapm_cta_text', true ),
			'url'      => RAPM_Destination::resolve_url(
				get_post_meta( $id, '_rapm_destination_type', true ),
				get_post_meta( $id, '_rapm_destination_value', true )
			),
			'start'    => (string) get_post_meta( $id, '_rapm_starts_at', true ),
			'end'      => (string) get_post_meta( $id, '_rapm_ends_at', true ),
		);
	}

	/** Words rapm-looks.js puts on the tour button. */
	private static function js_text() {
		return array(
			'pause' => __( 'Pause the tour', 'rapm' ),
			'play'  => __( 'Play the tour', 'rapm' ),
			'view'  => __( 'View product', 'rapm' ),
			'close' => __( 'Close', 'rapm' ),
		);
	}

	/**
	 * Early <head> loading only; shortcode() loads the files wherever the
	 * looks appear (see RAPM_Hero_Carousel::should_load_assets()).
	 */
	public static function should_load_assets() {
		if ( is_singular() ) {
			global $post;
			if ( $post && has_shortcode( $post->post_content, 'rapm_looks' ) ) {
				return true;
			}
		}
		return false;
	}

	public static function enqueue() {
		if ( ! self::should_load_assets() ) {
			return;
		}
		RAPM_Assets::need( 'looks' );
	}
}
