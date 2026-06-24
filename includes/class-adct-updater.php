<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ADCT_Updater {

	const DEFAULT_UPDATE_URL = 'https://plugin.cubescenter.org/api/update.json';

	const CACHE_KEY = 'adct_plugin_update';

	const CACHE_TTL = 43200;

	public static function init() {
		add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'inject_update' ) );
		add_filter( 'plugins_api', array( __CLASS__, 'plugin_info' ), 20, 3 );
		add_filter( 'upgrader_source_selection', array( __CLASS__, 'fix_source_directory' ), 10, 4 );
	}

	public static function get_plugin_basename() {
		return plugin_basename( ADCT_PLUGIN_FILE );
	}

	public static function get_update_api_url() {
		$url = self::DEFAULT_UPDATE_URL;

		if ( defined( 'ADCT_UPDATE_API_URL' ) && ADCT_UPDATE_API_URL ) {
			$url = ADCT_UPDATE_API_URL;
		}

		return apply_filters( 'adct_update_api_url', $url );
	}

	public static function normalize_version( $version ) {
		return ltrim( (string) $version, 'vV' );
	}

	private static function hub_get( $url ) {
		return wp_remote_get(
			$url,
			array(
				'timeout' => 15,
				'headers' => array(
					'Accept'     => 'application/json',
					'User-Agent' => 'Tracking-Template-WordPress-Plugin',
				),
			)
		);
	}

	public static function fetch_latest_release( $force = false ) {
		if ( ! $force ) {
			$cached = get_transient( self::CACHE_KEY );

			if ( is_array( $cached ) && ! empty( $cached['version'] ) ) {
				return $cached;
			}
		}

		$response = self::hub_get( self::get_update_api_url() );

		if ( is_wp_error( $response ) ) {
			return array(
				'version'      => ADCT_VERSION,
				'download_url' => '',
				'html_url'     => 'https://plugin.cubescenter.org',
				'error'        => $response->get_error_message(),
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		if ( 200 !== $code ) {
			return array(
				'version'      => ADCT_VERSION,
				'download_url' => '',
				'html_url'     => 'https://plugin.cubescenter.org',
				'error'        => 'Update server returned HTTP ' . $code,
			);
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $data ) || empty( $data['version'] ) ) {
			return array(
				'version'      => ADCT_VERSION,
				'download_url' => '',
				'html_url'     => 'https://plugin.cubescenter.org',
				'error'        => 'Update server returned invalid JSON.',
			);
		}

		$release = array(
			'version'      => self::normalize_version( $data['version'] ),
			'download_url' => isset( $data['download_url'] ) ? (string) $data['download_url'] : '',
			'html_url'     => isset( $data['homepage'] ) ? (string) $data['homepage'] : 'https://plugin.cubescenter.org',
			'name'         => isset( $data['name'] ) ? (string) $data['name'] : 'Tracking Template',
			'body'         => isset( $data['changelog'] ) ? (string) $data['changelog'] : '',
			'published_at' => '',
			'requires'     => isset( $data['requires'] ) ? (string) $data['requires'] : '5.8',
			'requires_php' => isset( $data['requires_php'] ) ? (string) $data['requires_php'] : '7.4',
			'tested'       => isset( $data['tested'] ) ? (string) $data['tested'] : '',
		);

		set_transient( self::CACHE_KEY, $release, self::CACHE_TTL );

		return $release;
	}

	public static function get_version_info( $force = false ) {
		$release = self::fetch_latest_release( $force );
		$latest  = isset( $release['version'] ) ? self::normalize_version( $release['version'] ) : ADCT_VERSION;
		$current = self::normalize_version( ADCT_VERSION );
		$has     = version_compare( $current, $latest, '<' );

		$info = array(
			'current'      => $current,
			'latest'       => $latest,
			'has_update'   => $has,
			'download_url' => $release['download_url'] ?? '',
			'release_url'  => $release['html_url'] ?? 'https://plugin.cubescenter.org',
			'error'        => $release['error'] ?? '',
			'up_to_date'   => ! $has,
		);

		if ( $has && ADCT_Settings::user_can_manage() ) {
			$plugin_file        = self::get_plugin_basename();
			$info['update_url'] = wp_nonce_url(
				self_admin_url( 'update.php?action=upgrade-plugin&plugin=' . rawurlencode( $plugin_file ) ),
				'upgrade-plugin_' . $plugin_file
			);
		}

		return $info;
	}

	public static function inject_update( $transient ) {
		if ( ! is_object( $transient ) ) {
			return $transient;
		}

		$info = self::get_version_info();

		if ( empty( $info['has_update'] ) || empty( $info['download_url'] ) ) {
			return $transient;
		}

		$plugin_file = self::get_plugin_basename();
		$release     = self::fetch_latest_release();

		$transient->response[ $plugin_file ] = (object) array(
			'slug'         => 'tracking-template',
			'plugin'       => $plugin_file,
			'new_version'  => $info['latest'],
			'url'          => $info['release_url'],
			'package'      => $info['download_url'],
			'icons'        => array(),
			'banners'      => array(),
			'banners_rtl'  => array(),
			'tested'       => ! empty( $release['tested'] ) ? $release['tested'] : get_bloginfo( 'version' ),
			'requires_php' => $release['requires_php'] ?? '7.4',
		);

		return $transient;
	}

	public static function plugin_info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action ) {
			return $result;
		}

		if ( empty( $args->slug ) || 'tracking-template' !== $args->slug ) {
			return $result;
		}

		$info    = self::get_version_info( true );
		$release = self::fetch_latest_release();

		$result                = new stdClass();
		$result->name          = 'Tracking Template';
		$result->slug          = 'tracking-template';
		$result->version       = $info['latest'];
		$result->author        = '<a href="https://plugin.cubescenter.org">Benjamin Clar</a>';
		$result->homepage      = $info['release_url'];
		$result->requires      = $release['requires'] ?? '5.8';
		$result->requires_php  = $release['requires_php'] ?? '7.4';
		$result->download_link = $info['download_url'];
		$result->sections      = array(
			'description' => 'WordPress contact-click tracking with marketing attribution and session reporting.',
			'changelog'   => ! empty( $release['body'] ) ? wp_kses_post( wpautop( $release['body'] ) ) : '',
		);

		return $result;
	}

	public static function fix_source_directory( $source, $remote_source, $upgrader, $hook_extra ) {
		if ( empty( $hook_extra['plugin'] ) || self::get_plugin_basename() !== $hook_extra['plugin'] ) {
			return $source;
		}

		global $wp_filesystem;

		if ( ! $wp_filesystem ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		$plugin_dir    = dirname( ADCT_PLUGIN_FILE );
		$plugin_folder = basename( $plugin_dir );

		if ( basename( $source ) === $plugin_folder ) {
			return $source;
		}

		$candidates = array(
			trailingslashit( $source ) . $plugin_folder,
			trailingslashit( dirname( $source ) ) . $plugin_folder,
		);

		foreach ( $candidates as $candidate ) {
			if ( $wp_filesystem->is_dir( $candidate ) ) {
				return $candidate;
			}
		}

		$new_source = trailingslashit( dirname( $source ) ) . $plugin_folder;

		if ( $wp_filesystem->move( $source, $new_source ) ) {
			return $new_source;
		}

		return $source;
	}
}
