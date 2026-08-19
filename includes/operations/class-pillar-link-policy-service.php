<?php
/**
 * Pillar-to-supporting internal link policy.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Generates cluster link suggestions based on pillar/supporting relationships.
 */
class RevIt_Publisher_Pillar_Link_Policy_Service {

	/**
	 * Analyze cluster pillar link coverage.
	 *
	 * @return array<string, mixed>|null
	 */
	public function get_cluster_coverage( string $cluster_key ): ?array {
		$term = $this->find_cluster_term( $cluster_key );
		if ( ! $term instanceof WP_Term ) {
			return null;
		}

		$pillar_key = (string) get_term_meta( $term->term_id, RevIt_Publisher_Taxonomies::TERM_PILLAR_KEY, true );
		$pillar     = '' !== $pillar_key ? RevIt_Publisher_Services::resolver()->resolve( $pillar_key ) : null;
		$pillar_id  = is_array( $pillar ) ? (int) ( $pillar['post_id'] ?? 0 ) : 0;

		$posts = get_posts(
			array(
				'post_type'      => 'post',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
					array(
						'taxonomy' => RevIt_Publisher_Taxonomies::CLUSTER,
						'field'    => 'term_id',
						'terms'    => array( $term->term_id ),
					),
				),
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => RevIt_Publisher_Post_Meta_Keys::MANAGED,
						'value' => '1',
					),
				),
			)
		);

		$supporting_articles = array();
		$linked     = 0;
		$missing    = array();

		foreach ( $posts as $post_id ) {
			if ( (int) $post_id === $pillar_id ) {
				continue;
			}
			$title = get_the_title( (int) $post_id );
			$supporting_articles[] = array(
				'post_id'     => (int) $post_id,
				'title'       => $title,
				'article_key' => (string) get_post_meta( (int) $post_id, RevIt_Publisher_Post_Meta_Keys::ARTICLE_KEY, true ),
			);

			if ( $pillar_id > 0 && RevIt_Publisher_Services::link_service()->content_already_links_to( $pillar_id, (int) $post_id ) ) {
				++$linked;
			} else {
				$missing[] = $title;
			}
		}

		$total = count( $supporting_articles );

		return array(
			'cluster_key'        => $cluster_key,
			'name'               => $term->name,
			'pillar_id'          => $pillar_id,
			'pillar_title'       => $pillar_id > 0 ? get_the_title( $pillar_id ) : '',
			'supporting'         => $total,
			'supporting_articles'=> $supporting_articles,
			'linked'             => $linked,
			'missing_titles'     => $missing,
			'coverage_pct'       => $total > 0 ? (int) round( ( $linked / $total ) * 100 ) : 100,
		);
	}

	/**
	 * Generate link suggestions for cluster pillar coverage gaps.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function generate_suggestions( string $cluster_key ): array {
		$coverage = $this->get_cluster_coverage( $cluster_key );
		if ( null === $coverage || (int) ( $coverage['pillar_id'] ?? 0 ) <= 0 ) {
			return array();
		}

		$pillar_id   = (int) $coverage['pillar_id'];
		$suggestions = array();

		foreach ( RevIt_Publisher_Services::graph()->get_outbound_relationships( $pillar_id ) as $link ) {
			if ( 'resolved' !== ( $link['status'] ?? '' ) ) {
				continue;
			}
			$target_id = (int) ( $link['target_post_id'] ?? 0 );
			if ( $target_id <= 0 || RevIt_Publisher_Services::link_service()->content_already_links_to( $pillar_id, $target_id ) ) {
				continue;
			}
			$anchor = (string) ( $link['preferred_anchor'] ?? '' );
			$loc    = RevIt_Publisher_Services::link_service()->find_anchor_location( $pillar_id, $anchor );
			if ( null === $loc ) {
				continue;
			}
			$suggestions[] = array(
				'source_post_id'     => $pillar_id,
				'target_post_id'     => $target_id,
				'target_article_key' => (string) ( $link['target_article_key'] ?? '' ),
				'anchor'             => $anchor,
				'relationship'       => (string) ( $link['relationship'] ?? 'supporting' ),
				'block_index'        => $loc['block_index'],
				'cluster_key'        => $cluster_key,
			);
		}

		// Supporting → pillar back-links.
		foreach ( (array) ( $coverage['supporting_articles'] ?? array() ) as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$source_id = (int) ( $item['post_id'] ?? 0 );
			if ( $source_id <= 0 || RevIt_Publisher_Services::link_service()->content_already_links_to( $source_id, $pillar_id ) ) {
				continue;
			}
			$pillar_title = get_the_title( $pillar_id );
			$loc = RevIt_Publisher_Services::link_service()->find_anchor_location( $source_id, $pillar_title );
			if ( null === $loc ) {
				continue;
			}
			$suggestions[] = array(
				'source_post_id' => $source_id,
				'target_post_id' => $pillar_id,
				'anchor'         => $pillar_title,
				'relationship'   => 'pillar',
				'block_index'    => $loc['block_index'],
				'cluster_key'    => $cluster_key,
			);
		}

		return $suggestions;
	}

	/**
	 * Apply cluster link suggestions with logging.
	 *
	 * @param array<int, array<string, mixed>> $suggestions
	 * @return array<string, mixed>
	 */
	public function apply_cluster_links( array $suggestions ): array {
		$max     = RevIt_Publisher_Services::settings()->max_cluster_links_per_article();
		$results = array();
		$applied = 0;
		$skipped = 0;

		foreach ( $suggestions as $index => $suggestion ) {
			$post_id = (int) ( $suggestion['source_post_id'] ?? 0 );
			if ( $post_id <= 0 ) {
				++$skipped;
				continue;
			}

			$fresh = null;
			$cluster_key = (string) ( $suggestion['cluster_key'] ?? '' );
			foreach ( $this->generate_suggestions( $cluster_key ) as $candidate ) {
				if ( (int) ( $candidate['target_post_id'] ?? 0 ) === (int) ( $suggestion['target_post_id'] ?? -1 )
					&& (int) ( $candidate['source_post_id'] ?? 0 ) === $post_id
				) {
					$fresh = $candidate;
					break;
				}
			}

			if ( null === $fresh ) {
				++$skipped;
				$results[] = array( 'index' => $index, 'success' => false, 'message' => __( 'Suggestion stale.', 'revit-publisher' ) );
				continue;
			}

			$result = RevIt_Publisher_Services::link_service()->apply_link_logged(
				$post_id,
				$fresh,
				RevIt_Publisher_Link_Change_Log::ACTION_CLUSTER
			);

			if ( is_wp_error( $result ) ) {
				++$skipped;
				$results[] = array( 'index' => $index, 'success' => false, 'message' => $result->get_error_message() );
				continue;
			}

			++$applied;
			$results[] = array( 'index' => $index, 'success' => true, 'log_id' => $result );
		}

		RevIt_Publisher_Services::event_logger()->log(
			'cluster_links_applied',
			array( 'applied' => $applied, 'skipped' => $skipped )
		);

		return array( 'applied' => $applied, 'skipped' => $skipped, 'results' => $results );
	}

	private function find_cluster_term( string $cluster_key ): ?WP_Term {
		$terms = get_terms(
			array(
				'taxonomy'   => RevIt_Publisher_Taxonomies::CLUSTER,
				'hide_empty' => false,
				'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => RevIt_Publisher_Taxonomies::TERM_CLUSTER_KEY,
						'value' => $cluster_key,
					),
				),
			)
		);
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return null;
		}
		return $terms[0];
	}
}
