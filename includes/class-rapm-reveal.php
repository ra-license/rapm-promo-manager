<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * [rapm_reveal placement="home"] (1.53.0, from the mockup Phil approved on
 * 2026-10-20, https://claude.ai/artifact/ChybrztYZZZJLc4Pr6rN7b): two to
 * four panels side by side, each a brand color with one big word that
 * opens up to a room photo, like the hero on hartandhill.com.
 *
 * Each panel is a promotion (kind "reveal") with its own dates: color
 * (_rapm_panel_color, or a linked Elementor color _rapm_panel_cg), big word
 * (headline), short line (subhead) and link label (cta_text), which show
 * with the photo, the word's color and font, and the word across or up the
 * side (_rapm_word_dir). The panel is the link.
 *
 * - Computers: the photo opens on hover or keyboard focus; the word moves
 *   up and aside and the short line and link label appear (0.8 s).
 * - Phones and touch screens (no hover): the panels stack and each opens as
 *   it reaches the middle of the screen (rapm-schedule.js, reveal()).
 *   Hart & Hill's phones only ever show the color blocks.
 * - Less motion asked for: no fade or movement.
 *
 * The first view is color and type only, so it shows at once; photos load
 * quietly after. Which panels are live is decided in the visitor's browser
 * (rapm-schedule.js), as everywhere else, with up to four shown.
 */
class RAPM_Reveal {

	const MAX = 4;

	public static function shortcode( $atts ) {
		$atts      = shortcode_atts(
			array(
				'placement'    => 'default',
				'height'       => 560,
				'phone_height' => 320,
				'label'        => '',
			),
			$atts,
			'rapm_reveal'
		);
		$placement = sanitize_title( $atts['placement'] );
		$height    = max( 280, min( 1000, (int) $atts['height'] ) );
		$phone_h   = max( 200, min( 700, (int) $atts['phone_height'] ) );

		$query = new WP_Query(
			array(
				'post_type'      => 'rapm_asset',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'menu_order date',
				'order'          => 'ASC',
				'no_found_rows'  => true,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
					'relation' => 'AND',
					array(
						'key'   => '_rapm_placement',
						'value' => $placement,
					),
					array(
						'key'   => '_rapm_kind',
						'value' => 'reveal',
					),
				),
			)
		);
		$panels = '';
		$live   = 0;
		foreach ( $query->posts as $post ) {
			$is_live = $live < self::MAX && RAPM_Quick_Links::is_live_now( get_post_meta( $post->ID, '_rapm_starts_at', true ), get_post_meta( $post->ID, '_rapm_ends_at', true ) );
			$html    = self::render_panel( $post->ID, $is_live );
			if ( '' === $html ) {
				continue;
			}
			$panels .= $html;
			$live   += $is_live ? 1 : 0;
		}
		if ( '' === $panels ) {
			return '';
		}
		$tags  = RAPM_Assets::need( 'reveal' );
		$label = '' !== $atts['label'] ? $atts['label'] : RAPM_Spots::name( 'reveal', $placement );
		return $tags . '<div class="rapm-rv" data-rapm-reveal role="group" aria-label="' . esc_attr( $label ) . '" style="' . esc_attr( '--rv-n:' . max( 1, $live ) . ';--rv-h:' . $height . 'px;--rv-ph:' . $phone_h . 'px' ) . '"' . ( $live ? '' : ' hidden' ) . '>' . $panels . '</div>';
	}

	/** A panel's color as CSS: a linked Elementor color follows the site's (1.50.0 pattern). */
	public static function color_css( $asset_id ) {
		$hex = sanitize_hex_color( (string) get_post_meta( $asset_id, '_rapm_panel_color', true ) );
		$hex = $hex ? $hex : '#1d3557';
		$cg  = sanitize_key( (string) get_post_meta( $asset_id, '_rapm_panel_cg', true ) );
		return $cg ? 'var(--e-global-color-' . $cg . ', ' . $hex . ')' : $hex;
	}

	private static function render_panel( $asset_id, $is_live ) {
		$word = (string) get_post_meta( $asset_id, '_rapm_headline', true );
		if ( '' === trim( $word ) ) {
			return ''; // A panel is its word; without one there's nothing to show.
		}
		$line     = (string) get_post_meta( $asset_id, '_rapm_subhead', true );
		$go       = (string) get_post_meta( $asset_id, '_rapm_cta_text', true );
		$color    = get_post_meta( $asset_id, '_rapm_text_color', true ) ?: '#ffffff'; // phpcs:ignore
		$font     = RAPM_Elementor::font_family_css( get_post_meta( $asset_id, '_rapm_text_font', true ) );
		$side     = 'side' === get_post_meta( $asset_id, '_rapm_word_dir', true );
		$url      = RAPM_Destination::resolve_url( get_post_meta( $asset_id, '_rapm_destination_type', true ), get_post_meta( $asset_id, '_rapm_destination_value', true ) );
		$desk_id  = (int) get_post_meta( $asset_id, '_rapm_image_desktop_id', true );
		$phone_id = (int) get_post_meta( $asset_id, '_rapm_image_mobile_id', true );
		$desk     = $desk_id ? wp_get_attachment_image_url( $desk_id, 'full' ) : '';
		$phone    = $phone_id ? wp_get_attachment_image_url( $phone_id, 'full' ) : '';
		$tag      = $url ? 'a' : 'div';
		$name     = $word . ( $go ? ': ' . $go : ( $line ? ': ' . $line : '' ) );
		ob_start();
		?>
		<<?php echo $tag; // phpcs:ignore WordPress.Security.EscapeOutput -- 'a' or 'div'. ?> class="rapm-rv-panel<?php echo $side ? ' is-side' : ''; ?><?php echo $desk ? '' : ' no-photo'; ?><?php echo $phone ? ' has-phone' : ''; ?>"<?php echo $url ? ' href="' . esc_url( $url ) . '"' : ' tabindex="0"'; ?> aria-label="<?php echo esc_attr( $name ); ?>" data-rapm-key="<?php echo (int) $asset_id; ?>" data-rapm-start="<?php echo esc_attr( get_post_meta( $asset_id, '_rapm_starts_at', true ) ); ?>" data-rapm-end="<?php echo esc_attr( get_post_meta( $asset_id, '_rapm_ends_at', true ) ); ?>" style="<?php echo esc_attr( '--rv-color:' . self::color_css( $asset_id ) . ';color:' . $color . ';' . $font . RAPM_Focal::style_vars( $asset_id ) ); ?>"<?php echo $is_live ? '' : ' hidden'; ?>>
			<span class="rapm-rv-color" aria-hidden="true"></span>
			<?php if ( $desk ) : ?>
				<picture class="rapm-rv-photo" aria-hidden="true">
					<?php if ( $phone ) : ?>
						<source media="(max-width: 767px)" srcset="<?php echo esc_url( $phone ); ?>" />
					<?php endif; ?>
					<img src="<?php echo esc_url( $desk ); ?>" alt="" loading="lazy" decoding="async" fetchpriority="low" />
				</picture>
			<?php endif; ?>
			<span class="rapm-rv-words" aria-hidden="true">
				<span class="rapm-rv-word"><?php echo esc_html( $word ); ?></span>
				<?php if ( $line || $go ) : ?>
					<span class="rapm-rv-more">
						<?php if ( $line ) : ?><span class="rapm-rv-line"><?php echo esc_html( $line ); ?></span><?php endif; ?>
						<?php if ( $go && $url ) : ?><span class="rapm-rv-go"><?php echo esc_html( $go ); ?> <span aria-hidden="true">&rarr;</span></span><?php endif; ?>
					</span>
				<?php endif; ?>
			</span>
		</<?php echo $tag; // phpcs:ignore WordPress.Security.EscapeOutput -- 'a' or 'div'. ?>>
		<?php
		return (string) ob_get_clean();
	}
}
