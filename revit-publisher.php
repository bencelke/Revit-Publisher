<?php
/**
 * Plugin Name:       RevIt Publisher
 * Plugin URI:        https://revit24.com
 * Description:       Private automotive SEO and content intelligence engine for RevIt24.
 * Version:           0.1.0
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

define( 'REVIT_PUBLISHER_VERSION', '0.1.0' );
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

RevIt_Publisher_Plugin::instance()->init();
