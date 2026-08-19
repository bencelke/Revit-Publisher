<?php
/**
 * SERP snippet preview and validation.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Validates title, description, slug, canonical, and index state for SERP display.
 */
class RevIt_Publisher_SERP_Preview_Service {

	private RevIt_Publisher_Sitemap_Service $sitemap;

	public function __construct( RevIt_Publisher_Sitemap_Service $sitemap ) {
		$this->sitemap = $sitemap;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function preview_post( int $post_id ): array {
		$post_type = get_post_type( $post_id );
		if ( RevIt_Publisher_Vehicle_Hub_Post_Type::POST_TYPE === $post_type ) {
			return $this->preview_hub( $post_id );
		}
		return $this->preview_article( $post_id );
	}

	/**
	 * @return array<string, mixed>
	 */
	public function preview_article( int $post_id ): array {
		$title       = (string) get_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::SEO_TITLE, true ) ?: get_the_title( $post_id );
		$description = (string) get_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::META_DESCRIPTION, true );
		$canonical   = (string) get_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::CANONICAL, true );
		$slug        = (string) get_post_field( 'post_name', $post_id );
		$permalink   = get_permalink( $post_id );
		$indexable   = $this->sitemap->is_post_indexable( $post_id, 'post' );

		$url = 'auto' === $canonical ? ( is_string( $permalink ) ? $permalink : '' ) : esc_url_raw( $canonical );

		return $this->build_preview(
			$post_id,
			'post',
			$title,
			$description,
			$slug,
			$url,
			$indexable
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function preview_hub( int $hub_id ): array {
		$title       = get_the_title( $hub_id );
		$description = (string) get_post_meta( $hub_id, RevIt_Publisher_Vehicle_Hub_Meta_Keys::INTRO, true );
		if ( '' === trim( $description ) ) {
			$description = (string) get_post_field( 'post_excerpt', $hub_id );
		}
		$slug      = (string) get_post_field( 'post_name', $hub_id );
		$permalink = get_permalink( $hub_id );
		$indexable = $this->sitemap->is_post_indexable( $hub_id, RevIt_Publisher_Vehicle_Hub_Post_Type::POST_TYPE );

		return $this->build_preview(
			$hub_id,
			RevIt_Publisher_Vehicle_Hub_Post_Type::POST_TYPE,
			$title,
			$description,
			$slug,
			is_string( $permalink ) ? $permalink : '',
			$indexable
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function build_preview(
		int $post_id,
		string $post_type,
		string $title,
		string $description,
		string $slug,
		string $canonical_url,
		bool $indexable
	): array {
		$title_len = mb_strlen( $title );
		$desc_len  = mb_strlen( $description );

		$issues = array();
		if ( $title_len < 30 ) {
			$issues[] = array( 'field' => 'title', 'level' => 'warning', 'message' => __( 'Title may be too short for SERP.', 'revit-publisher' ) );
		} elseif ( $title_len > 60 ) {
			$issues[] = array( 'field' => 'title', 'level' => 'warning', 'message' => __( 'Title may truncate in SERP.', 'revit-publisher' ) );
		}
		if ( '' === trim( $description ) ) {
			$issues[] = array( 'field' => 'description', 'level' => 'error', 'message' => __( 'Missing meta description.', 'revit-publisher' ) );
		} elseif ( $desc_len < 70 ) {
			$issues[] = array( 'field' => 'description', 'level' => 'warning', 'message' => __( 'Description may be too short.', 'revit-publisher' ) );
		} elseif ( $desc_len > 160 ) {
			$issues[] = array( 'field' => 'description', 'level' => 'warning', 'message' => __( 'Description may truncate in SERP.', 'revit-publisher' ) );
		}
		if ( '' === $slug ) {
			$issues[] = array( 'field' => 'slug', 'level' => 'error', 'message' => __( 'Missing URL slug.', 'revit-publisher' ) );
		}
		if ( '' === $canonical_url ) {
			$issues[] = array( 'field' => 'canonical', 'level' => 'error', 'message' => __( 'Missing canonical URL.', 'revit-publisher' ) );
		}
		if ( ! $indexable ) {
			$issues[] = array( 'field' => 'index', 'level' => 'info', 'message' => __( 'Not indexable in sitemap.', 'revit-publisher' ) );
		}

		return array(
			'post_id'     => $post_id,
			'post_type'   => $post_type,
			'snippet'     => array(
				'title'       => $title,
				'description' => $description,
				'url'         => $canonical_url,
				'slug'        => $slug,
			),
			'lengths'     => array(
				'title'       => $title_len,
				'description' => $desc_len,
			),
			'indexable'   => $indexable,
			'issues'      => $issues,
			'valid'       => ! array_filter( $issues, static fn( $i ) => 'error' === ( $i['level'] ?? '' ) ),
		);
	}
}
