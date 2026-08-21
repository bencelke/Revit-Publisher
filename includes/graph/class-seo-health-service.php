<?php
/**
 * SEO health signal detection.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Detects individual SEO health signals for RevIt-managed articles.
 */
class RevIt_Publisher_SEO_Health_Service {

	/**
	 * Content graph.
	 *
	 * @var RevIt_Publisher_Content_Graph
	 */
	private RevIt_Publisher_Content_Graph $graph;

	/**
	 * Internal link service.
	 *
	 * @var RevIt_Publisher_Internal_Link_Service
	 */
	private RevIt_Publisher_Internal_Link_Service $link_service;

	/**
	 * Topic normalizer.
	 *
	 * @var RevIt_Publisher_Topic_Normalizer
	 */
	private RevIt_Publisher_Topic_Normalizer $topic_normalizer;

	/**
	 * Constructor.
	 */
	public function __construct(
		RevIt_Publisher_Content_Graph $graph,
		RevIt_Publisher_Internal_Link_Service $link_service,
		RevIt_Publisher_Topic_Normalizer $topic_normalizer
	) {
		$this->graph            = $graph;
		$this->link_service     = $link_service;
		$this->topic_normalizer = $topic_normalizer;
	}

	/**
	 * Get health signals for one post.
	 *
	 * @return array<string, mixed>
	 */
	public function get_post_health( int $post_id ): array {
		$inbound_resolved = $this->count_resolved_inbound( $post_id );

		return array(
			'post_id'              => $post_id,
			'is_orphan'            => 0 === $inbound_resolved,
			'unresolved_links'     => count( $this->graph->get_unresolved_links( $post_id ) ),
			'missing_pillar'       => $this->has_missing_pillar( $post_id ),
			'missing_vehicle'      => '' === $this->graph->get_vehicle_label( $post_id ),
			'missing_seo_title'    => '' === (string) get_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::SEO_TITLE, true ),
			'missing_meta_description' => '' === (string) get_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::META_DESCRIPTION, true ),
			'missing_primary_topic'=> '' === (string) get_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::PRIMARY_TOPIC, true ),
		);
	}

	/**
	 * Aggregate dashboard health metrics.
	 *
	 * @return array<string, int>
	 */
	public function get_summary(): array {
		$post_ids = $this->get_managed_post_ids();
		$summary  = array(
			'revit_articles'     => count( $post_ids ),
			'orphan_articles'    => 0,
			'unresolved_links'   => 0,
			'missing_pillars'    => 0,
			'missing_meta'       => 0,
			'duplicate_topics'   => count( $this->find_duplicate_topics() ),
		);

		foreach ( $post_ids as $post_id ) {
			$health = $this->get_post_health( $post_id );
			if ( ! empty( $health['is_orphan'] ) ) {
				++$summary['orphan_articles'];
			}
			$summary['unresolved_links'] += (int) $health['unresolved_links'];
			if ( ! empty( $health['missing_pillar'] ) ) {
				++$summary['missing_pillars'];
			}
			if ( ! empty( $health['missing_seo_title'] ) || ! empty( $health['missing_meta_description'] ) || ! empty( $health['missing_primary_topic'] ) ) {
				++$summary['missing_meta'];
			}
		}

		return $summary;
	}

	/**
	 * Get orphan posts.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_orphans(): array {
		$orphans = array();
		foreach ( $this->get_managed_post_ids() as $post_id ) {
			$health = $this->get_post_health( $post_id );
			if ( ! empty( $health['is_orphan'] ) ) {
				$orphans[] = array(
					'post_id' => $post_id,
					'title'   => get_the_title( $post_id ),
					'edit_url'=> get_edit_post_link( $post_id, 'raw' ),
				);
			}
		}

		return $orphans;
	}

	/**
	 * Find duplicate normalized primary topics.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function find_duplicate_topics(): array {
		$map       = array();
		$duplicates = array();

		foreach ( $this->get_managed_post_ids() as $post_id ) {
			$topic = (string) get_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::PRIMARY_TOPIC, true );
			if ( '' === $topic ) {
				continue;
			}

			$key = $this->topic_normalizer->normalize( $topic );
			if ( '' === $key ) {
				continue;
			}

			if ( isset( $map[ $key ] ) ) {
				$duplicates[] = array(
					'normalized_topic' => $key,
					'post_ids'         => array( $map[ $key ], $post_id ),
				);
			} else {
				$map[ $key ] = $post_id;
			}
		}

		return $duplicates;
	}

	/**
	 * Compare package hash for update foundation.
	 *
	 * @return array{status: string, stored_hash: string, incoming_hash: string}
	 */
	public function compare_package_hash( int $post_id, mixed $incoming_package ): array {
		$hash_service = new RevIt_Publisher_Package_Hash();
		$incoming     = $hash_service->compute( $incoming_package );
		$stored       = (string) get_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::PACKAGE_HASH, true );

		return array(
			'status'        => ( $stored === $incoming ) ? 'unchanged' : 'changed',
			'stored_hash'   => $stored,
			'incoming_hash' => $incoming,
		);
	}

	/**
	 * Count resolved inbound contextual links (applied or in content).
	 */
	private function count_resolved_inbound( int $post_id ): int {
		$scan = ( new RevIt_Publisher_Post_Content_Scanner() )->scan_post( $post_id );
		return (int) ( $scan['inbound_count'] ?? 0 );
	}

	/**
	 * Whether post has planned but missing pillar.
	 */
	private function has_missing_pillar( int $post_id ): bool {
		$pillar_key = (string) get_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::PILLAR_ARTICLE_KEY, true );
		if ( '' === $pillar_key ) {
			return false;
		}

		$pillar = $this->graph->get_pillar_article( $post_id );
		return is_array( $pillar ) && 'pillar_planned' === ( $pillar['status'] ?? '' );
	}

	/**
	 * @return int[]
	 */
	private function get_managed_post_ids(): array {
		$posts = get_posts(
			array(
				'post_type'      => 'post',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => RevIt_Publisher_Post_Meta_Keys::MANAGED,
						'value' => '1',
					),
				),
			)
		);

		return array_map( 'intval', $posts );
	}
}
