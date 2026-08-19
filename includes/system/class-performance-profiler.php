<?php
/**
 * Admin-only performance profiling utility.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RevIt_Publisher_Performance_Profiler {

	public const OPTION_KEY = 'revit_publisher_profiler_log';
	public const MAX_ENTRIES = 100;

	public function record( string $operation, float $duration_seconds, int $row_count = 0 ): void {
		$entry = array(
			'operation' => sanitize_key( $operation ),
			'duration'  => round( $duration_seconds, 4 ),
			'rows'      => $row_count,
			'memory_mb' => round( memory_get_peak_usage( true ) / 1024 / 1024, 2 ),
			'timestamp' => gmdate( 'c' ),
		);
		$log = get_option( self::OPTION_KEY, array() );
		$log = is_array( $log ) ? $log : array();
		array_unshift( $log, $entry );
		update_option( self::OPTION_KEY, array_slice( $log, 0, self::MAX_ENTRIES ), false );
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function get_recent( int $limit = 20 ): array {
		$log = get_option( self::OPTION_KEY, array() );
		return array_slice( is_array( $log ) ? $log : array(), 0, $limit );
	}

	/**
	 * @return array<string, mixed>
	 */
	public function benchmark_reconcile(): array {
		$started = microtime( true );
		$result  = RevIt_Publisher_Services::editorial_reconciler()->reconcile();
		$this->record( 'benchmark_reconcile', microtime( true ) - $started, (int) ( $result['candidates'] ?? 0 ) );
		return $result;
	}
}
