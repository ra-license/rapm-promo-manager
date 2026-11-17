<?php
/**
 * Two safety nets so a broken release can't take a website down (1.73.0).
 *
 * Why: on Nov 16, 2026, 1.69.0 shipped with a missing "}" at the end of
 * class-rapm-hero-carousel.php. Every page on staging, admin included,
 * showed WordPress's critical error, and 1.69.1 (the fix, five minutes
 * later) couldn't install itself, because the updater is part of the same
 * plugin and didn't load either. Staging was down about 18 hours, until
 * Rocket.net added the brace by hand (#ext-ramarketing, Nov 17). It never
 * reached production, but client sites update themselves (1.67.0), so the
 * same mistake there would have needed a person on every server.
 *
 * 1. The safe loader (load(), paused()): the main file loads Promo Manager's
 *    files inside try/catch. Since PHP 7, a file that can't be read (a
 *    syntax error) or fails while loading throws an error that can be
 *    caught. Then nothing else of Promo Manager starts: the website keeps
 *    working without promotions, its shortcodes print nothing instead of
 *    "[rapm_hero]", administrators see a notice saying which file and line,
 *    and the updater (loaded on its own, after) can still install the fix.
 *    When everything loads again, the pause clears itself.
 *
 * 2. The check before installing (check_package()): when WordPress is about
 *    to replace Promo Manager with a new version (automatic update, Update
 *    now, R&A's monitor, or an uploaded zip), every PHP file in the new
 *    version is read with this website's own PHP (token_get_all with
 *    TOKEN_PARSE, the same check R&A runs before each release) and every
 *    file the code loads by name must be there. If anything fails, the
 *    update is refused before the old files are touched: the version already
 *    installed keeps running, and administrators see what was wrong.
 *
 * 1.73.1: R&A hears about a pause. The first request that finds Promo
 * Manager paused sends R&A's Website Monitor a signed "something changed"
 * note naming the file and line (RA_Monitor_Client::report_change), and
 * every page carries <meta name="ra-promo-manager" content="paused">.
 * Website Monitor 1.10.0 counts that marker as a failed visitor check, so
 * its usual "Visitor check failed" email goes out about 15 minutes after
 * the pause. Before this, a paused website looked fine to the monitor.
 *
 * What these don't catch: a mistake that only shows when a page runs (a
 * misspelled function, say) and a few errors PHP can't hand back as an
 * exception (like declaring the same method twice). R&A's own checks before
 * each release, and the monitor's visitor check after each update, are for
 * those. The check before installing works from the version that does the
 * installing, so it protects updates to 1.73.1 and later.
 */

defined( 'ABSPATH' ) || exit;

class RAPM_Safety {

	const OPTION_PAUSED  = 'rapm_paused';
	const OPTION_REFUSED = 'rapm_update_refused';

	/** Every shortcode Promo Manager adds: printed as nothing while paused. */
	const SHORTCODES = array( 'rapm_hero', 'rapm_fold_banner', 'rapm_curated_results', 'rapm_marquee', 'rapm_quick_links', 'rapm_coupon_book', 'rapm_looks', 'rapm_promo_box', 'rapm_reveal', 'rapm_promotions_calendar' );

	private static $plugin_file = '';

	/** What stopped Promo Manager from loading on this request, or null. */
	private static $error = null;

	public static function init( $plugin_file ) {
		self::$plugin_file = $plugin_file;
		add_filter( 'upgrader_source_selection', array( __CLASS__, 'check_package' ), 20, 4 );
		add_action( 'admin_notices', array( __CLASS__, 'notices' ) );
		add_action( 'network_admin_notices', array( __CLASS__, 'notices' ) );
	}

	/* ------------------------------------------------------------------
	 * 1. The safe loader
	 * ---------------------------------------------------------------- */

	/** Loads files in order (paths under the plugin folder). False at the first one that can't load. */
	public static function load( $files ) {
		foreach ( $files as $rel ) {
			try {
				require_once RAPM_DIR . $rel;
			} catch ( \Throwable $e ) {
				$where       = (string) $e->getFile();
				self::$error = array(
					'file'    => 0 === strpos( $where, RAPM_DIR ) ? substr( $where, strlen( RAPM_DIR ) ) : $rel,
					'line'    => (int) $e->getLine(),
					'message' => (string) $e->getMessage(),
					'version' => RAPM_VERSION,
				);
				return false;
			}
		}
		return true;
	}

	/** Promo Manager couldn't load: keep the website up and say why. */
	public static function paused() {
		if ( get_option( self::OPTION_PAUSED ) !== self::$error ) {
			update_option( self::OPTION_PAUSED, self::$error, false );
			error_log( 'RA Promo Manager ' . RAPM_VERSION . ' is paused: ' . self::$error['file'] . ' line ' . self::$error['line'] . ': ' . self::$error['message'] ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
			// 1.73.1: tell R&A's Website Monitor once (a signed note, a few
			// minutes later). It then checks the website as a visitor, sees
			// the marker below and emails. Which file is in the note, not on
			// the page.
			if ( class_exists( 'RA_Monitor_Client' ) && method_exists( 'RA_Monitor_Client', 'report_change' ) ) {
				RA_Monitor_Client::report_change( 'RA Promo Manager ' . RAPM_VERSION . ' paused: ' . self::$error['file'] . ' line ' . self::$error['line'] );
			}
		}
		// 1.73.1: a marker on every page while paused, for the monitor's
		// visitor check (Website Monitor 1.10.0+). Says only "paused".
		add_action(
			'wp_head',
			function () {
				echo '<meta name="ra-promo-manager" content="paused" />' . "\n";
			},
			1
		);
		add_action(
			'init',
			function () {
				foreach ( self::SHORTCODES as $tag ) {
					add_shortcode( $tag, '__return_empty_string' );
				}
			}
		);
	}

	/** Everything loaded: clear a pause left from before. */
	public static function loaded() {
		if ( false !== get_option( self::OPTION_PAUSED, false ) ) {
			delete_option( self::OPTION_PAUSED );
		}
	}

	/* ------------------------------------------------------------------
	 * 2. The check before installing
	 * ---------------------------------------------------------------- */

	/** Whether the folder WordPress is about to install is Promo Manager. */
	private static function is_ours( $source, $hook_extra ) {
		if ( isset( $hook_extra['plugin'] ) && self::$plugin_file && plugin_basename( self::$plugin_file ) === $hook_extra['plugin'] ) {
			return true;
		}
		$main = trailingslashit( $source ) . 'rapm-promo-manager.php';
		if ( ! is_readable( $main ) ) {
			return false;
		}
		$head = (string) file_get_contents( $main, false, null, 0, 800 ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		return false !== strpos( $head, 'Plugin Name: RA Promo Manager' );
	}

	/**
	 * Problems in a plugin folder: PHP files that can't be read, and files
	 * the code loads by name ("RAPM_DIR . 'includes/…php'") that are missing.
	 * Each as "file line N: message". At most 5.
	 */
	public static function problems( $dir ) {
		$dir      = trailingslashit( $dir );
		$problems = array();
		if ( ! is_readable( $dir . 'rapm-promo-manager.php' ) ) {
			return array( __( 'rapm-promo-manager.php is missing', 'rapm' ) );
		}
		if ( ! function_exists( 'token_get_all' ) || ! defined( 'TOKEN_PARSE' ) ) {
			return array(); // This server's PHP has no tokenizer, so it can't check. Not a reason to refuse.
		}
		$files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ) );
		foreach ( $files as $file ) {
			if ( count( $problems ) >= 5 ) {
				break;
			}
			if ( 'php' !== strtolower( $file->getExtension() ) ) {
				continue;
			}
			$path = $file->getPathname();
			$rel  = ltrim( str_replace( '\\', '/', substr( $path, strlen( $dir ) ) ), '/' );
			$code = (string) file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			try {
				token_get_all( $code, TOKEN_PARSE );
			} catch ( \Throwable $e ) {
				/* translators: 1: file, 2: line number, 3: PHP's message */
				$problems[] = sprintf( __( '%1$s line %2$d: %3$s', 'rapm' ), $rel, (int) $e->getLine(), $e->getMessage() );
				continue;
			}
			// Files loaded by name: "RAPM_DIR . 'lib/…php'" anywhere, and the
			// main file's list for the safe loader ('includes/…php').
			$needed_files = array();
			if ( preg_match_all( "/RAPM_DIR\\s*\\.\\s*'([A-Za-z0-9_\\/.-]+\\.php)'/", $code, $m ) ) {
				$needed_files = $m[1];
			}
			if ( 'rapm-promo-manager.php' === $rel && preg_match_all( "/'((?:includes|lib)\\/[A-Za-z0-9_\\/.-]+\\.php)'/", $code, $m ) ) {
				$needed_files = array_merge( $needed_files, $m[1] );
			}
			if ( $needed_files ) {
				foreach ( array_unique( $needed_files ) as $needed ) {
					if ( ! is_readable( $dir . $needed ) ) {
						/* translators: 1: missing file, 2: the file that loads it */
						$problems[] = sprintf( __( '%1$s is missing (%2$s loads it)', 'rapm' ), $needed, $rel );
					}
				}
			}
		}
		return array_slice( $problems, 0, 5 );
	}

	/**
	 * upgrader_source_selection: runs after the new version is unpacked and
	 * before WordPress removes the old one. A WP_Error stops the update.
	 */
	public static function check_package( $source, $remote_source = '', $upgrader = null, $hook_extra = array() ) {
		if ( is_wp_error( $source ) || ! is_string( $source ) || ! self::is_ours( $source, (array) $hook_extra ) ) {
			return $source;
		}
		$problems = self::problems( $source );
		$data     = get_file_data( trailingslashit( $source ) . 'rapm-promo-manager.php', array( 'Version' => 'Version' ) );
		$version  = isset( $data['Version'] ) && '' !== $data['Version'] ? $data['Version'] : '?';
		if ( ! $problems ) {
			delete_option( self::OPTION_REFUSED );
			return $source;
		}
		update_option(
			self::OPTION_REFUSED,
			array(
				'version'  => $version,
				'kept'     => defined( 'RAPM_VERSION' ) ? RAPM_VERSION : '',
				'problems' => $problems,
				'time'     => time(),
			),
			false
		);
		error_log( 'RA Promo Manager ' . $version . ' was not installed: ' . implode( '; ', $problems ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
		return new WP_Error(
			'rapm_package_broken',
			/* translators: 1: version refused, 2: what's wrong, 3: version kept */
			sprintf( __( 'RA Promo Manager %1$s was not installed because a file in it is broken (%2$s). Version %3$s keeps running. Tell R&A Marketing.', 'rapm' ), $version, implode( '; ', $problems ), defined( 'RAPM_VERSION' ) ? RAPM_VERSION : '' )
		);
	}

	/* ------------------------------------------------------------------
	 * What administrators see
	 * ---------------------------------------------------------------- */

	public static function notices() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$paused = get_option( self::OPTION_PAUSED, false );
		if ( is_array( $paused ) && null !== self::$error ) {
			echo '<div class="notice notice-error"><p><strong>' . esc_html__( 'RA Promo Manager is paused.', 'rapm' ) . '</strong> ';
			/* translators: 1: file, 2: line number, 3: PHP's message, 4: version */
			echo esc_html( sprintf( __( 'One of its files couldn\'t load (%1$s, line %2$d: %3$s) in version %4$s. The website keeps working without promotions. When R&A releases a fixed version, it installs itself; R&A can also reinstall the plugin from its zip.', 'rapm' ), $paused['file'], (int) $paused['line'], $paused['message'], $paused['version'] ) );
			echo '</p></div>';
		}
		$refused = get_option( self::OPTION_REFUSED, false );
		if ( is_array( $refused ) ) {
			if ( empty( $refused['time'] ) || time() - (int) $refused['time'] > 14 * DAY_IN_SECONDS ) {
				delete_option( self::OPTION_REFUSED );
				return;
			}
			echo '<div class="notice notice-warning"><p><strong>' . esc_html__( 'A Promo Manager update was stopped.', 'rapm' ) . '</strong> ';
			/* translators: 1: version refused, 2: what's wrong, 3: version kept */
			echo esc_html( sprintf( __( 'Version %1$s wasn\'t installed because a file in it is broken (%2$s). Version %3$s keeps running, so nothing changed for visitors. R&A will release a fixed version.', 'rapm' ), $refused['version'], implode( '; ', (array) $refused['problems'] ), $refused['kept'] ) );
			echo '</p></div>';
		}
	}
}
