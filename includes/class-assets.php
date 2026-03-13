<?php
/**
 * Asset loading.
 *
 * @package MediaCategories
 */

namespace Media_Categories;

defined( 'ABSPATH' ) || exit;

/**
 * Registers admin assets.
 */
class Assets {
	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	/**
	 * Enqueue assets on relevant screens.
	 *
	 * @return void
	 */
	public function enqueue_admin_assets() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( ! $screen instanceof \WP_Screen ) {
			return;
		}

		$is_media_screen      = 'upload' === $screen->base;
		$is_attachment_screen = 'attachment' === $screen->id;

		if ( ! $is_media_screen && ! $is_attachment_screen ) {
			return;
		}

		wp_enqueue_style(
			'media-categories-admin',
			MEDIA_CATEGORIES_URL . 'assets/css/admin.css',
			array(),
			MEDIA_CATEGORIES_VERSION
		);

		if ( $is_media_screen ) {
			$terms = get_terms(
				array(
					'taxonomy'   => TAXONOMY,
					'hide_empty' => false,
					'orderby'    => 'name',
				)
			);

			wp_enqueue_script(
				'media-categories-grid',
				MEDIA_CATEGORIES_URL . 'assets/js/media-grid.js',
				array( 'jquery', 'media-views' ),
				MEDIA_CATEGORIES_VERSION,
				true
			);

			wp_localize_script(
				'media-categories-grid',
				'mediaCategoriesData',
				array(
					'terms'           => array_values(
						array_map(
							static function ( $term ) {
								return array(
									'id'   => (int) $term->term_id,
									'name' => $term->name,
								);
							},
							is_array( $terms ) ? $terms : array()
						)
					),
					'selected'        => isset( $_GET['media_category_filter'] ) ? sanitize_text_field( wp_unslash( $_GET['media_category_filter'] ) ) : '',
					'dropdownLabel'   => __( 'Filter by Media Categories', 'media-categories' ),
					'uncategorized'   => __( 'Uncategorized', 'media-categories' ),
					'allLabel'        => __( 'All media categories', 'media-categories' ),
				'libraryTitle'    => __( 'Media Categories', 'media-categories' ),
					'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
					'nonce'           => wp_create_nonce( 'media_categories_manage_terms' ),
					'canManage'       => current_user_can( MANAGE_CAP ),
					'strings'         => array(
						'createPrompt'      => __( 'Enter a name for the new folder.', 'media-categories' ),
						'renamePrompt'      => __( 'Enter a new folder name.', 'media-categories' ),
						'deleteConfirm'     => __( 'Delete this folder? Media items will remain in the library.', 'media-categories' ),
						'selectFolder'      => __( 'Select a folder first.', 'media-categories' ),
						'folderCreated'     => __( 'Folder created.', 'media-categories' ),
						'folderRenamed'     => __( 'Folder renamed.', 'media-categories' ),
						'folderDeleted'     => __( 'Folder deleted.', 'media-categories' ),
						'collapseLabel'     => __( 'Collapse media categories panel', 'media-categories' ),
						'expandLabel'       => __( 'Expand media categories panel', 'media-categories' ),
						'sortAscending'     => __( 'Folders sorted A to Z.', 'media-categories' ),
						'sortDescending'    => __( 'Folders sorted Z to A.', 'media-categories' ),
					),
				)
			);
		}
	}
}
