<?php
/**
 * Plugin deactivation.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles plugin deactivation.
 */
class RevIt_Publisher_Deactivator {

	/**
	 * Run on plugin deactivation.
	 */
	public static function deactivate(): void {
		require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/operations/class-audit-cron.php';
		require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/public/class-issue-retention-cron.php';
		RevIt_Publisher_Audit_Cron::deactivate();
		RevIt_Publisher_Issue_Retention_Cron::deactivate();
		flush_rewrite_rules();
	}
}
