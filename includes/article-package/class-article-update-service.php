<?php
/**
 * Article package update and diff service.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles re-import updates for existing article_key posts.
 */
class RevIt_Publisher_Article_Update_Service {

	public const MODE_FULL          = 'full';
	public const MODE_SEO           = 'seo';
	public const MODE_RELATIONSHIPS = 'relationships';

	private RevIt_Publisher_Article_Package_Validator $validator;
	private RevIt_Publisher_Article_Registry $registry;
	private RevIt_Publisher_Vehicle_Taxonomy_Service $vehicle_service;
	private RevIt_Publisher_Cluster_Service $cluster_service;
	private RevIt_Publisher_Content_Renderer $content_renderer;
	private RevIt_Publisher_Package_Hash $package_hash;

	public function __construct(
		RevIt_Publisher_Article_Package_Validator $validator,
		RevIt_Publisher_Article_Registry $registry,
		RevIt_Publisher_Vehicle_Taxonomy_Service $vehicle_service,
		RevIt_Publisher_Cluster_Service $cluster_service,
		RevIt_Publisher_Content_Renderer $content_renderer,
		RevIt_Publisher_Package_Hash $package_hash
	) {
		$this->validator       = $validator;
		$this->registry        = $registry;
		$this->vehicle_service = $vehicle_service;
		$this->cluster_service = $cluster_service;
		$this->content_renderer = $content_renderer;
		$this->package_hash    = $package_hash;
	}

	/**
	 * Preview update diff.
	 *
	 * @return array<string, mixed>
	 */
	public function preview_update( int $post_id, mixed $data, string $mode = self::MODE_FULL ): array {
		$validation = $this->validate_existing( $post_id, $data );
		if ( ! $validation['valid'] ) {
			return $validation;
		}

		$package = $validation['package'];
		$hash    = RevIt_Publisher_Services::health_service()->compare_package_hash( $post_id, $package );

		if ( 'unchanged' === $hash['status'] ) {
			return array(
				'valid'   => true,
				'status'  => 'unchanged',
				'message' => __( 'No changes detected.', 'revit-publisher' ),
				'hash'    => $hash,
			);
		}

		return array(
			'valid'              => true,
			'status'             => 'changed',
			'post_id'            => $post_id,
			'article_key'        => (string) $package->article->article_key,
			'mode'               => $mode,
			'hash'               => $hash,
			'diff'               => $this->build_diff( $post_id, $package, $mode ),
			'manual_edits'       => $this->detect_manual_edits( $post_id ),
			'revision_note'      => __( 'Previous version remains available in WordPress revisions.', 'revit-publisher' ),
		);
	}

	/**
	 * Apply approved update.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public function apply_update( int $post_id, mixed $data, string $mode = self::MODE_FULL ) {
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new WP_Error( 'revit_forbidden', __( 'You cannot edit this post.', 'revit-publisher' ), array( 'status' => 403 ) );
		}

		$preview = $this->preview_update( $post_id, $data, $mode );
		if ( empty( $preview['valid'] ) ) {
			return new WP_Error( 'revit_invalid_update', __( 'Update preview failed.', 'revit-publisher' ), array( 'status' => 400 ) );
		}
		if ( 'unchanged' === ( $preview['status'] ?? '' ) ) {
			return array(
				'success' => true,
				'status'  => 'unchanged',
				'post_id' => $post_id,
			);
		}

		$package = $this->normalize_package( $data );
		$post    = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return new WP_Error( 'revit_invalid_post', __( 'Post not found.', 'revit-publisher' ) );
		}

		wp_save_post_revision( $post_id );

		$update_data = array( 'ID' => $post_id );

		if ( self::MODE_FULL === $mode ) {
			$update_data['post_title']   = sanitize_text_field( (string) $package->article->title );
			$update_data['post_excerpt'] = sanitize_text_field( (string) $package->article->excerpt );
			$update_data['post_content'] = $this->content_renderer->render( $package->content );
		}

		if ( ! empty( $update_data['post_title'] ) || ! empty( $update_data['post_content'] ) ) {
			$result = wp_update_post( wp_slash( $update_data ), true );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		if ( in_array( $mode, array( self::MODE_FULL, self::MODE_SEO ), true ) ) {
			$this->apply_seo_meta( $post_id, $package );
		}

		if ( in_array( $mode, array( self::MODE_FULL, self::MODE_RELATIONSHIPS ), true ) ) {
			$this->apply_relationship_meta( $post_id, $package );
		}

		if ( self::MODE_FULL === $mode ) {
			$this->vehicle_service->sync_post( $post_id, $package->vehicle );
			$this->cluster_service->sync_post( $post_id, $package->cluster );
			wp_set_object_terms( $post_id, sanitize_key( (string) $package->article->article_type ), RevIt_Publisher_Taxonomies::ARTICLE_TYPE, false );
			update_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::SOURCES, $this->sanitize_sources( $package->sources ?? array() ) );
		}

		update_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::PACKAGE_HASH, $this->package_hash->compute( $package ) );
		update_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::IMPORTED_AT, gmdate( 'c' ) );

		if ( self::MODE_FULL === $mode ) {
			$content = (string) get_post_field( 'post_content', $post_id );
			update_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::LAST_IMPORT_CONTENT_HASH, hash( 'sha256', $content ) );
		}

		RevIt_Publisher_Services::review_status()->sync_status( $post_id );
		RevIt_Publisher_Services::topic_overlaps()->invalidate_cache();

		return array(
			'success'     => true,
			'status'      => 'updated',
			'post_id'     => $post_id,
			'article_key' => (string) $package->article->article_key,
			'mode'        => $mode,
			'edit_url'    => get_edit_post_link( $post_id, 'raw' ),
		);
	}

	/**
	 * @return array{valid: bool, package?: object, errors?: array<int, array{path: string, message: string}>}
	 */
	private function validate_existing( int $post_id, mixed $data ): array {
		$validation = $this->validator->validate( $data );
		if ( ! $validation['valid'] ) {
			return array(
				'valid'  => false,
				'errors' => $validation['errors'],
			);
		}

		$package     = $this->normalize_package( $data );
		$article_key = (string) $package->article->article_key;
		$existing_id = $this->registry->find_post_id_by_article_key( $article_key );

		if ( null === $existing_id || $existing_id !== $post_id ) {
			return array(
				'valid'  => false,
				'errors' => array(
					array(
						'path'    => 'article.article_key',
						'message' => __( 'Package article_key must match the existing post.', 'revit-publisher' ),
					),
				),
			);
		}

		if ( ! (bool) get_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::MANAGED, true ) ) {
			return array(
				'valid'  => false,
				'errors' => array(
					array(
						'path'    => '',
						'message' => __( 'Post is not RevIt-managed.', 'revit-publisher' ),
					),
				),
			);
		}

		return array(
			'valid'   => true,
			'package' => $package,
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function build_diff( int $post_id, object $package, string $mode ): array {
		$diff = array(
			'article'       => array(),
			'seo'           => array(),
			'content'       => array(),
			'vehicle'       => array( 'changed' => false ),
			'cluster'       => array( 'changed' => false ),
			'relationships' => array(),
			'sources'       => array(),
		);

		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return $diff;
		}

		if ( in_array( $mode, array( self::MODE_FULL, self::MODE_SEO ), true ) ) {
			if ( $post->post_title !== (string) $package->article->title ) {
				$diff['article']['title'] = array( 'changed' => true );
			}
			if ( $post->post_excerpt !== (string) $package->article->excerpt ) {
				$diff['article']['excerpt'] = array( 'changed' => true );
			}

			$fields = array(
				'seo_title'        => RevIt_Publisher_Post_Meta_Keys::SEO_TITLE,
				'meta_description' => RevIt_Publisher_Post_Meta_Keys::META_DESCRIPTION,
				'primary_topic'    => RevIt_Publisher_Post_Meta_Keys::PRIMARY_TOPIC,
			);
			foreach ( $fields as $key => $meta_key ) {
				$stored = (string) get_post_meta( $post_id, $meta_key, true );
				$incoming = (string) ( $package->seo->{$key} ?? '' );
				if ( $stored !== $incoming ) {
					$diff['seo'][ $key ] = array( 'changed' => true );
				}
			}
		}

		if ( self::MODE_FULL === $mode ) {
			$rendered = $this->content_renderer->render( $package->content );
			if ( $post->post_content !== $rendered ) {
				$incoming_blocks = count( parse_blocks( $rendered ) );
				$stored_blocks   = count( parse_blocks( $post->post_content ) );
				$diff['content'] = array(
					'changed'       => true,
					'blocks_added'  => max( 0, $incoming_blocks - $stored_blocks ),
					'blocks_removed'=> max( 0, $stored_blocks - $incoming_blocks ),
				);
			}
		}

		if ( in_array( $mode, array( self::MODE_FULL, self::MODE_RELATIONSHIPS ), true ) ) {
			$stored_links = get_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::INTERNAL_LINKS, true );
			$stored_links = is_array( $stored_links ) ? $stored_links : array();
			$incoming_links = $this->sanitize_links( $package->internal_links ?? array() );
			if ( wp_json_encode( $stored_links ) !== wp_json_encode( $incoming_links ) ) {
				$diff['relationships']['internal_links'] = array(
					'changed' => true,
					'added'   => max( 0, count( $incoming_links ) - count( $stored_links ) ),
				);
			}
		}

		return $diff;
	}

	/**
	 * Detect manual WordPress edits since last import.
	 */
	public function detect_manual_edits( int $post_id ): bool {
		$stored_hash = (string) get_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::LAST_IMPORT_CONTENT_HASH, true );
		if ( '' === $stored_hash ) {
			return false;
		}

		$content = (string) get_post_field( 'post_content', $post_id );
		return hash( 'sha256', $content ) !== $stored_hash;
	}

	private function apply_seo_meta( int $post_id, object $package ): void {
		update_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::PRIMARY_TOPIC, sanitize_text_field( (string) $package->seo->primary_topic ) );
		update_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::SECONDARY_TOPICS, $this->sanitize_string_array( $package->seo->secondary_topics ?? array() ) );
		update_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::SEARCH_INTENT, sanitize_key( (string) $package->seo->search_intent ) );
		update_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::SEO_TITLE, sanitize_text_field( (string) $package->seo->seo_title ) );
		update_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::META_DESCRIPTION, sanitize_textarea_field( (string) $package->seo->meta_description ) );
		update_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::CANONICAL, sanitize_text_field( (string) $package->seo->canonical ) );
		update_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::INDEX, ! empty( $package->seo->index ) ? '1' : '0' );
		update_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::FOLLOW, ! empty( $package->seo->follow ) ? '1' : '0' );
	}

	private function apply_relationship_meta( int $post_id, object $package ): void {
		update_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::INTERNAL_LINKS, $this->sanitize_links( $package->internal_links ?? array() ) );
		update_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::RELATED_ARTICLES, $this->sanitize_related( $package->related_articles ?? array() ) );
	}

	private function normalize_package( mixed $data ): object {
		return is_array( $data ) ? json_decode( wp_json_encode( $data ), false ) : $data;
	}

	/**
	 * @param mixed $values
	 * @return string[]
	 */
	private function sanitize_string_array( mixed $values ): array {
		if ( ! is_array( $values ) ) {
			return array();
		}
		return array_values( array_filter( array_map( static fn( $v ): string => sanitize_text_field( (string) $v ), $values ) ) );
	}

	/**
	 * @param mixed $links
	 * @return array<int, array<string, mixed>>
	 */
	private function sanitize_links( mixed $links ): array {
		if ( ! is_array( $links ) ) {
			return array();
		}
		$out = array();
		foreach ( $links as $link ) {
			$item = (object) $link;
			$out[] = array(
				'target_article_key' => sanitize_text_field( (string) ( $item->target_article_key ?? '' ) ),
				'preferred_anchor'   => sanitize_text_field( (string) ( $item->preferred_anchor ?? '' ) ),
				'relationship'       => sanitize_key( (string) ( $item->relationship ?? '' ) ),
				'required'             => ! empty( $item->required ),
			);
		}
		return $out;
	}

	/**
	 * @param mixed $related
	 * @return array<int, array<string, mixed>>
	 */
	private function sanitize_related( mixed $related ): array {
		if ( ! is_array( $related ) ) {
			return array();
		}
		$out = array();
		foreach ( $related as $item ) {
			$row = (object) $item;
			$out[] = array(
				'article_key'  => sanitize_text_field( (string) ( $row->article_key ?? '' ) ),
				'relationship' => sanitize_key( (string) ( $row->relationship ?? '' ) ),
				'priority'     => (int) ( $row->priority ?? 0 ),
			);
		}
		return $out;
	}

	/**
	 * @param mixed $sources
	 * @return array<int, array<string, mixed>>
	 */
	private function sanitize_sources( mixed $sources ): array {
		if ( ! is_array( $sources ) ) {
			return array();
		}
		$out = array();
		foreach ( $sources as $source ) {
			$row = (object) $source;
			$url = esc_url_raw( (string) ( $row->url ?? '' ) );
			if ( '' === $url ) {
				continue;
			}
			$out[] = array(
				'source_name' => sanitize_text_field( (string) ( $row->source_name ?? '' ) ),
				'title'       => sanitize_text_field( (string) ( $row->title ?? '' ) ),
				'url'         => $url,
				'source_type' => sanitize_key( (string) ( $row->source_type ?? '' ) ),
				'purpose'     => sanitize_textarea_field( (string) ( $row->purpose ?? '' ) ),
			);
		}
		return $out;
	}
}
