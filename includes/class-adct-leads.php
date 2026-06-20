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

	public static function format_lead_datetime( $datetime ) {
		if ( empty( $datetime ) ) {
			return '—';
		}

		$timestamp = strtotime( (string) $datetime );

		if ( ! $timestamp ) {
			return (string) $datetime;
		}

		$today     = wp_date( 'Y-m-d' );
		$yesterday = wp_date( 'Y-m-d', strtotime( '-1 day' ) );
		$day       = wp_date( 'Y-m-d', $timestamp );
		$time      = wp_date( 'g:i A', $timestamp );

		if ( $day === $today ) {
			return sprintf(
				/* translators: %s: time */
				__( 'Today %s', 'tracking-template' ),
				$time
			);
		}

		if ( $day === $yesterday ) {
			return sprintf(
				/* translators: %s: time */
				__( 'Yesterday %s', 'tracking-template' ),
				$time
			);
		}

		return wp_date( 'M j, Y · g:i A', $timestamp );
	}

	public static function build_channel_tab_url( $channel, array $args = array() ) {
		$args['page']         = 'tracking-template-leads';
		$args['lead_channel'] = $channel;
		unset( $args['paged'] );

		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}
}
