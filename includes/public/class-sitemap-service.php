<?php
/**
 * WordPress core sitemap integration for RevIt public content.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ensures indexable vehicle hubs and managed articles appear in wp_sitemaps.
 */
class RevIt_Publisher_Sitemap_Service {

	/** @var string[] */
	private const OPERATIONAL_POST_TYPES = array(
		RevIt_Publisher_Operations_Post_Types::AUDIT_SNAPSHOT,
		RevIt_Publisher_Operations_Post_Types::ISSUE,
		RevIt_Publisher_Operations_Post_Types::REDIRECT,
		RevIt_Publisher_Operations_Post_Types::LINK_CHANGE,
		RevIt_Publisher_Operations_Post_Types::NOT_FOUND,
		RevIt_Publisher_Content_Plan_Post_Type::POST_TYPE,
	);

	public function init(): void {
		add_filter( 'wp_sitemaps_post_types', array( $this, 'filter_post_types' ) );
		add_filter( 'wp_sitemaps_posts_query_args', array( $this, 'filter_posts_query_args' ), 10, 2 );
		add_filter( 'wp_sitemaps_posts_entry', array( $this, 'filter_posts_entry' ), 10, 3 );
	}

	/**
	 * @param array<string, WP_Post_Type> $post_types
	 * @return array<string, WP_Post_Type>
	 */
	public function filter_post_types( array $post_types ): array {
		foreach ( self::OPERATIONAL_POST_TYPES as $type ) {
			unset( $post_types[ $type ] );
		}
		return $post_types;
	}

	/**
	 * @param array<string, mixed> $args
	 * @return array<string, mixed>
	 */
	public function filter_posts_query_args( array $args, string $post_type ): array {
		if ( RevIt_Publisher_Vehicle_Hub_Post_Type::POST_TYPE === $post_type ) {
			$args['post_status'] = 'publish';
		}

		return $args;
	}

	/**
	 * @param array<string, mixed> $entry
	 * @param WP_Post              $post
	 * @return array<string, mixed>|false
	 */
	public function filter_posts_entry( array $entry, WP_Post $post, string $post_type ): array|false {
		if ( ! $this->is_post_indexable( (int) $post->ID, $post_type ) ) {
			return false;
		}
		return $entry;
	}

	public function is_post_indexable( int $post_id, ?string $post_type = null ): bool {
		$post_type = $post_type ?? get_post_type( $post_id );
		if ( ! is_string( $post_type ) ) {
			return false;
		}

		if ( in_array( $post_type, self::OPERATIONAL_POST_TYPES, true ) ) {
			return false;
		}

		if ( 'publish' !== get_post_status( $post_id ) ) {
			return false;
		}

		if ( RevIt_Publisher_Vehicle_Hub_Post_Type::POST_TYPE === $post_type ) {
			return true;
		}

		if ( 'post' === $post_type ) {
			if ( ! RevIt_Publisher_Services::resolver()->is_managed( $post_id ) ) {
				return true;
			}
			return '1' === (string) get_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::INDEX, true );
		}

		return true;
	}

	/**
	 * @return int[]
	 */
	public function get_indexable_post_ids(): array {
		$ids = array();

		$hubs = get_posts(
			array(
				'post_type'      => RevIt_Publisher_Vehicle_Hub_Post_Type::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);
		foreach ( $hubs as $hub_id ) {
			if ( $this->is_post_indexable( (int) $hub_id, RevIt_Publisher_Vehicle_Hub_Post_Type::POST_TYPE ) ) {
				$ids[] = (int) $hub_id;
			}
		}

		foreach ( RevIt_Publisher_Services::registry()->get_managed_post_ids() as $post_id ) {
			if ( $this->is_post_indexable( (int) $post_id, 'post' ) ) {
				$ids[] = (int) $post_id;
			}
		}

		return array_values( array_unique( $ids ) );
	}
}
