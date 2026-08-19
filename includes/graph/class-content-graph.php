<?php
/**
 * Content relationship graph service.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Derives SEO content graph relationships from meta and taxonomies.
 */
class RevIt_Publisher_Content_Graph {

	/**
	 * Article resolver.
	 *
	 * @var RevIt_Publisher_Article_Resolver
	 */
	private RevIt_Publisher_Article_Resolver $resolver;

	/**
	 * Constructor.
	 */
	public function __construct( RevIt_Publisher_Article_Resolver $resolver ) {
		$this->resolver = $resolver;
	}

	/**
	 * Get outbound planned internal links with resolution status.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_outbound_relationships( int $post_id ): array {
		$links = get_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::INTERNAL_LINKS, true );
		if ( ! is_array( $links ) ) {
			return array();
		}

		$out = array();
		foreach ( $links as $index => $link ) {
			if ( ! is_array( $link ) ) {
				continue;
			}
			$key    = (string) ( $link['target_article_key'] ?? '' );
			$status = $this->resolver->classify_target_status( $key );
			$target = $this->resolver->resolve( $key );

			$out[] = array_merge(
				$link,
				array(
					'index'            => $index,
					'direction'        => 'outbound',
					'status'           => $status,
					'target_post_id'   => $target['post_id'] ?? null,
					'target_title'     => $target['title'] ?? null,
					'target_permalink' => $target['permalink'] ?? null,
				)
			);
		}

		return $out;
	}

	/**
	 * Get inbound links from other RevIt articles planning to link here.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_inbound_relationships( int $post_id ): array {
		$article_key = (string) get_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::ARTICLE_KEY, true );
		if ( '' === $article_key ) {
			return array();
		}

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

		$inbound = array();
		foreach ( $posts as $source_id ) {
			if ( (int) $source_id === $post_id ) {
				continue;
			}

			$links = get_post_meta( (int) $source_id, RevIt_Publisher_Post_Meta_Keys::INTERNAL_LINKS, true );
			if ( ! is_array( $links ) ) {
				continue;
			}

			foreach ( $links as $index => $link ) {
				if ( ! is_array( $link ) ) {
					continue;
				}
				if ( (string) ( $link['target_article_key'] ?? '' ) !== $article_key ) {
					continue;
				}

				$inbound[] = array_merge(
					$link,
					array(
						'index'           => $index,
						'direction'       => 'inbound',
						'source_post_id'  => (int) $source_id,
						'source_title'    => get_the_title( (int) $source_id ),
						'status'          => 'resolved',
					)
				);
			}
		}

		return $inbound;
	}

	/**
	 * Get resolved outbound links only.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_resolved_links( int $post_id ): array {
		return array_values(
			array_filter(
				$this->get_outbound_relationships( $post_id ),
				static fn( array $link ): bool => 'resolved' === ( $link['status'] ?? '' )
			)
		);
	}

	/**
	 * Get unresolved outbound links.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_unresolved_links( int $post_id ): array {
		return array_values(
			array_filter(
				$this->get_outbound_relationships( $post_id ),
				static fn( array $link ): bool => in_array( $link['status'] ?? '', array( 'target_missing', 'unresolved', 'unavailable', 'target_private' ), true )
			)
		);
	}

	/**
	 * Get pillar article for post.
	 *
	 * @return array<string, mixed>|null
	 */
	public function get_pillar_article( int $post_id ): ?array {
		$pillar_key = (string) get_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::PILLAR_ARTICLE_KEY, true );
		if ( '' === $pillar_key ) {
			return null;
		}

		$resolved = $this->resolver->resolve( $pillar_key );
		if ( null === $resolved ) {
			return array(
				'article_key' => $pillar_key,
				'status'      => 'pillar_planned',
				'message'     => __( 'Pillar planned — not imported yet', 'revit-publisher' ),
			);
		}

		return array_merge( $resolved, array( 'status' => 'resolved' ) );
	}

	/**
	 * Get supporting articles for a pillar post.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_supporting_articles( int $post_id ): array {
		$article_key = (string) get_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::ARTICLE_KEY, true );
		if ( '' === $article_key ) {
			return array();
		}

		$posts = get_posts(
			array(
				'post_type'      => 'post',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => RevIt_Publisher_Post_Meta_Keys::PILLAR_ARTICLE_KEY,
						'value' => $article_key,
					),
				),
			)
		);

		$supporting = array();
		foreach ( $posts as $support_id ) {
			if ( (int) $support_id === $post_id ) {
				continue;
			}
			$resolved = $this->resolver->resolve_post( (int) $support_id );
			if ( null !== $resolved ) {
				$supporting[] = $resolved;
			}
		}

		return $supporting;
	}

	/**
	 * Get articles in same cluster taxonomy term.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_cluster_articles( int $post_id ): array {
		$terms = wp_get_post_terms( $post_id, RevIt_Publisher_Taxonomies::CLUSTER, array( 'fields' => 'ids' ) );
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return array();
		}

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
						'terms'    => $terms,
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

		$articles = array();
		foreach ( $posts as $id ) {
			if ( (int) $id === $post_id ) {
				continue;
			}
			$resolved = $this->resolver->resolve_post( (int) $id );
			if ( null !== $resolved ) {
				$articles[] = $resolved;
			}
		}

		return $articles;
	}

	/**
	 * Get articles sharing trim-level vehicle identity.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_vehicle_articles( int $post_id ): array {
		$trim_terms = wp_get_post_terms( $post_id, RevIt_Publisher_Taxonomies::TRIM, array( 'fields' => 'ids' ) );
		if ( ! is_wp_error( $trim_terms ) && ! empty( $trim_terms ) ) {
			return $this->get_articles_by_taxonomy( RevIt_Publisher_Taxonomies::TRIM, $trim_terms, $post_id );
		}

		$model_terms = wp_get_post_terms( $post_id, RevIt_Publisher_Taxonomies::MODEL, array( 'fields' => 'ids' ) );
		if ( ! is_wp_error( $model_terms ) && ! empty( $model_terms ) ) {
			return $this->get_articles_by_taxonomy( RevIt_Publisher_Taxonomies::MODEL, $model_terms, $post_id );
		}

		return array();
	}

	/**
	 * Build vehicle graph summaries for admin.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_vehicle_summaries(): array {
		$posts = $this->get_managed_post_ids();
		$groups = array();

		foreach ( $posts as $post_id ) {
			$label = $this->get_vehicle_label( $post_id );
			if ( '' === $label ) {
				continue;
			}
			if ( ! isset( $groups[ $label ] ) ) {
				$groups[ $label ] = array(
					'label'    => $label,
					'articles' => 0,
					'types'    => array(),
					'clusters' => array(),
					'unresolved_links' => 0,
				);
			}

			++$groups[ $label ]['articles'];

			$type_terms = wp_get_post_terms( $post_id, RevIt_Publisher_Taxonomies::ARTICLE_TYPE, array( 'fields' => 'names' ) );
			if ( ! is_wp_error( $type_terms ) && ! empty( $type_terms ) ) {
				$type = (string) $type_terms[0];
				$groups[ $label ]['types'][ $type ] = ( $groups[ $label ]['types'][ $type ] ?? 0 ) + 1;
			}

			$cluster_terms = wp_get_post_terms( $post_id, RevIt_Publisher_Taxonomies::CLUSTER, array( 'fields' => 'names' ) );
			if ( ! is_wp_error( $cluster_terms ) ) {
				foreach ( $cluster_terms as $cluster ) {
					$groups[ $label ]['clusters'][ (string) $cluster ] = true;
				}
			}

			$groups[ $label ]['unresolved_links'] += count( $this->get_unresolved_links( $post_id ) );
		}

		$summaries = array_values( $groups );
		foreach ( $summaries as &$summary ) {
			$summary['clusters'] = array_keys( $summary['clusters'] );
		}

		return $summaries;
	}

	/**
	 * Build cluster graph summaries.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_cluster_summaries(): array {
		$terms = get_terms(
			array(
				'taxonomy'   => RevIt_Publisher_Taxonomies::CLUSTER,
				'hide_empty' => false,
			)
		);

		if ( is_wp_error( $terms ) ) {
			return array();
		}

		$summaries = array();
		foreach ( $terms as $term ) {
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
				)
			);

			$pillar_key = (string) get_term_meta( $term->term_id, RevIt_Publisher_Taxonomies::TERM_PILLAR_KEY, true );
			$pillar     = '' !== $pillar_key ? $this->resolver->resolve( $pillar_key ) : null;
			$resolved   = 0;
			$missing    = 0;

			foreach ( $posts as $post_id ) {
				foreach ( $this->get_outbound_relationships( (int) $post_id ) as $link ) {
					if ( 'resolved' === ( $link['status'] ?? '' ) ) {
						++$resolved;
					} else {
						++$missing;
					}
				}
			}

			$summaries[] = array(
				'cluster_key'  => (string) get_term_meta( $term->term_id, RevIt_Publisher_Taxonomies::TERM_CLUSTER_KEY, true ) ?: $term->slug,
				'name'         => $term->name,
				'article_count'=> count( $posts ),
				'pillar'       => $pillar,
				'pillar_key'   => $pillar_key,
				'resolved_links' => $resolved,
				'missing_links'  => $missing,
			);
		}

		return $summaries;
	}

	/**
	 * Get vehicle label from post meta.
	 */
	public function get_vehicle_label( int $post_id ): string {
		$parts = array_filter(
			array(
				get_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::VEHICLE_MANUFACTURER, true ),
				get_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::VEHICLE_MODEL, true ),
				get_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::VEHICLE_GENERATION, true ),
				get_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::VEHICLE_TRIM, true ),
			)
		);

		return implode( ' ', array_map( 'strval', $parts ) );
	}

	/**
	 * @param int[] $term_ids Term IDs.
	 * @return array<int, array<string, mixed>>
	 */
	private function get_articles_by_taxonomy( string $taxonomy, array $term_ids, int $exclude_id ): array {
		$posts = get_posts(
			array(
				'post_type'      => 'post',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
					array(
						'taxonomy' => $taxonomy,
						'field'    => 'term_id',
						'terms'    => $term_ids,
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

		$articles = array();
		foreach ( $posts as $id ) {
			if ( (int) $id === $exclude_id ) {
				continue;
			}
			$resolved = $this->resolver->resolve_post( (int) $id );
			if ( null !== $resolved ) {
				$articles[] = $resolved;
			}
		}

		return $articles;
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
