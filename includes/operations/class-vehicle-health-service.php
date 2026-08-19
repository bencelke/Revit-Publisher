<?php
/**
 * Multi-vehicle content health and hub query preparation.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Vehicle-level health metrics for the command center and future public hubs.
 */
class RevIt_Publisher_Vehicle_Health_Service {

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function get_all_vehicle_summaries(): array {
		$summaries = RevIt_Publisher_Services::graph()->get_vehicle_summaries();
		$plans     = RevIt_Publisher_Services::plan_service()->list_plans();
		$plan_map  = array();
		foreach ( $plans as $plan ) {
			$plan_map[ (string) ( $plan['vehicle'] ?? '' ) ] = $plan;
		}

		$out = array();
		foreach ( $summaries as $summary ) {
			$label = (string) ( $summary['label'] ?? '' );
			$out[] = $this->enrich_vehicle( $label, $summary, $plan_map[ $label ] ?? null );
		}
		usort( $out, static fn( $a, $b ) => ( $b['seo_health_avg'] ?? 0 ) <=> ( $a['seo_health_avg'] ?? 0 ) );
		return $out;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function get_vehicle_detail( string $vehicle_label ): ?array {
		$summaries = $this->get_all_vehicle_summaries();
		foreach ( $summaries as $summary ) {
			if ( (string) ( $summary['label'] ?? '' ) === $vehicle_label ) {
				return $this->build_detail( $summary );
			}
		}
		return null;
	}

	/**
	 * @param array<string, mixed> $summary
	 * @param array<string, mixed>|null $plan
	 * @return array<string, mixed>
	 */
	private function enrich_vehicle( string $label, array $summary, ?array $plan ): array {
		$post_ids = $this->get_vehicle_post_ids( $label );
		$scores   = array();
		$published = 0;
		$draft     = 0;
		$orphans   = 0;
		$overlaps  = 0;

		foreach ( $post_ids as $post_id ) {
			$analysis = RevIt_Publisher_Services::seo_score()->analyze( (int) $post_id );
			$scores[] = (int) ( $analysis['total_score'] ?? 0 );
			$status   = get_post_status( (int) $post_id );
			if ( 'publish' === $status ) {
				++$published;
			} elseif ( in_array( $status, array( 'draft', 'pending', 'private' ), true ) ) {
				++$draft;
			}
			$health = RevIt_Publisher_Services::health_service()->get_post_health( (int) $post_id );
			if ( ! empty( $health['is_orphan'] ) ) {
				++$orphans;
			}
			foreach ( RevIt_Publisher_Services::topic_overlaps()->get_post_overlaps( (int) $post_id ) as $overlap ) {
				if ( 'high' === ( $overlap['risk'] ?? '' ) ) {
					++$overlaps;
					break;
				}
			}
		}

		return array_merge(
			$summary,
			array(
				'seo_health_avg'    => ! empty( $scores ) ? (int) round( array_sum( $scores ) / count( $scores ) ) : 0,
				'published'         => $published,
				'draft'             => $draft,
				'orphans'           => $orphans,
				'high_overlaps'     => $overlaps,
				'plan_coverage'     => (int) ( $plan['summary']['overall_coverage'] ?? 0 ),
				'missing_articles'  => (int) ( $plan['summary']['missing_articles'] ?? 0 ),
				'clusters_count'    => count( (array) ( $summary['clusters'] ?? array() ) ),
				'review_due'        => $this->count_review_due( $post_ids ),
			)
		);
	}

	/**
	 * @param array<string, mixed> $summary
	 * @return array<string, mixed>
	 */
	private function build_detail( array $summary ): array {
		$label    = (string) ( $summary['label'] ?? '' );
		$post_ids = $this->get_vehicle_post_ids( $label );
		$clusters = $this->get_vehicle_clusters( $post_ids );
		$needs    = array(
			'orphans'          => (int) ( $summary['orphans'] ?? 0 ),
			'missing_articles' => (int) ( $summary['missing_articles'] ?? 0 ),
			'unresolved_links' => (int) ( $summary['unresolved_links'] ?? 0 ),
			'topic_overlaps'   => (int) ( $summary['high_overlaps'] ?? 0 ),
		);

		return array(
			'vehicle'         => $label,
			'articles'        => (int) ( $summary['articles'] ?? 0 ),
			'published'       => (int) ( $summary['published'] ?? 0 ),
			'draft'           => (int) ( $summary['draft'] ?? 0 ),
			'clusters'        => (int) ( $summary['clusters_count'] ?? 0 ),
			'plan_coverage'   => (int) ( $summary['plan_coverage'] ?? 0 ),
			'seo_health_avg'  => (int) ( $summary['seo_health_avg'] ?? 0 ),
			'needs_attention' => $needs,
			'cluster_breakdown' => $clusters,
			'post_ids'        => $post_ids,
		);
	}

	/**
	 * Hub-prep query: published articles for a vehicle grouped by type.
	 *
	 * @return array<string, array<int, array<string, mixed>>>
	 */
	public function get_published_by_type( string $vehicle_label ): array {
		$groups = array();
		foreach ( $this->get_vehicle_post_ids( $vehicle_label ) as $post_id ) {
			if ( 'publish' !== get_post_status( (int) $post_id ) ) {
				continue;
			}
			$types = wp_get_post_terms( (int) $post_id, RevIt_Publisher_Taxonomies::ARTICLE_TYPE, array( 'fields' => 'slugs' ) );
			$type  = ( ! is_wp_error( $types ) && ! empty( $types ) ) ? (string) $types[0] : 'other';
			$groups[ $type ] ??= array();
			$groups[ $type ][] = array(
				'post_id'     => (int) $post_id,
				'title'       => get_the_title( (int) $post_id ),
				'article_key' => (string) get_post_meta( (int) $post_id, RevIt_Publisher_Post_Meta_Keys::ARTICLE_KEY, true ),
				'permalink'   => get_permalink( (int) $post_id ),
			);
		}
		return $groups;
	}

	/**
	 * @return int[]
	 */
	private function get_vehicle_post_ids( string $vehicle_label ): array {
		$ids = array();
		foreach ( RevIt_Publisher_Services::registry()->get_managed_post_ids() as $post_id ) {
			if ( RevIt_Publisher_Services::graph()->get_vehicle_label( (int) $post_id ) === $vehicle_label ) {
				$ids[] = (int) $post_id;
			}
		}
		return $ids;
	}

	/**
	 * @param int[] $post_ids
	 * @return array<int, array<string, mixed>>
	 */
	private function get_vehicle_clusters( array $post_ids ): array {
		$clusters = array();
		foreach ( RevIt_Publisher_Services::graph()->get_cluster_summaries() as $cluster ) {
			$clusters[] = array(
				'cluster_key' => (string) ( $cluster['cluster_key'] ?? '' ),
				'name'        => (string) ( $cluster['name'] ?? '' ),
				'articles'    => (int) ( $cluster['article_count'] ?? 0 ),
			);
		}
		return $clusters;
	}

	/**
	 * @param int[] $post_ids
	 */
	private function count_review_due( array $post_ids ): int {
		$count = 0;
		foreach ( $post_ids as $post_id ) {
			if ( RevIt_Publisher_Services::review_status()->is_review_due( (int) $post_id ) ) {
				++$count;
			}
		}
		return $count;
	}
}
