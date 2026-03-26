<?php
/**
 * Plugin Name: Media Categories
 * Plugin URI:  https://github.com/ericsatzman/media-categories
 * Description: Adds hierarchical media categories, media-library filtering, and virtual folders for attachments.
 * Version:     1.0.1
 * Author:      Eric Satzman
 * Update URI:  https://satzman.com/plugin-updates/media-categories/
 * License:     MIT
 * License URI: https://opensource.org/licenses/MIT
 * Text Domain: media-categories
 *
 * @package MediaCategories
 */

defined( 'ABSPATH' ) || exit;

define( 'MEDIA_CATEGORIES_VERSION', '1.0.1' );
define( 'MEDIA_CATEGORIES_FILE', __FILE__ );
define( 'MEDIA_CATEGORIES_DIR', plugin_dir_path( __FILE__ ) );
define( 'MEDIA_CATEGORIES_URL', plugin_dir_url( __FILE__ ) );
define( 'MEDIA_CATEGORIES_UPDATE_INFO_URL', 'https://satzman.com/plugin-updates/media-categories/info.json' );
define( 'MEDIA_CATEGORIES_UPDATE_PACKAGE_URL', 'https://satzman.com/plugin-updates/media-categories/media-categories-1.0.1.zip' );

require_once MEDIA_CATEGORIES_DIR . 'includes/helpers.php';
require_once MEDIA_CATEGORIES_DIR . 'includes/class-plugin.php';

register_activation_hook( MEDIA_CATEGORIES_FILE, array( 'Media_Categories\\Plugin', 'activate' ) );
register_deactivation_hook( MEDIA_CATEGORIES_FILE, array( 'Media_Categories\\Plugin', 'deactivate' ) );

Media_Categories\Plugin::instance()->init();
