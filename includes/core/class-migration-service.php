<?php
/**
 * Database migration framework.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RevIt_Publisher_Migration_Service {

	public const DB_VERSION_OPTION = 'revit_publisher_db_version';
	public const LOG_OPTION        = 'revit_publisher_migration_log';
	public const TARGET_VERSION    = 1;

	/**
	 * Run pending migrations idempotently.
	 *
	 * @return array<string, mixed>
	 */
	public function maybe_upgrade(): array {
		$current = (int) get_option( self::DB_VERSION_OPTION, 0 );
		if ( $current >= self::TARGET_VERSION ) {
			return array( 'success' => true, 'current' => $current, 'ran' => array() );
		}

		$ran = array();
		for ( $version = $current + 1; $version <= self::TARGET_VERSION; ++$version ) {
			$result = $this->run_migration( $version );
			if ( is_wp_error( $result ) ) {
				return array(
					'success' => false,
					'current' => $current,
					'failed'  => $version,
					'message' => $result->get_error_message(),
				);
			}
			update_option( self::DB_VERSION_OPTION, $version, false );
			$ran[] = $version;
			$current = $version;
		}

		return array( 'success' => true, 'current' => $current, 'ran' => $ran );
	}

	/**
	 * @return true|WP_Error
	 */
	private function run_migration( int $version ): bool|WP_Error {
		try {
			switch ( $version ) {
				case 1:
					RevIt_Publisher_GSC_Schema::install();
					break;
				default:
					return new WP_Error( 'revit_unknown_migration', 'Unknown migration version.' );
			}

			$this->log_migration( $version, 'success' );
			RevIt_Publisher_Services::event_logger()->log( 'migration', array( 'version' => $version ) );
			return true;
		} catch ( Throwable $e ) {
			$this->log_migration( $version, 'failure', $e->getMessage() );
			return new WP_Error( 'revit_migration_failed', $e->getMessage() );
		}
	}

	private function log_migration( int $version, string $status, string $message = '' ): void {
		$log = get_option( self::LOG_OPTION, array() );
		$log = is_array( $log ) ? $log : array();
		array_unshift(
			$log,
			array(
				'version'   => $version,
				'status'    => sanitize_key( $status ),
				'message'   => sanitize_text_field( $message ),
				'timestamp' => gmdate( 'c' ),
			)
		);
		update_option( self::LOG_OPTION, array_slice( $log, 0, 50 ), false );
	}

	public function get_installed_version(): int {
		return (int) get_option( self::DB_VERSION_OPTION, 0 );
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function get_log(): array {
		$log = get_option( self::LOG_OPTION, array() );
		return is_array( $log ) ? $log : array();
	}
}
