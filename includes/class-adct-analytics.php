<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ADCT_Analytics {

	const PERIOD_OPTIONS = array( 7, 30, 90 );

	const DEFAULT_PERIOD = 30;

	const BREAKDOWN_LIMIT = 8;

	const CONTACT_COLORS = array(
		'whatsapp'          => '#25d366',
		'phone'             => '#3858e9',
		'showroom_landline' => '#6b4e9b',
		'floating_whatsapp' => '#c9a227',
		'floating_phone'    => '#b26200',
		'elfsight_call'     => '#50575e',
		'footer_landline'   => '#563a82',
	);

	const SOURCE_COLORS = array(
		'google_cpc'     => '#4285f4',
		'google_organic' => '#34a853',
		'direct'         => '#9aa0a6',
		'referral'       => '#7c5cbf',
		'youtube'        => '#ff0000',
		'facebook'       => '#1877f2',
		'instagram'      => '#e4405f',
	);

	public static function get_period_from_request() {
		$period = isset( $_GET['period'] ) ? absint( $_GET['period'] ) : self::DEFAULT_PERIOD;

		if ( ! in_array( $period, self::PERIOD_OPTIONS, true ) ) {
			$period = self::DEFAULT_PERIOD;
		}

		return $period;
	}

	public static function get_period_bounds( $days ) {
		$days = absint( $days );

		if ( ! in_array( $days, self::PERIOD_OPTIONS, true ) ) {
			$days = self::DEFAULT_PERIOD;
		}

		$end_date   = wp_date( 'Y-m-d' );
		$start_date = wp_date( 'Y-m-d', strtotime( '-' . ( $days - 1 ) . ' days' ) );

		return array(
			'days'       => $days,
			'start'      => $start_date . ' 00:00:00',
			'end'        => $end_date . ' 23:59:59',
			'start_date' => $start_date,
			'end_date'   => $end_date,
			'label'      => sprintf(
				/* translators: %d: number of days */
				_n( 'Last %d day', 'Last %d days', $days, 'tracking-template' ),
				$days
			),
		);
	}

	public static function get_overview_data( $days ) {
		$bounds = self::get_period_bounds( $days );

		$contact_breakdown = self::get_grouped_counts( 'contact_type', $bounds['start'], $bounds['end'] );
		$source_breakdown  = self::get_grouped_counts( 'entry_source', $bounds['start'], $bounds['end'] );
		$campaign_breakdown = self::limit_breakdown(
			self::get_grouped_counts( 'utm_campaign', $bounds['start'], $bounds['end'] )
		);
		$landing_breakdown = self::limit_breakdown(
			self::get_grouped_counts( 'landing_path', $bounds['start'], $bounds['end'] )
		);

		$daily_clicks   = self::get_daily_clicks( $bounds['start'], $bounds['end'] );
		$daily_sessions = self::get_daily_sessions( $bounds['start'], $bounds['end'] );

		$total_clicks    = array_sum( wp_list_pluck( $contact_breakdown, 'count' ) );
		$total_sessions  = self::count_sessions_between( $bounds['start'], $bounds['end'] );
		$total_attributed = array_sum( wp_list_pluck( $source_breakdown, 'count' ) );
		$total_campaigns = array_sum( wp_list_pluck( $campaign_breakdown, 'count' ) );
		$whatsapp_clicks = self::sum_counts_for_types( $contact_breakdown, array( 'whatsapp', 'floating_whatsapp' ) );
		$paid_sessions   = self::count_sessions_with_sources( $bounds['start'], $bounds['end'], array( 'google_cpc' ) );

		$chart_days        = self::build_day_series( $bounds['start_date'], $bounds['end_date'] );
		$contact_formatted = self::format_breakdown( $contact_breakdown, $total_clicks, 'contact_type', $bounds );
		$source_formatted  = self::format_breakdown( $source_breakdown, $total_attributed, 'entry_source', $bounds );
		$campaign_formatted = self::format_breakdown( $campaign_breakdown, $total_campaigns, 'utm_campaign', $bounds );
		$landing_formatted  = self::format_breakdown( $landing_breakdown, array_sum( wp_list_pluck( $landing_breakdown, 'count' ) ), 'landing_path', $bounds );

		return array(
			'period'             => $bounds,
			'contact_breakdown'  => $contact_formatted,
			'source_breakdown'   => $source_formatted,
			'campaign_breakdown' => $campaign_formatted,
			'landing_breakdown'  => $landing_formatted,
			'totals'             => array(
				'clicks'            => $total_clicks,
				'sessions'          => $total_sessions,
				'attributed_clicks' => $total_attributed,
				'whatsapp_clicks'   => $whatsapp_clicks,
				'paid_sessions'     => $paid_sessions,
				'whatsapp_rate'     => self::percentage( $whatsapp_clicks, $total_clicks ),
				'paid_session_rate' => self::percentage( $paid_sessions, $total_sessions ),
				'top_source'        => self::get_top_label( $source_breakdown, 'entry_source' ),
				'top_campaign'      => self::get_top_label( $campaign_breakdown, 'utm_campaign' ),
				'top_landing'       => self::get_top_label( $landing_breakdown, 'landing_path' ),
			),
			'charts'             => array(
				'labels'          => array_map( array( __CLASS__, 'format_chart_day' ), $chart_days ),
				'clicks'          => self::map_series_to_days( $chart_days, $daily_clicks ),
				'sessions'        => self::map_series_to_days( $chart_days, $daily_sessions ),
				'contact_labels'  => wp_list_pluck( $contact_formatted, 'label' ),
				'contact_counts'  => wp_list_pluck( $contact_formatted, 'count' ),
				'contact_colors'  => wp_list_pluck( $contact_formatted, 'color' ),
				'source_labels'   => wp_list_pluck( $source_formatted, 'label' ),
				'source_counts'   => wp_list_pluck( $source_formatted, 'count' ),
				'source_colors'   => wp_list_pluck( $source_formatted, 'color' ),
				'campaign_labels' => wp_list_pluck( $campaign_formatted, 'label' ),
				'campaign_counts' => wp_list_pluck( $campaign_formatted, 'count' ),
				'campaign_colors' => wp_list_pluck( $campaign_formatted, 'color' ),
			),
		);
	}

	private static function get_grouped_counts( $field, $start, $end ) {
		global $wpdb;

		$allowed = array(
			'contact_type'  => 'contact_type',
			'entry_source'  => 'entry_source',
			'utm_campaign'  => 'utm_campaign',
			'landing_path'  => 'landing_path',
		);

		if ( ! isset( $allowed[ $field ] ) ) {
			return array();
		}

		$column = $allowed[ $field ];
		$table  = ADCT_Database::table_name();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql = "SELECT {$column} AS group_key, COUNT(*) AS total
			FROM {$table}
			WHERE clicked_at >= %s AND clicked_at <= %s AND {$column} <> ''
			GROUP BY {$column}
			ORDER BY total DESC, {$column} ASC";

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $start, $end ) );

		$items = array();

		foreach ( $rows as $row ) {
			$items[] = array(
				'key'   => (string) $row->group_key,
				'count' => (int) $row->total,
			);
		}

		return $items;
	}

	private static function limit_breakdown( array $items ) {
		if ( count( $items ) <= self::BREAKDOWN_LIMIT ) {
			return $items;
		}

		$top    = array_slice( $items, 0, self::BREAKDOWN_LIMIT - 1 );
		$others = array_slice( $items, self::BREAKDOWN_LIMIT - 1 );
		$other_count = 0;

		foreach ( $others as $item ) {
			$other_count += (int) $item['count'];
		}

		if ( $other_count > 0 ) {
			$top[] = array(
				'key'   => '__other__',
				'count' => $other_count,
			);
		}

		return $top;
	}

	private static function get_daily_clicks( $start, $end ) {
		global $wpdb;

		$table = ADCT_Database::table_name();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql = "SELECT DATE(clicked_at) AS day_key, COUNT(*) AS total
			FROM {$table}
			WHERE clicked_at >= %s AND clicked_at <= %s
			GROUP BY DATE(clicked_at)
			ORDER BY day_key ASC";

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $start, $end ) );

		$map = array();

		foreach ( $rows as $row ) {
			$map[ (string) $row->day_key ] = (int) $row->total;
		}

		return $map;
	}

	private static function get_daily_sessions( $start, $end ) {
		global $wpdb;

		$table      = ADCT_Database::table_name();
		$group_expr = ADCT_Database::get_session_group_expression();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql = "SELECT day_key, COUNT(*) AS total FROM (
				SELECT DATE(MIN(clicked_at)) AS day_key
				FROM {$table}
				WHERE clicked_at >= %s AND clicked_at <= %s
				GROUP BY {$group_expr}
			) AS adct_daily_sessions
			GROUP BY day_key
			ORDER BY day_key ASC";

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $start, $end ) );

		$map = array();

		foreach ( $rows as $row ) {
			$map[ (string) $row->day_key ] = (int) $row->total;
		}

		return $map;
	}

	private static function count_sessions_between( $start, $end ) {
		global $wpdb;

		$table      = ADCT_Database::table_name();
		$group_expr = ADCT_Database::get_session_group_expression();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM (
					SELECT 1 FROM {$table}
					WHERE clicked_at >= %s AND clicked_at <= %s
					GROUP BY {$group_expr}
				) AS adct_overview_sessions",
				$start,
				$end
			)
		);
	}

	private static function count_sessions_with_sources( $start, $end, array $sources ) {
		global $wpdb;

		if ( empty( $sources ) ) {
			return 0;
		}

		$table        = ADCT_Database::table_name();
		$group_expr   = ADCT_Database::get_session_group_expression();
		$placeholders = implode( ',', array_fill( 0, count( $sources ), '%s' ) );
		$params       = array_merge( array( $start, $end ), $sources );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql = "SELECT COUNT(*) FROM (
				SELECT {$group_expr} AS session_key
				FROM {$table}
				WHERE clicked_at >= %s AND clicked_at <= %s
				GROUP BY {$group_expr}
				HAVING MAX(CASE WHEN entry_source IN ({$placeholders}) THEN 1 ELSE 0 END) = 1
			) AS adct_paid_sessions";

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return (int) $wpdb->get_var( $wpdb->prepare( $sql, $params ) );
	}

	private static function format_breakdown( array $items, $total, $type, array $bounds = array() ) {
		$output  = array();
		$palette = self::fallback_palette();
		$index   = 0;

		foreach ( $items as $item ) {
			$key   = $item['key'];
			$count = (int) $item['count'];

			if ( $count < 1 ) {
				continue;
			}

			$output[] = array(
				'key'         => $key,
				'label'       => self::format_group_label( $key, $type ),
				'count'       => $count,
				'percent'     => self::percentage( $count, $total ),
				'percent_raw' => $total > 0 ? round( ( $count / $total ) * 100, 1 ) : 0,
				'color'       => self::color_for_key( $key, $type, $palette[ $index % count( $palette ) ] ),
				'filter_url'  => self::build_filter_url( $key, $type, $bounds ),
			);

			$index++;
		}

		return $output;
	}

	private static function format_group_label( $key, $type ) {
		if ( 'contact_type' === $type ) {
			return ADCT_Admin::format_contact_type_label( $key );
		}

		if ( 'entry_source' === $type ) {
			return ADCT_Admin::format_entry_source( $key );
		}

		if ( '__other__' === $key ) {
			return 'Other';
		}

		if ( 'landing_path' === $type ) {
			return $key;
		}

		return $key;
	}

	private static function color_for_key( $key, $type, $fallback ) {
		if ( 'contact_type' === $type ) {
			return self::CONTACT_COLORS[ $key ] ?? $fallback;
		}

		if ( 'entry_source' === $type ) {
			return self::SOURCE_COLORS[ $key ] ?? $fallback;
		}

		if ( '__other__' === $key ) {
			return '#c3c4c7';
		}

		return $fallback;
	}

	private static function fallback_palette() {
		return array( '#1a2332', '#c9a227', '#4285f4', '#34a853', '#7c5cbf', '#b26200', '#50575e' );
	}

	private static function build_filter_url( $key, $type, array $bounds = array() ) {
		if ( '__other__' === $key ) {
			return '';
		}

		$args = array(
			'page' => 'tracking-template-sessions',
		);

		if ( ! empty( $bounds['start_date'] ) ) {
			$args['date_from'] = $bounds['start_date'];
		}

		if ( ! empty( $bounds['end_date'] ) ) {
			$args['date_to'] = $bounds['end_date'];
		}

		if ( 'contact_type' === $type ) {
			$args['contact_type'] = $key;
		}

		if ( 'entry_source' === $type ) {
			$args['entry_source'] = $key;
		}

		if ( 'utm_campaign' === $type ) {
			$args['utm_campaign'] = $key;
		}

		if ( 'landing_path' === $type ) {
			$args['landing_path'] = $key;
		}

		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}

	private static function build_day_series( $start_date, $end_date ) {
		$days   = array();
		$cursor = strtotime( $start_date . ' 00:00:00' );
		$end    = strtotime( $end_date . ' 00:00:00' );

		while ( $cursor <= $end ) {
			$days[] = wp_date( 'Y-m-d', $cursor );
			$cursor = strtotime( '+1 day', $cursor );
		}

		return $days;
	}

	private static function map_series_to_days( array $days, array $map ) {
		$series = array();

		foreach ( $days as $day ) {
			$series[] = isset( $map[ $day ] ) ? (int) $map[ $day ] : 0;
		}

		return $series;
	}

	public static function format_chart_day( $day ) {
		$timestamp = strtotime( $day . ' 00:00:00' );

		if ( ! $timestamp ) {
			return $day;
		}

		return wp_date( 'j M', $timestamp );
	}

	private static function percentage( $part, $total ) {
		$part  = (int) $part;
		$total = (int) $total;

		if ( $total < 1 ) {
			return '0%';
		}

		return number_format_i18n( round( ( $part / $total ) * 100, 1 ), 1 ) . '%';
	}

	private static function sum_counts_for_types( array $items, array $keys ) {
		$sum = 0;

		foreach ( $items as $item ) {
			if ( in_array( $item['key'], $keys, true ) ) {
				$sum += (int) $item['count'];
			}
		}

		return $sum;
	}

	private static function get_top_label( array $items, $type ) {
		if ( empty( $items ) ) {
			return '—';
		}

		usort(
			$items,
			static function ( $a, $b ) {
				return $b['count'] <=> $a['count'];
			}
		);

		if ( '__other__' === $items[0]['key'] && isset( $items[1] ) ) {
			return self::format_group_label( $items[1]['key'], $type );
		}

		return self::format_group_label( $items[0]['key'], $type );
	}
}
