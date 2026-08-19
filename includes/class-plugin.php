<?php
/**
 * Core plugin bootstrap.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/article-package/class-article-package-validator.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/rest/class-article-package-rest-controller.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/admin/class-admin.php';

/**
 * Main plugin singleton.
 */
final class RevIt_Publisher_Plugin {

	/**
	 * Plugin instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Get plugin instance.
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Initialize hooks.
	 */
	public function init(): void {
		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

		if ( is_admin() ) {
			RevIt_Publisher_Admin::instance()->init();
		}
	}

	/**
	 * Load plugin translations.
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			'revit-publisher',
			false,
			dirname( plugin_basename( REVIT_PUBLISHER_PLUGIN_FILE ) ) . '/languages'
		);
	}

	/**
	 * Register REST API routes.
	 */
	public function register_rest_routes(): void {
		$controller = new RevIt_Publisher_Article_Package_Rest_Controller(
			new RevIt_Publisher_Article_Package_Validator()
		);
		$controller->register_routes();
	}
}
