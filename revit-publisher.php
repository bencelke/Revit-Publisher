<?php
/**
 * Plugin Name:       RevIt Publisher
 * Plugin URI:        https://revit24.com
 * Description:       Private automotive SEO and content intelligence engine for RevIt24.
 * Version:           0.8.0
 * Requires at least: 6.0
 * Requires PHP:      8.2
 * Author:            RevIt24
 * Text Domain:       revit-publisher
 * Domain Path:       /languages
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'REVIT_PUBLISHER_VERSION', '0.8.0' );
define( 'REVIT_PUBLISHER_DB_VERSION', 1 );
define( 'REVIT_PUBLISHER_PLUGIN_FILE', __FILE__ );
define( 'REVIT_PUBLISHER_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'REVIT_PUBLISHER_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

$revit_publisher_autoload = REVIT_PUBLISHER_PLUGIN_DIR . 'vendor/autoload.php';
if ( file_exists( $revit_publisher_autoload ) ) {
	require_once $revit_publisher_autoload;
}

require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/class-activator.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/class-deactivator.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/class-plugin.php';

register_activation_hook( __FILE__, array( 'RevIt_Publisher_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'RevIt_Publisher_Deactivator', 'deactivate' ) );
register_uninstall_hook( __FILE__, 'revit_publisher_uninstall' );

RevIt_Publisher_Plugin::instance()->init();

/**
 * Plugin uninstall handler.
 */
function revit_publisher_uninstall(): void {
	if ( ! get_option( 'revit_delete_data_on_uninstall', false ) ) {
		return;
	}
	global $wpdb;
	$tables = array(
		$wpdb->prefix . 'revit_gsc_page_metrics',
		$wpdb->prefix . 'revit_gsc_query_metrics',
		$wpdb->prefix . 'revit_gsc_inspections',
	);
	foreach ( $tables as $table ) {
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
	delete_option( 'revit_publisher_db_version' );
	delete_option( 'revit_publisher_event_log' );
	delete_option( 'revit_publisher_profiler_log' );
}
