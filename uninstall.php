<?php
/**
 * Uninstall routine for Media Categories.
 *
 * @package MediaCategories
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'media_categories_manage_roles' );
