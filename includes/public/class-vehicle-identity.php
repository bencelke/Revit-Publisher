<?php
/**
 * Deterministic vehicle identity keys.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class RevIt_Publisher_Vehicle_Identity {

	/**
	 * Build stable vehicle key from identity parts.
	 */
	public static function build_key(
		string $manufacturer,
		string $model,
		string $generation = '',
		string $trim = ''
	): string {
		$parts = array_filter(
			array(
				self::slug( $manufacturer ),
				self::slug( $model ),
				'' !== $generation ? self::slug( $generation ) : '',
				'' !== $trim ? self::slug( $trim ) : '',
			)
		);
		return implode( '-', $parts );
	}

	/**
	 * Build key from RevIt article post meta.
	 */
	public static function from_post( int $post_id ): string {
		return self::build_key(
			(string) get_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::VEHICLE_MANUFACTURER, true ),
			(string) get_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::VEHICLE_MODEL, true ),
			(string) get_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::VEHICLE_GENERATION, true ),
			(string) get_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::VEHICLE_TRIM, true )
		);
	}

	/**
	 * Human-readable vehicle label.
	 */
	public static function label( string $manufacturer, string $model, string $generation = '', string $trim = '' ): string {
		return implode( ' ', array_filter( array( $manufacturer, $model, $generation, $trim ) ) );
	}

	public static function slug( string $value ): string {
		return sanitize_title( $value );
	}
}
