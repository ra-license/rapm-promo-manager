<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A "Sliders" dashboard — the missing piece behind the plugin's Placement
 * confusion. A Placement was always real (it groups assets into one
 * rotating carousel) but had no screen of its own; this makes each
 * Kind+Placement combination a visible, manageable thing: a card with a
 * thumbnail, a slide count, its shortcode, and a detail view for
 * reordering (drag-and-drop, persisted via menu_order — the same field
 * every RAPM_*_Carousel/Book/Marquee query already orders by) and
 * managing just that slider's own slides.
 */
class RAPM_Sliders_Dashboard {

	public static function add_menu() {
		add_submenu_page(
			'edit.php?post_type=rapm_asset',
			__( 'Sliders', 'rapm' ),
			__( 'Sliders', 'rapm' ),
			'edit_posts',
			'rapm-sliders',
			array( __CLASS__, 'render_page' )
		);
	}

	public static function render_page() {
		$kind_key  = isset( $_GET['kind'] ) ? sanitize_key( wp_unslash( $_GET['kind'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$placement = isset( $_GET['placement'] ) ? sanitize_title( wp_unslash( $_GET['placement'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		echo '<div class="wrap">';
		if ( $kind_key && $placement ) {
			self::render_detail( $kind_key, $placement );
		} else {
			self::render_grid();
		}
		echo '</div>';
	}

	/**
	 * Every rapm_asset, grouped by its Kind+Placement — a "slider" has no
	 * dedicated row of its own in the database, it only ever exists as
	 * this grouping, computed fresh each time.
	 */
	private static function get_all_sliders() {
		$posts = get_posts(
			array(
				'post_type'      => 'rapm_asset',
				'post_status'    => array( 'publish', 'draft', 'future', 'pending' ),
				'posts_per_page' => -1,
				'orderby'        => 'menu_order date',
				'order'          => 'ASC',
			)
		);

		$sliders = array();
		foreach ( $posts as $post ) {
			$kind_key      = get_post_meta( $post->ID, '_rapm_kind', true ) ?: 'hero'; // phpcs:ignore
			$placement_key = get_post_meta( $post->ID, '_rapm_placement', true ) ?: 'default'; // phpcs:ignore
			$group_key     = $kind_key . '|' . $placement_key;
			if ( ! isset( $sliders[ $group_key ] ) ) {
				$sliders[ $group_key ] = array(
					'kind_key'  => $kind_key,
					'placement' => $placement_key,
					'posts'     => array(),
				);
			}
			$sliders[ $group_key ]['posts'][] = $post;
		}
		return $sliders;
	}

	private static function render_grid() {
		$sliders = self::get_all_sliders();
		$kinds   = RAPM_Slots::kinds();
		?>
		<h1 class="wp-heading-inline"><?php esc_html_e( 'Sliders', 'rapm' ); ?></h1>
		<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=rapm_asset&page=rapm-add-asset' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Add New Asset', 'rapm' ); ?></a>
		<p class="description" style="max-width:640px;margin:12px 0 24px;"><?php esc_html_e( 'Each card below is one rotating carousel — every promotion inside it shares the same "Which Spot on the Site" value and takes turns rotating together. Click a card to see and reorder its slides.', 'rapm' ); ?></p>

		<div class="rapm-calendar-promo">
			<div class="rapm-calendar-promo-body">
				<h3><?php esc_html_e( 'Promotions Calendar', 'rapm' ); ?></h3>
				<p class="description"><?php esc_html_e( 'A different way to show your promotions — a month calendar visitors can browse, instead of a rotating carousel. It automatically includes any promotion (any type) that has both a start date and an end date set, and updates itself with no extra work whenever you add, edit, or reschedule one.', 'rapm' ); ?></p>
				<p><?php esc_html_e( 'Paste this code onto any page to add it there:', 'rapm' ); ?> <code>[rapm_promotions_calendar]</code></p>
				<p class="description"><?php esc_html_e( 'Want just one type, or one spot? Add placement="..." or kind="..." inside the brackets to narrow it down.', 'rapm' ); ?></p>
			</div>
			<div class="rapm-calendar-promo-count">
				<span class="rapm-calendar-promo-num"><?php echo esc_html( RAPM_Calendar::count_scheduled() ); ?></span>
				<span class="rapm-calendar-promo-label"><?php esc_html_e( 'promotions currently show on it', 'rapm' ); ?></span>
			</div>
		</div>

		<?php if ( ! $sliders ) : ?>
			<p><?php esc_html_e( 'No promotions yet.', 'rapm' ); ?> <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=rapm_asset&page=rapm-add-asset' ) ); ?>"><?php esc_html_e( 'Add your first one.', 'rapm' ); ?></a></p>
		<?php else : ?>
		<div class="rapm-slider-grid">
			<?php
			foreach ( $sliders as $group ) :
				$kind_info  = isset( $kinds[ $group['kind_key'] ] ) ? $kinds[ $group['kind_key'] ] : $kinds['hero'];
				$thumb_id   = 0;
				$live_count = 0;
				foreach ( $group['posts'] as $p ) {
					if ( ! $thumb_id ) {
						$thumb_id = (int) get_post_meta( $p->ID, '_rapm_image_desktop_id', true );
					}
					if ( 'publish' === $p->post_status ) {
						$starts = get_post_meta( $p->ID, '_rapm_starts_at', true );
						$ends   = get_post_meta( $p->ID, '_rapm_ends_at', true );
						$now    = current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp
						if ( ( ! $starts || strtotime( $starts ) <= $now ) && ( ! $ends || strtotime( $ends ) >= $now ) && false === RAPM_Between::waiting( $p ) ) { // 1.69.0: not a between-sales slide that's waiting.
							++$live_count;
						}
					}
				}
				$shortcode_text = 'default' === $group['placement']
					? '[' . $kind_info['shortcode'] . ']'
					: '[' . $kind_info['shortcode'] . ' placement="' . $group['placement'] . '"]';
				$detail_url     = add_query_arg(
					array(
						'page'      => 'rapm-sliders',
						'kind'      => $group['kind_key'],
						'placement' => $group['placement'],
					),
					admin_url( 'edit.php?post_type=rapm_asset' )
				);
				$add_slide_url  = add_query_arg(
					array(
						'page'      => 'rapm-add-asset',
						'kind'      => $group['kind_key'],
						'placement' => $group['placement'],
					),
					admin_url( 'edit.php?post_type=rapm_asset' )
				);
				?>
				<div class="rapm-slider-card">
					<a href="<?php echo esc_url( $detail_url ); ?>" class="rapm-slider-card-thumb">
						<?php if ( $thumb_id ) : ?>
							<?php echo wp_get_attachment_image( $thumb_id, array( 320, 100 ), false, array( 'style' => 'width:100%;height:100px;object-fit:cover;display:block;' ) ); ?>
						<?php else : ?>
							<span class="rapm-slider-card-noimg"><?php esc_html_e( 'No image', 'rapm' ); ?></span>
						<?php endif; ?>
					</a>
					<div class="rapm-slider-card-body">
						<h3><a href="<?php echo esc_url( $detail_url ); ?>"><?php echo esc_html( $kind_info['label'] ); ?> — <?php echo esc_html( $group['placement'] ); ?></a></h3>
						<p class="description">
							<?php
							echo esc_html(
								sprintf(
									/* translators: 1: total slide count, 2: how many are currently live */
									_n( '%1$d promotion (%2$d live now)', '%1$d promotions (%2$d live now)', count( $group['posts'] ), 'rapm' ),
									count( $group['posts'] ),
									$live_count
								)
							);
							?>
						</p>
						<p><code><?php echo esc_html( $shortcode_text ); ?></code></p>
						<p class="rapm-slider-card-actions">
							<a href="<?php echo esc_url( $detail_url ); ?>" class="button button-small"><?php esc_html_e( 'Manage Slides', 'rapm' ); ?></a>
							<a href="<?php echo esc_url( $add_slide_url ); ?>" class="button button-small"><?php esc_html_e( 'Add Slide', 'rapm' ); ?></a>
						</p>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
		<?php endif; ?>
		<style>
			.rapm-calendar-promo { display: flex; flex-wrap: wrap; gap: 16px; align-items: center; justify-content: space-between; background: #f0f6fc; border-left: 4px solid #72aee6; border-radius: 4px; padding: 14px 18px; margin-bottom: 22px; }
			.rapm-calendar-promo-body { flex: 1 1 420px; min-width: 280px; }
			.rapm-calendar-promo-body h3 { margin: 0 0 6px; font-size: 14px; }
			.rapm-calendar-promo-body p { margin: 0 0 6px; font-size: 12.5px; }
			.rapm-calendar-promo-body p:last-child { margin-bottom: 0; }
			.rapm-calendar-promo-count { flex: none; text-align: center; padding: 6px 20px; }
			.rapm-calendar-promo-num { display: block; font-size: 28px; font-weight: 600; line-height: 1; color: #2271b1; }
			.rapm-calendar-promo-label { display: block; font-size: 11.5px; color: #646970; max-width: 110px; margin-top: 4px; }
			.rapm-slider-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 16px; margin-top: 8px; }
			.rapm-slider-card { background: #fff; border: 1px solid #dcdcde; border-radius: 6px; overflow: hidden; }
			.rapm-slider-card-thumb { display: block; background: #f0f0f1; }
			.rapm-slider-card-noimg { display: flex; align-items: center; justify-content: center; height: 100px; color: #999; font-style: italic; font-size: 13px; }
			.rapm-slider-card-body { padding: 12px 14px; }
			.rapm-slider-card-body h3 { margin: 0 0 6px; font-size: 14px; }
			.rapm-slider-card-body h3 a { text-decoration: none; }
			.rapm-slider-card-body .description { margin: 0 0 6px; font-size: 12.5px; }
			.rapm-slider-card-actions { margin-top: 10px; }
		</style>
		<?php
	}

	private static function render_detail( $kind_key, $placement ) {
		$kinds     = RAPM_Slots::kinds();
		$kind_info = isset( $kinds[ $kind_key ] ) ? $kinds[ $kind_key ] : $kinds['hero'];

		$posts = get_posts(
			array(
				'post_type'      => 'rapm_asset',
				'post_status'    => array( 'publish', 'draft', 'future', 'pending' ),
				'posts_per_page' => -1,
				'orderby'        => 'menu_order date',
				'order'          => 'ASC',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
					'relation' => 'AND',
					array( 'key' => '_rapm_kind', 'value' => $kind_key ),
					array( 'key' => '_rapm_placement', 'value' => $placement ),
				),
			)
		);

		$shortcode_text = 'default' === $placement
			? '[' . $kind_info['shortcode'] . ']'
			: '[' . $kind_info['shortcode'] . ' placement="' . $placement . '"]';
		$add_slide_url  = add_query_arg(
			array(
				'page'      => 'rapm-add-asset',
				'kind'      => $kind_key,
				'placement' => $placement,
			),
			admin_url( 'edit.php?post_type=rapm_asset' )
		);
		$back_url       = admin_url( 'edit.php?post_type=rapm_asset&page=rapm-sliders' );
		?>
		<p><a href="<?php echo esc_url( $back_url ); ?>">&larr; <?php esc_html_e( 'All Sliders', 'rapm' ); ?></a></p>
		<h1 class="wp-heading-inline"><?php echo esc_html( $kind_info['label'] ); ?> — <?php echo esc_html( $placement ); ?></h1>
		<a href="<?php echo esc_url( $add_slide_url ); ?>" class="page-title-action"><?php esc_html_e( 'Add Slide', 'rapm' ); ?></a>
		<p class="description" style="max-width:640px;margin:12px 0 20px;"><?php esc_html_e( 'Drag a slide to change the order it plays in.', 'rapm' ); ?> <?php esc_html_e( 'This code shows them on the website:', 'rapm' ); ?> <code><?php echo esc_html( $shortcode_text ); ?></code></p>

		<?php if ( ! $posts ) : ?>
			<p><?php esc_html_e( 'No slides in this slider yet.', 'rapm' ); ?> <a href="<?php echo esc_url( $add_slide_url ); ?>"><?php esc_html_e( 'Add one.', 'rapm' ); ?></a></p>
		<?php else : ?>
		<ul id="rapm-slider-slide-list" class="rapm-slide-list">
			<?php
			foreach ( $posts as $post ) :
				$thumb_id = (int) get_post_meta( $post->ID, '_rapm_image_desktop_id', true );
				$edit_url = admin_url( 'edit.php?post_type=rapm_asset&page=rapm-add-asset&edit=' . $post->ID );
				$dup_url  = wp_nonce_url( admin_url( 'admin-post.php?action=rapm_duplicate_asset&rapm_duplicate=' . $post->ID ), 'rapm_duplicate_' . $post->ID );
				?>
				<li class="rapm-slide-row" data-id="<?php echo esc_attr( $post->ID ); ?>">
					<span class="rapm-slide-drag" aria-hidden="true">&#9776;</span>
					<span class="rapm-slide-thumb">
						<?php if ( $thumb_id ) : ?>
							<?php echo wp_get_attachment_image( $thumb_id, array( 70, 44 ), false, array( 'style' => 'width:70px;height:44px;object-fit:cover;display:block;border-radius:3px;' ) ); ?>
						<?php else : ?>
							<span class="rapm-slide-noimg"></span>
						<?php endif; ?>
					</span>
					<span class="rapm-slide-title">
						<?php echo esc_html( get_the_title( $post ) ); ?>
						<?php if ( 'publish' !== $post->post_status ) : ?>
							<span class="rapm-slide-draft-pill"><?php echo esc_html( ucfirst( $post->post_status ) ); ?></span>
						<?php endif; ?>
					</span>
					<span class="rapm-slide-actions">
						<a href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Edit', 'rapm' ); ?></a>
						<a href="<?php echo esc_url( $dup_url ); ?>"><?php esc_html_e( 'Duplicate', 'rapm' ); ?></a>
						<a href="<?php echo esc_url( get_delete_post_link( $post->ID ) ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Move this to the trash?', 'rapm' ) ); ?>');"><?php esc_html_e( 'Trash', 'rapm' ); ?></a>
					</span>
				</li>
			<?php endforeach; ?>
		</ul>
		<p class="description" id="rapm-reorder-status"></p>
		<?php endif; ?>
		<style>
			.rapm-slide-list { list-style: none; margin: 0; padding: 0; max-width: 640px; }
			.rapm-slide-row { display: flex; align-items: center; gap: 12px; background: #fff; border: 1px solid #dcdcde; border-radius: 4px; padding: 8px 12px; margin-bottom: 6px; cursor: grab; }
			.rapm-slide-row.is-dragging { opacity: 0.4; }
			.rapm-slide-drag { color: #a7aaad; font-size: 16px; flex: none; }
			.rapm-slide-thumb { flex: none; width: 70px; height: 44px; }
			.rapm-slide-noimg { display: block; width: 70px; height: 44px; background: #f0f0f1; border-radius: 3px; }
			.rapm-slide-title { flex: 1; font-size: 13px; }
			.rapm-slide-draft-pill { display: inline-block; margin-left: 6px; font-size: 11px; padding: 1px 7px; border-radius: 100px; background: #f0f0f1; color: #646970; text-transform: uppercase; }
			.rapm-slide-actions { flex: none; display: flex; gap: 10px; font-size: 12.5px; }
		</style>
		<script>
			( function () {
				var list = document.getElementById( 'rapm-slider-slide-list' );
				if ( ! list ) { return; }
				var status  = document.getElementById( 'rapm-reorder-status' );
				var ajaxUrl = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
				var nonce   = <?php echo wp_json_encode( wp_create_nonce( 'rapm_reorder_slides' ) ); ?>;
				var dragEl  = null;

				function getDragAfterElement( container, y ) {
					var rows = Array.prototype.slice.call( container.querySelectorAll( '.rapm-slide-row:not(.is-dragging)' ) );
					return rows.reduce( function ( closest, row ) {
						var box    = row.getBoundingClientRect();
						var offset = y - box.top - box.height / 2;
						if ( offset < 0 && offset > closest.offset ) {
							return { offset: offset, element: row };
						}
						return closest;
					}, { offset: -Infinity } ).element;
				}

				function saveOrder() {
					var ids      = Array.prototype.map.call( list.querySelectorAll( '.rapm-slide-row' ), function ( row ) { return row.getAttribute( 'data-id' ); } );
					var formData = new FormData();
					formData.append( 'action', 'rapm_reorder_slides' );
					formData.append( 'nonce', nonce );
					ids.forEach( function ( id ) { formData.append( 'order[]', id ); } );
					status.textContent = <?php echo wp_json_encode( __( 'Saving order…', 'rapm' ) ); ?>;
					fetch( ajaxUrl, { method: 'POST', body: formData } ).then( function ( r ) { return r.json(); } ).then( function ( res ) {
						status.textContent = res.success ? <?php echo wp_json_encode( __( 'Order saved.', 'rapm' ) ); ?> : <?php echo wp_json_encode( __( 'Could not save the new order — please try again.', 'rapm' ) ); ?>;
					} ).catch( function () {
						status.textContent = <?php echo wp_json_encode( __( 'Could not save the new order — please try again.', 'rapm' ) ); ?>;
					} );
				}

				Array.prototype.forEach.call( list.querySelectorAll( '.rapm-slide-row' ), function ( row ) {
					row.setAttribute( 'draggable', 'true' );
					row.addEventListener( 'dragstart', function () {
						dragEl = row;
						row.classList.add( 'is-dragging' );
					} );
					row.addEventListener( 'dragend', function () {
						row.classList.remove( 'is-dragging' );
						dragEl = null;
						saveOrder();
					} );
				} );
				list.addEventListener( 'dragover', function ( e ) {
					e.preventDefault();
					if ( ! dragEl ) { return; }
					var after = getDragAfterElement( list, e.clientY );
					if ( null === after ) {
						list.appendChild( dragEl );
					} else {
						list.insertBefore( dragEl, after );
					}
				} );
			} )();
		</script>
		<?php
	}

	/**
	 * Persists a new drag-and-drop order as menu_order — the same field
	 * RAPM_Hero_Carousel, RAPM_Marquee, and RAPM_Coupon_Book already sort
	 * by ('orderby' => 'menu_order date'), so this is the one place that
	 * needed to exist for reordering to actually work end to end.
	 */
	public static function ajax_reorder() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Not allowed.', 'rapm' ) ), 403 );
		}
		check_ajax_referer( 'rapm_reorder_slides', 'nonce' );

		$order = isset( $_POST['order'] ) ? array_map( 'absint', (array) $_POST['order'] ) : array();
		foreach ( $order as $index => $post_id ) {
			if ( $post_id && 'rapm_asset' === get_post_type( $post_id ) ) {
				wp_update_post(
					array(
						'ID'         => $post_id,
						'menu_order' => $index,
					)
				);
			}
		}
		wp_send_json_success();
	}
}
