<?php
/**
 * RA Monitor Client 1.1.0
 *
 * Shared by every R&A plugin, bundled into each one the same way
 * plugin-update-checker is. Once a day it sends one short check-in to
 * R&A's Plugin Monitor (help.ramarketing.com) listing the R&A plugins
 * installed on this site: version, on/off, update channel, and whether the
 * last update check actually reached GitHub.
 *
 * What it sends: the site's address and name, WordPress/WooCommerce/PHP
 * versions, and the details above. Never tokens, customer data, orders or
 * any content. Each reporting plugin's row on the Plugins screen shows when
 * the last check-in went out and has a "Check in now" link. Turn it off on
 * a site with:
 *   define( 'RA_MONITOR_DISABLE', true );
 *
 * Usage, right after a plugin builds its update checker:
 *   require_once __DIR__ . '/lib/ra-monitor-client/ra-monitor-client.php';
 *   RA_Monitor_Client::register( array(
 *       'file'      => __FILE__,
 *       'slug'      => 'my-plugin',
 *       'checker'   => $updateChecker,  // plugin-update-checker instance, or null
 *       'has_token' => (bool) $token,
 *       'channel'   => 'production',    // or 'staging'
 *   ) );
 *
 * Several R&A plugins on one site each bundle a copy; whichever loads first
 * defines the class and the rest register with it, so the public API below
 * must stay backward compatible. A breaking change needs a new class name.
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'RA_Monitor_Client' ) ) {

	class RA_Monitor_Client {

		const VERSION  = '1.1.0';
		const ENDPOINT = 'https://help.ramarketing.com/wp-json/ra-monitor/v1/checkin';

		// Must match RA_MONITOR_KEY in the RA Plugin Monitor receiver.
		const KEY = 'd4c32372cdacedaf657a9ab4b53fce90dda323e391cd1dbec0ac9279ebc17c25';

		const CRON_HOOK     = 'ra_monitor_daily_checkin';
		const ERRORS_OPTION = 'ra_monitor_update_errors';
		const SENT_OPTION   = 'ra_monitor_last_sent';

		// Every R&A product plugin, by main-file name (without .php). Used to
		// list R&A plugins on the site even when their installed version is
		// too old to include this client, or they're turned off.
		private static $known_slugs = array(
			'universal-room-planner',
			'rapm-promo-manager',
			'woo-native-dimensions',
			'ra-space-viewer',
		);

		private static $plugins = array();
		private static $booted  = false;

		public static function register( $args ) {
			if ( defined( 'RA_MONITOR_DISABLE' ) && RA_MONITOR_DISABLE ) {
				return;
			}
			$args = wp_parse_args( $args, array(
				'file'      => '',
				'slug'      => '',
				'checker'   => null,
				'has_token' => null,
				'channel'   => 'production',
			) );
			if ( ! $args['file'] || ! $args['slug'] ) {
				return;
			}
			self::$plugins[ $args['slug'] ] = $args;
			self::boot();
		}

		private static function boot() {
			if ( self::$booted ) {
				return;
			}
			self::$booted = true;

			add_action( 'puc_api_error', array( __CLASS__, 'record_update_error' ), 10, 4 );
			add_action( self::CRON_HOOK, array( __CLASS__, 'send' ) );
			add_action( 'init', array( __CLASS__, 'schedule_daily' ) );

			// Catch up within a minute or two of anything changing, instead of
			// up to a day later. Scanning installed plugins isn't free, so this
			// only happens in wp-admin and right after installs/updates, never
			// on visitors' page loads.
			add_action( 'admin_init', array( __CLASS__, 'check_for_changes' ) );
			add_action( 'upgrader_process_complete', array( __CLASS__, 'schedule_soon' ) );
			add_action( 'activated_plugin', array( __CLASS__, 'schedule_soon' ) );
			add_action( 'deactivated_plugin', array( __CLASS__, 'on_deactivated' ) );

			add_filter( 'plugin_row_meta', array( __CLASS__, 'plugin_row_meta' ), 10, 2 );
			add_action( 'admin_post_ra_monitor_checkin_now', array( __CLASS__, 'checkin_now' ) );
			add_action( 'admin_notices', array( __CLASS__, 'checkin_notice' ) );
		}

		/**
		 * Under each reporting plugin on the Plugins screen: when the last
		 * check-in went out, and a link to send one right now.
		 */
		public static function plugin_row_meta( $meta, $basename ) {
			if ( ! current_user_can( 'manage_options' ) ) {
				return $meta;
			}
			foreach ( self::$plugins as $args ) {
				if ( plugin_basename( $args['file'] ) === $basename ) {
					$url    = wp_nonce_url( admin_url( 'admin-post.php?action=ra_monitor_checkin_now' ), 'ra_monitor_checkin_now' );
					$meta[] = esc_html( self::describe_last_checkin() ) . ' <a href="' . esc_url( $url ) . '">Check in now</a>';
					break;
				}
			}
			return $meta;
		}

		public static function checkin_now() {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( 'You do not have permission to do this.' );
			}
			check_admin_referer( 'ra_monitor_checkin_now' );
			self::send( 10 );
			wp_safe_redirect( add_query_arg( 'ra_monitor_checked', 1, admin_url( 'plugins.php' ) ) );
			exit;
		}

		public static function checkin_notice() {
			if ( empty( $_GET['ra_monitor_checked'] ) || ! current_user_can( 'manage_options' ) ) {
				return;
			}
			$last = get_option( self::SENT_OPTION );
			$ok   = is_array( $last ) && 200 === (int) $last['code'];
			printf(
				'<div class="notice notice-%s is-dismissible"><p><strong>R&amp;A Plugin Monitor:</strong> %s</p></div>',
				$ok ? 'success' : 'error',
				esc_html( $ok ? 'Check-in delivered. This site’s R&A plugins are now up to date on the monitor.' : self::describe_last_checkin() )
			);
		}

		/**
		 * Plain-language summary of the last check-in, including why it
		 * failed, so a site that stops showing up can be diagnosed from the
		 * site itself.
		 */
		private static function describe_last_checkin() {
			$last = get_option( self::SENT_OPTION );
			if ( ! is_array( $last ) || empty( $last['time'] ) ) {
				return 'Hasn’t checked in with R&A’s Plugin Monitor yet.';
			}
			$ago  = human_time_diff( (int) $last['time'], time() ) . ' ago';
			$code = (int) $last['code'];
			if ( 200 === $code ) {
				return 'Last check-in with R&A’s Plugin Monitor: delivered ' . $ago . '.';
			}
			if ( 0 === $code ) {
				$why = 'couldn’t reach help.ramarketing.com' . ( ! empty( $last['error'] ) ? ' (' . $last['error'] . ')' : '' );
			} elseif ( 401 === $code ) {
				$why = 'the monitor rejected it, because this plugin’s check-in key doesn’t match the monitor’s';
			} elseif ( 404 === $code ) {
				$why = 'the RA Plugin Monitor plugin isn’t installed or turned on at help.ramarketing.com';
			} else {
				$why = 'the monitor answered with error ' . $code;
			}
			return 'Last check-in with R&A’s Plugin Monitor failed ' . $ago . ': ' . $why . '.';
		}

		public static function schedule_daily() {
			if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
				wp_schedule_event( time() + 5 * MINUTE_IN_SECONDS, 'daily', self::CRON_HOOK );
			}
		}

		public static function schedule_soon() {
			// WordPress ignores a duplicate single event within 10 minutes, so
			// repeated calls don't pile up.
			wp_schedule_single_event( time() + MINUTE_IN_SECONDS, self::CRON_HOOK );
		}

		public static function check_for_changes() {
			$last = get_option( self::SENT_OPTION );
			if ( ! is_array( $last ) || ! isset( $last['fingerprint'] ) || $last['fingerprint'] !== self::fingerprint() ) {
				self::schedule_soon();
			}
		}

		/**
		 * Reports right away when an R&A plugin is switched off: if it was
		 * the only one on this site, no code would be left running to send a
		 * later check-in, and the dashboard would only notice days later.
		 */
		public static function on_deactivated( $basename ) {
			foreach ( self::$plugins as $args ) {
				if ( plugin_basename( $args['file'] ) === $basename ) {
					self::send( 5 );
					return;
				}
			}
		}

		/**
		 * plugin-update-checker announces every failed GitHub request here.
		 * Keeps the latest one per plugin, so a check-in can say why updates
		 * are failing (e.g. "401 Bad credentials") instead of just that they are.
		 */
		public static function record_update_error( $error, $response = null, $url = null, $slug = null ) {
			if ( ! $slug || ! isset( self::$plugins[ $slug ] ) ) {
				return;
			}

			$message = is_wp_error( $error ) ? $error->get_error_message() : 'Unknown error';
			if ( is_array( $response ) && function_exists( 'wp_remote_retrieve_response_code' ) ) {
				$code = (int) wp_remote_retrieve_response_code( $response );
				$body = json_decode( wp_remote_retrieve_body( $response ), true );
				if ( $code ) {
					$message = 'GitHub said: ' . $code . ( ! empty( $body['message'] ) ? ' ' . $body['message'] : '' );
				}
			}

			$errors = get_option( self::ERRORS_OPTION, array() );
			if ( ! is_array( $errors ) ) {
				$errors = array();
			}
			$errors[ $slug ] = array(
				'time'    => time(),
				'message' => substr( wp_strip_all_tags( $message ), 0, 200 ),
			);
			update_option( self::ERRORS_OPTION, $errors, false );
		}

		public static function send( $timeout = 15 ) {
			$payload = self::build_payload();
			$body    = wp_json_encode( $payload );
			$time    = time();

			$response = wp_remote_post( apply_filters( 'ra_monitor_endpoint', self::ENDPOINT ), array(
				'timeout' => (int) $timeout > 0 ? (int) $timeout : 15,
				'headers' => array(
					'Content-Type'   => 'application/json',
					'X-RA-Timestamp' => (string) $time,
					'X-RA-Signature' => hash_hmac( 'sha256', $time . '.' . $body, self::KEY ),
				),
				'body'    => $body,
			) );

			$code = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
			update_option( self::SENT_OPTION, array(
				'time'        => $time,
				'code'        => $code,
				'error'       => is_wp_error( $response ) ? $response->get_error_message() : '',
				// Only a delivered check-in counts, so a failed one is retried
				// on the next wp-admin visit rather than waiting a day.
				'fingerprint' => 200 === $code ? self::fingerprint() : '',
			), false );
		}

		/**
		 * What the site looks like right now: which R&A plugins, which
		 * versions, which are on. A change here triggers an early check-in.
		 */
		private static function fingerprint() {
			$parts = array();
			foreach ( self::installed_plugins() as $basename => $data ) {
				$parts[] = $basename . '@' . $data['Version'] . ( self::is_active( $basename ) ? '+' : '-' );
			}
			sort( $parts );
			return md5( implode( '|', $parts ) . '|' . self::VERSION );
		}

		private static function installed_plugins() {
			if ( ! function_exists( 'get_plugins' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			$found = array();
			foreach ( get_plugins() as $basename => $data ) {
				if ( in_array( basename( $basename, '.php' ), self::$known_slugs, true ) ) {
					$found[ $basename ] = $data;
				}
			}
			return $found;
		}

		private static function is_active( $basename ) {
			if ( ! function_exists( 'is_plugin_active' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			return is_plugin_active( $basename ) || ( is_multisite() && is_plugin_active_for_network( $basename ) );
		}

		public static function build_payload() {
			$registered = array();
			foreach ( self::$plugins as $slug => $args ) {
				$registered[ plugin_basename( $args['file'] ) ] = $slug;
			}

			$plugins = array();
			foreach ( self::installed_plugins() as $basename => $data ) {
				$slug  = isset( $registered[ $basename ] ) ? $registered[ $basename ] : basename( $basename, '.php' );
				$entry = array(
					'slug'      => $slug,
					'name'      => $data['Name'],
					'version'   => $data['Version'],
					'active'    => self::is_active( $basename ),
					'reporting' => isset( $registered[ $basename ] ),
				);
				if ( $entry['reporting'] ) {
					$entry = array_merge( $entry, self::update_health( self::$plugins[ $slug ] ) );
				}
				$plugins[] = $entry;
			}

			return array(
				'site_url'       => home_url(),
				'site_name'      => get_bloginfo( 'name' ),
				'wp_version'     => get_bloginfo( 'version' ),
				'wc_version'     => defined( 'WC_VERSION' ) ? WC_VERSION : '',
				'php_version'    => PHP_VERSION,
				'client_version' => self::VERSION,
				'plugins'        => $plugins,
			);
		}

		/**
		 * Reads plugin-update-checker's own saved result from its last run.
		 * After a successful check it stores the newest version it found on
		 * GitHub (even when that's the one already installed); after a failed
		 * one it stores nothing. That's what separates "up to date" from
		 * "couldn't reach GitHub", which WordPress's Updates screen shows
		 * identically.
		 */
		private static function update_health( $args ) {
			$health = array(
				'channel'           => 'staging' === $args['channel'] ? 'staging' : 'production',
				'update_status'     => 'unknown',
				'update_message'    => '',
				'latest_available'  => '',
				'last_update_check' => 0,
			);

			$checker = $args['checker'];
			if ( ! $checker || ! method_exists( $checker, 'getUpdateState' ) ) {
				return $health;
			}
			if ( false === $args['has_token'] ) {
				$health['update_status'] = 'no_token';
				return $health;
			}

			$state      = $checker->getUpdateState();
			$last_check = (int) $state->getLastCheck();
			$update     = $state->getUpdate();

			$health['last_update_check'] = $last_check;
			if ( ! $last_check ) {
				return $health;
			}

			if ( $update && ! empty( $update->version ) ) {
				$health['update_status']    = 'ok';
				$health['latest_available'] = (string) $update->version;
				return $health;
			}

			$health['update_status'] = 'failed';
			$errors = get_option( self::ERRORS_OPTION, array() );
			$slug   = $args['slug'];
			// The library saves the check time before contacting GitHub, so an
			// error from this same check is timestamped at or after it.
			if ( isset( $errors[ $slug ]['time'] ) && $errors[ $slug ]['time'] >= $last_check - 5 ) {
				$health['update_message'] = $errors[ $slug ]['message'];
			}
			return $health;
		}
	}
}
