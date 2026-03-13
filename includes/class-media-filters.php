<?php
/**
 * Media library filtering integrations.
 *
 * @package MediaCategories
 */

namespace Media_Categories;

defined( 'ABSPATH' ) || exit;

/**
 * Adds list and grid filters.
 */
class Media_Filters {
	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'restrict_manage_posts', array( $this, 'render_list_filter' ) );
		add_filter( 'parse_query', array( $this, 'apply_list_filter' ) );
		add_filter( 'ajax_query_attachments_args', array( $this, 'apply_grid_filters' ) );
	}

	/**
	 * Render list-table dropdown.
	 *
	 * @param string $post_type Post type.
	 * @return void
	 */
	public function render_list_filter( $post_type ) {
		if ( 'attachment' !== $post_type || ! current_user_can( 'upload_files' ) ) {
			return;
		}

		$selected = isset( $_GET['media_category_filter'] ) ? sanitize_text_field( wp_unslash( $_GET['media_category_filter'] ) ) : '';

		$options = get_terms(
			array(
				'taxonomy'   => TAXONOMY,
				'hide_empty' => false,
				'orderby'    => 'name',
			)
		);

		echo '<label class="screen-reader-text" for="filter-by-media-category">' . esc_html__( 'Filter by Media Categories', 'media-categories' ) . '</label>';
		echo '<select id="filter-by-media-category" name="media_category_filter">';
		echo '<option value="">' . esc_html__( 'Filter by Media Categories', 'media-categories' ) . '</option>';
		echo '<option value="uncategorized" ' . selected( 'uncategorized', $selected, false ) . '>' . esc_html__( 'Uncategorized', 'media-categories' ) . '</option>';

		foreach ( $options as $term ) {
			printf(
				'<option value="%1$d" %2$s>%3$s</option>',
				(int) $term->term_id,
				selected( (string) $term->term_id, $selected, false ),
				esc_html( $term->name )
			);
		}

		echo '</select>';
	}

	/**
	 * Apply list-table taxonomy filter.
	 *
	 * @param \WP_Query $query Query object.
	 * @return \WP_Query
	 */
	public function apply_list_filter( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return $query;
		}

		if ( 'attachment' !== $query->get( 'post_type' ) ) {
			return $query;
		}

		$selected = isset( $_GET['media_category_filter'] ) ? sanitize_text_field( wp_unslash( $_GET['media_category_filter'] ) ) : '';

		if ( '' === $selected ) {
			return $query;
		}

		return $this->apply_term_query_vars( $query, $selected );
	}

	/**
	 * Apply taxonomy filtering to AJAX media queries.
	 *
	 * @param array $query Existing query args.
	 * @return array
	 */
	public function apply_grid_filters( $query ) {
		$selected = '';

		if ( isset( $_REQUEST['query']['media_category_filter'] ) ) {
			$selected = sanitize_text_field( wp_unslash( $_REQUEST['query']['media_category_filter'] ) );
		}

		if ( '' === $selected ) {
			return $query;
		}

		if ( 'uncategorized' === $selected ) {
			$query['tax_query'] = array(
				array(
					'taxonomy' => TAXONOMY,
					'operator' => 'NOT EXISTS',
				),
			);

			return $query;
		}

		$query['tax_query'] = array(
			array(
				'taxonomy'         => TAXONOMY,
				'field'            => 'term_id',
				'terms'            => array( (int) $selected ),
				'include_children' => false,
			),
		);

		return $query;
	}

	/**
	 * Apply selected value to a WP_Query.
	 *
	 * @param \WP_Query $query Query object.
	 * @param string    $selected Selected value.
	 * @return \WP_Query
	 */
	private function apply_term_query_vars( $query, $selected ) {
		if ( 'uncategorized' === $selected ) {
			$query->set(
				'tax_query',
				array(
					array(
						'taxonomy' => TAXONOMY,
						'operator' => 'NOT EXISTS',
					),
				)
			);

			return $query;
		}

		$query->set(
			'tax_query',
			array(
				array(
					'taxonomy'         => TAXONOMY,
					'field'            => 'term_id',
					'terms'            => array( (int) $selected ),
					'include_children' => false,
				),
			)
		);

		return $query;
	}
}
