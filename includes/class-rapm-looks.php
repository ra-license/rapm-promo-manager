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
 * Numbered dots on the photo, the pieces in it and Add to cart come in
 * 1.31.0 and 1.32.0.
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

	public static function sanitize_focus( $value ) {
		return in_array( $value, self::FOCUS_POINTS, true ) ? $value : 'center center';
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

		ob_start();
		// Loads the files wherever the looks render (popup, template,
		// widget). Prints them inline only if the footer already went out.
		echo RAPM_Assets::need( 'looks' ); // phpcs:ignore WordPress.Security.EscapeOutput -- core-generated link and script tags.
		?>
		<section class="rapm-looks <?php echo esc_attr( $instance_id ); ?>" id="<?php echo esc_attr( $instance_id ); ?>" style="display:none;--rapm-looks-accent:<?php echo esc_attr( $accent ); ?>;--rapm-looks-speed:<?php echo (int) $speed; ?>ms;" data-rapm-looks aria-roledescription="carousel" aria-label="<?php esc_attr_e( 'Shop the look', 'rapm' ); ?>">
			<div class="rapm-looks-stage">
				<?php foreach ( $looks as $i => $look ) : ?>
					<div class="rapm-look-photo" id="<?php echo esc_attr( $instance_id . '-photo-' . $look['id'] ); ?>" data-rapm-key="<?php echo esc_attr( $look['id'] ); ?>" data-rapm-start="<?php echo esc_attr( $look['start'] ); ?>" data-rapm-end="<?php echo esc_attr( $look['end'] ); ?>" data-tab="<?php echo esc_attr( $look['tab'] ); ?>" role="tabpanel" aria-roledescription="<?php esc_attr_e( 'slide', 'rapm' ); ?>" aria-label="<?php echo esc_attr( $look['tab'] ); ?>">
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
					<div class="rapm-look-text" id="<?php echo esc_attr( $instance_id . '-text-' . $look['id'] ); ?>" data-rapm-key="<?php echo esc_attr( $look['id'] ); ?>" hidden>
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
		$tab = trim( (string) get_post_meta( $id, '_rapm_tab_label', true ) );
		return array(
			'id'       => $id,
			'image_id' => $image_id,
			'tab'      => '' !== $tab ? $tab : get_the_title( $post ),
			'focus'    => self::sanitize_focus( get_post_meta( $id, '_rapm_focus', true ) ),
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
