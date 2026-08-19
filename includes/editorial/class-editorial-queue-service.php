<?php
/**
 * Editorial queue storage and lifecycle.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RevIt_Publisher_Editorial_Queue_Service {

	public const STATUS_OPEN        = 'open';
	public const STATUS_IN_PROGRESS = 'in_progress';
	public const STATUS_DEFERRED    = 'deferred';
	public const STATUS_COMPLETED   = 'completed';
	public const STATUS_DISMISSED   = 'dismissed';

	/**
	 * @param array<string, mixed> $filters
	 * @return array<int, array<string, mixed>>
	 */
	public function list_items( array $filters = array() ): array {
		$meta_query = array( 'relation' => 'AND' );
		if ( ! empty( $filters['status'] ) ) {
			$meta_query[] = array(
				'key'   => RevIt_Publisher_Editorial_Meta_Keys::STATUS,
				'value' => sanitize_key( (string) $filters['status'] ),
			);
		}
		if ( ! empty( $filters['action_type'] ) ) {
			$meta_query[] = array(
				'key'   => RevIt_Publisher_Editorial_Meta_Keys::ACTION_TYPE,
				'value' => sanitize_key( (string) $filters['action_type'] ),
			);
		}
		if ( ! empty( $filters['vehicle'] ) ) {
			$meta_query[] = array(
				'key'   => RevIt_Publisher_Editorial_Meta_Keys::VEHICLE,
				'value' => sanitize_text_field( (string) $filters['vehicle'] ),
			);
		}

		$query = array(
			'post_type'      => RevIt_Publisher_Operations_Post_Types::EDITORIAL,
			'post_status'    => 'private',
			'posts_per_page' => (int) ( $filters['limit'] ?? 100 ),
			'orderby'        => 'meta_value_num',
			'meta_key'       => RevIt_Publisher_Editorial_Meta_Keys::PRIORITY_SCORE,
			'order'          => 'DESC',
		);
		if ( count( $meta_query ) > 1 ) {
			$query['meta_query'] = $meta_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		}

		$items = array();
		foreach ( get_posts( $query ) as $post ) {
			$item = $this->format_item( (int) $post->ID );
			if ( null === $item ) {
				continue;
			}
			if ( ! empty( $filters['cluster'] ) && ( $item['cluster_key'] ?? '' ) !== $filters['cluster'] ) {
				continue;
			}
			if ( ! empty( $filters['priority'] ) && ( $item['priority_level'] ?? '' ) !== $filters['priority'] ) {
				continue;
			}
			if ( ! empty( $filters['today'] ) && ! $this->is_visible_today( $item ) ) {
				continue;
			}
			$items[] = $item;
		}
		return $items;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function get_item( int $item_id ): ?array {
		$post = get_post( $item_id );
		if ( ! $post || RevIt_Publisher_Operations_Post_Types::EDITORIAL !== $post->post_type ) {
			return null;
		}
		return $this->format_item( $item_id );
	}

	/**
	 * @param array<string, mixed> $data
	 */
	public function create_manual( array $data ): int|WP_Error {
		$title = sanitize_text_field( (string) ( $data['title'] ?? '' ) );
		if ( '' === $title ) {
			return new WP_Error( 'revit_editorial_title', __( 'Title is required.', 'revit-publisher' ) );
		}
		$action = sanitize_key( (string) ( $data['action_type'] ?? RevIt_Publisher_Editorial_Priority_Service::ACTION_REVIEW ) );
		$level  = sanitize_key( (string) ( $data['priority_level'] ?? RevIt_Publisher_Editorial_Priority_Service::LEVEL_MEDIUM ) );
		$now    = gmdate( 'c' );

		$post_id = wp_insert_post(
			array(
				'post_type'   => RevIt_Publisher_Operations_Post_Types::EDITORIAL,
				'post_status' => 'private',
				'post_title'  => $title,
			),
			true
		);
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$vehicle = sanitize_text_field( (string) ( $data['vehicle'] ?? '' ) );
		$article_post = (int) ( $data['post_id'] ?? 0 );
		$article_key  = sanitize_text_field( (string) ( $data['article_key'] ?? '' ) );
		$cluster      = sanitize_text_field( (string) ( $data['cluster_key'] ?? '' ) );
		$fp = RevIt_Publisher_Services::editorial_priority()->fingerprint(
			$action,
			$article_post,
			$article_key,
			$cluster,
			$vehicle,
			0
		) . '_manual_' . wp_generate_password( 8, false );

		update_post_meta( $post_id, RevIt_Publisher_Editorial_Meta_Keys::ACTION_TYPE, $action );
		update_post_meta( $post_id, RevIt_Publisher_Editorial_Meta_Keys::PRIORITY_LEVEL, $level );
		update_post_meta( $post_id, RevIt_Publisher_Editorial_Meta_Keys::PRIORITY_SCORE, (int) ( $data['priority_score'] ?? 50 ) );
		update_post_meta( $post_id, RevIt_Publisher_Editorial_Meta_Keys::STATUS, self::STATUS_OPEN );
		update_post_meta( $post_id, RevIt_Publisher_Editorial_Meta_Keys::FINGERPRINT, $fp );
		update_post_meta( $post_id, RevIt_Publisher_Editorial_Meta_Keys::VEHICLE, $vehicle );
		update_post_meta( $post_id, RevIt_Publisher_Editorial_Meta_Keys::POST_ID, $article_post );
		update_post_meta( $post_id, RevIt_Publisher_Editorial_Meta_Keys::ARTICLE_KEY, $article_key );
		update_post_meta( $post_id, RevIt_Publisher_Editorial_Meta_Keys::CLUSTER_KEY, $cluster );
		update_post_meta( $post_id, RevIt_Publisher_Editorial_Meta_Keys::EXPLANATION, sanitize_textarea_field( (string) ( $data['notes'] ?? '' ) ) );
		update_post_meta( $post_id, RevIt_Publisher_Editorial_Meta_Keys::NOTES, sanitize_textarea_field( (string) ( $data['notes'] ?? '' ) ) );
		update_post_meta( $post_id, RevIt_Publisher_Editorial_Meta_Keys::MANUAL, '1' );
		update_post_meta( $post_id, RevIt_Publisher_Editorial_Meta_Keys::CREATED_AT, $now );
		update_post_meta( $post_id, RevIt_Publisher_Editorial_Meta_Keys::UPDATED_AT, $now );
		if ( ! empty( $data['due_date'] ) ) {
			update_post_meta( $post_id, RevIt_Publisher_Editorial_Meta_Keys::DUE_DATE, sanitize_text_field( (string) $data['due_date'] ) );
		}

		RevIt_Publisher_Services::event_logger()->log( 'editorial_create', array( 'item_id' => (int) $post_id ) );
		return (int) $post_id;
	}

	/**
	 * @param array<string, mixed> $updates
	 */
	public function update_item( int $item_id, array $updates ): array|WP_Error {
		$item = $this->get_item( $item_id );
		if ( null === $item ) {
			return new WP_Error( 'revit_editorial_not_found', __( 'Queue item not found.', 'revit-publisher' ) );
		}

		$now = gmdate( 'c' );
		$status = sanitize_key( (string) ( $updates['status'] ?? $item['status'] ) );

		update_post_meta( $item_id, RevIt_Publisher_Editorial_Meta_Keys::STATUS, $status );
		update_post_meta( $item_id, RevIt_Publisher_Editorial_Meta_Keys::UPDATED_AT, $now );

		if ( ! empty( $updates['notes'] ) ) {
			update_post_meta( $item_id, RevIt_Publisher_Editorial_Meta_Keys::NOTES, sanitize_textarea_field( (string) $updates['notes'] ) );
		}

		if ( self::STATUS_DEFERRED === $status ) {
			$until = (string) ( $updates['deferred_until'] ?? gmdate( 'Y-m-d', strtotime( '+7 days' ) ) );
			update_post_meta( $item_id, RevIt_Publisher_Editorial_Meta_Keys::DEFERRED_UNTIL, sanitize_text_field( $until ) );
		}

		if ( self::STATUS_COMPLETED === $status || self::STATUS_DISMISSED === $status ) {
			update_post_meta( $item_id, RevIt_Publisher_Editorial_Meta_Keys::COMPLETED_AT, $now );
			$cooldown_days = RevIt_Publisher_Services::settings()->editorial_cooldown_days();
			update_post_meta(
				$item_id,
				RevIt_Publisher_Editorial_Meta_Keys::COOLDOWN_UNTIL,
				gmdate( 'Y-m-d', strtotime( '+' . $cooldown_days . ' days' ) )
			);
			RevIt_Publisher_Services::event_logger()->log(
				'editorial_' . $status,
				array( 'item_id' => $item_id, 'fingerprint' => (string) ( $item['fingerprint'] ?? '' ) )
			);
		}

		return $this->get_item( $item_id ) ?? array();
	}

	public function count_by_priority( string $tab = 'today' ): array {
		$counts = array(
			RevIt_Publisher_Editorial_Priority_Service::LEVEL_URGENT => 0,
			RevIt_Publisher_Editorial_Priority_Service::LEVEL_HIGH   => 0,
			RevIt_Publisher_Editorial_Priority_Service::LEVEL_MEDIUM => 0,
			RevIt_Publisher_Editorial_Priority_Service::LEVEL_LOW    => 0,
		);
		foreach ( $this->list_items( array( 'today' => 'today' === $tab ) ) as $item ) {
			$level = (string) ( $item['priority_level'] ?? '' );
			if ( isset( $counts[ $level ] ) && in_array( $item['status'] ?? '', array( self::STATUS_OPEN, self::STATUS_IN_PROGRESS ), true ) ) {
				++$counts[ $level ];
			}
		}
		return $counts;
	}

	/**
	 * @param array<string, mixed> $candidate
	 */
	public function upsert_from_candidate( array $candidate ): int {
		$fp = (string) ( $candidate['fingerprint'] ?? '' );
		$existing = $this->find_by_fingerprint( $fp );
		$now = gmdate( 'c' );

		if ( $existing ) {
			$cooldown = (string) get_post_meta( $existing, RevIt_Publisher_Editorial_Meta_Keys::COOLDOWN_UNTIL, true );
			$status   = (string) get_post_meta( $existing, RevIt_Publisher_Editorial_Meta_Keys::STATUS, true );
			if ( self::STATUS_COMPLETED === $status || self::STATUS_DISMISSED === $status ) {
				if ( '' !== $cooldown && strtotime( $cooldown ) > time() ) {
					return $existing;
				}
				update_post_meta( $existing, RevIt_Publisher_Editorial_Meta_Keys::STATUS, self::STATUS_OPEN );
				delete_post_meta( $existing, RevIt_Publisher_Editorial_Meta_Keys::COMPLETED_AT );
				delete_post_meta( $existing, RevIt_Publisher_Editorial_Meta_Keys::COOLDOWN_UNTIL );
			}
			update_post_meta( $existing, RevIt_Publisher_Editorial_Meta_Keys::PRIORITY_SCORE, (int) ( $candidate['priority_score'] ?? 0 ) );
			update_post_meta( $existing, RevIt_Publisher_Editorial_Meta_Keys::PRIORITY_LEVEL, (string) ( $candidate['priority_level'] ?? '' ) );
			update_post_meta( $existing, RevIt_Publisher_Editorial_Meta_Keys::REASONS, wp_json_encode( $candidate['reasons'] ?? array() ) );
			update_post_meta( $existing, RevIt_Publisher_Editorial_Meta_Keys::SIGNALS, wp_json_encode( $candidate['signals'] ?? array() ) );
			update_post_meta( $existing, RevIt_Publisher_Editorial_Meta_Keys::EXPLANATION, sanitize_textarea_field( (string) ( $candidate['explanation'] ?? '' ) ) );
			update_post_meta( $existing, RevIt_Publisher_Editorial_Meta_Keys::NEXT_STEP, sanitize_text_field( (string) ( $candidate['next_step'] ?? '' ) ) );
			update_post_meta( $existing, RevIt_Publisher_Editorial_Meta_Keys::UPDATED_AT, $now );
			return $existing;
		}

		$title = sprintf(
			'[%s] %s',
			strtoupper( str_replace( '_', ' ', (string) ( $candidate['action_type'] ?? 'task' ) ) ),
			(string) ( $candidate['title'] ?? 'Editorial task' )
		);

		$post_id = wp_insert_post(
			array(
				'post_type'   => RevIt_Publisher_Operations_Post_Types::EDITORIAL,
				'post_status' => 'private',
				'post_title'  => sanitize_text_field( $title ),
			),
			true
		);
		if ( is_wp_error( $post_id ) ) {
			return 0;
		}

		update_post_meta( $post_id, RevIt_Publisher_Editorial_Meta_Keys::ACTION_TYPE, sanitize_key( (string) ( $candidate['action_type'] ?? '' ) ) );
		update_post_meta( $post_id, RevIt_Publisher_Editorial_Meta_Keys::PRIORITY_LEVEL, sanitize_key( (string) ( $candidate['priority_level'] ?? '' ) ) );
		update_post_meta( $post_id, RevIt_Publisher_Editorial_Meta_Keys::PRIORITY_SCORE, (int) ( $candidate['priority_score'] ?? 0 ) );
		update_post_meta( $post_id, RevIt_Publisher_Editorial_Meta_Keys::STATUS, self::STATUS_OPEN );
		update_post_meta( $post_id, RevIt_Publisher_Editorial_Meta_Keys::FINGERPRINT, $fp );
		update_post_meta( $post_id, RevIt_Publisher_Editorial_Meta_Keys::VEHICLE, sanitize_text_field( (string) ( $candidate['vehicle'] ?? '' ) ) );
		update_post_meta( $post_id, RevIt_Publisher_Editorial_Meta_Keys::POST_ID, (int) ( $candidate['post_id'] ?? 0 ) );
		update_post_meta( $post_id, RevIt_Publisher_Editorial_Meta_Keys::ARTICLE_KEY, sanitize_text_field( (string) ( $candidate['article_key'] ?? '' ) ) );
		update_post_meta( $post_id, RevIt_Publisher_Editorial_Meta_Keys::CLUSTER_KEY, sanitize_text_field( (string) ( $candidate['cluster_key'] ?? '' ) ) );
		update_post_meta( $post_id, RevIt_Publisher_Editorial_Meta_Keys::PLAN_ID, (int) ( $candidate['plan_id'] ?? 0 ) );
		update_post_meta( $post_id, RevIt_Publisher_Editorial_Meta_Keys::EXPLANATION, sanitize_textarea_field( (string) ( $candidate['explanation'] ?? '' ) ) );
		update_post_meta( $post_id, RevIt_Publisher_Editorial_Meta_Keys::REASONS, wp_json_encode( $candidate['reasons'] ?? array() ) );
		update_post_meta( $post_id, RevIt_Publisher_Editorial_Meta_Keys::SIGNALS, wp_json_encode( $candidate['signals'] ?? array() ) );
		update_post_meta( $post_id, RevIt_Publisher_Editorial_Meta_Keys::NEXT_STEP, sanitize_text_field( (string) ( $candidate['next_step'] ?? '' ) ) );
		update_post_meta( $post_id, RevIt_Publisher_Editorial_Meta_Keys::TITLE_LABEL, sanitize_text_field( (string) ( $candidate['title'] ?? '' ) ) );
		update_post_meta( $post_id, RevIt_Publisher_Editorial_Meta_Keys::MANUAL, '0' );
		update_post_meta( $post_id, RevIt_Publisher_Editorial_Meta_Keys::CREATED_AT, $now );
		update_post_meta( $post_id, RevIt_Publisher_Editorial_Meta_Keys::UPDATED_AT, $now );

		return (int) $post_id;
	}

	public function resolve_stale( array $active_fingerprints ): void {
		$posts = get_posts(
			array(
				'post_type'      => RevIt_Publisher_Operations_Post_Types::EDITORIAL,
				'post_status'    => 'private',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => RevIt_Publisher_Editorial_Meta_Keys::STATUS,
						'value' => array( self::STATUS_OPEN, self::STATUS_IN_PROGRESS ),
						'compare' => 'IN',
					),
					array(
						'key'   => RevIt_Publisher_Editorial_Meta_Keys::MANUAL,
						'value' => '0',
					),
				),
			)
		);
		foreach ( $posts as $id ) {
			$fp = (string) get_post_meta( (int) $id, RevIt_Publisher_Editorial_Meta_Keys::FINGERPRINT, true );
			if ( ! in_array( $fp, $active_fingerprints, true ) ) {
				update_post_meta( (int) $id, RevIt_Publisher_Editorial_Meta_Keys::STATUS, self::STATUS_COMPLETED );
				update_post_meta( (int) $id, RevIt_Publisher_Editorial_Meta_Keys::COMPLETED_AT, gmdate( 'c' ) );
			}
		}
	}

	private function find_by_fingerprint( string $fp ): int {
		$posts = get_posts(
			array(
				'post_type'      => RevIt_Publisher_Operations_Post_Types::EDITORIAL,
				'post_status'    => 'private',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => RevIt_Publisher_Editorial_Meta_Keys::FINGERPRINT,
						'value' => $fp,
					),
				),
			)
		);
		return (int) ( $posts[0] ?? 0 );
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private function format_item( int $item_id ): ?array {
		$post = get_post( $item_id );
		if ( ! $post ) {
			return null;
		}
		$reasons = json_decode( (string) get_post_meta( $item_id, RevIt_Publisher_Editorial_Meta_Keys::REASONS, true ), true );
		$signals = json_decode( (string) get_post_meta( $item_id, RevIt_Publisher_Editorial_Meta_Keys::SIGNALS, true ), true );
		$post_id = (int) get_post_meta( $item_id, RevIt_Publisher_Editorial_Meta_Keys::POST_ID, true );

		return array(
			'id'             => $item_id,
			'title'          => (string) get_post_meta( $item_id, RevIt_Publisher_Editorial_Meta_Keys::TITLE_LABEL, true ) ?: $post->post_title,
			'action_type'    => (string) get_post_meta( $item_id, RevIt_Publisher_Editorial_Meta_Keys::ACTION_TYPE, true ),
			'priority_level' => (string) get_post_meta( $item_id, RevIt_Publisher_Editorial_Meta_Keys::PRIORITY_LEVEL, true ),
			'priority_score' => (int) get_post_meta( $item_id, RevIt_Publisher_Editorial_Meta_Keys::PRIORITY_SCORE, true ),
			'status'         => (string) get_post_meta( $item_id, RevIt_Publisher_Editorial_Meta_Keys::STATUS, true ),
			'fingerprint'    => (string) get_post_meta( $item_id, RevIt_Publisher_Editorial_Meta_Keys::FINGERPRINT, true ),
			'vehicle'        => (string) get_post_meta( $item_id, RevIt_Publisher_Editorial_Meta_Keys::VEHICLE, true ),
			'post_id'        => $post_id,
			'article_key'    => (string) get_post_meta( $item_id, RevIt_Publisher_Editorial_Meta_Keys::ARTICLE_KEY, true ),
			'cluster_key'    => (string) get_post_meta( $item_id, RevIt_Publisher_Editorial_Meta_Keys::CLUSTER_KEY, true ),
			'plan_id'        => (int) get_post_meta( $item_id, RevIt_Publisher_Editorial_Meta_Keys::PLAN_ID, true ),
			'explanation'    => (string) get_post_meta( $item_id, RevIt_Publisher_Editorial_Meta_Keys::EXPLANATION, true ),
			'reasons'        => is_array( $reasons ) ? $reasons : array(),
			'signals'        => is_array( $signals ) ? $signals : array(),
			'next_step'      => (string) get_post_meta( $item_id, RevIt_Publisher_Editorial_Meta_Keys::NEXT_STEP, true ),
			'notes'          => (string) get_post_meta( $item_id, RevIt_Publisher_Editorial_Meta_Keys::NOTES, true ),
			'deferred_until' => (string) get_post_meta( $item_id, RevIt_Publisher_Editorial_Meta_Keys::DEFERRED_UNTIL, true ),
			'completed_at'   => (string) get_post_meta( $item_id, RevIt_Publisher_Editorial_Meta_Keys::COMPLETED_AT, true ),
			'manual'         => '1' === get_post_meta( $item_id, RevIt_Publisher_Editorial_Meta_Keys::MANUAL, true ),
			'due_date'       => (string) get_post_meta( $item_id, RevIt_Publisher_Editorial_Meta_Keys::DUE_DATE, true ),
			'created_at'     => (string) get_post_meta( $item_id, RevIt_Publisher_Editorial_Meta_Keys::CREATED_AT, true ),
			'updated_at'     => (string) get_post_meta( $item_id, RevIt_Publisher_Editorial_Meta_Keys::UPDATED_AT, true ),
			'edit_url'       => $post_id > 0 ? get_edit_post_link( $post_id, 'raw' ) : null,
			'gsc_metrics'    => is_array( $signals ) ? ( $signals['gsc'] ?? null ) : null,
		);
	}

	/**
	 * @param array<string, mixed> $item
	 */
	private function is_visible_today( array $item ): bool {
		$status = (string) ( $item['status'] ?? '' );
		if ( self::STATUS_DEFERRED === $status ) {
			$until = (string) ( $item['deferred_until'] ?? '' );
			return '' !== $until && strtotime( $until ) <= time();
		}
		return in_array( $status, array( self::STATUS_OPEN, self::STATUS_IN_PROGRESS ), true );
	}
}
