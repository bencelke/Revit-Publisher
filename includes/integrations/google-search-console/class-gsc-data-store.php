<?php
/**
 * Local Search Console metrics storage.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RevIt_Publisher_GSC_Data_Store {

	private RevIt_Publisher_GSC_Page_Mapper $mapper;

	public function __construct( RevIt_Publisher_GSC_Page_Mapper $mapper ) {
		$this->mapper = $mapper;
	}

	/**
	 * Replace metrics for a sync window.
	 *
	 * @param array<int, array<string, mixed>> $rows
	 */
	public function store_page_rows( string $property, string $window_key, string $metric_date, array $rows ): int {
		global $wpdb;
		$table = RevIt_Publisher_GSC_Schema::page_table();
		$wpdb->delete( $table, array( 'property' => $property, 'window_key' => $window_key ), array( '%s', '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		$count = 0;
		$now   = gmdate( 'Y-m-d H:i:s' );
		foreach ( $rows as $row ) {
			$page_url = $this->mapper->normalize_url( (string) ( $row['page_url'] ?? '' ) );
			if ( '' === $page_url ) {
				continue;
			}
			$map = $this->mapper->map_url( $page_url );
			$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$table,
				array(
					'property'    => $property,
					'page_url'    => $page_url,
					'post_id'     => (int) ( $map['post_id'] ?? 0 ),
					'hub_id'      => (int) ( $map['hub_id'] ?? 0 ),
					'metric_date' => $metric_date,
					'window_key'  => $window_key,
					'clicks'      => (int) ( $row['clicks'] ?? 0 ),
					'impressions' => (int) ( $row['impressions'] ?? 0 ),
					'ctr'         => (float) ( $row['ctr'] ?? 0 ),
					'position'    => (float) ( $row['position'] ?? 0 ),
					'synced_at'   => $now,
				),
				array( '%s', '%s', '%d', '%d', '%s', '%s', '%d', '%d', '%f', '%f', '%s' )
			);
			++$count;
		}
		return $count;
	}

	/**
	 * @param array<int, array<string, mixed>> $rows
	 */
	public function store_query_rows( string $property, string $window_key, string $metric_date, array $rows ): int {
		global $wpdb;
		$table = RevIt_Publisher_GSC_Schema::query_table();
		$wpdb->delete( $table, array( 'property' => $property, 'window_key' => $window_key ), array( '%s', '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		$count = 0;
		$now   = gmdate( 'Y-m-d H:i:s' );
		foreach ( $rows as $row ) {
			$page_url = $this->mapper->normalize_url( (string) ( $row['page_url'] ?? '' ) );
			$query    = sanitize_text_field( (string) ( $row['query'] ?? '' ) );
			if ( '' === $page_url || '' === $query ) {
				continue;
			}
			$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$table,
				array(
					'property'    => $property,
					'page_url'    => $page_url,
					'query_text'  => $query,
					'metric_date' => $metric_date,
					'window_key'  => $window_key,
					'clicks'      => (int) ( $row['clicks'] ?? 0 ),
					'impressions' => (int) ( $row['impressions'] ?? 0 ),
					'ctr'         => (float) ( $row['ctr'] ?? 0 ),
					'position'    => (float) ( $row['position'] ?? 0 ),
					'synced_at'   => $now,
				),
				array( '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%f', '%f', '%s' )
			);
			++$count;
		}
		return $count;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function get_post_metrics( int $post_id, string $window_key = '28d' ): ?array {
		global $wpdb;
		$table = RevIt_Publisher_GSC_Schema::page_table();
		$row   = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT SUM(clicks) AS clicks, SUM(impressions) AS impressions,
					AVG(ctr) AS ctr, AVG(position) AS position
				FROM {$table} WHERE post_id = %d AND window_key = %s",
				$post_id,
				$window_key
			),
			ARRAY_A
		);
		return is_array( $row ) ? $row : null;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function get_top_pages( string $window_key = '28d', int $limit = 20, array $filters = array() ): array {
		global $wpdb;
		$table  = RevIt_Publisher_GSC_Schema::page_table();
		$where  = $wpdb->prepare( 'window_key = %s AND post_id > 0', $window_key );
		if ( ! empty( $filters['vehicle'] ) ) {
			$posts = $this->post_ids_for_vehicle( (string) $filters['vehicle'] );
			if ( empty( $posts ) ) {
				return array();
			}
			$where .= ' AND post_id IN (' . implode( ',', array_map( 'intval', $posts ) ) . ')';
		}
		if ( ! empty( $filters['article_type'] ) ) {
			$where .= $wpdb->prepare(
				' AND post_id IN (SELECT post_id FROM ' . $wpdb->postmeta . ' pm
					INNER JOIN ' . $wpdb->term_relationships . ' tr ON tr.object_id = pm.post_id
					INNER JOIN ' . $wpdb->term_taxonomy . ' tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
					INNER JOIN ' . $wpdb->terms . ' t ON t.term_id = tt.term_id
					WHERE tt.taxonomy = %s AND t.slug = %s)',
				RevIt_Publisher_Taxonomies::ARTICLE_TYPE,
				sanitize_key( (string) $filters['article_type'] )
			);
		}
		$sql = "SELECT post_id, page_url, SUM(clicks) AS clicks, SUM(impressions) AS impressions,
			AVG(ctr) AS ctr, AVG(position) AS position
			FROM {$table} WHERE {$where}
			GROUP BY post_id, page_url
			ORDER BY clicks DESC LIMIT " . (int) $limit;
		$rows = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function get_post_queries( int $post_id, string $window_key = '28d', int $limit = 10 ): array {
		global $wpdb;
		$permalink = get_permalink( $post_id );
		if ( ! is_string( $permalink ) ) {
			return array();
		}
		$page_url = $this->mapper->normalize_url( $permalink );
		$table    = RevIt_Publisher_GSC_Schema::query_table();
		$rows     = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT query_text AS query, clicks, impressions, ctr, position
				FROM {$table} WHERE page_url = %s AND window_key = %s
				ORDER BY impressions DESC LIMIT %d",
				$page_url,
				$window_key,
				$limit
			),
			ARRAY_A
		);
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * @return array<string, mixed>
	 */
	public function get_summary( string $window_key = '28d' ): array {
		global $wpdb;
		$table = RevIt_Publisher_GSC_Schema::page_table();
		$row   = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT SUM(clicks) AS clicks, SUM(impressions) AS impressions,
					AVG(ctr) AS ctr, AVG(position) AS position
				FROM {$table} WHERE window_key = %s AND post_id > 0",
				$window_key
			),
			ARRAY_A
		);
		return is_array( $row ) ? $row : array(
			'clicks'      => 0,
			'impressions' => 0,
			'ctr'         => 0,
			'position'    => 0,
		);
	}

	/**
	 * @return int[]
	 */
	private function post_ids_for_vehicle( string $vehicle_label ): array {
		$ids = array();
		foreach ( RevIt_Publisher_Services::registry()->get_managed_post_ids() as $post_id ) {
			if ( RevIt_Publisher_Services::graph()->get_vehicle_label( (int) $post_id ) === $vehicle_label ) {
				$ids[] = (int) $post_id;
			}
		}
		return $ids;
	}

	/**
	 * @param array<string, mixed> $result
	 */
	public function store_inspection( string $property, string $page_url, int $post_id, array $result ): void {
		global $wpdb;
		$table = RevIt_Publisher_GSC_Schema::inspection_table();
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$table,
			array(
				'property'     => $property,
				'page_url'     => $this->mapper->normalize_url( $page_url ),
				'post_id'      => $post_id,
				'indexed'      => ! empty( $result['indexed'] ) ? 1 : 0,
				'result_json'  => wp_json_encode( $result ),
				'inspected_at' => gmdate( 'Y-m-d H:i:s' ),
			),
			array( '%s', '%s', '%d', '%d', '%s', '%s' )
		);
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function get_latest_inspection( int $post_id ): ?array {
		global $wpdb;
		$table = RevIt_Publisher_GSC_Schema::inspection_table();
		$row   = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE post_id = %d ORDER BY inspected_at DESC LIMIT 1",
				$post_id
			),
			ARRAY_A
		);
		if ( ! is_array( $row ) ) {
			return null;
		}
		$row['result'] = json_decode( (string) ( $row['result_json'] ?? '' ), true );
		return $row;
	}
}
