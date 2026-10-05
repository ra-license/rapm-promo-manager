<?php
/**
 * Plugin Name: RA Promo Manager
 * Description: Validated, scheduled promotional assets (hero banners and more) for client sites — enforces correct image dimensions/format/size on upload, schedules reliably even behind full-page caching, and links out to WordPress content, Elementor pages, or WooCommerce products/categories. Shortcode: [rapm_hero placement="default"].
 * Version: 1.28.0
 * Author: RA Marketing
 * Text Domain: rapm
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'RAPM_VERSION', '1.28.0' );
define( 'RAPM_DIR', plugin_dir_path( __FILE__ ) );
define( 'RAPM_URL', plugin_dir_url( __FILE__ ) );

require_once RAPM_DIR . 'includes/class-rapm-post-types.php';
require_once RAPM_DIR . 'includes/class-rapm-slots.php';
require_once RAPM_DIR . 'includes/class-rapm-assets.php';
require_once RAPM_DIR . 'includes/class-rapm-webp-converter.php';
require_once RAPM_DIR . 'includes/class-rapm-destination.php';
require_once RAPM_DIR . 'includes/class-rapm-admin-settings.php';
require_once RAPM_DIR . 'includes/class-rapm-upload-handler.php';
require_once RAPM_DIR . 'includes/class-rapm-admin-list.php';
require_once RAPM_DIR . 'includes/class-rapm-sliders-dashboard.php';
require_once RAPM_DIR . 'includes/class-rapm-spot-usage.php';
require_once RAPM_DIR . 'includes/class-rapm-promotions-screen.php';
require_once RAPM_DIR . 'includes/class-rapm-cache.php';
require_once RAPM_DIR . 'includes/class-rapm-help.php';
require_once RAPM_DIR . 'includes/class-rapm-training-guide.php';
require_once RAPM_DIR . 'includes/class-rapm-link-source.php';
require_once RAPM_DIR . 'includes/class-rapm-sync.php';
require_once RAPM_DIR . 'includes/class-rapm-hero-carousel.php';
require_once RAPM_DIR . 'includes/class-rapm-marquee.php';
require_once RAPM_DIR . 'includes/class-rapm-coupon-book.php';
require_once RAPM_DIR . 'includes/class-rapm-calendar.php';
require_once RAPM_DIR . 'includes/class-rapm-curated-results.php';
require_once RAPM_DIR . 'includes/class-rapm-elementor.php';
require_once RAPM_DIR . 'includes/class-rapm-updater.php';

// Self-update from private GitHub repos — see class-rapm-updater.php.
RAPM_Updater::init( __FILE__ );

final class RAPM_Plugin {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );

		add_action( 'init', array( 'RAPM_Post_Types', 'register' ) );

		add_filter( 'manage_rapm_asset_posts_columns', array( 'RAPM_Admin_List', 'columns' ) );
		add_action( 'manage_rapm_asset_posts_custom_column', array( 'RAPM_Admin_List', 'column_content' ), 10, 2 );
		add_action( 'restrict_manage_posts', array( 'RAPM_Admin_List', 'restrict_manage_posts' ) );
		add_action( 'pre_get_posts', array( 'RAPM_Admin_List', 'filter_query' ) );
		add_filter( 'post_row_actions', array( 'RAPM_Admin_List', 'row_actions' ), 10, 2 );
		add_filter( 'get_edit_post_link', array( 'RAPM_Admin_List', 'filter_edit_post_link' ), 10, 2 );
		add_action( 'admin_post_rapm_duplicate_asset', array( 'RAPM_Admin_List', 'handle_duplicate' ) );

		// Promotions screen (1.28.0) and the shorter menu: "All promotions" and "Help".
		add_action( 'admin_menu', array( 'RAPM_Promotions_Screen', 'add_menu' ) );
		add_action( 'admin_menu', array( 'RAPM_Promotions_Screen', 'tidy_menu' ), 999 );
		add_filter( 'submenu_file', array( 'RAPM_Promotions_Screen', 'submenu_file' ) );
		add_filter( 'admin_title', array( 'RAPM_Promotions_Screen', 'admin_title' ), 10, 2 );
		add_action( 'admin_init', array( 'RAPM_Promotions_Screen', 'maybe_redirect_list' ) );
		add_action( 'admin_enqueue_scripts', array( 'RAPM_Promotions_Screen', 'enqueue' ) );
		add_action( 'wp_ajax_rapm_toggle_asset', array( 'RAPM_Promotions_Screen', 'ajax_toggle' ) );
		add_action( 'wp_ajax_rapm_rename_spot', array( 'RAPM_Promotions_Screen', 'ajax_rename' ) );
		add_filter( 'wp_untrash_post_status', array( 'RAPM_Promotions_Screen', 'untrash_status' ), 10, 3 );

		// Clear the page cache for the pages a changed promotion shows on (RAPM_Cache).
		add_action( 'save_post_rapm_asset', array( 'RAPM_Cache', 'queue_asset' ) );
		add_action( 'transition_post_status', array( 'RAPM_Cache', 'on_transition' ), 10, 3 );
		add_action( 'before_delete_post', array( 'RAPM_Cache', 'on_delete' ) );

		// "Shows on:" is cached; any saved page, template or widget can change it.
		add_action( 'save_post', array( 'RAPM_Spot_Usage', 'maybe_flush_on_save' ) );
		add_action( 'deleted_post', array( 'RAPM_Spot_Usage', 'maybe_flush_on_save' ) );
		add_action( 'trashed_post', array( 'RAPM_Spot_Usage', 'maybe_flush_on_save' ) );
		add_action( 'untrashed_post', array( 'RAPM_Spot_Usage', 'maybe_flush_on_save' ) );
		foreach ( array( 'widget_text', 'widget_custom_html', 'widget_block', 'sidebars_widgets' ) as $rapm_widget_option ) {
			add_action( 'update_option_' . $rapm_widget_option, array( 'RAPM_Spot_Usage', 'flush' ) );
		}

		add_action( 'admin_menu', array( 'RAPM_Upload_Handler', 'add_menu' ) );
		add_action( 'admin_menu', array( 'RAPM_Sliders_Dashboard', 'add_menu' ) );
		add_action( 'admin_menu', array( 'RAPM_Help', 'add_menu' ) );
		add_action( 'admin_menu', array( 'RAPM_Training_Guide', 'add_menu' ) );
		add_action( 'wp_ajax_rapm_reorder_slides', array( 'RAPM_Sliders_Dashboard', 'ajax_reorder' ) );
		add_action( 'admin_init', array( 'RAPM_Upload_Handler', 'maybe_redirect_native_add_new' ) );
		add_action( 'admin_init', array( 'RAPM_Upload_Handler', 'maybe_redirect_native_edit' ) );
		add_action( 'admin_enqueue_scripts', array( 'RAPM_Upload_Handler', 'enqueue_admin_assets' ) );
		add_action( 'admin_enqueue_scripts', array( 'RAPM_Training_Guide', 'enqueue_admin_assets' ) );
		add_action( 'admin_post_rapm_save_asset', array( 'RAPM_Upload_Handler', 'handle_save' ) );
		add_action( 'admin_post_rapm_check_link_now', array( 'RAPM_Sync', 'handle_check_now' ) );
		add_action( 'wp_ajax_rapm_search_destination', array( 'RAPM_Destination', 'ajax_search' ) );
		add_action( 'wp_ajax_rapm_import_skus', array( 'RAPM_Destination', 'ajax_import_skus' ) );
		add_action( 'wp_ajax_rapm_placement_summary', array( 'RAPM_Upload_Handler', 'ajax_placement_summary' ) );
		add_action( 'wp_ajax_rapm_preview_link', array( 'RAPM_Upload_Handler', 'ajax_preview_link' ) );

		add_action( 'init', array( 'RAPM_Sync', 'schedule' ) );
		add_action( RAPM_Sync::HOOK, array( 'RAPM_Sync', 'run' ) );

		add_action( 'admin_menu', array( 'RAPM_Admin_Settings', 'add_menu' ) );
		add_action( 'admin_init', array( 'RAPM_Admin_Settings', 'register_settings' ) );

		add_shortcode( 'rapm_hero', array( 'RAPM_Hero_Carousel', 'shortcode_hero' ) );
		add_shortcode( 'rapm_fold_banner', array( 'RAPM_Hero_Carousel', 'shortcode_fold_banner' ) );
		add_shortcode( 'rapm_curated_results', array( 'RAPM_Curated_Results', 'shortcode' ) );
		add_shortcode( 'rapm_marquee', array( 'RAPM_Marquee', 'shortcode' ) );
		add_shortcode( 'rapm_coupon_book', array( 'RAPM_Coupon_Book', 'shortcode' ) );
		add_shortcode( 'rapm_promotions_calendar', array( 'RAPM_Calendar', 'shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( 'RAPM_Assets', 'register' ), 5 );
		add_action( 'wp_enqueue_scripts', array( 'RAPM_Hero_Carousel', 'enqueue' ) );
		add_action( 'wp_enqueue_scripts', array( 'RAPM_Marquee', 'enqueue' ) );
		add_action( 'wp_enqueue_scripts', array( 'RAPM_Coupon_Book', 'enqueue' ) );
		add_action( 'wp_enqueue_scripts', array( 'RAPM_Calendar', 'enqueue' ) );
		add_filter( 'rocket_delay_js_exclusions', array( 'RAPM_Assets', 'exclude_from_rocket_delay' ) );

		add_action( 'elementor/widgets/register', array( 'RAPM_Elementor', 'register_widgets' ) );
		add_action( 'elementor/elements/categories_registered', array( 'RAPM_Elementor', 'register_category' ) );
	}

	public function activate() {
		RAPM_Post_Types::register();
		RAPM_Sync::schedule();
		flush_rewrite_rules();
	}

	public function deactivate() {
		RAPM_Sync::unschedule();
		flush_rewrite_rules();
	}
}

RAPM_Plugin::instance();
