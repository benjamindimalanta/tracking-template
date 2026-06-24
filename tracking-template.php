<?php
/**
 * Plugin Name: Tracking Template
 * Plugin URI: https://github.com/benjamindimalanta/tracking-template
 * Description: WordPress contact-click tracking with marketing attribution, session grouping, and admin reporting. A reusable template by Benjamin Clar.
 * Version: 1.6.4
 * Author: Benjamin Clar
 * Author URI: https://github.com/benjamindimalanta
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: tracking-template
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ADCT_VERSION', '1.6.4' );
define( 'ADCT_PLUGIN_FILE', __FILE__ );
define( 'ADCT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ADCT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once ADCT_PLUGIN_DIR . 'includes/class-adct-visitor.php';
require_once ADCT_PLUGIN_DIR . 'includes/class-adct-database.php';
require_once ADCT_PLUGIN_DIR . 'includes/class-adct-leads.php';
require_once ADCT_PLUGIN_DIR . 'includes/class-adct-settings.php';
require_once ADCT_PLUGIN_DIR . 'includes/class-adct-license.php';
require_once ADCT_PLUGIN_DIR . 'includes/class-adct-updater.php';
require_once ADCT_PLUGIN_DIR . 'includes/class-adct-analytics.php';
require_once ADCT_PLUGIN_DIR . 'includes/class-adct-ajax.php';
require_once ADCT_PLUGIN_DIR . 'includes/class-adct-admin.php';

final class Tracking_Template_Plugin {

	public static function init() {
		register_activation_hook( ADCT_PLUGIN_FILE, array( __CLASS__, 'on_activate' ) );

		add_action( 'plugins_loaded', array( 'ADCT_Database', 'maybe_install' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_entry_capture' ), 5 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_tracker' ), 10 );
		add_filter( 'rocket_exclude_defer_js', array( __CLASS__, 'exclude_from_wp_rocket_defer' ) );
		add_filter( 'rocket_delay_js_exclusions', array( __CLASS__, 'exclude_from_wp_rocket_delay' ) );
		add_filter( 'rocket_exclude_js', array( __CLASS__, 'exclude_from_wp_rocket_minify' ) );

		ADCT_Settings::init();
		ADCT_License::init();
		ADCT_Updater::init();
		ADCT_Ajax::init();
		ADCT_Admin::init();
	}

	public static function on_activate() {
		ADCT_Database::install();
		ADCT_Settings::sync_capabilities();
	}

	public static function exclude_from_wp_rocket_minify( $excluded ) {
		$excluded[] = '/tracking-template/assets/tracker.js';
		$excluded[] = '/tracking-template/assets/entry-capture.js';
		return $excluded;
	}

	public static function exclude_from_wp_rocket_defer( $excluded ) {
		$excluded[] = '/tracking-template/assets/tracker.js';
		$excluded[] = '/tracking-template/assets/entry-capture.js';
		return $excluded;
	}

	public static function exclude_from_wp_rocket_delay( $excluded ) {
		$excluded[] = 'adct-tracker';
		$excluded[] = 'adct-entry-capture';
		$excluded[] = 'adctConfig';
		$excluded[] = 'adctGetAttribution';
		return $excluded;
	}

	public static function enqueue_entry_capture() {
		if ( is_admin() || ! ADCT_License::is_active() ) {
			return;
		}

		wp_enqueue_script(
			'adct-entry-capture',
			ADCT_PLUGIN_URL . 'assets/entry-capture.js',
			array(),
			ADCT_VERSION . '.' . filemtime( ADCT_PLUGIN_DIR . 'assets/entry-capture.js' ),
			false
		);
	}

	public static function enqueue_tracker() {
		if ( is_admin() || ! ADCT_License::is_active() ) {
			return;
		}

		wp_enqueue_script(
			'adct-tracker',
			ADCT_PLUGIN_URL . 'assets/tracker.js',
			array( 'adct-entry-capture' ),
			ADCT_VERSION . '.' . filemtime( ADCT_PLUGIN_DIR . 'assets/tracker.js' ),
			true
		);

		$config = array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'adct_log_click' ),
			'mode'    => 'site',
		);

		if ( function_exists( 'is_product' ) && is_product() ) {
			$product_id        = get_the_ID();
			$snapshot          = ADCT_Database::get_product_snapshot( $product_id );
			$config['mode']    = 'product';
			$config['product'] = array(
				'id'        => $product_id,
				'title'     => get_the_title(),
				'url'       => get_permalink(),
				'price'     => $snapshot['product_price'],
				'mileage'   => $snapshot['product_mileage'],
				'image_url' => $snapshot['product_image_url'],
			);
		} else {
			$config['page'] = self::get_page_context();
		}

		wp_localize_script( 'adct-tracker', 'adctConfig', $config );
	}

	private static function get_page_context() {
		if ( is_singular() ) {
			return array(
				'title' => get_the_title(),
				'url'   => get_permalink(),
			);
		}

		if ( is_tax() || is_category() || is_tag() ) {
			$term = get_queried_object();

			if ( $term && ! is_wp_error( $term ) ) {
				$link = get_term_link( $term );

				return array(
					'title' => $term->name,
					'url'   => is_wp_error( $link ) ? self::get_current_url() : $link,
				);
			}
		}

		if ( is_post_type_archive() ) {
			$post_type = get_query_var( 'post_type' );
			$link      = get_post_type_archive_link( is_array( $post_type ) ? reset( $post_type ) : $post_type );

			return array(
				'title' => post_type_archive_title( '', false ),
				'url'   => $link ? $link : self::get_current_url(),
			);
		}

		return array(
			'title' => wp_strip_all_tags( html_entity_decode( wp_get_document_title(), ENT_QUOTES, 'UTF-8' ) ),
			'url'   => self::get_current_url(),
		);
	}

	private static function get_current_url() {
		if ( isset( $_SERVER['HTTP_HOST'], $_SERVER['REQUEST_URI'] ) ) {
			$scheme = is_ssl() ? 'https://' : 'http://';

			return esc_url_raw( $scheme . sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) . wp_unslash( $_SERVER['REQUEST_URI'] ) );
		}

		return home_url( '/' );
	}
}

Tracking_Template_Plugin::init();
