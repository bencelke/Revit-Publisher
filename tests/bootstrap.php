<?php
/**
 * PHPUnit bootstrap for RevIt Publisher.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

define( 'ABSPATH', __DIR__ . '/../' );
define( 'REVIT_PUBLISHER_VERSION', '0.1.0' );
define( 'REVIT_PUBLISHER_PLUGIN_FILE', __DIR__ . '/../revit-publisher.php' );
define( 'REVIT_PUBLISHER_PLUGIN_DIR', __DIR__ . '/../' );
define( 'REVIT_PUBLISHER_PLUGIN_URL', 'http://example.com/wp-content/plugins/revit-publisher/' );

if ( ! function_exists( '__' ) ) {
	/**
	 * Minimal translation stub for tests.
	 *
	 * @param string $text Text.
	 */
	function __( string $text ): string {
		return $text;
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	/**
	 * JSON encode stub.
	 *
	 * @param mixed $data Data.
	 */
	function wp_json_encode( $data ): string {
		return (string) json_encode( $data );
	}
}

require_once REVIT_PUBLISHER_PLUGIN_DIR . 'vendor/autoload.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/article-package/class-article-package-validator.php';
