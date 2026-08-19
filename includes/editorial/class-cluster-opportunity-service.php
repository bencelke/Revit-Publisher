<?php
/**
 * Cluster-level editorial opportunity summary.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RevIt_Publisher_Cluster_Opportunity_Service {

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function list_all( string $window_key = '28d' ): array {
		$out = array();
		foreach ( RevIt_Publisher_Services::gsc_insights()->get_cluster_performance( $window_key ) as $cluster ) {
			$key = (string) ( $cluster['cluster_key'] ?? '' );
			$out[] = $this->summarize_cluster( $key, $cluster );
		}
		if ( empty( $out ) ) {
			foreach ( RevIt_Publisher_Services::graph()->get_cluster_summaries() as $cluster ) {
				$out[] = $this->summarize_cluster( (string) ( $cluster['cluster_key'] ?? '' ), array() );
			}
		}
		usort( $out, static fn( $a, $b ) => ( $b['opportunity_score'] ?? 0 ) <=> ( $a['opportunity_score'] ?? 0 ) );
		return $out;
	}

	/**
	 * @param array<string, mixed> $gsc
	 * @return array<string, mixed>
	 */
	public function summarize_cluster( string $cluster_key, array $gsc = array() ): array {
		$reasons = array();
		$score   = 25;
		$name    = $cluster_key;
		foreach ( RevIt_Publisher_Services::graph()->get_cluster_summaries() as $c ) {
			if ( ( $c['cluster_key'] ?? '' ) === $cluster_key ) {
				$name = (string) ( $c['name'] ?? $cluster_key );
				break;
			}
		}

		$published = 0;
		$missing   = 0;
		foreach ( RevIt_Publisher_Services::plan_service()->list_plans() as $plan ) {
			$coverage = RevIt_Publisher_Services::plan_service()->get_coverage( (int) ( $plan['plan_id'] ?? 0 ) );
			foreach ( (array) ( $coverage['clusters'] ?? array() ) as $cluster ) {
				if ( ( $cluster['cluster_key'] ?? '' ) === $cluster_key ) {
					$published = (int) ( $cluster['published'] ?? 0 );
					$missing   = (int) ( $cluster['missing'] ?? 0 );
				}
			}
		}
		if ( $missing > 0 ) {
			$reasons[] = sprintf( '%d missing planned articles', $missing );
			$score += min( 25, $missing * 8 );
		}

		$impressions = (int) ( $gsc['impressions'] ?? 0 );
		$position    = (float) ( $gsc['position'] ?? 0 );
		if ( $impressions > 0 ) {
			$reasons[] = sprintf( '%s impressions', number_format_i18n( $impressions ) );
			$score += min( 20, (int) round( $impressions / 2000 ) );
		}
		if ( $position > 0 && $position <= 15 ) {
			$reasons[] = sprintf( 'Average position %.1f', $position );
		}

		$page2 = 0;
		foreach ( RevIt_Publisher_Services::editorial_queue()->list_items( array( 'cluster' => $cluster_key, 'limit' => 50 ) ) as $item ) {
			if ( 'refresh_content' === ( $item['action_type'] ?? '' ) && in_array( $item['status'] ?? '', array( 'open', 'in_progress' ), true ) ) {
				++$page2;
			}
		}
		if ( $page2 > 0 ) {
			$reasons[] = sprintf( '%d page-2 opportunities', $page2 );
			$score += $page2 * 5;
		}

		$level = $score >= 65 ? 'high' : ( $score >= 40 ? 'medium' : 'low' );

		return array(
			'cluster_key'       => $cluster_key,
			'name'              => $name,
			'opportunity_level' => $level,
			'opportunity_score' => min( 100, $score ),
			'reasons'           => $reasons,
			'published'         => $published,
			'missing_planned'   => $missing,
			'impressions'       => $impressions,
			'clicks'            => (int) ( $gsc['clicks'] ?? 0 ),
			'position'          => $position,
			'page2_opportunities' => $page2,
		);
	}
}
