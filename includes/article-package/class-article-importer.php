<?php
/**
 * Article package importer.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Imports validated revit-article-v1 packages as WordPress posts.
 */
class RevIt_Publisher_Article_Importer {

	/**
	 * Allowed import post statuses.
	 *
	 * @var string[]
	 */
	public const ALLOWED_STATUSES = array( 'draft', 'pending', 'private' );

	/**
	 * Validator service.
	 *
	 * @var RevIt_Publisher_Article_Package_Validator
	 */
	private RevIt_Publisher_Article_Package_Validator $validator;

	/**
	 * Article registry.
	 *
	 * @var RevIt_Publisher_Article_Registry
	 */
	private RevIt_Publisher_Article_Registry $registry;

	/**
	 * Vehicle taxonomy service.
	 *
	 * @var RevIt_Publisher_Vehicle_Taxonomy_Service
	 */
	private RevIt_Publisher_Vehicle_Taxonomy_Service $vehicle_service;

	/**
	 * Cluster service.
	 *
	 * @var RevIt_Publisher_Cluster_Service
	 */
	private RevIt_Publisher_Cluster_Service $cluster_service;

	/**
	 * Content renderer.
	 *
	 * @var RevIt_Publisher_Content_Renderer
	 */
	private RevIt_Publisher_Content_Renderer $content_renderer;

	/**
	 * Package hash utility.
	 *
	 * @var RevIt_Publisher_Package_Hash
	 */
	private RevIt_Publisher_Package_Hash $package_hash;

	/**
	 * Constructor.
	 */
	public function __construct(
		RevIt_Publisher_Article_Package_Validator $validator,
		RevIt_Publisher_Article_Registry $registry,
		RevIt_Publisher_Vehicle_Taxonomy_Service $vehicle_service,
		RevIt_Publisher_Cluster_Service $cluster_service,
		RevIt_Publisher_Content_Renderer $content_renderer,
		RevIt_Publisher_Package_Hash $package_hash
	) {
		$this->validator         = $validator;
		$this->registry          = $registry;
		$this->vehicle_service   = $vehicle_service;
		$this->cluster_service   = $cluster_service;
		$this->content_renderer  = $content_renderer;
		$this->package_hash      = $package_hash;
	}

	/**
	 * Import a package.
	 *
	 * @param mixed $data Package data.
	 * @return array<string, mixed>
	 */
	public function import( mixed $data, array $context = array() ): array {
		$validation = $this->validator->validate( $data );
		if ( ! $validation['valid'] ) {
			return array(
				'success' => false,
				'status'  => 'validation_failed',
				'errors'  => $validation['errors'],
			);
		}

		$package = is_array( $data ) ? json_decode( wp_json_encode( $data ), false ) : $data;
		if ( ! is_object( $package ) ) {
			return array(
				'success' => false,
				'status'  => 'validation_failed',
				'errors'  => array(
					array(
						'path'    => '',
						'message' => __( 'Invalid package payload.', 'revit-publisher' ),
					),
				),
			);
		}

		$article_key = (string) $package->article->article_key;
		$existing_id = $this->registry->find_post_id_by_article_key( $article_key );
		if ( null !== $existing_id ) {
			return array(
				'success'     => false,
				'status'      => 'existing_article',
				'article_key' => $article_key,
				'post_id'     => $existing_id,
				'edit_url'    => get_edit_post_link( $existing_id, 'raw' ),
			);
		}

		$import_status = $this->resolve_post_status( $package );
		if ( is_wp_error( $import_status ) ) {
			return array(
				'success' => false,
				'status'  => 'validation_failed',
				'errors'  => array(
					array(
						'path'    => 'publishing.status',
						'message' => $import_status->get_error_message(),
					),
				),
			);
		}

		$post_content = $this->content_renderer->render( $package->content );
		$post_excerpt = sanitize_text_field( (string) $package->article->excerpt );

		$post_data = array(
			'post_type'    => 'post',
			'post_status'  => $import_status,
			'post_title'   => sanitize_text_field( (string) $package->article->title ),
			'post_name'    => sanitize_title( (string) $package->article->slug ),
			'post_content' => $post_content,
			'post_excerpt' => $post_excerpt,
			'comment_status' => ! empty( $package->publishing->allow_comments ) ? 'open' : 'closed',
		);

		if ( ! empty( $package->publishing->author ) ) {
			$post_data['post_author'] = (int) $package->publishing->author;
		}

		$post_id = wp_insert_post( wp_slash( $post_data ), true );
		if ( is_wp_error( $post_id ) ) {
			return array(
				'success' => false,
				'status'  => 'import_failed',
				'errors'  => array(
					array(
						'path'    => '',
						'message' => $post_id->get_error_message(),
					),
				),
			);
		}

		$post_id = (int) $post_id;

		$meta_result = $this->store_metadata( $post_id, $package );
		if ( is_wp_error( $meta_result ) ) {
			wp_delete_post( $post_id, true );
			return array(
				'success' => false,
				'status'  => 'import_failed',
				'errors'  => array(
					array(
						'path'    => '',
						'message' => $meta_result->get_error_message(),
					),
				),
			);
		}

		$vehicle_result = $this->vehicle_service->sync_post( $post_id, $package->vehicle );
		if ( is_wp_error( $vehicle_result ) ) {
			wp_delete_post( $post_id, true );
			return array(
				'success' => false,
				'status'  => 'import_failed',
				'errors'  => array(
					array(
						'path'    => 'vehicle',
						'message' => $vehicle_result->get_error_message(),
					),
				),
			);
		}

		$cluster_result = $this->cluster_service->sync_post( $post_id, $package->cluster );
		if ( is_wp_error( $cluster_result ) ) {
			wp_delete_post( $post_id, true );
			return array(
				'success' => false,
				'status'  => 'import_failed',
				'errors'  => array(
					array(
						'path'    => 'cluster',
						'message' => $cluster_result->get_error_message(),
					),
				),
			);
		}

		$article_type = sanitize_key( (string) $package->article->article_type );
		wp_set_object_terms( $post_id, array( $article_type ), RevIt_Publisher_Taxonomies::ARTICLE_TYPE, false );

		$register_result = $this->registry->register( $post_id, $article_key );
		if ( is_wp_error( $register_result ) ) {
			wp_delete_post( $post_id, true );
			return array(
				'success' => false,
				'status'  => 'import_failed',
				'errors'  => array(
					array(
						'path'    => 'article.article_key',
						'message' => $register_result->get_error_message(),
					),
				),
			);
		}

		if ( ! empty( $package->publishing->featured_image_id ) ) {
			set_post_thumbnail( $post_id, (int) $package->publishing->featured_image_id );
		}

		update_post_meta(
			$post_id,
			RevIt_Publisher_Post_Meta_Keys::LAST_IMPORT_CONTENT_HASH,
			hash( 'sha256', (string) get_post_field( 'post_content', $post_id ) )
		);
		RevIt_Publisher_Services::review_status()->sync_status( $post_id );

		$batch_id = sanitize_key( (string) ( $context['batch_id'] ?? '' ) );
		if ( '' !== $batch_id ) {
			update_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::IMPORT_BATCH_ID, $batch_id );
		}

		return array(
			'success'     => true,
			'status'      => 'created',
			'article_key' => $article_key,
			'post_id'     => $post_id,
			'edit_url'    => get_edit_post_link( $post_id, 'raw' ),
			'post_status' => $import_status,
		);
	}

	/**
	 * Resolve and validate post status with defense in depth.
	 *
	 * @return string|WP_Error
	 */
	private function resolve_post_status( object $package ) {
		$status = sanitize_key( (string) ( $package->publishing->status ?? 'draft' ) );

		if ( 'publish' === $status || ! in_array( $status, self::ALLOWED_STATUSES, true ) ) {
			return new WP_Error(
				'revit_invalid_publish_status',
				__( 'Imported packages cannot be published directly. Allowed statuses: draft, pending, private.', 'revit-publisher' )
			);
		}

		return $status;
	}

	/**
	 * Store RevIt metadata on post.
	 *
	 * @return true|WP_Error
	 */
	private function store_metadata( int $post_id, object $package ) {
		update_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::SCHEMA_VERSION, RevIt_Publisher_Article_Package_Validator::SCHEMA_VERSION );
		update_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::IMPORTED_AT, gmdate( 'c' ) );
		update_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::PACKAGE_HASH, $this->package_hash->compute( $package ) );

		update_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::PRIMARY_TOPIC, sanitize_text_field( (string) $package->seo->primary_topic ) );
		update_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::SECONDARY_TOPICS, $this->sanitize_string_array( $package->seo->secondary_topics ?? array() ) );
		update_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::SEARCH_INTENT, sanitize_key( (string) $package->seo->search_intent ) );
		update_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::SEO_TITLE, sanitize_text_field( (string) $package->seo->seo_title ) );
		update_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::META_DESCRIPTION, sanitize_textarea_field( (string) $package->seo->meta_description ) );
		update_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::CANONICAL, sanitize_text_field( (string) $package->seo->canonical ) );
		update_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::INDEX, ! empty( $package->seo->index ) ? '1' : '0' );
		update_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::FOLLOW, ! empty( $package->seo->follow ) ? '1' : '0' );

		update_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::INTERNAL_LINKS, $this->sanitize_links( $package->internal_links ?? array() ) );
		update_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::RELATED_ARTICLES, $this->sanitize_related( $package->related_articles ?? array() ) );
		update_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::SOURCES, $this->sanitize_sources( $package->sources ?? array() ) );
		update_post_meta(
			$post_id,
			RevIt_Publisher_Post_Meta_Keys::STRUCTURED_DATA,
			array(
				'article'     => ! empty( $package->structured_data->article ),
				'breadcrumbs' => ! empty( $package->structured_data->breadcrumbs ),
				'faq'         => ! empty( $package->structured_data->faq ),
			)
		);

		return true;
	}

	/**
	 * Sanitize string array meta.
	 *
	 * @param mixed $values Raw values.
	 * @return string[]
	 */
	private function sanitize_string_array( mixed $values ): array {
		if ( ! is_array( $values ) ) {
			return array();
		}

		return array_values(
			array_filter(
				array_map(
					static fn( $value ): string => sanitize_text_field( (string) $value ),
					$values
				)
			)
		);
	}

	/**
	 * Sanitize internal links array.
	 *
	 * @param mixed $links Raw links.
	 * @return array<int, array<string, mixed>>
	 */
	private function sanitize_links( mixed $links ): array {
		if ( ! is_array( $links ) ) {
			return array();
		}

		$sanitized = array();
		foreach ( $links as $link ) {
			if ( ! is_object( $link ) && ! is_array( $link ) ) {
				continue;
			}
			$item = (object) $link;
			$sanitized[] = array(
				'target_article_key' => sanitize_text_field( (string) ( $item->target_article_key ?? '' ) ),
				'preferred_anchor'   => sanitize_text_field( (string) ( $item->preferred_anchor ?? '' ) ),
				'relationship'       => sanitize_key( (string) ( $item->relationship ?? '' ) ),
				'required'             => ! empty( $item->required ),
			);
		}

		return $sanitized;
	}

	/**
	 * Sanitize related articles array.
	 *
	 * @param mixed $related Raw related articles.
	 * @return array<int, array<string, mixed>>
	 */
	private function sanitize_related( mixed $related ): array {
		if ( ! is_array( $related ) ) {
			return array();
		}

		$sanitized = array();
		foreach ( $related as $item ) {
			if ( ! is_object( $item ) && ! is_array( $item ) ) {
				continue;
			}
			$row = (object) $item;
			$sanitized[] = array(
				'article_key'  => sanitize_text_field( (string) ( $row->article_key ?? '' ) ),
				'relationship' => sanitize_key( (string) ( $row->relationship ?? '' ) ),
				'priority'     => (int) ( $row->priority ?? 0 ),
			);
		}

		return $sanitized;
	}

	/**
	 * Sanitize sources array.
	 *
	 * @param mixed $sources Raw sources.
	 * @return array<int, array<string, mixed>>
	 */
	private function sanitize_sources( mixed $sources ): array {
		if ( ! is_array( $sources ) ) {
			return array();
		}

		$sanitized = array();
		foreach ( $sources as $source ) {
			if ( ! is_object( $source ) && ! is_array( $source ) ) {
				continue;
			}
			$row = (object) $source;
			$url = esc_url_raw( (string) ( $row->url ?? '' ) );
			if ( '' === $url ) {
				continue;
			}
			$sanitized[] = array(
				'source_name' => sanitize_text_field( (string) ( $row->source_name ?? '' ) ),
				'title'       => sanitize_text_field( (string) ( $row->title ?? '' ) ),
				'url'         => $url,
				'source_type' => sanitize_key( (string) ( $row->source_type ?? '' ) ),
				'purpose'     => sanitize_textarea_field( (string) ( $row->purpose ?? '' ) ),
			);
		}

		return $sanitized;
	}
}
