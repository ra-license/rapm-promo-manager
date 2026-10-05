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

		$this->add_control(
			'kind',
			array(
				'label'   => __( 'Kind', 'rapm' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'hero',
				'options' => array(
					'hero'        => __( 'Hero (full carousel)', 'rapm' ),
					'fold_banner' => __( 'Fold Banner (shorter, near the fold)', 'rapm' ),
				),
			)
		);

		$this->add_control(
			'placement',
			array(
				'label'       => __( 'Placement', 'rapm' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => 'default',
				'description' => __( 'Matches the "Placement" field set on each asset under Promo > Add New Asset.', 'rapm' ),
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
		$settings = $this->get_settings_for_display();
		$tag      = 'fold_banner' === $settings['kind'] ? 'rapm_fold_banner' : 'rapm_hero';
		$max      = isset( $settings['max_slides'] ) ? max( 0, (int) $settings['max_slides'] ) : 0;
		return '[' . $tag . ' placement="' . esc_attr( $settings['placement'] ) . '"' . ( $max ? ' max="' . $max . '"' : '' ) . ']';
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
