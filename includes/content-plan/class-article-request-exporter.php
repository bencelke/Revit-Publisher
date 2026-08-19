<?php
/**
 * Article request JSON export for missing planned content.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Exports revit-article-request-v1 payloads for ChatGPT handoff.
 */
class RevIt_Publisher_Article_Request_Exporter {

	public const REQUEST_TYPE = 'revit-article-request-v1';

	private RevIt_Publisher_Content_Plan_Service $plan_service;

	public function __construct( RevIt_Publisher_Content_Plan_Service $plan_service ) {
		$this->plan_service = $plan_service;
	}

	/**
	 * Export request for a single missing article.
	 *
	 * @return array<string, mixed>|null
	 */
	public function export_single( int $plan_id, string $article_key ): ?array {
		$plan = $this->plan_service->get_plan_data( $plan_id );
		if ( null === $plan ) {
			return null;
		}

		foreach ( (array) ( $plan->articles ?? array() ) as $entry ) {
			if ( (string) ( $entry->article_key ?? '' ) === $article_key ) {
				return $this->build_request( $plan, $entry );
			}
		}

		return null;
	}

	/**
	 * Export all missing articles for a cluster.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function export_cluster( int $plan_id, string $cluster_key ): array {
		$coverage = $this->plan_service->get_coverage( $plan_id );
		$requests = array();

		foreach ( $coverage['missing'] as $missing ) {
			if ( ( $missing['cluster_key'] ?? '' ) === $cluster_key ) {
				$request = $this->export_single( $plan_id, (string) $missing['article_key'] );
				if ( null !== $request ) {
					$requests[] = $request;
				}
			}
		}

		return $requests;
	}

	/**
	 * Export all missing articles for a vehicle plan.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function export_vehicle( int $plan_id ): array {
		$coverage = $this->plan_service->get_coverage( $plan_id );
		$requests = array();

		foreach ( $coverage['missing'] as $missing ) {
			$request = $this->export_single( $plan_id, (string) $missing['article_key'] );
			if ( null !== $request ) {
				$requests[] = $request;
			}
		}

		return $requests;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function build_request( object $plan, object $entry ): array {
		$cluster_key = (string) ( $entry->cluster_key ?? '' );
		$cluster     = null;

		foreach ( (array) ( $plan->clusters ?? array() ) as $cluster_entry ) {
			if ( (string) ( $cluster_entry->cluster_key ?? '' ) === $cluster_key ) {
				$cluster = $cluster_entry;
				break;
			}
		}

		return array(
			'request_type'  => self::REQUEST_TYPE,
			'article_key'   => (string) ( $entry->article_key ?? '' ),
			'title'         => (string) ( $entry->title ?? '' ),
			'article_type'  => (string) ( $entry->article_type ?? '' ),
			'primary_topic' => (string) ( $entry->primary_topic ?? '' ),
			'priority'      => (int) ( $entry->priority ?? 0 ),
			'vehicle'       => json_decode( wp_json_encode( $plan->vehicle ), true ),
			'cluster'       => null !== $cluster ? json_decode( wp_json_encode( $cluster ), true ) : array(
				'cluster_key' => $cluster_key,
			),
			'plan_key'      => (string) ( $plan->plan_key ?? '' ),
			'editorial_context' => $this->build_editorial_context( $plan, $entry, $cluster_key, $cluster ),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function build_editorial_context( object $plan, object $entry, string $cluster_key, ?object $cluster ): array {
		$neighbor_keys = array();
		foreach ( (array) ( $plan->articles ?? array() ) as $article ) {
			if ( (string) ( $article->cluster_key ?? '' ) === $cluster_key && (string) ( $article->article_key ?? '' ) !== (string) ( $entry->article_key ?? '' ) ) {
				$neighbor_keys[] = (string) $article->article_key;
			}
		}
		$published_neighbors = array();
		foreach ( $neighbor_keys as $key ) {
			$post_id = RevIt_Publisher_Services::registry()->find_post_id_by_article_key( $key );
			if ( $post_id && 'publish' === get_post_status( $post_id ) ) {
				$published_neighbors[] = array(
					'article_key' => $key,
					'title'       => get_the_title( $post_id ),
					'url'         => get_permalink( $post_id ),
				);
			}
		}

		$gsc_signal = null;
		if ( RevIt_Publisher_Services::gsc_auth()->is_connected() ) {
			$clusters = RevIt_Publisher_Services::gsc_insights()->get_cluster_performance( '28d' );
			foreach ( $clusters as $cluster_perf ) {
				if ( ( $cluster_perf['cluster_key'] ?? '' ) === $cluster_key ) {
					$gsc_signal = array(
						'impressions' => (int) ( $cluster_perf['impressions'] ?? 0 ),
						'clicks'      => (int) ( $cluster_perf['clicks'] ?? 0 ),
						'position'    => (float) ( $cluster_perf['position'] ?? 0 ),
					);
					break;
				}
			}
		}

		return array(
			'pillar_article_key'       => null !== $cluster ? (string) ( $cluster->pillar_article_key ?? '' ) : '',
			'related_planned_articles'   => $neighbor_keys,
			'published_neighbors'      => $published_neighbors,
			'search_console_cluster'   => $gsc_signal,
			'creation_priority'        => (int) ( $entry->priority ?? 0 ),
		);
	}
}
