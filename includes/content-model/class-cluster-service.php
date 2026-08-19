<?php
/**
 * Cluster registry and taxonomy sync.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages cluster taxonomy terms and cluster metadata.
 */
class RevIt_Publisher_Cluster_Service {

	/**
	 * Sync cluster taxonomy and post meta for an article.
	 *
	 * @param int    $post_id Post ID.
	 * @param object $cluster Cluster object from package.
	 * @return true|WP_Error
	 */
	public function sync_post( int $post_id, object $cluster ) {
		$cluster_key        = sanitize_text_field( (string) ( $cluster->cluster_key ?? '' ) );
		$name               = sanitize_text_field( (string) ( $cluster->name ?? '' ) );
		$pillar_article_key = isset( $cluster->pillar_article_key ) && null !== $cluster->pillar_article_key
			? sanitize_text_field( (string) $cluster->pillar_article_key )
			: '';
		$parent_cluster_key = isset( $cluster->parent_cluster_key ) && null !== $cluster->parent_cluster_key
			? sanitize_text_field( (string) $cluster->parent_cluster_key )
			: '';

		update_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::CLUSTER_KEY, $cluster_key );
		update_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::PILLAR_ARTICLE_KEY, $pillar_article_key );

		$parent_term_id = 0;
		if ( '' !== $parent_cluster_key ) {
			$parent_term = $this->find_term_by_cluster_key( $parent_cluster_key );
			if ( null !== $parent_term ) {
				$parent_term_id = $parent_term;
			}
		}

		$term = $this->ensure_cluster_term(
			$cluster_key,
			$name,
			$pillar_article_key,
			$parent_cluster_key,
			$parent_term_id
		);

		if ( is_wp_error( $term ) ) {
			return $term;
		}

		$result = wp_set_object_terms( $post_id, array( (int) $term['term_id'] ), RevIt_Publisher_Taxonomies::CLUSTER, false );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return true;
	}

	/**
	 * Count cluster taxonomy terms.
	 */
	public function count_clusters(): int {
		$count = wp_count_terms(
			array(
				'taxonomy'   => RevIt_Publisher_Taxonomies::CLUSTER,
				'hide_empty' => false,
			)
		);

		return is_wp_error( $count ) ? 0 : (int) $count;
	}

	/**
	 * Find cluster term by stable cluster key.
	 */
	public function find_term_by_cluster_key( string $cluster_key ): ?int {
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

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			$term = get_term_by( 'slug', sanitize_title( $cluster_key ), RevIt_Publisher_Taxonomies::CLUSTER );
			return $term instanceof WP_Term ? (int) $term->term_id : null;
		}

		return (int) $terms[0]->term_id;
	}

	/**
	 * Ensure cluster term exists with metadata.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	private function ensure_cluster_term(
		string $cluster_key,
		string $name,
		string $pillar_article_key,
		string $parent_cluster_key,
		int $parent_term_id
	) {
		$existing = $this->find_term_by_cluster_key( $cluster_key );
		if ( null !== $existing ) {
			update_term_meta( $existing, RevIt_Publisher_Taxonomies::TERM_CLUSTER_KEY, $cluster_key );
			update_term_meta( $existing, RevIt_Publisher_Taxonomies::TERM_PILLAR_KEY, $pillar_article_key );
			update_term_meta( $existing, RevIt_Publisher_Taxonomies::TERM_PARENT_CLUSTER_KEY, $parent_cluster_key );
			return array(
				'term_id' => $existing,
				'term_taxonomy_id' => $existing,
			);
		}

		$result = wp_insert_term(
			$name,
			RevIt_Publisher_Taxonomies::CLUSTER,
			array(
				'slug'   => sanitize_title( $cluster_key ),
				'parent' => $parent_term_id,
			)
		);

		if ( is_wp_error( $result ) ) {
			if ( 'term_exists' === $result->get_error_code() ) {
				$term_id = (int) $result->get_error_data();
			} else {
				return $result;
			}
		} else {
			$term_id = (int) $result['term_id'];
		}

		update_term_meta( $term_id, RevIt_Publisher_Taxonomies::TERM_CLUSTER_KEY, $cluster_key );
		update_term_meta( $term_id, RevIt_Publisher_Taxonomies::TERM_PILLAR_KEY, $pillar_article_key );
		update_term_meta( $term_id, RevIt_Publisher_Taxonomies::TERM_PARENT_CLUSTER_KEY, $parent_cluster_key );

		return array(
			'term_id' => $term_id,
			'term_taxonomy_id' => $term_id,
		);
	}
}
