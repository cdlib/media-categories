<?php
/**
 * Virtual folder sidebar.
 *
 * @package MediaCategories
 */

namespace Media_Categories;

defined( 'ABSPATH' ) || exit;

/**
 * Renders and prepares virtual folder data.
 */
class Folder_Sidebar {
	/**
	 * Cached visible counts keyed by term ID.
	 *
	 * @var array<int,int>
	 */
	private $term_counts = array();

	/**
	 * Whether the sidebar has already been rendered for this request.
	 *
	 * @var bool
	 */
	private $rendered = false;

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'all_admin_notices', array( $this, 'render_list_sidebar' ) );
		add_action( 'admin_notices', array( $this, 'render_list_sidebar' ) );
		add_action( 'restrict_manage_posts', array( $this, 'render_list_sidebar_from_filters' ), 0 );
		add_action( 'admin_footer-upload.php', array( $this, 'render_sidebar' ) );
		add_filter( 'admin_body_class', array( $this, 'add_collapsed_body_class' ) );
		add_action( 'wp_ajax_media_categories_create_term', array( $this, 'ajax_create_term' ) );
		add_action( 'wp_ajax_media_categories_rename_term', array( $this, 'ajax_rename_term' ) );
		add_action( 'wp_ajax_media_categories_delete_term', array( $this, 'ajax_delete_term' ) );
	}

	/**
	 * Start the media library with the folder sidebar hidden before JavaScript runs.
	 *
	 * @param string $classes Admin body classes.
	 * @return string
	 */
	public function add_collapsed_body_class( $classes ) {
		$mode = $this->get_media_library_mode();

		if ( $this->is_upload_screen() && $mode ) {
			$classes .= ' mode-' . $mode;
		}

		$is_collapsed = true;

		if ( 'list' === $mode ) {
			$is_collapsed = ! isset( $_COOKIE['mediaCategoriesSidebarCollapsed'] ) || '0' !== sanitize_text_field( wp_unslash( $_COOKIE['mediaCategoriesSidebarCollapsed'] ) );
		}

		if ( $this->is_upload_screen() && $is_collapsed ) {
			$classes .= ' media-categories-sidebar-collapsed';
		}

		return $classes;
	}

	/**
	 * Render the sidebar early in list view to avoid post-load layout shifts.
	 *
	 * @return void
	 */
	public function render_list_sidebar() {
		if ( 'list' !== $this->get_media_library_mode() ) {
			return;
		}

		$this->render_sidebar();
	}

	/**
	 * Render the list sidebar from the list-table filter hook when notice hooks are unavailable.
	 *
	 * @param string $post_type Post type.
	 * @return void
	 */
	public function render_list_sidebar_from_filters( $post_type ) {
		if ( 'attachment' !== $post_type || 'list' !== $this->get_media_library_mode() ) {
			return;
		}

		$this->render_sidebar();
	}

	/**
	 * Render sidebar on media library screen.
	 *
	 * @return void
	 */
	public function render_sidebar() {
		if ( $this->rendered ) {
			return;
		}

		if ( ! $this->is_upload_screen() || ! current_user_can( 'upload_files' ) ) {
			return;
		}

		if ( 'list' === $this->get_media_library_mode() && doing_action( 'admin_footer-upload.php' ) ) {
			return;
		}

		$current = isset( $_GET['media_category_filter'] ) ? sanitize_text_field( wp_unslash( $_GET['media_category_filter'] ) ) : '';
		$tree    = $this->get_term_tree();
		$can_manage = current_user_can( MANAGE_CAP );
		$this->rendered = true;
		?>
		<div class="media-categories-layout" data-media-categories-sidebar-root="true">
			<aside class="media-categories-sidebar" aria-label="<?php esc_attr_e( 'Media category folders', 'media-categories' ); ?>">
				<div class="media-categories-sidebar__inner">
					<div class="media-categories-toolbar">
						<button type="button" class="button button-primary media-categories-toolbar__new" <?php disabled( $can_manage, false ); ?>>
							<?php esc_html_e( 'New Folder', 'media-categories' ); ?>
						</button>
						<div class="media-categories-toolbar__actions">
							<button type="button" class="button media-categories-toolbar__action media-categories-toolbar__rename" <?php disabled( $can_manage, false ); ?>>
								<?php esc_html_e( 'Rename', 'media-categories' ); ?>
							</button>
							<button type="button" class="button media-categories-toolbar__action media-categories-toolbar__delete" <?php disabled( $can_manage, false ); ?>>
								<?php esc_html_e( 'Delete', 'media-categories' ); ?>
							</button>
							<button type="button" class="button media-categories-toolbar__action media-categories-toolbar__sort" aria-pressed="false">
								<?php esc_html_e( 'Sort', 'media-categories' ); ?>
							</button>
						</div>
					</div>
					<div class="media-categories-search">
						<label class="screen-reader-text" for="media-categories-folder-search"><?php esc_html_e( 'Search folders', 'media-categories' ); ?></label>
						<span class="dashicons dashicons-search" aria-hidden="true"></span>
						<input type="search" id="media-categories-folder-search" class="media-categories-search__input" placeholder="<?php esc_attr_e( 'Enter folder name...', 'media-categories' ); ?>" />
					</div>
					<ul class="media-categories-tree">
						<?php $this->render_folder_item( '', __( 'All Files', 'media-categories' ), $this->get_all_files_count(), $current, array(), true ); ?>
						<?php $this->render_folder_item( 'uncategorized', __( 'Uncategorized', 'media-categories' ), $this->get_uncategorized_count(), $current, array() ); ?>
						<li class="media-categories-tree__divider" aria-hidden="true"></li>
						<?php foreach ( $tree as $node ) : ?>
							<?php $this->render_term_node( $node, $current ); ?>
						<?php endforeach; ?>
					</ul>
				</div>
			</aside>
		</div>
		<?php
	}

	/**
	 * Get the current media library view mode.
	 *
	 * @return string
	 */
	private function get_media_library_mode() {
		if ( ! $this->is_upload_screen() ) {
			return '';
		}

		$mode = isset( $_GET['mode'] ) ? sanitize_key( wp_unslash( $_GET['mode'] ) ) : get_user_setting( 'posts_list_mode', 'list' );

		return in_array( $mode, array( 'grid', 'list' ), true ) ? $mode : 'list';
	}

	/**
	 * Whether the current request is for the media library screen.
	 *
	 * @return bool
	 */
	private function is_upload_screen() {
		global $pagenow;

		return is_media_library_screen() || 'upload.php' === $pagenow;
	}

	/**
	 * Render a term node recursively.
	 *
	 * @param array  $node Tree node.
	 * @param string $current Current selection.
	 * @return void
	 */
	private function render_term_node( $node, $current ) {
		$this->render_folder_item(
			(string) $node['term_id'],
			$node['name'],
			$node['count'],
			$current,
			$node['children'],
			false,
			$node['term_id']
		);
	}

	/**
	 * Render a folder list item.
	 *
	 * @param string $value Selected value.
	 * @param string $label Display label.
	 * @param int    $count Direct count.
	 * @param string $current Current filter.
	 * @param array  $children Child nodes.
	 * @return void
	 */
	private function render_folder_item( $value, $label, $count, $current, $children, $is_all_files = false, $term_id = 0 ) {
		$mode = isset( $_GET['mode'] ) ? sanitize_key( wp_unslash( $_GET['mode'] ) ) : get_user_setting( 'posts_list_mode', 'list' );
		$mode = in_array( $mode, array( 'grid', 'list' ), true ) ? $mode : 'list';

		$url = add_query_arg(
			array(
				'post_type'             => 'attachment',
				'mode'                  => $mode,
				'media_category_filter' => $value,
			),
			admin_url( 'upload.php' )
		);

		$is_current = (string) $current === (string) $value;
		$has_child  = ! empty( $children );
		$is_virtual = $is_all_files || 'uncategorized' === $value;

		echo '<li class="media-categories-tree__item">';
		printf(
			'<a href="%1$s" class="media-categories-folder%2$s" data-media-category-filter="%3$s" data-term-id="%4$d" data-virtual-folder="%5$s"><span class="media-categories-folder__icon%6$s" aria-hidden="true"></span><span class="media-categories-folder__label">%7$s</span><span class="media-categories-folder__count">%8$d</span></a>',
			esc_url( $url ),
			$is_current ? ' is-current' : '',
			esc_attr( $value ),
			(int) $term_id,
			$is_virtual ? 'yes' : 'no',
			$is_all_files ? ' media-categories-folder__icon--home' : '',
			esc_html( $label ),
			(int) $count
		);

		if ( $has_child ) {
			echo '<ul class="media-categories-tree media-categories-tree--children">';

			foreach ( $children as $child ) {
				$this->render_term_node( $child, $current );
			}

			echo '</ul>';
		}

		echo '</li>';
	}

	/**
	 * Build term tree.
	 *
	 * @return array[]
	 */
	public function get_term_tree() {
		$terms = get_terms(
			array(
				'taxonomy'   => TAXONOMY,
				'hide_empty' => false,
				'orderby'    => 'name',
			)
		);

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return array();
		}

		$indexed = array();

		foreach ( $terms as $term ) {
			$indexed[ $term->term_id ] = array(
				'term_id'  => (int) $term->term_id,
				'name'     => $term->name,
				'count'    => $this->get_visible_term_count( (int) $term->term_id ),
				'parent'   => (int) $term->parent,
				'children' => array(),
			);
		}

		$tree = array();

		foreach ( $indexed as $term_id => $node ) {
			if ( 0 !== $node['parent'] && isset( $indexed[ $node['parent'] ] ) ) {
				$indexed[ $node['parent'] ]['children'][] = &$indexed[ $term_id ];
			} else {
				$tree[] = &$indexed[ $term_id ];
			}
		}

		return $tree;
	}

	/**
	 * Count attachments visible for a term filter, including descendants.
	 *
	 * @param int $term_id Term ID.
	 * @return int
	 */
	private function get_visible_term_count( $term_id ) {
		if ( isset( $this->term_counts[ $term_id ] ) ) {
			return $this->term_counts[ $term_id ];
		}

		$query = new \WP_Query(
			array(
				'post_type'              => 'attachment',
				'post_status'            => 'inherit',
				'fields'                 => 'ids',
				'posts_per_page'         => 1,
				'no_found_rows'          => false,
				'update_post_term_cache' => false,
				'update_post_meta_cache' => false,
				'tax_query'              => array(
					array(
						'taxonomy'         => TAXONOMY,
						'field'            => 'term_id',
						'terms'            => array( $term_id ),
						'include_children' => true,
					),
				),
			)
		);

		$this->term_counts[ $term_id ] = (int) $query->found_posts;

		return $this->term_counts[ $term_id ];
	}

	/**
	 * Count uncategorized attachments.
	 *
	 * @return int
	 */
	private function get_uncategorized_count() {
		$query = new \WP_Query(
			array(
				'post_type'              => 'attachment',
				'post_status'            => 'inherit',
				'fields'                 => 'ids',
				'posts_per_page'         => 1,
				'no_found_rows'          => false,
				'update_post_term_cache' => false,
				'update_post_meta_cache' => false,
				'tax_query'              => array(
					array(
						'taxonomy' => TAXONOMY,
						'operator' => 'NOT EXISTS',
					),
				),
			)
		);

		return (int) $query->found_posts;
	}

	/**
	 * Count all attachments.
	 *
	 * @return int
	 */
	private function get_all_files_count() {
		$query = new \WP_Query(
			array(
				'post_type'              => 'attachment',
				'post_status'            => 'inherit',
				'fields'                 => 'ids',
				'posts_per_page'         => 1,
				'no_found_rows'          => false,
				'update_post_term_cache' => false,
				'update_post_meta_cache' => false,
			)
		);

		return (int) $query->found_posts;
	}

	/**
	 * Create a new media category.
	 *
	 * @return void
	 */
	public function ajax_create_term() {
		check_ajax_referer( 'media_categories_manage_terms', 'nonce' );

		if ( ! current_user_can( MANAGE_CAP ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to create media categories.', 'media-categories' ) ), 403 );
		}

		$name      = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$parent_id = isset( $_POST['parent_id'] ) ? absint( $_POST['parent_id'] ) : 0;

		if ( '' === $name ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a folder name.', 'media-categories' ) ), 400 );
		}

		$result = wp_insert_term(
			$name,
			TAXONOMY,
			array(
				'parent' => $parent_id,
			)
		);

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}

		wp_send_json_success( array( 'message' => __( 'Folder created.', 'media-categories' ) ) );
	}

	/**
	 * Rename an existing media category.
	 *
	 * @return void
	 */
	public function ajax_rename_term() {
		check_ajax_referer( 'media_categories_manage_terms', 'nonce' );

		if ( ! current_user_can( MANAGE_CAP ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to rename media categories.', 'media-categories' ) ), 403 );
		}

		$term_id = isset( $_POST['term_id'] ) ? absint( $_POST['term_id'] ) : 0;
		$name    = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';

		if ( ! $term_id || '' === $name ) {
			wp_send_json_error( array( 'message' => __( 'Please select a folder and provide a new name.', 'media-categories' ) ), 400 );
		}

		$result = wp_update_term(
			$term_id,
			TAXONOMY,
			array(
				'name' => $name,
			)
		);

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}

		wp_send_json_success( array( 'message' => __( 'Folder renamed.', 'media-categories' ) ) );
	}

	/**
	 * Delete an existing media category.
	 *
	 * @return void
	 */
	public function ajax_delete_term() {
		check_ajax_referer( 'media_categories_manage_terms', 'nonce' );

		if ( ! current_user_can( DELETE_CAP ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to delete media categories.', 'media-categories' ) ), 403 );
		}

		$term_id = isset( $_POST['term_id'] ) ? absint( $_POST['term_id'] ) : 0;

		if ( ! $term_id ) {
			wp_send_json_error( array( 'message' => __( 'Please select a folder to delete.', 'media-categories' ) ), 400 );
		}

		$result = wp_delete_term( $term_id, TAXONOMY );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}

		wp_send_json_success( array( 'message' => __( 'Folder deleted.', 'media-categories' ) ) );
	}
}
