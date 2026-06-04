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
		$is_taxonomy_screen   = 'edit-' . TAXONOMY === $screen->id;

		if ( ! $is_media_screen && ! $is_attachment_screen && ! $is_taxonomy_screen ) {
			return;
		}

		$admin_css_version = $this->get_asset_version( MEDIA_CATEGORIES_DIR . 'assets/css/admin.css' );

		wp_enqueue_style(
			'media-categories-admin',
			MEDIA_CATEGORIES_URL . 'assets/css/admin.css',
			array(),
			$admin_css_version
		);

		if ( $is_media_screen || $is_attachment_screen ) {
			$attachment_categories_js_version = $this->get_asset_version( MEDIA_CATEGORIES_DIR . 'assets/js/attachment-categories.js' );

			wp_enqueue_script(
				'media-categories-attachment-categories',
				MEDIA_CATEGORIES_URL . 'assets/js/attachment-categories.js',
				array( 'jquery' ),
				$attachment_categories_js_version,
				true
			);
		}

		if ( $is_media_screen ) {
			$terms = get_terms(
				array(
					'taxonomy'   => TAXONOMY,
					'hide_empty' => false,
					'orderby'    => 'name',
				)
			);
			$term_options = get_media_category_term_options( TAXONOMY );
			$author_options = $this->get_attachment_author_options();

			$grid_js_version = $this->get_asset_version( MEDIA_CATEGORIES_DIR . 'assets/js/media-grid.js' );

			wp_enqueue_script(
				'media-categories-grid',
				MEDIA_CATEGORIES_URL . 'assets/js/media-grid.js',
				array( 'jquery', 'media-views' ),
				$grid_js_version,
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
									'id'     => (int) $term->term_id,
									'name'   => $term->name,
									'parent' => (int) $term->parent,
								);
							},
							is_array( $terms ) ? $terms : array()
						)
					),
					'selected'        => isset( $_GET['media_category_filter'] ) ? sanitize_text_field( wp_unslash( $_GET['media_category_filter'] ) ) : '',
					'dropdownLabel'   => __( 'Filter by Media Categories', 'media-categories' ),
					'uncategorized'   => __( 'Uncategorized', 'media-categories' ),
					'allLabel'        => __( 'All categories', 'media-categories' ),
					'termOptions'     => array_values( $term_options ),
					'authorSelected'  => isset( $_GET['author'] ) ? absint( wp_unslash( $_GET['author'] ) ) : 0,
					'authorLabel'     => __( 'Filter by author', 'media-categories' ),
					'allAuthorsLabel' => __( 'All authors', 'media-categories' ),
					'authorOptions'   => array_values( $author_options ),
					'libraryTitle'    => __( 'Media Categories', 'media-categories' ),
					'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
					'nonce'           => wp_create_nonce( 'media_categories_manage_terms' ),
					'canManage'       => current_user_can( MANAGE_CAP ),
					'strings'         => array(
						'createPrompt'      => __( 'Create New Folder', 'media-categories' ),
						'nameLabel'         => __( 'Folder name', 'media-categories' ),
						'parentLabel'       => __( 'Parent folder', 'media-categories' ),
						'noneOption'        => __( 'None', 'media-categories' ),
						'createButton'      => __( 'Create Folder', 'media-categories' ),
						'cancelButton'      => __( 'Cancel', 'media-categories' ),
						'nameRequired'      => __( 'Please enter a folder name.', 'media-categories' ),
						'renamePrompt'      => __( 'Enter a new folder name.', 'media-categories' ),
						'deleteConfirm'     => __( 'Delete this folder? Media items will remain in the library.', 'media-categories' ),
						'selectFolder'      => __( 'Select a folder first.', 'media-categories' ),
						'folderCreated'     => __( 'Folder created.', 'media-categories' ),
						'folderRenamed'     => __( 'Folder renamed.', 'media-categories' ),
						'folderDeleted'     => __( 'Folder deleted.', 'media-categories' ),
						'collapseLabel'     => __( 'Collapse media categories panel', 'media-categories' ),
						'expandLabel'       => __( 'Expand media categories panel', 'media-categories' ),
						'browseButton'      => __( 'Open side panel', 'media-categories' ),
						'closePanelButton'  => __( 'Close side panel', 'media-categories' ),
						'sortAscending'     => __( 'Folders sorted A to Z.', 'media-categories' ),
						'sortDescending'    => __( 'Folders sorted Z to A.', 'media-categories' ),
					),
				)
			);
		}

		if ( $is_taxonomy_screen ) {
			$taxonomy_js_version = $this->get_asset_version( MEDIA_CATEGORIES_DIR . 'assets/js/taxonomy-admin.js' );

			wp_enqueue_script(
				'media-categories-taxonomy',
				MEDIA_CATEGORIES_URL . 'assets/js/taxonomy-admin.js',
				array( 'jquery' ),
				$taxonomy_js_version,
				true
			);
		}
	}

	/**
	 * Get an asset version that changes when the file changes.
	 *
	 * @param string $path Absolute asset path.
	 * @return string
	 */
	private function get_asset_version( $path ) {
		$mtime = file_exists( $path ) ? (string) filemtime( $path ) : '';

		if ( '' === $mtime ) {
			return MEDIA_CATEGORIES_VERSION;
		}

		return MEDIA_CATEGORIES_VERSION . '.' . $mtime;
	}

	/**
	 * Get users who are authors of attachments for the grid author filter.
	 *
	 * @return array<int,array{value:int,label:string}>
	 */
	private function get_attachment_author_options() {
		global $wpdb;

		$author_ids = $wpdb->get_col(
			"SELECT DISTINCT post_author FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_author > 0 ORDER BY post_author ASC"
		);

		if ( empty( $author_ids ) ) {
			return array();
		}

		$users = get_users(
			array(
				'include' => array_map( 'intval', $author_ids ),
				'orderby' => 'display_name',
				'fields'  => array( 'ID', 'display_name', 'user_login' ),
			)
		);

		return array_map(
			static function ( $user ) {
				return array(
					'value' => (int) $user->ID,
					'label' => $user->display_name ? $user->display_name : $user->user_login,
				);
			},
			$users
		);
	}
}
