<?php
/**
 * JSON-LD structured data output.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Outputs Article and BreadcrumbList JSON-LD for RevIt-managed posts.
 */
class RevIt_Publisher_Structured_Data_Output {

	/**
	 * Settings.
	 *
	 * @var RevIt_Publisher_Settings
	 */
	private RevIt_Publisher_Settings $settings;

	/**
	 * Resolver.
	 *
	 * @var RevIt_Publisher_Article_Resolver
	 */
	private RevIt_Publisher_Article_Resolver $resolver;

	/**
	 * Content graph.
	 *
	 * @var RevIt_Publisher_Content_Graph
	 */
	private RevIt_Publisher_Content_Graph $graph;

	/**
	 * Public breadcrumbs.
	 *
	 * @var RevIt_Publisher_Public_Breadcrumbs
	 */
	private RevIt_Publisher_Public_Breadcrumbs $breadcrumbs;

	/**
	 * Constructor.
	 */
	public function __construct(
		RevIt_Publisher_Settings $settings,
		RevIt_Publisher_Article_Resolver $resolver,
		RevIt_Publisher_Content_Graph $graph,
		RevIt_Publisher_Public_Breadcrumbs $breadcrumbs
	) {
		$this->settings    = $settings;
		$this->resolver    = $resolver;
		$this->graph       = $graph;
		$this->breadcrumbs = $breadcrumbs;
	}

	/**
	 * Register hooks.
	 */
	public function init(): void {
		add_action( 'wp_head', array( $this, 'output_json_ld' ), 20 );
	}

	/**
	 * Output JSON-LD scripts.
	 */
	public function output_json_ld(): void {
		if ( ! is_singular( 'post' ) || ! $this->settings->public_seo_output_enabled() ) {
			return;
		}

		$post_id = get_queried_object_id();
		if ( ! $this->resolver->is_managed( $post_id ) ) {
			return;
		}

		$structured = get_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::STRUCTURED_DATA, true );
		if ( ! is_array( $structured ) ) {
			$structured = array();
		}

		$graphs = array();

		if ( $this->settings->enable_article_schema() && ! empty( $structured['article'] ) ) {
			$article = $this->build_article_schema( $post_id );
			if ( null !== $article ) {
				$graphs[] = $article;
			}
		}

		if ( $this->settings->enable_breadcrumb_schema() && ! empty( $structured['breadcrumbs'] ) ) {
			$breadcrumb = $this->build_breadcrumb_schema( $post_id );
			if ( null !== $breadcrumb ) {
				$graphs[] = $breadcrumb;
			}
		}

		if ( empty( $graphs ) ) {
			return;
		}

		$payload = 1 === count( $graphs )
			? $graphs[0]
			: array( '@context' => 'https://schema.org', '@graph' => $graphs );

		if ( ! isset( $payload['@context'] ) ) {
			$payload['@context'] = 'https://schema.org';
		}

		echo '<script type="application/ld+json">' . wp_json_encode( $payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
	}

	/**
	 * Build Article schema object.
	 *
	 * @return array<string, mixed>|null
	 */
	public function build_article_schema( int $post_id ): ?array {
		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return null;
		}

		$schema = array(
			'@type'            => 'Article',
			'headline'         => (string) get_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::SEO_TITLE, true ) ?: get_the_title( $post_id ),
			'description'      => (string) get_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::META_DESCRIPTION, true ),
			'datePublished'    => get_the_date( 'c', $post_id ),
			'dateModified'     => get_the_modified_date( 'c', $post_id ),
			'mainEntityOfPage' => get_permalink( $post_id ),
		);

		$thumbnail_id = get_post_thumbnail_id( $post_id );
		if ( $thumbnail_id > 0 ) {
			$image_url = wp_get_attachment_image_url( $thumbnail_id, 'full' );
			if ( is_string( $image_url ) && '' !== $image_url ) {
				$schema['image'] = array(
					'@type' => 'ImageObject',
					'url'   => $image_url,
				);
			}
		}

		$author_id = (int) $post->post_author;
		if ( $author_id > 0 ) {
			$schema['author'] = array(
				'@type' => 'Person',
				'name'  => get_the_author_meta( 'display_name', $author_id ),
			);
		}

		$org_name = $this->settings->org_name();
		if ( '' !== $org_name ) {
			$publisher = array(
				'@type' => 'Organization',
				'name'  => $org_name,
			);
			$logo = $this->settings->org_logo_url();
			if ( '' !== $logo ) {
				$publisher['logo'] = array(
					'@type' => 'ImageObject',
					'url'   => $logo,
				);
			}
			$schema['publisher'] = $publisher;
		}

		return $schema;
	}

	/**
	 * Build BreadcrumbList with valid URLs only.
	 *
	 * @return array<string, mixed>|null
	 */
	public function build_breadcrumb_schema( int $post_id ): ?array {
		unset( $post_id );
		$items = $this->breadcrumbs->get_json_ld_items();
		if ( count( $items ) < 2 ) {
			return null;
		}

		return array(
			'@type'           => 'BreadcrumbList',
			'itemListElement' => $items,
		);
	}
}
