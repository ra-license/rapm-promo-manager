<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * [rapm_promotions_calendar] — a month-grid view of scheduled promotions
 * across the site (or a specific kind/placement, via shortcode
 * attributes). Deliberately distinct from the separate Community Events
 * Calendar plugin: this shows RA Promo Manager's own promotional assets
 * and their scheduled date ranges, not community-submitted events, and
 * has no public submission path at all — every asset it can show is one
 * created through the validated Add/Edit Asset admin form.
 *
 * Rendered client-side from an embedded JSON payload, for the same
 * caching-safety reason as every other display mode here: a full-page
 * cache plugin only ever serves a static snapshot, so "what month is it,
 * what's active on which day" has to be decided in the visitor's own
 * browser, not baked in at render time.
 *
 * Only promotions with BOTH a start and an end date are included — an
 * evergreen asset with no schedule isn't a "promotion with dates" in the
 * sense a calendar is useful for.
 */
class RAPM_Calendar {

	/**
	 * How many published promotions currently qualify to appear on
	 * [rapm_promotions_calendar] (both a start and end date set) — the
	 * same rule the shortcode itself applies. Used by the Sliders
	 * dashboard to show a live count next to the shortcode, rather than
	 * just describing what it does.
	 */
	public static function count_scheduled() {
		$posts = get_posts(
			array(
				'post_type'      => 'rapm_asset',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
					'relation' => 'AND',
					array( 'key' => '_rapm_starts_at', 'value' => '', 'compare' => '!=' ),
					array( 'key' => '_rapm_ends_at', 'value' => '', 'compare' => '!=' ),
				),
			)
		);
		return count( $posts );
	}

	public static function shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'placement' => '',
				'kind'      => '',
			),
			$atts,
			'rapm_promotions_calendar'
		);

		$meta_query = array( 'relation' => 'AND' );
		if ( $atts['placement'] ) {
			$meta_query[] = array( 'key' => '_rapm_placement', 'value' => sanitize_title( $atts['placement'] ) );
		}
		if ( $atts['kind'] ) {
			$meta_query[] = array( 'key' => '_rapm_kind', 'value' => sanitize_key( $atts['kind'] ) );
		}
		$meta_query[] = array( 'key' => '_rapm_starts_at', 'value' => '', 'compare' => '!=' );
		$meta_query[] = array( 'key' => '_rapm_ends_at', 'value' => '', 'compare' => '!=' );

		$query = new WP_Query(
			array(
				'post_type'      => 'rapm_asset',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'meta_query'     => $meta_query, // phpcs:ignore WordPress.DB.SlowDBQuery
			)
		);

		$items = array();
		foreach ( $query->posts as $post ) {
			$starts = get_post_meta( $post->ID, '_rapm_starts_at', true );
			$ends   = get_post_meta( $post->ID, '_rapm_ends_at', true );
			if ( ! $starts || ! $ends ) {
				continue;
			}
			$headline   = get_post_meta( $post->ID, '_rapm_headline', true );
			$dest_type  = get_post_meta( $post->ID, '_rapm_destination_type', true );
			$dest_value = get_post_meta( $post->ID, '_rapm_destination_value', true );
			$items[]    = array(
				'label' => $headline ? $headline : get_the_title( $post->ID ),
				'url'   => RAPM_Destination::resolve_url( $dest_type, $dest_value ),
				'start' => gmdate( 'Y-m-d', strtotime( $starts ) ),
				'end'   => gmdate( 'Y-m-d', strtotime( $ends ) ),
			);
		}
		wp_reset_postdata();

		if ( ! $items ) {
			return '<p class="rapm-calendar-empty">' . esc_html__( 'No scheduled promotions right now.', 'rapm' ) . '</p>';
		}

		$instance_id = 'rapm-cal-' . wp_unique_id();

		ob_start();
		echo RAPM_Assets::need( 'calendar' ); // phpcs:ignore WordPress.Security.EscapeOutput -- core-generated link and style tags.
		?>
		<div class="rapm-calendar <?php echo esc_attr( $instance_id ); ?>" data-rapm-calendar>
			<div class="rapm-calendar-header">
				<button type="button" class="rapm-calendar-prev" aria-label="<?php esc_attr_e( 'Previous month', 'rapm' ); ?>">&lsaquo;</button>
				<span class="rapm-calendar-title"></span>
				<button type="button" class="rapm-calendar-next" aria-label="<?php esc_attr_e( 'Next month', 'rapm' ); ?>">&rsaquo;</button>
			</div>
			<div class="rapm-calendar-grid"></div>
			<div class="rapm-calendar-day-detail" hidden></div>
		</div>
		<script type="application/json" class="rapm-calendar-data"><?php echo wp_json_encode( $items ); ?></script>
		<script>
			( function () {
				function init() {
					var root = document.querySelector( '.<?php echo esc_js( $instance_id ); ?>' );
					var data = JSON.parse( root.nextElementSibling.textContent );

					function pad( n ) { return n < 10 ? '0' + n : '' + n; }
					function toISO( y, m, d ) { return y + '-' + pad( m + 1 ) + '-' + pad( d ); }

					var today     = new Date();
					var viewYear  = today.getFullYear();
					var viewMonth = today.getMonth();

					var titleEl  = root.querySelector( '.rapm-calendar-title' );
					var gridEl   = root.querySelector( '.rapm-calendar-grid' );
					var detailEl = root.querySelector( '.rapm-calendar-day-detail' );
					var weekdayLabels = [
						<?php
						echo wp_json_encode( __( 'Sun', 'rapm' ) ) . ',' . wp_json_encode( __( 'Mon', 'rapm' ) ) . ',' . wp_json_encode( __( 'Tue', 'rapm' ) ) . ','
							. wp_json_encode( __( 'Wed', 'rapm' ) ) . ',' . wp_json_encode( __( 'Thu', 'rapm' ) ) . ',' . wp_json_encode( __( 'Fri', 'rapm' ) ) . ',' . wp_json_encode( __( 'Sat', 'rapm' ) );
						?>
					];

					function showDetail( list ) {
						detailEl.innerHTML = '';
						detailEl.hidden = false;
						var ul = document.createElement( 'ul' );
						list.forEach( function ( item ) {
							var li = document.createElement( 'li' );
							if ( item.url ) {
								var a = document.createElement( 'a' );
								a.href = item.url;
								a.textContent = item.label;
								li.appendChild( a );
							} else {
								li.textContent = item.label;
							}
							ul.appendChild( li );
						} );
						detailEl.appendChild( ul );
					}

					// How many promotions can stack in one week before the rest
					// collapse into a "+N more" note (click any day that week
					// to see everything active that day, including overflow).
					var MAX_LANES = 3;

					function render() {
						gridEl.innerHTML = '';
						detailEl.hidden = true;

						var first       = new Date( viewYear, viewMonth, 1 );
						var startDay    = first.getDay();
						var daysInMonth = new Date( viewYear, viewMonth + 1, 0 ).getDate();
						var todayIso    = toISO( today.getFullYear(), today.getMonth(), today.getDate() );

						titleEl.textContent = new Intl.DateTimeFormat( undefined, { month: 'long', year: 'numeric' } ).format( first );

						var weekdayRow = document.createElement( 'div' );
						weekdayRow.className = 'rapm-calendar-weekdays';
						weekdayLabels.forEach( function ( label ) {
							var el = document.createElement( 'div' );
							el.className = 'rapm-calendar-weekday';
							el.textContent = label;
							weekdayRow.appendChild( el );
						} );
						gridEl.appendChild( weekdayRow );

						var totalWeeks = Math.ceil( ( startDay + daysInMonth ) / 7 );

						for ( var w = 0; w < totalWeeks; w++ ) {
							var weekEl = document.createElement( 'div' );
							weekEl.className = 'rapm-calendar-week';

							// Every day this week resolves first, since the cell
							// backgrounds below need to know which column is
							// today/out-of-month before any buttons exist.
							var weekDates = [];
							for ( var c = 0; c < 7; c++ ) {
								var dayNum = w * 7 + c - startDay + 1;
								weekDates.push( ( dayNum < 1 || dayNum > daysInMonth ) ? null : toISO( viewYear, viewMonth, dayNum ) );
							}

							// One full-height background box per day, appended
							// before anything else so it paints behind the day
							// number and any bars sharing its column — this is
							// what gives the week its boxed, Google Calendar
							// look without changing the bar-stacking layout.
							for ( var bgc = 0; bgc < 7; bgc++ ) {
								var cellBg = document.createElement( 'div' );
								cellBg.className = 'rapm-calendar-cellbg' +
									( ! weekDates[ bgc ] ? ' is-empty' : '' ) +
									( weekDates[ bgc ] === todayIso ? ' is-today' : '' );
								cellBg.style.gridColumn = String( bgc + 1 );
								weekEl.appendChild( cellBg );
							}

							// Day-number cells for this week (row 1 of the week's
							// own mini-grid) — same button-per-day the detail-list
							// click-through has always used, now also serving as
							// the overflow escape hatch for a week with more
							// promotions than visible bar lanes.
							for ( var c = 0; c < 7; c++ ) {
								var dayNum = w * 7 + c - startDay + 1;
								var dayCell = document.createElement( 'button' );
								dayCell.type = 'button';
								dayCell.className = 'rapm-calendar-daynum-cell';
								dayCell.style.gridColumn = String( c + 1 );
								dayCell.style.gridRow = '1';

								if ( ! weekDates[ c ] ) {
									dayCell.classList.add( 'is-empty' );
									dayCell.disabled = true;
								} else {
									var iso = weekDates[ c ];
									var num = document.createElement( 'span' );
									num.className = 'rapm-calendar-daynum' + ( iso === todayIso ? ' is-today' : '' );
									num.textContent = dayNum;
									dayCell.appendChild( num );

									var dayItems = data.filter( function ( item ) { return item.start <= iso && iso <= item.end; } );
									if ( dayItems.length ) {
										dayCell.addEventListener( 'click', ( function ( list ) {
											return function () { showDetail( list ); };
										} )( dayItems ) );
									}
								}
								weekEl.appendChild( dayCell );
							}

							// Every promotion active anywhere in this week becomes
							// one bar, spanning the columns it's active on within
							// this week (clipped at the week boundary — a
							// promotion running longer than one week just
							// continues as its own bar on the next row, with
							// square instead of rounded ends where it's cut).
							var activeItems = data.filter( function ( item ) {
								return weekDates.some( function ( iso ) { return iso && item.start <= iso && iso <= item.end; } );
							} );
							activeItems.sort( function ( a, b ) {
								if ( a.start !== b.start ) { return a.start < b.start ? -1 : 1; }
								return a.label < b.label ? -1 : ( a.label > b.label ? 1 : 0 );
							} );

							var laneEnds     = [];
							var overflowItems = [];

							activeItems.forEach( function ( item ) {
								var colStart = -1, colEnd = -1;
								for ( var c2 = 0; c2 < 7; c2++ ) {
									if ( weekDates[ c2 ] && item.start <= weekDates[ c2 ] && weekDates[ c2 ] <= item.end ) {
										if ( colStart === -1 ) { colStart = c2; }
										colEnd = c2;
									}
								}
								if ( colStart === -1 ) { return; }

								var lane = 0;
								while ( laneEnds[ lane ] !== undefined && laneEnds[ lane ] >= colStart ) { lane++; }
								if ( lane >= MAX_LANES ) {
									overflowItems.push( item );
									return;
								}
								laneEnds[ lane ] = colEnd;

								var isTrueStart = weekDates[ colStart ] === item.start;
								var isTrueEnd   = weekDates[ colEnd ] === item.end;

								var bar = document.createElement( item.url ? 'a' : 'span' );
								bar.className = 'rapm-calendar-bar' + ( isTrueStart ? ' is-start' : '' ) + ( isTrueEnd ? ' is-end' : '' );
								bar.style.gridColumn = ( colStart + 1 ) + ' / ' + ( colEnd + 2 );
								bar.style.gridRow    = String( lane + 2 );
								bar.textContent      = item.label;
								bar.title            = item.label;
								if ( item.url ) { bar.href = item.url; }
								weekEl.appendChild( bar );
							} );

							if ( overflowItems.length > 0 ) {
								var more = document.createElement( 'button' );
								more.type = 'button';
								more.className = 'rapm-calendar-more';
								more.style.gridColumn = '1 / 8';
								more.style.gridRow    = String( MAX_LANES + 2 );
								more.textContent      = 1 === overflowItems.length
									? <?php echo wp_json_encode( __( '+1 more', 'rapm' ) ); ?>
									: '+' + overflowItems.length + <?php echo wp_json_encode( ' ' . __( 'more', 'rapm' ) ); ?>;
								// Shows exactly the promotion(s) that didn't fit a
								// bar this week — not just whatever else happens
								// to be active on some day nearby, which could
								// easily be a different, unrelated promotion.
								// The IIFE below matters: overflowItems is a
								// plain `var`, so without it every week's button
								// would share the same (last-written) value
								// instead of its own week's overflow list.
								more.addEventListener( 'click', ( function ( list ) {
									return function () { showDetail( list ); };
								} )( overflowItems ) );
								weekEl.appendChild( more );
							}

							gridEl.appendChild( weekEl );
						}
					}

					root.querySelector( '.rapm-calendar-prev' ).addEventListener( 'click', function () {
						viewMonth--;
						if ( viewMonth < 0 ) { viewMonth = 11; viewYear--; }
						render();
					} );
					root.querySelector( '.rapm-calendar-next' ).addEventListener( 'click', function () {
						viewMonth++;
						if ( viewMonth > 11 ) { viewMonth = 0; viewYear++; }
						render();
					} );

					render();
				}
				if ( document.readyState === 'loading' ) {
					document.addEventListener( 'DOMContentLoaded', init );
				} else {
					init();
				}
			} )();
		</script>
		<?php
		return ob_get_clean();
	}

	/**
	 * Early <head> loading only; shortcode() loads the files wherever the
	 * calendar appears (see RAPM_Hero_Carousel::should_load_assets()). The
	 * brand-color line now lives in RAPM_Assets::need() so it's added once,
	 * whichever path loads the stylesheet.
	 */
	public static function should_load_assets() {
		if ( is_singular() ) {
			global $post;
			if ( $post && has_shortcode( $post->post_content, 'rapm_promotions_calendar' ) ) {
				return true;
			}
		}
		return is_active_widget( false, false, 'text', true ) || is_active_widget( false, false, 'shortcode', true );
	}

	public static function enqueue() {
		if ( ! self::should_load_assets() ) {
			return;
		}
		RAPM_Assets::need( 'calendar' );
	}
}
