<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ADCT_Database {

	const DB_VERSION = '1.4.0';

	const PER_PAGE_OPTIONS = array( 10, 20, 50, 100, 200, 500 );

	const DEFAULT_PER_PAGE = 10;

	public static function table_name() {
		global $wpdb;

		return $wpdb->prefix . 'adct_clicks';
	}

	public static function maybe_install() {
		if ( get_option( 'adct_db_version' ) === self::DB_VERSION ) {
			return;
		}

		self::install();
	}

	public static function install() {
		global $wpdb;

		$table   = self::table_name();
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			clicked_at datetime NOT NULL,
			product_id bigint(20) unsigned NOT NULL DEFAULT 0,
			product_title varchar(500) NOT NULL DEFAULT '',
			product_url varchar(500) NOT NULL DEFAULT '',
			product_price varchar(100) NOT NULL DEFAULT '',
			product_mileage varchar(100) NOT NULL DEFAULT '',
			product_image_url varchar(500) NOT NULL DEFAULT '',
			agent_id varchar(50) NOT NULL DEFAULT '',
			agent_name varchar(200) NOT NULL DEFAULT '',
			contact_type varchar(50) NOT NULL DEFAULT '',
			clicked_value varchar(500) NOT NULL DEFAULT '',
			source varchar(100) NOT NULL DEFAULT '',
			ip_hash char(64) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			KEY clicked_at (clicked_at),
			KEY product_id (product_id),
			KEY agent_name (agent_name),
			KEY contact_type (contact_type)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
		self::maybe_add_columns();

		update_option( 'adct_db_version', self::DB_VERSION );

		delete_option( 'adct_webapp_url' );
		delete_option( 'adct_secret_token' );
	}

	public static function maybe_add_columns() {
		global $wpdb;

		$table   = self::table_name();
		$columns = $wpdb->get_col( "DESCRIBE {$table}", 0 ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( ! is_array( $columns ) ) {
			return;
		}

		$additions = array(
			'product_price'     => "ADD COLUMN product_price varchar(100) NOT NULL DEFAULT '' AFTER product_url",
			'product_mileage'   => "ADD COLUMN product_mileage varchar(100) NOT NULL DEFAULT '' AFTER product_price",
			'product_image_url' => "ADD COLUMN product_image_url varchar(500) NOT NULL DEFAULT '' AFTER product_mileage",
			'device_type'       => "ADD COLUMN device_type varchar(20) NOT NULL DEFAULT '' AFTER ip_hash",
			'browser_name'      => "ADD COLUMN browser_name varchar(100) NOT NULL DEFAULT '' AFTER device_type",
			'visitor_country'   => "ADD COLUMN visitor_country varchar(100) NOT NULL DEFAULT '' AFTER browser_name",
			'visitor_region'    => "ADD COLUMN visitor_region varchar(100) NOT NULL DEFAULT '' AFTER visitor_country",
			'landing_url'       => "ADD COLUMN landing_url varchar(500) NOT NULL DEFAULT '' AFTER visitor_region",
			'landing_path'      => "ADD COLUMN landing_path varchar(200) NOT NULL DEFAULT '' AFTER landing_url",
			'landing_referrer'  => "ADD COLUMN landing_referrer varchar(200) NOT NULL DEFAULT '' AFTER landing_path",
			'entry_source'      => "ADD COLUMN entry_source varchar(50) NOT NULL DEFAULT '' AFTER landing_referrer",
			'utm_source'        => "ADD COLUMN utm_source varchar(100) NOT NULL DEFAULT '' AFTER entry_source",
			'utm_medium'        => "ADD COLUMN utm_medium varchar(100) NOT NULL DEFAULT '' AFTER utm_source",
			'utm_campaign'      => "ADD COLUMN utm_campaign varchar(100) NOT NULL DEFAULT '' AFTER utm_medium",
			'utm_id'            => "ADD COLUMN utm_id varchar(100) NOT NULL DEFAULT '' AFTER utm_campaign",
			'utm_term'          => "ADD COLUMN utm_term varchar(255) NOT NULL DEFAULT '' AFTER utm_id",
			'utm_content'       => "ADD COLUMN utm_content varchar(255) NOT NULL DEFAULT '' AFTER utm_term",
			'gclid'             => "ADD COLUMN gclid varchar(255) NOT NULL DEFAULT '' AFTER utm_content",
			'duration_seconds'  => "ADD COLUMN duration_seconds int(10) unsigned NOT NULL DEFAULT 0 AFTER gclid",
			'session_id'        => "ADD COLUMN session_id varchar(36) NOT NULL DEFAULT '' AFTER duration_seconds",
		);

		foreach ( $additions as $column => $sql_part ) {
			if ( in_array( $column, $columns, true ) ) {
				continue;
			}

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE {$table} {$sql_part}" );
		}
	}

	public static function get_product_snapshot( $product_id ) {
		$product_id = absint( $product_id );
		$price      = '';
		$mileage    = '';
		$image_url  = '';

		if ( $product_id && function_exists( 'wc_get_product' ) ) {
			$product = wc_get_product( $product_id );

			if ( $product ) {
				$raw_price = $product->get_price();

				if ( '' !== $raw_price && null !== $raw_price ) {
					$price = 'AED ' . number_format_i18n( (float) $raw_price );
				}
			}
		}

		if ( $product_id && function_exists( 'get_field' ) ) {
			$mileage = (string) get_field( 'mileage', $product_id );
		}

		if ( '' === trim( $mileage ) ) {
			$mileage = (string) get_post_meta( $product_id, 'mileage', true );
		}

		$mileage = trim( $mileage );

		if ( $product_id ) {
			$image_url = get_the_post_thumbnail_url( $product_id, 'thumbnail' );

			if ( ! $image_url ) {
				$image_url = get_the_post_thumbnail_url( $product_id, 'medium' );
			}
		}

		return array(
			'product_price'     => $price,
			'product_mileage'   => $mileage,
			'product_image_url' => $image_url ? esc_url_raw( $image_url ) : '',
		);
	}

	public static function sanitize_attribution( array $data ) {
		return array(
			'landing_url'      => esc_url_raw( $data['landing_url'] ?? '' ),
			'landing_path'     => sanitize_text_field( $data['landing_path'] ?? '' ),
			'landing_referrer' => sanitize_text_field( $data['landing_referrer'] ?? '' ),
			'entry_source'     => sanitize_key( $data['entry_source'] ?? '' ),
			'utm_source'       => sanitize_text_field( $data['utm_source'] ?? '' ),
			'utm_medium'       => sanitize_text_field( $data['utm_medium'] ?? '' ),
			'utm_campaign'     => sanitize_text_field( $data['utm_campaign'] ?? '' ),
			'utm_id'           => sanitize_text_field( $data['utm_id'] ?? '' ),
			'utm_term'         => sanitize_text_field( $data['utm_term'] ?? '' ),
			'utm_content'      => sanitize_text_field( $data['utm_content'] ?? '' ),
			'gclid'            => sanitize_text_field( $data['gclid'] ?? '' ),
			'duration_seconds' => absint( $data['duration_seconds'] ?? 0 ),
			'session_id'       => sanitize_text_field( $data['session_id'] ?? '' ),
		);
	}

	public static function insert_click( array $data ) {
		global $wpdb;

		$product_id  = absint( $data['product_id'] ?? 0 );
		$snapshot    = self::get_product_snapshot( $product_id );
		$visitor     = class_exists( 'ADCT_Visitor' ) ? ADCT_Visitor::get_context_for_request() : array();
		$attribution = self::sanitize_attribution( $data );

		$inserted = $wpdb->insert(
			self::table_name(),
			array(
				'clicked_at'        => current_time( 'mysql' ),
				'product_id'        => $product_id,
				'product_title'     => sanitize_text_field( $data['product_title'] ?? '' ),
				'product_url'       => esc_url_raw( $data['product_url'] ?? '' ),
				'product_price'     => sanitize_text_field( $data['product_price'] ?? $snapshot['product_price'] ),
				'product_mileage'   => sanitize_text_field( $data['product_mileage'] ?? $snapshot['product_mileage'] ),
				'product_image_url' => esc_url_raw( $data['product_image_url'] ?? $snapshot['product_image_url'] ),
				'agent_id'          => sanitize_text_field( $data['agent_id'] ?? '' ),
				'agent_name'        => sanitize_text_field( $data['agent_name'] ?? '' ),
				'contact_type'      => sanitize_key( $data['contact_type'] ?? '' ),
				'clicked_value'     => sanitize_text_field( $data['clicked_value'] ?? '' ),
				'source'            => sanitize_key( $data['source'] ?? '' ),
				'ip_hash'           => self::hash_ip(),
				'device_type'       => sanitize_text_field( $visitor['device_type'] ?? '' ),
				'browser_name'      => sanitize_text_field( $visitor['browser_name'] ?? '' ),
				'visitor_country'   => sanitize_text_field( $visitor['visitor_country'] ?? '' ),
				'visitor_region'    => sanitize_text_field( $visitor['visitor_region'] ?? '' ),
				'landing_url'       => $attribution['landing_url'],
				'landing_path'      => $attribution['landing_path'],
				'landing_referrer'  => $attribution['landing_referrer'],
				'entry_source'      => $attribution['entry_source'],
				'utm_source'        => $attribution['utm_source'],
				'utm_medium'        => $attribution['utm_medium'],
				'utm_campaign'      => $attribution['utm_campaign'],
				'utm_id'            => $attribution['utm_id'],
				'utm_term'          => $attribution['utm_term'],
				'utm_content'       => $attribution['utm_content'],
				'gclid'             => $attribution['gclid'],
				'duration_seconds'  => $attribution['duration_seconds'],
				'session_id'        => sanitize_text_field( $data['session_id'] ?? '' ),
			),
			array( '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s' )
		);

		return false !== $inserted;
	}

	public static function hash_ip() {
		$ip = class_exists( 'ADCT_Visitor' ) ? ADCT_Visitor::get_client_ip() : '';

		if ( '' === $ip && ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}

		if ( '' === $ip ) {
			return '';
		}

		return hash( 'sha256', $ip . wp_salt( 'auth' ) );
	}

	public static function get_pagination_args() {
		$per_page = isset( $_GET['per_page'] ) ? absint( wp_unslash( $_GET['per_page'] ) ) : self::DEFAULT_PER_PAGE;

		if ( ! in_array( $per_page, self::PER_PAGE_OPTIONS, true ) ) {
			$per_page = self::DEFAULT_PER_PAGE;
		}

		$paged  = isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 1;
		$offset = ( $paged - 1 ) * $per_page;

		return array(
			'per_page' => $per_page,
			'paged'    => $paged,
			'offset'   => $offset,
		);
	}

	public static function get_filters_from_request() {
		$date_from = isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '';
		$date_to   = isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '';

		return array(
			'date_from'    => $date_from,
			'date_to'      => $date_to,
			'agent_name'   => isset( $_GET['agent_name'] ) ? sanitize_text_field( wp_unslash( $_GET['agent_name'] ) ) : '',
			'contact_type' => isset( $_GET['contact_type'] ) ? sanitize_key( wp_unslash( $_GET['contact_type'] ) ) : '',
			'product_id'      => isset( $_GET['product_id'] ) ? absint( $_GET['product_id'] ) : 0,
			'device_type'     => isset( $_GET['device_type'] ) ? sanitize_key( wp_unslash( $_GET['device_type'] ) ) : '',
			'visitor_country' => isset( $_GET['visitor_country'] ) ? sanitize_text_field( wp_unslash( $_GET['visitor_country'] ) ) : '',
			'entry_source'    => isset( $_GET['entry_source'] ) ? sanitize_key( wp_unslash( $_GET['entry_source'] ) ) : '',
			'utm_campaign'    => isset( $_GET['utm_campaign'] ) ? sanitize_text_field( wp_unslash( $_GET['utm_campaign'] ) ) : '',
			'landing_path'    => isset( $_GET['landing_path'] ) ? sanitize_text_field( wp_unslash( $_GET['landing_path'] ) ) : '',
			'search'          => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '',
			'session_id'      => isset( $_GET['session_id'] ) ? sanitize_text_field( wp_unslash( $_GET['session_id'] ) ) : '',
		);
	}

	public static function build_where( array $filters ) {
		global $wpdb;

		$where  = array( '1=1' );
		$params = array();

		if ( ! empty( $filters['date_from'] ) ) {
			$where[]  = 'clicked_at >= %s';
			$params[] = $filters['date_from'] . ' 00:00:00';
		}

		if ( ! empty( $filters['date_to'] ) ) {
			$where[]  = 'clicked_at <= %s';
			$params[] = $filters['date_to'] . ' 23:59:59';
		}

		if ( ! empty( $filters['agent_name'] ) ) {
			$where[]  = 'agent_name = %s';
			$params[] = $filters['agent_name'];
		}

		if ( ! empty( $filters['contact_type'] ) ) {
			$where[]  = 'contact_type = %s';
			$params[] = $filters['contact_type'];
		}

		if ( ! empty( $filters['lead_channel'] ) && class_exists( 'ADCT_Leads' ) ) {
			$channel_types = ADCT_Leads::get_types_for_channel( $filters['lead_channel'] );

			if ( ! empty( $channel_types ) ) {
				$placeholders = implode( ',', array_fill( 0, count( $channel_types ), '%s' ) );
				$where[]      = "contact_type IN ({$placeholders})";
				$params       = array_merge( $params, $channel_types );
			}
		}

		if ( ! empty( $filters['product_id'] ) ) {
			$where[]  = 'product_id = %d';
			$params[] = $filters['product_id'];
		}

		if ( ! empty( $filters['device_type'] ) ) {
			$where[]  = 'device_type = %s';
			$params[] = $filters['device_type'];
		}

		if ( ! empty( $filters['visitor_country'] ) ) {
			$where[]  = 'visitor_country = %s';
			$params[] = $filters['visitor_country'];
		}

		if ( ! empty( $filters['entry_source'] ) ) {
			$where[]  = 'entry_source = %s';
			$params[] = $filters['entry_source'];
		}

		if ( ! empty( $filters['utm_campaign'] ) ) {
			$where[]  = 'utm_campaign = %s';
			$params[] = $filters['utm_campaign'];
		}

		if ( ! empty( $filters['landing_path'] ) ) {
			$where[]  = 'landing_path = %s';
			$params[] = $filters['landing_path'];
		}

		if ( ! empty( $filters['session_id'] ) ) {
			$session_id = sanitize_text_field( $filters['session_id'] );

			if ( 0 === strpos( $session_id, 'legacy-' ) ) {
				$where[]  = "session_id = '' AND id = %d";
				$params[] = absint( substr( $session_id, 7 ) );
			} else {
				$where[]  = 'session_id = %s';
				$params[] = $session_id;
			}
		}

		if ( ! empty( $filters['search'] ) ) {
			$like     = '%' . $wpdb->esc_like( $filters['search'] ) . '%';
			$where[]  = '(product_title LIKE %s OR product_url LIKE %s OR agent_name LIKE %s OR utm_term LIKE %s OR utm_campaign LIKE %s OR landing_path LIKE %s OR landing_url LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		return array(
			'sql'    => implode( ' AND ', $where ),
			'params' => $params,
		);
	}

	public static function get_session_group_expression() {
		return "CASE WHEN session_id <> '' THEN session_id ELSE CONCAT('legacy-', id) END";
	}

	public static function get_sessions( array $filters, $limit = 20, $offset = 0 ) {
		global $wpdb;

		$table      = self::table_name();
		$where      = self::build_where( $filters );
		$group_expr = self::get_session_group_expression();
		$sql        = "SELECT
				{$group_expr} AS session_key,
				MIN(clicked_at) AS session_started,
				MAX(clicked_at) AS session_ended,
				MAX(duration_seconds) AS session_duration,
				COUNT(*) AS click_count,
				MAX(landing_url) AS landing_url,
				MAX(landing_path) AS landing_path,
				MAX(landing_referrer) AS landing_referrer,
				MAX(entry_source) AS entry_source,
				MAX(utm_source) AS utm_source,
				MAX(utm_medium) AS utm_medium,
				MAX(utm_campaign) AS utm_campaign,
				MAX(utm_id) AS utm_id,
				MAX(utm_term) AS utm_term,
				MAX(utm_content) AS utm_content,
				MAX(gclid) AS gclid,
				MAX(visitor_country) AS visitor_country,
				MAX(visitor_region) AS visitor_region,
				MAX(device_type) AS device_type,
				MAX(browser_name) AS browser_name
			FROM {$table}
			WHERE {$where['sql']}
			GROUP BY {$group_expr}
			ORDER BY session_started DESC
			LIMIT %d OFFSET %d";

		$params   = $where['params'];
		$params[] = absint( $limit );
		$params[] = absint( $offset );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
	}

	public static function count_sessions( array $filters ) {
		global $wpdb;

		$table      = self::table_name();
		$where      = self::build_where( $filters );
		$group_expr = self::get_session_group_expression();
		$sql        = "SELECT COUNT(*) FROM (
				SELECT 1
				FROM {$table}
				WHERE {$where['sql']}
				GROUP BY {$group_expr}
			) AS adct_sessions";

		if ( empty( $where['params'] ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			return (int) $wpdb->get_var( $sql );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return (int) $wpdb->get_var( $wpdb->prepare( $sql, $where['params'] ) );
	}

	public static function get_clicks_for_session_keys( array $session_keys, array $filters ) {
		global $wpdb;

		if ( empty( $session_keys ) ) {
			return array();
		}

		$table       = self::table_name();
		$where       = self::build_where( $filters );
		$session_ids = array();
		$legacy_ids  = array();

		foreach ( $session_keys as $session_key ) {
			$session_key = (string) $session_key;

			if ( 0 === strpos( $session_key, 'legacy-' ) ) {
				$legacy_ids[] = absint( substr( $session_key, 7 ) );
				continue;
			}

			$session_ids[] = sanitize_text_field( $session_key );
		}

		$or_parts = array();
		$params   = $where['params'];

		if ( ! empty( $session_ids ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $session_ids ), '%s' ) );
			$or_parts[]   = "session_id IN ({$placeholders})";
			$params       = array_merge( $params, $session_ids );
		}

		if ( ! empty( $legacy_ids ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $legacy_ids ), '%d' ) );
			$or_parts[]   = "(session_id = '' AND id IN ({$placeholders}))";
			$params       = array_merge( $params, $legacy_ids );
		}

		if ( empty( $or_parts ) ) {
			return array();
		}

		$sql = "SELECT *,
				" . self::get_session_group_expression() . " AS session_key
			FROM {$table}
			WHERE {$where['sql']} AND (" . implode( ' OR ', $or_parts ) . ')
			ORDER BY clicked_at ASC';

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ) );

		$grouped = array();

		foreach ( $rows as $row ) {
			$key = $row->session_key ?? '';

			if ( ! isset( $grouped[ $key ] ) ) {
				$grouped[ $key ] = array();
			}

			$grouped[ $key ][] = $row;
		}

		return $grouped;
	}

	public static function get_summary( array $filters, $limit = 500, $offset = 0 ) {
		global $wpdb;

		$table = self::table_name();
		$where = self::build_where( $filters );
		$sql   = "SELECT product_id, product_title, product_url,
				MAX(product_price) AS product_price,
				MAX(product_mileage) AS product_mileage,
				MAX(product_image_url) AS product_image_url,
				MAX(clicked_at) AS last_clicked_at,
				agent_name, contact_type, COUNT(*) AS clicks
			FROM {$table}
			WHERE {$where['sql']}
			GROUP BY product_id, product_title, product_url, agent_name, contact_type
			ORDER BY clicks DESC, product_title ASC, agent_name ASC
			LIMIT %d OFFSET %d";

		$params   = $where['params'];
		$params[] = absint( $limit );
		$params[] = absint( $offset );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
	}

	public static function count_summary_groups( array $filters ) {
		global $wpdb;

		$table = self::table_name();
		$where = self::build_where( $filters );
		$sql   = "SELECT COUNT(*) FROM (
				SELECT 1
				FROM {$table}
				WHERE {$where['sql']}
				GROUP BY product_id, product_title, product_url, agent_name, contact_type
			) AS adct_summary_groups";

		if ( empty( $where['params'] ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			return (int) $wpdb->get_var( $sql );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return (int) $wpdb->get_var( $wpdb->prepare( $sql, $where['params'] ) );
	}

	public static function get_clicks( array $filters, $limit = 200, $offset = 0 ) {
		global $wpdb;

		$table = self::table_name();
		$where = self::build_where( $filters );
		$sql   = "SELECT * FROM {$table}
			WHERE {$where['sql']}
			ORDER BY clicked_at DESC
			LIMIT %d OFFSET %d";

		$params   = $where['params'];
		$params[] = absint( $limit );
		$params[] = absint( $offset );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
	}

	public static function count_clicks( array $filters ) {
		global $wpdb;

		$table = self::table_name();
		$where = self::build_where( $filters );
		$sql   = "SELECT COUNT(*) FROM {$table} WHERE {$where['sql']}";

		if ( empty( $where['params'] ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			return (int) $wpdb->get_var( $sql );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return (int) $wpdb->get_var( $wpdb->prepare( $sql, $where['params'] ) );
	}

	public static function get_distinct_values( $column ) {
		global $wpdb;

		$allowed = array(
			'agent_name'      => 'agent_name',
			'contact_type'    => 'contact_type',
			'device_type'     => 'device_type',
			'browser_name'    => 'browser_name',
			'visitor_country' => 'visitor_country',
			'entry_source'    => 'entry_source',
			'utm_campaign'    => 'utm_campaign',
			'landing_path'    => 'landing_path',
		);

		if ( ! isset( $allowed[ $column ] ) ) {
			return array();
		}

		$field = $allowed[ $column ];
		$table = self::table_name();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql = "SELECT DISTINCT {$field} FROM {$table} WHERE {$field} <> '' ORDER BY {$field} ASC";

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return $wpdb->get_col( $sql );
	}

	public static function count_clicks_since( $datetime_start ) {
		global $wpdb;

		$table = self::table_name();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE clicked_at >= %s",
				$datetime_start
			)
		);
	}

	public static function count_sessions_since( $datetime_start ) {
		global $wpdb;

		$table      = self::table_name();
		$group_expr = self::get_session_group_expression();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM (
					SELECT 1 FROM {$table} WHERE clicked_at >= %s GROUP BY {$group_expr}
				) AS adct_period_sessions",
				$datetime_start
			)
		);
	}

	public static function get_top_field_since( $field, $datetime_start ) {
		global $wpdb;

		$allowed = array(
			'utm_campaign' => 'utm_campaign',
			'landing_path' => 'landing_path',
		);

		if ( ! isset( $allowed[ $field ] ) ) {
			return '';
		}

		$column = $allowed[ $field ];
		$table  = self::table_name();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql = "SELECT {$column} FROM {$table}
			WHERE clicked_at >= %s AND {$column} <> ''
			GROUP BY {$column}
			ORDER BY COUNT(*) DESC
			LIMIT 1";

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return (string) $wpdb->get_var( $wpdb->prepare( $sql, $datetime_start ) );
	}

	public static function get_last_clicked_at() {
		global $wpdb;

		$table = self::table_name();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_var( "SELECT MAX(clicked_at) FROM {$table}" );
	}

	public static function get_live_snapshot() {
		$today_start = wp_date( 'Y-m-d' ) . ' 00:00:00';
		$week_start  = wp_date( 'Y-m-d', strtotime( '-7 days' ) ) . ' 00:00:00';

		return array(
			'clicks_today'    => self::count_clicks_since( $today_start ),
			'sessions_week'   => self::count_sessions_since( $week_start ),
			'top_campaign'    => self::get_top_field_since( 'utm_campaign', $week_start ),
			'top_landing'     => self::get_top_field_since( 'landing_path', $week_start ),
			'last_click'      => self::get_last_clicked_at(),
			'total_all_time'  => self::count_clicks( array() ),
			'sessions_all_time' => self::count_sessions( array() ),
		);
	}

	public static function get_session_row_key( $row ) {
		if ( is_object( $row ) ) {
			if ( ! empty( $row->session_id ) ) {
				return (string) $row->session_id;
			}

			if ( ! empty( $row->id ) ) {
				return 'legacy-' . absint( $row->id );
			}
		}

		return '';
	}

	public static function get_lead_session_context( array $rows ) {
		$session_ids = array();

		foreach ( $rows as $row ) {
			$key = self::get_session_row_key( $row );

			if ( '' !== $key && 0 !== strpos( $key, 'legacy-' ) ) {
				$session_ids[] = $key;
			}
		}

		$session_ids = array_values( array_unique( $session_ids ) );

		return array(
			'summaries' => self::get_session_summaries_by_ids( $session_ids ),
			'positions' => self::get_click_positions_for_rows( $rows ),
		);
	}

	public static function get_session_summaries_by_ids( array $session_ids ) {
		global $wpdb;

		$session_ids = array_values(
			array_filter(
				array_unique(
					array_map( 'sanitize_text_field', $session_ids )
				)
			)
		);

		if ( empty( $session_ids ) ) {
			return array();
		}

		$table        = self::table_name();
		$placeholders = implode( ',', array_fill( 0, count( $session_ids ), '%s' ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql = "SELECT session_id,
				COUNT(*) AS click_count,
				MIN(clicked_at) AS session_started,
				MAX(clicked_at) AS session_ended,
				MAX(entry_source) AS entry_source,
				MAX(device_type) AS device_type,
				MAX(visitor_country) AS visitor_country,
				MAX(landing_path) AS landing_path
			FROM {$table}
			WHERE session_id IN ({$placeholders})
			GROUP BY session_id";

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $session_ids ) );

		$summaries = array();

		foreach ( $rows as $row ) {
			$summaries[ $row->session_id ] = $row;
		}

		return $summaries;
	}

	public static function get_click_positions_for_rows( array $rows ) {
		global $wpdb;

		$session_ids = array();

		foreach ( $rows as $row ) {
			$key = self::get_session_row_key( $row );

			if ( '' !== $key && 0 !== strpos( $key, 'legacy-' ) ) {
				$session_ids[] = $key;
			}
		}

		$session_ids = array_values( array_unique( $session_ids ) );
		$positions   = array();

		foreach ( $rows as $row ) {
			$key = self::get_session_row_key( $row );

			if ( '' === $key ) {
				continue;
			}

			if ( 0 === strpos( $key, 'legacy-' ) ) {
				$positions[ absint( $row->id ) ] = array(
					'position' => 1,
					'total'    => 1,
				);
			}
		}

		if ( empty( $session_ids ) ) {
			return $positions;
		}

		$table        = self::table_name();
		$placeholders = implode( ',', array_fill( 0, count( $session_ids ), '%s' ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql = "SELECT id, session_id
			FROM {$table}
			WHERE session_id IN ({$placeholders})
			ORDER BY session_id ASC, clicked_at ASC, id ASC";

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$clicks = $wpdb->get_results( $wpdb->prepare( $sql, $session_ids ) );

		$grouped = array();

		foreach ( $clicks as $click ) {
			$grouped[ $click->session_id ][] = (int) $click->id;
		}

		foreach ( $grouped as $ids ) {
			$total = count( $ids );

			foreach ( $ids as $index => $click_id ) {
				$positions[ $click_id ] = array(
					'position' => $index + 1,
					'total'    => $total,
				);
			}
		}

		return $positions;
	}
}
