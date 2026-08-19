<?php
/**
 * Vehicle-level editorial opportunity summary.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RevIt_Publisher_Vehicle_Opportunity_Service {

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function list_all(): array {
		$out = array();
		foreach ( RevIt_Publisher_Services::vehicle_health()->get_all_vehicle_summaries() as $summary ) {
			$label = (string) ( $summary['label'] ?? '' );
			if ( '' !== $label ) {
				$out[] = $this->summarize_vehicle( $label, $summary );
			}
		}
		usort( $out, static fn( $a, $b ) => ( $b['opportunity_score'] ?? 0 ) <=> ( $a['opportunity_score'] ?? 0 ) );
		return $out;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function summarize_vehicle( string $vehicle_label, ?array $summary = null ): array {
		$summary = $summary ?? $this->find_summary( $vehicle_label );
		$reasons = array();
		$score   = 30;

		$missing = (int) ( $summary['missing_articles'] ?? 0 );
		if ( $missing > 0 ) {
			$reasons[] = sprintf( '%d missing planned articles', $missing );
			$score += min( 20, $missing * 3 );
		}

		$coverage = (int) ( $summary['plan_coverage'] ?? 0 );
		if ( $coverage < 70 ) {
			$reasons[] = sprintf( 'Plan coverage %d%%', $coverage );
			$score += 10;
		}

		$queue_items = array_filter(
			RevIt_Publisher_Services::editorial_queue()->list_items( array( 'vehicle' => $vehicle_label, 'limit' => 50 ) ),
			static fn( array $item ) => in_array( $item['status'] ?? '', array( 'open', 'in_progress' ), true )
		);
		$page2 = 0;
		foreach ( $queue_items as $item ) {
			if ( 'refresh_content' === ( $item['action_type'] ?? '' ) ) {
				++$page2;
			}
		}
		if ( $page2 > 0 ) {
			$reasons[] = sprintf( '%d refresh opportunities', $page2 );
			$score += min( 15, $page2 * 5 );
		}

		if ( RevIt_Publisher_Services::gsc_auth()->is_connected() ) {
			$vehicles = RevIt_Publisher_Services::gsc_insights()->get_vehicle_performance( '28d' );
			foreach ( $vehicles as $v ) {
				if ( ( $v['vehicle'] ?? '' ) === $vehicle_label ) {
					$prev = RevIt_Publisher_Services::gsc_data_store()->get_summary( 'prev_28d' );
					unset( $prev );
					$change = RevIt_Publisher_Services::gsc_insights()->get_summary_with_comparison( '28d' );
					if ( ( $change['change']['impressions_pct'] ?? 0 ) > 10 ) {
						$reasons[] = sprintf( 'Organic impressions +%.0f%%', (float) $change['change']['impressions_pct'] );
						$score += 15;
					}
					break;
				}
			}
		}

		$level = $score >= 70 ? 'high' : ( $score >= 45 ? 'medium' : 'low' );

		return array(
			'vehicle'           => $vehicle_label,
			'opportunity_level' => $level,
			'opportunity_score' => min( 100, $score ),
			'reasons'           => $reasons,
			'plan_coverage'     => $coverage,
			'missing_articles'  => $missing,
			'seo_health_avg'    => (int) ( $summary['seo_health_avg'] ?? 0 ),
			'queue_open'        => count( $queue_items ),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function for_hub_id( int $hub_id ): array {
		$identity = RevIt_Publisher_Services::vehicle_hubs()->get_identity( $hub_id );
		$label    = trim(
			sprintf(
				'%s %s %s',
				(string) ( $identity['manufacturer'] ?? '' ),
				(string) ( $identity['model'] ?? '' ),
				(string) ( $identity['trim'] ?? '' )
			)
		);
		return $this->summarize_vehicle( $label );
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private function find_summary( string $label ): ?array {
		foreach ( RevIt_Publisher_Services::vehicle_health()->get_all_vehicle_summaries() as $summary ) {
			if ( ( $summary['label'] ?? '' ) === $label ) {
				return $summary;
			}
		}
		return array( 'label' => $label );
	}
}
