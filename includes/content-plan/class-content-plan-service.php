<?php
/**
 * Content plan reconciliation and coverage.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reconciles content plans against the WordPress site.
 */
class RevIt_Publisher_Content_Plan_Service {

	private RevIt_Publisher_Article_Registry $registry;

	public function __construct( RevIt_Publisher_Article_Registry $registry ) {
		$this->registry = $registry;
	}

	/**
	 * Format vehicle label from plan vehicle object.
	 */
	public static function format_vehicle_label( object $vehicle ): string {
		return trim(
			sprintf(
				'%s %s %s %s',
				(string) ( $vehicle->manufacturer ?? '' ),
				(string) ( $vehicle->model ?? '' ),
				(string) ( $vehicle->generation ?? '' ),
				(string) ( $vehicle->trim ?? '' )
			)
		);
	}

	/**
	 * Load plan object from plan post ID.
	 */
	public function get_plan_data( int $plan_id ): ?object {
		$raw = get_post_meta( $plan_id, RevIt_Publisher_Content_Plan_Meta_Keys::PLAN_DATA, true );
		if ( ! is_array( $raw ) ) {
			return null;
		}

		return json_decode( wp_json_encode( $raw ), false );
	}

	/**
	 * Summarize plan without persisted post.
	 *
	 * @return array<string, mixed>
	 */
	public function summarize_plan_data( object $plan ): array {
		$articles = $this->reconcile_articles( $plan );

		$existing = 0;
		$missing  = 0;
		$published = 0;
		$draft     = 0;

		foreach ( $articles as $article ) {
			if ( 'missing' === $article['site_status'] ) {
				++$missing;
			} else {
				++$existing;
				if ( 'publish' === $article['site_status'] ) {
					++$published;
				} elseif ( in_array( $article['site_status'], array( 'draft', 'pending' ), true ) ) {
					++$draft;
				}
			}
		}

		return array(
			'planned_articles' => count( $articles ),
			'existing_articles'=> $existing,
			'missing_articles' => $missing,
			'published'        => $published,
			'draft'            => $draft,
			'clusters'         => count( (array) ( $plan->clusters ?? array() ) ),
			'overall_coverage' => count( $articles ) > 0 ? (int) round( ( $existing / count( $articles ) ) * 100 ) : 0,
		);
	}

	/**
	 * Full coverage report for imported plan.
	 *
	 * @return array<string, mixed>
	 */
	public function get_coverage( int $plan_id ): array {
		$plan = $this->get_plan_data( $plan_id );
		if ( null === $plan ) {
			return array();
		}

		$articles = $this->reconcile_articles( $plan );
		$clusters = $this->get_cluster_coverage( $plan, $articles );
		$summary  = $this->summarize_plan_data( $plan );
		$by_type  = $this->group_by_type( $plan, $articles );

		$search_performance = RevIt_Publisher_Services::gsc_content_status()->summarize_plan_articles( $articles );

		return array(
			'plan_id'            => $plan_id,
			'plan_key'           => (string) $plan->plan_key,
			'vehicle'            => self::format_vehicle_label( $plan->vehicle ),
			'summary'            => $summary,
			'clusters'           => $clusters,
			'articles'           => $articles,
			'by_type'            => $by_type,
			'search_performance' => $search_performance,
			'missing'            => array_values(
				array_filter(
					$articles,
					static fn( array $row ): bool => 'missing' === $row['site_status']
				)
			),
			'next_content'       => $this->get_next_content( $articles ),
		);
	}

	/**
	 * Reconcile each planned article against site registry.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function reconcile_articles( object $plan ): array {
		$rows = array();

		foreach ( (array) ( $plan->articles ?? array() ) as $entry ) {
			$key     = (string) ( $entry->article_key ?? '' );
			$post_id = $this->registry->find_post_id_by_article_key( $key );
			$status  = $this->classify_article_status( $key, $post_id );

			$rows[] = array(
				'article_key'   => $key,
				'title'         => (string) ( $entry->title ?? '' ),
				'article_type'  => (string) ( $entry->article_type ?? '' ),
				'cluster_key'   => (string) ( $entry->cluster_key ?? '' ),
				'primary_topic' => (string) ( $entry->primary_topic ?? '' ),
				'priority'      => (int) ( $entry->priority ?? 0 ),
				'pillar'        => ! empty( $entry->pillar ),
				'plan_status'   => (string) ( $entry->status ?? 'planned' ),
				'site_status'   => $status['site_status'],
				'post_id'       => $status['post_id'],
				'edit_url'      => $status['edit_url'],
			);
		}

		usort(
			$rows,
			static fn( array $a, array $b ): int => ( $b['priority'] ?? 0 ) <=> ( $a['priority'] ?? 0 )
		);

		return $rows;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function classify_article_status( string $article_key, ?int $post_id ): array {
		if ( null === $post_id ) {
			return array(
				'site_status' => 'missing',
				'post_id'     => null,
				'edit_url'    => null,
			);
		}

		if ( ! (bool) get_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::MANAGED, true ) ) {
			return array(
				'site_status' => 'unmanaged_collision',
				'post_id'     => $post_id,
				'edit_url'    => get_edit_post_link( $post_id, 'raw' ),
			);
		}

		$post = get_post( $post_id );
		$status = $post instanceof WP_Post ? $post->post_status : 'missing';

		return array(
			'site_status' => $status,
			'post_id'     => $post_id,
			'edit_url'    => get_edit_post_link( $post_id, 'raw' ),
		);
	}

	/**
	 * @param array<int, array<string, mixed>> $articles
	 * @return array<int, array<string, mixed>>
	 */
	private function get_cluster_coverage( object $plan, array $articles ): array {
		$graph   = RevIt_Publisher_Services::graph();
		$health  = RevIt_Publisher_Services::health_service();
		$clusters = array();

		foreach ( (array) ( $plan->clusters ?? array() ) as $cluster ) {
			$key          = (string) ( $cluster->cluster_key ?? '' );
			$planned_keys = (array) ( $cluster->articles ?? array() );
			$planned      = count( $planned_keys );
			$existing     = 0;
			$published    = 0;
			$missing      = 0;
			$orphans      = 0;
			$resolved_links = 0;
			$total_links    = 0;

			foreach ( $articles as $article ) {
				if ( $article['cluster_key'] !== $key ) {
					continue;
				}
				if ( 'missing' === $article['site_status'] ) {
					++$missing;
				} else {
					++$existing;
					if ( 'publish' === $article['site_status'] ) {
						++$published;
					}
					$post_id = (int) ( $article['post_id'] ?? 0 );
					if ( $post_id > 0 ) {
						$post_health = $health->get_post_health( $post_id );
						if ( ! empty( $post_health['is_orphan'] ) ) {
							++$orphans;
						}
						$outbound = $graph->get_outbound_relationships( $post_id );
						$total_links += count( $outbound );
						$resolved_links += count(
							array_filter(
								$outbound,
								static fn( array $link ): bool => 'resolved' === ( $link['status'] ?? '' )
							)
						);
					}
				}
			}

			$pillar_key = (string) ( $cluster->pillar_article_key ?? '' );
			$pillar     = $this->registry->find_post_id_by_article_key( $pillar_key );
			$pillar_status = null === $pillar ? 'missing' : get_post_status( $pillar );

			$meta_complete = 0;
			$meta_total    = 0;
			foreach ( $articles as $article ) {
				if ( $article['cluster_key'] !== $key || 'missing' === $article['site_status'] ) {
					continue;
				}
				$post_id = (int) ( $article['post_id'] ?? 0 );
				if ( $post_id <= 0 ) {
					continue;
				}
				++$meta_total;
				$has_meta = '' !== (string) get_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::SEO_TITLE, true )
					&& '' !== (string) get_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::META_DESCRIPTION, true );
				if ( $has_meta ) {
					++$meta_complete;
				}
			}

			$clusters[] = array(
				'cluster_key'         => $key,
				'name'                => (string) ( $cluster->name ?? '' ),
				'planned'             => $planned,
				'existing'            => $existing,
				'published'           => $published,
				'missing'             => $missing,
				'plan_coverage'       => $planned > 0 ? (int) round( ( $existing / $planned ) * 100 ) : 0,
				'pillar_status'       => $pillar_status,
				'internal_link_pct'   => $total_links > 0 ? (int) round( ( $resolved_links / $total_links ) * 100 ) : 100,
				'meta_completeness'   => $meta_total > 0 ? (int) round( ( $meta_complete / $meta_total ) * 100 ) : 0,
				'orphans'             => $orphans,
			);
		}

		return $clusters;
	}

	/**
	 * @param array<int, array<string, mixed>> $articles
	 * @return array<string, array{planned: int, existing: int}>
	 */
	private function group_by_type( object $plan, array $articles ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		$groups = array(
			'problem'       => array( 'planned' => 0, 'existing' => 0 ),
			'maintenance'   => array( 'planned' => 0, 'existing' => 0 ),
			'modification'  => array( 'planned' => 0, 'existing' => 0 ),
			'buying'        => array( 'planned' => 0, 'existing' => 0 ),
			'reliability'   => array( 'planned' => 0, 'existing' => 0 ),
			'pillar'        => array( 'planned' => 0, 'existing' => 0 ),
			'other'         => array( 'planned' => 0, 'existing' => 0 ),
		);

		foreach ( $articles as $article ) {
			$type = (string) ( $article['article_type'] ?? 'other' );
			if ( ! isset( $groups[ $type ] ) ) {
				$type = 'other';
			}
			++$groups[ $type ]['planned'];
			if ( 'missing' !== $article['site_status'] ) {
				++$groups[ $type ]['existing'];
			}
		}

		return $groups;
	}

	/**
	 * @param array<int, array<string, mixed>> $articles
	 * @return array<int, array<string, mixed>>
	 */
	private function get_next_content( array $articles ): array {
		return array_values(
			array_slice(
				array_filter(
					$articles,
					static fn( array $row ): bool => 'missing' === $row['site_status']
				),
				0,
				5
			)
		);
	}

	/**
	 * List all imported plans.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function list_plans(): array {
		$posts = get_posts(
			array(
				'post_type'      => RevIt_Publisher_Content_Plan_Post_Type::POST_TYPE,
				'post_status'    => 'private',
				'posts_per_page' => -1,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);

		$plans = array();
		foreach ( $posts as $post ) {
			$plan = $this->get_plan_data( (int) $post->ID );
			if ( null === $plan ) {
				continue;
			}
			$summary = $this->summarize_plan_data( $plan );
			$plans[] = array(
				'plan_id'  => (int) $post->ID,
				'plan_key' => (string) get_post_meta( $post->ID, RevIt_Publisher_Content_Plan_Meta_Keys::PLAN_KEY, true ),
				'vehicle'  => self::format_vehicle_label( $plan->vehicle ),
				'summary'  => $summary,
			);
		}

		return $plans;
	}
}
