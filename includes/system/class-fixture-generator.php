<?php
/**
 * Generate large fixture datasets for performance testing.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RevIt_Publisher_Fixture_Generator {

	/**
	 * @return array<string, mixed>
	 */
	public function generate_metadata_fixture( int $count = 5000, int $vehicles = 100 ): array {
		$started = microtime( true );
		$created = 0;
		$manufacturers = array( 'BMW', 'Audi', 'Mercedes', 'Volkswagen', 'Porsche' );
		$models = array( 'X3', 'X5', '3 Series', 'A4', 'Q5', 'C-Class', 'Golf' );

		for ( $i = 0; $i < $count; ++$i ) {
			$key = 'perf-fixture-' . $i;
			if ( RevIt_Publisher_Services::registry()->find_post_id_by_article_key( $key ) ) {
				continue;
			}
			$post_id = wp_insert_post(
				array(
					'post_type'   => 'post',
					'post_status' => 'publish',
					'post_title'  => 'Performance Fixture ' . $i,
					'post_name'   => 'perf-fixture-' . $i,
					'post_content'=> 'Fixture content stub.',
				),
				true
			);
			if ( is_wp_error( $post_id ) ) {
				continue;
			}
			update_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::MANAGED, '1' );
			update_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::ARTICLE_KEY, $key );
			update_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::INDEX, '1' );
			update_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::VEHICLE_MANUFACTURER, $manufacturers[ $i % count( $manufacturers ) ] );
			update_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::VEHICLE_MODEL, $models[ $i % count( $models ) ] );
			update_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::CLUSTER_KEY, 'cluster-' . ( $i % 20 ) );
			++$created;
		}

		$duration = round( microtime( true ) - $started, 2 );
		RevIt_Publisher_Services::profiler()->record( 'fixture_generate', $duration, $created );

		return array(
			'success'  => true,
			'created'  => $created,
			'target'   => $count,
			'vehicles' => $vehicles,
			'duration' => $duration,
		);
	}
}
