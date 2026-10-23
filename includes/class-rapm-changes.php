<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Change log (1.60.0): records every change to promotions, spots and
 * Promo Manager's settings on this site: when, who, which thing, each
 * field from → to, and the route it came by (Promo Manager, the
 * WordPress editor, the automatic schedule, and later the help desk form,
 * "Manage promotions" and ZiFlow approvals through set_route()).
 *
 * Asked for by Phil on 2026-10-23: "a log of changes ... that documents
 * who made the changes that R&A Help desk users can see and download".
 * Decisions: R&A only on the help desk, administrators only on the site,
 * kept 2 years, CSV download. This release only records; the screens and
 * the download come next, after a mockup.
 *
 * Changes are gathered while a request runs and written once at the end,
 * one row per thing changed, so a save that writes twenty fields (or
 * writes one field twice) is one entry with each field's first "from"
 * and last "to". Bookkeeping that isn't a person's change (link-check
 * times, sync errors, edit locks) is left out.
 */
class RAPM_Changes {

	const TABLE      = 'rapm_changes';
	const DB_VERSION = '1';
	const DB_OPTION  = 'rapm_changes_db';
	const PRUNE_HOOK = 'rapm_changes_prune';
	const KEEP_DAYS  = 731; // 2 years.

	/** Options recorded, by what they are. Never the GitHub token. */
	const OPTIONS = array(
		'rapm_settings'       => 'settings',
		'rapm_update_channel' => 'settings',
		'rapm_brand_kit'      => 'brand-kit',
		'rapm_spots'          => 'spot',
	);

	/** Meta key endings written by the hourly link check and the forms' own bookkeeping. */
	const SKIP_ENDINGS = array( '_synced_at', '_sync_error', '_waiting', '_folder_seen' );
	const SKIP_KEYS    = array( '_rapm_link_check', '_rapm_from_gallery' );

	/** Things changed in this request: key => type, id, name, created, deleted, fields. */
	private static $pending = array();
	private static $route   = '';
	private static $actor   = '';
	private static $hooked  = false;

	public static function init() {
		add_action( 'plugins_loaded', array( __CLASS__, 'maybe_install' ) );
		add_action( 'init', array( __CLASS__, 'schedule' ) );
		add_action( self::PRUNE_HOOK, array( __CLASS__, 'prune' ) );

		add_action( 'wp_insert_post', array( __CLASS__, 'on_insert' ), 10, 3 );
		add_action( 'post_updated', array( __CLASS__, 'on_post_updated' ), 10, 3 );
		add_action( 'before_delete_post', array( __CLASS__, 'on_delete' ), 10, 2 );
		add_filter( 'add_post_metadata', array( __CLASS__, 'on_add_meta' ), 10, 3 );
		add_filter( 'update_post_metadata', array( __CLASS__, 'on_update_meta' ), 10, 4 );
		add_filter( 'delete_post_metadata', array( __CLASS__, 'on_delete_meta' ), 10, 3 );
		foreach ( array_keys( self::OPTIONS ) as $option ) {
			add_action( 'add_option_' . $option, array( __CLASS__, 'on_add_option' ), 10, 2 );
			add_action( 'update_option_' . $option, array( __CLASS__, 'on_update_option' ), 10, 3 );
		}
	}

	/**
	 * For changes that come from somewhere other than this site's own
	 * screens, set before saving, e.g. set_route( 'helpdesk-form', 'Kenna
	 * Walls (help desk)' ). The actor is recorded as written when nobody is
	 * signed in to WordPress.
	 */
	public static function set_route( $route, $actor = '' ) {
		self::$route = sanitize_key( $route );
		self::$actor = sanitize_text_field( $actor );
	}

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE;
	}

	/** Updates arrive from GitHub without the activation hook, so the table is checked on load. */
	public static function maybe_install() {
		if ( get_option( self::DB_OPTION ) === self::DB_VERSION ) {
			return;
		}
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table = self::table();
		dbDelta(
			"CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			created_at datetime NOT NULL,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			user_name varchar(250) NOT NULL DEFAULT '',
			user_login varchar(60) NOT NULL DEFAULT '',
			route varchar(30) NOT NULL DEFAULT '',
			object_type varchar(20) NOT NULL DEFAULT '',
			object_id bigint(20) unsigned NOT NULL DEFAULT 0,
			object_key varchar(191) NOT NULL DEFAULT '',
			object_name varchar(250) NOT NULL DEFAULT '',
			action varchar(20) NOT NULL DEFAULT '',
			details longtext NOT NULL,
			PRIMARY KEY  (id),
			KEY created_at (created_at),
			KEY object (object_type,object_id)
			) {$wpdb->get_charset_collate()};"
		);
		update_option( self::DB_OPTION, self::DB_VERSION );
	}

	public static function schedule() {
		if ( ! wp_next_scheduled( self::PRUNE_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::PRUNE_HOOK );
		}
	}

	public static function unschedule() {
		wp_clear_scheduled_hook( self::PRUNE_HOOK );
	}

	/** Entries older than 2 years are removed, a batch a day. */
	public static function prune() {
		global $wpdb;
		$table = self::table();
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE created_at < %s LIMIT 5000", gmdate( 'Y-m-d H:i:s', time() - self::KEEP_DAYS * DAY_IN_SECONDS ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/* ------------------------------------------------------------------
	 * Promotions
	 * ---------------------------------------------------------------- */

	private static function is_promotion( $post_id ) {
		return 'rapm_asset' === get_post_type( $post_id );
	}

	private static function tracked_key( $key ) {
		if ( 0 !== strpos( $key, '_rapm_' ) || in_array( $key, self::SKIP_KEYS, true ) ) {
			return false;
		}
		foreach ( self::SKIP_ENDINGS as $ending ) {
			if ( substr( $key, -strlen( $ending ) ) === $ending ) {
				return false;
			}
		}
		return true;
	}

	private static function &entry( $type, $id, $key = '' ) {
		$slot = $type . ':' . ( $id ? $id : $key );
		if ( ! isset( self::$pending[ $slot ] ) ) {
			self::$pending[ $slot ] = array(
				'type'    => $type,
				'id'      => (int) $id,
				'key'     => (string) $key,
				'created' => false,
				'removed' => false,
				'fields'  => array(),
			);
			self::hook_flush();
		}
		return self::$pending[ $slot ];
	}

	private static function hook_flush() {
		if ( ! self::$hooked ) {
			self::$hooked = true;
			add_action( 'shutdown', array( __CLASS__, 'flush' ) );
		}
	}

	/** Keeps the first "from" and the latest "to" for each field. */
	private static function note( &$entry, $field, $from, $to ) {
		if ( ! array_key_exists( $field, $entry['fields'] ) ) {
			$entry['fields'][ $field ] = array( 'from' => $from, 'to' => $to );
		} else {
			$entry['fields'][ $field ]['to'] = $to;
		}
	}

	public static function on_insert( $post_id, $post, $update ) {
		if ( $update || 'rapm_asset' !== $post->post_type || 'auto-draft' === $post->post_status ) {
			return;
		}
		$entry            = &self::entry( 'promotion', $post_id );
		$entry['created'] = true;
		self::note( $entry, 'title', '', $post->post_title );
		self::note( $entry, 'status', '', $post->post_status );
	}

	public static function on_post_updated( $post_id, $after, $before ) {
		if ( 'rapm_asset' !== $after->post_type ) {
			return;
		}
		$entry = &self::entry( 'promotion', $post_id );
		foreach ( array( 'post_title' => 'title', 'post_status' => 'status', 'menu_order' => 'order' ) as $prop => $field ) {
			if ( (string) $before->$prop !== (string) $after->$prop ) {
				self::note( $entry, $field, (string) $before->$prop, (string) $after->$prop );
			}
		}
	}

	/** Deleting for good (emptying the trash) is written straight away; the post is gone by the end. */
	public static function on_delete( $post_id, $post = null ) {
		$post = $post ? $post : get_post( $post_id );
		if ( ! $post || 'rapm_asset' !== $post->post_type || 'auto-draft' === $post->post_status ) {
			return;
		}
		unset( self::$pending[ 'promotion:' . $post_id ] );
		if ( get_post_meta( $post_id, '_rapm_from_gallery', true ) ) {
			return; // A gallery copy that was never saved.
		}
		self::write( 'promotion', $post_id, '', $post->post_title, 'deleted', array() );
	}

	public static function on_add_meta( $check, $post_id, $key ) {
		if ( self::tracked_key( $key ) && self::is_promotion( $post_id ) ) {
			$entry = &self::entry( 'promotion', $post_id );
			self::note( $entry, $key, self::meta_now( $post_id, $key ), null );
		}
		return $check;
	}

	public static function on_update_meta( $check, $post_id, $key, $value ) {
		if ( self::tracked_key( $key ) && self::is_promotion( $post_id ) ) {
			$entry = &self::entry( 'promotion', $post_id );
			self::note( $entry, $key, self::meta_now( $post_id, $key ), null );
		}
		return $check;
	}

	public static function on_delete_meta( $check, $post_id, $key ) {
		if ( '_rapm_from_gallery' === $key && self::is_promotion( $post_id ) && get_post_meta( $post_id, $key, true ) ) {
			// A gallery copy's first save: to everyone but the form, this is when it was made.
			$entry            = &self::entry( 'promotion', $post_id );
			$entry['created'] = true;
		} elseif ( self::tracked_key( $key ) && self::is_promotion( $post_id ) ) {
			$entry = &self::entry( 'promotion', $post_id );
			self::note( $entry, $key, self::meta_now( $post_id, $key ), null );
		}
		return $check;
	}

	/**
	 * The stored value right now. The meta filters run before the write,
	 * so this is the "from"; at the end of the request the same read gives
	 * the "to". Reading the result instead of the value handed to the write
	 * means a write WordPress turns down is never recorded.
	 */
	private static function meta_now( $post_id, $key ) {
		$values = get_post_meta( $post_id, $key, false );
		if ( ! $values ) {
			return '';
		}
		return self::text( 1 === count( $values ) ? $values[0] : $values, $key );
	}

	/* ------------------------------------------------------------------
	 * Spots, settings, brand kit
	 * ---------------------------------------------------------------- */

	public static function on_add_option( $option, $value ) {
		self::on_update_option( array(), $value, $option );
	}

	public static function on_update_option( $old, $new, $option ) {
		$type = isset( self::OPTIONS[ $option ] ) ? self::OPTIONS[ $option ] : '';
		if ( ! $type ) {
			return;
		}
		if ( 'spot' === $type ) {
			$old = is_array( $old ) ? $old : array();
			$new = is_array( $new ) ? $new : array();
			foreach ( array_unique( array_merge( array_keys( $old ), array_keys( $new ) ) ) as $spot ) {
				$was = isset( $old[ $spot ] ) && is_array( $old[ $spot ] ) ? $old[ $spot ] : null;
				$now = isset( $new[ $spot ] ) && is_array( $new[ $spot ] ) ? $new[ $spot ] : null;
				if ( $was === $now ) {
					continue;
				}
				$entry = &self::entry( 'spot', 0, $spot );
				if ( null === $was ) {
					$entry['created'] = true;
				} elseif ( null === $now ) {
					$entry['removed'] = true;
				}
				self::diff( $entry, (array) $was, (array) $now );
				unset( $entry );
			}
			return;
		}
		$entry = &self::entry( $type, 0, $option );
		if ( 'rapm_update_channel' === $option ) {
			self::note( $entry, 'update_channel', self::text( $old ), self::text( $new ) );
			return;
		}
		self::diff( $entry, is_array( $old ) ? $old : array(), is_array( $new ) ? $new : array() );
	}

	private static function diff( &$entry, $old, $new ) {
		foreach ( array_unique( array_merge( array_keys( $old ), array_keys( $new ) ) ) as $field ) {
			$from = isset( $old[ $field ] ) ? self::text( $old[ $field ] ) : '';
			$to   = isset( $new[ $field ] ) ? self::text( $new[ $field ] ) : '';
			if ( $from !== $to || array_key_exists( $field, $entry['fields'] ) ) {
				self::note( $entry, (string) $field, $from, $to );
			}
		}
	}

	/* ------------------------------------------------------------------
	 * Writing
	 * ---------------------------------------------------------------- */

	/** A value as short text: pictures by number and file name, long values cut. */
	private static function text( $value, $key = '' ) {
		if ( is_bool( $value ) ) {
			$value = $value ? '1' : '0';
		}
		if ( is_array( $value ) || is_object( $value ) ) {
			// An empty list is the same as nothing stored ("dots: none").
			$value = array() === (array) $value ? '' : wp_json_encode( $value );
		}
		$value = (string) $value;
		if ( $value && ctype_digit( $value ) && preg_match( '/_id$/', $key ) ) {
			$file  = get_attached_file( (int) $value );
			$value = '#' . $value . ( $file ? ' ' . wp_basename( $file ) : '' );
		}
		return strlen( $value ) > 500 ? substr( $value, 0, 497 ) . '...' : $value;
	}

	public static function flush() {
		$pending       = self::$pending;
		self::$pending = array();
		$orders        = array();

		foreach ( $pending as $entry ) {
			$name = '';
			if ( 'promotion' === $entry['type'] ) {
				$post = get_post( $entry['id'] );
				if ( ! $post || 'auto-draft' === $post->post_status ) {
					continue;
				}
				if ( get_post_meta( $entry['id'], '_rapm_from_gallery', true ) ) {
					continue; // Still an unsaved gallery copy.
				}
				$name = $post->post_title;
				// Meta "to" values are read now, after every write in the request.
				foreach ( $entry['fields'] as $field => $change ) {
					if ( null === $change['to'] ) {
						$entry['fields'][ $field ]['to'] = self::meta_now( $entry['id'], $field );
					}
				}
			} elseif ( 'spot' === $entry['type'] ) {
				$parts = explode( '|', $entry['key'] );
				$name  = class_exists( 'RAPM_Spots' ) && 2 === count( $parts ) ? RAPM_Spots::name( $parts[0], $parts[1] ) : $entry['key'];
			} else {
				$name = 'brand-kit' === $entry['type'] ? 'Brand kit' : 'Settings';
			}

			$details = array();
			foreach ( $entry['fields'] as $field => $change ) {
				if ( $change['from'] !== $change['to'] || $entry['created'] ) {
					$details[] = array(
						'field' => 0 === strpos( $field, '_rapm_' ) ? substr( $field, 6 ) : $field,
						'from'  => $change['from'],
						'to'    => $change['to'],
					);
				}
			}
			$details = array_values( array_filter( $details, function ( $d ) {
				return '' !== $d['from'] || '' !== $d['to'];
			} ) );
			if ( ! $details && ! $entry['created'] && ! $entry['removed'] ) {
				continue;
			}

			$action = 'changed';
			if ( $entry['created'] ) {
				$action = 'created';
			} elseif ( $entry['removed'] ) {
				$action = 'removed';
			} elseif ( 'promotion' === $entry['type'] ) {
				foreach ( $details as $d ) {
					if ( 'status' === $d['field'] && 'trash' === $d['to'] ) {
						$action = 'trashed';
					} elseif ( 'status' === $d['field'] && 'trash' === $d['from'] ) {
						$action = 'restored';
					}
				}
				// A drag that only moves promotions is one "reordered" entry per spot.
				if ( 1 === count( $details ) && 'order' === $details[0]['field'] ) {
					$spot              = get_post_meta( $entry['id'], '_rapm_kind', true ) . '|' . get_post_meta( $entry['id'], '_rapm_placement', true );
					$orders[ $spot ][] = array(
						'field' => $name,
						'from'  => $details[0]['from'],
						'to'    => $details[0]['to'],
					);
					continue;
				}
			}
			self::write( $entry['type'], $entry['id'], $entry['key'], $name, $action, $details );
		}

		foreach ( $orders as $spot => $moves ) {
			$parts = explode( '|', $spot );
			$name  = class_exists( 'RAPM_Spots' ) && 2 === count( $parts ) && $parts[0] ? RAPM_Spots::name( $parts[0], $parts[1] ) : $spot;
			self::write( 'spot', 0, $spot, $name, 'reordered', $moves );
		}
	}

	private static function write( $type, $id, $key, $name, $action, $details ) {
		global $wpdb;
		if ( get_option( self::DB_OPTION ) !== self::DB_VERSION ) {
			return;
		}
		$user = wp_get_current_user();
		$wpdb->insert(
			self::table(),
			array(
				'created_at'  => current_time( 'mysql', true ),
				'user_id'     => (int) $user->ID,
				'user_name'   => substr( $user->ID ? $user->display_name : self::$actor, 0, 250 ),
				'user_login'  => $user->ID ? $user->user_login : '',
				'route'       => self::route(),
				'object_type' => $type,
				'object_id'   => (int) $id,
				'object_key'  => substr( (string) $key, 0, 191 ),
				'object_name' => substr( (string) $name, 0, 250 ),
				'action'      => $action,
				'details'     => (string) wp_json_encode( $details ),
			)
		);
	}

	/**
	 * How the change arrived. Promo Manager's own screens, forms and
	 * buttons all use "rapm_" actions or "rapm-" pages; its settings page
	 * saves through options.php under its own option group.
	 */
	private static function route() {
		if ( self::$route ) {
			return self::$route;
		}
		if ( wp_doing_cron() ) {
			return 'automatic';
		}
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return 'wp-cli';
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return 'rest';
		}
		// phpcs:disable WordPress.Security.NonceVerification
		$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';
		$page   = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		$group  = isset( $_POST['option_page'] ) ? sanitize_key( wp_unslash( $_POST['option_page'] ) ) : '';
		// phpcs:enable
		if ( 0 === strpos( $action, 'rapm_' ) || 0 === strpos( $page, 'rapm-' ) || 'rapm_settings' === $group ) {
			return 'promo-manager';
		}
		global $pagenow;
		if ( in_array( $pagenow, array( 'post.php', 'post-new.php', 'edit.php' ), true ) ) {
			return 'wordpress';
		}
		return is_admin() ? 'wp-admin' : 'other';
	}
}
