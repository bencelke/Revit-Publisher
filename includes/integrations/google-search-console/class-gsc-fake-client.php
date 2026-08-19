<?php
/**
 * Fake Search Console client for tests and Docker acceptance.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RevIt_Publisher_GSC_Fake_Client implements RevIt_Publisher_GSC_Client_Interface {

	private bool $fail_next = false;

	public function set_fail_next( bool $fail ): void {
		$this->fail_next = $fail;
	}

	public function list_sites(): array {
		$this->guard();
		return RevIt_Publisher_GSC_Fixture_Data::sites();
	}

	public function search_analytics_query( string $property, array $params ): array {
		unset( $property );
		$this->guard();
		$use_prev   = ! empty( $params['previous_period'] );
		$dimensions = (array) ( $params['dimensions'] ?? array( 'page' ) );
		$rows       = array();

		if ( in_array( 'query', $dimensions, true ) ) {
			foreach ( RevIt_Publisher_GSC_Fixture_Data::query_profiles() as $slug => $queries ) {
				foreach ( $queries as $q ) {
					$rows[] = array(
						'keys'        => array( self::url_for_slug( $slug ), (string) $q['query'] ),
						'clicks'      => (int) $q['clicks'],
						'impressions' => (int) $q['impressions'],
						'ctr'         => (float) $q['ctr'],
						'position'    => (float) $q['position'],
					);
				}
			}
			return $rows;
		}

		foreach ( RevIt_Publisher_GSC_Fixture_Data::page_profiles() as $slug => $profile ) {
			$rows[] = array(
				'keys'        => array( self::url_for_slug( $slug ) ),
				'clicks'      => (int) ( $use_prev ? $profile['prev_clicks'] : $profile['clicks'] ),
				'impressions' => (int) ( $use_prev ? $profile['prev_impressions'] : $profile['impressions'] ),
				'ctr'         => (float) ( $use_prev
					? ( $profile['prev_impressions'] > 0 ? $profile['prev_clicks'] / $profile['prev_impressions'] : 0 )
					: $profile['ctr'] ),
				'position'    => (float) ( $use_prev ? $profile['prev_position'] : $profile['position'] ),
			);
		}
		return $rows;
	}

	public function inspect_url( string $property, string $url ): array {
		unset( $property );
		$this->guard();
		if ( str_contains( $url, 'zero-visibility' ) || str_contains( $url, 'not-indexed' ) ) {
			return RevIt_Publisher_GSC_Fixture_Data::inspection_profiles()['not_indexed'];
		}
		if ( str_contains( $url, 'canonical-mismatch' ) ) {
			$profile = RevIt_Publisher_GSC_Fixture_Data::inspection_profiles()['canonical_mismatch'];
			$profile['userCanonical'] = $url;
			return $profile;
		}
		$profile = RevIt_Publisher_GSC_Fixture_Data::inspection_profiles()['indexed'];
		$profile['userCanonical'] = $url;
		$profile['googleCanonical'] = $url;
		return $profile;
	}

	public function list_sitemaps( string $property ): array {
		unset( $property );
		$this->guard();
		return RevIt_Publisher_GSC_Fixture_Data::sitemaps();
	}

	public function submit_sitemap( string $property, string $sitemap_url ): array {
		unset( $property );
		$this->guard();
		return array(
			'success'     => true,
			'sitemap_url' => $sitemap_url,
		);
	}

	public static function url_for_slug( string $slug ): string {
		$home = untrailingslashit( home_url() );
		return match ( $slug ) {
			'vehicle-hub' => $home . '/vehicles/bmw-x3-g01-m40i/',
			'cooling-guide' => $home . '/bmw-x3-m40i-cooling-system-guide/',
			'coolant-loss' => $home . '/bmw-x3-m40i-coolant-loss/',
			'water-pump' => $home . '/bmw-x3-m40i-water-pump-failure/',
			'thermostat' => $home . '/bmw-x3-m40i-thermostat/',
			'maintenance-guide' => $home . '/bmw-x3-m40i-maintenance/',
			'intake-guide' => $home . '/bmw-x3-m40i-intake/',
			'zero-visibility' => $home . '/bmw-x3-m40i-zero-visibility/',
			default => $home . '/' . $slug . '/',
		};
	}

	private function guard(): void {
		if ( $this->fail_next ) {
			$this->fail_next = false;
			throw new RuntimeException( 'Fixture GSC API failure.' );
		}
	}
}
