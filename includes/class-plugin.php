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
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/article-package/class-article-update-service.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/content-plan/class-content-plan-meta-keys.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/content-plan/class-content-plan-post-type.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/content-plan/class-content-plan-validator.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/content-plan/class-content-plan-importer.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/content-plan/class-content-plan-service.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/content-plan/class-article-request-exporter.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/intelligence/class-topic-fingerprint.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/intelligence/class-topic-overlap-service.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/intelligence/class-seo-score-service.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/intelligence/class-optimization-service.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/intelligence/class-review-status-service.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/seo/class-seo-plugin-detector.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/seo/class-settings.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/seo/class-public-seo-output.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/seo/class-structured-data-output.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/graph/class-topic-normalizer.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/graph/class-article-resolver.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/graph/class-content-graph.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/graph/class-internal-link-service.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/graph/class-seo-health-service.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/graph/class-link-audit-service.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/operations/class-operations-meta-keys.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/operations/class-operations-post-types.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/operations/class-severity.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/operations/class-event-logger.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/operations/class-link-change-log.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/operations/class-issue-service.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/operations/class-scheduled-audit-service.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/operations/class-pillar-link-policy-service.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/operations/class-link-undo-service.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/operations/class-redirect-service.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/operations/class-redirect-runtime.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/operations/class-consolidation-service.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/operations/class-404-monitor.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/operations/class-vehicle-health-service.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/operations/class-audit-cron.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/public/class-vehicle-hub-meta-keys.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/public/class-vehicle-identity.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/public/class-vehicle-hub-post-type.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/public/class-hub-cache.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/public/class-vehicle-hub-service.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/public/class-public-template-loader.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/public/class-public-breadcrumbs.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/public/class-public-blocks.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/public/class-sitemap-service.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/public/class-sitemap-health-service.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/public/class-hub-seo-health.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/public/class-cluster-link-matrix.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/public/class-serp-preview-service.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/public/class-issue-retention-cron.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/public/class-hub-structured-data.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/scan/class-engine-family.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/scan/class-heading-auditor.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/scan/class-natural-anchor-finder.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/scan/class-rendered-html-analyzer.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/scan/class-mechanical-seo-checklist.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/scan/class-link-opportunity-discovery.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/scan/class-import-batch-service.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/scan/class-post-content-scanner.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/scan/class-rendered-page-validator.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/scan/class-seo-fix-service.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/scan/class-site-seo-scan-service.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/class-services.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/rest/class-article-package-rest-controller.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/rest/class-seo-graph-rest-controller.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/rest/class-content-plan-rest-controller.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/rest/class-operations-rest-controller.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/rest/class-vehicle-hub-rest-controller.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/integrations/google-search-console/interface-gsc-client.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/integrations/google-search-console/class-gsc-schema.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/integrations/google-search-console/class-gsc-fixture-data.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/integrations/google-search-console/class-gsc-fake-client.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/integrations/google-search-console/class-gsc-google-client.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/integrations/google-search-console/class-gsc-client.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/integrations/google-search-console/class-gsc-token-store.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/integrations/google-search-console/class-gsc-auth-service.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/integrations/google-search-console/class-gsc-page-mapper.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/integrations/google-search-console/class-gsc-data-store.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/integrations/google-search-console/class-gsc-sync-service.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/integrations/google-search-console/class-gsc-search-analytics-service.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/integrations/google-search-console/class-gsc-opportunity-service.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/integrations/google-search-console/class-gsc-url-inspection-service.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/integrations/google-search-console/class-gsc-sitemap-service.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/integrations/google-search-console/class-gsc-insights-service.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/integrations/google-search-console/class-gsc-query-coverage-service.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/integrations/google-search-console/class-gsc-content-status.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/integrations/google-search-console/class-gsc-refresh-export.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/integrations/google-search-console/class-gsc-cron.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/rest/class-gsc-rest-controller.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/core/class-migration-service.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/editorial/class-editorial-meta-keys.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/editorial/class-editorial-priority-service.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/editorial/class-editorial-queue-service.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/editorial/class-editorial-queue-reconciler.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/editorial/class-vehicle-opportunity-service.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/editorial/class-cluster-opportunity-service.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/system/class-system-health-service.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/system/class-backup-service.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/system/class-performance-profiler.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/system/class-fixture-generator.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/rest/class-editorial-rest-controller.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/rest/class-system-health-rest-controller.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/rest/class-backup-rest-controller.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/rest/class-seo-scan-rest-controller.php';
require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/admin/class-vehicle-hub-meta-box.php';
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
		RevIt_Publisher_Content_Plan_Post_Type::init();
		RevIt_Publisher_Operations_Post_Types::init();
		RevIt_Publisher_Vehicle_Hub_Post_Type::init();
		RevIt_Publisher_Audit_Cron::init();
		RevIt_Publisher_Issue_Retention_Cron::init();
		RevIt_Publisher_GSC_Cron::init();
		RevIt_Publisher_GSC_Schema::maybe_install();
		RevIt_Publisher_Services::migrations()->maybe_upgrade();

		add_action( 'admin_init', array( $this, 'handle_gsc_oauth_callback' ) );

		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
		add_action( 'admin_init', array( RevIt_Publisher_Settings::class, 'register' ) );

		$this->init_public_seo();
		RevIt_Publisher_Services::hub_cache()->init();
		RevIt_Publisher_Services::not_found_monitor()->init();
		( new RevIt_Publisher_Redirect_Runtime() )->init();
		( new RevIt_Publisher_Public_Template_Loader() )->init();
		( new RevIt_Publisher_Public_Breadcrumbs() )->init();
		( new RevIt_Publisher_Public_Blocks() )->init();
		RevIt_Publisher_Services::sitemap()->init();

		if ( is_admin() ) {
			RevIt_Publisher_Admin::instance()->init();
			RevIt_Publisher_Post_Meta_Box::instance()->init();
			RevIt_Publisher_Post_List_Columns::instance()->init();
			RevIt_Publisher_Vehicle_Hub_Meta_Box::init();
		}
	}

	/**
	 * Initialize frontend SEO output hooks.
	 */
	private function init_public_seo(): void {
		$settings   = RevIt_Publisher_Services::settings();
		$resolver   = RevIt_Publisher_Services::resolver();
		$graph      = RevIt_Publisher_Services::graph();
		$breadcrumbs = new RevIt_Publisher_Public_Breadcrumbs();

		( new RevIt_Publisher_Public_SEO_Output( $settings, $resolver ) )->init();
		( new RevIt_Publisher_Structured_Data_Output( $settings, $resolver, $graph, $breadcrumbs ) )->init();
		( new RevIt_Publisher_Hub_Structured_Data( $settings, $breadcrumbs ) )->init();
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
		$registry        = RevIt_Publisher_Services::registry();
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

		$package_controller = new RevIt_Publisher_Article_Package_Rest_Controller(
			$validator,
			$preview,
			$importer,
			$registry,
			$vehicle_service,
			$cluster_service
		);
		$package_controller->register_routes();

		$graph_controller = new RevIt_Publisher_SEO_Graph_Rest_Controller();
		$graph_controller->register_routes();

		$plan_controller = new RevIt_Publisher_Content_Plan_Rest_Controller();
		$plan_controller->register_routes();

		$ops_controller = new RevIt_Publisher_Operations_Rest_Controller();
		$ops_controller->register_routes();

		$hub_controller = new RevIt_Publisher_Vehicle_Hub_Rest_Controller(
			RevIt_Publisher_Services::vehicle_hubs()
		);
		$hub_controller->register_routes();

		$gsc_controller = new RevIt_Publisher_GSC_Rest_Controller();
		$gsc_controller->register_routes();

		$editorial_controller = new RevIt_Publisher_Editorial_Rest_Controller();
		$editorial_controller->register_routes();

		$health_controller = new RevIt_Publisher_System_Health_Rest_Controller();
		$health_controller->register_routes();

		$backup_controller = new RevIt_Publisher_Backup_Rest_Controller();
		$backup_controller->register_routes();

		$scan_controller = new RevIt_Publisher_Seo_Scan_Rest_Controller();
		$scan_controller->register_routes();
	}

	/**
	 * Handle Google OAuth callback on settings page.
	 */
	public function handle_gsc_oauth_callback(): void {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( empty( $_GET['revit_gsc_oauth'] ) || empty( $_GET['code'] ) || empty( $_GET['state'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		$code  = sanitize_text_field( wp_unslash( (string) $_GET['code'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$state = sanitize_text_field( wp_unslash( (string) $_GET['state'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$result = RevIt_Publisher_Services::gsc_auth()->handle_oauth_callback( $code, $state );
		if ( is_wp_error( $result ) ) {
			add_action(
				'admin_notices',
				static function () use ( $result ): void {
					printf(
						'<div class="notice notice-error"><p>%s</p></div>',
						esc_html( $result->get_error_message() )
					);
				}
			);
			return;
		}
		wp_safe_redirect( admin_url( 'admin.php?page=revit-publisher-settings&gsc_connected=1' ) );
		exit;
	}
}
