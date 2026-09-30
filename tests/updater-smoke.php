<?php
/**
 * Standalone updater checks: php tests/updater-smoke.php
 *
 * @package MediaCategories
 */

define( 'ABSPATH', __DIR__ );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'MEDIA_CATEGORIES_FILE', __DIR__ . '/../media-categories.php' );
define( 'MEDIA_CATEGORIES_UPDATE_INFO_URL', 'https://raw.githubusercontent.com/cdlib/media-categories/main/downloads/info.json' );

$cached_info = false;
$response_body = '';
$response_code = 200;
$request_count = 0;

function plugin_basename( $file ) {
	return basename( dirname( $file ) ) . '/' . basename( $file );
}
function get_site_transient( $key ) {
	global $cached_info;
	return $cached_info;
}
function set_site_transient( $key, $value, $ttl ) {
	global $cached_info;
	$cached_info = $value;
}
function wp_remote_get( $url, $args ) {
	global $response_body, $response_code, $request_count;
	++$request_count;
	return array( 'body' => $response_body, 'code' => $response_code );
}
function is_wp_error( $value ) {
	return false;
}
function wp_remote_retrieve_response_code( $response ) {
	return $response['code'];
}
function wp_remote_retrieve_body( $response ) {
	return $response['body'];
}
function sanitize_text_field( $value ) {
	return trim( (string) $value );
}
function esc_url_raw( $value ) {
	return (string) $value;
}
function plugins_url( $path, $plugin ) {
	return 'https://example.test/' . $path;
}
function wp_parse_url( $url ) {
	return parse_url( $url );
}
function wp_kses_post( $value ) {
	return (string) $value;
}
function sanitize_key( $value ) {
	return strtolower( (string) $value );
}
function wp_unslash( $value ) {
	return stripslashes( $value );
}

require_once __DIR__ . '/../includes/class-updater.php';

function assert_same( $expected, $actual, $label ) {
	if ( $expected !== $actual ) {
		fwrite( STDERR, $label . " failed\n" );
		exit( 1 );
	}
}

$updater = new Media_Categories\Updater();
$plugin_file = plugin_basename( MEDIA_CATEGORIES_FILE );
$good_url = 'https://github.com/cdlib/media-categories/releases/download/v1.1.2/media-categories-1.1.2.zip';
$body = array(
	'version'      => '1.1.2',
	'download_url' => $good_url,
);
$response_body = json_encode( $body );
$update = $updater->filter_update( false, array(), $plugin_file, array() );
assert_same( $good_url, $update->package, 'matching GitHub package' );
assert_same( 'https://github.com/cdlib/media-categories/', $update->id, 'update ID' );

$response_code = 404;
$cached_info = false;
assert_same( false, $updater->filter_update( false, array(), $plugin_file, array() ), 'non-200 metadata' );
$response_code = 200;
$response_body = '{bad json';
assert_same( false, $updater->filter_update( false, array(), $plugin_file, array() ), 'malformed metadata' );

foreach ( array(
	'https://cdlib.org/services-groups/webprod/plugins/media-categories/files/media-categories-1.1.2.zip',
	'https://github.com/other/media-categories/releases/download/v1.1.2/media-categories-1.1.2.zip',
	'https://github.com/cdlib/media-categories/releases/download/v1.1.1/media-categories-1.1.1.zip',
	'https://github.com/cdlib/media-categories/releases/download/v1.1.2/media-categories-1.1.2.zip?x=1',
) as $bad_url ) {
	$cached_info = false;
	$body['download_url'] = $bad_url;
	$response_body = json_encode( $body );
	assert_same( false, $updater->filter_update( false, array(), $plugin_file, array() ), 'rejected package URL' );
}

$cached_info = array( 'version' => '1.1.1', 'download_url' => 'https://cdlib.org/old.zip' );
$body['download_url'] = $good_url;
$response_body = json_encode( $body );
$request_count = 0;
assert_same( $good_url, $updater->filter_update( false, array(), $plugin_file, array() )->package, 'stale cache refresh' );
assert_same( 1, $request_count, 'stale cache request count' );
$updater->filter_update( false, array(), $plugin_file, array() );
assert_same( 1, $request_count, 'valid cache request count' );
$_GET['force-check'] = '1';
$updater->filter_update( false, array(), $plugin_file, array() );
assert_same( 2, $request_count, 'forced refresh request count' );
unset( $_GET['force-check'] );

$transient = (object) array( 'checked' => array( $plugin_file => '1.1.1' ) );
$transient = $updater->inject_legacy_update( $transient );
assert_same( $good_url, $transient->response[ $plugin_file ]->package, 'legacy transient package' );

echo "Updater smoke checks passed.\n";
