<?php
/**
 * Vehicle taxonomy service integration tests.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

/**
 * Tests for RevIt_Publisher_Vehicle_Taxonomy_Service.
 */
class VehicleTaxonomyTest extends WP_UnitTestCase {

	/**
	 * Vehicle service.
	 *
	 * @var RevIt_Publisher_Vehicle_Taxonomy_Service
	 */
	private RevIt_Publisher_Vehicle_Taxonomy_Service $service;

	/**
	 * Set up test case.
	 */
	public function set_up(): void {
		parent::set_up();
		RevIt_Publisher_Taxonomies::register();
		$this->service = new RevIt_Publisher_Vehicle_Taxonomy_Service();
	}

	/**
	 * Term creation works for vehicle hierarchy.
	 */
	public function test_term_creation(): void {
		$post_id = self::factory()->post->create();
		$vehicle = (object) array(
			'manufacturer' => 'BMW',
			'model'        => 'X3',
			'generation'   => 'G01',
			'trim'         => 'M40i',
			'start_year'   => 2018,
			'end_year'     => 2024,
			'engines'      => array( 'B58' ),
		);

		$result = $this->service->sync_post( $post_id, $vehicle );
		$this->assertTrue( $result );

		$manufacturers = wp_get_post_terms( $post_id, RevIt_Publisher_Taxonomies::MANUFACTURER );
		$this->assertCount( 1, $manufacturers );
	}

	/**
	 * Deterministic term reuse avoids duplicates.
	 */
	public function test_deterministic_term_reuse(): void {
		$post_one = self::factory()->post->create();
		$post_two = self::factory()->post->create();
		$vehicle  = (object) array(
			'manufacturer' => 'BMW',
			'model'        => 'X3',
			'generation'   => 'G01',
			'trim'         => 'M40i',
			'start_year'   => 2018,
			'end_year'     => 2024,
			'engines'      => array( 'B58' ),
		);

		$this->service->sync_post( $post_one, $vehicle );
		$this->service->sync_post( $post_two, $vehicle );

		$count = wp_count_terms(
			array(
				'taxonomy'   => RevIt_Publisher_Taxonomies::MANUFACTURER,
				'hide_empty' => false,
			)
		);

		$this->assertSame( 1, (int) $count );
	}

	/**
	 * Multiple engines create multiple engine terms.
	 */
	public function test_multiple_engines(): void {
		$post_id = self::factory()->post->create();
		$vehicle = (object) array(
			'manufacturer' => 'BMW',
			'model'        => 'X3',
			'generation'   => 'G01',
			'trim'         => 'M40i',
			'start_year'   => 2018,
			'end_year'     => 2024,
			'engines'      => array( 'B58', 'B48' ),
		);

		$this->service->sync_post( $post_id, $vehicle );

		$engines = wp_get_post_terms( $post_id, RevIt_Publisher_Taxonomies::ENGINE, array( 'fields' => 'names' ) );
		$this->assertCount( 2, $engines );
	}
}
