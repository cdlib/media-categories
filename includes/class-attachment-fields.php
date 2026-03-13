<?php
/**
 * Attachment term assignment UI.
 *
 * @package MediaCategories
 */

namespace Media_Categories;

defined( 'ABSPATH' ) || exit;

/**
 * Adds checklist-based term assignment for attachments.
 */
class Attachment_Fields {
	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'add_meta_boxes_attachment', array( $this, 'add_meta_box' ) );
		add_action( 'edit_attachment', array( $this, 'save_attachment_terms' ) );
		add_filter( 'attachment_fields_to_edit', array( $this, 'add_modal_fields' ), 10, 2 );
		add_filter( 'attachment_fields_to_save', array( $this, 'save_modal_fields' ), 10, 2 );
	}

	/**
	 * Add meta box to attachment edit screen.
	 *
	 * @return void
	 */
	public function add_meta_box() {
		add_meta_box(
			'media-categories-metabox',
			__( 'Media Categories', 'media-categories' ),
			array( $this, 'render_meta_box' ),
			'attachment',
			'side',
			'default'
		);
	}

	/**
	 * Render the attachment edit checklist.
	 *
	 * @param \WP_Post $post Attachment post.
	 * @return void
	 */
	public function render_meta_box( $post ) {
		$selected_ids = wp_get_object_terms( $post->ID, TAXONOMY, array( 'fields' => 'ids' ) );
		$selected_ids = $this->expand_with_ancestor_terms( $selected_ids );

		wp_nonce_field( 'media_categories_attachment_terms', 'media_categories_attachment_terms_nonce' );

		echo '<div class="media-categories-checklist">';
		wp_terms_checklist(
			$post->ID,
			array(
				'taxonomy'      => TAXONOMY,
				'selected_cats' => $selected_ids,
				'checked_ontop' => false,
			)
		);
		echo '</div>';
	}

	/**
	 * Save attachment terms from edit screen.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return void
	 */
	public function save_attachment_terms( $attachment_id ) {
		if ( ! isset( $_POST['media_categories_attachment_terms_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['media_categories_attachment_terms_nonce'] ) ), 'media_categories_attachment_terms' ) ) {
			return;
		}

		if ( ! current_user_can( 'upload_files' ) ) {
			return;
		}

		$terms = isset( $_POST['tax_input'][ TAXONOMY ] ) ? (array) wp_unslash( $_POST['tax_input'][ TAXONOMY ] ) : array();
		$terms = array_map( 'intval', $terms );
		$terms = $this->expand_with_ancestor_terms( $terms );

		wp_set_object_terms( $attachment_id, $terms, TAXONOMY, false );
	}

	/**
	 * Add category checklist to media modal details pane.
	 *
	 * @param array   $form_fields Existing fields.
	 * @param \WP_Post $post Attachment post.
	 * @return array
	 */
	public function add_modal_fields( $form_fields, $post ) {
		if ( ! current_user_can( 'upload_files' ) ) {
			return $form_fields;
		}

		$selected_ids = wp_get_object_terms( $post->ID, TAXONOMY, array( 'fields' => 'ids' ) );
		$selected_ids = $this->expand_with_ancestor_terms( $selected_ids );
		$html = '<div class="media-categories-modal-field"><fieldset><legend class="screen-reader-text">' . esc_html__( 'Media Categories', 'media-categories' ) . '</legend>';
		$html .= sprintf(
			'<input type="hidden" class="media-categories-modal-input" name="attachments[%1$d][media_categories_terms]" value="%2$s" />',
			(int) $post->ID,
			esc_attr( implode( ',', array_map( 'intval', $selected_ids ) ) )
		);
		$html .= $this->render_modal_term_checklist( $selected_ids );
		$html                     .= '</fieldset></div>';
		$form_fields['media_categories_terms'] = array(
			'label' => __( 'Media Categories', 'media-categories' ),
			'input' => 'html',
			'html'  => $html,
		);

		return $form_fields;
	}

	/**
	 * Save terms from media modal.
	 *
	 * @param array $post Attachment payload.
	 * @param array $attachment Submitted attachment data.
	 * @return array
	 */
	public function save_modal_fields( $post, $attachment ) {
		if ( ! current_user_can( 'upload_files' ) ) {
			return $post;
		}

		$terms = array();

		if ( isset( $attachment['media_categories_terms'] ) ) {
			if ( is_array( $attachment['media_categories_terms'] ) ) {
				$terms = $attachment['media_categories_terms'];
			} else {
				$terms = array_filter(
					array_map(
						'trim',
						explode( ',', (string) $attachment['media_categories_terms'] )
					),
					'strlen'
				);
			}
		}

		$terms = array_map( 'intval', $terms );
		$terms = $this->expand_with_ancestor_terms( $terms );

		wp_set_object_terms( $post['ID'], $terms, TAXONOMY, false );

		return $post;
	}

	/**
	 * Render hierarchical checklist markup for the media modal.
	 *
	 * @param int[] $selected_ids Selected term IDs.
	 * @return string
	 */
	private function render_modal_term_checklist( $selected_ids ) {
		$tree = $this->get_term_tree();

		if ( empty( $tree ) ) {
			return '<p class="description">' . esc_html__( 'No media categories available yet.', 'media-categories' ) . '</p>';
		}

		return '<ul class="media-categories-modal-tree">' . $this->render_modal_term_nodes( $tree, $selected_ids ) . '</ul>';
	}

	/**
	 * Render checklist items recursively.
	 *
	 * @param array[] $nodes Term tree nodes.
	 * @param int[]   $selected_ids Selected term IDs.
	 * @return string
	 */
	private function render_modal_term_nodes( $nodes, $selected_ids ) {
		$html = '';

		foreach ( $nodes as $node ) {
			$html .= '<li class="media-categories-modal-tree__item">';
			$html .= sprintf(
				'<label><input type="checkbox" class="media-categories-modal-checkbox" value="%1$d" data-parent-term-id="%2$d" %3$s /> %4$s</label>',
				(int) $node['term_id'],
				(int) $node['parent'],
				checked( in_array( (int) $node['term_id'], $selected_ids, true ), true, false ),
				esc_html( $node['name'] )
			);

			if ( ! empty( $node['children'] ) ) {
				$html .= '<ul class="media-categories-modal-tree">';
				$html .= $this->render_modal_term_nodes( $node['children'], $selected_ids );
				$html .= '</ul>';
			}

			$html .= '</li>';
		}

		return $html;
	}

	/**
	 * Build a hierarchical term tree.
	 *
	 * @return array[]
	 */
	private function get_term_tree() {
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
				'parent'   => (int) $term->parent,
				'children' => array(),
			);
		}

		$tree = array();

		foreach ( array_keys( $indexed ) as $term_id ) {
			$parent = $indexed[ $term_id ]['parent'];

			if ( 0 !== $parent && isset( $indexed[ $parent ] ) ) {
				$indexed[ $parent ]['children'][] = &$indexed[ $term_id ];
			} else {
				$tree[] = &$indexed[ $term_id ];
			}
		}

		return $tree;
	}

	/**
	 * Ensure ancestor terms are included for every selected child term.
	 *
	 * @param int[] $term_ids Selected term IDs.
	 * @return int[]
	 */
	private function expand_with_ancestor_terms( $term_ids ) {
		$term_ids = array_values( array_unique( array_filter( array_map( 'intval', $term_ids ) ) ) );

		foreach ( $term_ids as $term_id ) {
			$ancestors = get_ancestors( $term_id, TAXONOMY, 'taxonomy' );

			if ( ! empty( $ancestors ) ) {
				$term_ids = array_merge( $term_ids, array_map( 'intval', $ancestors ) );
			}
		}

		$term_ids = array_values( array_unique( array_filter( $term_ids ) ) );
		sort( $term_ids );

		return $term_ids;
	}
}
