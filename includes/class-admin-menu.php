<?php
/**
 * Admin menu links.
 *
 * @package MediaCategories
 */

namespace Media_Categories;

defined( 'ABSPATH' ) || exit;

/**
 * Adds menu items.
 */
class Admin_Menu {
	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'add_menus' ) );
		add_filter( 'submenu_file', array( $this, 'highlight_taxonomy_submenu' ) );
	}

	/**
	 * Add menu items.
	 *
	 * @return void
	 */
	public function add_menus() {
		add_submenu_page(
			'upload.php',
			__( 'Media Categories', 'media-categories' ),
			__( 'Media Categories', 'media-categories' ),
			MANAGE_CAP,
			'edit-tags.php?taxonomy=' . TAXONOMY . '&post_type=attachment'
		);
	}

	/**
	 * Highlight submenu entry for taxonomy page.
	 *
	 * @param string $submenu_file Current submenu file.
	 * @return string
	 */
	public function highlight_taxonomy_submenu( $submenu_file ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( $screen instanceof \WP_Screen && 'edit-' . TAXONOMY === $screen->id ) {
			return 'edit-tags.php?taxonomy=' . TAXONOMY . '&post_type=attachment';
		}

		return $submenu_file;
	}
}
