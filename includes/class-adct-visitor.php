<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ADCT_Visitor {

	public static function get_context_for_request() {
		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] )
			? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) )
			: '';

		$parsed = self::parse_user_agent( $user_agent );
		$geo    = self::get_geo();

		return array(
			'device_type'     => $parsed['device_type'],
			'browser_name'    => $parsed['browser_name'],
			'visitor_country' => $geo['visitor_country'],
			'visitor_region'  => $geo['visitor_region'],
		);
	}

	public static function get_client_ip() {
		$candidates = array(
			'HTTP_CF_CONNECTING_IP',
			'HTTP_X_REAL_IP',
			'HTTP_X_FORWARDED_FOR',
			'REMOTE_ADDR',
		);

		foreach ( $candidates as $key ) {
			if ( empty( $_SERVER[ $key ] ) ) {
				continue;
			}

			$raw = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );

			if ( 'HTTP_X_FORWARDED_FOR' === $key ) {
				$parts = array_map( 'trim', explode( ',', $raw ) );

				foreach ( $parts as $part ) {
					if ( self::is_public_ip( $part ) ) {
						return $part;
					}
				}

				continue;
			}

			if ( self::is_public_ip( $raw ) ) {
				return $raw;
			}
		}

		return '';
	}

	public static function parse_user_agent( $user_agent ) {
		$user_agent = (string) $user_agent;

		if ( '' === $user_agent ) {
			return array(
				'device_type'  => '',
				'browser_name' => '',
			);
		}

		$device = 'desktop';

		if ( preg_match( '/ipad|tablet|playbook|silk|(android(?!.*mobile))/i', $user_agent ) ) {
			$device = 'tablet';
		} elseif ( preg_match( '/mobile|iphone|ipod|android.*mobile|windows phone|blackberry/i', $user_agent ) ) {
			$device = 'mobile';
		}

		$browser = 'Unknown';

		if ( preg_match( '/EdgA|EdgiOS|Edg\//i', $user_agent ) ) {
			$browser = ( 'mobile' === $device ) ? 'Edge Mobile' : 'Edge';
		} elseif ( preg_match( '/OPR\/|Opera/i', $user_agent ) ) {
			$browser = ( 'mobile' === $device ) ? 'Opera Mobile' : 'Opera';
		} elseif ( preg_match( '/SamsungBrowser/i', $user_agent ) ) {
			$browser = 'Samsung Internet';
		} elseif ( preg_match( '/CriOS/i', $user_agent ) ) {
			$browser = 'Chrome Mobile';
		} elseif ( preg_match( '/Chrome/i', $user_agent ) ) {
			$browser = ( 'mobile' === $device || 'tablet' === $device ) ? 'Chrome Mobile' : 'Chrome';
		} elseif ( preg_match( '/FxiOS/i', $user_agent ) ) {
			$browser = 'Firefox Mobile';
		} elseif ( preg_match( '/Firefox/i', $user_agent ) ) {
			$browser = ( 'mobile' === $device ) ? 'Firefox Mobile' : 'Firefox';
		} elseif ( preg_match( '/iPhone|iPad|iPod/i', $user_agent ) && preg_match( '/Safari/i', $user_agent ) ) {
			$browser = ( 'desktop' === $device ) ? 'Safari' : 'Mobile Safari';
		} elseif ( preg_match( '/Safari/i', $user_agent ) ) {
			$browser = 'Safari';
		}

		return array(
			'device_type'  => $device,
			'browser_name' => $browser,
		);
	}

	public static function get_geo() {
		$country = '';
		$region  = '';

		if ( ! empty( $_SERVER['HTTP_CF_IPCOUNTRY'] ) ) {
			$country = self::country_code_to_name(
				sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_IPCOUNTRY'] ) )
			);
		}

		$ip = self::get_client_ip();

		if ( ( '' === $ip || self::is_local_ip( $ip ) ) && self::should_use_local_geo_fallback() ) {
			return self::lookup_geo_cached( 'local-outbound' );
		}

		if ( '' === $ip || self::is_local_ip( $ip ) ) {
			return array(
				'visitor_country' => $country,
				'visitor_region'  => $region,
			);
		}

		return self::lookup_geo_cached( $ip, $country );
	}

	private static function lookup_geo_cached( $ip_key, $country_hint = '' ) {
		$cache_key = 'adct_geo_' . md5( (string) $ip_key );
		$cached    = get_transient( $cache_key );

		if ( is_array( $cached ) ) {
			return array(
				'visitor_country' => $cached['visitor_country'] ?? $country_hint,
				'visitor_region'  => $cached['visitor_region'] ?? '',
			);
		}

		$lookup  = self::lookup_geo( $ip_key );
		$country = $country_hint;

		if ( '' === $country && ! empty( $lookup['visitor_country'] ) ) {
			$country = $lookup['visitor_country'];
		}

		$data = array(
			'visitor_country' => $country,
			'visitor_region'  => $lookup['visitor_region'] ?? '',
		);

		set_transient( $cache_key, $data, DAY_IN_SECONDS );

		return $data;
	}

	private static function should_use_local_geo_fallback() {
		$host = wp_parse_url( home_url(), PHP_URL_HOST );

		if ( ! is_string( $host ) || '' === $host ) {
			return false;
		}

		$host = strtolower( $host );

		return in_array( $host, array( 'localhost', '127.0.0.1', '::1' ), true )
			|| str_ends_with( $host, '.local' );
	}

	private static function lookup_geo( $ip ) {
		$lookup = self::lookup_geo_ip_api( $ip );

		if ( ! empty( $lookup['visitor_country'] ) ) {
			return $lookup;
		}

		return self::lookup_geo_ipapi_co( $ip );
	}

	private static function lookup_geo_ip_api( $ip ) {
		if ( '' === $ip || 'local-outbound' === $ip ) {
			$url = 'http://ip-api.com/json/?fields=status,country,regionName';
		} else {
			$url = add_query_arg(
				array(
					'fields' => 'status,country,regionName',
				),
				'http://ip-api.com/json/' . rawurlencode( $ip )
			);
		}

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 3,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'visitor_country' => '',
				'visitor_region'  => '',
			);
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $body['status'] ) || 'success' !== $body['status'] ) {
			return array(
				'visitor_country' => '',
				'visitor_region'  => '',
			);
		}

		return array(
			'visitor_country' => sanitize_text_field( $body['country'] ?? '' ),
			'visitor_region'  => sanitize_text_field( $body['regionName'] ?? '' ),
		);
	}

	private static function lookup_geo_ipapi_co( $ip ) {
		if ( '' === $ip || 'local-outbound' === $ip ) {
			$url = 'https://ipapi.co/json/';
		} else {
			$url = 'https://ipapi.co/' . rawurlencode( $ip ) . '/json/';
		}

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 3,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'visitor_country' => '',
				'visitor_region'  => '',
			);
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! empty( $body['error'] ) ) {
			return array(
				'visitor_country' => '',
				'visitor_region'  => '',
			);
		}

		$region = $body['region'] ?? $body['city'] ?? '';

		return array(
			'visitor_country' => sanitize_text_field( $body['country_name'] ?? '' ),
			'visitor_region'  => sanitize_text_field( $region ),
		);
	}

	private static function country_code_to_name( $code ) {
		$code = strtoupper( trim( (string) $code ) );

		if ( '' === $code || 'XX' === $code || 'T1' === $code ) {
			return '';
		}

		if ( function_exists( 'locale_get_display_region' ) ) {
			$name = locale_get_display_region( 'und_' . $code, 'en' );

			if ( $name && $name !== $code ) {
				return $name;
			}
		}

		$map = array(
			'AE' => 'United Arab Emirates',
			'SA' => 'Saudi Arabia',
			'OM' => 'Oman',
			'QA' => 'Qatar',
			'BH' => 'Bahrain',
			'KW' => 'Kuwait',
			'US' => 'United States',
			'GB' => 'United Kingdom',
			'IN' => 'India',
			'PK' => 'Pakistan',
		);

		return $map[ $code ] ?? $code;
	}

	private static function is_public_ip( $ip ) {
		return (bool) filter_var(
			$ip,
			FILTER_VALIDATE_IP,
			FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
		);
	}

	private static function is_local_ip( $ip ) {
		return in_array( $ip, array( '127.0.0.1', '::1' ), true ) || ! self::is_public_ip( $ip );
	}
}
