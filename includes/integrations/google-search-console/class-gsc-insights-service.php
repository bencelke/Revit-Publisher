<?php
/**
 * Dashboard aggregations for Search Console data.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RevIt_Publisher_GSC_Insights_Service {

	private RevIt_Publisher_GSC_Data_Store $store;

	public function __construct( RevIt_Publisher_GSC_Data_Store $store ) {
		$this->store = $store;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function get_summary_with_comparison( string $window_key = '28d' ): array {
		$current  = $this->store->get_summary( $window_key );
		$prev_key = '7d' === $window_key ? 'prev_7d' : 'prev_28d';
		$previous = $this->store->get_summary( $prev_key );

		return array(
			'window'   => $window_key,
			'current'  => $this->format_metrics( $current ),
			'previous' => $this->format_metrics( $previous ),
			'change'   => $this->compare( $current, $previous ),
		);
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function get_vehicle_performance( string $window_key = '28d' ): array {
		$summaries = RevIt_Publisher_Services::vehicle_health()->get_all_vehicle_summaries();
		$out       = array();
		foreach ( $summaries as $summary ) {
			$label = (string) ( $summary['label'] ?? '' );
			$posts = $this->post_ids_for_vehicle( $label );
			$agg   = $this->aggregate_posts( $posts, $window_key );
			$with_impr = 0;
			foreach ( $posts as $post_id ) {
				$m = $this->store->get_post_metrics( (int) $post_id, $window_key );
				if ( is_array( $m ) && (int) ( $m['impressions'] ?? 0 ) > 0 ) {
					++$with_impr;
				}
			}
			$out[] = array_merge(
				$agg,
				array(
					'vehicle'              => $label,
					'articles_total'       => count( $posts ),
					'articles_with_impressions' => $with_impr,
					'plan_coverage'        => (int) ( $summary['plan_coverage'] ?? 0 ),
					'seo_health_avg'       => (int) ( $summary['seo_health_avg'] ?? 0 ),
				)
			);
		}
		usort( $out, static fn( $a, $b ) => ( $b['clicks'] ?? 0 ) <=> ( $a['clicks'] ?? 0 ) );
		return $out;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function get_cluster_performance( string $window_key = '28d' ): array {
		$out = array();
		foreach ( RevIt_Publisher_Services::graph()->get_cluster_summaries() as $cluster ) {
			$key   = (string) ( $cluster['cluster_key'] ?? '' );
			$posts = $this->post_ids_for_cluster( $key );
			$agg   = $this->aggregate_posts( $posts, $window_key );
			$out[] = array_merge(
				$agg,
				array(
					'cluster_key' => $key,
					'name'        => (string) ( $cluster['name'] ?? '' ),
					'articles'    => count( $posts ),
				)
			);
		}
		usort( $out, static fn( $a, $b ) => ( $b['clicks'] ?? 0 ) <=> ( $a['clicks'] ?? 0 ) );
		return $out;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function get_post_performance( int $post_id, string $window_key = '28d' ): array {
		$metrics = $this->store->get_post_metrics( $post_id, $window_key );
		$prev_key = '7d' === $window_key ? 'prev_7d' : 'prev_28d';
		$prev    = $this->store->get_post_metrics( $post_id, $prev_key );
		$health  = RevIt_Publisher_Services::seo_score()->analyze( $post_id );
		$opps    = array_values(
			array_filter(
				RevIt_Publisher_Services::gsc_opportunities()->list_opportunities( $window_key ),
				static fn( array $o ): bool => (int) ( $o['post_id'] ?? 0 ) === $post_id
			)
		);
		return array(
			'metrics'    => $this->format_metrics( is_array( $metrics ) ? $metrics : array() ),
			'trend'      => $this->compare(
				is_array( $metrics ) ? $metrics : array(),
				is_array( $prev ) ? $prev : array()
			),
			'queries'    => $this->store->get_post_queries( $post_id, $window_key, 5 ),
			'seo_health' => (int) ( $health['total_score'] ?? 0 ),
			'opportunity'=> $opps[0]['issue_type'] ?? null,
			'opportunity_detail' => $opps[0] ?? null,
		);
	}

	/**
	 * @param int[] $post_ids
	 * @return array<string, mixed>
	 */
	private function aggregate_posts( array $post_ids, string $window_key ): array {
		$clicks = 0;
		$impr   = 0;
		$ctr_sum = 0.0;
		$pos_sum = 0.0;
		$count  = 0;
		foreach ( $post_ids as $post_id ) {
			$m = $this->store->get_post_metrics( (int) $post_id, $window_key );
			if ( ! is_array( $m ) ) {
				continue;
			}
			$clicks += (int) ( $m['clicks'] ?? 0 );
			$impr   += (int) ( $m['impressions'] ?? 0 );
			$ctr_sum += (float) ( $m['ctr'] ?? 0 );
			$pos_sum += (float) ( $m['position'] ?? 0 );
			++$count;
		}
		return array(
			'clicks'      => $clicks,
			'impressions' => $impr,
			'ctr'         => $count > 0 ? round( $ctr_sum / $count, 4 ) : 0,
			'position'    => $count > 0 ? round( $pos_sum / $count, 1 ) : 0,
		);
	}

	/**
	 * @param array<string, mixed> $metrics
	 * @return array<string, mixed>
	 */
	private function format_metrics( array $metrics ): array {
		return array(
			'clicks'      => (int) ( $metrics['clicks'] ?? 0 ),
			'impressions' => (int) ( $metrics['impressions'] ?? 0 ),
			'ctr'         => round( (float) ( $metrics['ctr'] ?? 0 ) * 100, 2 ),
			'position'    => round( (float) ( $metrics['position'] ?? 0 ), 1 ),
		);
	}

	/**
	 * @param array<string, mixed> $current
	 * @param array<string, mixed> $previous
	 * @return array<string, mixed>
	 */
	private function compare( array $current, array $previous ): array {
		$calc = static function ( float $cur, float $prev ): ?float {
			if ( 0.0 === $prev ) {
				return null;
			}
			return round( ( ( $cur - $prev ) / $prev ) * 100, 1 );
		};
		$cur_clicks = (float) ( $current['clicks'] ?? 0 );
		$prev_clicks = (float) ( $previous['clicks'] ?? 0 );
		$cur_impr = (float) ( $current['impressions'] ?? 0 );
		$prev_impr = (float) ( $previous['impressions'] ?? 0 );
		$cur_pos = (float) ( $current['position'] ?? 0 );
		$prev_pos = (float) ( $previous['position'] ?? 0 );

		return array(
			'clicks_pct'      => $calc( $cur_clicks, $prev_clicks ),
			'impressions_pct' => $calc( $cur_impr, $prev_impr ),
			'position_delta'  => ( $cur_pos > 0 && $prev_pos > 0 ) ? round( $prev_pos - $cur_pos, 1 ) : null,
		);
	}

	/**
	 * @return int[]
	 */
	private function post_ids_for_vehicle( string $vehicle_label ): array {
		$ids = array();
		foreach ( RevIt_Publisher_Services::registry()->get_managed_post_ids() as $post_id ) {
			if ( RevIt_Publisher_Services::graph()->get_vehicle_label( (int) $post_id ) === $vehicle_label ) {
				$ids[] = (int) $post_id;
			}
		}
		return $ids;
	}

	/**
	 * @return int[]
	 */
	private function post_ids_for_cluster( string $cluster_key ): array {
		$ids = array();
		foreach ( RevIt_Publisher_Services::registry()->get_managed_post_ids() as $post_id ) {
			if ( (string) get_post_meta( (int) $post_id, RevIt_Publisher_Post_Meta_Keys::CLUSTER_KEY, true ) === $cluster_key ) {
				$ids[] = (int) $post_id;
			}
		}
		return $ids;
	}
}
