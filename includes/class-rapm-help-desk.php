<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * "R&A Help Desk" in Promo Manager's Settings (1.65.0, from the mockup Phil
 * approved on 2026-10-26, https://claude.ai/artifact/3EuNFCm8L1fTiqN5G1Kc8U).
 *
 * Connects this website to R&A's help desk (the Plugin Monitor 1.4.0 at
 * help.ramarketing.com) with a key of its own. Until 1.65.0 every copy of
 * every R&A plugin signed its check-ins with one shared key, so anyone with
 * the plugin could pretend to be any website. Connecting:
 *   1. An administrator clicks Connect. This website makes its own random
 *      key and sends it with a request id to the monitor's /connect,
 *      signed with the shared key (all that proves is "an R&A plugin").
 *   2. The monitor calls back this website at its own address
 *      (rapm/v1/hd-confirm) with the request id and a random challenge.
 *      Only this website can answer with the challenge signed by the key,
 *      so the request really came from here.
 *   3. R&A approves it on the monitor. This website hears so in the answer
 *      to its next poll or check-in (every 15 minutes).
 * From step 1 on, check-ins and polls are signed with the website's own key;
 * once approved, the monitor refuses ones signed with the shared key.
 *
 * Once connected, the daily check-in also carries:
 *   - spots: each spot's name, kind, picture sizes and published pages;
 *   - caching: which caching plugins are on, and WP Rocket's main switches;
 *   - pages: the public pages with a promotion, for the visitor check.
 * And when a page is saved in Elementor, its address is sent, so the
 * monitor can load it as a visitor 10 minutes later. Never passwords, keys,
 * customers, orders, form entries or page content.
 *
 * Stored in one option, OPTION:
 *   status       'pending' or 'connected' (no option = not connected)
 *   key          this website's own key (64 hex characters)
 *   request      the id of the connect request
 *   asked_at     when Connect was clicked; asked_by the user id
 *   approved_at  when this website heard it was approved
 * Settings' box is administrators only, like the rest of Settings.
 */
class RAPM_Help_Desk {

	const OPTION = 'rapm_help_desk';
	// The newest shared monitor files that know the site key (1.65.0).
	const NEEDS_CLIENT  = '1.2.0';
	const NEEDS_UPDATES = '1.0.3';
	// What the monitor's answer last said, for the box (status, time, message).
	const NOTE_OPTION = 'rapm_help_desk_note';

	public static function init() {
		add_filter( 'ra_monitor_signing_key', array( __CLASS__, 'signing_key' ) );
		add_filter( 'ra_monitor_checkin_payload', array( __CLASS__, 'add_to_checkin' ) );
		add_action( 'ra_monitor_checkin_response', array( __CLASS__, 'hear' ) );
		add_action( 'ra_monitor_poll_response', array( __CLASS__, 'hear' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_action( 'admin_post_rapm_help_desk', array( __CLASS__, 'handle' ) );
		add_action( 'elementor/editor/after_save', array( __CLASS__, 'page_saved' ) );
	}

	public static function get() {
		$saved = get_option( self::OPTION );
		if ( ! is_array( $saved ) || empty( $saved['key'] ) || ! in_array( isset( $saved['status'] ) ? $saved['status'] : '', array( 'pending', 'connected' ), true ) ) {
			return array( 'status' => 'none' );
		}
		return $saved;
	}

	public static function status() {
		$c = self::get();
		return $c['status'];
	}

	private static function forget( $note = '' ) {
		delete_option( self::OPTION );
		if ( $note ) {
			update_option( self::NOTE_OPTION, array( 'time' => time(), 'message' => $note ), false );
		}
	}

	/** The monitor's address for one of its routes, following the same testing switch as the check-in. */
	private static function monitor_url( $route ) {
		$base = class_exists( 'RA_Monitor_Client' ) ? RA_Monitor_Client::ENDPOINT : 'https://help.ramarketing.com/wp-json/ra-monitor/v1/checkin';
		$checkin = apply_filters( 'ra_monitor_endpoint', $base );
		return preg_replace( '#/checkin/?$#', '/' . $route, $checkin );
	}

	/** Signs and sends one request to the monitor. Returns array( code, decoded answer or null, error ). */
	private static function send( $route, $data, $key, $blocking = true ) {
		$body     = wp_json_encode( $data );
		$time     = time();
		$response = wp_remote_post( self::monitor_url( $route ), array(
			'timeout'  => $blocking ? 15 : 3,
			'blocking' => $blocking,
			'headers'  => array(
				'Content-Type'   => 'application/json',
				'X-RA-Timestamp' => (string) $time,
				'X-RA-Signature' => hash_hmac( 'sha256', $time . '.' . $body, $key ),
			),
			'body'     => $body,
		) );
		if ( is_wp_error( $response ) ) {
			return array( 0, null, $response->get_error_message() );
		}
		$answer = json_decode( wp_remote_retrieve_body( $response ), true );
		return array( (int) wp_remote_retrieve_response_code( $response ), is_array( $answer ) ? $answer : null, '' );
	}

	/**
	 * Whether the shared monitor files running on this site know about the
	 * site key. Another R&A plugin with older copies can load first (they
	 * define the classes); then connecting would break its check-ins.
	 * Returns '' when fine, or the plain reason it can't connect yet.
	 */
	public static function blocker() {
		foreach ( array( 'RA_Monitor_Client' => self::NEEDS_CLIENT, 'RA_Monitor_Updates' => self::NEEDS_UPDATES ) as $class => $needs ) {
			if ( ! class_exists( $class ) ) {
				return __( 'R&A\'s check-in is turned off on this website (RA_MONITOR_DISABLE), so it can\'t connect.', 'rapm' );
			}
			if ( version_compare( constant( $class . '::VERSION' ), $needs, '<' ) ) {
				$reflect = new ReflectionClass( $class );
				$plugin  = dirname( plugin_basename( $reflect->getFileName() ), 3 );
				/* translators: %s: plugin folder name */
				return sprintf( __( 'Another R&A plugin on this website (%s) has an older copy of the check-in. Update it first, then connect.', 'rapm' ), $plugin );
			}
		}
		return '';
	}

	/* ------------------------------------------------------------------
	 * Hooks into the shared check-in and poll
	 * --------------------------------------------------------------- */

	public static function signing_key( $key ) {
		$c = self::get();
		return 'none' === $c['status'] ? $key : $c['key'];
	}

	public static function add_to_checkin( $payload ) {
		if ( 'connected' === self::status() && is_array( $payload ) ) {
			$payload['help_desk'] = array(
				'spots'   => self::spots(),
				'pages'   => self::pages(),
				'caching' => self::caching(),
			);
		}
		return $payload;
	}

	/**
	 * The monitor's answer to a check-in or poll says where the connection
	 * stands: 'connected' (approved), 'pending', 'refused' or 'removed' (by
	 * R&A), and 'rekey' when R&A asked for a new key.
	 */
	public static function hear( $answer ) {
		$c = self::get();
		if ( 'none' === $c['status'] || ! is_array( $answer ) || empty( $answer['connection'] ) || ! is_array( $answer['connection'] ) ) {
			return;
		}
		$said = isset( $answer['connection']['status'] ) ? (string) $answer['connection']['status'] : '';
		if ( 'connected' === $said && 'connected' !== $c['status'] ) {
			$c['status']      = 'connected';
			$c['approved_at'] = time();
			update_option( self::OPTION, $c, false );
			delete_option( self::NOTE_OPTION );
			// Send the spots, pages and caching straight away.
			if ( class_exists( 'RA_Monitor_Client' ) ) {
				RA_Monitor_Client::schedule_soon();
			}
		} elseif ( 'refused' === $said ) {
			self::forget( __( 'R&A turned down the connection. Ask R&A, then connect again.', 'rapm' ) );
		} elseif ( 'removed' === $said ) {
			self::forget( __( 'R&A removed the connection.', 'rapm' ) );
		}
		if ( 'connected' === $said && ! empty( $answer['connection']['rekey'] ) ) {
			self::rekey();
		}
		self::hear_visitor( $answer );
	}

	/** R&A asked for a new key: make one and send it, signed with the old one. */
	private static function rekey() {
		$c   = self::get();
		$new = bin2hex( random_bytes( 32 ) );
		list( $code ) = self::send( 'rekey', array( 'site_url' => home_url(), 'new_key' => $new ), $c['key'] );
		if ( 200 === $code ) {
			$c['key'] = $new;
			update_option( self::OPTION, $c, false );
		}
	}

	/* ------------------------------------------------------------------
	 * The monitor's call back (step 2)
	 * --------------------------------------------------------------- */

	public static function routes() {
		register_rest_route( 'rapm/v1', '/hd-confirm', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'confirm' ),
			// Anyone may ask; only the right request id gets an answer, and
			// the answer proves the key without giving it away.
			'permission_callback' => '__return_true',
			'args'                => array(
				'request'   => array( 'type' => 'string', 'required' => true ),
				'challenge' => array( 'type' => 'string', 'required' => true ),
			),
		) );
	}

	public static function confirm( WP_REST_Request $request ) {
		$c         = self::get();
		$id        = (string) $request->get_param( 'request' );
		$challenge = (string) $request->get_param( 'challenge' );
		if ( 'none' === $c['status'] || empty( $c['request'] ) || ! hash_equals( $c['request'], $id ) || ! preg_match( '/^[0-9a-f]{32,128}$/', $challenge ) ) {
			return new WP_REST_Response( array( 'ok' => false ), 404 );
		}
		return new WP_REST_Response( array( 'ok' => true, 'proof' => hash_hmac( 'sha256', 'confirm.' . $challenge, $c['key'] ) ), 200 );
	}

	/* ------------------------------------------------------------------
	 * Connect, cancel, disconnect (the box's buttons)
	 * --------------------------------------------------------------- */

	public static function handle() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Only administrators can connect this website to R&A\'s help desk.', 'rapm' ) );
		}
		check_admin_referer( 'rapm_help_desk' );
		$do   = isset( $_POST['do'] ) ? sanitize_key( wp_unslash( $_POST['do'] ) ) : '';
		$back = admin_url( 'edit.php?post_type=rapm_asset&page=rapm-settings' );
		$note = '';

		if ( 'connect' === $do && 'none' === self::status() ) {
			$blocker = self::blocker();
			if ( $blocker ) {
				$note = $blocker;
			} else {
				$user = wp_get_current_user();
				$c    = array(
					'status'   => 'pending',
					'key'      => bin2hex( random_bytes( 32 ) ),
					'request'  => bin2hex( random_bytes( 16 ) ),
					'asked_at' => time(),
					'asked_by' => (int) $user->ID,
				);
				$shared = class_exists( 'RA_Monitor_Client' ) ? RA_Monitor_Client::KEY : '';
				list( $code, $answer, $error ) = self::send( 'connect', array(
					'site_url'       => home_url(),
					'site_name'      => get_bloginfo( 'name' ),
					'request'        => $c['request'],
					'key'            => $c['key'],
					'confirm_url'    => rest_url( 'rapm/v1/hd-confirm' ),
					'name'           => $user->display_name,
					'email'          => $user->user_email,
					'plugin_version' => RAPM_VERSION,
				), $shared );
				if ( 200 === $code ) {
					update_option( self::OPTION, $c, false );
					delete_option( self::NOTE_OPTION );
				} elseif ( 409 === $code ) {
					$note = __( 'R&A\'s help desk says this website is already connected. Ask R&A to remove the old connection first.', 'rapm' );
				} else {
					/* translators: %s: error code or message */
					$note = sprintf( __( 'Couldn\'t reach R&A\'s help desk (%s). Nothing was changed; try again later.', 'rapm' ), $code ? $code : $error );
				}
			}
		} elseif ( in_array( $do, array( 'cancel', 'disconnect' ), true ) && 'none' !== self::status() ) {
			$c = self::get();
			// Only forget it here once the monitor has too: if the monitor
			// still thought this website was connected, it would refuse its
			// check-ins signed with the shared key.
			list( $code, $answer ) = self::send( 'disconnect', array( 'site_url' => home_url() ), $c['key'] );
			if ( 200 === $code || ( is_array( $answer ) && isset( $answer['error'] ) && 'not_connected' === $answer['error'] ) ) {
				self::forget();
			} else {
				$note = __( 'Couldn\'t reach R&A\'s help desk, so this website is still connected. Try again later.', 'rapm' );
			}
		}

		if ( $note ) {
			update_option( self::NOTE_OPTION, array( 'time' => time(), 'message' => $note ), false );
		}
		wp_safe_redirect( $back . '#rapm-help-desk' );
		exit;
	}

	/* ------------------------------------------------------------------
	 * Elementor saves (the visitor check, a page at a time)
	 * --------------------------------------------------------------- */

	public static function page_saved( $post_id ) {
		$c = self::get();
		if ( 'connected' !== $c['status'] || wp_is_post_revision( $post_id ) ) {
			return;
		}
		// 1.66.0: RA Monitor Client 1.3.0 reports every Elementor save itself.
		if ( class_exists( 'RA_Monitor_Client' ) && version_compare( RA_Monitor_Client::VERSION, '1.3.0', '>=' ) ) {
			return;
		}
		$type = get_post_type( $post_id );
		if ( 'elementor_library' === $type ) {
			// A header, footer or other template: it's on every page, so check the home page.
			$url = home_url( '/' );
		} elseif ( 'publish' === get_post_status( $post_id ) && is_post_type_viewable( $type ) ) {
			$url = get_permalink( $post_id );
		} else {
			return;
		}
		// Fire and forget: never slow down Elementor's Publish button.
		self::send( 'page-saved', array( 'site_url' => home_url(), 'url' => $url ), $c['key'], false );
	}

	/* ------------------------------------------------------------------
	 * What a connected website adds to its check-in
	 * --------------------------------------------------------------- */

	/** Each spot: name, kind, the picture sizes it takes, and its public pages. */
	public static function spots() {
		$out = array();
		foreach ( array_slice( RAPM_Spots::known(), 0, 60 ) as $spot ) {
			$kind  = RAPM_Slots::kind_for( $spot['kind'], $spot['placement'] );
			$sizes = array();
			foreach ( array_unique( array( $kind['desktop'], $kind['mobile'] ) ) as $slot_key ) {
				$slot = RAPM_Slots::get( $slot_key );
				if ( $slot ) {
					$sizes[] = array(
						'screen' => $kind['desktop'] === $kind['mobile'] ? 'all' : ( $slot_key === $kind['desktop'] ? 'computer' : 'phone' ),
						'width'  => (int) $slot['width'],
						'height' => (int) $slot['height'],
					);
				}
			}
			$pages = array();
			foreach ( RAPM_Spot_Usage::for_spot( $spot['kind'], $spot['placement'] ) as $use ) {
				if ( ! empty( $use['sitewide'] ) ) {
					$pages[] = __( 'Every page (template or widget)', 'rapm' );
				} elseif ( ! empty( $use['path'] ) && ( empty( $use['status'] ) || 'publish' === $use['status'] ) ) {
					$pages[] = $use['path'];
				}
			}
			$out[] = array(
				'name'  => $spot['name'],
				'kind'  => $spot['type'],
				'sizes' => $sizes,
				'pages' => array_values( array_unique( $pages ) ),
			);
		}
		return $out;
	}

	/** The public pages a visitor would see a promotion on (home page first), at most 6. */
	public static function pages() {
		$urls = array( home_url( '/' ) );
		foreach ( RAPM_Spot_Usage::get() as $key => $uses ) {
			if ( 'calendar' === $key ) {
				continue;
			}
			foreach ( $uses as $use ) {
				if ( empty( $use['sitewide'] ) && ! empty( $use['url'] ) && ( empty( $use['status'] ) || 'publish' === $use['status'] ) ) {
					$urls[] = $use['url'];
				}
			}
		}
		return array_slice( array_values( array_unique( $urls ) ), 0, 6 );
	}

	/**
	 * Caching plugins that are on, by folder name, and WP Rocket's main
	 * switches, read from its own saved settings (wp_rocket_settings). Only
	 * on/off values: never keys, licenses or addresses.
	 */
	public static function caching() {
		$known = array(
			'wp-rocket'               => 'WP Rocket',
			'litespeed-cache'         => 'LiteSpeed Cache',
			'w3-total-cache'          => 'W3 Total Cache',
			'wp-super-cache'          => 'WP Super Cache',
			'wp-fastest-cache'        => 'WP Fastest Cache',
			'autoptimize'             => 'Autoptimize',
			'perfmatters'             => 'Perfmatters',
			'sg-cachepress'           => 'SiteGround Optimizer',
			'breeze'                  => 'Breeze',
			'nitropack'               => 'NitroPack',
			'wp-optimize'             => 'WP-Optimize',
			'hummingbird-performance' => 'Hummingbird',
			'flying-press'            => 'FlyingPress',
			'cloudflare'              => 'Cloudflare',
		);
		$active = (array) get_option( 'active_plugins', array() );
		if ( is_multisite() ) {
			$active = array_merge( $active, array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) );
		}
		$on = array();
		foreach ( $active as $basename ) {
			$folder = strtok( (string) $basename, '/' );
			if ( isset( $known[ $folder ] ) ) {
				$on[] = $known[ $folder ];
			}
		}
		$out = array( 'plugins' => array_values( array_unique( $on ) ) );

		if ( in_array( 'WP Rocket', $out['plugins'], true ) ) {
			$wpr      = get_option( 'wp_rocket_settings', array() );
			$wpr      = is_array( $wpr ) ? $wpr : array();
			$flag     = function ( $key ) use ( $wpr ) {
				return ! empty( $wpr[ $key ] );
			};
			$safelist = isset( $wpr['remove_unused_css_safelist'] ) ? (array) $wpr['remove_unused_css_safelist'] : array();
			$elementor_safe = false;
			foreach ( $safelist as $line ) {
				if ( false !== strpos( (string) $line, '/elementor/css/' ) ) {
					$elementor_safe = true;
				}
			}
			$out['wp_rocket'] = array(
				'version'           => defined( 'WP_ROCKET_VERSION' ) ? (string) WP_ROCKET_VERSION : '',
				'phone_cache'       => $flag( 'cache_mobile' ) && $flag( 'do_caching_mobile_files' ),
				'remove_unused_css' => $flag( 'remove_unused_css' ),
				'elementor_safe'    => $elementor_safe,
				'delay_js'          => $flag( 'delay_js' ),
				'minify_css'        => $flag( 'minify_css' ),
				// 1.64.2: Promo Manager's stylesheets are kept out of the trimming.
				'promo_kept'        => false !== has_filter( 'rocket_rucss_external_exclusions', array( 'RAPM_Assets', 'keep_css_in_rocket_rucss' ) ),
			);
		}
		return $out;
	}

	/* ------------------------------------------------------------------
	 * The box at the top of Settings
	 * --------------------------------------------------------------- */

	public static function render_box() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$c       = self::get();
		$status  = $c['status'];
		$note    = get_option( self::NOTE_OPTION );
		$blocker = 'none' === $status ? self::blocker() : '';
		$labels  = array(
			'none'      => array( 'is-off', __( 'Not connected', 'rapm' ) ),
			'pending'   => array( 'is-wait', __( 'Waiting for R&A', 'rapm' ) ),
			'connected' => array( 'is-on', __( 'Connected', 'rapm' ) ),
		);
		$last    = get_option( 'ra_monitor_last_sent' );
		?>
		<style>
			.rapm-hd { background: #fff; border: 1px solid #c3c4c7; padding: 4px 22px 12px; margin: 16px 0 8px; max-width: 980px; }
			.rapm-hd-head { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 8px 16px; }
			.rapm-hd-head h2 { margin: 14px 0 6px; font-size: 18px; }
			.rapm-hd-pill { display: inline-block; border-radius: 10px; padding: 2px 10px; font-size: 13px; font-weight: 600; background: #f0f0f1; color: #3c434a; }
			.rapm-hd-pill.is-on { background: #edfaef; color: #0a5c2c; }
			.rapm-hd-pill.is-wait { background: #fcf9e8; color: #6b4e00; }
			.rapm-hd-pill.is-bad { background: #fcf0f1; color: #8a1f1f; }
			.rapm-hd-lead { font-size: 15px; max-width: 760px; }
			.rapm-hd-cols { display: flex; flex-wrap: wrap; gap: 16px; margin: 8px 0 14px; }
			.rapm-hd-cols > div { flex: 1 1 280px; min-width: 0; background: #f6f7f7; padding: 12px 16px; }
			.rapm-hd-cols ul { list-style: disc; margin: 6px 0 6px 20px; }
			.rapm-hd-row { display: flex; flex-wrap: wrap; align-items: center; gap: 10px 16px; }
			.rapm-hd-blocked { background: #fcf9e8; color: #6b4e00; padding: 10px 14px; }
			.rapm-hd-facts { display: grid; grid-template-columns: minmax(140px, 0.4fr) minmax(0, 1fr); gap: 8px 16px; margin: 10px 0 14px; }
			.rapm-hd-facts dt { font-weight: 600; }
			.rapm-hd-facts dd { margin: 0; }
			.rapm-hd .button.rapm-hd-disconnect { color: #b32d2e; border-color: #b32d2e; }
			.rapm-hd-foot { border-top: 1px solid #f0f0f1; padding-top: 10px; margin-top: 14px; }
			@media (max-width: 600px) { .rapm-hd-facts { grid-template-columns: 1fr; } .rapm-hd .button { min-height: 44px; } }
		</style>
		<div class="rapm-hd" id="rapm-help-desk">
			<div class="rapm-hd-head">
				<h2><?php esc_html_e( 'R&A Help Desk', 'rapm' ); ?></h2>
				<span class="rapm-hd-pill <?php echo esc_attr( $labels[ $status ][0] ); ?>"><?php echo esc_html( $labels[ $status ][1] ); ?></span>
			</div>
			<?php if ( is_array( $note ) && ! empty( $note['message'] ) ) : ?>
				<div class="notice notice-warning inline"><p><?php echo esc_html( $note['message'] ); ?></p></div>
			<?php endif; ?>

			<?php if ( 'none' === $status ) : ?>
				<p class="rapm-hd-lead"><?php esc_html_e( 'Connect this website to R&A\'s help desk. R&A can then see your promotion spots and picture sizes, and check that visitors see your promotions right after every update.', 'rapm' ); ?></p>
				<div class="rapm-hd-cols">
					<div>
						<strong><?php esc_html_e( 'What it shares with R&A', 'rapm' ); ?></strong>
						<ul>
							<li><?php esc_html_e( 'Plugin versions (already shared today)', 'rapm' ); ?></li>
							<li><?php esc_html_e( 'Your promotion spots and their picture sizes', 'rapm' ); ?></li>
							<li><?php esc_html_e( 'Your caching settings (which caching plugins are on)', 'rapm' ); ?></li>
							<li><?php esc_html_e( 'Which page was just saved in Elementor, so R&A can check it', 'rapm' ); ?></li>
						</ul>
					</div>
					<div>
						<strong><?php esc_html_e( 'What it never shares', 'rapm' ); ?></strong>
						<ul>
							<li><?php esc_html_e( 'Passwords or keys', 'rapm' ); ?></li>
							<li><?php esc_html_e( 'Customers, orders or form entries', 'rapm' ); ?></li>
							<li><?php esc_html_e( 'What\'s written on your pages', 'rapm' ); ?></li>
						</ul>
						<span class="description"><?php esc_html_e( 'R&A only looks at your public pages, the same way any visitor does.', 'rapm' ); ?></span>
					</div>
				</div>
				<?php if ( $blocker ) : ?>
					<p class="rapm-hd-blocked"><?php echo esc_html( $blocker ); ?></p>
				<?php else : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="rapm-hd-row">
						<input type="hidden" name="action" value="rapm_help_desk">
						<input type="hidden" name="do" value="connect">
						<?php wp_nonce_field( 'rapm_help_desk' ); ?>
						<button type="submit" class="button button-primary"><?php esc_html_e( 'Connect to R&A Help Desk', 'rapm' ); ?></button>
						<span class="description"><?php esc_html_e( 'R&A approves each connection, usually the same day.', 'rapm' ); ?></span>
					</form>
				<?php endif; ?>

			<?php elseif ( 'pending' === $status ) : ?>
				<p class="rapm-hd-lead">
					<?php
					/* translators: %s: how long ago */
					echo esc_html( sprintf( __( 'You asked to connect %s ago. R&A will approve it, usually the same day. Nothing new is shared until then.', 'rapm' ), human_time_diff( (int) $c['asked_at'] ) ) );
					?>
				</p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="rapm_help_desk">
					<input type="hidden" name="do" value="cancel">
					<?php wp_nonce_field( 'rapm_help_desk' ); ?>
					<button type="submit" class="button"><?php esc_html_e( 'Cancel the request', 'rapm' ); ?></button>
				</form>

			<?php else : ?>
				<dl class="rapm-hd-facts">
					<dt><?php esc_html_e( 'Connected', 'rapm' ); ?></dt>
					<dd>
						<?php
						/* translators: %s: date */
						echo esc_html( sprintf( __( 'Since %s, approved by R&A', 'rapm' ), wp_date( get_option( 'date_format' ), (int) ( isset( $c['approved_at'] ) ? $c['approved_at'] : $c['asked_at'] ) ) ) );
						?>
					</dd>
					<dt><?php esc_html_e( 'Last check-in', 'rapm' ); ?></dt>
					<dd>
						<?php
						if ( is_array( $last ) && ! empty( $last['time'] ) ) {
							/* translators: 1: how long ago, 2: number of spots */
							echo esc_html( sprintf( __( '%1$s ago: %2$d spots, caching settings, plugin versions', 'rapm' ), human_time_diff( (int) $last['time'] ), count( self::spots() ) ) );
						} else {
							esc_html_e( 'Not yet', 'rapm' );
						}
						?>
					</dd>
					<dt><?php esc_html_e( 'Visitor check', 'rapm' ); ?></dt>
					<dd><?php self::render_visitor_line(); ?></dd>
				</dl>
				<p class="description"><?php esc_html_e( 'Disconnecting stops sharing spots, sizes and caching settings, and stops the visitor checks. Plugin versions are still shared, as before.', 'rapm' ); ?></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Disconnect this website from R&A\'s help desk?', 'rapm' ) ); ?>');">
					<input type="hidden" name="action" value="rapm_help_desk">
					<input type="hidden" name="do" value="disconnect">
					<?php wp_nonce_field( 'rapm_help_desk' ); ?>
					<button type="submit" class="button rapm-hd-disconnect"><?php esc_html_e( 'Disconnect', 'rapm' ); ?></button>
				</form>
			<?php endif; ?>
			<p class="rapm-hd-foot description"><?php esc_html_e( 'Only administrators see this box.', 'rapm' ); ?></p>
		</div>
		<?php
	}

	/** The last visitor check, as the monitor reported it in its last answer. */
	private static function render_visitor_line() {
		$vc = get_option( 'rapm_help_desk_visitor' );
		if ( ! is_array( $vc ) || empty( $vc['result'] ) ) {
			esc_html_e( 'Not checked yet. R&A checks after every update.', 'rapm' );
			return;
		}
		$ago = isset( $vc['time'] ) ? human_time_diff( (int) $vc['time'] ) : '';
		if ( 'pass' === $vc['result'] ) {
			echo '<span class="rapm-hd-pill is-on">' . esc_html__( 'Looks right', 'rapm' ) . '</span> ';
			/* translators: %s: how long ago */
			echo esc_html( sprintf( __( '%s ago, on a computer and a phone.', 'rapm' ), $ago ) );
		} elseif ( 'fail' === $vc['result'] ) {
			echo '<span class="rapm-hd-pill is-bad">' . esc_html__( 'R&A is on it', 'rapm' ) . '</span> ';
			/* translators: %s: how long ago */
			echo esc_html( sprintf( __( '%s ago, R&A\'s check found that some visitors weren\'t getting your promotions\' styles. R&A has been told.', 'rapm' ), $ago ) );
		} else {
			/* translators: %s: how long ago */
			echo esc_html( sprintf( __( '%s ago, R&A couldn\'t load your pages to check them. R&A has been told.', 'rapm' ), $ago ) );
		}
	}

	/** Keeps the last visitor check the monitor mentions in its answers, for the box. */
	public static function hear_visitor( $answer ) {
		if ( is_array( $answer ) && ! empty( $answer['visitor_check'] ) && is_array( $answer['visitor_check'] ) && 'connected' === self::status() ) {
			$vc = $answer['visitor_check'];
			if ( isset( $vc['result'] ) && in_array( $vc['result'], array( 'pass', 'fail', 'couldnt' ), true ) ) {
				update_option( 'rapm_help_desk_visitor', array( 'result' => $vc['result'], 'time' => isset( $vc['time'] ) ? (int) $vc['time'] : time() ), false );
			}
		}
	}
}
