<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ADCT_License {

	const OPTION_KEY           = 'adct_license_key';
	const OPTION_STATUS        = 'adct_license_status';
	const OPTION_PLAN          = 'adct_license_plan';
	const OPTION_EXPIRES       = 'adct_license_expires';
	const OPTION_CHECKED_AT    = 'adct_license_checked_at';
	const OPTION_LAST_VALID_AT = 'adct_license_last_valid_at';
	const OPTION_MESSAGE       = 'adct_license_message';
	const OPTION_GRACE_UNTIL   = 'adct_license_grace_until';

	const TRANSIENT_VALID = 'adct_license_valid';

	const CRON_HOOK = 'adct_license_daily_check';

	const CACHE_TTL = 43200;

	const GRACE_DAYS = 14;

	const REMOTE_GRACE_DAYS = 7;

	const DEFAULT_API_URL = 'https://plugin.cubescenter.org/api/licenses.json';

	const PURCHASE_URL = 'https://github.com/benjamindimalanta/tracking-template';

	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'maybe_activate_license' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_deactivate_license' ) );
		add_action( 'admin_notices', array( __CLASS__, 'admin_notices' ) );
		add_action( self::CRON_HOOK, array( __CLASS__, 'cron_validate' ) );

		add_action( 'init', array( __CLASS__, 'maybe_schedule_cron' ) );
		add_action( 'update_option_' . self::OPTION_KEY, array( __CLASS__, 'clear_cache' ), 10, 0 );
	}

	public static function maybe_schedule_cron() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
		}

		self::maybe_start_grace_period();
	}

	public static function maybe_start_grace_period() {
		if ( get_option( self::OPTION_GRACE_UNTIL ) ) {
			return;
		}

		update_option(
			self::OPTION_GRACE_UNTIL,
			gmdate( 'Y-m-d H:i:s', time() + ( self::GRACE_DAYS * DAY_IN_SECONDS ) )
		);
	}

	public static function is_bypassed() {
		if ( defined( 'ADCT_LICENSE_BYPASS' ) && ADCT_LICENSE_BYPASS ) {
			return true;
		}

		return (bool) apply_filters( 'adct_license_bypass', false );
	}

	public static function is_active() {
		if ( self::is_bypassed() ) {
			return true;
		}

		if ( self::in_install_grace_period() ) {
			return true;
		}

		$key = self::get_key();

		if ( '' === $key ) {
			return false;
		}

		$cached = get_transient( self::TRANSIENT_VALID );

		if ( is_array( $cached ) && ! empty( $cached['active'] ) ) {
			return true;
		}

		$result = self::validate( $key, false );

		return ! empty( $result['active'] );
	}

	public static function in_install_grace_period() {
		$until = get_option( self::OPTION_GRACE_UNTIL );

		if ( ! $until ) {
			return false;
		}

		$timestamp = strtotime( (string) $until );

		if ( ! $timestamp ) {
			return false;
		}

		if ( '' !== self::get_key() && 'active' === get_option( self::OPTION_STATUS ) ) {
			return false;
		}

		return time() <= $timestamp;
	}

	public static function get_grace_days_remaining() {
		$until = get_option( self::OPTION_GRACE_UNTIL );

		if ( ! $until ) {
			return 0;
		}

		$timestamp = strtotime( (string) $until );

		if ( ! $timestamp ) {
			return 0;
		}

		return max( 0, (int) ceil( ( $timestamp - time() ) / DAY_IN_SECONDS ) );
	}

	public static function get_key() {
		return sanitize_text_field( (string) get_option( self::OPTION_KEY, '' ) );
	}

	public static function mask_key( $key ) {
		$key = self::normalize_license_key( (string) $key );

		if ( '' === $key ) {
			return '';
		}

		$parts = explode( '-', $key );

		if ( count( $parts ) >= 4 ) {
			return $parts[0] . '-****-****-' . $parts[ count( $parts ) - 1 ];
		}

		if ( strlen( $key ) <= 8 ) {
			return str_repeat( '*', strlen( $key ) );
		}

		return substr( $key, 0, 4 ) . str_repeat( '*', max( 4, strlen( $key ) - 8 ) ) . substr( $key, -4 );
	}

	public static function get_api_url() {
		$url = self::DEFAULT_API_URL;

		if ( defined( 'ADCT_LICENSE_API_URL' ) && ADCT_LICENSE_API_URL ) {
			$url = ADCT_LICENSE_API_URL;
		}

		return apply_filters( 'adct_license_api_url', $url );
	}

	public static function get_site_host() {
		$host = wp_parse_url( home_url( '/' ), PHP_URL_HOST );

		if ( ! is_string( $host ) || '' === $host ) {
			return '';
		}

		return strtolower( $host );
	}

	public static function normalize_license_key( $key ) {
		$key = strtoupper( trim( (string) $key ) );
		$key = preg_replace( '/\s+/', '', $key );

		return $key;
	}

	public static function clear_cache() {
		delete_transient( self::TRANSIENT_VALID );
	}

	public static function cron_validate() {
		$key = self::get_key();

		if ( '' === $key ) {
			return;
		}

		self::validate( $key, true );
	}

	public static function validate( $key, $force_remote = false ) {
		$key = self::normalize_license_key( $key );

		if ( '' === $key ) {
			return self::store_result(
				array(
					'active'  => false,
					'status'  => 'invalid',
					'message' => 'License key is required.',
				)
			);
		}

		if ( ! $force_remote ) {
			$cached = get_transient( self::TRANSIENT_VALID );

			if ( is_array( $cached ) && isset( $cached['key'] ) && $cached['key'] === $key ) {
				return $cached;
			}
		}

		$remote = self::fetch_remote_licenses();

		if ( is_wp_error( $remote ) ) {
			return self::handle_remote_failure( $remote );
		}

		$site_host = self::get_site_host();

		if ( in_array( $site_host, $remote['revoked_sites'], true ) ) {
			return self::store_result(
				array(
					'active'  => false,
					'status'  => 'revoked',
					'message' => 'This site has been revoked. Contact the plugin author.',
					'key'     => $key,
				)
			);
		}

		if ( in_array( $key, $remote['revoked_keys'], true ) ) {
			return self::store_result(
				array(
					'active'  => false,
					'status'  => 'revoked',
					'message' => 'This license key has been revoked.',
					'key'     => $key,
				)
			);
		}

		if ( empty( $remote['licenses'][ $key ] ) ) {
			return self::store_result(
				array(
					'active'  => false,
					'status'  => 'invalid',
					'message' => 'License key not found.',
					'key'     => $key,
				)
			);
		}

		$license = $remote['licenses'][ $key ];

		if ( empty( $license['active'] ) ) {
			return self::store_result(
				array(
					'active'  => false,
					'status'  => 'inactive',
					'message' => ! empty( $license['reason'] ) ? (string) $license['reason'] : 'License is inactive.',
					'key'     => $key,
				)
			);
		}

		if ( ! self::site_allowed_for_license( $license, $site_host ) ) {
			return self::store_result(
				array(
					'active'  => false,
					'status'  => 'invalid',
					'message' => 'This license key is not valid for ' . $site_host . '.',
					'key'     => $key,
				)
			);
		}

		$expires = isset( $license['expires'] ) ? (string) $license['expires'] : '';

		if ( $expires && strtotime( $expires ) < time() ) {
			return self::store_result(
				array(
					'active'  => false,
					'status'  => 'expired',
					'message' => 'License expired on ' . $expires . '.',
					'expires' => $expires,
					'key'     => $key,
				)
			);
		}

		return self::store_result(
			array(
				'active'  => true,
				'status'  => 'active',
				'message' => 'License is active.',
				'expires' => $expires,
				'plan'    => isset( $license['plan'] ) ? (string) $license['plan'] : '',
				'key'     => $key,
			),
			true
		);
	}

	private static function site_allowed_for_license( array $license, $site_host ) {
		$sites = isset( $license['sites'] ) && is_array( $license['sites'] ) ? $license['sites'] : array();

		if ( empty( $sites ) ) {
			return true;
		}

		$normalized = array();

		foreach ( $sites as $site ) {
			$site = strtolower( trim( (string) $site ) );
			$site = preg_replace( '#^www\.#', '', $site );

			if ( $site ) {
				$normalized[] = $site;
			}
		}

		$host = preg_replace( '#^www\.#', '', $site_host );

		return in_array( $host, $normalized, true );
	}

	private static function fetch_remote_licenses() {
		$response = wp_remote_get(
			self::get_api_url(),
			array(
				'timeout' => 15,
				'headers' => array(
					'Accept' => 'application/json',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		if ( 200 !== $code ) {
			if ( 404 === $code ) {
				return new WP_Error(
					'adct_license_missing',
					'License server not found. The author must publish licenses.json on GitHub first.'
				);
			}

			return new WP_Error(
				'adct_license_http',
				sprintf( 'License server returned HTTP %d.', $code )
			);
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $body ) ) {
			return new WP_Error( 'adct_license_json', 'License server returned invalid JSON.' );
		}

		return array(
			'licenses'      => isset( $body['licenses'] ) && is_array( $body['licenses'] ) ? $body['licenses'] : array(),
			'revoked_keys'  => isset( $body['revoked_keys'] ) && is_array( $body['revoked_keys'] ) ? array_map( array( __CLASS__, 'normalize_license_key' ), $body['revoked_keys'] ) : array(),
			'revoked_sites' => isset( $body['revoked_sites'] ) && is_array( $body['revoked_sites'] ) ? array_map( 'strtolower', $body['revoked_sites'] ) : array(),
		);
	}

	private static function handle_remote_failure( WP_Error $error ) {
		$last_valid = get_option( self::OPTION_LAST_VALID_AT );
		$message    = $error->get_error_message();

		if ( $last_valid ) {
			$elapsed = time() - (int) strtotime( (string) $last_valid );

			if ( $elapsed <= ( self::REMOTE_GRACE_DAYS * DAY_IN_SECONDS ) ) {
				return self::store_result(
					array(
						'active'  => true,
						'status'  => 'grace_remote',
						'message' => 'License server unreachable. Running on grace period.',
						'key'     => self::get_key(),
					),
					false
				);
			}
		}

		return self::store_result(
			array(
				'active'  => false,
				'status'  => 'unreachable',
				'message' => 'Could not reach license server: ' . $message,
				'key'     => self::get_key(),
			)
		);
	}

	private static function store_result( array $result, $mark_valid = false ) {
		$status = isset( $result['status'] ) ? sanitize_key( $result['status'] ) : 'invalid';

		update_option( self::OPTION_STATUS, $status );
		update_option( self::OPTION_MESSAGE, isset( $result['message'] ) ? sanitize_text_field( $result['message'] ) : '' );
		update_option( self::OPTION_EXPIRES, isset( $result['expires'] ) ? sanitize_text_field( $result['expires'] ) : '' );
		update_option( self::OPTION_PLAN, isset( $result['plan'] ) ? sanitize_text_field( $result['plan'] ) : '' );
		update_option( self::OPTION_CHECKED_AT, gmdate( 'Y-m-d H:i:s' ) );

		if ( ! empty( $result['active'] ) ) {
			update_option( self::OPTION_LAST_VALID_AT, gmdate( 'Y-m-d H:i:s' ) );
			delete_option( self::OPTION_GRACE_UNTIL );

			set_transient(
				self::TRANSIENT_VALID,
				$result,
				self::CACHE_TTL
			);
		} else {
			delete_transient( self::TRANSIENT_VALID );
		}

		return $result;
	}

	public static function get_status_summary() {
		if ( self::is_bypassed() ) {
			return array(
				'active'  => true,
				'status'  => 'bypass',
				'label'   => 'Bypassed',
				'message' => 'License checks are bypassed on this site.',
				'expires' => '',
				'plan'    => '',
			);
		}

		if ( self::in_install_grace_period() && '' === self::get_key() ) {
			return array(
				'active'  => true,
				'status'  => 'grace_install',
				'label'   => 'Activation required',
				'message' => sprintf(
					'Enter your license key within %d day(s) to keep tracking active.',
					self::get_grace_days_remaining()
				),
				'expires' => '',
				'plan'    => '',
			);
		}

		$status  = sanitize_key( (string) get_option( self::OPTION_STATUS, 'inactive' ) );
		$message = (string) get_option( self::OPTION_MESSAGE, '' );
		$expires = (string) get_option( self::OPTION_EXPIRES, '' );
		$plan    = (string) get_option( self::OPTION_PLAN, '' );
		$labels  = array(
			'active'        => 'Active',
			'inactive'      => 'Inactive',
			'invalid'       => 'Invalid',
			'expired'       => 'Expired',
			'revoked'       => 'Revoked',
			'unreachable'   => 'Unreachable',
			'grace_remote'  => 'Active (grace)',
			'grace_install' => 'Activation required',
		);

		return array(
			'active'  => self::is_active(),
			'status'  => $status,
			'label'   => $labels[ $status ] ?? ucfirst( $status ),
			'message' => $message,
			'expires' => $expires,
			'plan'    => $plan,
		);
	}

	public static function maybe_activate_license() {
		if ( ! ADCT_Settings::user_can_manage() ) {
			return;
		}

		if ( empty( $_POST['adct_activate_license'] ) ) {
			return;
		}

		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

		if ( ! in_array( $page, ADCT_Settings::PLUGIN_PAGES, true ) ) {
			return;
		}

		check_admin_referer( 'adct_activate_license' );

		$key    = isset( $_POST['adct_license_key'] ) ? self::normalize_license_key( wp_unslash( $_POST['adct_license_key'] ) ) : '';
		$result = self::validate( $key, true );

		if ( ! empty( $result['active'] ) ) {
			update_option( self::OPTION_KEY, $key );
		} else {
			delete_option( self::OPTION_KEY );
		}

		$redirect_args = array(
			'page'          => $page,
			'adct_license'  => ! empty( $result['active'] ) ? 'activated' : 'failed',
		);

		wp_safe_redirect( add_query_arg( $redirect_args, admin_url( 'admin.php' ) ) );
		exit;
	}

	public static function maybe_deactivate_license() {
		if ( ! ADCT_Settings::user_can_manage() ) {
			return;
		}

		if ( empty( $_POST['adct_deactivate_license'] ) ) {
			return;
		}

		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

		if ( 'tracking-template-license' !== $page ) {
			return;
		}

		check_admin_referer( 'adct_deactivate_license' );

		self::deactivate_license();

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'         => $page,
					'adct_license' => 'deactivated',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	public static function admin_notices() {
		if ( ! ADCT_Settings::user_can_manage() || ! ADCT_Settings::is_plugin_admin_page() ) {
			return;
		}

		if ( isset( $_GET['adct_license'] ) && 'deactivated' === $_GET['adct_license'] ) {
			echo '<div class="notice notice-info is-dismissible"><p>License deactivated on this site.</p></div>';
		}

		if ( isset( $_GET['adct_license'] ) && 'activated' === $_GET['adct_license'] ) {
			echo '<div class="notice notice-success is-dismissible"><p>License activated successfully.</p></div>';
		}

		if ( isset( $_GET['adct_license'] ) && 'failed' === $_GET['adct_license'] ) {
			$summary = self::get_status_summary();
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $summary['message'] ?: 'License activation failed.' ) . '</p></div>';
		}

		if ( self::is_active() && self::in_install_grace_period() && '' === self::get_key() ) {
			$license_url = admin_url( 'admin.php?page=tracking-template-license' );
			echo '<div class="notice notice-warning"><p><strong>Tracking Template:</strong> Enter your license key within ';
			echo esc_html( (string) self::get_grace_days_remaining() );
			echo ' day(s). <a href="' . esc_url( $license_url ) . '">Activate license</a></p></div>';
		}
	}

	public static function deactivate_license() {
		delete_option( self::OPTION_KEY );
		delete_option( self::OPTION_STATUS );
		delete_option( self::OPTION_PLAN );
		delete_option( self::OPTION_EXPIRES );
		delete_option( self::OPTION_MESSAGE );
		self::clear_cache();
	}
}
