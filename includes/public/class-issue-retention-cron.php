<?php
/**
 * Scheduled purge of resolved and ignored operational issues.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Deletes stale resolved/ignored issues past retention policy.
 */
class RevIt_Publisher_Issue_Retention_Cron {

	public const CRON_HOOK = 'revit_publisher_issue_retention';

	public static function init(): void {
		add_action( self::CRON_HOOK, array( self::class, 'run_purge' ) );
	}

	public static function activate(): void {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
		}
	}

	public static function deactivate(): void {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	public static function run_purge(): void {
		$days = RevIt_Publisher_Services::settings()->issue_retention_days();
		if ( $days <= 0 ) {
			return;
		}

		$cutoff = gmdate( 'c', time() - ( $days * DAY_IN_SECONDS ) );
		$purged = 0;

		$issues = get_posts(
			array(
				'post_type'      => RevIt_Publisher_Operations_Post_Types::ISSUE,
				'post_status'    => 'private',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'     => RevIt_Publisher_Operations_Meta_Keys::ISSUE_STATUS,
						'value'   => array(
							RevIt_Publisher_Issue_Service::STATUS_RESOLVED,
							RevIt_Publisher_Issue_Service::STATUS_IGNORED,
						),
						'compare' => 'IN',
					),
				),
			)
		);

		foreach ( $issues as $issue_id ) {
			$resolved_at = (string) get_post_meta( (int) $issue_id, RevIt_Publisher_Operations_Meta_Keys::ISSUE_RESOLVED_AT, true );
			if ( '' === $resolved_at ) {
				$resolved_at = (string) get_post_meta( (int) $issue_id, RevIt_Publisher_Operations_Meta_Keys::ISSUE_LAST_SEEN, true );
			}
			if ( '' === $resolved_at || $resolved_at > $cutoff ) {
				continue;
			}

			if ( wp_delete_post( (int) $issue_id, true ) ) {
				++$purged;
			}
		}

		if ( $purged > 0 ) {
			RevIt_Publisher_Services::event_logger()->log(
				'issue_retention_purge',
				array(
					'purged' => $purged,
					'days'   => $days,
				)
			);
		}
	}
}
