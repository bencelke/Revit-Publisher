<?php
/**
 * Real Google Search Console API client.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RevIt_Publisher_GSC_Google_Client implements RevIt_Publisher_GSC_Client_Interface {

	public function list_sites(): array {
		$service = $this->service();
		$sites   = $service->sites->listSites();
		$out     = array();
		foreach ( (array) ( $sites->getSiteEntry() ?? array() ) as $entry ) {
			$out[] = array(
				'site_url'         => (string) $entry->getSiteUrl(),
				'permission_level' => (string) $entry->getPermissionLevel(),
			);
		}
		return $out;
	}

	public function search_analytics_query( string $property, array $params ): array {
		$service = $this->service();
		$request = new Google\Service\SearchConsole\SearchAnalyticsQueryRequest();
		$request->setStartDate( (string) ( $params['start_date'] ?? '' ) );
		$request->setEndDate( (string) ( $params['end_date'] ?? '' ) );
		$request->setDimensions( (array) ( $params['dimensions'] ?? array( 'page' ) ) );
		$request->setRowLimit( (int) ( $params['row_limit'] ?? 1000 ) );

		$response = $service->searchanalytics->query( $property, $request );
		$rows     = array();
		foreach ( (array) ( $response->getRows() ?? array() ) as $row ) {
			$rows[] = array(
				'keys'        => (array) $row->getKeys(),
				'clicks'      => (int) $row->getClicks(),
				'impressions' => (int) $row->getImpressions(),
				'ctr'         => (float) $row->getCtr(),
				'position'    => (float) $row->getPosition(),
			);
		}
		return $rows;
	}

	public function inspect_url( string $property, string $url ): array {
		$service = $this->service();
		$request = new Google\Service\SearchConsole\InspectUrlIndexRequest();
		$request->setInspectionUrl( $url );
		$request->setSiteUrl( $property );
		$result  = $service->urlInspection_index->inspect( $request );
		$index   = $result->getInspectionResult()?->getIndexStatusResult();
		return array(
			'indexed'         => 'PASS' === (string) ( $index?->getVerdict() ?? '' ),
			'lastCrawlTime'   => (string) ( $index?->getLastCrawlTime() ?? '' ),
			'googleCanonical' => (string) ( $index?->getGoogleCanonical() ?? '' ),
			'userCanonical'   => (string) ( $index?->getUserCanonical() ?? '' ),
			'verdict'         => (string) ( $index?->getVerdict() ?? '' ),
			'coverageState'   => (string) ( $index?->getCoverageState() ?? '' ),
		);
	}

	public function list_sitemaps( string $property ): array {
		$service  = $this->service();
		$response = $service->sitemaps->listSitemaps( $property );
		$out      = array();
		foreach ( (array) ( $response->getSitemap() ?? array() ) as $sitemap ) {
			$out[] = array(
				'path'            => (string) $sitemap->getPath(),
				'lastSubmitted'   => (string) $sitemap->getLastSubmitted(),
				'lastDownloaded'  => (string) $sitemap->getLastDownloaded(),
				'isPending'       => (bool) $sitemap->getIsPending(),
				'isSitemapsIndex' => (bool) $sitemap->getIsSitemapsIndex(),
				'errors'          => (int) ( $sitemap->getErrors() ?? 0 ),
				'warnings'        => (int) ( $sitemap->getWarnings() ?? 0 ),
			);
		}
		return $out;
	}

	public function submit_sitemap( string $property, string $sitemap_url ): array {
		$service = $this->service();
		$service->sitemaps->submit( $property, $sitemap_url );
		return array( 'success' => true, 'sitemap_url' => $sitemap_url );
	}

	private function service(): Google\Service\SearchConsole {
		if ( ! class_exists( Google\Client::class ) ) {
			throw new RuntimeException( 'Google API client is not installed.' );
		}
		$settings = RevIt_Publisher_Services::settings();
		$client   = new Google\Client();
		$client->setClientId( $settings->gsc_client_id() );
		$client->setClientSecret( $settings->gsc_client_secret() );
		$client->setAccessType( 'offline' );
		$client->setScopes(
			$settings->gsc_sitemap_write_enabled()
				? array( Google\Service\SearchConsole::WEBMASTERS )
				: array( Google\Service\SearchConsole::WEBMASTERS_READONLY )
		);

		$tokens = RevIt_Publisher_Services::gsc_tokens()->get();
		if ( ! empty( $tokens['access_token'] ) ) {
			$client->setAccessToken(
				array(
					'access_token'  => (string) $tokens['access_token'],
					'refresh_token' => (string) ( $tokens['refresh_token'] ?? '' ),
					'expires_in'    => max( 0, (int) ( $tokens['expires_at'] ?? 0 ) - time() ),
				)
			);
		}
		if ( $client->isAccessTokenExpired() && ! empty( $tokens['refresh_token'] ) ) {
			$new = $client->fetchAccessTokenWithRefreshToken( (string) $tokens['refresh_token'] );
			if ( is_array( $new ) && ! empty( $new['access_token'] ) ) {
				RevIt_Publisher_Services::gsc_tokens()->save(
					array_merge(
						$tokens,
						array(
							'access_token' => (string) $new['access_token'],
							'expires_at'   => time() + (int) ( $new['expires_in'] ?? 3600 ),
						)
					)
				);
			}
		}
		return new Google\Service\SearchConsole( $client );
	}
}
