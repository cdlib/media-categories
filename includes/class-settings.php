<?php
/**
 * Plugin settings page.
 *
 * @package MediaCategories
 */

namespace Media_Categories;

defined( 'ABSPATH' ) || exit;

/**
 * Handles settings UI.
 */
class Settings {
	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Add settings page.
	 *
	 * @return void
	 */
	public function add_settings_page() {
		add_options_page(
			__( 'Media Categories', 'media-categories' ),
			__( 'Media Categories', 'media-categories' ),
			'manage_options',
			'media-categories-settings',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Register settings API objects.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			'media_categories',
			SETTINGS_OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_roles' ),
				'default'           => array( 'administrator' ),
			)
		);

		add_settings_section(
			'media_categories_permissions',
			__( 'Category Management Permissions', 'media-categories' ),
			array( $this, 'render_section' ),
			'media-categories-settings'
		);

		add_settings_field(
			'media_categories_manage_roles',
			__( 'Roles allowed to manage categories', 'media-categories' ),
			array( $this, 'render_roles_field' ),
			'media-categories-settings',
			'media_categories_permissions'
		);
	}

	/**
	 * Sanitize roles field.
	 *
	 * @param mixed $value Submitted value.
	 * @return string[]
	 */
	public function sanitize_roles( $value ) {
		$roles          = is_array( $value ) ? $value : array();
		$available      = wp_roles()->roles;
		$sanitized      = array();

		foreach ( $roles as $role ) {
			$role = sanitize_key( $role );

			if ( isset( $available[ $role ] ) ) {
				$sanitized[] = $role;
			}
		}

		if ( ! in_array( 'administrator', $sanitized, true ) ) {
			$sanitized[] = 'administrator';
		}

		return $sanitized;
	}

	/**
	 * Section copy.
	 *
	 * @return void
	 */
	public function render_section() {
		echo '<p>' . esc_html__( 'Choose which roles can create, edit, and delete media categories. Any user who can upload files can still assign categories to attachments.', 'media-categories' ) . '</p>';
	}

	/**
	 * Render role checkboxes.
	 *
	 * @return void
	 */
	public function render_roles_field() {
		$saved_roles = get_manage_roles();
		$roles       = wp_roles()->roles;

		echo '<fieldset>';

		foreach ( $roles as $role_key => $role_data ) {
			$checked  = in_array( $role_key, $saved_roles, true );
			$disabled = 'administrator' === $role_key;
			$field_id = 'media-categories-role-' . $role_key;

			printf(
				'<label for="%1$s"><input type="checkbox" id="%1$s" name="%2$s[]" value="%3$s" %4$s %5$s /> %6$s</label><br />',
				esc_attr( $field_id ),
				esc_attr( SETTINGS_OPTION ),
				esc_attr( $role_key ),
				checked( $checked, true, false ),
				disabled( $disabled, true, false ),
				esc_html( translate_user_role( $role_data['name'] ) )
			);
		}

		echo '<p class="description">' . esc_html__( 'Administrators always retain category-management access.', 'media-categories' ) . '</p>';
		echo '</fieldset>';
	}

	/**
	 * Render settings page.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to access this page.', 'media-categories' ) );
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Media Categories', 'media-categories' ); ?></h1>
			<form action="options.php" method="post">
				<?php
				settings_fields( 'media_categories' );
				do_settings_sections( 'media-categories-settings' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}
}
