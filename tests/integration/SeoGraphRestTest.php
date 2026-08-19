<?php
/**
 * SEO graph REST integration tests.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

require_once __DIR__ . '/GraphTestHelper.php';

/**
 * Tests for graph REST endpoints.
 */
class SeoGraphRestTest extends WP_UnitTestCase {

	use RevIt_Graph_Test_Helper;

	/**
	 * Admin user ID.
	 *
	 * @var int
	 */
	private int $admin_id;

	/**
	 * Set up.
	 */
	public function set_up(): void {
		parent::set_up();
		$this->set_up_graph_importer();
		$this->admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );

		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		do_action( 'rest_api_init' );
	}

	/**
	 * Tear down REST server.
	 */
	public function tear_down(): void {
		global $wp_rest_server;
		$wp_rest_server = null;
		parent::tear_down();
	}

	/**
	 * Link suggestions endpoint.
	 */
	public function test_link_suggestions_endpoint(): void {
		wp_set_current_user( $this->admin_id );
		$coolant_id = $this->import_graph_package( 'x3-coolant-loss.json' );
		$this->import_graph_package( 'x3-water-pump.json' );

		$request  = new WP_REST_Request( 'GET', '/revit-publisher/v1/posts/' . $coolant_id . '/link-suggestions' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertNotEmpty( $data['suggestions'] );
	}

	/**
	 * Apply link endpoint.
	 */
	public function test_apply_link_endpoint(): void {
		wp_set_current_user( $this->admin_id );
		$coolant_id = $this->import_graph_package( 'x3-coolant-loss.json' );
		$this->import_graph_package( 'x3-water-pump.json' );

		$suggestions = RevIt_Publisher_Services::link_service()->get_suggestions( $coolant_id );
		$this->assertNotEmpty( $suggestions );

		$request = new WP_REST_Request( 'POST', '/revit-publisher/v1/posts/' . $coolant_id . '/apply-link' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $suggestions[0] ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( $response->get_data()['success'] );
	}

	/**
	 * Unauthorized user denied.
	 */
	public function test_unauthorized_denied(): void {
		wp_set_current_user( 0 );
		$post_id  = self::factory()->post->create();
		$request  = new WP_REST_Request( 'GET', '/revit-publisher/v1/posts/' . $post_id . '/link-suggestions' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertTrue( in_array( $response->get_status(), array( 401, 403 ), true ) );
	}

	/**
	 * Invalid target rejected.
	 */
	public function test_invalid_target_rejected(): void {
		wp_set_current_user( $this->admin_id );
		$coolant_id = $this->import_graph_package( 'x3-coolant-loss.json' );

		$request = new WP_REST_Request( 'POST', '/revit-publisher/v1/posts/' . $coolant_id . '/apply-link' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body(
			wp_json_encode(
				array(
					'target_post_id' => 999999,
					'anchor'         => 'test anchor',
					'block_index'    => 0,
				)
			)
		);
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 400, $response->get_status() );
	}
}
