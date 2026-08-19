<?php
/**
 * Google Search Console integration tests.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

class GscIntegrationTest extends WP_UnitTestCase {

	public static function wpSetUpBeforeClass( $factory ): void {
		unset( $factory );
		RevIt_Publisher_GSC_Schema::install();
	}

	public function test_fixture_connect_and_sync(): void {
		RevIt_Publisher_Services::gsc_auth()->connect_fixture();
		$this->assertTrue( RevIt_Publisher_Services::gsc_auth()->is_connected() );

		$post_id = wp_insert_post(
			array(
				'post_type'   => 'post',
				'post_status' => 'publish',
				'post_title'  => 'Coolant Loss Test',
				'post_name'   => 'bmw-x3-m40i-coolant-loss',
			)
		);
		update_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::MANAGED, '1' );
		update_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::ARTICLE_KEY, 'test-coolant' );
		update_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::INDEX, '1' );

		$result = RevIt_Publisher_Services::gsc_sync()->sync( true );
		$this->assertIsArray( $result );
		$this->assertNotEmpty( $result['success'] );

		$metrics = RevIt_Publisher_Services::gsc_data_store()->get_post_metrics( (int) $post_id, '28d' );
		$this->assertIsArray( $metrics );
		$this->assertGreaterThan( 0, (int) ( $metrics['impressions'] ?? 0 ) );
	}

	public function test_opportunity_detection(): void {
		RevIt_Publisher_Services::gsc_auth()->connect_fixture();
		$post_id = wp_insert_post(
			array(
				'post_type'   => 'post',
				'post_status' => 'publish',
				'post_title'  => 'Water Pump',
				'post_name'   => 'bmw-x3-m40i-water-pump-failure',
			)
		);
		update_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::MANAGED, '1' );
		update_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::INDEX, '1' );
		RevIt_Publisher_Services::gsc_sync()->sync( true );

		$opps = RevIt_Publisher_Services::gsc_opportunities()->list_opportunities( '28d' );
		$types = wp_list_pluck( $opps, 'issue_type' );
		$this->assertContains( 'gsc_page2_opportunity', $types );
	}

	public function test_refresh_export_excludes_tokens(): void {
		$post_id = wp_insert_post(
			array( 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'Test' )
		);
		update_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::MANAGED, '1' );
		update_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::ARTICLE_KEY, 'test-key' );

		$export = RevIt_Publisher_Services::gsc_refresh_export()->export_for_post( (int) $post_id );
		$this->assertIsArray( $export );
		$this->assertSame( 'revit-refresh-request-v1', $export['request_type'] );
		$json = wp_json_encode( $export );
		$this->assertIsString( $json );
		$this->assertStringNotContainsString( 'access_token', $json );
	}

	public function test_sync_failure_preserves_data(): void {
		RevIt_Publisher_Services::gsc_auth()->connect_fixture();
		RevIt_Publisher_Services::gsc_sync()->sync( true );
		$before = RevIt_Publisher_Services::gsc_data_store()->get_summary( '28d' );

		$client = new RevIt_Publisher_GSC_Fake_Client();
		$client->set_fail_next( true );
		$sync = new RevIt_Publisher_GSC_Sync_Service(
			$client,
			RevIt_Publisher_Services::gsc_data_store(),
			RevIt_Publisher_Services::gsc_auth(),
			RevIt_Publisher_Services::settings()
		);
		$this->assertInstanceOf( WP_Error::class, $sync->sync( true ) );
		$after = RevIt_Publisher_Services::gsc_data_store()->get_summary( '28d' );
		$this->assertSame( (int) ( $before['impressions'] ?? 0 ), (int) ( $after['impressions'] ?? 0 ) );
	}

	public function test_disconnect_clears_tokens(): void {
		RevIt_Publisher_Services::gsc_auth()->connect_fixture();
		RevIt_Publisher_Services::gsc_auth()->disconnect();
		$this->assertFalse( RevIt_Publisher_Services::gsc_auth()->is_connected() );
	}
}
