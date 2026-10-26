<?php
/**
 * RA Monitor Updates 1.0.3
 *
 * Lets R&A's Plugin Monitor (help.ramarketing.com) ask this site to update
 * an R&A plugin right away, instead of waiting for WordPress's twice-daily
 * automatic updates. Bundled into each R&A plugin next to the check-in
 * client (ra-monitor-client.php). It's a separate class on purpose: on a
 * site where another R&A plugin with an older check-in client loads first,
 * this still works.
 *
 * Every 15 minutes this site asks the monitor whether there's anything for
 * it (a short signed "poll"). If someone clicked "Update now" for one of
 * this site's R&A plugins, the answer says so, and this site:
 *   1. asks GitHub for the newest version on its own update channel, with
 *      its own token, exactly like "Check for updates" on the Plugins screen;
 *   2. installs it with WordPress's own automatic updater, which puts the
 *      previous version back if the new one breaks the site (WordPress 6.6+);
 *   3. tells the monitor what happened on its next poll, a minute later.
 * The monitor can only say "update this plugin". It never sends files or
 * links, so the only thing it can ever cause to be installed is a release
 * already in R&A's GitHub repo for this site's channel.
 *
 * What a poll sends: the site's address and name, and the version and update
 * channel of each R&A plugin registered here. Never tokens or customer data.
 * Turn it off on a site with define( 'RA_MONITOR_REMOTE_UPDATES', false );
 * (RA_MONITOR_DISABLE turns off this and the daily check-in).
 *
 * Usage, next to RA_Monitor_Client::register():
 *   require_once __DIR__ . '/lib/ra-monitor-client/ra-monitor-updates.php';
 *   RA_Monitor_Updates::register( array(
 *       'file'    => __FILE__,
 *       'slug'    => 'my-plugin',
 *       'checker' => $updateChecker,  // plugin-update-checker instance
 *       'channel' => 'production',    // or 'staging'
 *   ) );
 *
 * 1.0.3: a poll is signed with the site's own key once the site is
 * connected to the help desk (the ra_monitor_signing_key filter, as for the
 * daily check-in), and the monitor's answer is passed on through the
 * ra_monitor_poll_response action, so the connection hears about approval.
 *
 * Several R&A plugins on one site may each bundle a copy; whichever loads
 * first defines the class and the rest register with it, so the public API
 * below must stay backward compatible. A breaking change needs a new class
 * name.
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'RA_Monitor_Updates' ) ) {

	class RA_Monitor_Updates {

		const VERSION = '1.0.3';

		// The check-in address; polls go to /poll next to it, so the
		// monitor's "Receive this site's own check-ins here" testing switch
		// (the ra_monitor_endpoint filter) moves both together.
		const CHECKIN = 'https://help.ramarketing.com/wp-json/ra-monitor/v1/checkin';

		// Must match RA_MONITOR_KEY in the RA Plugin Monitor receiver.
		const KEY = 'd4c32372cdacedaf657a9ab4b53fce90dda323e391cd1dbec0ac9279ebc17c25';

		const POLL_HOOK      = 'ra_monitor_poll';
		const RUN_HOOK       = 'ra_monitor_run_updates';
		const INTERVAL       = 'ra_monitor_quarter_hour';
		const QUEUE_OPTION   = 'ra_monitor_update_queue';
		const RESULTS_OPTION = 'ra_monitor_update_results';
		const HANDLED_OPTION = 'ra_monitor_update_handled';
		const POLLED_OPTION  = 'ra_monitor_last_poll';

		private static $plugins = array();
		private static $booted  = false;

		public static function register( $args ) {
			if ( ( defined( 'RA_MONITOR_DISABLE' ) && RA_MONITOR_DISABLE ) || ( defined( 'RA_MONITOR_REMOTE_UPDATES' ) && ! RA_MONITOR_REMOTE_UPDATES ) ) {
				return;
			}
			$args = wp_parse_args( $args, array(
				'file'    => '',
				'slug'    => '',
				'checker' => null,
				'channel' => 'production',
			) );
			if ( ! $args['file'] || ! $args['slug'] || ! is_object( $args['checker'] ) || ! method_exists( $args['checker'], 'checkForUpdates' ) ) {
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

			add_filter( 'cron_schedules', array( __CLASS__, 'add_interval' ) );
			add_action( 'init', array( __CLASS__, 'schedule' ) );
			add_action( self::POLL_HOOK, array( __CLASS__, 'poll' ) );
			add_action( self::RUN_HOOK, array( __CLASS__, 'run_queue' ) );
			add_action( 'deactivated_plugin', array( __CLASS__, 'on_deactivated' ) );
		}

		public static function add_interval( $schedules ) {
			$schedules[ self::INTERVAL ] = array(
				'interval' => 15 * MINUTE_IN_SECONDS,
				'display'  => 'Every 15 minutes (R&A Plugin Monitor)',
			);
			return $schedules;
		}

		public static function schedule() {
			if ( ! wp_next_scheduled( self::POLL_HOOK ) ) {
				wp_schedule_event( time() + MINUTE_IN_SECONDS, self::INTERVAL, self::POLL_HOOK );
			}
		}

		/**
		 * Stops polling when the last R&A plugin using this is turned off,
		 * since nothing would be left here to act on the answer.
		 */
		public static function on_deactivated( $basename ) {
			if ( ! function_exists( 'is_plugin_active' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			foreach ( self::$plugins as $args ) {
				$own = plugin_basename( $args['file'] );
				if ( $own !== $basename && is_plugin_active( $own ) ) {
					return;
				}
			}
			wp_clear_scheduled_hook( self::POLL_HOOK );
		}

		public static function endpoint() {
			$checkin = apply_filters( 'ra_monitor_endpoint', self::CHECKIN );
			return apply_filters( 'ra_monitor_poll_endpoint', preg_replace( '#/checkin/?$#', '/poll', $checkin ) );
		}

		/**
		 * True unless this site has turned off automatic updates
		 * (DISALLOW_FILE_MODS, AUTOMATIC_UPDATER_DISABLED or the
		 * automatic_updater_disabled filter), the same test WordPress's own
		 * updater uses. Reported in each poll so the monitor can say so
		 * instead of offering a button that can't work.
		 */
		public static function can_update() {
			if ( ! wp_is_file_mod_allowed( 'automatic_updater' ) ) {
				return false;
			}
			$disabled = defined( 'AUTOMATIC_UPDATER_DISABLED' ) && AUTOMATIC_UPDATER_DISABLED;
			return ! apply_filters( 'automatic_updater_disabled', $disabled );
		}

		private static function installed_version( $args ) {
			if ( ! function_exists( 'get_plugin_data' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			$data = get_plugin_data( $args['file'], false, false );
			return isset( $data['Version'] ) ? (string) $data['Version'] : '';
		}

		/** 1.0.3: the same key choice as RA_Monitor_Client::signing_key(). */
		public static function signing_key() {
			$key = apply_filters( 'ra_monitor_signing_key', self::KEY );
			return is_string( $key ) && preg_match( '/^[0-9a-f]{64}$/', $key ) ? $key : self::KEY;
		}

		/**
		 * Asks the monitor for instructions, and reports the results of
		 * earlier ones until the monitor confirms it has them.
		 */
		public static function poll( $timeout = 10 ) {
			$plugins = array();
			foreach ( self::$plugins as $slug => $args ) {
				$plugins[] = array(
					'slug'    => $slug,
					'version' => self::installed_version( $args ),
					'channel' => 'staging' === $args['channel'] ? 'staging' : 'production',
				);
			}
			$results = get_option( self::RESULTS_OPTION, array() );
			if ( ! is_array( $results ) ) {
				$results = array();
			}

			$body = wp_json_encode( array(
				'site_url'       => home_url(),
				'site_name'      => get_bloginfo( 'name' ),
				'client_version' => self::VERSION,
				'can_update'     => self::can_update(),
				'plugins'        => $plugins,
				'results'        => array_values( $results ),
			) );
			$time     = time();
			$response = wp_remote_post( self::endpoint(), array(
				'timeout' => (int) $timeout > 0 ? (int) $timeout : 10,
				'headers' => array(
					'Content-Type'   => 'application/json',
					'X-RA-Timestamp' => (string) $time,
					'X-RA-Signature' => hash_hmac( 'sha256', $time . '.' . $body, self::signing_key() ),
				),
				'body'    => $body,
			) );

			$code = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
			update_option( self::POLLED_OPTION, array(
				'time'  => $time,
				'code'  => $code,
				'error' => is_wp_error( $response ) ? $response->get_error_message() : '',
			), false );
			if ( 200 !== $code ) {
				return;
			}
			$data = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( ! is_array( $data ) ) {
				return;
			}
			do_action( 'ra_monitor_poll_response', $data );

			// The monitor has these results now; stop sending them.
			if ( ! empty( $data['acked'] ) && is_array( $data['acked'] ) && $results ) {
				foreach ( $data['acked'] as $id ) {
					unset( $results[ (int) $id ] );
				}
				update_option( self::RESULTS_OPTION, $results, false );
			}

			if ( ! empty( $data['commands'] ) && is_array( $data['commands'] ) ) {
				self::accept( $data['commands'] );
			}

			// 1.0.1: anything still waiting runs now, new or not. Before,
			// only a fresh instruction started a run, so one whose run never
			// happened (Promo Manager 1.42.1 on staging: nothing scheduled,
			// lock free) waited until the monitor re-sent it 30 minutes later.
			self::run_waiting();
		}

		/**
		 * Runs the queue: right here when WordPress's scheduler is the one
		 * polling, which is where updates belong, or as a scheduled job
		 * started straight away when it's a click (the test button).
		 */
		private static function run_waiting() {
			$queue = get_option( self::QUEUE_OPTION, array() );
			if ( ! is_array( $queue ) || ! $queue ) {
				return;
			}
			if ( wp_doing_cron() ) {
				self::run_queue();
				return;
			}
			self::schedule_run( 0 );
			spawn_cron();
		}

		/**
		 * Clears any earlier run first: WordPress won't schedule a job if an
		 * identical one is already set within 10 minutes, and silently
		 * dropping a run is what left staging's update waiting.
		 */
		private static function schedule_run( $delay ) {
			wp_clear_scheduled_hook( self::RUN_HOOK );
			wp_schedule_single_event( time() + (int) $delay, self::RUN_HOOK );
		}

		/**
		 * Queues valid "update" instructions: only for an R&A plugin
		 * registered on this site, and each instruction only once. Anything
		 * else in the answer is ignored.
		 */
		private static function accept( $commands ) {
			$queue   = get_option( self::QUEUE_OPTION, array() );
			$handled = get_option( self::HANDLED_OPTION, array() );
			$queue   = is_array( $queue ) ? $queue : array();
			$handled = is_array( $handled ) ? $handled : array();

			foreach ( array_slice( $commands, 0, 10 ) as $command ) {
				if ( ! is_array( $command ) ) {
					continue;
				}
				$id   = isset( $command['id'] ) ? (int) $command['id'] : 0;
				$type = isset( $command['type'] ) ? (string) $command['type'] : '';
				$slug = isset( $command['slug'] ) ? sanitize_key( $command['slug'] ) : '';
				if ( $id < 1 || 'update' !== $type || ! isset( self::$plugins[ $slug ] ) || in_array( $id, $handled, true ) ) {
					continue;
				}
				$queue[ $id ] = $slug;
			}
			update_option( self::QUEUE_OPTION, $queue, false );
		}

		private static function load_upgrader() {
			if ( ! class_exists( 'WP_Automatic_Updater' ) ) {
				// The same files WordPress loads for its own automatic updates
				// (wp_maybe_auto_update()).
				include_once ABSPATH . 'wp-admin/includes/admin.php';
				require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
			}
			return class_exists( 'WP_Automatic_Updater' ) && class_exists( 'WP_Upgrader' );
		}

		public static function run_queue() {
			$queue = get_option( self::QUEUE_OPTION, array() );
			if ( ! is_array( $queue ) || ! $queue || ! self::load_upgrader() ) {
				return;
			}
			// WordPress's own updater uses this lock too, so the two never
			// update at the same time. If it's busy, try again in 2 minutes
			// (1.0.1; before, the queue waited for a fresh instruction).
			if ( ! WP_Upgrader::create_lock( 'auto_updater' ) ) {
				self::schedule_run( 2 * MINUTE_IN_SECONDS );
				return;
			}

			$results = get_option( self::RESULTS_OPTION, array() );
			$handled = get_option( self::HANDLED_OPTION, array() );
			$results = is_array( $results ) ? $results : array();
			$handled = is_array( $handled ) ? $handled : array();

			try {
				foreach ( $queue as $id => $slug ) {
					$id = (int) $id;
					// Recorded before starting, so a crash mid-update reports
					// as one instead of retrying forever.
					unset( $queue[ $id ] );
					$handled[]      = $id;
					$results[ $id ] = array(
						'id'      => $id,
						'slug'    => $slug,
						'status'  => 'failed',
						'message' => 'The update was interrupted before it finished.',
						'time'    => time(),
					);
					update_option( self::QUEUE_OPTION, $queue, false );
					update_option( self::HANDLED_OPTION, array_slice( $handled, -50 ), false );
					update_option( self::RESULTS_OPTION, $results, false );

					if ( isset( self::$plugins[ $slug ] ) ) {
						$results[ $id ] = array_merge( array( 'id' => $id, 'slug' => $slug, 'time' => time() ), self::update_one( self::$plugins[ $slug ] ) );
					}
					update_option( self::RESULTS_OPTION, $results, false );
				}
			} finally {
				WP_Upgrader::release_lock( 'auto_updater' );
			}

			// Report back in a minute instead of at the next 15-minute poll.
			wp_schedule_single_event( time() + MINUTE_IN_SECONDS, self::POLL_HOOK );
		}

		/**
		 * Checks GitHub and installs the newest version of one plugin.
		 *
		 * @return array status ("done", "current" or "failed"), from, version, message.
		 */
		private static function update_one( $args ) {
			$basename = plugin_basename( $args['file'] );
			$before   = self::installed_version( $args );
			$result   = array( 'from' => $before, 'version' => $before );

			if ( ! self::can_update() ) {
				return $result + array( 'status' => 'failed', 'message' => 'Automatic updates are turned off on this site.' );
			}

			// 1. A fresh look at GitHub, the same as "Check for updates".
			$checker = $args['checker'];
			$update  = $checker->checkForUpdates();
			if ( ! $update ) {
				$state  = method_exists( $checker, 'getUpdateState' ) ? $checker->getUpdateState() : null;
				$latest = $state ? $state->getUpdate() : null;
				if ( $latest && ! empty( $latest->version ) ) {
					return $result + array( 'status' => 'current', 'message' => 'Already the newest version on GitHub (' . $latest->version . ').' );
				}
				$why    = 'Couldn’t reach GitHub';
				$errors = method_exists( $checker, 'getLastRequestApiErrors' ) ? $checker->getLastRequestApiErrors() : array();
				if ( ! empty( $errors[0]['error'] ) && is_wp_error( $errors[0]['error'] ) ) {
					$why .= ': ' . $errors[0]['error']->get_error_message();
				}
				return $result + array( 'status' => 'failed', 'message' => substr( $why, 0, 200 ) . '.' );
			}

			// 2. WordPress's own automatic updater, with the update the
			// checker just added to WordPress's list of available updates.
			$available = get_site_transient( 'update_plugins' );
			$item      = is_object( $available ) && isset( $available->response[ $basename ] ) ? $available->response[ $basename ] : null;
			$add       = null;
			$hiders    = '';
			if ( ! $item ) {
				// 1.0.2: StarFine (2026-10-23) got "didn't list it" here while
				// its Plugins screen listed and installed the same update, so
				// something on the site hid it from the list in this context
				// only. WordPress's updater reads the list again before
				// installing, so the update the checker just got from GitHub
				// is put back on the list for this one install. The names of
				// any filters that could hide it are kept with the result.
				$hiders = self::list_filters();
				$item   = method_exists( $update, 'toWpFormat' ) ? $update->toWpFormat() : null;
				if ( ! $item || empty( $item->package ) || empty( $item->plugin ) ) {
					return $result + array( 'status' => 'failed', 'message' => substr( 'Version ' . $update->version . ' is on GitHub, but WordPress didn’t list it as an update' . ( $hiders ? ' (' . $hiders . ')' : '' ), 0, 199 ) . '.' );
				}
				$item->autoupdate = false; // As the checker does, unless a plugin allows it.
				$add = function ( $list ) use ( $basename, $item ) {
					if ( is_object( $list ) ) {
						if ( ! isset( $list->response ) || ! is_array( $list->response ) ) {
							$list->response = array();
						}
						$list->response[ $basename ] = $item;
					}
					return $list;
				};
				add_filter( 'pre_site_transient_update_plugins', $add, PHP_INT_MAX );
				add_filter( 'site_transient_update_plugins', $add, PHP_INT_MAX );
			}

			$updater = new WP_Automatic_Updater();
			try {
				$outcome = $updater->update( 'plugin', $item );
			} finally {
				if ( $add ) {
					remove_filter( 'pre_site_transient_update_plugins', $add, PHP_INT_MAX );
					remove_filter( 'site_transient_update_plugins', $add, PHP_INT_MAX );
				}
			}
			$after = self::installed_version( $args );
			$result['version'] = $after;

			if ( is_wp_error( $outcome ) ) {
				return $result + array( 'status' => 'failed', 'message' => substr( wp_strip_all_tags( $outcome->get_error_message() ), 0, 200 ) );
			}
			if ( $after && version_compare( $after, $before, '>' ) ) {
				$note = $add ? substr( 'It wasn’t on WordPress’s update list, so it was added for this update' . ( $hiders ? ' (' . $hiders . ')' : '' ), 0, 199 ) . '.' : '';
				return $result + array( 'status' => 'done', 'message' => $note );
			}
			if ( false === $outcome ) {
				return $result + array( 'status' => 'failed', 'message' => 'WordPress skipped the update. Automatic updates may not be allowed for this plugin here.' );
			}
			if ( null === $outcome ) {
				// WordPress stops before installing (a failed download or
				// unzip) without returning the error; its updater keeps it.
				$why = self::updater_messages( $updater );
				return $result + array( 'status' => 'failed', 'message' => 'The new version couldn’t be downloaded or unpacked' . ( $why ? ': ' . $why : '.' ) );
			}
			return $result + array( 'status' => 'failed', 'message' => 'The update didn’t take. WordPress kept version ' . $after . ', possibly because the new version caused an error.' );
		}

		/**
		 * 1.0.2: the filters that can take an update off WordPress's list:
		 * anything on "pre_site_transient_update_plugins", and anything on
		 * "site_transient_update_plugins" that runs after the update checker
		 * (priority 10). Names only, e.g. "Some_Class::method".
		 */
		private static function list_filters() {
			global $wp_filter;
			$names = array();
			foreach ( array( 'pre_site_transient_update_plugins' => -PHP_INT_MAX, 'site_transient_update_plugins' => 10 ) as $hook => $after ) {
				if ( empty( $wp_filter[ $hook ] ) || ! is_object( $wp_filter[ $hook ] ) ) {
					continue;
				}
				foreach ( $wp_filter[ $hook ]->callbacks as $priority => $callbacks ) {
					if ( $priority <= $after ) {
						continue;
					}
					foreach ( $callbacks as $callback ) {
						$function = $callback['function'];
						if ( is_string( $function ) ) {
							$names[] = $function;
						} elseif ( is_array( $function ) && isset( $function[0], $function[1] ) ) {
							$names[] = ( is_object( $function[0] ) ? get_class( $function[0] ) : (string) $function[0] ) . '::' . $function[1];
						} else {
							$names[] = 'a closure';
						}
					}
				}
			}
			return $names ? 'filtered by ' . implode( ', ', array_unique( $names ) ) : '';
		}

		/**
		 * The last lines WordPress's automatic updater logged for its most
		 * recent update, e.g. "Download failed. A valid URL was not provided."
		 * They're kept in a protected property, so this reads it carefully and
		 * gives up quietly if a future WordPress changes it.
		 */
		private static function updater_messages( $updater ) {
			try {
				$property = new ReflectionProperty( 'WP_Automatic_Updater', 'update_results' );
				if ( PHP_VERSION_ID < 80100 ) {
					$property->setAccessible( true ); // A no-op (and deprecated) from PHP 8.1.
				}
				$all = $property->getValue( $updater );
			} catch ( Exception $e ) {
				return '';
			}
			if ( empty( $all['plugin'] ) || ! is_array( $all['plugin'] ) ) {
				return '';
			}
			$last     = end( $all['plugin'] );
			$messages = isset( $last->messages ) && is_array( $last->messages ) ? $last->messages : array();
			$messages = array_filter( array_map( function ( $message ) {
				return html_entity_decode( wp_strip_all_tags( (string) $message ), ENT_QUOTES, 'UTF-8' );
			}, $messages ) );
			return substr( implode( ' ', array_slice( $messages, -2 ) ), 0, 180 );
		}
	}
}
