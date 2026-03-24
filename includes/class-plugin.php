<?php
/**
 * Main plugin bootstrap.
 *
 * @package MediaCategories
 */

namespace Media_Categories;

defined( 'ABSPATH' ) || exit;

require_once MEDIA_CATEGORIES_DIR . 'includes/class-taxonomy.php';
require_once MEDIA_CATEGORIES_DIR . 'includes/class-capabilities.php';
require_once MEDIA_CATEGORIES_DIR . 'includes/class-admin-menu.php';
require_once MEDIA_CATEGORIES_DIR . 'includes/class-settings.php';
require_once MEDIA_CATEGORIES_DIR . 'includes/class-attachment-fields.php';
require_once MEDIA_CATEGORIES_DIR . 'includes/class-media-filters.php';
require_once MEDIA_CATEGORIES_DIR . 'includes/class-folder-sidebar.php';
require_once MEDIA_CATEGORIES_DIR . 'includes/class-assets.php';
require_once MEDIA_CATEGORIES_DIR . 'includes/class-updater.php';

/**
 * Coordinates plugin services.
 */
final class Plugin {
	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance;

	/**
	 * Service objects.
	 *
	 * @var object[]
	 */
	private $services = array();

	/**
	 * Get singleton instance.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Activation callback.
	 *
	 * @return void
	 */
	public static function activate() {
		Capabilities::sync_role_capabilities();
		flush_rewrite_rules();
	}

	/**
	 * Deactivation callback.
	 *
	 * @return void
	 */
	public static function deactivate() {
		flush_rewrite_rules();
	}

	/**
	 * Boot services.
	 *
	 * @return void
	 */
	public function init() {
		add_filter( 'all_plugins', array( $this, 'filter_plugin_author_link' ) );
		add_filter( 'plugin_row_meta', array( $this, 'filter_plugin_row_meta' ), 10, 4 );

		$this->services = array(
			new Capabilities(),
			new Taxonomy(),
			new Admin_Menu(),
			new Settings(),
			new Attachment_Fields(),
			new Media_Filters(),
			new Folder_Sidebar(),
			new Assets(),
			new Updater(),
		);

		foreach ( $this->services as $service ) {
			if ( method_exists( $service, 'register' ) ) {
				$service->register();
			}
		}
	}

	/**
	 * Add a mailto author link for this plugin on the Plugins screen.
	 *
	 * @param array<string,array<string,mixed>> $plugins All discovered plugins.
	 * @return array<string,array<string,mixed>>
	 */
	public function filter_plugin_author_link( $plugins ) {
		$plugin_file = plugin_basename( MEDIA_CATEGORIES_FILE );

		if ( ! isset( $plugins[ $plugin_file ] ) || ! is_array( $plugins[ $plugin_file ] ) ) {
			return $plugins;
		}

		$plugins[ $plugin_file ]['Author'] = '<a href="mailto:esatzman@ucop.edu">Eric Satzman</a>';

		return $plugins;
	}

	/**
	 * Rename the plugin site link on the Plugins screen.
	 *
	 * @param string[] $plugin_meta Plugin row meta links.
	 * @param string   $plugin_file Plugin file path.
	 * @param array    $plugin_data Plugin header data.
	 * @param string   $status Plugin status.
	 * @return string[]
	 */
	public function filter_plugin_row_meta( $plugin_meta, $plugin_file, $plugin_data, $status ) {
		unset( $plugin_data, $status );

		if ( plugin_basename( MEDIA_CATEGORIES_FILE ) !== $plugin_file ) {
			return $plugin_meta;
		}

		foreach ( $plugin_meta as $index => $link ) {
			if ( false !== strpos( $link, 'href="https://github.com/ericsatzman/media-categories"' ) ) {
				$plugin_meta[ $index ] = preg_replace(
					'#>[^<]+<#',
					'>' . esc_html__( 'View Details', 'media-categories' ) . '<',
					$link
				);
			}
		}

		return $plugin_meta;
	}
}
