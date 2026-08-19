<?php
/**
 * Reconcile editorial queue from detected signals.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RevIt_Publisher_Editorial_Queue_Reconciler {

	/**
	 * @return array<string, mixed>
	 */
	public function reconcile(): array {
		$started = microtime( true );
		$this->reopen_deferred();
		$candidates = RevIt_Publisher_Services::editorial_priority()->detect_candidates();
		$fps = array();

		foreach ( $candidates as $candidate ) {
			$fp = (string) ( $candidate['fingerprint'] ?? '' );
			if ( '' === $fp ) {
				continue;
			}
			$fps[] = $fp;
			RevIt_Publisher_Services::editorial_queue()->upsert_from_candidate( $candidate );
		}

		RevIt_Publisher_Services::editorial_queue()->resolve_stale( $fps );
		$duration = round( microtime( true ) - $started, 3 );

		RevIt_Publisher_Services::event_logger()->log(
			'editorial_reconcile',
			array(
				'candidates' => count( $candidates ),
				'duration'   => $duration,
			)
		);
		RevIt_Publisher_Services::profiler()->record( 'editorial_reconcile', $duration, count( $candidates ) );

		return array(
			'success'    => true,
			'candidates' => count( $candidates ),
			'duration'   => $duration,
		);
	}

	private function reopen_deferred(): void {
		$posts = get_posts(
			array(
				'post_type'      => RevIt_Publisher_Operations_Post_Types::EDITORIAL,
				'post_status'    => 'private',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => RevIt_Publisher_Editorial_Meta_Keys::STATUS,
						'value' => RevIt_Publisher_Editorial_Queue_Service::STATUS_DEFERRED,
					),
				),
			)
		);
		foreach ( $posts as $id ) {
			$until = (string) get_post_meta( (int) $id, RevIt_Publisher_Editorial_Meta_Keys::DEFERRED_UNTIL, true );
			if ( '' !== $until && strtotime( $until ) <= time() ) {
				update_post_meta( (int) $id, RevIt_Publisher_Editorial_Meta_Keys::STATUS, RevIt_Publisher_Editorial_Queue_Service::STATUS_OPEN );
				delete_post_meta( (int) $id, RevIt_Publisher_Editorial_Meta_Keys::DEFERRED_UNTIL );
			}
		}
	}
}
