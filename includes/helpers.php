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

/**
 * Get hierarchical term options for the media category dropdown.
 *
 * @param string $taxonomy Taxonomy slug.
 * @return array<int,array<string,mixed>>
 */
function get_media_category_term_options( $taxonomy = TAXONOMY ) {
	$taxonomy = sanitize_key( (string) $taxonomy );

	if ( '' === $taxonomy || ! taxonomy_exists( $taxonomy ) ) {
		return array();
	}

	$terms = get_terms(
		array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
			'orderby'    => 'name',
			'order'      => 'ASC',
		)
	);

	if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
		return array();
	}

	usort(
		$terms,
		static function ( $left, $right ) {
			if ( ! ( $left instanceof \WP_Term ) || ! ( $right instanceof \WP_Term ) ) {
				return 0;
			}

			return strcasecmp( (string) $left->name, (string) $right->name );
		}
	);

	$terms_by_parent = array();

	foreach ( $terms as $term ) {
		if ( ! ( $term instanceof \WP_Term ) ) {
			continue;
		}

		$parent_id = max( 0, (int) $term->parent );

		if ( ! isset( $terms_by_parent[ $parent_id ] ) ) {
			$terms_by_parent[ $parent_id ] = array();
		}

		$terms_by_parent[ $parent_id ][] = $term;
	}

	$options = array();

	$append_term_option = static function ( $parent_id, $depth ) use ( &$append_term_option, &$options, $terms_by_parent ) {
		if ( empty( $terms_by_parent[ $parent_id ] ) ) {
			return;
		}

		foreach ( $terms_by_parent[ $parent_id ] as $term ) {
			if ( ! ( $term instanceof \WP_Term ) ) {
				continue;
			}

			$prefix    = $depth > 0 ? str_repeat( '- ', $depth ) : '';
			$options[] = array(
				'value' => (int) $term->term_id,
				'label' => $prefix . (string) $term->name,
			);

			$append_term_option( (int) $term->term_id, $depth + 1 );
		}
	};

	$append_term_option( 0, 0 );

	return $options;
}
