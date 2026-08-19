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

require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/content-model/class-post-meta-keys.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/content-model/class-taxonomies.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/content-model/class-article-registry.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/content-model/class-vehicle-taxonomy-service.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/content-model/class-cluster-service.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/article-package/class-article-package-validator.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/article-package/class-package-hash.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/article-package/class-content-renderer.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/article-package/class-package-preview.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/article-package/class-article-importer.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/rest/class-article-package-rest-controller.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/admin/class-admin.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/admin/class-post-meta-box.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/admin/class-post-list-columns.php';

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
		RevIt_Publisher_Taxonomies::init();

		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

		if ( is_admin() ) {
			RevIt_Publisher_Admin::instance()->init();
			RevIt_Publisher_Post_Meta_Box::instance()->init();
			RevIt_Publisher_Post_List_Columns::instance()->init();
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
		$registry        = new RevIt_Publisher_Article_Registry();
		$vehicle_service = new RevIt_Publisher_Vehicle_Taxonomy_Service();
		$cluster_service = new RevIt_Publisher_Cluster_Service();
		$validator       = new RevIt_Publisher_Article_Package_Validator();
		$preview         = new RevIt_Publisher_Package_Preview( $registry, $vehicle_service );
		$importer        = new RevIt_Publisher_Article_Importer(
			$validator,
			$registry,
			$vehicle_service,
			$cluster_service,
			new RevIt_Publisher_Content_Renderer(),
			new RevIt_Publisher_Package_Hash()
		);

		$controller = new RevIt_Publisher_Article_Package_Rest_Controller(
			$validator,
			$preview,
			$importer,
			$registry,
			$vehicle_service,
			$cluster_service
		);
		$controller->register_routes();
	}
}
