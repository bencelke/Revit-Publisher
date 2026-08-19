<?php
/**
 * Review status and stale article signals.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Derives and manages _revit_review_status post meta.
 */
class RevIt_Publisher_Review_Status_Service {

	public const STATUS_HEALTHY         = 'healthy';
	public const STATUS_REVIEW_DUE      = 'review_due';
	public const STATUS_NEEDS_ATTENTION = 'needs_attention';
	public const STATUS_UPDATE_AVAILABLE = 'update_available';

	/**
	 * Derive review status for a post.
	 */
	public function derive_status( int $post_id ): string {
		$health = RevIt_Publisher_Services::health_service()->get_post_health( $post_id );

		if ( $this->is_review_due( $post_id ) ) {
			return self::STATUS_REVIEW_DUE;
		}

		if ( ! empty( $health['is_orphan'] )
			|| (int) ( $health['unresolved_links'] ?? 0 ) > 0
			|| ! empty( $health['missing_seo_title'] )
			|| ! empty( $health['missing_meta_description'] )
		) {
			return self::STATUS_NEEDS_ATTENTION;
		}

		foreach ( RevIt_Publisher_Services::topic_overlaps()->get_post_overlaps( $post_id ) as $overlap ) {
			if ( 'high' === ( $overlap['risk'] ?? '' ) ) {
				return self::STATUS_NEEDS_ATTENTION;
			}
		}

		return self::STATUS_HEALTHY;
	}

	/**
	 * Sync derived status to post meta.
	 */
	public function sync_status( int $post_id ): string {
		$status = $this->derive_status( $post_id );
		update_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::REVIEW_STATUS, $status );
		return $status;
	}

	/**
	 * Whether article is due for review based on age setting.
	 */
	public function is_review_due( int $post_id ): bool {
		$months = (int) RevIt_Publisher_Services::settings()->review_after_months();
		if ( $months <= 0 ) {
			return false;
		}

		$imported_at = (string) get_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::IMPORTED_AT, true );
		if ( '' === $imported_at ) {
			return false;
		}

		$timestamp = strtotime( $imported_at );
		if ( false === $timestamp ) {
			return false;
		}

		$threshold = strtotime( "-{$months} months" );
		return $timestamp <= $threshold;
	}

	/**
	 * Mark update available status.
	 */
	public function mark_update_available( int $post_id ): void {
		update_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::REVIEW_STATUS, self::STATUS_UPDATE_AVAILABLE );
	}
}
