<?php
/**
 * Maps Search Console page URLs to RevIt content.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RevIt_Publisher_GSC_Page_Mapper {

	/**
	 * @return array<string, mixed>
	 */
	public function map_url( string $page_url ): array {
		$normalized = $this->normalize_url( $page_url );
		$post_id    = url_to_postid( $normalized );
		if ( $post_id > 0 ) {
			return $this->map_post( (int) $post_id, $normalized );
		}

		$hub_id = $this->find_hub_by_url( $normalized );
		if ( null !== $hub_id ) {
			return $this->map_hub( $hub_id, $normalized );
		}

		return array(
			'page_url'     => $normalized,
			'post_id'      => 0,
			'hub_id'       => 0,
			'article_key'  => '',
			'vehicle'      => '',
			'cluster_key'  => '',
			'article_type' => '',
			'mapped'       => false,
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function map_post( int $post_id, ?string $page_url = null ): array {
		$permalink = $page_url ?? get_permalink( $post_id );
		$vehicle   = RevIt_Publisher_Services::graph()->get_vehicle_label( $post_id );
		$types     = wp_get_post_terms( $post_id, RevIt_Publisher_Taxonomies::ARTICLE_TYPE, array( 'fields' => 'slugs' ) );
		return array(
			'page_url'     => is_string( $permalink ) ? $this->normalize_url( $permalink ) : '',
			'post_id'      => $post_id,
			'hub_id'       => 0,
			'article_key'  => (string) get_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::ARTICLE_KEY, true ),
			'vehicle'      => $vehicle,
			'cluster_key'  => (string) get_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::CLUSTER_KEY, true ),
			'article_type' => ( ! is_wp_error( $types ) && ! empty( $types ) ) ? (string) $types[0] : '',
			'mapped'       => RevIt_Publisher_Services::resolver()->is_managed( $post_id ),
		);
	}

	public function normalize_url( string $url ): string {
		$url = esc_url_raw( $url );
		$url = untrailingslashit( $url );
		$home = untrailingslashit( home_url() );
		if ( str_starts_with( $url, $home ) ) {
			return $url;
		}
		return $url;
	}

	private function find_hub_by_url( string $url ): ?int {
		$hubs = get_posts(
			array(
				'post_type'      => RevIt_Publisher_Vehicle_Hub_Post_Type::POST_TYPE,
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);
		foreach ( $hubs as $hub_id ) {
			$permalink = get_permalink( (int) $hub_id );
			if ( is_string( $permalink ) && $this->normalize_url( $permalink ) === $url ) {
				return (int) $hub_id;
			}
		}
		return null;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function map_hub( int $hub_id, string $page_url ): array {
		$identity = RevIt_Publisher_Services::vehicle_hubs()->get_identity( $hub_id );
		$vehicle  = RevIt_Publisher_Vehicle_Identity::label(
			(string) ( $identity['manufacturer'] ?? '' ),
			(string) ( $identity['model'] ?? '' ),
			(string) ( $identity['generation'] ?? '' ),
			(string) ( $identity['trim'] ?? '' )
		);
		return array(
			'page_url'     => $page_url,
			'post_id'      => 0,
			'hub_id'       => $hub_id,
			'article_key'  => '',
			'vehicle'      => $vehicle,
			'cluster_key'  => '',
			'article_type' => 'vehicle_hub',
			'mapped'       => true,
		);
	}
}
