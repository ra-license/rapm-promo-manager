<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The brand kit (1.50.0, Design Center roadmap step 4, from the mockup
 * Phil approved on 2026-10-19, https://claude.ai/artifact/NfgxFBRHXzuHUnsRmZd3P2):
 * one per website, in the Design Center's third tab. It holds:
 * - the website's Elementor colors (read only here, linked in designs),
 * - saved colors (also pickable from a picture, worked out in the browser),
 * - text styles (font, weight, size, color, letter case),
 * - logos and badges (Media Library pictures, shown first in the canvas),
 * - templates (saved canvas designs), plus three R&A starters.
 *
 * Phil's rule (2026-10-19): anything that changes Elementor or the live
 * website is administrators only. The kit changes neither: Elementor's
 * colors are only read, and colors, styles and templates are copied into
 * a design when used, so editing the kit never changes a live promotion.
 * It's open to everyone who edits promotions.
 */
class RAPM_Brand_Kit {

	const OPTION = 'rapm_brand_kit';
	const NONCE  = 'rapm_brand_kit';

	public static function get() {
		$kit = get_option( self::OPTION, array() );
		return self::sanitize( is_array( $kit ) ? $kit : array() );
	}

	/** The website's Elementor colors that are plain hex values: list of array( id, name, hex ). */
	public static function site_colors() {
		$names  = RAPM_Elementor::global_colors();
		$values = RAPM_Elementor::color_value_map();
		$out    = array();
		foreach ( $names as $id => $name ) {
			$hex = isset( $values[ $id ] ) ? sanitize_hex_color( $values[ $id ] ) : '';
			if ( $hex ) {
				$out[] = array( 'id' => sanitize_key( $id ), 'name' => $name, 'hex' => strtolower( $hex ) );
			}
		}
		return $out;
	}

	private static function clean_text( $v, $max ) {
		return mb_substr( sanitize_text_field( (string) $v ), 0, $max );
	}

	/** Everything the kit can hold, checked value by value. */
	public static function sanitize( $raw ) {
		$kit = array( 'colors' => array(), 'styles' => array(), 'logos' => array(), 'templates' => array() );

		foreach ( isset( $raw['colors'] ) && is_array( $raw['colors'] ) ? $raw['colors'] : array() as $c ) {
			$hex = is_array( $c ) && isset( $c['hex'] ) ? sanitize_hex_color( (string) $c['hex'] ) : '';
			if ( $hex && count( $kit['colors'] ) < 40 ) {
				$kit['colors'][] = array( 'name' => self::clean_text( isset( $c['name'] ) ? $c['name'] : '', 40 ), 'hex' => strtolower( $hex ) );
			}
		}

		foreach ( isset( $raw['styles'] ) && is_array( $raw['styles'] ) ? $raw['styles'] : array() as $st ) {
			if ( ! is_array( $st ) || count( $kit['styles'] ) >= 30 ) {
				continue;
			}
			$color = isset( $st['c'] ) ? sanitize_hex_color( (string) $st['c'] ) : '';
			$one   = array(
				'id'   => isset( $st['id'] ) && preg_match( '/^[a-z0-9]{6,20}$/', (string) $st['id'] ) ? (string) $st['id'] : strtolower( wp_generate_password( 10, false ) ),
				'name' => self::clean_text( isset( $st['name'] ) ? $st['name'] : '', 40 ),
				'fw'   => isset( $st['fw'] ) && in_array( (int) $st['fw'], array( 400, 500, 600, 700, 800, 900 ), true ) ? (int) $st['fw'] : 700,
				's'    => round( max( 2, min( 20, isset( $st['s'] ) ? (float) $st['s'] : 8 ) ), 1 ),
				'c'    => $color ? strtolower( $color ) : '#1d3557',
				'tt'   => ! empty( $st['tt'] ),
			);
			$ff = isset( $st['ff'] ) ? RAPM_Fonts::canonical( $st['ff'] ) : '';
			$fg = isset( $st['fg'] ) ? sanitize_key( (string) $st['fg'] ) : '';
			if ( $ff ) {
				$one['ff'] = $ff;
			} elseif ( $fg ) {
				$one['fg'] = $fg;
			}
			$kit['styles'][] = $one;
		}

		foreach ( isset( $raw['logos'] ) && is_array( $raw['logos'] ) ? $raw['logos'] : array() as $a ) {
			$a = absint( $a );
			if ( $a && wp_attachment_is_image( $a ) && ! in_array( $a, $kit['logos'], true ) && count( $kit['logos'] ) < 30 ) {
				$kit['logos'][] = $a;
			}
		}

		foreach ( isset( $raw['templates'] ) && is_array( $raw['templates'] ) ? $raw['templates'] : array() as $t ) {
			if ( ! is_array( $t ) || count( $kit['templates'] ) >= 50 ) {
				continue;
			}
			$kit['templates'][] = self::clean_template( $t );
		}
		return $kit;
	}

	private static function clean_template( $t ) {
		$texts = isset( $t['texts'] ) && is_array( $t['texts'] ) ? $t['texts'] : array();
		return array(
			'id'     => isset( $t['id'] ) && preg_match( '/^[a-z0-9]{6,20}$/', (string) $t['id'] ) ? (string) $t['id'] : strtolower( wp_generate_password( 10, false ) ),
			'name'   => self::clean_text( isset( $t['name'] ) ? $t['name'] : __( 'Template', 'rapm' ), 60 ),
			'canvas' => RAPM_Split::sanitize_canvas( isset( $t['canvas'] ) && is_array( $t['canvas'] ) ? $t['canvas'] : array() ),
			'texts'  => array(
				'headline' => self::clean_text( isset( $texts['headline'] ) ? $texts['headline'] : '', 120 ),
				'sub'      => self::clean_text( isset( $texts['sub'] ) ? $texts['sub'] : '', 160 ),
				'btn'      => self::clean_text( isset( $texts['btn'] ) ? $texts['btn'] : '', 40 ),
			),
			'at'     => isset( $t['at'] ) ? absint( $t['at'] ) : time(),
		);
	}

	/**
	 * Three R&A starter templates, colored with this website's own
	 * colors (Primary, Secondary, Accent, when Elementor has them).
	 */
	public static function starters() {
		$by = array();
		foreach ( self::site_colors() as $c ) {
			$by[ $c['id'] ] = $c['hex'];
		}
		/*
		 * The first of these site colors that white words can sit on
		 * (4.5:1) and that isn't $not; else the fallback. A site color
		 * stays linked (cg), like one picked in the canvas.
		 */
		$pick = function ( $ids, $fallback, $not = '' ) use ( $by ) {
			foreach ( $ids as $id ) {
				if ( isset( $by[ $id ] ) && $by[ $id ] !== $not && RAPM_Elementor::contrast_with_white( $by[ $id ] ) >= 4.5 ) {
					return array( 'c' => $by[ $id ], 'cg' => $id );
				}
			}
			return array( 'c' => $fallback );
		};
		$bg_sale = $pick( array( 'secondary', 'primary' ), '#1d3557' );
		$white   = '#ffffff';
		$sale_bt = $pick( array( 'accent', 'primary', 'secondary' ), '#c8102e', $bg_sale['c'] );
		$event   = $pick( array( 'secondary', 'primary' ), '#1d3557' );
		$finance = $pick( array( 'primary', 'secondary' ), '#7a1f2b' );
		return array(
			array(
				'id'     => 'starter-sale',
				'name'   => __( 'Sale', 'rapm' ),
				'canvas' => array( 'bg' => $bg_sale['c'], 'pic' => false, 'items' => array(
					array( 'k' => 'headline', 'x' => 50, 'y' => 36, 's' => 13, 'c' => $white, 'fw' => 800, 'tt' => true ),
					array( 'k' => 'sub', 'x' => 50, 'y' => 55, 's' => 5, 'c' => $white ),
					array( 'k' => 'btn', 'x' => 50, 'y' => 76, 's' => 3.8 ) + $sale_bt,
				) ),
				'texts'  => array( 'headline' => __( 'Big Sale', 'rapm' ), 'sub' => __( 'This weekend only', 'rapm' ), 'btn' => __( 'Shop now', 'rapm' ) ),
			),
			array(
				'id'     => 'starter-event',
				'name'   => __( 'Event', 'rapm' ),
				'canvas' => array( 'bg' => '#ffffff', 'pic' => false, 'items' => array(
					array( 'k' => 'headline', 'x' => 50, 'y' => 34, 's' => 10, 'fw' => 700 ) + $event,
					array( 'k' => 'sub', 'x' => 50, 'y' => 52, 's' => 5, 'c' => '#333333' ),
					array( 'k' => 'btn', 'x' => 50, 'y' => 74, 's' => 3.8 ) + $event,
				) ),
				'texts'  => array( 'headline' => __( 'Brand Event', 'rapm' ), 'sub' => __( 'Nov 1–15 in our showroom', 'rapm' ), 'btn' => __( 'See the details', 'rapm' ) ),
			),
			array(
				'id'     => 'starter-financing',
				'name'   => __( 'Financing', 'rapm' ),
				'canvas' => array( 'bg' => '#f3ead8', 'pic' => false, 'items' => array(
					array( 'k' => 'headline', 'x' => 50, 'y' => 34, 's' => 11, 'fw' => 800 ) + $finance,
					array( 'k' => 'sub', 'x' => 50, 'y' => 53, 's' => 4.8, 'c' => '#333333' ),
					array( 'k' => 'btn', 'x' => 50, 'y' => 74, 's' => 3.8 ) + $finance,
				) ),
				'texts'  => array( 'headline' => __( '0% for 60 months', 'rapm' ), 'sub' => __( 'On purchases over $1,999', 'rapm' ), 'btn' => __( 'Apply now', 'rapm' ) ),
			),
		);
	}

	/** Everything the canvas editor and the kit tab need, ready for JSON. */
	public static function for_browser() {
		$kit   = self::get();
		$logos = array();
		foreach ( $kit['logos'] as $a ) {
			$logos[] = array(
				'a' => $a,
				'u' => (string) wp_get_attachment_image_url( $a, 'medium' ),
				't' => wp_strip_all_tags( html_entity_decode( get_the_title( $a ), ENT_QUOTES, 'UTF-8' ) ),
			);
		}
		$templates = array();
		foreach ( array_merge( $kit['templates'], self::starters() ) as $t ) {
			$t = 0 === strpos( (string) $t['id'], 'starter-' ) ? array_merge( $t, array( 'canvas' => RAPM_Split::sanitize_canvas( $t['canvas'] ), 'starter' => true ) ) : $t;
			$t['canvas'] = RAPM_Split::canvas_for_editor( $t['canvas'] );
			$templates[] = $t;
		}
		$brand_fonts = array();
		$families    = RAPM_Elementor::font_family_map();
		foreach ( RAPM_Elementor::global_fonts() as $id => $title ) {
			if ( ! empty( $families[ $id ] ) ) {
				$brand_fonts[ $id ] = array( $title, $families[ $id ] );
			}
		}
		return array(
			'site'      => self::site_colors(),
			'colors'    => $kit['colors'],
			'styles'    => $kit['styles'],
			'logos'     => $logos,
			'templates' => $templates,
			'fonts'     => $brand_fonts,
		);
	}

	/** Saves the whole kit (the Brand kit tab). Templates come from the form's "Save as template". */
	public static function ajax_save() {
		check_ajax_referer( self::NONCE, 'nonce' );
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Not allowed.', 'rapm' ) ), 403 );
		}
		$raw = isset( $_POST['kit'] ) ? json_decode( wp_unslash( $_POST['kit'] ), true ) : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- sanitized below.
		if ( ! is_array( $raw ) ) {
			wp_send_json_error( array( 'message' => __( 'Nothing to save.', 'rapm' ) ), 400 );
		}
		$kit = self::sanitize( $raw );
		update_option( self::OPTION, $kit, false );
		wp_send_json_success( array( 'kit' => self::for_browser() ) );
	}

	/** "Save as template…" from the promotion box canvas: adds one template. */
	public static function ajax_add_template() {
		check_ajax_referer( self::NONCE, 'nonce' );
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Not allowed.', 'rapm' ) ), 403 );
		}
		$canvas = isset( $_POST['canvas'] ) ? json_decode( wp_unslash( $_POST['canvas'] ), true ) : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- sanitized in clean_template().
		$texts  = isset( $_POST['texts'] ) ? json_decode( wp_unslash( $_POST['texts'] ), true ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- sanitized in clean_template().
		$name   = isset( $_POST['name'] ) ? wp_unslash( $_POST['name'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- sanitized in clean_template().
		if ( ! is_array( $canvas ) || '' === trim( (string) $name ) ) {
			wp_send_json_error( array( 'message' => __( 'Give the template a name.', 'rapm' ) ), 400 );
		}
		$kit = self::get();
		if ( count( $kit['templates'] ) >= 50 ) {
			wp_send_json_error( array( 'message' => __( 'This site already has 50 templates. Remove one in the Brand kit first.', 'rapm' ) ), 400 );
		}
		$tpl                 = self::clean_template( array( 'name' => $name, 'canvas' => $canvas, 'texts' => is_array( $texts ) ? $texts : array(), 'at' => time() ) );
		array_unshift( $kit['templates'], $tpl );
		update_option( self::OPTION, $kit, false );
		$tpl['canvas'] = RAPM_Split::canvas_for_editor( $tpl['canvas'] );
		wp_send_json_success( array( 'template' => $tpl ) );
	}

	public static function enqueue() {
		wp_enqueue_media();
		wp_enqueue_style( 'rapm-box-canvas-css', RAPM_URL . 'assets/css/rapm-box-canvas.css', array( 'rapm-hero-css' ), RAPM_VERSION );
		wp_enqueue_script( 'rapm-brand-kit', RAPM_URL . 'assets/js/rapm-brand-kit.js', array(), RAPM_VERSION, true );
		wp_localize_script(
			'rapm-brand-kit',
			'RAPM_BrandKit',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( self::NONCE ),
				'gfonts'  => RAPM_URL . 'assets/data/google-fonts.json?ver=' . RAPM_VERSION,
				'text'    => array(
					'saving'     => __( 'Saving…', 'rapm' ),
					'saved'      => __( 'Saved.', 'rapm' ),
					'failed'     => __( 'That didn\'t save. Please try again.', 'rapm' ),
					'linked'     => __( 'linked', 'rapm' ),
					'remove'     => __( 'Remove', 'rapm' ),
					'noSite'     => __( 'No Elementor global colors found on this site.', 'rapm' ),
					'noSaved'    => __( 'No saved colors yet.', 'rapm' ),
					'newColor'   => __( 'New color', 'rapm' ),
					'pickPic'    => __( 'Pick a picture to take colors from', 'rapm' ),
					'usePic'     => __( 'Use this picture', 'rapm' ),
					'colorful'   => __( 'Colorful', 'rapm' ),
					'muted'      => __( 'Muted', 'rapm' ),
					'deep'       => __( 'Deep', 'rapm' ),
					'light'      => __( 'Light', 'rapm' ),
					'fromPic'    => __( 'From picture', 'rapm' ),
					'picFailed'  => __( 'That picture couldn\'t be read here. Try another one from the Media Library.', 'rapm' ),
					'noStyles'   => __( 'No text styles yet. Add one for your sale headlines, smaller lines and fine print.', 'rapm' ),
					'newStyle'   => __( 'New style', 'rapm' ),
					'styleName'  => __( 'Name', 'rapm' ),
					'font'       => __( 'Font', 'rapm' ),
					'siteDefault' => __( 'Site default', 'rapm' ),
					'googleFont' => __( 'Or a Google font (type its name)', 'rapm' ),
					'weight'     => __( 'Weight', 'rapm' ),
					'size'       => __( 'Size', 'rapm' ),
					'color'      => __( 'Color', 'rapm' ),
					'caps'       => __( 'ALL CAPS', 'rapm' ),
					'sample'     => __( 'Big Sale 30% Off', 'rapm' ),
					'noLogos'    => __( 'No logos or badges yet.', 'rapm' ),
					'pickLogos'  => __( 'Pick logos and badges', 'rapm' ),
					'addLogos'   => __( 'Add them', 'rapm' ),
					'noTpls'     => __( 'No saved templates yet. In a promotion box design, use "Save as template…".', 'rapm' ),
					'starter'    => __( 'R&A starter', 'rapm' ),
					'thisStore'  => __( 'This store', 'rapm' ),
					'rename'     => __( 'Template name', 'rapm' ),
					'cancel'     => __( 'Cancel', 'rapm' ),
					'removeAsk'  => __( 'Remove this template? Promotions already made from it don\'t change.', 'rapm' ),
				),
			)
		);
	}

	/** The Brand kit tab's markup; rapm-brand-kit.js fills it in. */
	public static function render() {
		?>
		<div class="rapm-kit" id="rapm-kit">
			<script type="application/json" id="rapm-kit-data"><?php echo wp_json_encode( self::for_browser(), JSON_HEX_TAG | JSON_HEX_AMP ); ?></script>
			<p class="rapm-kit-status" id="rapm-kit-status" aria-live="polite"></p>
			<div class="rapm-kit-grid">
				<section class="rapm-kit-panel">
					<h2><?php esc_html_e( 'Colors', 'rapm' ); ?></h2>
					<h3><?php esc_html_e( 'From your website (Elementor)', 'rapm' ); ?></h3>
					<div class="rapm-kit-swatches" id="rapm-kit-site"></div>
					<p class="description"><?php esc_html_e( 'Linked: change one in Elementor\'s Site Settings and the designs using it follow. They\'re changed there, not here.', 'rapm' ); ?></p>
					<h3><?php esc_html_e( 'Saved colors', 'rapm' ); ?></h3>
					<div class="rapm-kit-swatches" id="rapm-kit-colors"></div>
					<p class="rapm-kit-add">
						<input type="color" id="rapm-kit-newhex" value="#2f6b4f" aria-label="<?php esc_attr_e( 'New color', 'rapm' ); ?>" />
						<input type="text" id="rapm-kit-newname" maxlength="40" placeholder="<?php esc_attr_e( 'Name, like Clearance red', 'rapm' ); ?>" aria-label="<?php esc_attr_e( 'Color name', 'rapm' ); ?>" />
						<button type="button" class="button" id="rapm-kit-addcolor"><?php esc_html_e( 'Add color', 'rapm' ); ?></button>
					</p>
				</section>
				<section class="rapm-kit-panel">
					<h2><?php esc_html_e( 'Pick colors from a picture', 'rapm' ); ?></h2>
					<p class="description"><?php esc_html_e( 'Choose any picture from the Media Library. Five colors are worked out right here in your browser; the picture isn\'t sent anywhere.', 'rapm' ); ?></p>
					<p><button type="button" class="button" id="rapm-kit-pickpic"><?php esc_html_e( 'Choose a picture…', 'rapm' ); ?></button></p>
					<div class="rapm-kit-picker" id="rapm-kit-picker" hidden>
						<canvas id="rapm-kit-canvas" width="240" height="180"></canvas>
						<div>
							<p class="rapm-kit-moods" id="rapm-kit-moods"></p>
							<div class="rapm-kit-swatches" id="rapm-kit-picked"></div>
							<p><button type="button" class="button" id="rapm-kit-savepicked"><?php esc_html_e( 'Save these 5 to Saved colors', 'rapm' ); ?></button></p>
						</div>
					</div>
				</section>
				<section class="rapm-kit-panel">
					<h2><?php esc_html_e( 'Text styles', 'rapm' ); ?> <button type="button" class="button button-small" id="rapm-kit-addstyle"><?php esc_html_e( 'New style', 'rapm' ); ?></button></h2>
					<p class="description"><?php esc_html_e( 'A font, weight, size, color and letter case saved together. In a design, pick one for any headline, smaller line or button; it\'s copied onto that piece, so changing the style later never changes a live promotion.', 'rapm' ); ?></p>
					<div id="rapm-kit-styles"></div>
				</section>
				<section class="rapm-kit-panel">
					<h2><?php esc_html_e( 'Logos and badges', 'rapm' ); ?> <button type="button" class="button button-small" id="rapm-kit-addlogos"><?php esc_html_e( 'Add from Media Library…', 'rapm' ); ?></button></h2>
					<p class="description"><?php esc_html_e( 'Shown first under "Your pictures" in the canvas. A PNG or SVG with a see-through background looks best.', 'rapm' ); ?></p>
					<div class="rapm-kit-logos" id="rapm-kit-logos"></div>
				</section>
				<section class="rapm-kit-panel is-wide">
					<h2><?php esc_html_e( 'Templates', 'rapm' ); ?></h2>
					<p class="description"><?php esc_html_e( 'Start a new promotion box design from one ("Start from a template" above the canvas). It\'s a copy, so changing or removing a template never changes promotions made from it.', 'rapm' ); ?></p>
					<div class="rapm-kit-tpls" id="rapm-kit-tpls"></div>
				</section>
			</div>
		</div>
		<?php
	}
}
