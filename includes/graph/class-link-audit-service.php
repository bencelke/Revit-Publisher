<?php
/**
 * Site-wide link audit service.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Audits planned relationships across all RevIt-managed posts.
 */
class RevIt_Publisher_Link_Audit_Service {

	public const TRANSIENT_KEY = 'revit_publisher_link_audit';

	/**
	 * Content graph.
	 *
	 * @var RevIt_Publisher_Content_Graph
	 */
	private RevIt_Publisher_Content_Graph $graph;

	/**
	 * Internal link service.
	 *
	 * @var RevIt_Publisher_Internal_Link_Service
	 */
	private RevIt_Publisher_Internal_Link_Service $link_service;

	/**
	 * SEO health service.
	 *
	 * @var RevIt_Publisher_SEO_Health_Service
	 */
	private RevIt_Publisher_SEO_Health_Service $health_service;

	/**
	 * Constructor.
	 */
	public function __construct(
		RevIt_Publisher_Content_Graph $graph,
		RevIt_Publisher_Internal_Link_Service $link_service,
		RevIt_Publisher_SEO_Health_Service $health_service
	) {
		$this->graph          = $graph;
		$this->link_service   = $link_service;
		$this->health_service = $health_service;
	}

	/**
	 * Run full link audit.
	 *
	 * @return array<string, mixed>
	 */
	public function audit_all_links(): array {
		$posts = get_posts(
			array(
				'post_type'      => 'post',
				'post_status'    => 'any',
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

		$total_planned = 0;
		$resolved      = 0;
		$unresolved    = 0;
		$broken        = 0;
		$opportunities = 0;

		foreach ( $posts as $post_id ) {
			foreach ( $this->graph->get_outbound_relationships( (int) $post_id ) as $link ) {
				++$total_planned;
				$status = (string) ( $link['status'] ?? '' );
				if ( 'resolved' === $status ) {
					++$resolved;
				} elseif ( 'target_missing' === $status ) {
					++$broken;
					++$unresolved;
				} else {
					++$unresolved;
				}
			}

			$opportunities += count( $this->link_service->get_backlink_opportunities( (int) $post_id ) );
		}

		$result = array(
			'total_planned'            => $total_planned,
			'resolved'                 => $resolved,
			'unresolved'               => $unresolved,
			'broken'                   => $broken,
			'orphan_posts'             => count( $this->health_service->get_orphans() ),
			'backlink_opportunities'   => $opportunities,
			'audited_at'               => gmdate( 'c' ),
		);

		set_transient( self::TRANSIENT_KEY, $result, HOUR_IN_SECONDS );

		return $result;
	}

	/**
	 * Get cached audit or run fresh.
	 *
	 * @return array<string, mixed>
	 */
	public function get_audit(): array {
		$cached = get_transient( self::TRANSIENT_KEY );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		return $this->audit_all_links();
	}
}
