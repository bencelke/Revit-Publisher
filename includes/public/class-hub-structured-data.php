<?php
/**
 * JSON-LD structured data for vehicle hub pages.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Outputs WebPage/CollectionPage and BreadcrumbList for public vehicle hubs.
 */
class RevIt_Publisher_Hub_Structured_Data {

	private RevIt_Publisher_Settings $settings;
	private RevIt_Publisher_Public_Breadcrumbs $breadcrumbs;

	public function __construct(
		RevIt_Publisher_Settings $settings,
		RevIt_Publisher_Public_Breadcrumbs $breadcrumbs
	) {
		$this->settings    = $settings;
		$this->breadcrumbs = $breadcrumbs;
	}

	public function init(): void {
		add_action( 'wp_head', array( $this, 'output_json_ld' ), 20 );
	}

	public function output_json_ld(): void {
		if ( ! $this->settings->public_seo_output_enabled() ) {
			return;
		}

		$graphs = array();
		$page   = $this->build_page_schema();
		if ( null !== $page ) {
			$graphs[] = $page;
		}

		if ( $this->settings->enable_breadcrumb_schema() ) {
			$breadcrumb = $this->build_breadcrumb_schema();
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
	 * @return array<string, mixed>|null
	 */
	public function build_page_schema(): ?array {
		if ( is_singular( RevIt_Publisher_Vehicle_Hub_Post_Type::POST_TYPE ) ) {
			$hub_id = get_queried_object_id();
			$url    = get_permalink( $hub_id );
			if ( ! is_string( $url ) || '' === $url ) {
				return null;
			}
			$description = (string) get_post_meta( $hub_id, RevIt_Publisher_Vehicle_Hub_Meta_Keys::INTRO, true );
			return array(
				'@type'       => 'WebPage',
				'name'        => get_the_title( $hub_id ),
				'description' => $description,
				'url'         => $url,
			);
		}

		if ( is_post_type_archive( RevIt_Publisher_Vehicle_Hub_Post_Type::POST_TYPE ) || '' !== (string) get_query_var( 'revit_manufacturer_hub' ) ) {
			$url = is_post_type_archive( RevIt_Publisher_Vehicle_Hub_Post_Type::POST_TYPE )
				? $this->breadcrumbs->vehicles_archive_url()
				: $this->breadcrumbs->manufacturer_url( (string) get_query_var( 'revit_manufacturer_hub' ) );
			return array(
				'@type' => 'CollectionPage',
				'name'  => wp_get_document_title(),
				'url'   => $url,
			);
		}

		return null;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function build_breadcrumb_schema(): ?array {
		if ( ! RevIt_Publisher_Public_Template_Loader::is_revit_public_page() ) {
			return null;
		}
		if ( is_singular( 'post' ) ) {
			return null;
		}

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
