<?php
/**
 * Needs Attention issue queue with reconciliation.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages operational issues detected by audits.
 */
class RevIt_Publisher_Issue_Service {

	public const STATUS_OPEN         = 'open';
	public const STATUS_ACKNOWLEDGED = 'acknowledged';
	public const STATUS_RESOLVED     = 'resolved';
	public const STATUS_IGNORED      = 'ignored';

	/**
	 * Reconcile detected issues against existing queue.
	 *
	 * @param array<int, array<string, mixed>> $detected
	 */
	public function reconcile( array $detected ): void {
		$now         = gmdate( 'c' );
		$fingerprints = array();

		foreach ( $detected as $issue ) {
			$fp = $this->fingerprint( $issue );
			$fingerprints[] = $fp;
			$existing = $this->find_by_fingerprint( $fp );

			if ( $existing ) {
				update_post_meta( $existing, RevIt_Publisher_Operations_Meta_Keys::ISSUE_LAST_SEEN, $now );
				if ( self::STATUS_RESOLVED === get_post_meta( $existing, RevIt_Publisher_Operations_Meta_Keys::ISSUE_STATUS, true ) ) {
					update_post_meta( $existing, RevIt_Publisher_Operations_Meta_Keys::ISSUE_STATUS, self::STATUS_OPEN );
					delete_post_meta( $existing, RevIt_Publisher_Operations_Meta_Keys::ISSUE_RESOLVED_AT );
				}
				continue;
			}

			$this->create_issue( $issue, $fp, $now );
		}

		$this->resolve_stale( $fingerprints, $now );
	}

	/**
	 * @param array<string, mixed> $issue
	 */
	private function create_issue( array $issue, string $fp, string $now ): int {
		$type = sanitize_key( (string) ( $issue['issue_type'] ?? 'unknown' ) );
		$title = sprintf( '[%s] %s', strtoupper( $type ), (string) ( $issue['title'] ?? $type ) );

		$post_id = wp_insert_post(
			array(
				'post_type'   => RevIt_Publisher_Operations_Post_Types::ISSUE,
				'post_status' => 'private',
				'post_title'  => sanitize_text_field( $title ),
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return 0;
		}

		$severity = RevIt_Publisher_Severity::for_issue( $type, (array) ( $issue['context'] ?? array() ) );

		update_post_meta( $post_id, RevIt_Publisher_Operations_Meta_Keys::ISSUE_TYPE, $type );
		update_post_meta( $post_id, RevIt_Publisher_Operations_Meta_Keys::ISSUE_SEVERITY, $severity );
		update_post_meta( $post_id, RevIt_Publisher_Operations_Meta_Keys::ISSUE_STATUS, self::STATUS_OPEN );
		update_post_meta( $post_id, RevIt_Publisher_Operations_Meta_Keys::ISSUE_FINGERPRINT, $fp );
		update_post_meta( $post_id, RevIt_Publisher_Operations_Meta_Keys::ISSUE_VEHICLE, sanitize_text_field( (string) ( $issue['vehicle'] ?? '' ) ) );
		update_post_meta( $post_id, RevIt_Publisher_Operations_Meta_Keys::ISSUE_POST_ID, (int) ( $issue['post_id'] ?? 0 ) );
		update_post_meta( $post_id, RevIt_Publisher_Operations_Meta_Keys::ISSUE_ARTICLE_KEY, sanitize_text_field( (string) ( $issue['article_key'] ?? '' ) ) );
		update_post_meta( $post_id, RevIt_Publisher_Operations_Meta_Keys::ISSUE_CLUSTER_KEY, sanitize_text_field( (string) ( $issue['cluster_key'] ?? '' ) ) );
		update_post_meta( $post_id, RevIt_Publisher_Operations_Meta_Keys::ISSUE_EXPLANATION, sanitize_textarea_field( (string) ( $issue['explanation'] ?? '' ) ) );
		update_post_meta( $post_id, RevIt_Publisher_Operations_Meta_Keys::ISSUE_ACTION, sanitize_text_field( (string) ( $issue['recommended_action'] ?? '' ) ) );
		update_post_meta( $post_id, RevIt_Publisher_Operations_Meta_Keys::ISSUE_FIRST_SEEN, $now );
		update_post_meta( $post_id, RevIt_Publisher_Operations_Meta_Keys::ISSUE_LAST_SEEN, $now );
		update_post_meta( $post_id, RevIt_Publisher_Operations_Meta_Keys::ISSUE_CONTEXT, wp_json_encode( $issue['context'] ?? array() ) );

		return (int) $post_id;
	}

	/**
	 * @param string[] $active_fingerprints
	 */
	private function resolve_stale( array $active_fingerprints, string $now ): void {
		$open = get_posts(
			array(
				'post_type'      => RevIt_Publisher_Operations_Post_Types::ISSUE,
				'post_status'    => 'private',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'     => RevIt_Publisher_Operations_Meta_Keys::ISSUE_STATUS,
						'value'   => array( self::STATUS_OPEN, self::STATUS_ACKNOWLEDGED ),
						'compare' => 'IN',
					),
				),
			)
		);

		foreach ( $open as $issue_id ) {
			$fp = (string) get_post_meta( (int) $issue_id, RevIt_Publisher_Operations_Meta_Keys::ISSUE_FINGERPRINT, true );
			if ( ! in_array( $fp, $active_fingerprints, true ) ) {
				update_post_meta( (int) $issue_id, RevIt_Publisher_Operations_Meta_Keys::ISSUE_STATUS, self::STATUS_RESOLVED );
				update_post_meta( (int) $issue_id, RevIt_Publisher_Operations_Meta_Keys::ISSUE_RESOLVED_AT, $now );
			}
		}
	}

	private function find_by_fingerprint( string $fp ): int {
		$posts = get_posts(
			array(
				'post_type'      => RevIt_Publisher_Operations_Post_Types::ISSUE,
				'post_status'    => 'private',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => RevIt_Publisher_Operations_Meta_Keys::ISSUE_FINGERPRINT,
				'meta_value'     => $fp,
			)
		);
		return ! empty( $posts ) ? (int) $posts[0] : 0;
	}

	/**
	 * @param array<string, mixed> $issue
	 */
	private function fingerprint( array $issue ): string {
		$parts = array(
			(string) ( $issue['issue_type'] ?? '' ),
			(string) ( $issue['post_id'] ?? '' ),
			(string) ( $issue['article_key'] ?? '' ),
			(string) ( $issue['cluster_key'] ?? '' ),
			(string) ( $issue['context']['target_article_key'] ?? '' ),
		);
		return hash( 'sha256', implode( '|', $parts ) );
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function list_issues( array $args = array() ): array {
		$status   = sanitize_key( (string) ( $args['status'] ?? '' ) );
		$severity = sanitize_key( (string) ( $args['severity'] ?? '' ) );
		$limit    = max( 1, min( 200, (int) ( $args['limit'] ?? 100 ) ) );

		$meta_query = array();
		if ( '' !== $status ) {
			$meta_query[] = array(
				'key'   => RevIt_Publisher_Operations_Meta_Keys::ISSUE_STATUS,
				'value' => $status,
			);
		} else {
			$meta_query[] = array(
				'key'     => RevIt_Publisher_Operations_Meta_Keys::ISSUE_STATUS,
				'value'   => array( self::STATUS_OPEN, self::STATUS_ACKNOWLEDGED ),
				'compare' => 'IN',
			);
		}
		if ( '' !== $severity ) {
			$meta_query[] = array(
				'key'   => RevIt_Publisher_Operations_Meta_Keys::ISSUE_SEVERITY,
				'value' => $severity,
			);
		}

		$posts = get_posts(
			array(
				'post_type'      => RevIt_Publisher_Operations_Post_Types::ISSUE,
				'post_status'    => 'private',
				'posts_per_page' => $limit,
				'meta_query'     => $meta_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'orderby'        => 'modified',
				'order'          => 'DESC',
			)
		);

		return array_map( array( $this, 'format_issue' ), $posts );
	}

	public function count_open(): int {
		$q = new WP_Query(
			array(
				'post_type'      => RevIt_Publisher_Operations_Post_Types::ISSUE,
				'post_status'    => 'private',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => false,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'     => RevIt_Publisher_Operations_Meta_Keys::ISSUE_STATUS,
						'value'   => array( self::STATUS_OPEN, self::STATUS_ACKNOWLEDGED ),
						'compare' => 'IN',
					),
				),
			)
		);
		return (int) $q->found_posts;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function get_issue( int $issue_id ): ?array {
		$post = get_post( $issue_id );
		if ( ! $post instanceof WP_Post || RevIt_Publisher_Operations_Post_Types::ISSUE !== $post->post_type ) {
			return null;
		}
		return $this->format_issue( $post );
	}

	/**
	 * @return array<string, mixed>|WP_Error
	 */
	public function update_status( int $issue_id, string $status ) {
		$allowed = array( self::STATUS_OPEN, self::STATUS_ACKNOWLEDGED, self::STATUS_RESOLVED, self::STATUS_IGNORED );
		if ( ! in_array( $status, $allowed, true ) ) {
			return new WP_Error( 'revit_invalid_status', __( 'Invalid issue status.', 'revit-publisher' ) );
		}
		$post = get_post( $issue_id );
		if ( ! $post instanceof WP_Post ) {
			return new WP_Error( 'revit_not_found', __( 'Issue not found.', 'revit-publisher' ) );
		}
		update_post_meta( $issue_id, RevIt_Publisher_Operations_Meta_Keys::ISSUE_STATUS, $status );
		if ( self::STATUS_RESOLVED === $status ) {
			update_post_meta( $issue_id, RevIt_Publisher_Operations_Meta_Keys::ISSUE_RESOLVED_AT, gmdate( 'c' ) );
		}
		return $this->get_issue( $issue_id ) ?? array();
	}

	/**
	 * @return array<string, mixed>
	 */
	private function format_issue( WP_Post $post ): array {
		return array(
			'issue_id'            => $post->ID,
			'issue_type'          => (string) get_post_meta( $post->ID, RevIt_Publisher_Operations_Meta_Keys::ISSUE_TYPE, true ),
			'severity'            => (string) get_post_meta( $post->ID, RevIt_Publisher_Operations_Meta_Keys::ISSUE_SEVERITY, true ),
			'status'              => (string) get_post_meta( $post->ID, RevIt_Publisher_Operations_Meta_Keys::ISSUE_STATUS, true ),
			'vehicle'             => (string) get_post_meta( $post->ID, RevIt_Publisher_Operations_Meta_Keys::ISSUE_VEHICLE, true ),
			'post_id'             => (int) get_post_meta( $post->ID, RevIt_Publisher_Operations_Meta_Keys::ISSUE_POST_ID, true ),
			'article_key'         => (string) get_post_meta( $post->ID, RevIt_Publisher_Operations_Meta_Keys::ISSUE_ARTICLE_KEY, true ),
			'cluster_key'         => (string) get_post_meta( $post->ID, RevIt_Publisher_Operations_Meta_Keys::ISSUE_CLUSTER_KEY, true ),
			'explanation'         => (string) get_post_meta( $post->ID, RevIt_Publisher_Operations_Meta_Keys::ISSUE_EXPLANATION, true ),
			'recommended_action'  => (string) get_post_meta( $post->ID, RevIt_Publisher_Operations_Meta_Keys::ISSUE_ACTION, true ),
			'first_detected'      => (string) get_post_meta( $post->ID, RevIt_Publisher_Operations_Meta_Keys::ISSUE_FIRST_SEEN, true ),
			'last_detected'       => (string) get_post_meta( $post->ID, RevIt_Publisher_Operations_Meta_Keys::ISSUE_LAST_SEEN, true ),
			'resolved_at'         => (string) get_post_meta( $post->ID, RevIt_Publisher_Operations_Meta_Keys::ISSUE_RESOLVED_AT, true ),
			'title'               => $post->post_title,
		);
	}
}
