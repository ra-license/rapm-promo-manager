<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Where each spot (a Kind + Placement pair, e.g. hero|home) is actually
 * placed on the site: which pages, templates and widgets carry its
 * shortcode or this plugin's Promo Carousel widget. The Promotions screen
 * shows this as "Shows on: …", and RAPM_Cache uses it to clear just those
 * pages when a promotion changes.
 *
 * Looks in three places, because a spot can live in any of them:
 * - post_content of any published post (pages, posts, WoodMart HTML blocks,
 *   Elementor templates, reusable blocks). Elementor's Shortcode widget and,
 *   since 1.26.0, our Promo Carousel widget save the shortcode here too.
 * - _elementor_data, Elementor's own copy of a page. An Elementor page's
 *   post_content can be out of date or empty, and a Promo Carousel widget
 *   saved before 1.26.0 only exists here.
 * - Classic Text, Custom HTML and block widgets (sidebars and footers).
 *
 * The result is cached for 12 hours and dropped whenever any post or
 * widget is saved, so it's rebuilt at most once per change.
 */
class RAPM_Spot_Usage {

	const TRANSIENT = 'rapm_spot_usage';

	/** Shortcode tag => spot kind. 'calendar' isn't a spot, but is tracked the same way. */
	const TAGS = array(
		'rapm_hero'                => 'hero',
		'rapm_fold_banner'         => 'fold_banner',
		'rapm_marquee'             => 'marquee',
		'rapm_quick_links'         => 'quick_links',
		'rapm_coupon_book'         => 'coupon',
		'rapm_looks'               => 'look',
		'rapm_promotions_calendar' => 'calendar',
	);

	/**
	 * @return array spot key ('hero|home', or 'calendar') => list of
	 *   array( 'id' => post ID or 0, 'label' => plain name, 'url' => view URL or '',
	 *   'sitewide' => true when it's a template or widget rather than one page ).
	 */
	public static function get() {
		$cached = get_transient( self::TRANSIENT );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$usage = self::scan();
		set_transient( self::TRANSIENT, $usage, 12 * HOUR_IN_SECONDS );
		return $usage;
	}

	public static function for_spot( $kind, $placement ) {
		$usage = self::get();
		$key   = $kind . '|' . $placement;
		return isset( $usage[ $key ] ) ? $usage[ $key ] : array();
	}

	public static function flush() {
		delete_transient( self::TRANSIENT );
	}

	/**
	 * Any saved post (except our own promotions and revisions) or widget
	 * change can add or remove a placement.
	 */
	public static function maybe_flush_on_save( $post_id ) {
		if ( wp_is_post_revision( $post_id ) || 'rapm_asset' === get_post_type( $post_id ) ) {
			return;
		}
		self::flush();
	}

	private static function scan() {
		global $wpdb;
		$found = array();
		$like  = '%' . $wpdb->esc_like( '[rapm_' ) . '%';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_type, post_title, post_content FROM {$wpdb->posts}
				WHERE post_status = 'publish' AND post_type NOT IN ( 'revision', 'rapm_asset', 'nav_menu_item' )
				AND post_content LIKE %s",
				$like
			)
		);
		foreach ( (array) $rows as $row ) {
			foreach ( self::shortcodes_in( $row->post_content ) as $key ) {
				self::add( $found, $key, $row );
			}
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID, p.post_type, p.post_title, pm.meta_value FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				WHERE pm.meta_key = '_elementor_data' AND p.post_status = 'publish'
				AND p.post_type NOT IN ( 'revision', 'rapm_asset' ) AND pm.meta_value LIKE %s",
				'%' . $wpdb->esc_like( 'rapm' ) . '%'
			)
		);
		foreach ( (array) $rows as $row ) {
			$data = json_decode( $row->meta_value, true );
			if ( is_array( $data ) ) {
				foreach ( self::keys_in_elementor( $data ) as $key ) {
					self::add( $found, $key, $row );
				}
			}
		}

		foreach ( array( 'widget_text' => 'text', 'widget_custom_html' => 'content', 'widget_block' => 'content' ) as $option => $field ) {
			$widgets = get_option( $option );
			if ( ! is_array( $widgets ) ) {
				continue;
			}
			foreach ( $widgets as $widget ) {
				if ( ! is_array( $widget ) || empty( $widget[ $field ] ) || ! is_string( $widget[ $field ] ) ) {
					continue;
				}
				foreach ( self::shortcodes_in( $widget[ $field ] ) as $key ) {
					self::add( $found, $key, null );
				}
			}
		}

		return $found;
	}

	/** Spot keys for every one of our shortcodes in a piece of text. */
	private static function shortcodes_in( $text ) {
		$keys = array();
		if ( false === strpos( $text, '[rapm_' ) ) {
			return $keys;
		}
		preg_match_all( '/' . get_shortcode_regex( array_keys( self::TAGS ) ) . '/', $text, $matches, PREG_SET_ORDER );
		foreach ( $matches as $m ) {
			$kind = self::TAGS[ $m[2] ];
			if ( 'calendar' === $kind ) {
				$keys[] = 'calendar';
				continue;
			}
			$atts      = shortcode_parse_atts( $m[3] );
			$placement = is_array( $atts ) && ! empty( $atts['placement'] ) ? sanitize_title( $atts['placement'] ) : 'default';
			$keys[]    = $kind . '|' . $placement;
		}
		return $keys;
	}

	/**
	 * Walks Elementor's element tree: our Promo Carousel widget by its own
	 * settings, and any text anywhere in it (Shortcode widget, Text Editor,
	 * HTML widget) for shortcodes.
	 */
	private static function keys_in_elementor( $node ) {
		$keys = array();
		if ( ! is_array( $node ) ) {
			return is_string( $node ) ? self::shortcodes_in( $node ) : $keys;
		}
		if ( isset( $node['widgetType'] ) && 'rapm_hero_carousel' === $node['widgetType'] ) {
			$settings  = isset( $node['settings'] ) && is_array( $node['settings'] ) ? $node['settings'] : array();
			$kind      = isset( $settings['kind'] ) && 'fold_banner' === $settings['kind'] ? 'fold_banner' : 'hero';
			$placement = ! empty( $settings['placement'] ) ? sanitize_title( $settings['placement'] ) : 'default';
			// The Spot list (1.29.0) wins over the older Kind/Placement fields.
			if ( ! empty( $settings['spot'] ) && is_string( $settings['spot'] ) && false !== strpos( $settings['spot'], '|' ) ) {
				list( $kind, $placement ) = explode( '|', $settings['spot'], 2 );
				$kind      = 'fold_banner' === $kind ? 'fold_banner' : 'hero';
				$placement = sanitize_title( $placement );
			}
			$keys[] = $kind . '|' . $placement;
		}
		foreach ( $node as $child ) {
			$keys = array_merge( $keys, self::keys_in_elementor( $child ) );
		}
		return $keys;
	}

	private static function add( &$found, $key, $row ) {
		if ( ! isset( $found[ $key ] ) ) {
			$found[ $key ] = array();
		}
		if ( null === $row ) {
			$entry = array( 'id' => 0, 'label' => __( 'A sidebar or footer widget', 'rapm' ), 'url' => '', 'sitewide' => true );
		} else {
			$id       = (int) $row->ID;
			$label    = $row->post_title ? $row->post_title : __( '(no title)', 'rapm' );
			$sitewide = false;
			if ( (int) get_option( 'page_on_front' ) === $id ) {
				$label = __( 'Home page', 'rapm' );
			} elseif ( 'elementor_library' === $row->post_type ) {
				/* translators: %s: Elementor template name */
				$label    = sprintf( __( '%s (template)', 'rapm' ), $label );
				$sitewide = true;
			} elseif ( ! is_post_type_viewable( $row->post_type ) ) {
				/* translators: %s: name of a reusable block, HTML block, etc. */
				$label    = sprintf( __( '%s (used on other pages)', 'rapm' ), $label );
				$sitewide = true;
			}
			$entry = array(
				'id'       => $id,
				'label'    => $label,
				'url'      => $sitewide ? '' : (string) get_permalink( $id ),
				'sitewide' => $sitewide,
			);
		}
		foreach ( $found[ $key ] as $existing ) {
			if ( $existing['id'] === $entry['id'] && $existing['label'] === $entry['label'] ) {
				return;
			}
		}
		$found[ $key ][] = $entry;
	}
}
