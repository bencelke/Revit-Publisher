<?php
/**
 * Cluster internal link matrix for public navigation and REST.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds planned and applied link relationships within a cluster.
 */
class RevIt_Publisher_Cluster_Link_Matrix {

	private RevIt_Publisher_Content_Graph $graph;
	private RevIt_Publisher_Internal_Link_Service $links;

	public function __construct(
		RevIt_Publisher_Content_Graph $graph,
		RevIt_Publisher_Internal_Link_Service $links
	) {
		$this->graph = $graph;
		$this->links = $links;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function build_for_term( int $term_id ): array {
		$term = get_term( $term_id, RevIt_Publisher_Taxonomies::CLUSTER );
		if ( ! $term instanceof WP_Term ) {
			return array(
				'term_id'  => $term_id,
				'valid'    => false,
				'articles' => array(),
				'matrix'   => array(),
			);
		}

		$cluster_key = (string) get_term_meta( $term_id, RevIt_Publisher_Taxonomies::TERM_CLUSTER_KEY, true ) ?: $term->slug;
		return $this->build_for_cluster_key( $cluster_key, $term_id );
	}

	/**
	 * @return array<string, mixed>
	 */
	public function build_for_cluster_key( string $cluster_key, ?int $term_id = null ): array {
		$term_id = $term_id ?? $this->find_term_id_by_cluster_key( $cluster_key );
		if ( null === $term_id ) {
			return array(
				'cluster_key' => $cluster_key,
				'valid'       => false,
				'articles'    => array(),
				'matrix'      => array(),
			);
		}

		$term        = get_term( $term_id, RevIt_Publisher_Taxonomies::CLUSTER );
		$pillar_key  = (string) get_term_meta( $term_id, RevIt_Publisher_Taxonomies::TERM_PILLAR_KEY, true );
		$pillar      = '' !== $pillar_key ? RevIt_Publisher_Services::resolver()->resolve( $pillar_key ) : null;
		$post_ids    = $this->get_cluster_post_ids( $term_id );
		$articles    = array();
		$key_to_id   = array();

		foreach ( $post_ids as $post_id ) {
			$article_key = (string) get_post_meta( (int) $post_id, RevIt_Publisher_Post_Meta_Keys::ARTICLE_KEY, true );
			$key_to_id[ $article_key ] = (int) $post_id;
			$articles[] = array(
				'post_id'      => (int) $post_id,
				'article_key'  => $article_key,
				'title'        => get_the_title( (int) $post_id ),
				'permalink'    => get_permalink( (int) $post_id ),
				'post_status'  => get_post_status( (int) $post_id ),
				'is_pillar'    => is_array( $pillar ) && (int) ( $pillar['post_id'] ?? 0 ) === (int) $post_id,
			);
		}

		$matrix = array();
		foreach ( $post_ids as $source_id ) {
			$source_key = (string) get_post_meta( (int) $source_id, RevIt_Publisher_Post_Meta_Keys::ARTICLE_KEY, true );
			$planned    = array();
			$applied    = array();

			foreach ( $this->graph->get_outbound_relationships( (int) $source_id ) as $link ) {
				$target_key = (string) ( $link['target_article_key'] ?? '' );
				if ( ! isset( $key_to_id[ $target_key ] ) ) {
					continue;
				}
				$target_id = (int) $key_to_id[ $target_key ];
				$planned[] = array(
					'target_post_id'     => $target_id,
					'target_article_key' => $target_key,
					'relationship'       => (string) ( $link['relationship'] ?? '' ),
					'status'             => (string) ( $link['status'] ?? '' ),
				);
				if ( $this->links->content_already_links_to( (int) $source_id, $target_id ) ) {
					$applied[] = array(
						'target_post_id'     => $target_id,
						'target_article_key' => $target_key,
						'applied_in_content' => true,
					);
				}
			}

			$matrix[] = array(
				'source_post_id'    => (int) $source_id,
				'source_article_key'=> $source_key,
				'planned_links'     => $planned,
				'applied_links'     => $applied,
				'planned_count'     => count( $planned ),
				'applied_count'     => count( $applied ),
			);
		}

		return array(
			'cluster_key' => $cluster_key,
			'term_id'     => $term_id,
			'name'        => $term instanceof WP_Term ? $term->name : '',
			'valid'       => true,
			'pillar'      => $pillar,
			'articles'    => $articles,
			'matrix'      => $matrix,
			'summary'     => array(
				'article_count'  => count( $articles ),
				'planned_total'  => array_sum( array_column( $matrix, 'planned_count' ) ),
				'applied_total'  => array_sum( array_column( $matrix, 'applied_count' ) ),
			),
		);
	}

	public function find_term_id_by_cluster_key( string $cluster_key ): ?int {
		$cluster_key = sanitize_text_field( $cluster_key );
		if ( '' === $cluster_key ) {
			return null;
		}

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
				'number'     => 1,
			)
		);

		if ( ! is_wp_error( $terms ) && ! empty( $terms ) && $terms[0] instanceof WP_Term ) {
			return (int) $terms[0]->term_id;
		}

		$term = get_term_by( 'slug', sanitize_title( $cluster_key ), RevIt_Publisher_Taxonomies::CLUSTER );
		return $term instanceof WP_Term ? (int) $term->term_id : null;
	}

	/**
	 * @return int[]
	 */
	private function get_cluster_post_ids( int $term_id ): array {
		$posts = get_posts(
			array(
				'post_type'      => 'post',
				'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
					array(
						'taxonomy' => RevIt_Publisher_Taxonomies::CLUSTER,
						'field'    => 'term_id',
						'terms'    => array( $term_id ),
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
		return array_map( 'intval', $posts );
	}
}
