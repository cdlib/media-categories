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
	 * Cached direct counts keyed by term ID.
	 *
	 * @var array<int,int>
	 */
	private $direct_counts = array();

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_notices', array( $this, 'render_sidebar' ) );
		add_action( 'wp_ajax_media_categories_create_term', array( $this, 'ajax_create_term' ) );
		add_action( 'wp_ajax_media_categories_rename_term', array( $this, 'ajax_rename_term' ) );
		add_action( 'wp_ajax_media_categories_delete_term', array( $this, 'ajax_delete_term' ) );
	}

	/**
	 * Render sidebar on media library screen.
	 *
	 * @return void
	 */
	public function render_sidebar() {
		if ( ! is_media_library_screen() || ! current_user_can( 'upload_files' ) ) {
			return;
		}

		$current = isset( $_GET['media_category_filter'] ) ? sanitize_text_field( wp_unslash( $_GET['media_category_filter'] ) ) : '';
		$tree    = $this->get_term_tree();
		$can_manage = current_user_can( MANAGE_CAP );
		?>
		<div class="media-categories-layout">
			<aside class="media-categories-sidebar" aria-label="<?php esc_attr_e( 'Media category folders', 'media-categories' ); ?>">
				<button type="button" class="media-categories-sidebar__toggle" aria-expanded="true" aria-label="<?php esc_attr_e( 'Collapse media categories panel', 'media-categories' ); ?>">
					<span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span>
				</button>
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
		$url = add_query_arg(
			array(
				'post_type'             => 'attachment',
				'mode'                  => isset( $_GET['mode'] ) ? sanitize_key( wp_unslash( $_GET['mode'] ) ) : null,
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
				'count'    => $this->get_direct_term_count( (int) $term->term_id ),
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

		foreach ( $tree as &$root_node ) {
			$this->sum_descendant_counts( $root_node );
		}
		unset( $root_node );

		return $tree;
	}

	/**
	 * Count attachments directly assigned to a term.
	 *
	 * @param int $term_id Term ID.
	 * @return int
	 */
	private function get_direct_term_count( $term_id ) {
		if ( isset( $this->direct_counts[ $term_id ] ) ) {
			return $this->direct_counts[ $term_id ];
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
						'include_children' => false,
					),
				),
			)
		);

		$this->direct_counts[ $term_id ] = (int) $query->found_posts;

		return $this->direct_counts[ $term_id ];
	}

	/**
	 * Sum descendant counts into parent nodes.
	 *
	 * @param array $node Tree node.
	 * @return int
	 */
	private function sum_descendant_counts( &$node ) {
		$total = (int) $node['count'];

		foreach ( $node['children'] as &$child ) {
			$total += $this->sum_descendant_counts( $child );
		}
		unset( $child );

		$node['count'] = $total;

		return $total;
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
