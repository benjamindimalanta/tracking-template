<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ADCT_Leads {

	const PHONE_TYPES = array(
		'phone',
		'floating_phone',
		'showroom_landline',
		'footer_landline',
		'elfsight_call',
	);

	const WHATSAPP_TYPES = array(
		'whatsapp',
		'floating_whatsapp',
	);

	const CHANNELS = array( 'all', 'phone', 'whatsapp' );

	const NON_SALESMAN_CONTACT_TYPES = array(
		'elfsight_call',
		'footer_landline',
	);

	const PLACEHOLDER_AGENT_NAMES = array(
		'Elfsight Call Us',
		'Showroom Landline',
	);

	public static function get_channel_from_request() {
		$channel = isset( $_GET['lead_channel'] ) ? sanitize_key( wp_unslash( $_GET['lead_channel'] ) ) : 'all';

		if ( ! in_array( $channel, self::CHANNELS, true ) ) {
			return 'all';
		}

		return $channel;
	}

	public static function get_types_for_channel( $channel ) {
		if ( 'phone' === $channel ) {
			return self::PHONE_TYPES;
		}

		if ( 'whatsapp' === $channel ) {
			return self::WHATSAPP_TYPES;
		}

		return array();
	}

	public static function apply_channel_filter( array $filters, $channel ) {
		if ( 'all' === $channel ) {
			unset( $filters['lead_channel'] );
			return $filters;
		}

		$filters['lead_channel'] = $channel;

		return $filters;
	}

	public static function get_channel_counts( array $filters ) {
		$base = $filters;
		unset( $base['lead_channel'] );

		return array(
			'all'      => ADCT_Database::count_clicks( $base ),
			'phone'    => ADCT_Database::count_clicks( self::apply_channel_filter( $base, 'phone' ) ),
			'whatsapp' => ADCT_Database::count_clicks( self::apply_channel_filter( $base, 'whatsapp' ) ),
		);
	}

	public static function get_lead_status_label( $contact_type ) {
		$contact_type = sanitize_key( (string) $contact_type );

		if ( in_array( $contact_type, self::WHATSAPP_TYPES, true ) ) {
			return 'WhatsApp lead';
		}

		if ( in_array( $contact_type, self::PHONE_TYPES, true ) ) {
			if ( 'elfsight_call' === $contact_type ) {
				return 'Widget call lead';
			}

			if ( 'showroom_landline' === $contact_type || 'footer_landline' === $contact_type ) {
				return 'Showroom lead';
			}

			return 'Phone lead';
		}

		return ADCT_Admin::format_contact_type_label( $contact_type ) . ' lead';
	}

	public static function is_salesman_attributed_click( $contact_type, $agent_name = '' ) {
		$contact_type = sanitize_key( (string) $contact_type );
		$agent_name   = trim( (string) $agent_name );

		if ( '' === $agent_name ) {
			return false;
		}

		if ( in_array( $contact_type, self::NON_SALESMAN_CONTACT_TYPES, true ) ) {
			return false;
		}

		return ! in_array( $agent_name, self::PLACEHOLDER_AGENT_NAMES, true );
	}

	public static function format_salesman_name( $contact_type, $agent_name ) {
		if ( ! self::is_salesman_attributed_click( $contact_type, $agent_name ) ) {
			return '—';
		}

		return $agent_name;
	}

	public static function format_lead_datetime( $datetime ) {
		$parts = self::format_lead_datetime_parts( $datetime );

		if ( empty( $parts['date'] ) ) {
			return '—';
		}

		if ( empty( $parts['time'] ) ) {
			return $parts['date'];
		}

		return $parts['date'] . ' ' . $parts['time'];
	}

	public static function format_lead_datetime_parts( $datetime ) {
		if ( empty( $datetime ) ) {
			return array(
				'date' => '—',
				'time' => '',
			);
		}

		// clicked_at is stored via current_time( 'mysql' ) — already site-local.
		$timestamp = (int) mysql2date( 'U', (string) $datetime, false );

		if ( $timestamp <= 0 ) {
			return array(
				'date' => (string) $datetime,
				'time' => '',
			);
		}

		$today     = wp_date( 'Y-m-d' );
		$yesterday = wp_date( 'Y-m-d', time() - DAY_IN_SECONDS );
		$day       = wp_date( 'Y-m-d', $timestamp );
		$time      = wp_date( 'g:i:s A', $timestamp );

		if ( $day === $today ) {
			return array(
				'date' => __( 'Today', 'tracking-template' ),
				'time' => $time,
			);
		}

		if ( $day === $yesterday ) {
			return array(
				'date' => __( 'Yesterday', 'tracking-template' ),
				'time' => $time,
			);
		}

		return array(
			'date' => wp_date( 'M j, Y', $timestamp ),
			'time' => wp_date( 'g:i:s A', $timestamp ),
		);
	}

	public static function build_channel_tab_url( $channel, array $args = array() ) {
		$args['page']         = 'tracking-template-leads';
		$args['lead_channel'] = $channel;
		unset( $args['paged'] );

		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}

	public static function format_session_code( $session_key ) {
		$session_key = (string) $session_key;

		if ( '' === $session_key ) {
			return '—';
		}

		if ( 0 === strpos( $session_key, 'legacy-' ) ) {
			return '#' . substr( $session_key, 7 );
		}

		$session_key = str_replace( '-', '', $session_key );

		return '#' . strtoupper( substr( $session_key, -4 ) );
	}

	public static function build_session_view_url( $session_key ) {
		$session_key = (string) $session_key;

		if ( '' === $session_key ) {
			return '';
		}

		return add_query_arg(
			array(
				'page'       => 'tracking-template-sessions',
				'session_id' => rawurlencode( $session_key ),
			),
			admin_url( 'admin.php' )
		);
	}

	public static function get_session_key_for_row( $row ) {
		return ADCT_Database::get_session_row_key( $row );
	}
}
