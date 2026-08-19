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
		);
	}
}
