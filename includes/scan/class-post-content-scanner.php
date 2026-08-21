<?php
/**
 * Scan the live WordPress post state (not only package metadata).
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads title, blocks, taxonomies, SEO meta, and link inventory from WP.
 */
class RevIt_Publisher_Post_Content_Scanner {

	private RevIt_Publisher_Heading_Auditor $headings;

	public function __construct( ?RevIt_Publisher_Heading_Auditor $headings = null ) {
		$this->headings = $headings ?? new RevIt_Publisher_Heading_Auditor();
	}

	/**
	 * @return array<string, mixed>
	 */
	public function scan_post( int $post_id ): array {
		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return array();
		}

		$blocks     = function_exists( 'parse_blocks' ) ? parse_blocks( $post->post_content ) : array();
		$extracted  = $this->extract_blocks( $blocks );
		$permalinks = $this->managed_permalinks();
		$links      = $this->classify_links( $extracted['hrefs'], $permalinks, $post_id );
		$inbound    = $this->count_inbound( $post_id, $permalinks[ $post_id ] ?? get_permalink( $post_id ) );

		$seo_title = (string) get_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::SEO_TITLE, true );
		$meta_desc = (string) get_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::META_DESCRIPTION, true );
		$canonical = (string) get_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::CANONICAL, true );
		$index     = get_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::INDEX, true );
		$follow    = get_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::FOLLOW, true );
		$schema    = get_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::STRUCTURED_DATA, true );
		$engines   = get_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::VEHICLE_ENGINES, true );
		$types     = wp_get_post_terms( $post_id, RevIt_Publisher_Taxonomies::ARTICLE_TYPE, array( 'fields' => 'slugs' ) );
		$clusters  = wp_get_post_terms( $post_id, RevIt_Publisher_Taxonomies::CLUSTER, array( 'fields' => 'names' ) );

		$heading_audit = $this->headings->audit( $extracted['headings'], $post->post_title );
		$is_orphan     = 0 === $inbound;

		return array(
			'post_id'                  => $post_id,
			'title'                    => $post->post_title,
			'slug'                     => $post->post_name,
			'status'                   => $post->post_status,
			'excerpt'                  => $post->post_excerpt,
			'content'                  => $post->post_content,
			'content_text'             => $extracted['text'],
			'paragraph_count'          => $extracted['paragraphs'],
			'list_count'               => $extracted['lists'],
			'headings'                 => $extracted['headings'],
			'heading_audit'            => $heading_audit,
			'internal_links'           => $links['internal'],
			'outbound_links'           => $links['outbound'],
			'broken_internal'          => $links['broken'],
			'outbound_internal_count'  => count( $links['internal'] ),
			'inbound_count'            => $inbound,
			'broken_internal_count'    => count( $links['broken'] ),
			'images'                   => $extracted['images'],
			'images_missing_alt'       => $extracted['images_missing_alt'],
			'broken_media_count'       => $extracted['broken_media'],
			'featured_image'           => (int) get_post_thumbnail_id( $post_id ) > 0,
			'vehicle'                  => array(
				'manufacturer' => (string) get_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::VEHICLE_MANUFACTURER, true ),
				'model'        => (string) get_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::VEHICLE_MODEL, true ),
				'generation'   => (string) get_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::VEHICLE_GENERATION, true ),
				'trim'         => (string) get_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::VEHICLE_TRIM, true ),
				'engines'      => is_array( $engines ) ? $engines : array(),
				'label'        => RevIt_Publisher_Services::graph()->get_vehicle_label( $post_id ),
			),
			'vehicle_label'            => RevIt_Publisher_Services::graph()->get_vehicle_label( $post_id ),
			'article_type'             => ( ! is_wp_error( $types ) && ! empty( $types ) ) ? (string) $types[0] : '',
			'cluster'                  => ( ! is_wp_error( $clusters ) && ! empty( $clusters ) ) ? (string) $clusters[0] : (string) get_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::CLUSTER_KEY, true ),
			'canonical'                => $canonical,
			'index'                    => '' === $index ? true : '1' === (string) $index,
			'follow'                   => '' === $follow ? true : '1' === (string) $follow,
			'seo_title'                => $seo_title,
			'meta_description'         => $meta_desc,
			'structured_data'          => is_array( $schema ) ? $schema : array(),
			'primary_topic'            => (string) get_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::PRIMARY_TOPIC, true ),
			'secondary_topics'         => (array) get_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::SECONDARY_TOPICS, true ),
			'engines'                  => is_array( $engines ) ? $engines : array(),
			'published_at'             => $post->post_date_gmt,
			'modified_at'              => $post->post_modified_gmt,
			'is_orphan'                => $is_orphan,
			'linked_post_ids'          => $links['internal_post_ids'],
			'manufacturer'             => (string) get_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::VEHICLE_MANUFACTURER, true ),
			'model'                    => (string) get_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::VEHICLE_MODEL, true ),
		);
	}

	/**
	 * @param array<int, array<string, mixed>> $blocks Blocks.
	 * @return array<string, mixed>
	 */
	private function extract_blocks( array $blocks ): array {
		$headings = array();
		$hrefs    = array();
		$images   = array();
		$missing_alt = 0;
		$broken_media = 0;
		$paragraphs = 0;
		$lists = 0;
		$texts = array();

		$walk = function ( array $block ) use ( &$walk, &$headings, &$hrefs, &$images, &$missing_alt, &$broken_media, &$paragraphs, &$lists, &$texts ): void {
			$name = (string) ( $block['blockName'] ?? '' );
			$html = (string) ( $block['innerHTML'] ?? '' );
			if ( '' !== $html ) {
				$texts[] = wp_strip_all_tags( $html );
			}

			if ( 'core/heading' === $name ) {
				$level = (int) ( $block['attrs']['level'] ?? 2 );
				if ( preg_match( '/<h([1-6])\b/i', $html, $m ) ) {
					$level = (int) $m[1];
				}
				$headings[] = array(
					'level' => $level,
					'text'  => trim( wp_strip_all_tags( $html ) ),
				);
			}

			if ( 'core/paragraph' === $name ) {
				++$paragraphs;
			}
			if ( 'core/list' === $name ) {
				++$lists;
			}

			if ( preg_match_all( '/href=["\']([^"\']+)["\']/', $html, $found ) ) {
				foreach ( $found[1] as $href ) {
					$hrefs[] = $href;
				}
			}

			if ( 'core/image' === $name || str_contains( $html, '<img' ) ) {
				$alt = (string) ( $block['attrs']['alt'] ?? '' );
				if ( '' === $alt && ! preg_match( '/\balt=/i', $html ) ) {
					++$missing_alt;
				}
				$id = (int) ( $block['attrs']['id'] ?? 0 );
				if ( $id > 0 && ! wp_get_attachment_url( $id ) ) {
					++$broken_media;
				}
				$images[] = array(
					'alt' => $alt,
					'id'  => $id,
				);
			}

			foreach ( (array) ( $block['innerBlocks'] ?? array() ) as $child ) {
				if ( is_array( $child ) ) {
					$walk( $child );
				}
			}
		};

		foreach ( $blocks as $block ) {
			if ( is_array( $block ) ) {
				$walk( $block );
			}
		}

		return array(
			'headings'            => $headings,
			'hrefs'               => $hrefs,
			'images'              => $images,
			'images_missing_alt'  => $missing_alt,
			'broken_media'        => $broken_media,
			'paragraphs'          => $paragraphs,
			'lists'               => $lists,
			'text'                => trim( implode( ' ', $texts ) ),
		);
	}

	/**
	 * @param string[]             $hrefs Hrefs.
	 * @param array<int, string>   $permalinks post_id => permalink.
	 * @return array<string, mixed>
	 */
	private function classify_links( array $hrefs, array $permalinks, int $source_id ): array {
		$home     = untrailingslashit( home_url() );
		$internal = array();
		$outbound = array();
		$broken   = array();
		$ids      = array();

		foreach ( $hrefs as $href ) {
			$href = html_entity_decode( $href, ENT_QUOTES, 'UTF-8' );
			if ( '' === $href || str_starts_with( $href, '#' ) || str_starts_with( $href, 'mailto:' ) ) {
				continue;
			}

			$is_internal = str_starts_with( $href, '/' ) || str_starts_with( $href, $home );
			if ( ! $is_internal ) {
				$outbound[] = $href;
				continue;
			}

			$matched_id = 0;
			foreach ( $permalinks as $post_id => $permalink ) {
				if ( $post_id === $source_id ) {
					continue;
				}
				if ( untrailingslashit( $href ) === untrailingslashit( (string) $permalink ) || str_contains( $href, (string) $permalink ) ) {
					$matched_id = (int) $post_id;
					break;
				}
			}

			if ( $matched_id > 0 ) {
				$internal[] = $href;
				$ids[]      = $matched_id;
				continue;
			}

			$resolved = function_exists( 'url_to_postid' ) ? url_to_postid( $href ) : 0;
			if ( $resolved > 0 ) {
				$internal[] = $href;
				$ids[]      = $resolved;
				continue;
			}

			$broken[] = $href;
		}

		return array(
			'internal'           => $internal,
			'outbound'           => $outbound,
			'broken'             => $broken,
			'internal_post_ids'  => array_values( array_unique( $ids ) ),
		);
	}

	/**
	 * @return array<int, string>
	 */
	private function managed_permalinks(): array {
		$map = array();
		foreach ( RevIt_Publisher_Services::registry()->get_managed_post_ids() as $post_id ) {
			$link = get_permalink( (int) $post_id );
			if ( is_string( $link ) && '' !== $link ) {
				$map[ (int) $post_id ] = $link;
			}
		}

		return $map;
	}

	private function count_inbound( int $post_id, string $permalink ): int {
		if ( '' === $permalink ) {
			return 0;
		}

		$count = 0;
		foreach ( RevIt_Publisher_Services::registry()->get_managed_post_ids() as $source_id ) {
			if ( (int) $source_id === $post_id ) {
				continue;
			}
			$source = get_post( (int) $source_id );
			if ( ! $source instanceof WP_Post ) {
				continue;
			}
			if ( str_contains( $source->post_content, $permalink ) ) {
				++$count;
			}
		}

		return $count;
	}
}
