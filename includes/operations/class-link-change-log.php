<?php
/**
 * Internal link change log for auditing and undo.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Records RevIt-applied link modifications.
 */
class RevIt_Publisher_Link_Change_Log {

	public const ACTION_APPLIED        = 'link_applied';
	public const ACTION_BATCH          = 'batch_link_applied';
	public const ACTION_CLUSTER        = 'cluster_link_applied';
	public const ACTION_REMOVED          = 'link_removed';

	/**
	 * Record a link change.
	 *
	 * @return int Log entry post ID.
	 */
	public function record(
		int $source_post_id,
		int $target_post_id,
		string $action,
		string $anchor,
		string $relationship,
		string $pre_hash,
		string $post_hash
	): int {
		$title = sprintf(
			'%s → %s (%s)',
			get_the_title( $source_post_id ) ?: (string) $source_post_id,
			get_the_title( $target_post_id ) ?: (string) $target_post_id,
			$action
		);

		$log_id = wp_insert_post(
			array(
				'post_type'   => RevIt_Publisher_Operations_Post_Types::LINK_CHANGE,
				'post_status' => 'private',
				'post_title'  => sanitize_text_field( $title ),
			),
			true
		);

		if ( is_wp_error( $log_id ) ) {
			return 0;
		}

		update_post_meta( $log_id, RevIt_Publisher_Operations_Meta_Keys::LINK_LOG_SOURCE, $source_post_id );
		update_post_meta( $log_id, RevIt_Publisher_Operations_Meta_Keys::LINK_LOG_TARGET, $target_post_id );
		update_post_meta( $log_id, RevIt_Publisher_Operations_Meta_Keys::LINK_LOG_ACTION, sanitize_key( $action ) );
		update_post_meta( $log_id, RevIt_Publisher_Operations_Meta_Keys::LINK_LOG_ANCHOR, sanitize_text_field( $anchor ) );
		update_post_meta( $log_id, RevIt_Publisher_Operations_Meta_Keys::LINK_LOG_RELATION, sanitize_key( $relationship ) );
		update_post_meta( $log_id, RevIt_Publisher_Operations_Meta_Keys::LINK_LOG_PRE_HASH, $pre_hash );
		update_post_meta( $log_id, RevIt_Publisher_Operations_Meta_Keys::LINK_LOG_POST_HASH, $post_hash );
		update_post_meta( $log_id, RevIt_Publisher_Operations_Meta_Keys::LINK_LOG_USER, get_current_user_id() );
		update_post_meta( $log_id, RevIt_Publisher_Operations_Meta_Keys::LINK_LOG_UNDONE, '0' );

		return (int) $log_id;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function list_for_post( int $post_id, int $limit = 20 ): array {
		$posts = get_posts(
			array(
				'post_type'      => RevIt_Publisher_Operations_Post_Types::LINK_CHANGE,
				'post_status'    => 'private',
				'posts_per_page' => $limit,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => RevIt_Publisher_Operations_Meta_Keys::LINK_LOG_SOURCE,
						'value' => $post_id,
					),
					array(
						'key'   => RevIt_Publisher_Operations_Meta_Keys::LINK_LOG_UNDONE,
						'value' => '0',
					),
				),
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);

		return array_map( array( $this, 'format_entry' ), $posts );
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function get_entry( int $log_id ): ?array {
		$post = get_post( $log_id );
		if ( ! $post instanceof WP_Post || RevIt_Publisher_Operations_Post_Types::LINK_CHANGE !== $post->post_type ) {
			return null;
		}
		return $this->format_entry( $post );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function format_entry( WP_Post $post ): array {
		return array(
			'log_id'        => $post->ID,
			'source_post_id'=> (int) get_post_meta( $post->ID, RevIt_Publisher_Operations_Meta_Keys::LINK_LOG_SOURCE, true ),
			'target_post_id'=> (int) get_post_meta( $post->ID, RevIt_Publisher_Operations_Meta_Keys::LINK_LOG_TARGET, true ),
			'action'        => (string) get_post_meta( $post->ID, RevIt_Publisher_Operations_Meta_Keys::LINK_LOG_ACTION, true ),
			'anchor'        => (string) get_post_meta( $post->ID, RevIt_Publisher_Operations_Meta_Keys::LINK_LOG_ANCHOR, true ),
			'relationship'  => (string) get_post_meta( $post->ID, RevIt_Publisher_Operations_Meta_Keys::LINK_LOG_RELATION, true ),
			'pre_hash'      => (string) get_post_meta( $post->ID, RevIt_Publisher_Operations_Meta_Keys::LINK_LOG_PRE_HASH, true ),
			'post_hash'     => (string) get_post_meta( $post->ID, RevIt_Publisher_Operations_Meta_Keys::LINK_LOG_POST_HASH, true ),
			'undone'        => '1' === get_post_meta( $post->ID, RevIt_Publisher_Operations_Meta_Keys::LINK_LOG_UNDONE, true ),
			'created_at'    => $post->post_date_gmt,
		);
	}

	public function mark_undone( int $log_id ): void {
		update_post_meta( $log_id, RevIt_Publisher_Operations_Meta_Keys::LINK_LOG_UNDONE, '1' );
	}
}
