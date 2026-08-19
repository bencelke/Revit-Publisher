<?php
/**
 * Article key registry.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Maps stable article_key values to WordPress posts.
 */
class RevIt_Publisher_Article_Registry {

	/**
	 * Find a post ID by article key.
	 */
	public function find_post_id_by_article_key( string $article_key ): ?int {
		$article_key = sanitize_text_field( $article_key );
		if ( '' === $article_key ) {
			return null;
		}

		$posts = get_posts(
			array(
				'post_type'      => 'post',
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => RevIt_Publisher_Post_Meta_Keys::ARTICLE_KEY,
						'value' => $article_key,
					),
				),
			)
		);

		if ( empty( $posts ) ) {
			return null;
		}

		return (int) $posts[0];
	}

	/**
	 * Determine whether an article key already exists.
	 */
	public function exists( string $article_key ): bool {
		return null !== $this->find_post_id_by_article_key( $article_key );
	}

	/**
	 * Register article key on a post after successful import.
	 *
	 * @return true|WP_Error
	 */
	public function register( int $post_id, string $article_key ) {
		$existing = $this->find_post_id_by_article_key( $article_key );
		if ( null !== $existing && $existing !== $post_id ) {
			return new WP_Error(
				'revit_duplicate_article_key',
				__( 'Article key is already registered to another post.', 'revit-publisher' ),
				array( 'post_id' => $existing )
			);
		}

		update_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::ARTICLE_KEY, sanitize_text_field( $article_key ) );
		update_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::MANAGED, '1' );

		return true;
	}

	/**
	 * Count imported RevIt-managed articles.
	 */
	public function count_managed_articles(): int {
		$query = new WP_Query(
			array(
				'post_type'      => 'post',
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => RevIt_Publisher_Post_Meta_Keys::MANAGED,
						'value' => '1',
					),
				),
			)
		);

		return (int) $query->found_posts;
	}
}
