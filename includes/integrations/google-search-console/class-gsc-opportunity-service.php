<?php
/**
 * Explainable Search Console opportunity detection.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RevIt_Publisher_GSC_Opportunity_Service {

	private RevIt_Publisher_GSC_Data_Store $store;
	private RevIt_Publisher_Settings $settings;

	public function __construct( RevIt_Publisher_GSC_Data_Store $store, RevIt_Publisher_Settings $settings ) {
		$this->store    = $store;
		$this->settings = $settings;
	}

	public function detect_and_reconcile(): void {
		$detected = array_merge(
			$this->detect_performance_opportunities(),
			RevIt_Publisher_Services::gsc_inspections()->detect_index_issues()
		);
		RevIt_Publisher_Services::issues()->reconcile( $detected );
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function list_opportunities( string $window_key = '28d' ): array {
		return $this->detect_performance_opportunities( $window_key );
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private function detect_performance_opportunities( string $window_key = '28d' ): array {
		$issues      = array();
		$min_impr    = $this->settings->gsc_min_impressions();
		$page2_min   = $this->settings->gsc_page2_min();
		$page2_max   = $this->settings->gsc_page2_max();
		$decline_pct = $this->settings->gsc_decline_threshold_pct();
		$grace_days  = $this->settings->gsc_zero_visibility_days();

		foreach ( RevIt_Publisher_Services::registry()->get_managed_post_ids() as $post_id ) {
			if ( 'publish' !== get_post_status( (int) $post_id ) ) {
				continue;
			}
			$metrics = $this->store->get_post_metrics( (int) $post_id, $window_key );
			if ( null === $metrics ) {
				continue;
			}
			$clicks      = (int) ( $metrics['clicks'] ?? 0 );
			$impressions = (int) ( $metrics['impressions'] ?? 0 );
			$ctr         = (float) ( $metrics['ctr'] ?? 0 );
			$position    = (float) ( $metrics['position'] ?? 0 );
			$title       = get_the_title( (int) $post_id );
			$vehicle     = RevIt_Publisher_Services::graph()->get_vehicle_label( (int) $post_id );
			$article_key = (string) get_post_meta( (int) $post_id, RevIt_Publisher_Post_Meta_Keys::ARTICLE_KEY, true );

			if ( $impressions >= $min_impr && $position >= $page2_min && $position <= $page2_max ) {
				$issues[] = $this->issue(
					'gsc_page2_opportunity',
					$title,
					(int) $post_id,
					$vehicle,
					$article_key,
					'Page 2 optimization opportunity based on Search Console position and impressions.',
					'Review content depth, internal links, and title/meta alignment.',
					array(
						'position'    => $position,
						'impressions' => $impressions,
						'clicks'      => $clicks,
						'ctr'         => $ctr,
						'reasons'     => array(
							sprintf( 'Average position between %d and %d', $page2_min, $page2_max ),
							sprintf( '>%d impressions in last %s', $min_impr, $window_key ),
						),
					)
				);
			}

			if ( $impressions >= $min_impr && $position <= 15 && $ctr < 0.02 ) {
				$issues[] = $this->issue(
					'gsc_low_ctr_opportunity',
					$title,
					(int) $post_id,
					$vehicle,
					$article_key,
					'High impressions with unusually low CTR.',
					'Review title/meta/search intent alignment.',
					array(
						'position'    => $position,
						'impressions' => $impressions,
						'clicks'      => $clicks,
						'ctr'         => $ctr,
						'reasons'     => array(
							sprintf( '>%d impressions', $min_impr ),
							'CTR below 2% with position in top 15',
						),
					)
				);
			}

			$prev_key = '7d' === $window_key ? 'prev_7d' : 'prev_28d';
			$prev     = $this->store->get_post_metrics( (int) $post_id, $prev_key );
			if ( is_array( $prev ) && $impressions > 0 ) {
				$prev_impr = (int) ( $prev['impressions'] ?? 0 );
				if ( $prev_impr > 0 ) {
					$change = ( ( $impressions - $prev_impr ) / $prev_impr ) * 100;
					if ( $change >= $decline_pct && $impressions >= $min_impr ) {
						$issues[] = $this->issue(
							'gsc_growing_page',
							$title,
							(int) $post_id,
							$vehicle,
							$article_key,
							'Search Console impressions increasing versus previous comparable period.',
							'Monitor momentum; consider reinforcing internal links to this page.',
							array(
								'change_pct'       => round( $change, 1 ),
								'impressions'      => $impressions,
								'prev_impressions' => $prev_impr,
								'reasons'          => array(
									sprintf( 'Impressions increased %.1f%%', $change ),
									sprintf( '>%d impressions in last %s', $min_impr, $window_key ),
								),
							)
						);
					}
					if ( $change <= -$decline_pct ) {
						$issues[] = $this->issue(
							'gsc_declining_page',
							$title,
							(int) $post_id,
							$vehicle,
							$article_key,
							'Search Console impressions declining versus previous period.',
							'Consider content refresh based on performance decline.',
							array(
								'change_pct'  => round( $change, 1 ),
								'impressions' => $impressions,
								'prev_impressions' => $prev_impr,
								'reasons'     => array(
									sprintf( 'Impressions declined %.1f%%', abs( $change ) ),
								),
							),
							'search_refresh_opportunity'
						);
					}
				}
			}

			if ( 0 === $impressions && $this->is_past_grace_period( (int) $post_id, $grace_days ) ) {
				$issues[] = $this->issue(
					'gsc_zero_visibility',
					$title,
					(int) $post_id,
					$vehicle,
					$article_key,
					'Published indexable article with zero Search Console impressions after grace period.',
					'Review indexing, internal links, and whether the topic matches search demand.',
					array(
						'grace_days' => $grace_days,
						'reasons'    => array(
							sprintf( 'Zero impressions after %d day grace period', $grace_days ),
						),
					),
					'search_refresh_opportunity'
				);
			}
		}
		return $issues;
	}

	/**
	 * @param array<string, mixed> $context
	 */
	private function issue(
		string $type,
		string $title,
		int $post_id,
		string $vehicle,
		string $article_key,
		string $explanation,
		string $action,
		array $context,
		string $recommended = ''
	): array {
		return array(
			'issue_type'         => $type,
			'title'              => $title,
			'post_id'            => $post_id,
			'vehicle'            => $vehicle,
			'article_key'        => $article_key,
			'cluster_key'        => (string) get_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::CLUSTER_KEY, true ),
			'explanation'        => $explanation,
			'recommended_action' => $action,
			'context'            => array_merge( $context, array( 'recommended' => $recommended ) ),
		);
	}

	private function is_past_grace_period( int $post_id, int $grace_days ): bool {
		$published = get_post_time( 'U', true, $post_id );
		if ( ! $published ) {
			return false;
		}
		return ( time() - (int) $published ) > ( $grace_days * DAY_IN_SECONDS );
	}
}
