<?php
/**
 * Search Analytics API wrapper.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RevIt_Publisher_GSC_Search_Analytics_Service {

	private RevIt_Publisher_GSC_Client_Interface $client;

	public function __construct( RevIt_Publisher_GSC_Client_Interface $client ) {
		$this->client = $client;
	}

	/**
	 * @param array<string, mixed> $params
	 * @return array<int, array<string, mixed>>
	 */
	public function query( string $property, array $params ): array {
		return $this->client->search_analytics_query( $property, $params );
	}
}
