<?php
/**
 * Scheduled audit cron registration.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Schedules and runs RevIt Publisher audits via WP-Cron.
 */
class RevIt_Publisher_Audit_Cron {

	public static function activate(): void {
		self::reschedule();
	}

	public static function deactivate(): void {
		wp_clear_scheduled_hook( RevIt_Publisher_Audit_Service::CRON_HOOK );
	}

	public static function init(): void {
		add_action( RevIt_Publisher_Audit_Service::CRON_HOOK, array( self::class, 'run_scheduled' ) );
		add_filter( 'cron_schedules', array( self::class, 'add_schedules' ) );
	}

	public static function reschedule(): void {
		wp_clear_scheduled_hook( RevIt_Publisher_Audit_Service::CRON_HOOK );
		$enabled = (bool) get_option( RevIt_Publisher_Settings::SCHEDULED_AUDIT_ENABLED, true );
		if ( ! $enabled ) {
			return;
		}
		$frequency = (string) get_option( RevIt_Publisher_Settings::AUDIT_FREQUENCY, 'daily' );
		if ( ! in_array( $frequency, array( 'daily', 'revit_twice_daily', 'weekly' ), true ) ) {
			$frequency = 'daily';
		}
		if ( ! wp_next_scheduled( RevIt_Publisher_Audit_Service::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, $frequency, RevIt_Publisher_Audit_Service::CRON_HOOK );
		}
	}

	public static function run_scheduled(): void {
		if ( ! RevIt_Publisher_Services::settings()->scheduled_audit_enabled() ) {
			return;
		}
		$result = RevIt_Publisher_Services::site_audit()->run( false );
		if ( 'batch' === ( $result['status'] ?? '' ) ) {
			wp_schedule_single_event( time() + 60, RevIt_Publisher_Audit_Service::CRON_HOOK );
		}
	}

	/**
	 * @param array<string, array<string, int|string>> $schedules
	 * @return array<string, array<string, int|string>>
	 */
	public static function add_schedules( array $schedules ): array {
		$schedules['revit_twice_daily'] = array(
			'interval' => 12 * HOUR_IN_SECONDS,
			'display'  => __( 'Twice Daily', 'revit-publisher' ),
		);
		return $schedules;
	}
}
