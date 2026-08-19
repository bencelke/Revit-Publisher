<?php
/**
 * Server-rendered Gutenberg blocks for vehicle hub content.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers RevIt public blocks with PHP render callbacks.
 */
class RevIt_Publisher_Public_Blocks {

	public function init(): void {
		add_action( 'init', array( $this, 'register_blocks' ) );
		add_filter( 'the_content', array( $this, 'prepend_supporting_navigation' ), 15 );
	}

	public function register_blocks(): void {
		register_block_type(
			'revit/vehicle-content',
			array(
				'render_callback' => array( $this, 'render_vehicle_content' ),
				'attributes'      => array(
					'vehicleKey'   => array( 'type' => 'string', 'default' => '' ),
					'allowedTypes' => array( 'type' => 'array', 'default' => array() ),
					'cluster'      => array( 'type' => 'string', 'default' => '' ),
					'max'          => array( 'type' => 'number', 'default' => 6 ),
					'order'        => array( 'type' => 'string', 'default' => 'date' ),
				),
			)
		);

		register_block_type(
			'revit/related-articles',
			array(
				'render_callback' => array( $this, 'render_related_articles' ),
				'attributes'      => array(
					'limit' => array( 'type' => 'number', 'default' => 5 ),
				),
			)
		);

		register_block_type(
			'revit/cluster-navigation',
			array(
				'render_callback' => array( $this, 'render_cluster_navigation' ),
				'attributes'      => array(
					'clusterKey' => array( 'type' => 'string', 'default' => '' ),
				),
			)
		);
	}

	/**
	 * @param array<string, mixed> $attributes
	 */
	public function render_vehicle_content( array $attributes ): string {
		$vehicle_key = sanitize_text_field( (string) ( $attributes['vehicleKey'] ?? '' ) );
		if ( '' === $vehicle_key && is_singular( RevIt_Publisher_Vehicle_Hub_Post_Type::POST_TYPE ) ) {
			$vehicle_key = RevIt_Publisher_Services::vehicle_hubs()->get_vehicle_key( get_queried_object_id() );
		}
		if ( '' === $vehicle_key ) {
			return '';
		}

		$hub_id = RevIt_Publisher_Services::vehicle_hubs()->find_by_key( $vehicle_key );
		if ( null === $hub_id ) {
			return '';
		}

		$allowed_types = is_array( $attributes['allowedTypes'] ?? null ) ? $attributes['allowedTypes'] : array();
		$allowed_types = array_map( 'sanitize_key', $allowed_types );
		$cluster_key   = sanitize_text_field( (string) ( $attributes['cluster'] ?? '' ) );
		$max           = max( 1, min( 20, (int) ( $attributes['max'] ?? 6 ) ) );
		$order         = sanitize_key( (string) ( $attributes['order'] ?? 'date' ) );

		$articles = $this->resolve_vehicle_articles( $hub_id, $allowed_types, $cluster_key );
		if ( 'title' === $order ) {
			usort( $articles, static fn( $a, $b ) => strcmp( (string) ( $a['title'] ?? '' ), (string) ( $b['title'] ?? '' ) ) );
		} else {
			usort( $articles, static fn( $a, $b ) => strcmp( (string) ( $b['modified'] ?? '' ), (string) ( $a['modified'] ?? '' ) ) );
		}
		$articles = array_slice( $articles, 0, $max );
		if ( empty( $articles ) ) {
			return '';
		}

		$html = '<ul class="revit-publisher-vehicle-content">';
		foreach ( $articles as $article ) {
			$html .= sprintf(
				'<li class="revit-publisher-vehicle-content__item"><a href="%s">%s</a></li>',
				esc_url( (string) ( $article['permalink'] ?? '' ) ),
				esc_html( (string) ( $article['title'] ?? '' ) )
			);
		}
		$html .= '</ul>';
		return $html;
	}

	/**
	 * @param array<string, mixed> $attributes
	 */
	public function render_related_articles( array $attributes ): string {
		if ( ! is_singular() ) {
			return '';
		}

		$post_id = get_queried_object_id();
		$limit   = max( 4, min( 6, (int) ( $attributes['limit'] ?? 5 ) ) );
		$related = $this->resolve_related_articles( $post_id, $limit );
		if ( empty( $related ) ) {
			return '';
		}

		$html = '<aside class="revit-publisher-related-articles"><h2 class="revit-publisher-related-articles__title">' . esc_html__( 'Related Articles', 'revit-publisher' ) . '</h2><ul class="revit-publisher-related-articles__list">';
		foreach ( $related as $article ) {
			$html .= sprintf(
				'<li class="revit-publisher-related-articles__item"><a href="%s">%s</a></li>',
				esc_url( (string) ( $article['permalink'] ?? '' ) ),
				esc_html( (string) ( $article['title'] ?? '' ) )
			);
		}
		$html .= '</ul></aside>';
		return $html;
	}

	/**
	 * @param array<string, mixed> $attributes
	 */
	public function render_cluster_navigation( array $attributes ): string {
		$cluster_key = sanitize_text_field( (string) ( $attributes['clusterKey'] ?? '' ) );
		if ( '' === $cluster_key && is_singular( 'post' ) ) {
			$cluster_key = (string) get_post_meta( get_queried_object_id(), RevIt_Publisher_Post_Meta_Keys::CLUSTER_KEY, true );
		}
		if ( '' === $cluster_key ) {
			return '';
		}

		$matrix = RevIt_Publisher_Services::cluster_link_matrix()->build_for_cluster_key( $cluster_key );
		if ( empty( $matrix['articles'] ) ) {
			return '';
		}

		$pillar = is_array( $matrix['pillar'] ?? null ) ? $matrix['pillar'] : null;
		$html   = '<nav class="revit-publisher-cluster-nav" aria-label="' . esc_attr__( 'Cluster navigation', 'revit-publisher' ) . '">';
		if ( null !== $pillar && ! empty( $pillar['permalink'] ) ) {
			$html .= sprintf(
				'<p class="revit-publisher-cluster-nav__pillar"><a href="%s">%s</a></p>',
				esc_url( (string) $pillar['permalink'] ),
				esc_html( (string) ( $pillar['title'] ?? __( 'Pillar', 'revit-publisher' ) ) )
			);
		}
		$html .= '<ul class="revit-publisher-cluster-nav__list">';
		foreach ( $matrix['articles'] as $article ) {
			if ( null !== $pillar && (int) ( $pillar['post_id'] ?? 0 ) === (int) ( $article['post_id'] ?? 0 ) ) {
				continue;
			}
			$html .= sprintf(
				'<li class="revit-publisher-cluster-nav__item"><a href="%s">%s</a></li>',
				esc_url( (string) ( $article['permalink'] ?? '' ) ),
				esc_html( (string) ( $article['title'] ?? '' ) )
			);
		}
		$html .= '</ul></nav>';
		return $html;
	}

	/**
	 * @param string[] $allowed_types
	 * @return array<int, array<string, mixed>>
	 */
	private function resolve_vehicle_articles( int $hub_id, array $allowed_types, string $cluster_key ): array {
		$articles = array();
		foreach ( RevIt_Publisher_Services::vehicle_hubs()->get_article_ids_for_hub( $hub_id, true ) as $post_id ) {
			if ( '' !== $cluster_key ) {
				$post_cluster = (string) get_post_meta( (int) $post_id, RevIt_Publisher_Post_Meta_Keys::CLUSTER_KEY, true );
				if ( $post_cluster !== $cluster_key ) {
					continue;
				}
			}
			if ( ! empty( $allowed_types ) ) {
				$slugs = wp_get_post_terms( (int) $post_id, RevIt_Publisher_Taxonomies::ARTICLE_TYPE, array( 'fields' => 'slugs' ) );
				$type  = ( ! is_wp_error( $slugs ) && ! empty( $slugs ) ) ? (string) $slugs[0] : 'other';
				if ( ! in_array( $type, $allowed_types, true ) ) {
					continue;
				}
			}
			$articles[] = array(
				'post_id'   => (int) $post_id,
				'title'     => get_the_title( (int) $post_id ),
				'permalink' => get_permalink( (int) $post_id ),
				'modified'  => get_post_modified_time( 'c', false, (int) $post_id ),
			);
		}
		return $articles;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private function resolve_related_articles( int $post_id, int $limit ): array {
		$cache_key = 'revit_related_' . $post_id;
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return array_slice( $cached, 0, $limit );
		}

		$related_keys = get_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::RELATED_ARTICLES, true );
		$articles     = array();
		$seen         = array( $post_id => true );

		if ( is_array( $related_keys ) ) {
			foreach ( $related_keys as $key ) {
				$resolved = RevIt_Publisher_Services::resolver()->resolve( (string) $key );
				if ( null === $resolved || 'publish' !== ( $resolved['post_status'] ?? '' ) ) {
					continue;
				}
				$id = (int) ( $resolved['post_id'] ?? 0 );
				if ( isset( $seen[ $id ] ) ) {
					continue;
				}
				$seen[ $id ]  = true;
				$articles[] = $resolved;
				if ( count( $articles ) >= $limit ) {
					break;
				}
			}
		}

		if ( count( $articles ) < $limit ) {
			$cluster_key = (string) get_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::CLUSTER_KEY, true );
			if ( '' !== $cluster_key ) {
				foreach ( $this->cluster_fallback_articles( $post_id, $cluster_key ) as $article ) {
					$id = (int) ( $article['post_id'] ?? 0 );
					if ( isset( $seen[ $id ] ) ) {
						continue;
					}
					$seen[ $id ]  = true;
					$articles[] = $article;
					if ( count( $articles ) >= $limit ) {
						break;
					}
				}
			}
		}

		if ( count( $articles ) < $limit ) {
			$vehicle_key = RevIt_Publisher_Vehicle_Identity::from_post( $post_id );
			$hub_id      = '' !== $vehicle_key ? RevIt_Publisher_Services::vehicle_hubs()->find_by_key( $vehicle_key ) : null;
			if ( null !== $hub_id ) {
				foreach ( RevIt_Publisher_Services::vehicle_hubs()->get_article_ids_for_hub( $hub_id, true ) as $id ) {
					if ( (int) $id === $post_id || isset( $seen[ (int) $id ] ) ) {
						continue;
					}
					$seen[ (int) $id ] = true;
					$articles[]        = array(
						'post_id'   => (int) $id,
						'title'     => get_the_title( (int) $id ),
						'permalink' => get_permalink( (int) $id ),
					);
					if ( count( $articles ) >= $limit ) {
						break;
					}
				}
			}
		}

		set_transient( $cache_key, $articles, HOUR_IN_SECONDS );
		return array_slice( $articles, 0, $limit );
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private function cluster_fallback_articles( int $post_id, string $cluster_key ): array {
		$term_id = RevIt_Publisher_Services::cluster_link_matrix()->find_term_id_by_cluster_key( $cluster_key );
		if ( null === $term_id ) {
			return array();
		}

		$posts = get_posts(
			array(
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'posts_per_page' => 10,
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

		$articles = array();
		foreach ( $posts as $id ) {
			if ( (int) $id === $post_id ) {
				continue;
			}
			$articles[] = array(
				'post_id'   => (int) $id,
				'title'     => get_the_title( (int) $id ),
				'permalink' => get_permalink( (int) $id ),
			);
		}
		return $articles;
	}

	/**
	 * Supporting article "Part of … Guide" navigation.
	 */
	public function prepend_supporting_navigation( string $content ): string {
		if ( ! is_singular( 'post' ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		$post_id = get_queried_object_id();
		if ( ! RevIt_Publisher_Services::resolver()->is_managed( $post_id ) ) {
			return $content;
		}

		$pillar = RevIt_Publisher_Services::graph()->get_pillar_article( $post_id );
		if ( null === $pillar || empty( $pillar['permalink'] ) || 'publish' !== ( $pillar['post_status'] ?? '' ) ) {
			return $content;
		}

		$vehicle = RevIt_Publisher_Services::graph()->get_vehicle_label( $post_id );
		$cluster = (string) get_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::CLUSTER_KEY, true );
		$guide   = trim( $vehicle . ( '' !== $cluster ? ' ' . ucwords( str_replace( '-', ' ', $cluster ) ) : '' ) );
		$html    = sprintf(
			'<nav class="revit-publisher-supporting-nav"><p>%s <a href="%s">%s</a></p></nav>',
			esc_html__( 'Part of the', 'revit-publisher' ),
			esc_url( (string) $pillar['permalink'] ),
			esc_html( $guide . ' ' . __( 'Guide', 'revit-publisher' ) )
		);

		$siblings = RevIt_Publisher_Services::graph()->get_cluster_articles( $post_id );
		if ( ! empty( $siblings ) ) {
			$html .= '<ul class="revit-publisher-supporting-nav__siblings">';
			foreach ( array_slice( $siblings, 0, 5 ) as $sibling ) {
				if ( 'publish' !== ( $sibling['post_status'] ?? '' ) ) {
					continue;
				}
				$html .= sprintf(
					'<li><a href="%s">%s</a></li>',
					esc_url( (string) ( $sibling['permalink'] ?? '' ) ),
					esc_html( (string) ( $sibling['title'] ?? '' ) )
				);
			}
			$html .= '</ul>';
		}

		return $html . $content;
	}
}
