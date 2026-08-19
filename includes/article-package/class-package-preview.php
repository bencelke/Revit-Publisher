<?php
/**
 * Article package import preview builder.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds preview payloads without creating WordPress content.
 */
class RevIt_Publisher_Package_Preview {

	/**
	 * Registry service.
	 *
	 * @var RevIt_Publisher_Article_Registry
	 */
	private RevIt_Publisher_Article_Registry $registry;

	/**
	 * Vehicle service.
	 *
	 * @var RevIt_Publisher_Vehicle_Taxonomy_Service
	 */
	private RevIt_Publisher_Vehicle_Taxonomy_Service $vehicle_service;

	/**
	 * Constructor.
	 */
	public function __construct(
		RevIt_Publisher_Article_Registry $registry,
		RevIt_Publisher_Vehicle_Taxonomy_Service $vehicle_service
	) {
		$this->registry        = $registry;
		$this->vehicle_service = $vehicle_service;
	}

	/**
	 * Build preview response from validated package object.
	 *
	 * @param object $package Validated package.
	 * @return array<string, mixed>
	 */
	public function build( object $package ): array {
		$article_key = (string) ( $package->article->article_key ?? '' );
		$existing_id = $this->registry->find_post_id_by_article_key( $article_key );

		return array(
			'valid'            => true,
			'article'          => array(
				'title'        => (string) ( $package->article->title ?? '' ),
				'article_key'  => $article_key,
				'article_type' => (string) ( $package->article->article_type ?? '' ),
			),
			'vehicle'          => $this->vehicle_service->format_vehicle_label( $package->vehicle ),
			'cluster'          => (string) ( $package->cluster->name ?? '' ),
			'seo'              => array(
				'primary_topic' => (string) ( $package->seo->primary_topic ?? '' ),
				'seo_title'     => (string) ( $package->seo->seo_title ?? '' ),
			),
			'relationships'    => array(
				'internal_links'     => is_array( $package->internal_links ?? null ) ? count( $package->internal_links ) : 0,
				'related_articles'   => is_array( $package->related_articles ?? null ) ? count( $package->related_articles ) : 0,
				'pillar_article_key' => $package->cluster->pillar_article_key ?? null,
			),
			'publishing'       => array(
				'status' => (string) ( $package->publishing->status ?? 'draft' ),
			),
			'existing_article' => null !== $existing_id,
			'existing_post_id' => $existing_id,
		);
	}
}
