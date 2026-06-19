<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ADCT_Ajax {

	const ALLOWED_CONTACT_TYPES = array(
		'whatsapp',
		'phone',
		'showroom_landline',
		'floating_whatsapp',
		'floating_phone',
		'elfsight_call',
		'footer_landline',
	);

	const SITEWIDE_CONTACT_TYPES = array(
		'floating_whatsapp',
		'floating_phone',
		'elfsight_call',
		'footer_landline',
	);

	public static function init() {
		add_action( 'wp_ajax_adct_log_click', array( __CLASS__, 'log_click' ) );
		add_action( 'wp_ajax_nopriv_adct_log_click', array( __CLASS__, 'log_click' ) );
	}

	public static function log_click() {
		check_ajax_referer( 'adct_log_click', 'nonce' );

		$product_id   = isset( $_POST['product_id'] ) ? absint( wp_unslash( $_POST['product_id'] ) ) : 0;
		$contact_type = isset( $_POST['contact_type'] ) ? sanitize_key( wp_unslash( $_POST['contact_type'] ) ) : '';

		if ( ! in_array( $contact_type, self::ALLOWED_CONTACT_TYPES, true ) ) {
			wp_send_json_error( array( 'message' => 'Invalid contact type.' ), 400 );
		}

		$is_site_wide = in_array( $contact_type, self::SITEWIDE_CONTACT_TYPES, true );

		if ( $is_site_wide ) {
			if ( $product_id && 'product' !== get_post_type( $product_id ) ) {
				$product_id = 0;
			}
		} elseif ( ! $product_id || 'product' !== get_post_type( $product_id ) ) {
			wp_send_json_error( array( 'message' => 'Invalid product.' ), 400 );
		}

		$product_title = isset( $_POST['product_title'] ) ? sanitize_text_field( wp_unslash( $_POST['product_title'] ) ) : '';
		$product_url   = isset( $_POST['product_url'] ) ? esc_url_raw( wp_unslash( $_POST['product_url'] ) ) : '';

		if ( $product_id ) {
			if ( '' === $product_title ) {
				$product_title = get_the_title( $product_id );
			}

			if ( '' === $product_url ) {
				$product_url = get_permalink( $product_id );
			}
		}

		$saved = ADCT_Database::insert_click(
			array(
				'product_id'        => $product_id,
				'product_title'     => $product_title,
				'product_url'       => $product_url,
				'agent_id'          => isset( $_POST['agent_id'] ) ? wp_unslash( $_POST['agent_id'] ) : '',
				'agent_name'        => isset( $_POST['agent_name'] ) ? wp_unslash( $_POST['agent_name'] ) : '',
				'contact_type'      => $contact_type,
				'clicked_value'     => isset( $_POST['clicked_value'] ) ? wp_unslash( $_POST['clicked_value'] ) : '',
				'source'            => isset( $_POST['source'] ) ? wp_unslash( $_POST['source'] ) : '',
				'landing_url'       => isset( $_POST['landing_url'] ) ? wp_unslash( $_POST['landing_url'] ) : '',
				'landing_path'      => isset( $_POST['landing_path'] ) ? wp_unslash( $_POST['landing_path'] ) : '',
				'landing_referrer'  => isset( $_POST['landing_referrer'] ) ? wp_unslash( $_POST['landing_referrer'] ) : '',
				'entry_source'      => isset( $_POST['entry_source'] ) ? wp_unslash( $_POST['entry_source'] ) : '',
				'utm_source'        => isset( $_POST['utm_source'] ) ? wp_unslash( $_POST['utm_source'] ) : '',
				'utm_medium'        => isset( $_POST['utm_medium'] ) ? wp_unslash( $_POST['utm_medium'] ) : '',
				'utm_campaign'      => isset( $_POST['utm_campaign'] ) ? wp_unslash( $_POST['utm_campaign'] ) : '',
				'utm_id'            => isset( $_POST['utm_id'] ) ? wp_unslash( $_POST['utm_id'] ) : '',
				'utm_term'          => isset( $_POST['utm_term'] ) ? wp_unslash( $_POST['utm_term'] ) : '',
				'utm_content'       => isset( $_POST['utm_content'] ) ? wp_unslash( $_POST['utm_content'] ) : '',
				'gclid'             => isset( $_POST['gclid'] ) ? wp_unslash( $_POST['gclid'] ) : '',
				'duration_seconds'  => isset( $_POST['duration_seconds'] ) ? wp_unslash( $_POST['duration_seconds'] ) : 0,
				'session_id'        => isset( $_POST['session_id'] ) ? wp_unslash( $_POST['session_id'] ) : '',
			)
		);

		if ( ! $saved ) {
			wp_send_json_error( array( 'message' => 'Could not save click.' ), 500 );
		}

		wp_send_json_success( array( 'logged' => true ) );
	}
}
