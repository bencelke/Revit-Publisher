<?php
/**
 * Redirect manager with safety validation.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CRUD for RevIt-managed 301 redirects.
 */
class RevIt_Publisher_Redirect_Service {

	public const STATUS_ACTIVE   = 'active';
	public const STATUS_DISABLED = 'disabled';
	public const TYPE_301        = '301';

	/**
	 * @return array<string, mixed>|WP_Error
	 */
	public function create( array $data ) {
		$source = $this->normalize_path( (string) ( $data['source_path'] ?? '' ) );
		if ( '' === $source ) {
			return new WP_Error( 'revit_invalid_source', __( 'Source path is required.', 'revit-publisher' ) );
		}

		$validation = $this->validate_redirect( $source, $data );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		$post_id = wp_insert_post(
			array(
				'post_type'   => RevIt_Publisher_Operations_Post_Types::REDIRECT,
				'post_status' => 'private',
				'post_title'  => sanitize_text_field( $source ),
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$this->save_meta( (int) $post_id, $source, $data );
		$this->invalidate_cache( $source );
		RevIt_Publisher_Services::event_logger()->log( 'redirect_created', array( 'redirect_id' => (int) $post_id ) );

		return $this->format_redirect( get_post( (int) $post_id ) );
	}

	/**
	 * @return array<string, mixed>|WP_Error
	 */
	public function update( int $redirect_id, array $data ) {
		$post = get_post( $redirect_id );
		if ( ! $post instanceof WP_Post || RevIt_Publisher_Operations_Post_Types::REDIRECT !== $post->post_type ) {
			return new WP_Error( 'revit_not_found', __( 'Redirect not found.', 'revit-publisher' ) );
		}

		$source = $this->normalize_path( (string) ( $data['source_path'] ?? get_post_meta( $redirect_id, RevIt_Publisher_Operations_Meta_Keys::REDIRECT_SOURCE, true ) ) );
		$validation = $this->validate_redirect( $source, $data, $redirect_id );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		wp_update_post(
			array(
				'ID'         => $redirect_id,
				'post_title' => sanitize_text_field( $source ),
			)
		);
		$this->save_meta( $redirect_id, $source, $data );
		$this->invalidate_cache( $source );
		RevIt_Publisher_Services::event_logger()->log( 'redirect_updated', array( 'redirect_id' => $redirect_id ) );

		return $this->format_redirect( get_post( $redirect_id ) );
	}

	public function disable( int $redirect_id ): bool {
		$post = get_post( $redirect_id );
		if ( ! $post instanceof WP_Post ) {
			return false;
		}
		$source = (string) get_post_meta( $redirect_id, RevIt_Publisher_Operations_Meta_Keys::REDIRECT_SOURCE, true );
		update_post_meta( $redirect_id, RevIt_Publisher_Operations_Meta_Keys::REDIRECT_STATUS, self::STATUS_DISABLED );
		$this->invalidate_cache( $source );
		return true;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function list_redirects(): array {
		$posts = get_posts(
			array(
				'post_type'      => RevIt_Publisher_Operations_Post_Types::REDIRECT,
				'post_status'    => 'private',
				'posts_per_page' => 500,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		return array_map( array( $this, 'format_redirect' ), $posts );
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function get_redirect( int $redirect_id ): ?array {
		$post = get_post( $redirect_id );
		if ( ! $post instanceof WP_Post || RevIt_Publisher_Operations_Post_Types::REDIRECT !== $post->post_type ) {
			return null;
		}
		return $this->format_redirect( $post );
	}

	/**
	 * Lookup redirect for a request path.
	 *
	 * @return array<string, mixed>|null
	 */
	public function lookup( string $path ): ?array {
		$path  = $this->normalize_path( $path );
		$cache = get_transient( 'revit_redirect_' . md5( $path ) );
		if ( is_array( $cache ) ) {
			return $cache ?: null;
		}

		$posts = get_posts(
			array(
				'post_type'      => RevIt_Publisher_Operations_Post_Types::REDIRECT,
				'post_status'    => 'private',
				'posts_per_page' => 1,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => RevIt_Publisher_Operations_Meta_Keys::REDIRECT_SOURCE,
						'value' => $path,
					),
					array(
						'key'   => RevIt_Publisher_Operations_Meta_Keys::REDIRECT_STATUS,
						'value' => self::STATUS_ACTIVE,
					),
				),
			)
		);

		if ( empty( $posts ) ) {
			set_transient( 'revit_redirect_' . md5( $path ), array(), HOUR_IN_SECONDS );
			return null;
		}

		$redirect = $this->format_redirect( $posts[0] );
		set_transient( 'revit_redirect_' . md5( $path ), $redirect, HOUR_IN_SECONDS );
		return $redirect;
	}

	/**
	 * @return true|WP_Error
	 */
	private function validate_redirect( string $source, array $data, int $exclude_id = 0 ) {
		if ( $this->source_exists( $source, $exclude_id ) ) {
			return new WP_Error( 'revit_duplicate_source', __( 'A redirect for this source path already exists.', 'revit-publisher' ) );
		}

		$target_post_id = (int) ( $data['target_post_id'] ?? 0 );
		$target_url     = esc_url_raw( (string) ( $data['target_url'] ?? '' ) );

		if ( $target_post_id <= 0 && '' === $target_url ) {
			return new WP_Error( 'revit_invalid_target', __( 'Redirect target is required.', 'revit-publisher' ) );
		}

		if ( $target_post_id > 0 ) {
			$dest_path = RevIt_Publisher_Services::redirects()->get_post_path( $target_post_id );
			if ( $dest_path === $source ) {
				return new WP_Error( 'revit_same_destination', __( 'Source and destination cannot be the same.', 'revit-publisher' ) );
			}
		}

		if ( '' !== $target_url && ! RevIt_Publisher_Services::settings()->external_redirects_allowed() ) {
			if ( ! $this->is_internal_url( $target_url ) ) {
				return new WP_Error( 'revit_external_blocked', __( 'External redirect targets are disabled in settings.', 'revit-publisher' ) );
			}
		}

		if ( $this->would_create_loop( $source, $target_post_id, $target_url ) ) {
			return new WP_Error( 'revit_redirect_loop', __( 'This redirect would create a loop.', 'revit-publisher' ) );
		}

		return true;
	}

	private function would_create_loop( string $source, int $target_post_id, string $target_url ): bool {
		$dest = $target_url;
		if ( $target_post_id > 0 ) {
			$permalink = get_permalink( $target_post_id );
			$dest = is_string( $permalink ) ? $permalink : '';
		}
		$dest_path = $this->normalize_path( (string) wp_parse_url( $dest, PHP_URL_PATH ) );
		if ( $dest_path === $source ) {
			return true;
		}
		$chain = $this->lookup( $dest_path );
		return is_array( $chain ) && (string) ( $chain['source_path'] ?? '' ) === $source;
	}

	private function is_internal_url( string $url ): bool {
		$home = home_url( '/' );
		return str_starts_with( $url, $home ) || str_starts_with( $url, '/' );
	}

	private function source_exists( string $source, int $exclude_id ): bool {
		$posts = get_posts(
			array(
				'post_type'      => RevIt_Publisher_Operations_Post_Types::REDIRECT,
				'post_status'    => 'private',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => RevIt_Publisher_Operations_Meta_Keys::REDIRECT_SOURCE,
						'value' => $source,
					),
				),
			)
		);
		if ( empty( $posts ) ) {
			return false;
		}
		return $exclude_id <= 0 || (int) $posts[0] !== $exclude_id;
	}

	public function normalize_path( string $path ): string {
		if ( str_contains( $path, '?' ) ) {
			$parts = wp_parse_url( $path );
			$query = isset( $parts['query'] ) ? '?' . $parts['query'] : '';
			$base  = isset( $parts['path'] ) ? (string) $parts['path'] : '';
			if ( '' === $base || '/' === $base ) {
				return '' !== $query ? $query : '/';
			}
			return untrailingslashit( $base ) . $query;
		}

		$path = wp_parse_url( $path, PHP_URL_PATH ) ?? $path;
		$path = '/' . trim( (string) $path, '/' );
		return '/' === $path ? '/' : untrailingslashit( $path );
	}

	/**
	 * Resolve a stable request path for a post permalink.
	 */
	public function get_post_path( int $post_id ): string {
		$permalink = get_permalink( $post_id );
		if ( ! is_string( $permalink ) || '' === $permalink ) {
			return '';
		}

		$path  = (string) wp_parse_url( $permalink, PHP_URL_PATH );
		$query = (string) wp_parse_url( $permalink, PHP_URL_QUERY );

		if ( ( '' === $path || '/' === $path ) && '' !== $query ) {
			return $this->normalize_path( '/?' . $query );
		}

		return $this->normalize_path( $path );
	}

	/**
	 * @param array<string, mixed> $data
	 */
	private function save_meta( int $post_id, string $source, array $data ): void {
		update_post_meta( $post_id, RevIt_Publisher_Operations_Meta_Keys::REDIRECT_SOURCE, $source );
		update_post_meta( $post_id, RevIt_Publisher_Operations_Meta_Keys::REDIRECT_TARGET_ID, (int) ( $data['target_post_id'] ?? 0 ) );
		update_post_meta( $post_id, RevIt_Publisher_Operations_Meta_Keys::REDIRECT_TARGET_URL, esc_url_raw( (string) ( $data['target_url'] ?? '' ) ) );
		update_post_meta( $post_id, RevIt_Publisher_Operations_Meta_Keys::REDIRECT_TYPE, self::TYPE_301 );
		update_post_meta( $post_id, RevIt_Publisher_Operations_Meta_Keys::REDIRECT_REASON, sanitize_textarea_field( (string) ( $data['reason'] ?? '' ) ) );
		update_post_meta( $post_id, RevIt_Publisher_Operations_Meta_Keys::REDIRECT_STATUS, sanitize_key( (string) ( $data['status'] ?? self::STATUS_ACTIVE ) ) );
		update_post_meta( $post_id, RevIt_Publisher_Operations_Meta_Keys::REDIRECT_CREATED_AT, gmdate( 'c' ) );
		update_post_meta( $post_id, RevIt_Publisher_Operations_Meta_Keys::REDIRECT_CREATED_BY, get_current_user_id() );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function format_redirect( WP_Post $post ): array {
		$target_id = (int) get_post_meta( $post->ID, RevIt_Publisher_Operations_Meta_Keys::REDIRECT_TARGET_ID, true );
		return array(
			'redirect_id'    => $post->ID,
			'source_path'    => (string) get_post_meta( $post->ID, RevIt_Publisher_Operations_Meta_Keys::REDIRECT_SOURCE, true ),
			'target_post_id' => $target_id,
			'target_url'     => (string) get_post_meta( $post->ID, RevIt_Publisher_Operations_Meta_Keys::REDIRECT_TARGET_URL, true ),
			'target_permalink'=> $target_id > 0 ? get_permalink( $target_id ) : '',
			'redirect_type'  => (string) get_post_meta( $post->ID, RevIt_Publisher_Operations_Meta_Keys::REDIRECT_TYPE, true ),
			'reason'         => (string) get_post_meta( $post->ID, RevIt_Publisher_Operations_Meta_Keys::REDIRECT_REASON, true ),
			'status'         => (string) get_post_meta( $post->ID, RevIt_Publisher_Operations_Meta_Keys::REDIRECT_STATUS, true ),
			'created_at'     => (string) get_post_meta( $post->ID, RevIt_Publisher_Operations_Meta_Keys::REDIRECT_CREATED_AT, true ),
		);
	}

	private function invalidate_cache( string $source ): void {
		delete_transient( 'revit_redirect_' . md5( $this->normalize_path( $source ) ) );
	}
}
