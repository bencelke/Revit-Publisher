<?php
/**
 * Vehicle hub and public SEO integration tests.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class VehicleHubTest extends WP_UnitTestCase {

	private static int $hub_id = 0;
	private static int $article_id = 0;

	public static function wpSetUpBeforeClass( $factory ): void {
		RevIt_Publisher_Taxonomies::register();
		RevIt_Publisher_Taxonomies::ensure_article_type_terms();
		RevIt_Publisher_Operations_Post_Types::register();
		RevIt_Publisher_Vehicle_Hub_Post_Type::register();
		flush_rewrite_rules();

		self::$article_id = wp_insert_post(
			array(
				'post_type'   => 'post',
				'post_status' => 'publish',
				'post_title'  => 'BMW X3 M40i Coolant Loss',
			)
		);
		update_post_meta( self::$article_id, RevIt_Publisher_Post_Meta_Keys::MANAGED, '1' );
		update_post_meta( self::$article_id, RevIt_Publisher_Post_Meta_Keys::ARTICLE_KEY, 'x3-coolant-loss' );
		update_post_meta( self::$article_id, RevIt_Publisher_Post_Meta_Keys::VEHICLE_MANUFACTURER, 'BMW' );
		update_post_meta( self::$article_id, RevIt_Publisher_Post_Meta_Keys::VEHICLE_MODEL, 'X3' );
		update_post_meta( self::$article_id, RevIt_Publisher_Post_Meta_Keys::VEHICLE_GENERATION, 'G01' );
		update_post_meta( self::$article_id, RevIt_Publisher_Post_Meta_Keys::VEHICLE_TRIM, 'M40i' );
		update_post_meta( self::$article_id, RevIt_Publisher_Post_Meta_Keys::INDEX, '1' );
		wp_set_object_terms( self::$article_id, array( 'problem' ), RevIt_Publisher_Taxonomies::ARTICLE_TYPE, false );

		$identity = array(
			'manufacturer' => 'BMW',
			'model'        => 'X3',
			'generation'   => 'G01',
			'trim'         => 'M40i',
			'start_year'   => '2018',
			'end_year'     => '2024',
			'engines'      => array( 'B58' ),
		);
		$key = RevIt_Publisher_Vehicle_Identity::build_key( 'BMW', 'X3', 'G01', 'M40i' );
		$result = RevIt_Publisher_Services::vehicle_hubs()->create_draft( $key, $identity );
		self::assertIsInt( $result );
		self::$hub_id = (int) $result;
	}

	public static function wpTearDownAfterClass(): void {
		if ( self::$hub_id > 0 ) {
			wp_delete_post( self::$hub_id, true );
		}
		if ( self::$article_id > 0 ) {
			wp_delete_post( self::$article_id, true );
		}
	}

	public function test_deterministic_vehicle_key(): void {
		$key = RevIt_Publisher_Vehicle_Identity::build_key( 'BMW', 'X3', 'G01', 'M40i' );
		$this->assertSame( 'bmw-x3-g01-m40i', $key );
		$this->assertSame( $key, RevIt_Publisher_Vehicle_Identity::from_post( self::$article_id ) );
	}

	public function test_duplicate_hub_prevention(): void {
		$key = RevIt_Publisher_Vehicle_Identity::build_key( 'BMW', 'X3', 'G01', 'M40i' );
		$result = RevIt_Publisher_Services::vehicle_hubs()->create_draft(
			$key,
			array(
				'manufacturer' => 'BMW',
				'model'        => 'X3',
				'generation'   => 'G01',
				'trim'         => 'M40i',
			)
		);
		$this->assertInstanceOf( WP_Error::class, $result );
	}

	public function test_article_grouping_and_draft_leakage(): void {
		$sections = RevIt_Publisher_Services::vehicle_hubs()->get_articles_by_section( self::$hub_id );
		$this->assertNotEmpty( $sections['common_problems'] );
		$this->assertSame( self::$article_id, (int) $sections['common_problems'][0]['post_id'] );

		$draft_id = wp_insert_post(
			array(
				'post_type'   => 'post',
				'post_status' => 'draft',
				'post_title'  => 'Draft Problem',
			)
		);
		update_post_meta( $draft_id, RevIt_Publisher_Post_Meta_Keys::MANAGED, '1' );
		update_post_meta( $draft_id, RevIt_Publisher_Post_Meta_Keys::VEHICLE_MANUFACTURER, 'BMW' );
		update_post_meta( $draft_id, RevIt_Publisher_Post_Meta_Keys::VEHICLE_MODEL, 'X3' );
		update_post_meta( $draft_id, RevIt_Publisher_Post_Meta_Keys::VEHICLE_GENERATION, 'G01' );
		update_post_meta( $draft_id, RevIt_Publisher_Post_Meta_Keys::VEHICLE_TRIM, 'M40i' );
		wp_set_object_terms( $draft_id, array( 'problem' ), RevIt_Publisher_Taxonomies::ARTICLE_TYPE, false );
		RevIt_Publisher_Services::hub_cache()->invalidate_hub( self::$hub_id );

		$sections = RevIt_Publisher_Services::vehicle_hubs()->get_articles_by_section( self::$hub_id );
		foreach ( $sections['common_problems'] as $article ) {
			$this->assertSame( 'publish', get_post_status( (int) $article['post_id'] ) );
		}
		wp_delete_post( $draft_id, true );
	}

	public function test_sitemap_indexability(): void {
		$sitemap = RevIt_Publisher_Services::sitemap();
		$this->assertTrue( $sitemap->is_post_indexable( self::$article_id, 'post' ) );

		update_post_meta( self::$article_id, RevIt_Publisher_Post_Meta_Keys::INDEX, '0' );
		$this->assertFalse( $sitemap->is_post_indexable( self::$article_id, 'post' ) );
		update_post_meta( self::$article_id, RevIt_Publisher_Post_Meta_Keys::INDEX, '1' );

		wp_update_post(
			array(
				'ID'          => self::$hub_id,
				'post_status' => 'publish',
			)
		);
		$this->assertTrue(
			$sitemap->is_post_indexable( self::$hub_id, RevIt_Publisher_Vehicle_Hub_Post_Type::POST_TYPE )
		);
		wp_update_post(
			array(
				'ID'          => self::$hub_id,
				'post_status' => 'draft',
			)
		);
	}

	public function test_issue_retention_purges_old_resolved_only(): void {
		$open_id = wp_insert_post(
			array(
				'post_type'   => RevIt_Publisher_Operations_Post_Types::ISSUE,
				'post_status' => 'private',
				'post_title'  => 'Open issue',
			)
		);
		update_post_meta( $open_id, RevIt_Publisher_Operations_Meta_Keys::ISSUE_STATUS, RevIt_Publisher_Issue_Service::STATUS_OPEN );

		$resolved_id = wp_insert_post(
			array(
				'post_type'   => RevIt_Publisher_Operations_Post_Types::ISSUE,
				'post_status' => 'private',
				'post_title'  => 'Old resolved',
			)
		);
		update_post_meta( $resolved_id, RevIt_Publisher_Operations_Meta_Keys::ISSUE_STATUS, RevIt_Publisher_Issue_Service::STATUS_RESOLVED );
		update_post_meta( $resolved_id, RevIt_Publisher_Operations_Meta_Keys::ISSUE_RESOLVED_AT, gmdate( 'c', time() - ( 400 * DAY_IN_SECONDS ) ) );

		RevIt_Publisher_Issue_Retention_Cron::run_purge();

		$this->assertInstanceOf( WP_Post::class, get_post( $open_id ) );
		$this->assertNull( get_post( $resolved_id ) );
	}
}
