<?php
/**
 * Export refresh request JSON for editorial workflow.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RevIt_Publisher_GSC_Refresh_Export {

	/**
	 * @return array<string, mixed>|WP_Error
	 */
	public function export_for_post( int $post_id, string $reason = 'page2_opportunity', ?array $queue_item = null ): array|WP_Error {
		if ( ! RevIt_Publisher_Services::resolver()->is_managed( $post_id ) ) {
			return new WP_Error( 'revit_not_managed', __( 'Post is not RevIt-managed.', 'revit-publisher' ) );
		}

		$metrics = RevIt_Publisher_Services::gsc_data_store()->get_post_metrics( $post_id, '28d' );
		$health  = RevIt_Publisher_Services::seo_score()->analyze( $post_id );
		$vehicle = array(
			'manufacturer' => (string) get_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::VEHICLE_MANUFACTURER, true ),
			'model'        => (string) get_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::VEHICLE_MODEL, true ),
			'generation'   => (string) get_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::VEHICLE_GENERATION, true ),
			'trim'         => (string) get_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::VEHICLE_TRIM, true ),
		);
		$cluster_key = (string) get_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::CLUSTER_KEY, true );
		$secondary   = get_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::SECONDARY_TOPICS, true );
		$stored_hash = (string) get_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::LAST_IMPORT_CONTENT_HASH, true );
		$manual_edit = '' !== $stored_hash
			&& hash( 'sha256', (string) get_post_field( 'post_content', $post_id ) ) !== $stored_hash;

		$export = array(
			'request_type' => 'revit-refresh-request-v1',
			'article_key'  => (string) get_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::ARTICLE_KEY, true ),
			'vehicle'      => $vehicle,
			'reason'       => sanitize_key( $reason ),
			'current_metrics' => array(
				'clicks'      => (int) ( $metrics['clicks'] ?? 0 ),
				'impressions' => (int) ( $metrics['impressions'] ?? 0 ),
				'ctr'         => (float) ( $metrics['ctr'] ?? 0 ),
				'position'    => (float) ( $metrics['position'] ?? 0 ),
			),
			'top_queries'  => RevIt_Publisher_Services::gsc_data_store()->get_post_queries( $post_id, '28d', 5 ),
			'revit_seo_health' => array(
				'total_score' => (int) ( $health['total_score'] ?? 0 ),
				'categories'  => $health['categories'] ?? array(),
			),
			'editorial_context' => array(
				'priority_level'  => (string) ( $queue_item['priority_level'] ?? '' ),
				'priority_score'  => (int) ( $queue_item['priority_score'] ?? 0 ),
				'reasons'         => (array) ( $queue_item['reasons'] ?? array() ),
				'cluster_key'     => $cluster_key,
				'primary_topic'   => (string) get_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::PRIMARY_TOPIC, true ),
				'secondary_topics'=> is_array( $secondary ) ? $secondary : array(),
				'manual_edit_lock'=> $manual_edit,
				'update_constraints' => array(
					'do_not_auto_publish' => true,
					'preserve_manual_edits' => $manual_edit,
				),
			),
		);

		return $export;
	}
}
