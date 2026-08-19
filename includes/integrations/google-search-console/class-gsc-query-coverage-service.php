<?php
/**
 * Compare Search Console queries to planned topics.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RevIt_Publisher_GSC_Query_Coverage_Service {

	private RevIt_Publisher_GSC_Data_Store $store;

	public function __construct( RevIt_Publisher_GSC_Data_Store $store ) {
		$this->store = $store;
	}

	public function detect_and_reconcile(): void {
		RevIt_Publisher_Services::issues()->reconcile( $this->detect_unexpected_queries() );
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function detect_unexpected_queries( string $window_key = '28d' ): array {
		$issues = array();
		foreach ( RevIt_Publisher_Services::registry()->get_managed_post_ids() as $post_id ) {
			if ( 'publish' !== get_post_status( (int) $post_id ) ) {
				continue;
			}
			$topics = $this->planned_topics( (int) $post_id );
			foreach ( $this->store->get_post_queries( (int) $post_id, $window_key, 20 ) as $query_row ) {
				$query = (string) ( $query_row['query'] ?? '' );
				$impr  = (int) ( $query_row['impressions'] ?? 0 );
				if ( '' === $query || $impr < 500 ) {
					continue;
				}
				if ( $this->matches_topics( $query, $topics ) ) {
					continue;
				}
				$title = get_the_title( (int) $post_id );
				$issues[] = array(
					'issue_type'         => 'gsc_unexpected_query',
					'title'              => $title,
					'post_id'            => (int) $post_id,
					'vehicle'            => RevIt_Publisher_Services::graph()->get_vehicle_label( (int) $post_id ),
					'article_key'        => (string) get_post_meta( (int) $post_id, RevIt_Publisher_Post_Meta_Keys::ARTICLE_KEY, true ),
					'cluster_key'        => (string) get_post_meta( (int) $post_id, RevIt_Publisher_Post_Meta_Keys::CLUSTER_KEY, true ),
					'explanation'        => sprintf( 'Unexpected Google query: "%s" with %d impressions.', $query, $impr ),
					'recommended_action' => 'Review whether the article should address this search intent.',
					'context'            => array(
						'query'       => $query,
						'impressions' => $impr,
						'match_type'  => 'unexpected',
					),
				);
			}
		}
		return $issues;
	}

	/**
	 * @return string[]
	 */
	private function planned_topics( int $post_id ): array {
		$primary   = (string) get_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::PRIMARY_TOPIC, true );
		$secondary = get_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::SECONDARY_TOPICS, true );
		$topics    = array_filter( array( $primary ) );
		if ( is_array( $secondary ) ) {
			$topics = array_merge( $topics, array_map( 'strval', $secondary ) );
		}
		return $topics;
	}

	/**
	 * @param string[] $topics
	 */
	private function matches_topics( string $query, array $topics ): bool {
		$query_tokens = $this->tokens( $query );
		foreach ( $topics as $topic ) {
			$topic_tokens = $this->tokens( (string) $topic );
			if ( empty( $topic_tokens ) ) {
				continue;
			}
			$overlap = count( array_intersect( $query_tokens, $topic_tokens ) );
			if ( $overlap >= max( 1, (int) floor( count( $topic_tokens ) * 0.6 ) ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @return string[]
	 */
	private function tokens( string $text ): array {
		$parts = preg_split( '/\s+/', strtolower( preg_replace( '/[^a-z0-9\s]/', ' ', strtolower( $text ) ) ) ) ?: array();
		return array_values( array_filter( $parts, static fn( string $t ): bool => strlen( $t ) > 2 ) );
	}
}
