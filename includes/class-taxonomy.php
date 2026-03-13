<?php
/**
 * Media taxonomy registration.
 *
 * @package MediaCategories
 */

namespace Media_Categories;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the media_category taxonomy.
 */
class Taxonomy {
	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'init', array( $this, 'register_taxonomy' ) );
		add_filter( 'parent_file', array( $this, 'highlight_media_menu' ) );
	}

	/**
	 * Register taxonomy.
	 *
	 * @return void
	 */
	public function register_taxonomy() {
		$labels = array(
			'name'              => __( 'Media Categories', 'media-categories' ),
			'singular_name'     => __( 'Media Category', 'media-categories' ),
			'search_items'      => __( 'Search Media Categories', 'media-categories' ),
			'all_items'         => __( 'All Media Categories', 'media-categories' ),
			'parent_item'       => __( 'Parent Media Category', 'media-categories' ),
			'parent_item_colon' => __( 'Parent Media Category:', 'media-categories' ),
			'edit_item'         => __( 'Edit Media Category', 'media-categories' ),
			'view_item'         => __( 'View Media Category', 'media-categories' ),
			'update_item'       => __( 'Update Media Category', 'media-categories' ),
			'add_new_item'      => __( 'Add New Media Category', 'media-categories' ),
			'new_item_name'     => __( 'New Media Category Name', 'media-categories' ),
			'menu_name'         => __( 'Media Categories', 'media-categories' ),
		);

		register_taxonomy(
			TAXONOMY,
			array( 'attachment' ),
			array(
				'labels'            => $labels,
				'public'            => false,
				'publicly_queryable'=> false,
				'show_ui'           => true,
				'show_in_menu'      => false,
				'show_admin_column' => true,
				'show_in_quick_edit'=> false,
				'show_in_nav_menus' => false,
				'show_tagcloud'     => false,
				'show_in_rest'      => true,
				'hierarchical'      => true,
				'rewrite'           => false,
				'query_var'         => TAXONOMY,
				'meta_box_cb'       => false,
				'capabilities'      => array(
					'manage_terms' => MANAGE_CAP,
					'edit_terms'   => EDIT_CAP,
					'delete_terms' => DELETE_CAP,
					'assign_terms' => 'upload_files',
				),
			)
		);
	}

	/**
	 * Keep taxonomy screens highlighted under Media.
	 *
	 * @param string $parent_file Parent menu file.
	 * @return string
	 */
	public function highlight_media_menu( $parent_file ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( $screen instanceof \WP_Screen && 'edit-' . TAXONOMY === $screen->id ) {
			return 'upload.php';
		}

		return $parent_file;
	}
}
