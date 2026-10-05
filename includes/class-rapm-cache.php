<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Clears the page cache for just the pages a promotion shows on, whenever
 * one is switched on or off, saved, reordered, trashed, or gets a new
 * picture from its link (1.28.0).
 *
 * Why it's needed: promotions are a private post type, so caching plugins
 * don't know which public pages display them. Checked in each plugin's
 * source: WP Rocket, WP Super Cache, SiteGround, Cloudflare and Kinsta all
 * skip non-public post types; W3 Total Cache only clears the post itself
 * and the blog home. The start/end schedule never needed this (it's
 * decided in the visitor's browser, rapm-schedule.js), but the on/off
 * switch and edits do.
 *
 * Rocket.net (the staging host) publishes no developer API, but its own
 * must-use plugin already clears its CDN when a promotion is saved
 * (measured on staging, 2026-10-05: home, Promotions and About Us went from
 * HIT to MISS right after a save). So every change here also goes through
 * a normal WordPress save.
 *
 * Every call below was checked against the plugin's own source
 * (WP Rocket 3.23.5.1, LiteSpeed Cache 7.9.1, W3 Total Cache 2.10.7,
 * WP Super Cache 3.1.4, SiteGround Speed Optimizer 7.8.3, Nginx Helper
 * 2.4.1) or the host's docs (WP Engine). Each runs only if that plugin is
 * active. Changes are collected during the request and cleared once, at
 * the end, after everything is saved.
 */
class RAPM_Cache {

	/** Promotions changed during this request. Their spot is looked up at the end: a new one gets its spot saved after WordPress's first save fires. */
	private static $ids = array();

	/** Spot keys ('hero|home') already known, for promotions deleted for good (their details are gone by the end). */
	private static $keys = array();

	private static $hooked = false;

	public static function queue_asset( $post_id ) {
		if ( 'rapm_asset' !== get_post_type( $post_id ) ) {
			return;
		}
		self::$ids[ (int) $post_id ] = true;
		self::hook();
	}

	/** On, off, trash, back from the trash. */
	public static function on_transition( $new_status, $old_status, $post ) {
		if ( $new_status !== $old_status && $post instanceof WP_Post && 'rapm_asset' === $post->post_type ) {
			self::queue_asset( $post->ID );
		}
	}

	public static function on_delete( $post_id ) {
		if ( 'rapm_asset' !== get_post_type( $post_id ) ) {
			return;
		}
		self::$keys[ self::spot_key( $post_id ) ] = true;
		self::hook();
	}

	private static function hook() {
		if ( ! self::$hooked ) {
			self::$hooked = true;
			add_action( 'shutdown', array( __CLASS__, 'flush_queue' ) );
		}
	}

	private static function spot_key( $post_id ) {
		$kind      = get_post_meta( $post_id, '_rapm_kind', true ) ?: 'hero'; // phpcs:ignore
		$placement = get_post_meta( $post_id, '_rapm_placement', true ) ?: 'default'; // phpcs:ignore
		return $kind . '|' . $placement;
	}

	public static function flush_queue() {
		$keys = self::$keys;
		foreach ( array_keys( self::$ids ) as $id ) {
			if ( get_post( $id ) ) {
				$keys[ self::spot_key( $id ) ] = true;
			}
		}
		self::$ids  = array();
		self::$keys = array();
		$spots      = array_keys( $keys );
		if ( ! $spots ) {
			return;
		}

		$usage = RAPM_Spot_Usage::get();
		$lists = array();
		foreach ( $spots as $key ) {
			$lists[] = isset( $usage[ $key ] ) ? $usage[ $key ] : array();
		}
		// The Promotions Calendar lists dated promotions from every spot.
		$lists[] = isset( $usage['calendar'] ) ? $usage['calendar'] : array();

		$ids      = array();
		$urls     = array();
		$sitewide = false;
		foreach ( $lists as $list ) {
			foreach ( $list as $use ) {
				if ( ! empty( $use['sitewide'] ) ) {
					$sitewide = true; // A template or widget: no single page to clear.
					continue;
				}
				if ( ! empty( $use['id'] ) ) {
					$ids[ (int) $use['id'] ] = true;
				}
				if ( ! empty( $use['url'] ) ) {
					$urls[ $use['url'] ] = true;
				}
			}
		}
		$ids  = array_keys( $ids );
		$urls = array_keys( $urls );

		if ( $sitewide ) {
			self::clear_everything();
		} elseif ( $ids || $urls ) {
			self::clear_pages( $ids, $urls );
		}

		/**
		 * After Promo Manager clears the cache for changed promotions. For a
		 * host or cache it doesn't know yet.
		 *
		 * @param int[]    $ids      Page IDs that show the changed promotions.
		 * @param string[] $urls     Their addresses.
		 * @param bool     $sitewide True when one sits in a template or widget,
		 *                           so the whole site was cleared instead.
		 */
		do_action( 'rapm_cache_cleared', $ids, $urls, $sitewide );
	}

	private static function clear_pages( $ids, $urls ) {
		foreach ( $ids as $id ) {
			if ( function_exists( 'rocket_clean_post' ) ) {
				rocket_clean_post( $id );
			}
			if ( defined( 'LSCWP_V' ) ) {
				do_action( 'litespeed_purge_post', $id );
			}
			if ( function_exists( 'w3tc_flush_post' ) ) {
				w3tc_flush_post( $id );
			}
			if ( function_exists( 'wpsc_delete_post_cache' ) ) {
				wpsc_delete_post_cache( $id );
			}
			if ( class_exists( 'WpeCommon' ) && method_exists( 'WpeCommon', 'purge_varnish_cache' ) ) {
				WpeCommon::purge_varnish_cache( $id );
			}
		}
		foreach ( $urls as $url ) {
			if ( function_exists( 'sg_cachepress_purge_cache' ) ) {
				sg_cachepress_purge_cache( $url );
			}
			if ( isset( $GLOBALS['nginx_purger'] ) && is_object( $GLOBALS['nginx_purger'] ) && method_exists( $GLOBALS['nginx_purger'], 'purge_url' ) ) {
				$GLOBALS['nginx_purger']->purge_url( $url );
			}
		}
	}

	private static function clear_everything() {
		if ( function_exists( 'rocket_clean_domain' ) ) {
			rocket_clean_domain();
		}
		if ( defined( 'LSCWP_V' ) ) {
			do_action( 'litespeed_purge_all' );
		}
		if ( function_exists( 'w3tc_flush_all' ) ) {
			w3tc_flush_all();
		}
		if ( function_exists( 'wp_cache_clear_cache' ) ) {
			wp_cache_clear_cache();
		}
		if ( function_exists( 'sg_cachepress_purge_everything' ) ) {
			sg_cachepress_purge_everything();
		}
		if ( isset( $GLOBALS['nginx_purger'] ) ) {
			do_action( 'rt_nginx_helper_purge_all' );
		}
		if ( class_exists( 'WpeCommon' ) && method_exists( 'WpeCommon', 'purge_varnish_cache' ) ) {
			WpeCommon::purge_varnish_cache();
		}
	}
}
