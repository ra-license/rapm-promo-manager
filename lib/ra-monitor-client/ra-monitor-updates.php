<?php
/**
 * RA Monitor Updates 1.1.0
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
 * 1.0.4 (Phil, Oct 27: "We need to make sure we can do automatic updates"):
 *   - "Update now" installs even when the site's "Enable auto-updates" toggle
 *     is off for the plugin. WordPress's automatic updater refuses a plugin
 *     whose toggle is off (WP_Automatic_Updater::should_update(), the
 *     auto_update_plugin filter), which is what "WordPress skipped the
 *     update" meant on Andrews, Barron's, Frazier & Son and Indian River.
 *   - R&A plugins turn their own automatic updates on (the same filter), and
 *     twice a day each one installs a newer release on its own channel by
 *     itself, the same way as "Update now". So a site updates after a
 *     production release even when WordPress's own list of updates was lost
 *     (StarFine's full object cache). Opt a site out with
 *     define( 'RA_PLUGINS_AUTO_UPDATE', false ); in wp-config.php ("Update
 *     now" still works there).
 *
 * 1.0.5: polls are signed like the check-in, with the site's own key once
 * the monitor has it (RA Monitor Client 1.5.0), and the monitor's answer
 * about that key is passed on to the client.
 *
 * 1.1.0 (Phil, Nov 18, 2026: "I need a way to rollback an update in case it
 * makes sites crash"):
 *   - Roll back. The monitor (1.12.0+) can ask for an earlier version of an
 *     R&A plugin: the site installs that version's tag from its own GitHub
 *     repo with WordPress's automatic updater, exactly like "Update now", so
 *     the check below and WordPress's own crash protection (6.6+: a plugin
 *     update that breaks the home page is undone) apply to it too. The bad
 *     version is then held back: the site doesn't install it again on its
 *     own, and updates by itself again once a newer release is out.
 *     "Update now" still installs whatever R&A asks for.
 *   - A check before installing, for every R&A plugin (Promo Manager has had
 *     its own since 1.73.0): after WordPress unpacks a new version and before
 *     it removes the old one, every PHP file is read with the site's own PHP,
 *     and the main file must be there. A broken package is refused and the
 *     installed version keeps running. Nov 16, 2026: Promo Manager 1.69.0's
 *     missing brace took staging down for 18 hours.
 *
 * Several R&A plugins on one site may each bundle a copy; whichever loads
 * first defines the class and the rest register with it, so the public API
 * below must stay backward compatible. A breaking change needs a new class
 * name.
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'RA_Monitor_Updates' ) ) {

	class RA_Monitor_Updates {

		const VERSION = '1.1.0';

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
		// 1.0.4: R&A plugins install their own updates twice a day.
		const SELF_HOOK      = 'ra_monitor_self_update';
		const SELF_OPTION    = 'ra_monitor_self_update_last';
		// 1.1.0: versions rolled back from (slug => version), and the last
		// package refused by the check before installing.
		const HOLD_OPTION    = 'ra_monitor_update_holds';
		const REFUSED_OPTION = 'ra_monitor_package_refused';

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
			// 1.0.4
			add_filter( 'auto_update_plugin', array( __CLASS__, 'allow_auto_update' ), 20, 2 );
			add_action( self::SELF_HOOK, array( __CLASS__, 'self_update' ) );
			// 1.1.0
			add_filter( 'upgrader_source_selection', array( __CLASS__, 'check_package' ), 20, 4 );
			add_action( 'admin_notices', array( __CLASS__, 'refused_notice' ) );
		}

		/** 1.0.4: off only with define( 'RA_PLUGINS_AUTO_UPDATE', false ). */
		public static function auto_updates_on() {
			return ! ( defined( 'RA_PLUGINS_AUTO_UPDATE' ) && ! RA_PLUGINS_AUTO_UPDATE );
		}

		private static function is_ours( $item ) {
			if ( ! is_object( $item ) || empty( $item->plugin ) ) {
				return false;
			}
			foreach ( self::$plugins as $args ) {
				if ( plugin_basename( $args['file'] ) === $item->plugin ) {
					return true;
				}
			}
			return false;
		}

		/** 1.0.4: R&A plugins update automatically unless the site opted out. */
		public static function allow_auto_update( $update, $item ) {
			if ( ! self::auto_updates_on() || ! self::is_ours( $item ) ) {
				return $update;
			}
			// 1.1.0: never on its own back to a version R&A rolled back from.
			$slug = self::slug_for( isset( $item->plugin ) ? $item->plugin : '' );
			if ( $slug && isset( $item->new_version ) && self::held( $slug, (string) $item->new_version ) ) {
				return false;
			}
			return true;
		}

		/* --------------------------------------------------------------
		 * 1.1.0: roll back, and the hold that keeps a bad version away
		 * ----------------------------------------------------------- */

		private static function slug_for( $basename ) {
			foreach ( self::$plugins as $slug => $args ) {
				if ( plugin_basename( $args['file'] ) === $basename ) {
					return $slug;
				}
			}
			return '';
		}

		/** Whether a version is one R&A rolled back from (or older). */
		private static function held( $slug, $version ) {
			$holds = get_option( self::HOLD_OPTION, array() );
			return is_array( $holds ) && ! empty( $holds[ $slug ] ) && '' !== $version && version_compare( $version, (string) $holds[ $slug ], '<=' );
		}

		private static function set_hold( $slug, $version ) {
			$holds = get_option( self::HOLD_OPTION, array() );
			$holds = is_array( $holds ) ? $holds : array();
			if ( '' === (string) $version ) {
				unset( $holds[ $slug ] );
			} else {
				$holds[ $slug ] = (string) $version;
			}
			update_option( self::HOLD_OPTION, $holds, false );
		}

		/**
		 * Installs an earlier version of one R&A plugin: its tag (vX.Y.Z) in
		 * the plugin's own GitHub repo on its channel, through WordPress's
		 * automatic updater, like "Update now".
		 *
		 * @return array status ("done" or "failed"), from, version, message.
		 */
		private static function rollback_one( $args, $version ) {
			$basename = plugin_basename( $args['file'] );
			$before   = self::installed_version( $args );
			$result   = array( 'from' => $before, 'version' => $before );
			if ( ! self::can_update() ) {
				return $result + array( 'status' => 'failed', 'message' => 'Automatic updates are turned off on this site.' );
			}
			if ( version_compare( $version, $before, '>=' ) ) {
				return $result + array( 'status' => 'failed', 'message' => 'Version ' . $version . ' isn’t older than the installed ' . $before . '.' );
			}
			$checker = $args['checker'];
			$api     = method_exists( $checker, 'getVcsApi' ) ? $checker->getVcsApi() : null;
			if ( ! $api || ! method_exists( $api, 'buildArchiveDownloadUrl' ) ) {
				return $result + array( 'status' => 'failed', 'message' => 'This plugin’s updater can’t install a chosen version.' );
			}
			$item = (object) array(
				'id'          => $basename,
				'slug'        => dirname( $basename ),
				'plugin'      => $basename,
				'new_version' => $version,
				'url'         => '',
				'package'     => $api->buildArchiveDownloadUrl( 'v' . ltrim( $version, 'vV' ) ),
				'autoupdate'  => true,
			);
			// WordPress's updater reads the package from its list of
			// updates, so the earlier version is put on it for this install.
			$add = function ( $list ) use ( $basename, $item ) {
				if ( is_object( $list ) ) {
					if ( ! isset( $list->response ) || ! is_array( $list->response ) ) {
						$list->response = array();
					}
					$list->response[ $basename ] = $item;
				}
				return $list;
			};
			$allow = function ( $update, $offer ) use ( $basename ) {
				return is_object( $offer ) && isset( $offer->plugin ) && $offer->plugin === $basename ? true : $update;
			};
			add_filter( 'pre_site_transient_update_plugins', $add, PHP_INT_MAX );
			add_filter( 'site_transient_update_plugins', $add, PHP_INT_MAX );
			add_filter( 'auto_update_plugin', $allow, PHP_INT_MAX, 2 );
			$updater = new WP_Automatic_Updater();
			try {
				$outcome = $updater->update( 'plugin', $item );
			} finally {
				remove_filter( 'auto_update_plugin', $allow, PHP_INT_MAX );
				remove_filter( 'pre_site_transient_update_plugins', $add, PHP_INT_MAX );
				remove_filter( 'site_transient_update_plugins', $add, PHP_INT_MAX );
			}
			$after             = self::installed_version( $args );
			$result['version'] = $after;
			if ( $after && version_compare( $after, $version, '==' ) ) {
				self::set_hold( self::slug_for( $basename ), $before );
				return $result + array( 'status' => 'done', 'message' => 'Rolled back from ' . $before . '. Version ' . $before . ' won’t install itself again; updates resume with the next newer release.' );
			}
			if ( is_wp_error( $outcome ) ) {
				return $result + array( 'status' => 'failed', 'message' => substr( wp_strip_all_tags( $outcome->get_error_message() ), 0, 200 ) );
			}
			$why = self::updater_messages( $updater );
			return $result + array( 'status' => 'failed', 'message' => substr( 'Version ' . $version . ' couldn’t be installed' . ( $why ? ': ' . $why : ' (is there a v' . ltrim( $version, 'vV' ) . ' tag on GitHub?)' ), 0, 199 ) . '.' );
		}

		/* --------------------------------------------------------------
		 * 1.1.0: the check before installing, for every R&A plugin
		 * ----------------------------------------------------------- */

		/** The registered R&A plugin a folder about to be installed is, or null. */
		private static function package_plugin( $source, $hook_extra ) {
			foreach ( self::$plugins as $slug => $args ) {
				$basename = plugin_basename( $args['file'] );
				if ( isset( $hook_extra['plugin'] ) && $hook_extra['plugin'] === $basename ) {
					return $args;
				}
				// A zip uploaded on Plugins › Add New: the same main file name.
				if ( empty( $hook_extra['plugin'] ) && is_readable( trailingslashit( $source ) . basename( $args['file'] ) ) ) {
					return $args;
				}
			}
			return null;
		}

		/** Problems in a plugin folder, each "file line N: message". At most 5. */
		public static function package_problems( $dir, $main ) {
			$dir = trailingslashit( $dir );
			if ( ! is_readable( $dir . $main ) ) {
				return array( $main . ' is missing' );
			}
			if ( ! function_exists( 'token_get_all' ) || ! defined( 'TOKEN_PARSE' ) ) {
				return array(); // No tokenizer on this server: it can't check, which isn't a reason to refuse.
			}
			$problems = array();
			$files    = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ) );
			foreach ( $files as $file ) {
				if ( count( $problems ) >= 5 ) {
					break;
				}
				if ( 'php' !== strtolower( $file->getExtension() ) ) {
					continue;
				}
				$path = $file->getPathname();
				try {
					token_get_all( (string) file_get_contents( $path ), TOKEN_PARSE ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a local file in the unpacked package.
				} catch ( \Throwable $e ) {
					$problems[] = ltrim( str_replace( '\\', '/', substr( $path, strlen( $dir ) ) ), '/' ) . ' line ' . (int) $e->getLine() . ': ' . $e->getMessage();
				}
			}
			return $problems;
		}

		/**
		 * upgrader_source_selection: after WordPress unpacks a new version
		 * and before it removes the old one. A WP_Error stops the install.
		 * Promo Manager checks its own package (RAPM_Safety), so it's left
		 * to that.
		 */
		public static function check_package( $source, $remote_source = '', $upgrader = null, $hook_extra = array() ) {
			if ( is_wp_error( $source ) || ! is_string( $source ) ) {
				return $source;
			}
			$args = self::package_plugin( $source, (array) $hook_extra );
			if ( ! $args || ( 'rapm-promo-manager' === $args['slug'] && class_exists( 'RAPM_Safety' ) ) ) {
				return $source;
			}
			$main     = basename( $args['file'] );
			$problems = self::package_problems( $source, $main );
			if ( ! $problems ) {
				return $source;
			}
			$data    = get_file_data( trailingslashit( $source ) . $main, array( 'Name' => 'Plugin Name', 'Version' => 'Version' ) );
			$name    = ! empty( $data['Name'] ) ? $data['Name'] : $args['slug'];
			$version = ! empty( $data['Version'] ) ? $data['Version'] : '?';
			$kept    = self::installed_version( $args );
			update_option( self::REFUSED_OPTION, array( 'name' => $name, 'version' => $version, 'kept' => $kept, 'problems' => $problems, 'time' => time() ), false );
			return new WP_Error( 'ra_package_broken', sprintf( '%1$s %2$s was not installed because a file in it is broken (%3$s). Version %4$s keeps running. Tell R&A Marketing.', $name, $version, implode( '; ', $problems ), $kept ) );
		}

		public static function refused_notice() {
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}
			$refused = get_option( self::REFUSED_OPTION, false );
			if ( ! is_array( $refused ) ) {
				return;
			}
			if ( empty( $refused['time'] ) || time() - (int) $refused['time'] > 14 * DAY_IN_SECONDS ) {
				delete_option( self::REFUSED_OPTION );
				return;
			}
			printf(
				'<div class="notice notice-warning"><p><strong>%s</strong> %s</p></div>',
				esc_html( sprintf( 'An update to %s was stopped.', $refused['name'] ) ),
				esc_html( sprintf( 'Version %1$s wasn’t installed because a file in it is broken (%2$s). Version %3$s keeps running, so nothing changed for visitors. R&A will release a fixed version.', $refused['version'], implode( '; ', (array) $refused['problems'] ), $refused['kept'] ) )
			);
		}

		/**
		 * 1.0.4: twice a day, each R&A plugin with a newer release on its
		 * own channel (as plugin-update-checker last saw on GitHub) installs
		 * it the same way as "Update now". The result is kept in SELF_OPTION.
		 */
		public static function self_update() {
			if ( ! self::auto_updates_on() || ! self::can_update() || ! self::load_upgrader() ) {
				return;
			}
			$due = array();
			foreach ( self::$plugins as $slug => $args ) {
				$update = method_exists( $args['checker'], 'getUpdate' ) ? $args['checker']->getUpdate() : null;
				if ( $update && ! empty( $update->version ) && version_compare( $update->version, (string) self::installed_version( $args ), '>' ) && ! self::held( $slug, (string) $update->version ) ) {
					$due[ $slug ] = $args;
				}
			}
			if ( ! $due || ! WP_Upgrader::create_lock( 'auto_updater' ) ) {
				return;
			}
			$done = get_option( self::SELF_OPTION, array() );
			$done = is_array( $done ) ? $done : array();
			try {
				foreach ( $due as $slug => $args ) {
					$done[ $slug ] = array_merge( array( 'time' => time() ), self::update_one( $args ) );
					update_option( self::SELF_OPTION, $done, false );
				}
			} finally {
				WP_Upgrader::release_lock( 'auto_updater' );
			}
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
			if ( ! wp_next_scheduled( self::SELF_HOOK ) ) {
				wp_schedule_event( time() + 10 * MINUTE_IN_SECONDS, 'twicedaily', self::SELF_HOOK );
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
			wp_clear_scheduled_hook( self::SELF_HOOK );
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

		/**
		 * 1.0.3: the same key choice as RA_Monitor_Client::signing_key().
		 * 1.0.5: asks RA_Monitor_Client 1.5.0+ itself, so polls are signed
		 * with the site's own key too.
		 */
		public static function signing_key() {
			if ( class_exists( 'RA_Monitor_Client' ) && method_exists( 'RA_Monitor_Client', 'own_key' ) ) {
				return RA_Monitor_Client::signing_key();
			}
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
				'can_rollback'   => true, // 1.1.0
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
			// 1.0.5: what the monitor says about the site's own key.
			if ( ! is_wp_error( $response ) && class_exists( 'RA_Monitor_Client' ) && method_exists( 'RA_Monitor_Client', 'hear_own_key' ) ) {
				RA_Monitor_Client::hear_own_key( json_decode( wp_remote_retrieve_body( $response ), true ), $code );
			}
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
				if ( $id < 1 || ! in_array( $type, array( 'update', 'rollback' ), true ) || ! isset( self::$plugins[ $slug ] ) || in_array( $id, $handled, true ) ) {
					continue;
				}
				if ( 'rollback' === $type ) {
					// 1.1.0: an earlier version, named by the monitor.
					$version = isset( $command['version'] ) ? (string) $command['version'] : '';
					if ( ! preg_match( '/^[0-9][0-9A-Za-z.\-+]{0,31}$/', $version ) ) {
						continue;
					}
					$queue[ $id ] = array( 'slug' => $slug, 'rollback' => $version );
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
				foreach ( $queue as $id => $entry ) {
					$id       = (int) $id;
					$slug     = is_array( $entry ) && isset( $entry['slug'] ) ? (string) $entry['slug'] : (string) $entry;
					$rollback = is_array( $entry ) && isset( $entry['rollback'] ) ? (string) $entry['rollback'] : '';
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
						$outcome        = '' !== $rollback ? self::rollback_one( self::$plugins[ $slug ], $rollback ) : self::update_one( self::$plugins[ $slug ] );
						$results[ $id ] = array_merge( array( 'id' => $id, 'slug' => $slug, 'time' => time() ), $outcome );
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

			// 1.0.4: a requested update installs even when the site's
			// "Enable auto-updates" toggle is off for this plugin; WordPress's
			// updater refuses it otherwise ("WordPress skipped the update").
			$allow = function ( $update, $offer ) use ( $basename ) {
				return is_object( $offer ) && isset( $offer->plugin ) && $offer->plugin === $basename ? true : $update;
			};
			add_filter( 'auto_update_plugin', $allow, PHP_INT_MAX, 2 );
			$updater = new WP_Automatic_Updater();
			try {
				$outcome = $updater->update( 'plugin', $item );
			} finally {
				remove_filter( 'auto_update_plugin', $allow, PHP_INT_MAX );
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
				if ( ! self::held( self::slug_for( $basename ), $after ) ) {
					self::set_hold( self::slug_for( $basename ), '' ); // 1.1.0: past the version rolled back from.
				}
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
