<?php
/**
 * Scheduled SEO operations audit engine.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Detects site-wide SEO maintenance issues. Detection only — never auto-modifies content.
 */
class RevIt_Publisher_Audit_Service {

	public const LOCK_KEY       = 'revit_audit_lock';
	public const PROGRESS_KEY   = 'revit_audit_progress';
	public const LOCK_TIMEOUT   = 900;
	public const BATCH_SIZE     = 150;
	public const CRON_HOOK      = 'revit_publisher_scheduled_audit';

	/**
	 * Run a full audit or continue batched progress.
	 *
	 * @return array<string, mixed>
	 */
	public function run( bool $manual = false ): array {
		if ( $this->is_locked() ) {
			return array(
				'success' => false,
				'status'  => 'running',
				'message' => __( 'Audit already running.', 'revit-publisher' ),
			);
		}

		$this->acquire_lock();
		RevIt_Publisher_Services::event_logger()->log( 'audit_start', array( 'manual' => $manual ? 1 : 0 ) );

		try {
			$progress = get_transient( self::PROGRESS_KEY );
			$progress = is_array( $progress ) ? $progress : null;

			if ( null === $progress ) {
				$progress = $this->init_progress();
			}

			$result = $this->process_batch( $progress );

			if ( ! empty( $result['complete'] ) ) {
				delete_transient( self::PROGRESS_KEY );
				$snapshot_id = $this->save_snapshot( $result['summary'] );
				RevIt_Publisher_Services::issues()->reconcile( $result['issues'] );
				RevIt_Publisher_Services::event_logger()->log(
					'audit_complete',
					array(
						'snapshot_id' => $snapshot_id,
						'issues'      => count( $result['issues'] ),
					)
				);
				$this->release_lock();
				return array(
					'success'     => true,
					'status'      => 'complete',
					'snapshot_id' => $snapshot_id,
					'summary'     => $result['summary'],
				);
			}

			set_transient( self::PROGRESS_KEY, $result['progress'], DAY_IN_SECONDS );
			$this->release_lock();
			return array(
				'success'  => true,
				'status'   => 'batch',
				'progress' => $result['progress'],
			);
		} catch ( Throwable $e ) {
			RevIt_Publisher_Services::event_logger()->log( 'audit_failure', array( 'error' => $e->getMessage() ) );
			$this->release_lock();
			delete_transient( self::PROGRESS_KEY );
			return array(
				'success' => false,
				'status'  => 'error',
				'message' => $e->getMessage(),
			);
		}
	}

	/**
	 * @return array<string, mixed>
	 */
	private function init_progress(): array {
		$posts = get_posts(
			array(
				'post_type'      => 'post',
				'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
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

		return array(
			'post_ids'    => array_map( 'intval', $posts ),
			'offset'      => 0,
			'issues'      => array(),
			'summary'     => $this->empty_summary(),
			'started_at'  => gmdate( 'c' ),
		);
	}

	/**
	 * @param array<string, mixed> $progress
	 * @return array<string, mixed>
	 */
	private function process_batch( array $progress ): array {
		$post_ids = (array) ( $progress['post_ids'] ?? array() );
		$offset   = (int) ( $progress['offset'] ?? 0 );
		$batch    = array_slice( $post_ids, $offset, self::BATCH_SIZE );
		$issues   = (array) ( $progress['issues'] ?? array() );
		$summary  = (array) ( $progress['summary'] ?? $this->empty_summary() );

		$graph   = RevIt_Publisher_Services::graph();
		$health  = RevIt_Publisher_Services::health_service();
		$overlaps = RevIt_Publisher_Services::topic_overlaps()->find_overlaps();

		foreach ( $batch as $post_id ) {
			++$summary['articles_scanned'];
			$post_health = $health->get_post_health( (int) $post_id );
			$vehicle     = $graph->get_vehicle_label( (int) $post_id );

			if ( ! empty( $post_health['is_orphan'] ) ) {
				++$summary['orphan_count'];
				$issues[] = $this->make_issue(
					'orphan',
					(int) $post_id,
					$vehicle,
					__( 'Article has no resolved inbound links.', 'revit-publisher' ),
					__( 'Add inbound links from pillar or related articles.', 'revit-publisher' )
				);
			}

			$unresolved = (int) ( $post_health['unresolved_links'] ?? 0 );
			if ( $unresolved > 0 ) {
				$summary['unresolved_link_count'] += $unresolved;
				$issues[] = $this->make_issue(
					'unresolved_link',
					(int) $post_id,
					$vehicle,
					sprintf(
						/* translators: %d: link count */
						__( '%d planned internal links are unresolved.', 'revit-publisher' ),
						$unresolved
					),
					__( 'Import missing targets or apply available links.', 'revit-publisher' )
				);
			}

			if ( ! empty( $post_health['missing_seo_title'] ) || ! empty( $post_health['missing_meta_description'] ) ) {
				++$summary['missing_meta_count'];
				$issues[] = $this->make_issue(
					'missing_meta',
					(int) $post_id,
					$vehicle,
					__( 'SEO metadata is incomplete.', 'revit-publisher' ),
					__( 'Add SEO title and meta description.', 'revit-publisher' )
				);
			}

			if ( ! empty( $post_health['missing_pillar'] ) ) {
				++$summary['missing_pillar_count'];
				$issues[] = $this->make_issue(
					'missing_pillar',
					(int) $post_id,
					$vehicle,
					__( 'Cluster pillar is planned but not imported.', 'revit-publisher' ),
					__( 'Import the pillar article for this cluster.', 'revit-publisher' )
				);
			}

			$review = RevIt_Publisher_Services::review_status()->derive_status( (int) $post_id );
			if ( 'review_due' === $review ) {
				++$summary['review_due_count'];
				$issues[] = $this->make_issue(
					'review_due',
					(int) $post_id,
					$vehicle,
					__( 'Article is due for editorial review.', 'revit-publisher' ),
					__( 'Review content accuracy and update if needed.', 'revit-publisher' )
				);
			}

			foreach ( $graph->get_outbound_relationships( (int) $post_id ) as $link ) {
				if ( in_array( (string) ( $link['status'] ?? '' ), array( 'target_missing', 'unavailable' ), true ) ) {
					++$summary['broken_relationship_count'];
					$issues[] = $this->make_issue(
						'broken_relationship',
						(int) $post_id,
						$vehicle,
						sprintf(
							/* translators: %s: article key */
							__( 'Broken internal target: %s', 'revit-publisher' ),
							(string) ( $link['target_article_key'] ?? '' )
						),
						__( 'Fix article key reference or import target.', 'revit-publisher' ),
						array( 'target_article_key' => (string) ( $link['target_article_key'] ?? '' ) )
					);
				}
			}
		}

		$new_offset = $offset + count( $batch );
		$complete   = $new_offset >= count( $post_ids );

		if ( $complete ) {
			$summary = $this->finalize_summary( $summary, $overlaps, $issues );
		}

		return array(
			'complete' => $complete,
			'progress' => array(
				'post_ids'   => $post_ids,
				'offset'     => $new_offset,
				'issues'     => $issues,
				'summary'    => $summary,
				'started_at' => (string) ( $progress['started_at'] ?? gmdate( 'c' ) ),
			),
			'issues'   => $issues,
			'summary'  => $summary,
		);
	}

	/**
	 * @param array<int, array<string, mixed>> $issues
	 * @return array<string, mixed>
	 */
	private function finalize_summary( array $summary, array $overlaps, array &$issues ): array {
		$plans = RevIt_Publisher_Services::plan_service()->list_plans();
		$missing = 0;
		foreach ( $plans as $plan ) {
			$missing += (int) ( $plan['summary']['missing_articles'] ?? 0 );
		}
		$summary['missing_content_count'] = $missing;
		$summary['vehicles_scanned']      = count( RevIt_Publisher_Services::graph()->get_vehicle_summaries() );
		$summary['clusters_scanned']      = count( RevIt_Publisher_Services::graph()->get_cluster_summaries() );

		$high_overlap = 0;
		foreach ( $overlaps as $overlap ) {
			if ( 'high' !== ( $overlap['risk'] ?? '' ) ) {
				continue;
			}
			++$high_overlap;
			$issues[] = array(
				'issue_type'         => 'topic_overlap',
				'title'              => sprintf(
					'%s vs %s',
					(string) ( $overlap['title_a'] ?? '' ),
					(string) ( $overlap['title_b'] ?? '' )
				),
				'post_id'            => (int) ( $overlap['post_id_a'] ?? 0 ),
				'article_key'        => '',
				'vehicle'            => (string) ( $overlap['vehicle'] ?? '' ),
				'cluster_key'        => (string) ( $overlap['cluster'] ?? '' ),
				'explanation'        => sprintf(
					/* translators: %s: overlap percentage */
					__( 'Potential topic overlap (%s).', 'revit-publisher' ),
					(string) ( $overlap['similarity'] ?? '' )
				),
				'recommended_action' => __( 'Review search intent before publishing both.', 'revit-publisher' ),
				'context'            => array( 'risk' => 'high', 'post_id_b' => (int) ( $overlap['post_id_b'] ?? 0 ) ),
			);
		}
		$summary['high_overlap_count'] = $high_overlap;

		$scores = array();
		foreach ( RevIt_Publisher_Services::registry()->get_managed_post_ids() as $pid ) {
			$analysis = RevIt_Publisher_Services::seo_score()->analyze( (int) $pid );
			$scores[] = (int) ( $analysis['total_score'] ?? 0 );
		}
		$summary['overall_health_avg'] = ! empty( $scores )
			? (int) round( array_sum( $scores ) / count( $scores ) )
			: 0;

		$summary['vehicle_summaries'] = RevIt_Publisher_Services::vehicle_health()->get_all_vehicle_summaries();

		return $summary;
	}

	/**
	 * @param array<string, mixed> $context
	 * @return array<string, mixed>
	 */
	private function make_issue(
		string $type,
		int $post_id,
		string $vehicle,
		string $explanation,
		string $action,
		array $context = array()
	): array {
		return array(
			'issue_type'         => $type,
			'title'              => get_the_title( $post_id ) ?: (string) $post_id,
			'post_id'            => $post_id,
			'article_key'        => (string) get_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::ARTICLE_KEY, true ),
			'vehicle'            => $vehicle,
			'cluster_key'        => '',
			'explanation'        => $explanation,
			'recommended_action' => $action,
			'context'            => $context,
		);
	}

	/**
	 * @param array<string, mixed> $summary
	 */
	private function save_snapshot( array $summary ): int {
		$title = sprintf(
			/* translators: %s: datetime */
			__( 'Audit %s', 'revit-publisher' ),
			gmdate( 'Y-m-d H:i:s' )
		);

		$snapshot_id = wp_insert_post(
			array(
				'post_type'   => RevIt_Publisher_Operations_Post_Types::AUDIT_SNAPSHOT,
				'post_status' => 'private',
				'post_title'  => sanitize_text_field( $title ),
			),
			true
		);

		if ( is_wp_error( $snapshot_id ) ) {
			return 0;
		}

		update_post_meta( $snapshot_id, RevIt_Publisher_Operations_Meta_Keys::SNAPSHOT_DATA, wp_json_encode( $summary ) );
		update_post_meta( $snapshot_id, RevIt_Publisher_Operations_Meta_Keys::SNAPSHOT_CREATED_AT, gmdate( 'c' ) );

		return (int) $snapshot_id;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function list_snapshots( int $limit = 30 ): array {
		$posts = get_posts(
			array(
				'post_type'      => RevIt_Publisher_Operations_Post_Types::AUDIT_SNAPSHOT,
				'post_status'    => 'private',
				'posts_per_page' => $limit,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);

		$out = array();
		foreach ( $posts as $index => $post ) {
			$data = json_decode( (string) get_post_meta( $post->ID, RevIt_Publisher_Operations_Meta_Keys::SNAPSHOT_DATA, true ), true );
			$data = is_array( $data ) ? $data : array();
			$trend = array( 'orphans' => 'unchanged', 'unresolved_links' => 'unchanged', 'missing_content' => 'unchanged' );
			if ( isset( $posts[ $index + 1 ] ) ) {
				$prev = json_decode( (string) get_post_meta( $posts[ $index + 1 ]->ID, RevIt_Publisher_Operations_Meta_Keys::SNAPSHOT_DATA, true ), true );
				$prev = is_array( $prev ) ? $prev : array();
				$trend = $this->calculate_trends( $data, $prev );
			}
			$out[] = array(
				'snapshot_id' => $post->ID,
				'created_at'  => (string) get_post_meta( $post->ID, RevIt_Publisher_Operations_Meta_Keys::SNAPSHOT_CREATED_AT, true ),
				'summary'     => $data,
				'trends'      => $trend,
			);
		}
		return $out;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function get_snapshot( int $snapshot_id ): ?array {
		$post = get_post( $snapshot_id );
		if ( ! $post instanceof WP_Post || RevIt_Publisher_Operations_Post_Types::AUDIT_SNAPSHOT !== $post->post_type ) {
			return null;
		}
		$data = json_decode( (string) get_post_meta( $snapshot_id, RevIt_Publisher_Operations_Meta_Keys::SNAPSHOT_DATA, true ), true );
		return array(
			'snapshot_id' => $snapshot_id,
			'created_at'  => (string) get_post_meta( $snapshot_id, RevIt_Publisher_Operations_Meta_Keys::SNAPSHOT_CREATED_AT, true ),
			'summary'     => is_array( $data ) ? $data : array(),
		);
	}

	/**
	 * @param array<string, mixed> $current
	 * @param array<string, mixed> $previous
	 * @return array<string, string>
	 */
	public function calculate_trends( array $current, array $previous ): array {
		return array(
			'orphans'          => $this->trend_direction( (int) ( $current['orphan_count'] ?? 0 ), (int) ( $previous['orphan_count'] ?? 0 ) ),
			'unresolved_links' => $this->trend_direction( (int) ( $current['unresolved_link_count'] ?? 0 ), (int) ( $previous['unresolved_link_count'] ?? 0 ) ),
			'missing_content'  => $this->trend_direction( (int) ( $current['missing_content_count'] ?? 0 ), (int) ( $previous['missing_content_count'] ?? 0 ) ),
			'high_overlap'     => $this->trend_direction( (int) ( $current['high_overlap_count'] ?? 0 ), (int) ( $previous['high_overlap_count'] ?? 0 ) ),
			'seo_health'       => $this->trend_direction( (int) ( $previous['overall_health_avg'] ?? 0 ), (int) ( $current['overall_health_avg'] ?? 0 ), true ),
		);
	}

	private function trend_direction( int $current, int $previous, bool $higher_is_better = false ): string {
		if ( $current === $previous ) {
			return 'unchanged';
		}
		if ( $higher_is_better ) {
			return $current > $previous ? 'improved' : 'worsened';
		}
		return $current < $previous ? 'improved' : 'worsened';
	}

	/**
	 * @return array<string, int>
	 */
	private function empty_summary(): array {
		return array(
			'articles_scanned'          => 0,
			'vehicles_scanned'          => 0,
			'clusters_scanned'          => 0,
			'orphan_count'              => 0,
			'unresolved_link_count'     => 0,
			'missing_content_count'     => 0,
			'high_overlap_count'        => 0,
			'review_due_count'          => 0,
			'missing_meta_count'        => 0,
			'missing_pillar_count'      => 0,
			'broken_relationship_count' => 0,
			'overall_health_avg'        => 0,
		);
	}

	public function is_locked(): bool {
		$lock = get_transient( self::LOCK_KEY );
		if ( ! is_array( $lock ) ) {
			return false;
		}
		$started = (int) ( $lock['started'] ?? 0 );
		if ( time() - $started > self::LOCK_TIMEOUT ) {
			delete_transient( self::LOCK_KEY );
			return false;
		}
		return true;
	}

	private function acquire_lock(): void {
		set_transient( self::LOCK_KEY, array( 'started' => time() ), self::LOCK_TIMEOUT );
	}

	private function release_lock(): void {
		delete_transient( self::LOCK_KEY );
	}
}
