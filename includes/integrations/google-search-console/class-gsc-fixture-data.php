<?php
/**
 * Fixture Search Console dataset for BMW X3 ecosystem tests.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RevIt_Publisher_GSC_Fixture_Data {

	public const PROPERTY = 'sc-domain:revit24.com';

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public static function sites(): array {
		return array(
			array(
				'site_url' => self::PROPERTY,
				'permission_level' => 'siteOwner',
			),
		);
	}

	/**
	 * Page metrics keyed by slug fragment for URL matching.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function page_profiles(): array {
		return array(
			'cooling-guide' => array(
				'clicks' => 542, 'impressions' => 18400, 'ctr' => 0.029, 'position' => 8.4,
				'prev_clicks' => 480, 'prev_impressions' => 16200, 'prev_position' => 9.1,
			),
			'coolant-loss' => array(
				'clicks' => 190, 'impressions' => 14800, 'ctr' => 0.0128, 'position' => 7.2,
				'prev_clicks' => 175, 'prev_impressions' => 13200, 'prev_position' => 7.8,
			),
			'water-pump' => array(
				'clicks' => 117, 'impressions' => 6842, 'ctr' => 0.0171, 'position' => 13.4,
				'prev_clicks' => 108, 'prev_impressions' => 6200, 'prev_position' => 14.2,
			),
			'thermostat' => array(
				'clicks' => 84, 'impressions' => 3200, 'ctr' => 0.026, 'position' => 10.1,
				'prev_clicks' => 52, 'prev_impressions' => 2100, 'prev_position' => 12.5,
			),
			'maintenance-guide' => array(
				'clicks' => 62, 'impressions' => 4100, 'ctr' => 0.015, 'position' => 15.2,
				'prev_clicks' => 98, 'prev_impressions' => 5200, 'prev_position' => 12.8,
			),
			'intake-guide' => array(
				'clicks' => 12, 'impressions' => 890, 'ctr' => 0.013, 'position' => 22.4,
				'prev_clicks' => 10, 'prev_impressions' => 820, 'prev_position' => 23.1,
			),
			'zero-visibility' => array(
				'clicks' => 0, 'impressions' => 0, 'ctr' => 0, 'position' => 0,
				'prev_clicks' => 0, 'prev_impressions' => 0, 'prev_position' => 0,
			),
			'vehicle-hub' => array(
				'clicks' => 240, 'impressions' => 8420, 'ctr' => 0.0285, 'position' => 9.2,
				'prev_clicks' => 210, 'prev_impressions' => 7800, 'prev_position' => 9.8,
			),
		);
	}

	/**
	 * @return array<string, array<int, array<string, mixed>>>
	 */
	public static function query_profiles(): array {
		return array(
			'coolant-loss' => array(
				array( 'query' => 'bmw x3 m40i coolant loss', 'clicks' => 190, 'impressions' => 4800, 'ctr' => 0.04, 'position' => 6.3 ),
				array( 'query' => 'x3 m40i low coolant warning', 'clicks' => 74, 'impressions' => 2100, 'ctr' => 0.035, 'position' => 8.7 ),
				array( 'query' => 'x3 m40i coolant smell', 'clicks' => 18, 'impressions' => 1420, 'ctr' => 0.012, 'position' => 11.2 ),
			),
			'water-pump' => array(
				array( 'query' => 'bmw x3 m40i water pump failure', 'clicks' => 65, 'impressions' => 3200, 'ctr' => 0.02, 'position' => 12.8 ),
				array( 'query' => 'x3 m40i water pump replacement', 'clicks' => 42, 'impressions' => 2100, 'ctr' => 0.02, 'position' => 14.1 ),
			),
			'vehicle-hub' => array(
				array( 'query' => 'bmw x3 m40i', 'clicks' => 120, 'impressions' => 4200, 'ctr' => 0.028, 'position' => 8.5 ),
			),
		);
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public static function sitemaps(): array {
		return array(
			array(
				'path'         => '/wp-sitemap.xml',
				'lastSubmitted'=> gmdate( 'c', time() - DAY_IN_SECONDS ),
				'lastDownloaded'=> gmdate( 'c', time() - HOUR_IN_SECONDS ),
				'isPending'    => false,
				'isSitemapsIndex' => true,
				'errors'       => 0,
				'warnings'     => 0,
			),
		);
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	public static function inspection_profiles(): array {
		return array(
			'indexed' => array(
				'indexed' => true,
				'lastCrawlTime' => gmdate( 'c', time() - ( 2 * DAY_IN_SECONDS ) ),
				'googleCanonical' => null,
				'userCanonical' => null,
				'verdict' => 'PASS',
			),
			'not_indexed' => array(
				'indexed' => false,
				'lastCrawlTime' => gmdate( 'c', time() - ( 5 * DAY_IN_SECONDS ) ),
				'googleCanonical' => null,
				'userCanonical' => null,
				'verdict' => 'NEUTRAL',
				'coverageState' => 'Discovered - currently not indexed',
			),
			'canonical_mismatch' => array(
				'indexed' => true,
				'lastCrawlTime' => gmdate( 'c', time() - DAY_IN_SECONDS ),
				'googleCanonical' => 'https://revit24.com/other-canonical/',
				'userCanonical' => null,
				'verdict' => 'NEUTRAL',
			),
		);
	}
}
