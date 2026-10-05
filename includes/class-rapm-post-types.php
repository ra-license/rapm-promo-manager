<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RAPM_Post_Types {

	public static function register() {
		register_post_type(
			'rapm_asset',
			array(
				'labels'          => array(
					'name'          => __( 'Promotional Assets', 'rapm' ),
					'singular_name' => __( 'Promotional Asset', 'rapm' ),
					// The menu reads "Promotions" > "All promotions" (1.28.0).
					// "All promotions" opens RAPM_Promotions_Screen.
					'menu_name'     => __( 'Promotions', 'rapm' ),
					'all_items'     => __( 'All promotions', 'rapm' ),
					'search_items'  => __( 'Search Assets', 'rapm' ),
					'not_found'     => __( 'No promotional assets found', 'rapm' ),
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => true,
				'menu_icon'       => 'dashicons-images-alt2',
				// Deliberately just 'title' — no 'thumbnail'/'editor' support.
				// Real asset creation always goes through RAPM_Upload_Handler's
				// validated upload form (Events... err, Promo > Add New Asset),
				// which sets images/copy via postmeta directly regardless of
				// what the native editor screen shows. Without 'thumbnail'
				// support, the native "Add New" screen has no unvalidated
				// image-upload path to begin with.
				'supports'        => array( 'title' ),
				'capability_type' => 'post',
				'map_meta_cap'    => true,
			)
		);
	}
}
