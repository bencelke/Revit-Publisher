<?php
/**
 * Vehicle hub registry and content resolution.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RevIt_Publisher_Vehicle_Hub_Service {

	public const CLUSTER_PUBLIC_THRESHOLD = 3;

	/** Hub section article types. */
	public const SECTION_TYPES = array(
		'common_problems' => array( 'problem' ),
		'maintenance'     => array( 'maintenance' ),
		'modifications'   => array( 'modification' ),
		'reliability'     => array( 'reliability' ),
		'buying'          => array( 'buying', 'guide' ),
	);

	private RevIt_Publisher_Article_Resolver $resolver;
	private RevIt_Publisher_Content_Graph $graph;
	private RevIt_Publisher_Hub_Cache $cache;

	public function __construct(
		RevIt_Publisher_Article_Resolver $resolver,
		RevIt_Publisher_Content_Graph $graph,
		RevIt_Publisher_Hub_Cache $cache
	) {
		$this->resolver = $resolver;
		$this->graph    = $graph;
		$this->cache    = $cache;
	}

	public function find_by_key( string $vehicle_key ): ?int {
		if ( '' === $vehicle_key ) {
			return null;
		}
		$posts = get_posts(
			array(
				'post_type'      => RevIt_Publisher_Vehicle_Hub_Post_Type::POST_TYPE,
				'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => RevIt_Publisher_Vehicle_Hub_Meta_Keys::VEHICLE_KEY,
						'value' => $vehicle_key,
					),
				),
			)
		);
		return ! empty( $posts ) ? (int) $posts[0] : null;
	}

	/**
	 * @return array<string, mixed>|WP_Error
	 */
	public function create_draft( string $vehicle_key, array $identity ): int|WP_Error {
		$existing = $this->find_by_key( $vehicle_key );
		if ( null !== $existing ) {
			return new WP_Error(
				'revit_duplicate_hub',
				__( 'A vehicle hub already exists for this identity.', 'revit-publisher' ),
				array( 'hub_id' => $existing )
			);
		}

		$title = RevIt_Publisher_Vehicle_Identity::label(
			(string) ( $identity['manufacturer'] ?? '' ),
			(string) ( $identity['model'] ?? '' ),
			(string) ( $identity['generation'] ?? '' ),
			(string) ( $identity['trim'] ?? '' )
		);

		$hub_id = wp_insert_post(
			array(
				'post_type'   => RevIt_Publisher_Vehicle_Hub_Post_Type::POST_TYPE,
				'post_status' => 'draft',
				'post_title'  => $title,
				'post_name'   => $vehicle_key,
			),
			true
		);

		if ( is_wp_error( $hub_id ) ) {
			return $hub_id;
		}

		$this->save_identity_meta( (int) $hub_id, $vehicle_key, $identity );
		$this->cache->invalidate_hub( (int) $hub_id );
		return (int) $hub_id;
	}

	/**
	 * @param array<string, mixed> $identity
	 */
	public function save_identity_meta( int $hub_id, string $vehicle_key, array $identity ): void {
		update_post_meta( $hub_id, RevIt_Publisher_Vehicle_Hub_Meta_Keys::VEHICLE_KEY, $vehicle_key );
		update_post_meta( $hub_id, RevIt_Publisher_Vehicle_Hub_Meta_Keys::MANUFACTURER, (string) ( $identity['manufacturer'] ?? '' ) );
		update_post_meta( $hub_id, RevIt_Publisher_Vehicle_Hub_Meta_Keys::MODEL, (string) ( $identity['model'] ?? '' ) );
		update_post_meta( $hub_id, RevIt_Publisher_Vehicle_Hub_Meta_Keys::GENERATION, (string) ( $identity['generation'] ?? '' ) );
		update_post_meta( $hub_id, RevIt_Publisher_Vehicle_Hub_Meta_Keys::TRIM, (string) ( $identity['trim'] ?? '' ) );
		update_post_meta( $hub_id, RevIt_Publisher_Vehicle_Hub_Meta_Keys::START_YEAR, (string) ( $identity['start_year'] ?? '' ) );
		update_post_meta( $hub_id, RevIt_Publisher_Vehicle_Hub_Meta_Keys::END_YEAR, (string) ( $identity['end_year'] ?? '' ) );
		update_post_meta( $hub_id, RevIt_Publisher_Vehicle_Hub_Meta_Keys::ENGINES, (array) ( $identity['engines'] ?? array() ) );
		if ( isset( $identity['content_plan_id'] ) ) {
			update_post_meta( $hub_id, RevIt_Publisher_Vehicle_Hub_Meta_Keys::CONTENT_PLAN_ID, (int) $identity['content_plan_id'] );
		}
	}

	public function get_vehicle_key( int $hub_id ): string {
		return (string) get_post_meta( $hub_id, RevIt_Publisher_Vehicle_Hub_Meta_Keys::VEHICLE_KEY, true );
	}

	/**
	 * @return array<string, mixed>
	 */
	public function get_identity( int $hub_id ): array {
		return array(
			'vehicle_key'  => $this->get_vehicle_key( $hub_id ),
			'manufacturer' => (string) get_post_meta( $hub_id, RevIt_Publisher_Vehicle_Hub_Meta_Keys::MANUFACTURER, true ),
			'model'        => (string) get_post_meta( $hub_id, RevIt_Publisher_Vehicle_Hub_Meta_Keys::MODEL, true ),
			'generation'   => (string) get_post_meta( $hub_id, RevIt_Publisher_Vehicle_Hub_Meta_Keys::GENERATION, true ),
			'trim'         => (string) get_post_meta( $hub_id, RevIt_Publisher_Vehicle_Hub_Meta_Keys::TRIM, true ),
			'start_year'   => (string) get_post_meta( $hub_id, RevIt_Publisher_Vehicle_Hub_Meta_Keys::START_YEAR, true ),
			'end_year'     => (string) get_post_meta( $hub_id, RevIt_Publisher_Vehicle_Hub_Meta_Keys::END_YEAR, true ),
			'engines'      => (array) get_post_meta( $hub_id, RevIt_Publisher_Vehicle_Hub_Meta_Keys::ENGINES, true ),
		);
	}

	/**
	 * @return int[]
	 */
	public function get_article_ids_for_hub( int $hub_id, bool $published_only = false ): array {
		$identity = $this->get_identity( $hub_id );
		$key      = (string) ( $identity['vehicle_key'] ?? '' );
		if ( '' === $key ) {
			return array();
		}

		$cache_key = 'articles_' . $key . ( $published_only ? '_pub' : '' );
		$cached    = $this->cache->get( $hub_id, $cache_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$ids = array();
		foreach ( RevIt_Publisher_Services::registry()->get_managed_post_ids() as $post_id ) {
			if ( RevIt_Publisher_Vehicle_Identity::from_post( (int) $post_id ) !== $key ) {
				continue;
			}
			if ( $published_only && 'publish' !== get_post_status( (int) $post_id ) ) {
				continue;
			}
			$ids[] = (int) $post_id;
		}

		$this->cache->set( $hub_id, $cache_key, $ids );
		return $ids;
	}

	/**
	 * @return array<string, array<int, array<string, mixed>>>
	 */
	public function get_articles_by_section( int $hub_id ): array {
		$cached = $this->cache->get( $hub_id, 'sections' );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$sections = array();
		foreach ( self::SECTION_TYPES as $section => $types ) {
			$sections[ $section ] = array();
		}
		$sections['recently_updated'] = array();

		foreach ( $this->get_article_ids_for_hub( $hub_id, true ) as $post_id ) {
			$entry = $this->article_entry( $post_id );
			$slugs = wp_get_post_terms( $post_id, RevIt_Publisher_Taxonomies::ARTICLE_TYPE, array( 'fields' => 'slugs' ) );
			$type  = ( ! is_wp_error( $slugs ) && ! empty( $slugs ) ) ? (string) $slugs[0] : 'other';

			foreach ( self::SECTION_TYPES as $section => $types ) {
				if ( in_array( $type, $types, true ) ) {
					$sections[ $section ][] = $entry;
				}
			}
			$sections['recently_updated'][] = $entry;
		}

		usort(
			$sections['recently_updated'],
			static fn( $a, $b ) => strcmp( (string) ( $b['modified'] ?? '' ), (string) ( $a['modified'] ?? '' ) )
		);
		$sections['recently_updated'] = array_slice( $sections['recently_updated'], 0, 6 );

		$this->cache->set( $hub_id, 'sections', $sections );
		return $sections;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function get_clusters_for_hub( int $hub_id ): array {
		$cached = $this->cache->get( $hub_id, 'clusters' );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$article_ids = $this->get_article_ids_for_hub( $hub_id, true );
		$term_ids    = array();
		foreach ( $article_ids as $post_id ) {
			$terms = wp_get_post_terms( (int) $post_id, RevIt_Publisher_Taxonomies::CLUSTER, array( 'fields' => 'ids' ) );
			if ( ! is_wp_error( $terms ) ) {
				$term_ids = array_merge( $term_ids, $terms );
			}
		}
		$term_ids = array_unique( array_map( 'intval', $term_ids ) );
		$clusters = array();

		foreach ( $term_ids as $term_id ) {
			$term = get_term( (int) $term_id, RevIt_Publisher_Taxonomies::CLUSTER );
			if ( ! $term instanceof WP_Term ) {
				continue;
			}
			$pillar_key = (string) get_term_meta( $term_id, RevIt_Publisher_Taxonomies::TERM_PILLAR_KEY, true );
			$pillar     = '' !== $pillar_key ? $this->resolver->resolve( $pillar_key ) : null;
			$count      = $this->count_published_in_cluster( (int) $term_id, $article_ids );
			$public     = null !== $pillar || $count >= self::CLUSTER_PUBLIC_THRESHOLD;

			$clusters[] = array(
				'term_id'      => (int) $term_id,
				'name'         => $term->name,
				'cluster_key'  => (string) get_term_meta( $term_id, RevIt_Publisher_Taxonomies::TERM_CLUSTER_KEY, true ) ?: $term->slug,
				'article_count'=> $count,
				'pillar'       => $pillar,
				'is_public'    => $public,
				'canonical_url'=> $this->cluster_canonical_url( $pillar, $public ),
			);
		}

		$this->cache->set( $hub_id, 'clusters', $clusters );
		return $clusters;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function get_engine_related_articles( int $hub_id ): array {
		$engines = (array) get_post_meta( $hub_id, RevIt_Publisher_Vehicle_Hub_Meta_Keys::ENGINES, true );
		if ( empty( $engines ) ) {
			return array();
		}

		$related = array();
		foreach ( RevIt_Publisher_Services::registry()->get_managed_post_ids() as $post_id ) {
			if ( 'publish' !== get_post_status( (int) $post_id ) ) {
				continue;
			}
			$post_engines = get_post_meta( (int) $post_id, RevIt_Publisher_Post_Meta_Keys::VEHICLE_ENGINES, true );
			if ( ! is_array( $post_engines ) ) {
				continue;
			}
			if ( array_intersect( $engines, $post_engines ) ) {
				$key = $this->get_vehicle_key( $hub_id );
				if ( RevIt_Publisher_Vehicle_Identity::from_post( (int) $post_id ) === $key ) {
					continue;
				}
				$related[] = $this->article_entry( (int) $post_id );
			}
		}
		return array_slice( $related, 0, 8 );
	}

	/**
	 * @return array<string, mixed>
	 */
	public function get_coverage( int $hub_id ): array {
		$published = count( $this->get_article_ids_for_hub( $hub_id, true ) );
		$clusters  = $this->get_clusters_for_hub( $hub_id );
		$plan_id   = (int) get_post_meta( $hub_id, RevIt_Publisher_Vehicle_Hub_Meta_Keys::CONTENT_PLAN_ID, true );
		$plan_pct  = 0;
		$missing   = 0;

		if ( $plan_id > 0 ) {
			$plan = RevIt_Publisher_Services::plan_service()->get_plan( $plan_id );
			if ( is_array( $plan ) ) {
				$plan_pct = (int) ( $plan['summary']['overall_coverage'] ?? 0 );
				$missing  = (int) ( $plan['summary']['missing_articles'] ?? 0 );
			}
		}

		return array(
			'published_articles' => $published,
			'clusters'           => count( $clusters ),
			'plan_coverage'      => $plan_pct,
			'missing_articles'   => $missing,
		);
	}

	/**
	 * Build identity from vehicle label by sampling first matching article.
	 *
	 * @return array<string, mixed>|null
	 */
	public function identity_from_label( string $vehicle_label ): ?array {
		foreach ( RevIt_Publisher_Services::registry()->get_managed_post_ids() as $post_id ) {
			if ( $this->graph->get_vehicle_label( (int) $post_id ) !== $vehicle_label ) {
				continue;
			}
			$key = RevIt_Publisher_Vehicle_Identity::from_post( (int) $post_id );
			return array(
				'vehicle_key'  => $key,
				'manufacturer' => (string) get_post_meta( (int) $post_id, RevIt_Publisher_Post_Meta_Keys::VEHICLE_MANUFACTURER, true ),
				'model'        => (string) get_post_meta( (int) $post_id, RevIt_Publisher_Post_Meta_Keys::VEHICLE_MODEL, true ),
				'generation'   => (string) get_post_meta( (int) $post_id, RevIt_Publisher_Post_Meta_Keys::VEHICLE_GENERATION, true ),
				'trim'         => (string) get_post_meta( (int) $post_id, RevIt_Publisher_Post_Meta_Keys::VEHICLE_TRIM, true ),
				'start_year'   => (string) get_post_meta( (int) $post_id, RevIt_Publisher_Post_Meta_Keys::VEHICLE_START_YEAR, true ),
				'end_year'     => (string) get_post_meta( (int) $post_id, RevIt_Publisher_Post_Meta_Keys::VEHICLE_END_YEAR, true ),
				'engines'      => (array) get_post_meta( (int) $post_id, RevIt_Publisher_Post_Meta_Keys::VEHICLE_ENGINES, true ),
			);
		}
		return null;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function list_published_hubs(): array {
		$posts = get_posts(
			array(
				'post_type'      => RevIt_Publisher_Vehicle_Hub_Post_Type::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
			)
		);
		$hubs = array();
		foreach ( $posts as $post ) {
			$hubs[] = array(
				'hub_id'       => (int) $post->ID,
				'title'        => get_the_title( (int) $post->ID ),
				'vehicle_key'  => $this->get_vehicle_key( (int) $post->ID ),
				'manufacturer' => (string) get_post_meta( (int) $post->ID, RevIt_Publisher_Vehicle_Hub_Meta_Keys::MANUFACTURER, true ),
				'permalink'    => get_permalink( (int) $post->ID ),
			);
		}
		return $hubs;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function get_publish_checks( int $hub_id ): array {
		$coverage = $this->get_coverage( $hub_id );
		$intro    = (string) get_post_meta( $hub_id, RevIt_Publisher_Vehicle_Hub_Meta_Keys::INTRO, true );
		$title    = get_the_title( $hub_id );

		return array(
			'warnings' => array(
				'articles'  => ( $coverage['published_articles'] ?? 0 ) < 3,
				'title'     => '' === trim( $title ),
				'intro'     => '' === trim( $intro ),
				'canonical' => false,
				'breadcrumbs' => false,
				'sitemap'   => false,
			),
			'coverage' => $coverage,
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function article_entry( int $post_id ): array {
		return array(
			'post_id'     => $post_id,
			'title'       => get_the_title( $post_id ),
			'permalink'   => get_permalink( $post_id ),
			'article_key' => (string) get_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::ARTICLE_KEY, true ),
			'modified'    => get_post_modified_time( 'c', false, $post_id ),
		);
	}

	/**
	 * @param int[] $vehicle_article_ids
	 */
	private function count_published_in_cluster( int $term_id, array $vehicle_article_ids ): int {
		$count = 0;
		foreach ( $vehicle_article_ids as $post_id ) {
			$terms = wp_get_post_terms( (int) $post_id, RevIt_Publisher_Taxonomies::CLUSTER, array( 'fields' => 'ids' ) );
			if ( ! is_wp_error( $terms ) && in_array( $term_id, $terms, true ) ) {
				++$count;
			}
		}
		return $count;
	}

	/**
	 * @param array<string, mixed>|null $pillar
	 */
	private function cluster_canonical_url( ?array $pillar, bool $is_public ): ?string {
		if ( ! $is_public ) {
			return null;
		}
		if ( null !== $pillar && ! empty( $pillar['permalink'] ) && 'publish' === ( $pillar['post_status'] ?? '' ) ) {
			return (string) $pillar['permalink'];
		}
		return null;
	}
}
