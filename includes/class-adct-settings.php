<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ADCT_Settings {

	const CAPABILITY = 'view_tracking_template';

	const OPTION_ALLOWED_ROLES = 'tt_allowed_roles';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'sync_capabilities' ), 20 );
		add_action( 'admin_init', array( __CLASS__, 'prune_inactive_allowed_roles' ) );
	}

	public static function user_can_view() {
		return current_user_can( 'manage_options' ) || current_user_can( self::CAPABILITY );
	}

	public static function user_can_manage() {
		return current_user_can( 'manage_options' );
	}

	public static function get_allowed_roles() {
		$roles = get_option( self::OPTION_ALLOWED_ROLES, array() );

		if ( ! is_array( $roles ) ) {
			return array();
		}

		return array_values( array_unique( array_map( 'sanitize_key', $roles ) ) );
	}

	public static function get_available_roles() {
		if ( ! function_exists( 'count_users' ) ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
		}

		if ( ! function_exists( 'get_editable_roles' ) ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
		}

		$user_counts = count_users();
		$active      = isset( $user_counts['avail_roles'] ) ? $user_counts['avail_roles'] : array();
		$editable    = get_editable_roles();
		$available   = array();

		foreach ( $active as $slug => $user_count ) {
			$user_count = (int) $user_count;

			if ( 'administrator' === $slug || $user_count < 1 ) {
				continue;
			}

			if ( ! isset( $editable[ $slug ] ) ) {
				continue;
			}

			$label = translate_user_role( $editable[ $slug ]['name'] );
			$available[ $slug ] = sprintf(
				'%s (%s)',
				$label,
				sprintf(
					/* translators: %s: number of users with this role */
					_n( '%s user', '%s users', $user_count, 'tracking-template' ),
					number_format_i18n( $user_count )
				)
			);
		}

		return $available;
	}

	public static function save_allowed_roles( array $role_slugs ) {
		$available = array_keys( self::get_available_roles() );
		$allowed   = array();

		foreach ( $role_slugs as $slug ) {
			$slug = sanitize_key( $slug );

			if ( in_array( $slug, $available, true ) ) {
				$allowed[] = $slug;
			}
		}

		update_option( self::OPTION_ALLOWED_ROLES, $allowed );
		self::sync_capabilities();
	}

	public static function prune_inactive_allowed_roles() {
		$active  = array_keys( self::get_available_roles() );
		$allowed = self::get_allowed_roles();
		$pruned  = array_values( array_intersect( $allowed, $active ) );

		if ( $pruned !== $allowed ) {
			update_option( self::OPTION_ALLOWED_ROLES, $pruned );
		}
	}

	public static function sync_capabilities() {
		if ( ! function_exists( 'get_editable_roles' ) ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
		}

		$allowed   = self::get_allowed_roles();
		$all_slugs = array_keys( get_editable_roles() );

		foreach ( $all_slugs as $slug ) {
			$role = get_role( $slug );

			if ( ! $role ) {
				continue;
			}

			if ( 'administrator' === $slug ) {
				$role->add_cap( self::CAPABILITY );
				continue;
			}

			if ( in_array( $slug, $allowed, true ) ) {
				$role->add_cap( self::CAPABILITY );
			} else {
				$role->remove_cap( self::CAPABILITY );
			}
		}
	}

	public static function maybe_save_access_settings() {
		if ( ! self::user_can_manage() ) {
			return;
		}

		if ( empty( $_POST['adct_save_access'] ) ) {
			return;
		}

		if ( empty( $_GET['page'] ) || 'tracking-template' !== $_GET['page'] ) {
			return;
		}

		check_admin_referer( 'adct_save_access' );

		$submitted = isset( $_POST['adct_allowed_roles'] ) ? (array) wp_unslash( $_POST['adct_allowed_roles'] ) : array();
		self::save_allowed_roles( $submitted );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'         => 'tracking-template',
					'adct_access'  => 'saved',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
