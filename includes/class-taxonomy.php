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
		add_filter( 'manage_edit-' . TAXONOMY . '_columns', array( $this, 'filter_term_columns' ) );
		add_filter( 'manage_' . TAXONOMY . '_custom_column', array( $this, 'render_term_column' ), 10, 3 );
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
				'update_count_callback' => '_update_generic_term_count',
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

	/**
	 * Replace the native stored-count column with a live attachment count.
	 *
	 * @param array<string,string> $columns Term list table columns.
	 * @return array<string,string>
	 */
	public function filter_term_columns( $columns ) {
		if ( isset( $columns['posts'] ) ) {
			$columns['media_category_attachments'] = $columns['posts'];
			unset( $columns['posts'] );
		}

		return $columns;
	}

	/**
	 * Render custom taxonomy columns.
	 *
	 * @param string $content Existing column content.
	 * @param string $column_name Column name.
	 * @param int    $term_id Term ID.
	 * @return string
	 */
	public function render_term_column( $content, $column_name, $term_id ) {
		if ( 'media_category_attachments' !== $column_name ) {
			return $content;
		}

		$count = $this->get_visible_attachment_count( (int) $term_id );
		$url   = add_query_arg(
			array(
				'post_type'             => 'attachment',
				'media_category_filter' => (int) $term_id,
			),
			admin_url( 'upload.php' )
		);

		return sprintf(
			'<a href="%1$s">%2$s</a>',
			esc_url( $url ),
			esc_html( number_format_i18n( $count ) )
		);
	}

	/**
	 * Count attachments matching a term filter, including descendants.
	 *
	 * @param int $term_id Term ID.
	 * @return int
	 */
	private function get_visible_attachment_count( $term_id ) {
		$term_ids = array( $term_id );
		$children = get_term_children( $term_id, TAXONOMY );

		if ( ! is_wp_error( $children ) ) {
			$term_ids = array_merge( $term_ids, array_map( 'intval', $children ) );
		}

		$query = new \WP_Query(
			array(
				'post_type'              => 'attachment',
				'post_status'            => 'inherit',
				'posts_per_page'         => 1,
				'fields'                 => 'ids',
				'no_found_rows'          => false,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'tax_query'              => array(
					array(
						'taxonomy'         => TAXONOMY,
						'field'            => 'term_id',
						'terms'            => array_values( array_unique( $term_ids ) ),
						'include_children' => false,
					),
				),
			)
		);

		return (int) $query->found_posts;
	}
}
