<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Self-update from a private GitHub repo — same system as Universal Room
 * Planner (v7.17.0–v7.20.0 there), brought over as-is rather than re-solved.
 *
 * Staging and production check TWO SEPARATE private repos, not two branches
 * of one repo: plugin-update-checker prefers tags/releases over whatever
 * branch is set, so branch-only separation can't reliably keep a tagged
 * release away from production. A version only reaches live sites once it's
 * deliberately pushed to the production repo, after being verified on staging.
 *
 * Channel (defaults to production — the safe choice if nothing is set):
 *   define( 'RAPM_UPDATE_CHANNEL', 'staging' ); // only ever on the staging site
 *
 * Each site also needs a read-only GitHub token covering BOTH repos (a token
 * scoped to only one of them silently fails on the other channel — the exact
 * bug Room Planner hit in v7.19.1). Either in wp-config.php (takes priority):
 *   define( 'RAPM_GITHUB_UPDATE_TOKEN', 'github_pat_xxxxxxxxxxxxxxxxxxxx' );
 * ...or via Promo Manager > Settings > Auto-Update Settings, for sites
 * managed only through wp-admin.
 */
class RAPM_Updater {

	const REPO_PRODUCTION = 'https://github.com/ra-license/rapm-promo-manager/';
	const REPO_STAGING    = 'https://github.com/ra-license/rapm-promo-manager-staging/';

	const OPTION_TOKEN   = 'rapm_github_update_token';
	const OPTION_CHANNEL = 'rapm_update_channel';

	public static function channel() {
		if ( defined( 'RAPM_UPDATE_CHANNEL' ) && RAPM_UPDATE_CHANNEL ) {
			return 'staging' === RAPM_UPDATE_CHANNEL ? 'staging' : 'production';
		}
		return 'staging' === get_option( self::OPTION_CHANNEL, 'production' ) ? 'staging' : 'production';
	}

	public static function token() {
		if ( defined( 'RAPM_GITHUB_UPDATE_TOKEN' ) && RAPM_GITHUB_UPDATE_TOKEN ) {
			return RAPM_GITHUB_UPDATE_TOKEN;
		}
		return get_option( self::OPTION_TOKEN, '' );
	}

	/**
	 * Runs at plugin load, not on a hook — the library needs to be set up
	 * before WordPress's own update check fires.
	 */
	public static function init( $plugin_file ) {
		require_once RAPM_DIR . 'lib/plugin-update-checker/plugin-update-checker.php';

		$checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
			'staging' === self::channel() ? self::REPO_STAGING : self::REPO_PRODUCTION,
			$plugin_file,
			'rapm-promo-manager'
		);
		$checker->setBranch( 'main' );

		$token = self::token();
		if ( $token ) {
			$checker->setAuthentication( $token );
		}

		// Auto-update on by default for this plugin on every site, so a
		// version promoted to the production repo applies everywhere with
		// zero clicks (matches Room Planner v7.20.0).
		$basename = plugin_basename( $plugin_file );
		add_filter(
			'auto_update_plugin',
			function ( $update, $item ) use ( $basename ) {
				if ( isset( $item->plugin ) && $item->plugin === $basename ) {
					return true;
				}
				return $update;
			},
			10,
			2
		);

		// Daily check-in to R&A's Plugin Monitor (help.ramarketing.com), via
		// the shared lib/ra-monitor-client — the same file bundled into every
		// R&A plugin (Woo Native Dimensions has shipped it since its v1.25).
		// Reports this site's R&A plugins, their versions, and whether the
		// last update check actually reached GitHub. No tokens or customer
		// data. Turn off per site with define( 'RA_MONITOR_DISABLE', true );
		require_once RAPM_DIR . 'lib/ra-monitor-client/ra-monitor-client.php';
		RA_Monitor_Client::register(
			array(
				'file'      => $plugin_file,
				'slug'      => 'rapm-promo-manager',
				'checker'   => $checker,
				'has_token' => (bool) $token,
				'channel'   => self::channel(),
			)
		);
	}

	public static function sanitize_channel( $value ) {
		return 'staging' === $value ? 'staging' : 'production';
	}
}
