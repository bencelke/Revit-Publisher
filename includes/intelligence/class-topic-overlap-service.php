<?php
/**
 * Topic overlap / cannibalization analysis.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Detects potential topic overlap between RevIt-managed articles.
 */
class RevIt_Publisher_Topic_Overlap_Service {

	private RevIt_Publisher_Topic_Fingerprint $fingerprint;
	private const CACHE_GROUP = 'revit_publisher';
	private const CACHE_KEY   = 'revit_topic_overlaps';

	public function __construct( RevIt_Publisher_Topic_Fingerprint $fingerprint ) {
		$this->fingerprint = $fingerprint;
	}

	/**
	 * Find potential overlaps site-wide (cached).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function find_overlaps( bool $refresh = false ): array {
		if ( ! $refresh ) {
			$cached = get_transient( self::CACHE_KEY );
			if ( is_array( $cached ) ) {
				return $cached;
			}
		}

		$posts = $this->get_managed_posts();
		$pairs = array();

		$count = count( $posts );
		for ( $i = 0; $i < $count; ++$i ) {
			for ( $j = $i + 1; $j < $count; ++$j ) {
				$a = $posts[ $i ];
				$b = $posts[ $j ];

				$topic_a = (string) get_post_meta( $a, RevIt_Publisher_Post_Meta_Keys::PRIMARY_TOPIC, true );
				$topic_b = (string) get_post_meta( $b, RevIt_Publisher_Post_Meta_Keys::PRIMARY_TOPIC, true );
				if ( '' === $topic_a || '' === $topic_b ) {
					continue;
				}

				$classification = $this->fingerprint->classify( $topic_a, $topic_b );
				if ( 'distinct' === $classification ) {
					continue;
				}

				$similarity = $this->fingerprint->similarity( $topic_a, $topic_b );
				$context    = $this->build_context( $a, $b );

				$pairs[] = array(
					'post_id_a'       => $a,
					'post_id_b'       => $b,
					'title_a'         => get_the_title( $a ),
					'title_b'         => get_the_title( $b ),
					'topic_a'         => $topic_a,
					'topic_b'         => $topic_b,
					'classification'  => $classification,
					'overlap_pct'     => (int) round( $similarity * 100 ),
					'same_vehicle'    => $context['same_vehicle'],
					'same_cluster'    => $context['same_cluster'],
					'same_intent'     => $context['same_intent'],
					'same_type'       => $context['same_type'],
					'risk'            => $this->calculate_risk( $classification, $context ),
				);
			}
		}

		usort(
			$pairs,
			static fn( array $x, array $y ): int => ( $y['overlap_pct'] ?? 0 ) <=> ( $x['overlap_pct'] ?? 0 )
		);

		set_transient( self::CACHE_KEY, $pairs, HOUR_IN_SECONDS );

		return $pairs;
	}

	/**
	 * Overlaps for a single post.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_post_overlaps( int $post_id ): array {
		return array_values(
			array_filter(
				$this->find_overlaps(),
				static fn( array $pair ): bool => (int) ( $pair['post_id_a'] ?? 0 ) === $post_id || (int) ( $pair['post_id_b'] ?? 0 ) === $post_id
			)
		);
	}

	public function invalidate_cache(): void {
		delete_transient( self::CACHE_KEY );
	}

	/**
	 * @return int[]
	 */
	private function get_managed_posts(): array {
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

	/**
	 * @return array{same_vehicle: bool, same_cluster: bool, same_intent: bool, same_type: bool}
	 */
	private function build_context( int $post_a, int $post_b ): array {
		$graph = RevIt_Publisher_Services::graph();

		$vehicle_a = $graph->get_vehicle_label( $post_a );
		$vehicle_b = $graph->get_vehicle_label( $post_b );

		$cluster_a = (string) get_post_meta( $post_a, RevIt_Publisher_Post_Meta_Keys::CLUSTER_KEY, true );
		$cluster_b = (string) get_post_meta( $post_b, RevIt_Publisher_Post_Meta_Keys::CLUSTER_KEY, true );

		$intent_a = (string) get_post_meta( $post_a, RevIt_Publisher_Post_Meta_Keys::SEARCH_INTENT, true );
		$intent_b = (string) get_post_meta( $post_b, RevIt_Publisher_Post_Meta_Keys::SEARCH_INTENT, true );

		$type_a = $this->get_article_type( $post_a );
		$type_b = $this->get_article_type( $post_b );

		return array(
			'same_vehicle' => '' !== $vehicle_a && $vehicle_a === $vehicle_b,
			'same_cluster' => '' !== $cluster_a && $cluster_a === $cluster_b,
			'same_intent'  => '' !== $intent_a && $intent_a === $intent_b,
			'same_type'    => '' !== $type_a && $type_a === $type_b,
		);
	}

	private function get_article_type( int $post_id ): string {
		$terms = wp_get_post_terms( $post_id, RevIt_Publisher_Taxonomies::ARTICLE_TYPE, array( 'fields' => 'slugs' ) );
		return ( ! is_wp_error( $terms ) && ! empty( $terms ) ) ? (string) $terms[0] : '';
	}

	/**
	 * @param array{same_vehicle: bool, same_cluster: bool, same_intent: bool, same_type: bool} $context
	 */
	private function calculate_risk( string $classification, array $context ): string {
		if ( 'exact' === $classification ) {
			return 'high';
		}

		$score = 0;
		if ( $context['same_vehicle'] ) {
			$score += 2;
		}
		if ( $context['same_cluster'] ) {
			$score += 2;
		}
		if ( $context['same_intent'] ) {
			$score += 1;
		}
		if ( $context['same_type'] ) {
			$score += 1;
		}

		if ( 'high_overlap' === $classification && $score >= 4 ) {
			return 'high';
		}
		if ( 'high_overlap' === $classification || $score >= 3 ) {
			return 'medium';
		}

		return 'low';
	}
}
