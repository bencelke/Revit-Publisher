<?php
/**
 * Transient cache for vehicle hub public queries.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RevIt_Publisher_Hub_Cache {

	private const PREFIX = 'revit_hub_cache_';
	private const TTL    = HOUR_IN_SECONDS;

	public function init(): void {
		add_action( 'save_post', array( $this, 'on_save_post' ), 20, 2 );
		add_action( 'deleted_post', array( $this, 'on_deleted_post' ) );
	}

	public function get( int $hub_id, string $key ): mixed {
		return get_transient( self::PREFIX . $hub_id . '_' . $key );
	}

	public function set( int $hub_id, string $key, mixed $value ): void {
		set_transient( self::PREFIX . $hub_id . '_' . $key, $value, self::TTL );
	}

	public function invalidate_hub( int $hub_id ): void {
		global $wpdb;
		$like = $wpdb->esc_like( self::PREFIX . $hub_id . '_' ) . '%';
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", '_transient_' . $like, '_transient_timeout_' . $like ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	public function invalidate_all_hubs(): void {
		global $wpdb;
		$like = $wpdb->esc_like( self::PREFIX ) . '%';
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", '_transient_' . $like, '_transient_timeout_' . $like ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	public function on_save_post( int $post_id, WP_Post $post ): void {
		if ( RevIt_Publisher_Vehicle_Hub_Post_Type::POST_TYPE === $post->post_type ) {
			$this->invalidate_hub( $post_id );
			return;
		}

		if ( 'post' === $post->post_type && RevIt_Publisher_Services::resolver()->is_managed( $post_id ) ) {
			$key = RevIt_Publisher_Vehicle_Identity::from_post( $post_id );
			if ( '' !== $key ) {
				$hub_id = RevIt_Publisher_Services::vehicle_hubs()->find_by_key( $key );
				if ( null !== $hub_id ) {
					$this->invalidate_hub( $hub_id );
				}
			}
			$this->invalidate_related_cache( $post_id );
		}
	}

	public function on_deleted_post( int $post_id ): void {
		$post = get_post( $post_id );
		if ( $post instanceof WP_Post && RevIt_Publisher_Vehicle_Hub_Post_Type::POST_TYPE === $post->post_type ) {
			$this->invalidate_hub( $post_id );
		}
	}

	private function invalidate_related_cache( int $post_id ): void {
		delete_transient( 'revit_related_' . $post_id );
	}
}
