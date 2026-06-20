<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ADCT_Updater {

	const GITHUB_REPO = 'benjamindimalanta/tracking-template';

	const CACHE_KEY = 'adct_github_release';

	const CACHE_TTL = 43200;

	public static function init() {
		add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'inject_update' ) );
		add_filter( 'plugins_api', array( __CLASS__, 'plugin_info' ), 20, 3 );
		add_filter( 'upgrader_source_selection', array( __CLASS__, 'fix_source_directory' ), 10, 4 );
	}

	public static function get_plugin_basename() {
		return plugin_basename( ADCT_PLUGIN_FILE );
	}

	public static function normalize_version( $version ) {
		return ltrim( (string) $version, 'vV' );
	}

	private static function github_get( $url ) {
		return wp_remote_get(
			$url,
			array(
				'timeout' => 15,
				'headers' => array(
					'Accept'     => 'application/vnd.github+json',
					'User-Agent' => 'Tracking-Template-WordPress-Plugin',
				),
			)
		);
	}

	private static function fetch_latest_tag() {
		$response = self::github_get( 'https://api.github.com/repos/' . self::GITHUB_REPO . '/tags' );

		if ( is_wp_error( $response ) ) {
			return array(
				'version'      => ADCT_VERSION,
				'download_url' => '',
				'html_url'     => 'https://github.com/' . self::GITHUB_REPO . '/releases',
				'error'        => $response->get_error_message(),
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		if ( 200 !== $code ) {
			return array(
				'version'      => ADCT_VERSION,
				'download_url' => '',
				'html_url'     => 'https://github.com/' . self::GITHUB_REPO . '/releases',
				'error'        => 'GitHub API returned HTTP ' . $code,
			);
		}

		$tags = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $tags[0]['name'] ) ) {
			return array(
				'version'      => ADCT_VERSION,
				'download_url' => '',
				'html_url'     => 'https://github.com/' . self::GITHUB_REPO . '/releases',
				'error'        => 'No tags found on GitHub.',
			);
		}

		$tag_name = (string) $tags[0]['name'];

		return array(
			'version'      => self::normalize_version( $tag_name ),
			'download_url' => 'https://github.com/' . self::GITHUB_REPO . '/archive/refs/tags/' . rawurlencode( $tag_name ) . '.zip',
			'html_url'     => 'https://github.com/' . self::GITHUB_REPO . '/releases/tag/' . rawurlencode( $tag_name ),
			'name'         => $tag_name,
			'body'         => '',
			'published_at' => '',
		);
	}

	public static function fetch_latest_release( $force = false ) {
		if ( ! $force ) {
			$cached = get_transient( self::CACHE_KEY );

			if ( is_array( $cached ) && ! empty( $cached['version'] ) ) {
				return $cached;
			}
		}

		$response = self::github_get( 'https://api.github.com/repos/' . self::GITHUB_REPO . '/releases/latest' );

		if ( is_wp_error( $response ) ) {
			return self::fetch_latest_tag();
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		if ( 404 === $code ) {
			$release = self::fetch_latest_tag();
			set_transient( self::CACHE_KEY, $release, self::CACHE_TTL );
			return $release;
		}

		if ( 200 !== $code ) {
			return array(
				'version'      => ADCT_VERSION,
				'download_url' => '',
				'html_url'     => 'https://github.com/' . self::GITHUB_REPO . '/releases',
				'error'        => 'GitHub API returned HTTP ' . $code,
			);
		}

		$data    = json_decode( wp_remote_retrieve_body( $response ), true );
		$tag     = isset( $data['tag_name'] ) ? self::normalize_version( $data['tag_name'] ) : ADCT_VERSION;
		$zip_url = isset( $data['zipball_url'] ) ? $data['zipball_url'] : '';

		if ( empty( $zip_url ) && ! empty( $data['tag_name'] ) ) {
			$zip_url = 'https://github.com/' . self::GITHUB_REPO . '/archive/refs/tags/' . rawurlencode( $data['tag_name'] ) . '.zip';
		}

		$release = array(
			'version'      => $tag,
			'download_url' => $zip_url,
			'html_url'     => isset( $data['html_url'] ) ? $data['html_url'] : 'https://github.com/' . self::GITHUB_REPO . '/releases',
			'name'         => isset( $data['name'] ) ? (string) $data['name'] : '',
			'body'         => isset( $data['body'] ) ? (string) $data['body'] : '',
			'published_at' => isset( $data['published_at'] ) ? (string) $data['published_at'] : '',
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
			'release_url'  => $release['html_url'] ?? 'https://github.com/' . self::GITHUB_REPO . '/releases',
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

		$transient->response[ $plugin_file ] = (object) array(
			'slug'         => 'tracking-template',
			'plugin'       => $plugin_file,
			'new_version'  => $info['latest'],
			'url'          => $info['release_url'],
			'package'      => $info['download_url'],
			'icons'        => array(),
			'banners'      => array(),
			'banners_rtl'  => array(),
			'tested'       => get_bloginfo( 'version' ),
			'requires_php' => '7.4',
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

		$result               = new stdClass();
		$result->name         = 'Tracking Template';
		$result->slug         = 'tracking-template';
		$result->version      = $info['latest'];
		$result->author       = '<a href="https://github.com/benjamindimalanta">Benjamin Clar</a>';
		$result->homepage     = 'https://github.com/' . self::GITHUB_REPO;
		$result->requires     = '5.8';
		$result->requires_php = '7.4';
		$result->download_link = $info['download_url'];
		$result->sections     = array(
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

		if ( basename( $source ) !== $plugin_folder ) {
			$new_source = trailingslashit( dirname( $source ) ) . $plugin_folder;

			if ( $wp_filesystem->move( $source, $new_source ) ) {
				return $new_source;
			}
		}

		return $source;
	}
}
