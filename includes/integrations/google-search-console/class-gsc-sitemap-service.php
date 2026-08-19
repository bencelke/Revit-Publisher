<?php
/**
 * Search Console sitemap integration.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RevIt_Publisher_GSC_Sitemap_Service {

	private RevIt_Publisher_GSC_Client_Interface $client;
	private RevIt_Publisher_Settings $settings;

	public function __construct( RevIt_Publisher_GSC_Client_Interface $client, RevIt_Publisher_Settings $settings ) {
		$this->client   = $client;
		$this->settings = $settings;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function list_sitemaps(): array {
		$property = $this->settings->gsc_property();
		if ( '' === $property ) {
			return array();
		}
		try {
			return $this->client->list_sitemaps( $property );
		} catch ( Throwable $e ) {
			return array();
		}
	}

	/**
	 * @return array<string, mixed>|WP_Error
	 */
	public function submit_wordpress_sitemap(): array|WP_Error {
		if ( ! $this->settings->gsc_sitemap_write_enabled() ) {
			return new WP_Error( 'revit_gsc_readonly', __( 'Sitemap submission requires write scope.', 'revit-publisher' ) );
		}
		$property = $this->settings->gsc_property();
		$url      = home_url( '/wp-sitemap.xml' );
		if ( '' === $property ) {
			return new WP_Error( 'revit_gsc_no_property', __( 'No property selected.', 'revit-publisher' ) );
		}
		try {
			$result = $this->client->submit_sitemap( $property, $url );
			update_option( 'revit_gsc_sitemap_submitted_at', gmdate( 'c' ) );
			return $result;
		} catch ( Throwable $e ) {
			return new WP_Error( 'revit_gsc_submit_failed', sanitize_text_field( $e->getMessage() ) );
		}
	}

	public function is_wordpress_sitemap_submitted(): bool {
		foreach ( $this->list_sitemaps() as $sitemap ) {
			if ( str_contains( (string) ( $sitemap['path'] ?? '' ), 'wp-sitemap' ) ) {
				return true;
			}
		}
		return false;
	}
}
