<?php
/**
 * Shared helper functions.
 *
 * @package MediaCategories
 */

namespace Media_Categories;

defined( 'ABSPATH' ) || exit;

const TAXONOMY = 'media_category';
const SETTINGS_OPTION = 'media_categories_manage_roles';
const MANAGE_CAP = 'manage_media_categories';
const EDIT_CAP = 'edit_media_categories';
const DELETE_CAP = 'delete_media_categories';

/**
 * Get the saved roles that may manage categories.
 *
 * @return string[]
 */
function get_manage_roles() {
	$roles = get_option( SETTINGS_OPTION, array( 'administrator' ) );

	if ( ! is_array( $roles ) ) {
		return array( 'administrator' );
	}

	return array_values(
		array_filter(
			array_map( 'sanitize_key', $roles )
		)
	);
}

/**
 * Return the custom management capabilities.
 *
 * @return string[]
 */
function get_management_caps() {
	return array(
		MANAGE_CAP,
		EDIT_CAP,
		DELETE_CAP,
	);
}

/**
 * Whether the current screen is a media library screen.
 *
 * @return bool
 */
function is_media_library_screen() {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

	return $screen instanceof \WP_Screen && 'upload' === $screen->base;
}
