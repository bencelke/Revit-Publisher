<?php
/**
 * Deterministic editorial priority calculation.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RevIt_Publisher_Editorial_Priority_Service {

	public const ACTION_CREATE          = 'create_content';
	public const ACTION_REFRESH         = 'refresh_content';
	public const ACTION_LINKS           = 'fix_internal_links';
	public const ACTION_INDEXING        = 'resolve_indexing';
	public const ACTION_OVERLAP         = 'resolve_topic_overlap';
	public const ACTION_CLUSTER         = 'complete_cluster';
	public const ACTION_REVIEW          = 'review_article';
	public const ACTION_METADATA        = 'fix_metadata';

	public const LEVEL_URGENT  = 'urgent';
	public const LEVEL_HIGH    = 'high';
	public const LEVEL_MEDIUM  = 'medium';
	public const LEVEL_LOW     = 'low';

	/**
	 * Detect editorial work candidates from all signals.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function detect_candidates(): array {
		$candidates = array();
		$candidates = array_merge( $candidates, $this->detect_from_posts() );
		$candidates = array_merge( $candidates, $this->detect_from_plans() );
		$candidates = array_merge( $candidates, $this->detect_cluster_gaps() );
		return $this->deduplicate_candidates( $candidates );
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private function detect_from_posts(): array {
		$items = array();
		$graph = RevIt_Publisher_Services::graph();
		$health_service = RevIt_Publisher_Services::health_service();

		foreach ( RevIt_Publisher_Services::registry()->get_managed_post_ids() as $post_id ) {
			$post_id = (int) $post_id;
			$title   = get_the_title( $post_id );
			$vehicle = $graph->get_vehicle_label( $post_id );
			$cluster = (string) get_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::CLUSTER_KEY, true );
			$article_key = (string) get_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::ARTICLE_KEY, true );
			$post_health = $health_service->get_post_health( $post_id );
			$seo_score   = (int) ( RevIt_Publisher_Services::seo_score()->analyze( $post_id )['total_score'] ?? 0 );

			$refresh_reasons = array();
			$refresh_signals = array();

			if ( RevIt_Publisher_Services::gsc_auth()->is_connected() ) {
				$metrics = RevIt_Publisher_Services::gsc_data_store()->get_post_metrics( $post_id, '28d' );
				if ( is_array( $metrics ) ) {
					$impressions = (int) ( $metrics['impressions'] ?? 0 );
					$position    = (float) ( $metrics['position'] ?? 0 );
					$ctr         = (float) ( $metrics['ctr'] ?? 0 );
					$refresh_signals['gsc'] = $metrics;

					if ( $impressions >= RevIt_Publisher_Services::settings()->gsc_min_impressions() ) {
						if ( $position >= RevIt_Publisher_Services::settings()->gsc_page2_min()
							&& $position <= RevIt_Publisher_Services::settings()->gsc_page2_max() ) {
							$refresh_reasons[] = sprintf(
								'Average position %.1f with %s impressions in last 28 days.',
								$position,
								number_format_i18n( $impressions )
							);
						}
						if ( $position <= 15 && $ctr < 0.02 ) {
							$refresh_reasons[] = 'High impressions with CTR below 2% in top positions.';
						}
					}

					$prev = RevIt_Publisher_Services::gsc_data_store()->get_post_metrics( $post_id, 'prev_28d' );
					if ( is_array( $prev ) ) {
						$prev_impr = (int) ( $prev['impressions'] ?? 0 );
						if ( $prev_impr > 0 && $impressions > 0 ) {
							$change = ( ( $impressions - $prev_impr ) / $prev_impr ) * 100;
							if ( $change <= -RevIt_Publisher_Services::settings()->gsc_decline_threshold_pct() ) {
								$refresh_reasons[] = sprintf( 'Impressions declined %.1f%% versus previous period.', abs( $change ) );
							}
						}
					}
				}
			}

			$open_issues = $this->open_issues_for_post( $post_id );
			foreach ( $open_issues as $issue ) {
				$type = (string) ( $issue['issue_type'] ?? '' );
				if ( in_array( $type, array( 'gsc_index_issue', 'gsc_canonical_mismatch' ), true ) ) {
					$items[] = $this->build_item(
						self::ACTION_INDEXING,
						$type === 'gsc_canonical_mismatch' ? self::LEVEL_URGENT : self::LEVEL_HIGH,
						$title,
						$post_id,
						$article_key,
						$vehicle,
						$cluster,
						0,
						(string) ( $issue['explanation'] ?? 'Indexing issue detected.' ),
						array( (string) ( $issue['explanation'] ?? '' ) ),
						array( 'issue' => $issue ),
						__( 'Inspect URL in Search Console and resolve indexing/canonical mismatch.', 'revit-publisher' ),
						$seo_score
					);
				}
				if ( 'gsc_unexpected_query' === $type ) {
					$refresh_reasons[] = (string) ( $issue['explanation'] ?? 'Unexpected Google query detected.' );
				}
				if ( in_array( $type, array( 'gsc_page2_opportunity', 'gsc_low_ctr_opportunity', 'gsc_declining_page' ), true ) ) {
					$refresh_reasons[] = (string) ( $issue['explanation'] ?? '' );
				}
			}

			if ( ! empty( $refresh_reasons ) ) {
				$level = $seo_score >= 80 ? self::LEVEL_HIGH : self::LEVEL_MEDIUM;
				$explanation = $seo_score >= 80
					? 'Article is structurally healthy; opportunity is likely query/content alignment rather than technical SEO.'
					: 'Review content and technical SEO signals together.';
				$items[] = $this->build_item(
					self::ACTION_REFRESH,
					$level,
					$title,
					$post_id,
					$article_key,
					$vehicle,
					$cluster,
					0,
					$explanation,
					array_values( array_unique( array_filter( $refresh_reasons ) ) ),
					$refresh_signals,
					__( 'Review top Google queries and update content only if useful information is missing.', 'revit-publisher' ),
					$seo_score
				);
			}

			if ( ! empty( $post_health['is_orphan'] ) ) {
				$items[] = $this->build_item(
					self::ACTION_LINKS,
					self::LEVEL_MEDIUM,
					$title,
					$post_id,
					$article_key,
					$vehicle,
					$cluster,
					0,
					'Article has no resolved inbound internal links.',
					array( 'No inbound pillar or cluster links resolved.' ),
					array(),
					__( 'Add inbound links from pillar or related articles.', 'revit-publisher' ),
					$seo_score
				);
			}

			$outbound = $graph->get_outbound_relationships( $post_id );
			$unresolved = array_filter( $outbound, static fn( $l ) => 'unresolved' === ( $l['status'] ?? '' ) );
			if ( count( $unresolved ) > 0 ) {
				$items[] = $this->build_item(
					self::ACTION_LINKS,
					self::LEVEL_MEDIUM,
					$title,
					$post_id,
					$article_key,
					$vehicle,
					$cluster,
					0,
					sprintf( '%d unresolved internal link(s).', count( $unresolved ) ),
					array( sprintf( '%d unresolved outbound links.', count( $unresolved ) ) ),
					array(),
					__( 'Resolve or apply suggested internal links.', 'revit-publisher' ),
					$seo_score
				);
			}

			if ( empty( get_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::SEO_TITLE, true ) )
				|| empty( get_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::META_DESCRIPTION, true ) ) ) {
				$items[] = $this->build_item(
					self::ACTION_METADATA,
					self::LEVEL_MEDIUM,
					$title,
					$post_id,
					$article_key,
					$vehicle,
					$cluster,
					0,
					'SEO title or meta description is missing.',
					array( 'Missing title and/or meta description.' ),
					array(),
					__( 'Complete SEO metadata in the article panel.', 'revit-publisher' ),
					$seo_score
				);
			}

			if ( RevIt_Publisher_Services::review_status()->is_review_due( $post_id ) ) {
				$items[] = $this->build_item(
					self::ACTION_REVIEW,
					self::LEVEL_MEDIUM,
					$title,
					$post_id,
					$article_key,
					$vehicle,
					$cluster,
					0,
					'Article is due for editorial review.',
					array( 'Review period elapsed.' ),
					array(),
					__( 'Review article accuracy and update if needed.', 'revit-publisher' ),
					$seo_score
				);
			}
		}

		$overlaps = RevIt_Publisher_Services::topic_overlaps()->find_overlaps();
		foreach ( $overlaps as $overlap ) {
			if ( 'high' !== ( $overlap['risk'] ?? '' ) ) {
				continue;
			}
			$post_id = (int) ( $overlap['post_id'] ?? 0 );
			if ( $post_id <= 0 ) {
				continue;
			}
			$items[] = $this->build_item(
				self::ACTION_OVERLAP,
				self::LEVEL_HIGH,
				get_the_title( $post_id ),
				$post_id,
				(string) get_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::ARTICLE_KEY, true ),
				$graph->get_vehicle_label( $post_id ),
				(string) get_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::CLUSTER_KEY, true ),
				0,
				'High-risk topic overlap detected.',
				array( (string) ( $overlap['explanation'] ?? 'Topic overlap with another article.' ) ),
				array( 'overlap' => $overlap ),
				__( 'Review overlap and consider consolidation or differentiation.', 'revit-publisher' ),
				(int) ( RevIt_Publisher_Services::seo_score()->analyze( $post_id )['total_score'] ?? 0 )
			);
		}

		return $items;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private function detect_from_plans(): array {
		$items = array();
		foreach ( RevIt_Publisher_Services::plan_service()->list_plans() as $plan ) {
			$plan_id = (int) ( $plan['plan_id'] ?? 0 );
			if ( $plan_id <= 0 ) {
				continue;
			}
			$coverage = RevIt_Publisher_Services::plan_service()->get_coverage( $plan_id );
			$vehicle  = (string) ( $coverage['vehicle'] ?? '' );

			foreach ( (array) ( $coverage['missing'] ?? array() ) as $missing ) {
				$priority = (int) ( $missing['priority'] ?? 0 );
				$items[] = $this->build_item(
					self::ACTION_CREATE,
					$priority >= 80 ? self::LEVEL_HIGH : ( $priority >= 50 ? self::LEVEL_MEDIUM : self::LEVEL_LOW ),
					(string) ( $missing['title'] ?? '' ),
					0,
					(string) ( $missing['article_key'] ?? '' ),
					$vehicle,
					(string) ( $missing['cluster_key'] ?? '' ),
					$plan_id,
					'Planned article is not yet published.',
					array(
						sprintf( 'Content plan priority: %d', $priority ),
						'Article missing from site registry.',
					),
					array( 'plan_priority' => $priority ),
					__( 'Create article from content plan request export.', 'revit-publisher' ),
					0,
					$priority
				);
			}
		}
		return $items;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private function detect_cluster_gaps(): array {
		$items = array();
		foreach ( RevIt_Publisher_Services::plan_service()->list_plans() as $plan ) {
			$plan_id  = (int) ( $plan['plan_id'] ?? 0 );
			$coverage = RevIt_Publisher_Services::plan_service()->get_coverage( $plan_id );
			$vehicle  = (string) ( $coverage['vehicle'] ?? '' );
			foreach ( (array) ( $coverage['clusters'] ?? array() ) as $cluster ) {
				if ( 'missing' === ( $cluster['pillar_status'] ?? '' ) ) {
					$key = (string) ( $cluster['cluster_key'] ?? '' );
					$items[] = $this->build_item(
						self::ACTION_CLUSTER,
						self::LEVEL_HIGH,
						(string) ( $cluster['name'] ?? $key ) . ' — Pillar',
						0,
						'',
						$vehicle,
						$key,
						$plan_id,
						'Cluster pillar article is missing.',
						array( 'Missing pillar for cluster ' . $key ),
						array(),
						__( 'Create or publish the cluster pillar article.', 'revit-publisher' ),
						0
					);
				}
			}
		}
		return $items;
	}

	/**
	 * @param array<int, array<string, mixed>> $candidates
	 * @return array<int, array<string, mixed>>
	 */
	private function deduplicate_candidates( array $candidates ): array {
		$merged = array();
		foreach ( $candidates as $candidate ) {
			$fp = (string) ( $candidate['fingerprint'] ?? '' );
			if ( isset( $merged[ $fp ] ) ) {
				$merged[ $fp ]['reasons'] = array_values(
					array_unique(
						array_merge(
							(array) ( $merged[ $fp ]['reasons'] ?? array() ),
							(array) ( $candidate['reasons'] ?? array() )
						)
					)
				);
				$merged[ $fp ]['priority_score'] = max(
					(int) ( $merged[ $fp ]['priority_score'] ?? 0 ),
					(int) ( $candidate['priority_score'] ?? 0 )
				);
				continue;
			}
			$merged[ $fp ] = $candidate;
		}
		usort(
			$merged,
			static fn( $a, $b ) => ( $b['priority_score'] ?? 0 ) <=> ( $a['priority_score'] ?? 0 )
		);
		return array_values( $merged );
	}

	/**
	 * @param string[] $reasons
	 * @param array<string, mixed> $signals
	 */
	private function build_item(
		string $action,
		string $level,
		string $title,
		int $post_id,
		string $article_key,
		string $vehicle,
		string $cluster_key,
		int $plan_id,
		string $explanation,
		array $reasons,
		array $signals,
		string $next_step,
		int $seo_score,
		int $plan_priority = 0
	): array {
		$score = $this->calculate_score( $action, $level, $signals, $plan_priority, $seo_score );
		return array(
			'action_type'     => $action,
			'priority_level'  => $this->level_from_score( $level, $score ),
			'priority_score'  => $score,
			'title'           => $title,
			'post_id'         => $post_id,
			'article_key'     => $article_key,
			'vehicle'         => $vehicle,
			'cluster_key'     => $cluster_key,
			'plan_id'         => $plan_id,
			'explanation'     => $explanation,
			'reasons'         => $reasons,
			'signals'         => $signals,
			'next_step'       => $next_step,
			'seo_health'      => $seo_score,
			'fingerprint'     => $this->fingerprint( $action, $post_id, $article_key, $cluster_key, $vehicle, $plan_id ),
		);
	}

	/**
	 * @param array<string, mixed> $signals
	 */
	public function calculate_score( string $action, string $level, array $signals, int $plan_priority, int $seo_score ): int {
		$score = match ( $action ) {
			self::ACTION_INDEXING => 85,
			self::ACTION_CREATE   => 40 + min( 20, (int) round( $plan_priority / 5 ) ),
			self::ACTION_REFRESH  => 55,
			self::ACTION_CLUSTER  => 60,
			self::ACTION_OVERLAP  => 65,
			self::ACTION_LINKS    => 45,
			self::ACTION_METADATA => 35,
			self::ACTION_REVIEW   => 40,
			default               => 30,
		};

		if ( self::LEVEL_URGENT === $level ) {
			$score = max( $score, 90 );
		}

		$gsc = (array) ( $signals['gsc'] ?? array() );
		$impressions = (int) ( $gsc['impressions'] ?? 0 );
		$score += min( 20, (int) round( $impressions / 1000 ) );

		if ( $seo_score >= 85 && self::ACTION_REFRESH === $action ) {
			$score += 10;
		}

		return min( 100, max( 0, $score ) );
	}

	private function level_from_score( string $suggested, int $score ): string {
		if ( self::LEVEL_URGENT === $suggested ) {
			return self::LEVEL_URGENT;
		}
		if ( $score >= 75 ) {
			return self::LEVEL_HIGH;
		}
		if ( $score >= 50 ) {
			return self::LEVEL_MEDIUM;
		}
		return self::LEVEL_LOW;
	}

	public function fingerprint(
		string $action,
		int $post_id,
		string $article_key,
		string $cluster_key,
		string $vehicle,
		int $plan_id
	): string {
		$key = $action . '|' . $post_id . '|' . $article_key . '|' . $cluster_key . '|' . $vehicle . '|' . $plan_id;
		return hash( 'sha256', $key );
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private function open_issues_for_post( int $post_id ): array {
		return array_values(
			array_filter(
				RevIt_Publisher_Services::issues()->list_issues( array( 'status' => 'open' ) ),
				static fn( array $issue ): bool => (int) ( $issue['post_id'] ?? 0 ) === $post_id
			)
		);
	}
}
