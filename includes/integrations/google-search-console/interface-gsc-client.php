<?php
/**
 * Google Search Console API client interface.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface RevIt_Publisher_GSC_Client_Interface {

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function list_sites(): array;

	/**
	 * @param array<string, mixed> $params
	 * @return array<int, array<string, mixed>>
	 */
	public function search_analytics_query( string $property, array $params ): array;

	/**
	 * @return array<string, mixed>
	 */
	public function inspect_url( string $property, string $url ): array;

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function list_sitemaps( string $property ): array;

	/**
	 * @return array<string, mixed>
	 */
	public function submit_sitemap( string $property, string $sitemap_url ): array;
}
