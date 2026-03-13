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
		?>
		<div class="media-categories-layout">
			<aside class="media-categories-sidebar" aria-label="<?php esc_attr_e( 'Media category folders', 'media-categories' ); ?>">
				<div class="media-categories-sidebar__inner">
					<h2><?php esc_html_e( 'Media Categories', 'media-categories' ); ?></h2>
					<ul class="media-categories-tree">
						<?php $this->render_folder_item( 'uncategorized', __( 'Uncategorized', 'media-categories' ), $this->get_uncategorized_count(), $current, array() ); ?>
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
			$node['children']
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
	private function render_folder_item( $value, $label, $count, $current, $children ) {
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

		echo '<li class="media-categories-tree__item">';
		printf(
			'<a href="%1$s" class="media-categories-folder%2$s" data-media-category-filter="%3$s"><span class="media-categories-folder__icon" aria-hidden="true"></span><span class="media-categories-folder__label">%4$s</span><span class="media-categories-folder__count">%5$d</span></a>',
			esc_url( $url ),
			$is_current ? ' is-current' : '',
			esc_attr( $value ),
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
}
