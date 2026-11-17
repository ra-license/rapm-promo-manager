<?php
/**
 * Coupon cards made for the shopper (1.71.0).
 *
 * Phil, Nov 17: when a shopper looks at dining tables and there's no dining
 * coupon, the Coupon row should still have something for them. Mockup
 * approved the same day (claude.ai/artifact/PwizTAyVpjnyHrH1PPHvPM).
 *
 * Safe by default (Phil's rule: the store only promises what it approved):
 *   - With nothing set up, the card is a REMINDER with no discount ("Still
 *     thinking about Dining Tables?"), so there's nothing to honor at
 *     checkout.
 *   - An offer replaces the reminder only once R&A has worked it out with
 *     the store and typed it in Settings › Coupons, with who approved it and
 *     when. Without both, the offer is kept but doesn't show. An offer can
 *     be about a product, financing, delivery or a design service, for one
 *     category (and the categories under it) or for any category (standing).
 *   - Which card a shopper gets, first match wins: a coupon someone made for
 *     that category (it already moves to the front, 1.70.0), then an
 *     approved offer for that category, then the approved standing offer,
 *     then the reminder.
 *   - At most one made card per row. First-time visitors, and anyone whose
 *     browser doesn't keep the note, see the row as before.
 *
 * How it works: on a product or category page, RAPM_Interests::record()
 * also keeps that page's categories' name, address and pictures in the
 * shopper's browser (localStorage "rapm_seen_cards", beside "rapm_seen").
 * The Coupon row carries the reminder wording, the picture choice and the
 * approved offers (data-rapm-made), and RAPM_Schedule.madeCard() builds the
 * card in the browser. Nothing is sent to the website, and every shopper
 * gets the same cached page.
 */

defined( 'ABSPATH' ) || exit;

class RAPM_Made_Cards {

	const KINDS = array( 'product', 'financing', 'delivery', 'design' );
	const PICS  = array( 'product', 'category', 'brand', 'upload' );
	const MAX_OFFERS = 50;

	public static function defaults() {
		return array(
			'made_enabled' => 0,
			'made_head'    => __( 'Still thinking about {category}?', 'rapm' ),
			'made_sub'     => __( 'See what\'s new in {category}.', 'rapm' ),
			'made_btn'     => __( 'See {category}', 'rapm' ),
			'made_pic'     => 'product',
			'made_pic_id'  => 0,
			'made_offers'  => array(),
		);
	}

	public static function init() {
		add_action( 'update_option_' . RAPM_Admin_Settings::OPTION, array( __CLASS__, 'on_settings_saved' ), 10, 2 );
	}

	/**
	 * Clear cached pages that show the change: everything when following or
	 * made cards were switched on or off (product and category pages keep
	 * the note), else just the pages with a Coupon row.
	 */
	public static function on_settings_saved( $old, $new ) {
		$old = wp_parse_args( (array) $old, self::defaults() + array( 'interests_enabled' => 0 ) );
		$new = wp_parse_args( (array) $new, self::defaults() + array( 'interests_enabled' => 0 ) );
		if ( (int) $old['made_enabled'] !== (int) $new['made_enabled'] || (int) $old['interests_enabled'] !== (int) $new['interests_enabled'] ) {
			RAPM_Cache::queue_everything();
		}
		foreach ( array_keys( self::defaults() ) as $key ) {
			if ( wp_json_encode( $old[ $key ] ) !== wp_json_encode( $new[ $key ] ) ) {
				foreach ( array_keys( RAPM_Spot_Usage::get() ) as $spot ) {
					if ( 0 === strpos( (string) $spot, 'coupon|' ) ) {
						RAPM_Cache::queue_spot( $spot );
					}
				}
				break;
			}
		}
	}

	/** Whether this website makes cards (WooCommerce and the switch). */
	public static function enabled() {
		return class_exists( 'WooCommerce' ) && (bool) RAPM_Admin_Settings::get( 'made_enabled' );
	}

	public static function kind_label( $kind ) {
		$labels = array(
			'product'   => __( 'Product', 'rapm' ),
			'financing' => __( 'Financing', 'rapm' ),
			'delivery'  => __( 'Delivery', 'rapm' ),
			'design'    => __( 'Design service', 'rapm' ),
		);
		return isset( $labels[ $kind ] ) ? $labels[ $kind ] : $labels['product'];
	}

	private static function clean( $v, $max ) {
		return mb_substr( sanitize_text_field( (string) $v ), 0, $max );
	}

	private static function clean_time( $v ) {
		$v = trim( (string) $v );
		return preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $v ) ? $v : '';
	}

	/**
	 * Settings › Coupons values into $out. The section's hidden marker says
	 * the form had them; without it (WooCommerce off for a moment, so the
	 * section wasn't shown) the saved values are kept, never wiped.
	 */
	public static function sanitize_into( $input, &$out ) {
		$saved = wp_parse_args( (array) get_option( RAPM_Admin_Settings::OPTION, array() ), self::defaults() );
		if ( empty( $input['made_form'] ) ) {
			foreach ( array_keys( self::defaults() ) as $key ) {
				$out[ $key ] = $saved[ $key ];
			}
			return;
		}
		$d = self::defaults();
		$out['made_enabled'] = isset( $input['made_enabled'] ) ? 1 : 0;
		foreach ( array( 'made_head' => 80, 'made_sub' => 140, 'made_btn' => 40 ) as $key => $max ) {
			$v           = isset( $input[ $key ] ) ? self::clean( $input[ $key ], $max ) : '';
			$out[ $key ] = '' !== $v ? $v : $d[ $key ];
		}
		$out['made_pic']    = isset( $input['made_pic'] ) && in_array( $input['made_pic'], self::PICS, true ) ? $input['made_pic'] : 'product';
		$out['made_pic_id'] = isset( $input['made_pic_id'] ) ? absint( $input['made_pic_id'] ) : 0;

		$offers  = array();
		$waiting = 0;
		foreach ( isset( $input['made_offers'] ) && is_array( $input['made_offers'] ) ? $input['made_offers'] : array() as $row ) {
			if ( ! is_array( $row ) || count( $offers ) >= self::MAX_OFFERS ) {
				continue;
			}
			$o = array(
				'for'   => isset( $row['for'] ) && preg_match( '/^c\d+$/', (string) $row['for'] ) ? (string) $row['for'] : 'any',
				'kind'  => isset( $row['kind'] ) && in_array( $row['kind'], self::KINDS, true ) ? $row['kind'] : 'product',
				'head'  => self::clean( isset( $row['head'] ) ? $row['head'] : '', 80 ),
				'sub'   => self::clean( isset( $row['sub'] ) ? $row['sub'] : '', 140 ),
				'btn'   => self::clean( isset( $row['btn'] ) ? $row['btn'] : '', 40 ),
				'start' => self::clean_time( isset( $row['start'] ) ? $row['start'] : '' ),
				'end'   => self::clean_time( isset( $row['end'] ) ? $row['end'] : '' ),
				'by'    => self::clean( isset( $row['by'] ) ? $row['by'] : '', 80 ),
				'on'    => isset( $row['on'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $row['on'] ) ? (string) $row['on'] : '',
			);
			if ( '' === $o['head'] ) {
				continue; // An empty row.
			}
			if ( '' === $o['by'] || '' === $o['on'] ) {
				$waiting++;
			}
			$offers[] = $o;
		}
		$out['made_offers'] = $offers;
		if ( $waiting ) {
			add_settings_error(
				RAPM_Admin_Settings::OPTION,
				'rapm_made_waiting',
				/* translators: %d: number of offers */
				sprintf( _n( '%d approved offer was saved but won\'t show until it says who at the store approved it and when.', '%d approved offers were saved but won\'t show until they say who at the store approved them and when.', $waiting, 'rapm' ), $waiting ),
				'warning'
			);
		}
	}

	/** Whether an offer may show: approved, and not past its end. */
	private static function offer_live( $o ) {
		if ( '' === $o['by'] || '' === $o['on'] ) {
			return false;
		}
		if ( '' !== $o['end'] ) {
			try {
				return new DateTimeImmutable( $o['end'], wp_timezone() ) > new DateTimeImmutable( 'now', wp_timezone() );
			} catch ( Exception $e ) {
				return true;
			}
		}
		return true;
	}

	/** The Coupon row's attribute: what the browser needs to make a card. */
	public static function row_attr( $placement ) {
		if ( ! self::enabled() ) {
			return '';
		}
		$s      = RAPM_Admin_Settings::get();
		$offers = array();
		foreach ( (array) $s['made_offers'] as $o ) {
			if ( self::offer_live( $o ) ) {
				$offers[] = array(
					'f'  => $o['for'],
					'k'  => $o['kind'],
					'h'  => $o['head'],
					's'  => $o['sub'],
					'b'  => $o['btn'],
					'st' => RAPM_Store_Time::attr( $o['start'] ),
					'en' => RAPM_Store_Time::attr( $o['end'] ),
				);
			}
		}
		$upload = 'upload' === $s['made_pic'] && $s['made_pic_id'] ? (string) wp_get_attachment_image_url( (int) $s['made_pic_id'], 'large' ) : '';
		$data   = array(
			'h'  => $s['made_head'],
			's'  => $s['made_sub'],
			'b'  => $s['made_btn'],
			'p'  => $s['made_pic'],
			'u'  => $upload,
			'bg' => RAPM_Elementor::resolve_accent_color_css(),
			'o'  => $offers,
			'sp' => RAPM_Spots::name( 'coupon', $placement ? $placement : 'default' ),
		);
		return ' data-rapm-made="' . esc_attr( wp_json_encode( $data ) ) . '"';
	}

	/* ------------------------------------------------------------------
	 * What the page being shown can tell the shopper's browser
	 * ---------------------------------------------------------------- */

	private static function img( $attachment_id ) {
		$attachment_id = (int) $attachment_id;
		if ( ! $attachment_id ) {
			return '';
		}
		$url = wp_get_attachment_image_url( $attachment_id, 'woocommerce_single' );
		return $url ? (string) $url : (string) wp_get_attachment_image_url( $attachment_id, 'large' );
	}

	/** The best-selling product with a photo in a category (and those under it), kept 12 hours. */
	private static function top_photo( $term_id ) {
		$key    = 'rapm_made_photo_' . (int) $term_id;
		$cached = get_transient( $key );
		if ( false !== $cached ) {
			return (string) $cached;
		}
		$q   = new WP_Query(
			array(
				'post_type'           => 'product',
				'post_status'         => 'publish',
				'posts_per_page'      => 1,
				'fields'              => 'ids',
				'no_found_rows'       => true,
				'ignore_sticky_posts' => true,
				'meta_key'            => 'total_sales', // phpcs:ignore WordPress.DB.SlowDBQuery
				'orderby'             => array( 'meta_value_num' => 'DESC', 'date' => 'DESC' ),
				'meta_query'          => array( array( 'key' => '_thumbnail_id', 'compare' => 'EXISTS' ) ), // phpcs:ignore WordPress.DB.SlowDBQuery
				'tax_query'           => array( array( 'taxonomy' => 'product_cat', 'field' => 'term_id', 'terms' => (int) $term_id, 'include_children' => true ) ), // phpcs:ignore WordPress.DB.SlowDBQuery
			)
		);
		$url = $q->posts ? self::img( get_post_thumbnail_id( (int) $q->posts[0] ) ) : '';
		set_transient( $key, $url, 12 * HOUR_IN_SECONDS );
		return $url;
	}

	/**
	 * The categories this page is directly about (not their parents), each
	 * as key => array( name, address, category picture, product photo,
	 * parent keys ). On a product page the product photo is that product's
	 * own; on a category page it's the category's best seller.
	 */
	public static function page_cards() {
		if ( ! self::enabled() ) {
			return array();
		}
		$skip     = (int) get_option( 'default_product_cat' ); // "Uncategorized".
		$photo    = '';
		$term_ids = array();
		if ( function_exists( 'is_product' ) && is_product() ) {
			$id       = (int) get_queried_object_id();
			$term_ids = wc_get_product_term_ids( $id, 'product_cat' );
			$photo    = self::img( get_post_thumbnail_id( $id ) );
		} elseif ( function_exists( 'is_product_category' ) && is_product_category() ) {
			$term_ids = array( (int) get_queried_object_id() );
		}
		$cards = array();
		foreach ( array_slice( (array) $term_ids, 0, 5 ) as $tid ) {
			$tid  = (int) $tid;
			$term = $tid && $tid !== $skip ? get_term( $tid, 'product_cat' ) : null;
			if ( ! $term || is_wp_error( $term ) ) {
				continue;
			}
			$link = get_term_link( $term );
			if ( is_wp_error( $link ) ) {
				continue;
			}
			$parents = array_map(
				function ( $p ) {
					return 'c' . (int) $p;
				},
				get_ancestors( $tid, 'product_cat', 'taxonomy' )
			);
			$cards[ 'c' . $tid ] = array(
				html_entity_decode( $term->name, ENT_QUOTES, 'UTF-8' ),
				(string) $link,
				self::img( get_term_meta( $tid, 'thumbnail_id', true ) ),
				'' !== $photo ? $photo : self::top_photo( $tid ),
				$parents,
			);
		}
		return $cards;
	}

	/* ------------------------------------------------------------------
	 * Settings › Coupons
	 * ---------------------------------------------------------------- */

	private static function category_options() {
		$terms = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false, 'orderby' => 'name' ) );
		if ( is_wp_error( $terms ) ) {
			return array();
		}
		$by_parent = array();
		foreach ( $terms as $t ) {
			$by_parent[ (int) $t->parent ][] = $t;
		}
		$out  = array();
		$walk = function ( $parent, $depth ) use ( &$walk, &$out, $by_parent ) {
			foreach ( isset( $by_parent[ $parent ] ) ? $by_parent[ $parent ] : array() as $t ) {
				$out[ 'c' . (int) $t->term_id ] = str_repeat( '— ', $depth ) . html_entity_decode( $t->name, ENT_QUOTES, 'UTF-8' );
				$walk( (int) $t->term_id, $depth + 1 );
			}
		};
		$walk( 0, 0 );
		return $out;
	}

	private static function offer_row( $i, $o, $cats ) {
		$name = RAPM_Admin_Settings::OPTION . '[made_offers][' . $i . ']';
		$o    = wp_parse_args( $o, array( 'for' => 'any', 'kind' => 'product', 'head' => '', 'sub' => '', 'btn' => '', 'start' => '', 'end' => '', 'by' => '', 'on' => '' ) );
		$wait = '' !== $o['head'] && ( '' === $o['by'] || '' === $o['on'] );
		?>
		<tr class="rapm-made-offer">
			<td>
				<select name="<?php echo esc_attr( $name ); ?>[for]" aria-label="<?php esc_attr_e( 'For', 'rapm' ); ?>" style="max-width:200px;">
					<option value="any" <?php selected( $o['for'], 'any' ); ?>><?php esc_html_e( 'Any category (standing)', 'rapm' ); ?></option>
					<?php foreach ( $cats as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $o['for'], $key ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
				<select name="<?php echo esc_attr( $name ); ?>[kind]" aria-label="<?php esc_attr_e( 'Kind of offer', 'rapm' ); ?>" style="margin-top:4px;">
					<?php foreach ( self::KINDS as $k ) : ?>
						<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $o['kind'], $k ); ?>><?php echo esc_html( self::kind_label( $k ) ); ?></option>
					<?php endforeach; ?>
				</select>
			</td>
			<td>
				<input type="text" name="<?php echo esc_attr( $name ); ?>[head]" value="<?php echo esc_attr( $o['head'] ); ?>" placeholder="<?php esc_attr_e( 'Headline, like 0% Interest for 36 Months', 'rapm' ); ?>" aria-label="<?php esc_attr_e( 'Headline', 'rapm' ); ?>" style="width:100%;" />
				<input type="text" name="<?php echo esc_attr( $name ); ?>[sub]" value="<?php echo esc_attr( $o['sub'] ); ?>" placeholder="<?php esc_attr_e( 'Small line: the fine print', 'rapm' ); ?>" aria-label="<?php esc_attr_e( 'Small line', 'rapm' ); ?>" style="width:100%;margin-top:4px;" />
				<input type="text" name="<?php echo esc_attr( $name ); ?>[btn]" value="<?php echo esc_attr( $o['btn'] ); ?>" placeholder="<?php esc_attr_e( 'Button (blank: Shop {category})', 'rapm' ); ?>" aria-label="<?php esc_attr_e( 'Button', 'rapm' ); ?>" style="width:100%;margin-top:4px;" />
			</td>
			<td>
				<label style="display:block;"><?php esc_html_e( 'Starts', 'rapm' ); ?><br /><input type="datetime-local" name="<?php echo esc_attr( $name ); ?>[start]" value="<?php echo esc_attr( $o['start'] ); ?>" /></label>
				<label style="display:block;margin-top:4px;"><?php esc_html_e( 'Ends', 'rapm' ); ?><br /><input type="datetime-local" name="<?php echo esc_attr( $name ); ?>[end]" value="<?php echo esc_attr( $o['end'] ); ?>" /></label>
			</td>
			<td>
				<input type="text" name="<?php echo esc_attr( $name ); ?>[by]" value="<?php echo esc_attr( $o['by'] ); ?>" placeholder="<?php esc_attr_e( 'Who at the store', 'rapm' ); ?>" aria-label="<?php esc_attr_e( 'Approved by', 'rapm' ); ?>" style="width:100%;" />
				<input type="date" name="<?php echo esc_attr( $name ); ?>[on]" value="<?php echo esc_attr( $o['on'] ); ?>" aria-label="<?php esc_attr_e( 'Approved on', 'rapm' ); ?>" style="margin-top:4px;" />
				<?php if ( $wait ) : ?>
					<p class="description" style="color:#b32d2e;"><?php esc_html_e( 'Not showing: add who approved it and when.', 'rapm' ); ?></p>
				<?php endif; ?>
			</td>
			<td><button type="button" class="button-link button-link-delete rapm-made-remove"><?php esc_html_e( 'Remove', 'rapm' ); ?></button></td>
		</tr>
		<?php
	}

	/** The rows under "Coupons" in Settings. */
	public static function render_settings( $opts ) {
		$o    = RAPM_Admin_Settings::OPTION;
		$cats = self::category_options();
		$pic  = (int) $opts['made_pic_id'];
		wp_enqueue_media();
		?>
		<tr>
			<th><?php esc_html_e( 'Cards made for shoppers', 'rapm' ); ?></th>
			<td>
				<input type="hidden" name="<?php echo esc_attr( $o ); ?>[made_form]" value="1" />
				<label><input type="checkbox" name="<?php echo esc_attr( $o ); ?>[made_enabled]" value="1" <?php checked( $opts['made_enabled'], 1 ); ?> /> <?php esc_html_e( 'When a shopper looked at a category that has no coupon, make a card for it', 'rapm' ); ?></label>
				<p class="description"><?php esc_html_e( 'At most one made card in a row, for the category the shopper looked at most in the last 30 days. Without an approved offer below, it\'s a reminder with no discount, so there\'s nothing for the store to honor at checkout. Everyone else sees the row as it is.', 'rapm' ); ?></p>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'The reminder card', 'rapm' ); ?></th>
			<td>
				<p class="description" style="margin-top:0;"><?php esc_html_e( '{category} is filled in with the category\'s name, like "Dining Tables".', 'rapm' ); ?></p>
				<p><label><?php esc_html_e( 'Headline', 'rapm' ); ?><br /><input type="text" class="regular-text" name="<?php echo esc_attr( $o ); ?>[made_head]" value="<?php echo esc_attr( $opts['made_head'] ); ?>" /></label></p>
				<p><label><?php esc_html_e( 'Small line', 'rapm' ); ?><br /><input type="text" class="regular-text" name="<?php echo esc_attr( $o ); ?>[made_sub]" value="<?php echo esc_attr( $opts['made_sub'] ); ?>" /></label></p>
				<p><label><?php esc_html_e( 'Button', 'rapm' ); ?><br /><input type="text" class="regular-text" name="<?php echo esc_attr( $o ); ?>[made_btn]" value="<?php echo esc_attr( $opts['made_btn'] ); ?>" /></label></p>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Picture for made cards', 'rapm' ); ?></th>
			<td>
				<fieldset>
					<label style="display:block;margin-bottom:6px;"><input type="radio" name="<?php echo esc_attr( $o ); ?>[made_pic]" value="product" <?php checked( $opts['made_pic'], 'product' ); ?> /> <?php esc_html_e( 'A product photo: the product the shopper looked at, or the category\'s best seller', 'rapm' ); ?></label>
					<label style="display:block;margin-bottom:6px;"><input type="radio" name="<?php echo esc_attr( $o ); ?>[made_pic]" value="category" <?php checked( $opts['made_pic'], 'category' ); ?> /> <?php esc_html_e( 'The category\'s own picture (Products › Categories; a product photo if it has none)', 'rapm' ); ?></label>
					<label style="display:block;margin-bottom:6px;"><input type="radio" name="<?php echo esc_attr( $o ); ?>[made_pic]" value="brand" <?php checked( $opts['made_pic'], 'brand' ); ?> /> <?php esc_html_e( 'The brand color, no photo (Brand Color above)', 'rapm' ); ?></label>
					<label style="display:block;"><input type="radio" name="<?php echo esc_attr( $o ); ?>[made_pic]" value="upload" <?php checked( $opts['made_pic'], 'upload' ); ?> /> <?php esc_html_e( 'A picture we choose', 'rapm' ); ?></label>
					<div style="margin:6px 0 0 24px;display:flex;gap:10px;align-items:center;">
						<input type="hidden" id="rapm_made_pic_id" name="<?php echo esc_attr( $o ); ?>[made_pic_id]" value="<?php echo esc_attr( $pic ); ?>" />
						<img id="rapm_made_pic_preview" src="<?php echo esc_url( $pic ? (string) wp_get_attachment_image_url( $pic, 'thumbnail' ) : '' ); ?>" alt="" style="width:60px;height:75px;object-fit:cover;border-radius:4px;<?php echo $pic ? '' : 'display:none;'; ?>" />
						<button type="button" class="button" id="rapm_made_pic_choose"><?php esc_html_e( 'Choose picture', 'rapm' ); ?></button>
					</div>
				</fieldset>
				<p class="description"><?php esc_html_e( 'If a made card has no picture to use, it shows the brand color.', 'rapm' ); ?></p>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Offers the store approved', 'rapm' ); ?></th>
			<td>
				<p class="description" style="margin-top:0;"><?php esc_html_e( 'An offer replaces the reminder card. Only add one the store agreed to, with who approved it and when: without both it\'s kept but doesn\'t show. It can be about a product, financing, delivery or a design service. One for a category also covers the categories under it. Which card a shopper gets, first match wins: a coupon someone made for that category, then an offer for that category, then a standing offer (any category), then the reminder.', 'rapm' ); ?></p>
				<table class="widefat" id="rapm-made-offers" style="max-width:1100px;margin-top:8px;">
					<thead>
						<tr>
							<th style="width:210px;"><?php esc_html_e( 'For / kind', 'rapm' ); ?></th>
							<th style="min-width:340px;"><?php esc_html_e( 'Words ({category} works here too)', 'rapm' ); ?></th>
							<th style="width:200px;"><?php esc_html_e( 'Dates (store time)', 'rapm' ); ?></th>
							<th style="width:190px;"><?php esc_html_e( 'Approved by', 'rapm' ); ?></th>
							<th style="width:60px;"><span class="screen-reader-text"><?php esc_html_e( 'Remove', 'rapm' ); ?></span></th>
						</tr>
					</thead>
					<tbody>
						<?php
						foreach ( array_values( (array) $opts['made_offers'] ) as $i => $offer ) {
							self::offer_row( $i, $offer, $cats );
						}
						?>
					</tbody>
				</table>
				<template id="rapm-made-offer-template"><?php self::offer_row( '__i__', array(), $cats ); ?></template>
				<p><button type="button" class="button" id="rapm-made-add"><?php esc_html_e( '+ Add an approved offer', 'rapm' ); ?></button></p>
				<script>
					( function () {
						var body = document.querySelector( '#rapm-made-offers tbody' );
						var tpl  = document.getElementById( 'rapm-made-offer-template' );
						document.getElementById( 'rapm-made-add' ).addEventListener( 'click', function () {
							var html = tpl.innerHTML.split( '__i__' ).join( 'n' + Date.now() );
							body.insertAdjacentHTML( 'beforeend', html );
							var rows = body.querySelectorAll( 'tr' );
							var first = rows[ rows.length - 1 ].querySelector( 'input[type="text"]' );
							if ( first ) { first.focus(); }
						} );
						body.addEventListener( 'click', function ( e ) {
							var b = e.target.closest ? e.target.closest( '.rapm-made-remove' ) : null;
							if ( b ) { b.closest( 'tr' ).remove(); }
						} );
						var choose = document.getElementById( 'rapm_made_pic_choose' ), frame = null;
						choose.addEventListener( 'click', function () {
							if ( ! window.wp || ! wp.media ) { return; }
							if ( ! frame ) {
								frame = wp.media( { title: choose.textContent, library: { type: 'image' }, multiple: false } );
								frame.on( 'select', function () {
									var a = frame.state().get( 'selection' ).first().toJSON();
									document.getElementById( 'rapm_made_pic_id' ).value = a.id;
									var img = document.getElementById( 'rapm_made_pic_preview' );
									img.src = ( a.sizes && a.sizes.thumbnail ? a.sizes.thumbnail.url : a.url );
									img.style.display = '';
									var radio = document.querySelector( 'input[name$="[made_pic]"][value="upload"]' );
									if ( radio ) { radio.checked = true; }
								} );
							}
							frame.open();
						} );
					} )();
				</script>
			</td>
		</tr>
		<?php
	}
}
