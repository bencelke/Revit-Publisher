<?php
/**
 * Article key resolution service.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves stable article_key values to WordPress posts.
 */
class RevIt_Publisher_Article_Resolver {

	/**
	 * Registry service.
	 *
	 * @var RevIt_Publisher_Article_Registry
	 */
	private RevIt_Publisher_Article_Registry $registry;

	/**
	 * Constructor.
	 */
	public function __construct( RevIt_Publisher_Article_Registry $registry ) {
		$this->registry = $registry;
	}

	/**
	 * Resolve article key to post details.
	 *
	 * @return array<string, mixed>|null
	 */
	public function resolve( string $article_key ): ?array {
		$article_key = sanitize_text_field( $article_key );
		if ( '' === $article_key ) {
			return null;
		}

		$cache_key = 'revit_resolve_' . md5( $article_key );
		$cached    = wp_cache_get( $cache_key, 'revit_publisher' );
		if ( false !== $cached ) {
			return is_array( $cached ) ? $cached : null;
		}

		$post_id = $this->registry->find_post_id_by_article_key( $article_key );
		if ( null === $post_id ) {
			wp_cache_set( $cache_key, null, 'revit_publisher', 300 );
			return null;
		}

		$result = $this->resolve_post( $post_id );
		wp_cache_set( $cache_key, $result, 'revit_publisher', 300 );

		return $result;
	}

	/**
	 * Resolve by post ID.
	 *
	 * @return array<string, mixed>|null
	 */
	public function resolve_post( int $post_id ): ?array {
		if ( ! $this->is_managed( $post_id ) ) {
			return null;
		}

		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return null;
		}

		return array(
			'post_id'      => $post_id,
			'article_key'  => (string) get_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::ARTICLE_KEY, true ),
			'title'        => get_the_title( $post_id ),
			'permalink'    => get_permalink( $post_id ),
			'edit_url'     => get_edit_post_link( $post_id, 'raw' ),
			'post_status'  => $post->post_status,
			'managed'      => true,
		);
	}

	/**
	 * Check if article key exists.
	 */
	public function exists( string $article_key ): bool {
		return null !== $this->resolve( $article_key );
	}

	/**
	 * Whether post is RevIt-managed.
	 */
	public function is_managed( int $post_id ): bool {
		return (bool) get_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::MANAGED, true );
	}

	/**
	 * Get permalink for article key.
	 */
	public function get_permalink( string $article_key ): ?string {
		$resolved = $this->resolve( $article_key );
		return is_array( $resolved ) ? (string) ( $resolved['permalink'] ?? null ) : null;
	}

	/**
	 * Classify link target availability.
	 */
	public function classify_target_status( string $article_key ): string {
		$resolved = $this->resolve( $article_key );
		if ( null === $resolved ) {
			return 'target_missing';
		}

		$status = (string) ( $resolved['post_status'] ?? '' );

		return match ( $status ) {
			'publish', 'draft', 'pending' => 'resolved',
			'private' => 'target_private',
			'future'  => 'unavailable',
			default   => 'unavailable',
		};
	}
}
