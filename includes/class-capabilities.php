<?php
/**
 * Role and capability management.
 *
 * @package MediaCategories
 */

namespace Media_Categories;

defined( 'ABSPATH' ) || exit;

/**
 * Sync custom capabilities to selected roles.
 */
class Capabilities {
	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'init', array( __CLASS__, 'sync_role_capabilities' ), 5 );
		add_action( 'update_option_' . SETTINGS_OPTION, array( __CLASS__, 'sync_role_capabilities' ), 10, 0 );
		add_action( 'add_option_' . SETTINGS_OPTION, array( __CLASS__, 'sync_role_capabilities' ), 10, 0 );
	}

	/**
	 * Synchronize role capabilities to the saved selection.
	 *
	 * @return void
	 */
	public static function sync_role_capabilities() {
		if ( ! function_exists( 'wp_roles' ) ) {
			return;
		}

		$selected_roles = get_manage_roles();
		$role_objects   = wp_roles()->roles;
		$caps           = get_management_caps();

		foreach ( array_keys( $role_objects ) as $role_name ) {
			$role = get_role( $role_name );

			if ( ! $role instanceof \WP_Role ) {
				continue;
			}

			foreach ( $caps as $cap ) {
				if ( in_array( $role_name, $selected_roles, true ) ) {
					$role->add_cap( $cap );
				} else {
					$role->remove_cap( $cap );
				}
			}
		}
	}
}
