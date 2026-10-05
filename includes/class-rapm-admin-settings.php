<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RAPM_Admin_Settings {

	const OPTION = 'rapm_settings';

	public static function defaults() {
		return array(
			'slot_overrides'           => array(),
			'default_autoplay'        => 0,
			'default_autoplay_speed'  => 7000,
			'default_nav_style'       => 'both',
			'default_marquee_items'   => 4,
			'default_marquee_autoplay' => 1,
			'default_marquee_speed'   => 5000,
			'curated_results_page_url' => '',
			// WooCommerce's own native Brands feature (added in WC 8.6)
			// uses this taxonomy name; older sites/plugins commonly use
			// the global attribute taxonomy pa_brand instead — sites on
			// that older convention should set this accordingly.
			'brand_taxonomy'          => 'product_brand',
			// Empty = native WooCommerce search (?s=...&post_type=product).
			// A site running Fast Simon in "Premium" mode replaces the
			// native search page with its own dedicated one (confirmed:
			// their own demo store uses /search-results?q=...) — set both
			// fields below to route "search results for..." links there
			// instead. Sites in Fast Simon "Basic" mode, or with no Fast
			// Simon at all, should leave this blank.
			'search_results_base_url'   => '',
			'search_results_query_param' => 's',
			// Empty = auto-detect from Elementor's Global "Primary",
			// "Accent" or "Secondary" color (the first one white text is
			// readable on), else a neutral default — see
			// RAPM_Elementor::resolve_accent_color_css(). Only needs
			// setting here if that auto-detected color isn't the one a
			// site actually wants used (or the site doesn't run
			// Elementor at all).
			'accent_color'            => '',
		);
	}

	public static function get( $key = null ) {
		$opts = wp_parse_args( get_option( self::OPTION, array() ), self::defaults() );
		if ( $key ) {
			return isset( $opts[ $key ] ) ? $opts[ $key ] : null;
		}
		return $opts;
	}

	public static function add_menu() {
		add_submenu_page(
			'edit.php?post_type=rapm_asset',
			__( 'Promo Manager Settings', 'rapm' ),
			__( 'Settings', 'rapm' ),
			'manage_options',
			'rapm-settings',
			array( __CLASS__, 'render_page' )
		);
	}

	public static function register_settings() {
		register_setting( self::OPTION, self::OPTION, array( __CLASS__, 'sanitize' ) );
		// Stored as their own options, not inside rapm_settings, so the
		// updater can read them at plugin load and so sanitize()'s reset-to-
		// defaults behavior can never wipe a site's token.
		register_setting( self::OPTION, RAPM_Updater::OPTION_TOKEN, array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( self::OPTION, RAPM_Updater::OPTION_CHANNEL, array( 'sanitize_callback' => array( 'RAPM_Updater', 'sanitize_channel' ) ) );
	}

	public static function sanitize( $input ) {
		$out = self::defaults();

		$out['default_autoplay']       = isset( $input['default_autoplay'] ) ? 1 : 0;
		$out['default_autoplay_speed'] = isset( $input['default_autoplay_speed'] ) ? max( 2000, absint( $input['default_autoplay_speed'] ) ) : $out['default_autoplay_speed'];
		$out['default_nav_style']      = isset( $input['default_nav_style'] ) && in_array( $input['default_nav_style'], array( 'both', 'arrows', 'dots', 'none' ), true )
			? $input['default_nav_style']
			: $out['default_nav_style'];
		$out['default_marquee_items']    = isset( $input['default_marquee_items'] ) ? max( 1, min( 5, absint( $input['default_marquee_items'] ) ) ) : $out['default_marquee_items'];
		$out['default_marquee_autoplay'] = isset( $input['default_marquee_autoplay'] ) ? 1 : 0;
		$out['default_marquee_speed']    = isset( $input['default_marquee_speed'] ) ? max( 2000, absint( $input['default_marquee_speed'] ) ) : $out['default_marquee_speed'];

		$out['curated_results_page_url'] = isset( $input['curated_results_page_url'] ) ? esc_url_raw( $input['curated_results_page_url'] ) : '';
		$out['brand_taxonomy']           = isset( $input['brand_taxonomy'] ) && '' !== trim( $input['brand_taxonomy'] )
			? sanitize_key( $input['brand_taxonomy'] )
			: $out['brand_taxonomy'];
		$out['search_results_base_url']   = isset( $input['search_results_base_url'] ) ? esc_url_raw( $input['search_results_base_url'] ) : '';
		$out['search_results_query_param'] = isset( $input['search_results_query_param'] ) && '' !== trim( $input['search_results_query_param'] )
			? sanitize_key( $input['search_results_query_param'] )
			: $out['search_results_query_param'];

		$accent = isset( $input['accent_color'] ) ? trim( $input['accent_color'] ) : '';
		$out['accent_color'] = ( $accent && preg_match( '/^#[0-9a-fA-F]{6}$/', $accent ) ) ? $accent : '';

		$overrides = array();
		if ( isset( $input['slot_overrides'] ) && is_array( $input['slot_overrides'] ) ) {
			foreach ( $input['slot_overrides'] as $slot_key => $fields ) {
				$slot_key = sanitize_key( $slot_key );
				foreach ( array( 'width', 'height', 'max_kb' ) as $field ) {
					if ( isset( $fields[ $field ] ) && '' !== $fields[ $field ] ) {
						$overrides[ $slot_key ][ $field ] = absint( $fields[ $field ] );
					}
				}
				if ( isset( $fields['aspect_ratio_tolerance'] ) && '' !== $fields['aspect_ratio_tolerance'] ) {
					$overrides[ $slot_key ]['aspect_ratio_tolerance'] = max( 0, min( 0.5, (float) $fields['aspect_ratio_tolerance'] ) );
				}
			}
		}
		$out['slot_overrides'] = $overrides;

		return $out;
	}

	public static function render_page() {
		$opts  = self::get();
		$slots = RAPM_Slots::defaults(); // labels/format from code, not merged overrides, for the form's own field labels.
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Promo Manager Settings', 'rapm' ); ?></h1>
			<form method="post" action="options.php">
				<?php settings_fields( self::OPTION ); ?>

				<h2><?php esc_html_e( 'Slot Overrides', 'rapm' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Every slot has a sensible default (e.g. the desktop hero is 1920x600, WebP, under 300KB). Only fill in a field here if this site genuinely needs a different size than that default — leave blank to keep it.', 'rapm' ); ?></p>
				<table class="widefat striped" style="max-width:900px;">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Slot', 'rapm' ); ?></th>
							<th><?php esc_html_e( 'Width (px)', 'rapm' ); ?></th>
							<th><?php esc_html_e( 'Height (px)', 'rapm' ); ?></th>
							<th><?php esc_html_e( 'Max Size (KB)', 'rapm' ); ?></th>
							<th><?php esc_html_e( 'Tolerance (0–0.5)', 'rapm' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $slots as $key => $slot ) : $ov = isset( $opts['slot_overrides'][ $key ] ) ? $opts['slot_overrides'][ $key ] : array(); ?>
							<tr>
								<td><strong><?php echo esc_html( $slot['label'] ); ?></strong><br /><span class="description"><?php echo esc_html( sprintf( '%1$dx%2$d, %3$s, under %4$dKB', $slot['width'], $slot['height'], strtoupper( $slot['format'] ), $slot['max_kb'] ) ); ?></span></td>
								<td><input type="number" min="1" name="<?php echo esc_attr( self::OPTION ); ?>[slot_overrides][<?php echo esc_attr( $key ); ?>][width]" value="<?php echo esc_attr( $ov['width'] ?? '' ); ?>" placeholder="<?php echo esc_attr( $slot['width'] ); ?>" style="width:90px;" /></td>
								<td><input type="number" min="1" name="<?php echo esc_attr( self::OPTION ); ?>[slot_overrides][<?php echo esc_attr( $key ); ?>][height]" value="<?php echo esc_attr( $ov['height'] ?? '' ); ?>" placeholder="<?php echo esc_attr( $slot['height'] ); ?>" style="width:90px;" /></td>
								<td><input type="number" min="1" name="<?php echo esc_attr( self::OPTION ); ?>[slot_overrides][<?php echo esc_attr( $key ); ?>][max_kb]" value="<?php echo esc_attr( $ov['max_kb'] ?? '' ); ?>" placeholder="<?php echo esc_attr( $slot['max_kb'] ); ?>" style="width:90px;" /></td>
								<td><input type="number" min="0" max="0.5" step="0.01" name="<?php echo esc_attr( self::OPTION ); ?>[slot_overrides][<?php echo esc_attr( $key ); ?>][aspect_ratio_tolerance]" value="<?php echo esc_attr( $ov['aspect_ratio_tolerance'] ?? '' ); ?>" placeholder="<?php echo esc_attr( $slot['aspect_ratio_tolerance'] ); ?>" style="width:90px;" /></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<h2><?php esc_html_e( 'Carousel Defaults', 'rapm' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Applied to both [rapm_hero] and [rapm_fold_banner] unless a specific shortcode overrides them with its own attributes. Auto-rotation is off by default — current UX research finds carousels perform best when they don\'t auto-advance past what a visitor is actually looking at.', 'rapm' ); ?></p>
				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'Autoplay', 'rapm' ); ?></th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[default_autoplay]" value="1" <?php checked( $opts['default_autoplay'], 1 ); ?> /> <?php esc_html_e( 'Auto-advance slides', 'rapm' ); ?></label></td>
					</tr>
					<tr>
						<th><label for="rapm_speed"><?php esc_html_e( 'Autoplay Speed (ms)', 'rapm' ); ?></label></th>
						<td><input type="number" min="2000" step="500" id="rapm_speed" name="<?php echo esc_attr( self::OPTION ); ?>[default_autoplay_speed]" value="<?php echo esc_attr( $opts['default_autoplay_speed'] ); ?>" />
							<p class="description"><?php esc_html_e( 'At least 6-8 seconds (6000-8000ms) is recommended if autoplay is on, so a slide is actually readable before it changes.', 'rapm' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="rapm_nav"><?php esc_html_e( 'Navigation Style', 'rapm' ); ?></label></th>
						<td>
							<select id="rapm_nav" name="<?php echo esc_attr( self::OPTION ); ?>[default_nav_style]">
								<option value="both" <?php selected( $opts['default_nav_style'], 'both' ); ?>><?php esc_html_e( 'Arrows & Dots', 'rapm' ); ?></option>
								<option value="arrows" <?php selected( $opts['default_nav_style'], 'arrows' ); ?>><?php esc_html_e( 'Arrows Only', 'rapm' ); ?></option>
								<option value="dots" <?php selected( $opts['default_nav_style'], 'dots' ); ?>><?php esc_html_e( 'Dots Only', 'rapm' ); ?></option>
								<option value="none" <?php selected( $opts['default_nav_style'], 'none' ); ?>><?php esc_html_e( 'None', 'rapm' ); ?></option>
							</select>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Brand Color', 'rapm' ); ?></h2>
				<?php
				$elementor_colors = RAPM_Elementor::color_value_map();
				$elementor_titles = RAPM_Elementor::global_colors();
				$auto_id          = RAPM_Elementor::auto_accent_id();
				?>
				<p class="description">
					<?php if ( $auto_id ) : ?>
						<?php
						printf(
							/* translators: 1: Elementor global color title (e.g. "Accent"), 2: its hex value */
							esc_html__( 'Right now, the Promotions Calendar automatically uses this site\'s Elementor "%1$s" color (%2$s) — nothing to do here unless you want a different color used instead.', 'rapm' ),
							esc_html( $elementor_titles[ $auto_id ] ),
							esc_html( $elementor_colors[ $auto_id ] ?? '' )
						);
						?>
					<?php else : ?>
						<?php esc_html_e( 'No Elementor site color was found to match automatically, so a neutral default color is used. Set one below if you\'d like it to match your brand instead.', 'rapm' ); ?>
					<?php endif; ?>
				</p>
				<table class="form-table">
					<tr>
						<th><label for="rapm_accent_color"><?php esc_html_e( 'Use this color instead', 'rapm' ); ?></label></th>
						<td>
							<input type="color" id="rapm_accent_color" name="<?php echo esc_attr( self::OPTION ); ?>[accent_color]" value="<?php echo esc_attr( $opts['accent_color'] ?: '#b5651d' ); ?>" style="height:32px;width:60px;padding:2px;vertical-align:middle;" />
							<button type="button" class="button" id="rapm_accent_color_clear"><?php esc_html_e( 'Clear (use automatic)', 'rapm' ); ?></button>
							<input type="hidden" id="rapm_accent_color_enabled" value="<?php echo esc_attr( $opts['accent_color'] ? '1' : '0' ); ?>" />
							<p class="description"><?php esc_html_e( 'Only used on the Promotions Calendar for now. Leave cleared to keep it matching your Elementor color automatically.', 'rapm' ); ?></p>
						</td>
					</tr>
				</table>
				<script>
					( function () {
						var enabledField = document.getElementById( 'rapm_accent_color_enabled' );
						var colorField   = document.getElementById( 'rapm_accent_color' );
						var clearBtn     = document.getElementById( 'rapm_accent_color_clear' );
						colorField.addEventListener( 'input', function () { enabledField.value = '1'; } );
						clearBtn.addEventListener( 'click', function () { enabledField.value = '0'; } );
						colorField.form.addEventListener( 'submit', function () {
							if ( '0' === enabledField.value ) {
								colorField.disabled = true; // omits it from the POST entirely, so sanitize() clears it.
							}
						} );
					} )();
				</script>

				<h2><?php esc_html_e( 'Tile Row Defaults', 'rapm' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Applied to [rapm_marquee] unless a specific one overrides it with its own items="..." attribute.', 'rapm' ); ?></p>
				<table class="form-table">
					<tr>
						<th><label for="rapm_marquee_items"><?php esc_html_e( 'Tiles Per Row', 'rapm' ); ?></label></th>
						<td>
							<select id="rapm_marquee_items" name="<?php echo esc_attr( self::OPTION ); ?>[default_marquee_items]">
								<?php for ( $n = 1; $n <= 5; $n++ ) : ?>
									<option value="<?php echo esc_attr( $n ); ?>" <?php selected( (int) $opts['default_marquee_items'], $n ); ?>><?php echo esc_html( $n ); ?></option>
								<?php endfor; ?>
							</select>
							<p class="description"><?php esc_html_e( 'How many tiles show at once. Extra tiles page in on their own, like a carousel, rather than showing all at the same time.', 'rapm' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Auto-Advance', 'rapm' ); ?></th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[default_marquee_autoplay]" value="1" <?php checked( $opts['default_marquee_autoplay'], 1 ); ?> /> <?php esc_html_e( 'Automatically page to the next set of tiles', 'rapm' ); ?></label>
							<p class="description"><?php esc_html_e( 'Visitors can always use the arrow buttons regardless of this setting — this only controls whether it also advances on its own.', 'rapm' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="rapm_marquee_speed"><?php esc_html_e( 'Auto-Advance Speed (ms)', 'rapm' ); ?></label></th>
						<td><input type="number" min="2000" step="500" id="rapm_marquee_speed" name="<?php echo esc_attr( self::OPTION ); ?>[default_marquee_speed]" value="<?php echo esc_attr( $opts['default_marquee_speed'] ); ?>" /></td>
					</tr>
				</table>

				<?php if ( class_exists( 'WooCommerce' ) ) : ?>
					<h2><?php esc_html_e( 'Product Linking', 'rapm' ); ?></h2>
					<p class="description"><?php esc_html_e( 'One-time setup so "A hand-picked list of products" links work on the Add/Edit Asset form. Not needed for the simpler "A specific product" / "A product category" / "Search results" links.', 'rapm' ); ?></p>
					<table class="form-table">
						<tr>
							<th><label for="rapm_curated_page"><?php esc_html_e( 'Curated Results Page', 'rapm' ); ?></label></th>
							<td><input type="url" class="regular-text" id="rapm_curated_page" name="<?php echo esc_attr( self::OPTION ); ?>[curated_results_page_url]" placeholder="https://yoursite.com/featured-products/" value="<?php echo esc_attr( $opts['curated_results_page_url'] ); ?>" />
								<p class="description"><?php esc_html_e( 'Create one plain page with [rapm_curated_results] on it and paste its address here. This one page is reused automatically for every "hand-picked list" link — nothing else to set up per-promotion.', 'rapm' ); ?></p>
							</td>
						</tr>
						<tr>
							<th><label for="rapm_brand_tax"><?php esc_html_e( 'Brand Field Name', 'rapm' ); ?></label></th>
							<td><input type="text" id="rapm_brand_tax" name="<?php echo esc_attr( self::OPTION ); ?>[brand_taxonomy]" value="<?php echo esc_attr( $opts['brand_taxonomy'] ); ?>" />
								<p class="description"><?php esc_html_e( 'Only change this if "A specific brand" doesn\'t show up as a link option, or shows the wrong list of brands — it means this site stores brands differently than the default. Ask whoever manages the website if you\'re not sure.', 'rapm' ); ?></p>
							</td>
						</tr>
						<tr>
							<th><label for="rapm_search_base"><?php esc_html_e( 'Search Results Page', 'rapm' ); ?></label></th>
							<td><input type="url" class="regular-text" id="rapm_search_base" name="<?php echo esc_attr( self::OPTION ); ?>[search_results_base_url]" placeholder="<?php esc_attr_e( 'Leave blank for the normal site search', 'rapm' ); ?>" value="<?php echo esc_attr( $opts['search_results_base_url'] ); ?>" />
								<p class="description"><?php esc_html_e( 'Only fill this in if this site has a separate, dedicated search results page from a tool like Fast Simon (rather than using the normal WordPress search). Leave blank otherwise — that\'s the right setting for most sites.', 'rapm' ); ?></p>
							</td>
						</tr>
					</table>
				<?php endif; ?>

				<h2><?php esc_html_e( 'Auto-Update Settings', 'rapm' ); ?></h2>
				<?php
				$channel_locked = defined( 'RAPM_UPDATE_CHANNEL' ) && RAPM_UPDATE_CHANNEL;
				$token_locked   = defined( 'RAPM_GITHUB_UPDATE_TOKEN' ) && RAPM_GITHUB_UPDATE_TOKEN;
				?>
				<p class="description"><?php esc_html_e( 'Lets this site find and install new versions of Promo Manager on its own, instead of someone uploading a zip file every time. Needs a one-time GitHub access key — ask R&A Marketing for it if you don\'t have it.', 'rapm' ); ?></p>
				<table class="form-table">
					<tr>
						<th><label for="rapm_update_channel"><?php esc_html_e( 'Update Channel', 'rapm' ); ?></label></th>
						<td>
							<select id="rapm_update_channel" name="<?php echo esc_attr( RAPM_Updater::OPTION_CHANNEL ); ?>" <?php disabled( $channel_locked ); ?>>
								<option value="production" <?php selected( RAPM_Updater::channel(), 'production' ); ?>><?php esc_html_e( 'Production (default — only gets updates that were already tested)', 'rapm' ); ?></option>
								<option value="staging" <?php selected( RAPM_Updater::channel(), 'staging' ); ?>><?php esc_html_e( 'Staging (gets new updates first, for testing)', 'rapm' ); ?></option>
							</select>
							<?php if ( $channel_locked ) : ?>
								<p class="description"><?php esc_html_e( 'Set in wp-config.php, which takes priority — this dropdown can\'t be changed here.', 'rapm' ); ?></p>
							<?php else : ?>
								<p class="description"><strong><?php esc_html_e( 'Leave this on "Production" for every real, live website.', 'rapm' ); ?></strong> <?php esc_html_e( 'Only pick "Staging" on the one test site used to check an update before it goes out to everyone else.', 'rapm' ); ?></p>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th><label for="rapm_github_token"><?php esc_html_e( 'GitHub Update Key', 'rapm' ); ?></label></th>
						<td>
							<input type="password" class="regular-text" id="rapm_github_token" name="<?php echo esc_attr( RAPM_Updater::OPTION_TOKEN ); ?>" value="<?php echo esc_attr( get_option( RAPM_Updater::OPTION_TOKEN, '' ) ); ?>" autocomplete="off" placeholder="github_pat_..." />
							<?php if ( $token_locked ) : ?>
								<p class="description"><?php esc_html_e( 'A key set in wp-config.php is already active and is used instead of this field.', 'rapm' ); ?></p>
							<?php else : ?>
								<p class="description"><?php esc_html_e( 'Paste the key here once. It can only read Promo Manager\'s own code — it can\'t see or change anything else.', 'rapm' ); ?></p>
							<?php endif; ?>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>

			<h2><?php esc_html_e( 'Shortcodes', 'rapm' ); ?></h2>
			<table class="widefat striped" style="max-width:800px;">
				<tr><td><code>[rapm_hero]</code></td><td><?php esc_html_e( 'Full hero carousel for the "default" placement.', 'rapm' ); ?></td></tr>
				<tr><td><code>[rapm_fold_banner]</code></td><td><?php esc_html_e( 'The shorter fold-banner carousel for the "default" placement — a separate kind of asset from the hero, added the same way under Promo > Add New Asset with "Kind" set to Fold Banner.', 'rapm' ); ?></td></tr>
				<tr><td><code>[rapm_hero placement="category-living-room"]</code></td><td><?php esc_html_e( 'A separate carousel scoped to just that placement — set the same placement value when adding assets. Works the same way for [rapm_fold_banner].', 'rapm' ); ?></td></tr>
				<tr><td><code>[rapm_hero placement="home" max="3"]</code></td><td><?php esc_html_e( 'Shows only the first 3 promotions that are live right now, starting from the top of the Sliders list. Leave max out (or set it to 0) to show them all. Works the same way for [rapm_fold_banner]. The Promo Carousel widget in Elementor has the same setting, called "Show at most."', 'rapm' ); ?></td></tr>
				<?php if ( class_exists( 'WooCommerce' ) ) : ?>
					<tr><td><code>[rapm_curated_results]</code></td><td><?php esc_html_e( 'Put this on the one page set as "Curated Results Page" above. Renders whichever hand-picked product list + fill-in results a given asset\'s link points to — nothing to configure on the page itself.', 'rapm' ); ?></td></tr>
				<?php endif; ?>
				<tr><td><code>[rapm_marquee]</code></td><td><?php esc_html_e( 'A compact row of square tiles for the "default" placement — added under Add New Asset with "Type of Promotion" set to Marquee. Add items="3" (1 to 5) to change how many show at once; extras page in like a carousel. Defaults set below.', 'rapm' ); ?></td></tr>
				<tr><td><code>[rapm_coupon_book]</code></td><td><?php esc_html_e( 'A horizontal scrolling row of coupon cards for the "default" placement — added the same way with "Type of Promotion" set to Coupon.', 'rapm' ); ?></td></tr>
				<tr><td><code>[rapm_promotions_calendar]</code></td><td><?php esc_html_e( 'A month calendar showing every promotion that has both a start and end date set, across all types. Add placement="..." or kind="..." to show only a specific one.', 'rapm' ); ?></td></tr>
			</table>
		</div>
		<?php
	}
}
