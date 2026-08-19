<?php
/**
 * Lightweight 404 monitor (privacy-safe).
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Aggregates 404 hits without storing IP or sensitive query data.
 */
class RevIt_Publisher_404_Monitor {

	public const STATUS_OPEN     = 'open';
	public const STATUS_IGNORED  = 'ignored';
	public const STATUS_RESOLVED = 'resolved';

	private const SKIP_PREFIXES = array( '/wp-', '/wp-content/', '/wp-includes/', '/feed', '/xmlrpc.php' );

	public function init(): void {
		if ( ! RevIt_Publisher_Services::settings()->enable_404_monitor() ) {
			return;
		}
		add_action( 'template_redirect', array( $this, 'maybe_log_404' ), 99 );
	}

	public function maybe_log_404(): void {
		if ( ! is_404() || is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}

		$path = RevIt_Publisher_Services::redirects()->normalize_path( (string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ) );
		if ( $this->should_skip( $path ) ) {
			return;
		}

		$this->record_hit( $path, $this->sanitize_referrer() );
	}

	public function record_hit( string $path, string $referrer = '' ): void {
		$path = RevIt_Publisher_Services::redirects()->normalize_path( $path );
		$existing = $this->find_by_path( $path );

		if ( $existing > 0 ) {
			$hits = (int) get_post_meta( $existing, RevIt_Publisher_Operations_Meta_Keys::NOT_FOUND_HITS, true );
			update_post_meta( $existing, RevIt_Publisher_Operations_Meta_Keys::NOT_FOUND_HITS, $hits + 1 );
			update_post_meta( $existing, RevIt_Publisher_Operations_Meta_Keys::NOT_FOUND_LAST, gmdate( 'c' ) );
			if ( '' !== $referrer ) {
				update_post_meta( $existing, RevIt_Publisher_Operations_Meta_Keys::NOT_FOUND_REFERRER, $referrer );
			}
			return;
		}

		$post_id = wp_insert_post(
			array(
				'post_type'   => RevIt_Publisher_Operations_Post_Types::NOT_FOUND,
				'post_status' => 'private',
				'post_title'  => sanitize_text_field( $path ),
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return;
		}

		update_post_meta( $post_id, RevIt_Publisher_Operations_Meta_Keys::NOT_FOUND_PATH, $path );
		update_post_meta( $post_id, RevIt_Publisher_Operations_Meta_Keys::NOT_FOUND_HITS, 1 );
		update_post_meta( $post_id, RevIt_Publisher_Operations_Meta_Keys::NOT_FOUND_LAST, gmdate( 'c' ) );
		update_post_meta( $post_id, RevIt_Publisher_Operations_Meta_Keys::NOT_FOUND_REFERRER, $referrer );
		update_post_meta( $post_id, RevIt_Publisher_Operations_Meta_Keys::NOT_FOUND_STATUS, self::STATUS_OPEN );
		update_post_meta(
			$post_id,
			RevIt_Publisher_Operations_Meta_Keys::NOT_FOUND_MATCH,
			null !== RevIt_Publisher_Services::redirects()->lookup( $path ) ? '1' : '0'
		);
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function list_entries( int $limit = 100 ): array {
		$posts = get_posts(
			array(
				'post_type'      => RevIt_Publisher_Operations_Post_Types::NOT_FOUND,
				'post_status'    => 'private',
				'posts_per_page' => $limit,
				'meta_key'       => RevIt_Publisher_Operations_Meta_Keys::NOT_FOUND_HITS,
				'orderby'        => 'meta_value_num',
				'order'          => 'DESC',
			)
		);
		return array_map( array( $this, 'format_entry' ), $posts );
	}

	public function update_status( int $entry_id, string $status ): bool {
		$allowed = array( self::STATUS_OPEN, self::STATUS_IGNORED, self::STATUS_RESOLVED );
		if ( ! in_array( $status, $allowed, true ) ) {
			return false;
		}
		update_post_meta( $entry_id, RevIt_Publisher_Operations_Meta_Keys::NOT_FOUND_STATUS, $status );
		return true;
	}

	private function find_by_path( string $path ): int {
		$posts = get_posts(
			array(
				'post_type'      => RevIt_Publisher_Operations_Post_Types::NOT_FOUND,
				'post_status'    => 'private',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => RevIt_Publisher_Operations_Meta_Keys::NOT_FOUND_PATH,
				'meta_value'     => $path,
			)
		);
		return ! empty( $posts ) ? (int) $posts[0] : 0;
	}

	private function should_skip( string $path ): bool {
		foreach ( self::SKIP_PREFIXES as $prefix ) {
			if ( str_starts_with( $path, $prefix ) ) {
				return true;
			}
		}
		return false;
	}

	private function sanitize_referrer(): string {
		$ref = isset( $_SERVER['HTTP_REFERER'] ) ? (string) wp_unslash( $_SERVER['HTTP_REFERER'] ) : '';
		if ( '' === $ref ) {
			return '';
		}
		$host = wp_parse_url( $ref, PHP_URL_HOST );
		$path = wp_parse_url( $ref, PHP_URL_PATH );
		return sanitize_text_field( (string) $host . (string) $path );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function format_entry( WP_Post $post ): array {
		return array(
			'entry_id'   => $post->ID,
			'path'       => (string) get_post_meta( $post->ID, RevIt_Publisher_Operations_Meta_Keys::NOT_FOUND_PATH, true ),
			'hits'       => (int) get_post_meta( $post->ID, RevIt_Publisher_Operations_Meta_Keys::NOT_FOUND_HITS, true ),
			'last_seen'  => (string) get_post_meta( $post->ID, RevIt_Publisher_Operations_Meta_Keys::NOT_FOUND_LAST, true ),
			'referrer'   => (string) get_post_meta( $post->ID, RevIt_Publisher_Operations_Meta_Keys::NOT_FOUND_REFERRER, true ),
			'status'     => (string) get_post_meta( $post->ID, RevIt_Publisher_Operations_Meta_Keys::NOT_FOUND_STATUS, true ),
			'has_match'  => '1' === get_post_meta( $post->ID, RevIt_Publisher_Operations_Meta_Keys::NOT_FOUND_MATCH, true ),
		);
	}
}
