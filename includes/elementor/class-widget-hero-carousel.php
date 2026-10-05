<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RAPM_Widget_Hero_Carousel extends \Elementor\Widget_Base {

	public function get_name() {
		return 'rapm_hero_carousel';
	}

	public function get_title() {
		return __( 'Promo Carousel', 'rapm' );
	}

	public function get_icon() {
		return 'eicon-slider-push';
	}

	public function get_categories() {
		return array( 'rapm-promo' );
	}

	protected function register_controls() {
		$this->start_controls_section(
			'section_content',
			array( 'label' => __( 'Content', 'rapm' ) )
		);

		// Pick a spot by its name (1.29.0). Only sliders and feature banners
		// fit this widget. A widget saved before 1.29.0 has no spot yet and
		// keeps using its Kind and Placement below until one is picked.
		// The list is only needed in the editor: Elementor also builds a
		// widget's controls on the live site, and uses a saved value as-is
		// without checking it against the options (Control_Base_Data::
		// get_value() in Elementor's source), so the live site skips it.
		$spots = array( '' => __( 'Pick a spot…', 'rapm' ) );
		if ( is_admin() || isset( $_GET['elementor-preview'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			foreach ( RAPM_Spots::known() as $spot ) {
				if ( RAPM_Spots::can_be_main_heading( $spot['kind'] ) ) {
					$spots[ $spot['kind'] . '|' . $spot['placement'] ] = $spot['name'] . ' (' . $spot['type'] . ')';
				}
			}
		}
		$this->add_control(
			'spot',
			array(
				'label'       => __( 'Spot', 'rapm' ),
				'type'        => \Elementor\Controls_Manager::SELECT,
				'default'     => '',
				'options'     => $spots,
				'description' => __( 'Pick the same spot on several pages to show the same promotions on each. New spots are added on the Promotions screen.', 'rapm' ),
			)
		);

		$this->add_control(
			'kind',
			array(
				'label'     => __( 'Type', 'rapm' ),
				'type'      => \Elementor\Controls_Manager::SELECT,
				'default'   => 'hero',
				'options'   => array(
					'hero'        => __( 'Slider', 'rapm' ),
					'fold_banner' => __( 'Feature banner', 'rapm' ),
				),
				'condition' => array( 'spot' => '' ),
			)
		);

		$this->add_control(
			'placement',
			array(
				'label'       => __( 'Placement code', 'rapm' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => 'default',
				'description' => __( 'Only for widgets made before spots had names. Picking a spot above replaces this.', 'rapm' ),
				'condition'   => array( 'spot' => '' ),
			)
		);

		$this->add_control(
			'max_slides',
			array(
				'label'       => __( 'Show at most', 'rapm' ),
				'type'        => \Elementor\Controls_Manager::NUMBER,
				'min'         => 0,
				'max'         => 20,
				'step'        => 1,
				'default'     => 0,
				'description' => __( 'The most promotions this carousel will show. It shows the first ones that are live right now, starting from the top of the Sliders list. 0 shows them all.', 'rapm' ),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Files Elementor should load wherever this widget is used, including
	 * popups and Theme Builder templates. The shortcode loads them too
	 * (RAPM_Assets::need()); this lets Elementor's own asset loading cover
	 * it as well.
	 */
	public function get_style_depends() {
		RAPM_Assets::register();
		return array( 'rapm-hero-css' );
	}

	public function get_script_depends() {
		RAPM_Assets::register();
		return array( 'rapm-swiper-js', 'rapm-schedule-js' );
	}

	private function shortcode_string() {
		$settings  = $this->get_settings_for_display();
		$spot      = isset( $settings['spot'] ) ? (string) $settings['spot'] : '';
		$kind      = isset( $settings['kind'] ) ? $settings['kind'] : 'hero';
		$placement = isset( $settings['placement'] ) ? $settings['placement'] : 'default';
		if ( false !== strpos( $spot, '|' ) ) {
			list( $kind, $placement ) = explode( '|', $spot, 2 );
		}
		$tag = 'fold_banner' === $kind ? 'rapm_fold_banner' : 'rapm_hero';
		$max = isset( $settings['max_slides'] ) ? max( 0, (int) $settings['max_slides'] ) : 0;
		return '[' . $tag . ' placement="' . esc_attr( $placement ) . '"' . ( $max ? ' max="' . $max . '"' : '' ) . ']';
	}

	protected function render() {
		echo do_shortcode( $this->shortcode_string() ); // phpcs:ignore WordPress.Security.EscapeOutput
	}

	/**
	 * What Elementor saves as the page's plain-text content. Saving the
	 * shortcode itself (like Elementor's own Shortcode widget does) instead
	 * of the rendered carousel lets has_shortcode() find it and load the
	 * files in <head>, and still shows the carousel if Elementor is ever
	 * switched off.
	 */
	public function render_plain_content() {
		echo $this->shortcode_string(); // phpcs:ignore WordPress.Security.EscapeOutput
	}
}
