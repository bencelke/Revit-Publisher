<?php
/**
 * Editorial queue integration tests.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

class EditorialIntegrationTest extends WP_UnitTestCase {

	public static function wpSetUpBeforeClass( $factory ): void {
		unset( $factory );
		RevIt_Publisher_GSC_Schema::install();
	}

	public function test_priority_detection_and_reconcile(): void {
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

		$result = RevIt_Publisher_Services::editorial_reconciler()->reconcile();
		$this->assertTrue( $result['success'] );
		$this->assertGreaterThan( 0, (int) ( $result['candidates'] ?? 0 ) );

		$items = RevIt_Publisher_Services::editorial_queue()->list_items( array( 'limit' => 20 ) );
		$this->assertNotEmpty( $items );
		$this->assertNotEmpty( $items[0]['reasons'] );
	}

	public function test_defer_complete_cooldown(): void {
		RevIt_Publisher_Services::editorial_reconciler()->reconcile();
		$items = RevIt_Publisher_Services::editorial_queue()->list_items( array( 'limit' => 1 ) );
		if ( empty( $items ) ) {
			$this->markTestSkipped( 'No queue items generated.' );
		}
		$item = $items[0];
		RevIt_Publisher_Services::editorial_queue()->update_item(
			(int) $item['id'],
			array( 'status' => 'completed' )
		);
		RevIt_Publisher_Services::editorial_reconciler()->reconcile();
		$updated = RevIt_Publisher_Services::editorial_queue()->get_item( (int) $item['id'] );
		$this->assertSame( 'completed', $updated['status'] ?? '' );
	}

	public function test_backup_excludes_secrets(): void {
		RevIt_Publisher_Services::gsc_auth()->connect_fixture();
		$backup = RevIt_Publisher_Services::backup()->export();
		$json = wp_json_encode( $backup );
		$this->assertIsString( $json );
		$this->assertStringNotContainsString( 'access_token', $json );
		$this->assertStringNotContainsString( 'refresh_token', $json );
	}

	public function test_system_health_checks(): void {
		$checks = RevIt_Publisher_Services::system_health()->run_checks();
		$this->assertNotEmpty( $checks );
		$ids = wp_list_pluck( $checks, 'id' );
		$this->assertContains( 'php_version', $ids );
	}
}
