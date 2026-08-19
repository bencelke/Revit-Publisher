<?php
/**
 * Plugin activation.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles plugin activation.
 */
class RevIt_Publisher_Activator {

	/**
	 * Run on plugin activation.
	 */
	public static function activate(): void {
		if ( version_compare( PHP_VERSION, '8.2', '<' ) ) {
			deactivate_plugins( plugin_basename( REVIT_PUBLISHER_PLUGIN_FILE ) );
			wp_die(
				esc_html__( 'RevIt Publisher requires PHP 8.2 or higher.', 'revit-publisher' ),
				esc_html__( 'Plugin Activation Error', 'revit-publisher' ),
				array( 'back_link' => true )
			);
		}

		flush_rewrite_rules();
	}
}
