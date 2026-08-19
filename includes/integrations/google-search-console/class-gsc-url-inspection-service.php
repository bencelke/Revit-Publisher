<?php
/**
 * URL Inspection integration with quota limits.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RevIt_Publisher_GSC_URL_Inspection_Service {

	private RevIt_Publisher_GSC_Client_Interface $client;
	private RevIt_Publisher_GSC_Data_Store $store;
	private RevIt_Publisher_Settings $settings;

	public function __construct(
		RevIt_Publisher_GSC_Client_Interface $client,
		RevIt_Publisher_GSC_Data_Store $store,
		RevIt_Publisher_Settings $settings
	) {
		$this->client   = $client;
		$this->store    = $store;
		$this->settings = $settings;
	}

	/**
	 * @return array<string, mixed>|WP_Error
	 */
	public function inspect_post( int $post_id ): array|WP_Error {
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new WP_Error( 'revit_forbidden', __( 'Permission denied.', 'revit-publisher' ), array( 'status' => 403 ) );
		}
		if ( ! $this->can_inspect_today() ) {
			return new WP_Error( 'revit_gsc_inspection_quota', __( 'Daily URL inspection limit reached.', 'revit-publisher' ) );
		}

		$property  = $this->settings->gsc_property();
		$permalink = get_permalink( $post_id );
		if ( ! is_string( $permalink ) || '' === $property ) {
			return new WP_Error( 'revit_gsc_invalid', __( 'Invalid post or property.', 'revit-publisher' ) );
		}

		try {
			$result = $this->client->inspect_url( $property, $permalink );
			$this->store->store_inspection( $property, $permalink, $post_id, $result );
			$this->increment_daily_count();
			return $this->format_result( $result );
		} catch ( Throwable $e ) {
			return new WP_Error( 'revit_gsc_inspection_failed', sanitize_text_field( $e->getMessage() ) );
		}
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function detect_index_issues(): array {
		$issues = array();
		foreach ( RevIt_Publisher_Services::registry()->get_managed_post_ids() as $post_id ) {
			if ( 'publish' !== get_post_status( (int) $post_id ) ) {
				continue;
			}
			$inspection = $this->store->get_latest_inspection( (int) $post_id );
			if ( null === $inspection ) {
				continue;
			}
			$result = is_array( $inspection['result'] ?? null ) ? $inspection['result'] : array();
			$title  = get_the_title( (int) $post_id );
			$vehicle = RevIt_Publisher_Services::graph()->get_vehicle_label( (int) $post_id );
			$key    = (string) get_post_meta( (int) $post_id, RevIt_Publisher_Post_Meta_Keys::ARTICLE_KEY, true );

			if ( empty( $result['indexed'] ) ) {
				$issues[] = array(
					'issue_type'         => 'gsc_index_issue',
					'title'              => $title,
					'post_id'            => (int) $post_id,
					'vehicle'            => $vehicle,
					'article_key'        => $key,
					'cluster_key'        => (string) get_post_meta( (int) $post_id, RevIt_Publisher_Post_Meta_Keys::CLUSTER_KEY, true ),
					'explanation'        => 'URL inspection indicates the page is not indexed.',
					'recommended_action' => 'Review indexing blockers and internal linking.',
					'context'            => $result,
				);
			}

			$google = (string) ( $result['googleCanonical'] ?? '' );
			$user   = (string) ( $result['userCanonical'] ?? get_permalink( (int) $post_id ) );
			if ( '' !== $google && '' !== $user && untrailingslashit( $google ) !== untrailingslashit( $user ) ) {
				$issues[] = array(
					'issue_type'         => 'gsc_canonical_mismatch',
					'title'              => $title,
					'post_id'            => (int) $post_id,
					'vehicle'            => $vehicle,
					'article_key'        => $key,
					'cluster_key'        => (string) get_post_meta( (int) $post_id, RevIt_Publisher_Post_Meta_Keys::CLUSTER_KEY, true ),
					'explanation'        => 'Google-selected canonical differs from user canonical.',
					'recommended_action' => 'Review canonical tags and consolidation policy.',
					'context'            => array(
						'google_canonical' => $google,
						'user_canonical'   => $user,
					),
				);
			}
		}
		return $issues;
	}

	/**
	 * @param array<string, mixed> $result
	 * @return array<string, mixed>
	 */
	public function format_result( array $result ): array {
		return array(
			'indexed'          => ! empty( $result['indexed'] ),
			'last_crawl'       => (string) ( $result['lastCrawlTime'] ?? '' ),
			'google_canonical' => (string) ( $result['googleCanonical'] ?? '' ),
			'user_canonical'   => (string) ( $result['userCanonical'] ?? '' ),
			'coverage_state'   => (string) ( $result['coverageState'] ?? '' ),
			'verdict'          => (string) ( $result['verdict'] ?? '' ),
		);
	}

	private function can_inspect_today(): bool {
		$count = (int) get_transient( 'revit_gsc_inspection_count_' . gmdate( 'Ymd' ) );
		return $count < $this->settings->gsc_inspection_daily_max();
	}

	private function increment_daily_count(): void {
		$key   = 'revit_gsc_inspection_count_' . gmdate( 'Ymd' );
		$count = (int) get_transient( $key );
		set_transient( $key, $count + 1, DAY_IN_SECONDS );
	}
}
