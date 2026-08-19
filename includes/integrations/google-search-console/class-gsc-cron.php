<?php
/**
 * Scheduled Search Console sync cron.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RevIt_Publisher_GSC_Cron {

	public const CRON_HOOK = 'revit_publisher_gsc_sync';

	public static function init(): void {
		add_action( self::CRON_HOOK, array( self::class, 'run' ) );
	}

	public static function activate(): void {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
		}
	}

	public static function deactivate(): void {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	public static function reschedule(): void {
		wp_clear_scheduled_hook( self::CRON_HOOK );
		if ( RevIt_Publisher_Services::settings()->gsc_sync_enabled() ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, RevIt_Publisher_Services::settings()->gsc_sync_frequency(), self::CRON_HOOK );
		}
	}

	public static function run(): void {
		if ( ! RevIt_Publisher_Services::settings()->gsc_sync_enabled() ) {
			return;
		}
		if ( ! RevIt_Publisher_Services::gsc_auth()->is_connected() ) {
			return;
		}
		RevIt_Publisher_Services::gsc_sync()->sync( false );
	}
}
