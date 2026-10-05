<?php
/**
 * Plugin Name:       QNAAPI Connect
 * Plugin URI:        https://qnaapi.com
 * Description:       Create and embed polls, quizzes, and forms/surveys powered by QNAAPI — shortcodes, Gutenberg blocks, AI-generated drafts, audience traits/profiles, and a wp-admin dashboard widget included.
 * Version:           0.4.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            QNAAPI
 * Author URI:        https://qnaapi.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       qnaapi-connect
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

define( 'QNAAPI_CONNECT_VERSION', '0.4.0' );
define( 'QNAAPI_CONNECT_FILE', __FILE__ );
define( 'QNAAPI_CONNECT_DIR', plugin_dir_path( __FILE__ ) );
define( 'QNAAPI_CONNECT_URL', plugin_dir_url( __FILE__ ) );
define( 'QNAAPI_CONNECT_DEFAULT_BASE_URL', 'https://qnaapi.com/api/v1' );

require_once QNAAPI_CONNECT_DIR . 'includes/class-qnaapi-connect-client.php';
require_once QNAAPI_CONNECT_DIR . 'includes/class-qnaapi-connect-settings.php';
require_once QNAAPI_CONNECT_DIR . 'includes/class-qnaapi-connect-resources-admin.php';
require_once QNAAPI_CONNECT_DIR . 'includes/class-qnaapi-connect-traits-admin.php';
require_once QNAAPI_CONNECT_DIR . 'includes/class-qnaapi-connect-profiles-admin.php';
require_once QNAAPI_CONNECT_DIR . 'includes/class-qnaapi-connect-generation-admin.php';
require_once QNAAPI_CONNECT_DIR . 'includes/class-qnaapi-connect-shortcodes.php';
require_once QNAAPI_CONNECT_DIR . 'includes/class-qnaapi-connect-blocks.php';
require_once QNAAPI_CONNECT_DIR . 'includes/class-qnaapi-connect-dashboard-widget.php';
require_once QNAAPI_CONNECT_DIR . 'includes/class-qnaapi-connect-assets.php';

/**
 * Boots every plugin subsystem. Each class wires its own hooks in its
 * constructor, so booting is just instantiation, in no particular order.
 */
function qnaapi_connect_boot() {
	new QNAAPI_Connect_Settings();
	new QNAAPI_Connect_Resources_Admin();
	new QNAAPI_Connect_Traits_Admin();
	new QNAAPI_Connect_Profiles_Admin();
	new QNAAPI_Connect_Generation_Admin();
	new QNAAPI_Connect_Shortcodes();
	new QNAAPI_Connect_Blocks();
	new QNAAPI_Connect_Dashboard_Widget();
	new QNAAPI_Connect_Assets();
}
add_action( 'plugins_loaded', 'qnaapi_connect_boot' );

/**
 * Returns the shared API client, configured from the plugin's saved settings.
 */
function qnaapi_connect_client() {
	static $client = null;

	if ( null === $client ) {
		$options  = get_option( 'qnaapi_connect_options', array() );
		$api_key  = isset( $options['api_key'] ) ? $options['api_key'] : '';
		$base_url = isset( $options['base_url'] ) && $options['base_url'] ? $options['base_url'] : QNAAPI_CONNECT_DEFAULT_BASE_URL;

		$client = new QNAAPI_Connect_Client( $api_key, $base_url );
	}

	return $client;
}
