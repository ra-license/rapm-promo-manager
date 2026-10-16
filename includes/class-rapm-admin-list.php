<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RAPM_Admin_List {

	public static function columns( $columns ) {
		$new = array();
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new['rapm_preview']   = __( 'Preview', 'rapm' );
				$new['rapm_kind']      = __( 'Kind', 'rapm' );
				$new['rapm_placement'] = __( 'Shortcode', 'rapm' );
				$new['rapm_status']    = __( 'Status', 'rapm' );
				$new['rapm_edit']      = __( 'Edit', 'rapm' );
			}
		}
		return $new;
	}

	public static function column_content( $column, $post_id ) {
		switch ( $column ) {
			case 'rapm_preview':
				$desktop_id = (int) get_post_meta( $post_id, '_rapm_image_desktop_id', true );
				if ( $desktop_id ) {
					echo wp_get_attachment_image( $desktop_id, array( 90, 30 ), false, array( 'style' => 'display:block;border-radius:4px;object-fit:cover;' ) );
				} else {
					echo '<span style="color:#999;font-style:italic;">' . esc_html__( 'No image', 'rapm' ) . '</span>';
				}
				break;

			case 'rapm_kind':
				$kind_key = get_post_meta( $post_id, '_rapm_kind', true ) ?: 'hero'; // phpcs:ignore
				$kind     = RAPM_Slots::kind( $kind_key );
				echo esc_html( $kind['label'] );
				break;

			case 'rapm_placement':
				$kind_key      = get_post_meta( $post_id, '_rapm_kind', true ) ?: 'hero'; // phpcs:ignore
				$kind          = RAPM_Slots::kind( $kind_key );
				$placement_key = get_post_meta( $post_id, '_rapm_placement', true ) ?: 'default'; // phpcs:ignore
				$shortcode     = 'default' === $placement_key
					? '[' . $kind['shortcode'] . ']'
					: '[' . $kind['shortcode'] . ' placement="' . $placement_key . '"]';
				echo '<code>' . esc_html( $shortcode ) . '</code>';
				break;

			case 'rapm_status':
				echo esc_html( self::schedule_status_label( $post_id ) );
				if ( get_post_meta( $post_id, '_rapm_image_desktop_sync_error', true ) || get_post_meta( $post_id, '_rapm_image_mobile_sync_error', true ) ) {
					echo ' <span style="color:#b32d2e;" title="' . esc_attr__( 'A linked picture stopped updating — the last one that worked is still showing. Edit this asset for details.', 'rapm' ) . '">&#9888; ' . esc_html__( 'Link issue', 'rapm' ) . '</span>';
				}
				break;

			case 'rapm_edit':
				echo '<a href="' . esc_url( admin_url( 'edit.php?post_type=rapm_asset&page=rapm-add-asset&edit=' . $post_id ) ) . '" class="button button-small">' . esc_html__( 'Edit', 'rapm' ) . '</a>';
				break;
		}
	}

	private static function schedule_status_label( $post_id ) {
		$starts = get_post_meta( $post_id, '_rapm_starts_at', true );
		$ends   = get_post_meta( $post_id, '_rapm_ends_at', true );
		$now    = current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp

		if ( $starts && strtotime( $starts ) > $now ) {
			return __( 'Scheduled', 'rapm' );
		}
		if ( $ends && strtotime( $ends ) < $now ) {
			return __( 'Expired', 'rapm' );
		}
		return __( 'Live', 'rapm' );
	}

	/**
	 * "All Assets" grows fast once a site has more than one or two
	 * sliders — these two dropdowns let someone jump straight to
	 * everything of one Kind, or everything sharing one Placement,
	 * instead of scanning the whole list by eye.
	 */
	public static function restrict_manage_posts() {
		global $typenow, $wpdb;
		if ( 'rapm_asset' !== $typenow ) {
			return;
		}

		$current_kind = isset( $_GET['rapm_filter_kind'] ) ? sanitize_key( wp_unslash( $_GET['rapm_filter_kind'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<select name="rapm_filter_kind"><option value="">' . esc_html__( 'All Types', 'rapm' ) . '</option>';
		foreach ( RAPM_Slots::kinds() as $key => $info ) {
			echo '<option value="' . esc_attr( $key ) . '" ' . selected( $current_kind, $key, false ) . '>' . esc_html( $info['label'] ) . '</option>';
		}
		echo '</select>';

		$current_placement = isset( $_GET['rapm_filter_placement'] ) ? sanitize_title( wp_unslash( $_GET['rapm_filter_placement'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$placements         = $wpdb->get_col(
			"SELECT DISTINCT pm.meta_value FROM {$wpdb->postmeta} pm
			 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			 WHERE pm.meta_key = '_rapm_placement' AND p.post_type = 'rapm_asset' AND pm.meta_value != ''
			 ORDER BY pm.meta_value ASC"
		);
		if ( $placements ) {
			echo '<select name="rapm_filter_placement"><option value="">' . esc_html__( 'All Spots', 'rapm' ) . '</option>';
			foreach ( $placements as $p ) {
				echo '<option value="' . esc_attr( $p ) . '" ' . selected( $current_placement, $p, false ) . '>' . esc_html( $p ) . '</option>';
			}
			echo '</select>';
		}
	}

	public static function filter_query( $query ) {
		global $pagenow, $typenow;
		if ( ! is_admin() || 'edit.php' !== $pagenow || 'rapm_asset' !== $typenow || ! $query->is_main_query() ) {
			return;
		}

		$meta_query = array(); // phpcs:ignore WordPress.DB.SlowDBQuery
		if ( ! empty( $_GET['rapm_filter_kind'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$meta_query[] = array( 'key' => '_rapm_kind', 'value' => sanitize_key( wp_unslash( $_GET['rapm_filter_kind'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
		if ( ! empty( $_GET['rapm_filter_placement'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$meta_query[] = array( 'key' => '_rapm_placement', 'value' => sanitize_title( wp_unslash( $_GET['rapm_filter_placement'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
		if ( $meta_query ) {
			if ( count( $meta_query ) > 1 ) {
				$meta_query['relation'] = 'AND';
			}
			$query->set( 'meta_query', $meta_query );
		}
	}

	/**
	 * A "Duplicate" row action — building a fifth slide similar to the
	 * fourth previously meant starting from a blank form every time.
	 * Starts the copy as a draft (never shown anywhere — every renderer
	 * only queries publish posts) so it can be reviewed before it's live,
	 * identical to the original it was copied from.
	 */
	public static function row_actions( $actions, $post ) {
		if ( 'rapm_asset' !== $post->post_type || ! current_user_can( 'edit_posts' ) ) {
			return $actions;
		}

		// "Quick Edit" is WordPress's own native inline editor — title,
		// slug, date, status. None of this post type's real fields
		// (image, headline, destination, schedule) live there, so it's
		// the same trap as the native post-edit screen fixed below: a
		// second, different-looking "edit" path that silently doesn't
		// touch what someone actually came to change.
		unset( $actions['inline hide-if-no-js'] );

		$url                   = wp_nonce_url(
			admin_url( 'admin-post.php?action=rapm_duplicate_asset&rapm_duplicate=' . $post->ID ),
			'rapm_duplicate_' . $post->ID
		);
		$actions['rapm_duplicate'] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Duplicate', 'rapm' ) . '</a>';
		return $actions;
	}

	/**
	 * WordPress builds both the post title's own link and the default
	 * "Edit" row action from get_edit_post_link() — pointing both at
	 * post.php?action=edit, the bare native editor this post type
	 * deliberately has no real fields on (see class-rapm-post-types.php).
	 * The dedicated "Edit" button added by the rapm_edit column above
	 * already linked to the real form; this makes every other built-in
	 * "Edit" entry point resolve to the exact same URL instead of a
	 * second, silently non-functional one.
	 */
	public static function filter_edit_post_link( $link, $post_id ) {
		if ( 'rapm_asset' !== get_post_type( $post_id ) ) {
			return $link;
		}
		return admin_url( 'edit.php?post_type=rapm_asset&page=rapm-add-asset&edit=' . $post_id );
	}

	/**
	 * A copy of a promotion, turned off, with all its settings (1.48.0:
	 * shared with the Design Center's Asset library). Returns the new ID,
	 * or 0.
	 */
	public static function duplicate( $asset_id ) {
		$original = get_post( $asset_id );
		if ( ! $original || 'rapm_asset' !== $original->post_type ) {
			return 0;
		}
		$new_id = wp_insert_post(
			array(
				/* translators: %s: the original asset's name */
				'post_title'  => sprintf( __( '%s (Copy)', 'rapm' ), $original->post_title ),
				'post_type'   => 'rapm_asset',
				'post_status' => 'draft',
				'menu_order'  => $original->menu_order,
			)
		);
		if ( is_wp_error( $new_id ) || ! $new_id ) {
			return 0;
		}
		foreach ( get_post_meta( $asset_id ) as $key => $values ) {
			if ( 0 !== strpos( $key, '_rapm_' ) ) {
				continue;
			}
			foreach ( $values as $value ) {
				add_post_meta( $new_id, $key, maybe_unserialize( $value ) );
			}
		}
		return (int) $new_id;
	}

	public static function handle_duplicate() {
		$asset_id = isset( $_GET['rapm_duplicate'] ) ? absint( $_GET['rapm_duplicate'] ) : 0;
		if ( ! $asset_id || 'rapm_asset' !== get_post_type( $asset_id ) || ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'Nothing to duplicate.', 'rapm' ) );
		}
		check_admin_referer( 'rapm_duplicate_' . $asset_id );

		$new_id = self::duplicate( $asset_id );
		if ( ! $new_id ) {
			wp_die( esc_html__( 'Could not duplicate this asset. Please try again.', 'rapm' ) );
		}

		wp_safe_redirect( admin_url( 'edit.php?post_type=rapm_asset&page=rapm-add-asset&edit=' . $new_id ) );
		exit;
	}
}
