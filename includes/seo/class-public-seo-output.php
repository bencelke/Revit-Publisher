<?php
/**
 * Public SEO metadata output for RevIt-managed posts.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Outputs meta description, canonical, robots, and document title.
 */
class RevIt_Publisher_Public_SEO_Output {

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
	 * Whether output already emitted (avoid duplicates).
	 *
	 * @var bool
	 */
	private bool $meta_description_output = false;

	/**
	 * Constructor.
	 */
	public function __construct( RevIt_Publisher_Settings $settings, RevIt_Publisher_Article_Resolver $resolver ) {
		$this->settings = $settings;
		$this->resolver = $resolver;
	}

	/**
	 * Register frontend hooks.
	 */
	public function init(): void {
		add_filter( 'document_title_parts', array( $this, 'filter_document_title' ), 20 );
		add_action( 'wp_head', array( $this, 'output_meta_description' ), 1 );
		add_action( 'wp_head', array( $this, 'output_canonical' ), 2 );
		add_action( 'wp_head', array( $this, 'output_robots' ), 3 );
	}

	/**
	 * Filter document title for RevIt-managed singular posts.
	 *
	 * @param array<string, string> $parts Title parts.
	 * @return array<string, string>
	 */
	public function filter_document_title( array $parts ): array {
		if ( ! is_singular( 'post' ) || ! $this->settings->public_seo_output_enabled() ) {
			return $parts;
		}

		$post_id = get_queried_object_id();
		if ( ! $this->resolver->is_managed( $post_id ) ) {
			return $parts;
		}

		$seo_title = (string) get_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::SEO_TITLE, true );
		if ( '' !== $seo_title ) {
			$parts['title'] = $seo_title;
		}

		return $parts;
	}

	/**
	 * Output meta description tag.
	 */
	public function output_meta_description(): void {
		if ( ! is_singular( 'post' ) || ! $this->settings->enable_meta_description() || $this->meta_description_output ) {
			return;
		}

		$post_id = get_queried_object_id();
		if ( ! $this->resolver->is_managed( $post_id ) ) {
			return;
		}

		$description = (string) get_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::META_DESCRIPTION, true );
		if ( '' === $description ) {
			return;
		}

		printf(
			'<meta name="description" content="%s" />' . "\n",
			esc_attr( $description )
		);
		$this->meta_description_output = true;
	}

	/**
	 * Output canonical link tag.
	 */
	public function output_canonical(): void {
		if ( ! is_singular( 'post' ) || ! $this->settings->enable_canonical() ) {
			return;
		}

		$post_id = get_queried_object_id();
		if ( ! $this->resolver->is_managed( $post_id ) ) {
			return;
		}

		$canonical = (string) get_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::CANONICAL, true );
		$url       = 'auto' === $canonical ? get_permalink( $post_id ) : esc_url_raw( $canonical );

		if ( ! is_string( $url ) || '' === $url ) {
			return;
		}

		printf( '<link rel="canonical" href="%s" />' . "\n", esc_url( $url ) );
	}

	/**
	 * Output robots meta tag.
	 */
	public function output_robots(): void {
		if ( ! is_singular( 'post' ) || ! $this->settings->enable_robots() ) {
			return;
		}

		$post_id = get_queried_object_id();
		if ( ! $this->resolver->is_managed( $post_id ) ) {
			return;
		}

		$index  = '1' === (string) get_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::INDEX, true );
		$follow = '1' === (string) get_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::FOLLOW, true );

		$directives = array();
		$directives[] = $index ? 'index' : 'noindex';
		$directives[] = $follow ? 'follow' : 'nofollow';

		printf(
			'<meta name="robots" content="%s" />' . "\n",
			esc_attr( implode( ', ', $directives ) )
		);
	}
}
