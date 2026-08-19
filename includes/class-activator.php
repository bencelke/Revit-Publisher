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

		require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/content-model/class-taxonomies.php';
		require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/content-plan/class-content-plan-post-type.php';
		require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/operations/class-operations-post-types.php';
		require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/operations/class-audit-cron.php';
		require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/public/class-issue-retention-cron.php';
		require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/public/class-vehicle-hub-post-type.php';
		require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/integrations/google-search-console/class-gsc-schema.php';
		require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/integrations/google-search-console/class-gsc-cron.php';
		require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/seo/class-settings.php';

		RevIt_Publisher_Taxonomies::register();
		RevIt_Publisher_Taxonomies::ensure_article_type_terms();
		RevIt_Publisher_Content_Plan_Post_Type::register();
		RevIt_Publisher_Operations_Post_Types::register();
		RevIt_Publisher_Vehicle_Hub_Post_Type::register();
		RevIt_Publisher_GSC_Schema::install();
		RevIt_Publisher_Audit_Cron::activate();
		RevIt_Publisher_Issue_Retention_Cron::activate();
		RevIt_Publisher_GSC_Cron::activate();

		flush_rewrite_rules();
	}
}
