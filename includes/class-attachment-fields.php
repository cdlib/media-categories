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
		wp_nonce_field( 'media_categories_attachment_terms', 'media_categories_attachment_terms_nonce' );

		echo '<div class="media-categories-checklist">';
		wp_terms_checklist(
			$post->ID,
			array(
				'taxonomy'      => TAXONOMY,
				'selected_cats' => wp_get_object_terms( $post->ID, TAXONOMY, array( 'fields' => 'ids' ) ),
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
		$terms        = get_terms(
			array(
				'taxonomy'   => TAXONOMY,
				'hide_empty' => false,
				'orderby'    => 'name',
			)
		);

		$html = '<div class="media-categories-modal-field"><fieldset><legend class="screen-reader-text">' . esc_html__( 'Media Categories', 'media-categories' ) . '</legend>';

		foreach ( $terms as $term ) {
			$html .= sprintf(
				'<label><input type="checkbox" name="attachments[%1$d][media_categories_terms][]" value="%2$d" %3$s /> %4$s</label><br />',
				(int) $post->ID,
				(int) $term->term_id,
				checked( in_array( (int) $term->term_id, $selected_ids, true ), true, false ),
				esc_html( $term->name )
			);
		}

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

		$terms = isset( $attachment['media_categories_terms'] ) ? (array) $attachment['media_categories_terms'] : array();
		$terms = array_map( 'intval', $terms );

		wp_set_object_terms( $post['ID'], $terms, TAXONOMY, false );

		return $post;
	}
}
