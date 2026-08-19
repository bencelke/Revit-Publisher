<?php
/**
 * Search Console data synchronization.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RevIt_Publisher_GSC_Sync_Service {

	private const LOCK_KEY = 'revit_gsc_sync_lock';
	private const LOCK_TTL = 900;

	private RevIt_Publisher_GSC_Client_Interface $client;
	private RevIt_Publisher_GSC_Data_Store $store;
	private RevIt_Publisher_GSC_Auth_Service $auth;
	private RevIt_Publisher_Settings $settings;

	public function __construct(
		RevIt_Publisher_GSC_Client_Interface $client,
		RevIt_Publisher_GSC_Data_Store $store,
		RevIt_Publisher_GSC_Auth_Service $auth,
		RevIt_Publisher_Settings $settings
	) {
		$this->client   = $client;
		$this->store    = $store;
		$this->auth     = $auth;
		$this->settings = $settings;
	}

	/**
	 * @return array<string, mixed>|WP_Error
	 */
	public function sync( bool $manual = false ): array|WP_Error {
		unset( $manual );
		if ( ! $this->auth->is_connected() ) {
			return new WP_Error( 'revit_gsc_not_connected', __( 'Search Console is not connected.', 'revit-publisher' ) );
		}
		if ( $this->is_locked() ) {
			return new WP_Error( 'revit_gsc_sync_locked', __( 'Search Console sync already running.', 'revit-publisher' ) );
		}

		$this->acquire_lock();
		$started = microtime( true );
		$property = $this->settings->gsc_property();
		if ( '' === $property ) {
			$this->release_lock();
			return new WP_Error( 'revit_gsc_no_property', __( 'No Search Console property selected.', 'revit-publisher' ) );
		}

		try {
			$pages_updated  = 0;
			$query_rows     = 0;
			$metric_date    = gmdate( 'Y-m-d' );
			$row_limit      = $this->settings->gsc_max_rows();

			foreach ( array( '7d', '28d', 'prev_7d', 'prev_28d' ) as $window ) {
				$range = $this->date_range_for_window( $window );
				$page_api_rows = $this->client->search_analytics_query(
					$property,
					array(
						'start_date'       => $range['start'],
						'end_date'         => $range['end'],
						'dimensions'       => array( 'page' ),
						'row_limit'        => $row_limit,
						'previous_period'  => str_starts_with( $window, 'prev_' ),
					)
				);
				$page_rows = array();
				foreach ( $page_api_rows as $row ) {
					$page_rows[] = array(
						'page_url'    => (string) ( $row['keys'][0] ?? '' ),
						'clicks'      => (int) ( $row['clicks'] ?? 0 ),
						'impressions' => (int) ( $row['impressions'] ?? 0 ),
						'ctr'         => (float) ( $row['ctr'] ?? 0 ),
						'position'    => (float) ( $row['position'] ?? 0 ),
					);
				}
				$pages_updated += $this->store->store_page_rows( $property, $window, $metric_date, $page_rows );

				$query_api_rows = $this->client->search_analytics_query(
					$property,
					array(
						'start_date'      => $range['start'],
						'end_date'        => $range['end'],
						'dimensions'      => array( 'page', 'query' ),
						'row_limit'       => $row_limit,
						'previous_period' => str_starts_with( $window, 'prev_' ),
					)
				);
				$query_rows_data = array();
				foreach ( $query_api_rows as $row ) {
					$query_rows_data[] = array(
						'page_url'    => (string) ( $row['keys'][0] ?? '' ),
						'query'       => (string) ( $row['keys'][1] ?? '' ),
						'clicks'      => (int) ( $row['clicks'] ?? 0 ),
						'impressions' => (int) ( $row['impressions'] ?? 0 ),
						'ctr'         => (float) ( $row['ctr'] ?? 0 ),
						'position'    => (float) ( $row['position'] ?? 0 ),
					);
				}
				$query_rows += $this->store->store_query_rows( $property, $window, $metric_date, $query_rows_data );
			}

			$duration = round( microtime( true ) - $started, 2 );
			$stats    = array(
				'pages_updated' => $pages_updated,
				'query_rows'    => $query_rows,
				'duration'      => $duration,
				'completed_at'  => gmdate( 'c' ),
			);
			update_option( 'revit_gsc_last_sync', gmdate( 'c' ) );
			update_option( 'revit_gsc_last_sync_stats', $stats );
			delete_option( 'revit_gsc_last_error' );

			RevIt_Publisher_Services::gsc_opportunities()->detect_and_reconcile();
			RevIt_Publisher_Services::gsc_query_coverage()->detect_and_reconcile();
			RevIt_Publisher_Services::editorial_reconciler()->reconcile();

			$this->release_lock();
			return array_merge( array( 'success' => true ), $stats );
		} catch ( Throwable $e ) {
			update_option(
				'revit_gsc_last_error',
				array(
					'timestamp' => gmdate( 'c' ),
					'message'   => sanitize_text_field( $e->getMessage() ),
				)
			);
			$this->release_lock();
			return new WP_Error( 'revit_gsc_sync_failed', $e->getMessage() );
		}
	}

	public function is_locked(): bool {
		return (bool) get_transient( self::LOCK_KEY );
	}

	private function acquire_lock(): void {
		set_transient( self::LOCK_KEY, gmdate( 'c' ), self::LOCK_TTL );
	}

	private function release_lock(): void {
		delete_transient( self::LOCK_KEY );
	}

	/**
	 * @return array{start: string, end: string}
	 */
	private function date_range_for_window( string $window ): array {
		$days = match ( $window ) {
			'7d', 'prev_7d' => 7,
			default => 28,
		};
		if ( str_starts_with( $window, 'prev_' ) ) {
			$end   = gmdate( 'Y-m-d', strtotime( '-' . ( $days + 1 ) . ' days' ) );
			$start = gmdate( 'Y-m-d', strtotime( '-' . ( ( 2 * $days ) + 1 ) . ' days' ) );
			return array( 'start' => $start, 'end' => $end );
		}
		$end   = gmdate( 'Y-m-d', strtotime( '-1 day' ) );
		$start = gmdate( 'Y-m-d', strtotime( '-' . $days . ' days' ) );
		return array( 'start' => $start, 'end' => $end );
	}
}
