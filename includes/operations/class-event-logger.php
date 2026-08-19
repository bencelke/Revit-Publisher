<?php
/**
 * Lightweight operational event logger.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Logs operational events without article content or secrets.
 */
class RevIt_Publisher_Event_Logger {

	public const OPTION_KEY = 'revit_publisher_event_log';
	public const MAX_EVENTS = 200;

	/**
	 * @param array<string, mixed> $context
	 */
	public function log( string $event, array $context = array() ): void {
		$entry = array(
			'event'     => sanitize_key( $event ),
			'timestamp' => gmdate( 'c' ),
			'user_id'   => get_current_user_id(),
			'context'   => $this->sanitize_context( $context ),
		);

		$log = get_option( self::OPTION_KEY, array() );
		$log = is_array( $log ) ? $log : array();
		array_unshift( $log, $entry );
		$log = array_slice( $log, 0, self::MAX_EVENTS );
		update_option( self::OPTION_KEY, $log, false );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( '[RevIt Publisher] ' . $event . ' ' . wp_json_encode( $entry['context'] ) );
		}
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function get_recent( int $limit = 50 ): array {
		$log = get_option( self::OPTION_KEY, array() );
		return array_slice( is_array( $log ) ? $log : array(), 0, $limit );
	}

	/**
	 * @param array<string, mixed> $context
	 * @return array<string, mixed>
	 */
	private function sanitize_context( array $context ): array {
		$safe = array();
		foreach ( $context as $key => $value ) {
			$key = sanitize_key( (string) $key );
			if ( is_scalar( $value ) ) {
				$safe[ $key ] = is_numeric( $value ) ? $value : sanitize_text_field( (string) $value );
			}
		}
		return $safe;
	}
}
