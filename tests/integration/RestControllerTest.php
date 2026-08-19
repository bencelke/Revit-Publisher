<?php
/**
 * REST API integration tests.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

/**
 * Tests for article package REST endpoints.
 */
class RestControllerTest extends WP_UnitTestCase {

	/**
	 * REST controller.
	 *
	 * @var RevIt_Publisher_Article_Package_Rest_Controller
	 */
	private RevIt_Publisher_Article_Package_Rest_Controller $controller;

	/**
	 * Valid package array.
	 *
	 * @var array<string, mixed>
	 */
	private array $valid_package;

	/**
	 * Set up REST tests.
	 */
	public function set_up(): void {
		parent::set_up();

		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		do_action( 'rest_api_init' );

		$registry        = new RevIt_Publisher_Article_Registry();
		$vehicle_service = new RevIt_Publisher_Vehicle_Taxonomy_Service();
		$cluster_service = new RevIt_Publisher_Cluster_Service();
		$validator       = new RevIt_Publisher_Article_Package_Validator();

		$this->controller = new RevIt_Publisher_Article_Package_Rest_Controller(
			$validator,
			new RevIt_Publisher_Package_Preview( $registry, $vehicle_service ),
			new RevIt_Publisher_Article_Importer(
				$validator,
				$registry,
				$vehicle_service,
				$cluster_service,
				new RevIt_Publisher_Content_Renderer(),
				new RevIt_Publisher_Package_Hash()
			),
			$registry,
			$vehicle_service,
			$cluster_service
		);
		$this->controller->register_routes();

		RevIt_Publisher_Taxonomies::register();
		RevIt_Publisher_Taxonomies::ensure_article_type_terms();

		$json = file_get_contents( REVIT_PUBLISHER_PLUGIN_DIR . 'examples/article-valid.json' );
		$this->valid_package = json_decode( (string) $json, true );
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
	 * Validate endpoint returns success for valid package.
	 */
	public function test_validate_endpoint(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

		$request = new WP_REST_Request( 'POST', '/revit-publisher/v1/article-packages/validate' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $this->valid_package ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( $response->get_data()['valid'] );
	}

	/**
	 * Preview endpoint returns preview payload.
	 */
	public function test_preview_endpoint(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

		$request = new WP_REST_Request( 'POST', '/revit-publisher/v1/article-packages/preview' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $this->valid_package ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( $response->get_data()['valid'] );
		$this->assertSame( 'BMW X3 G01 M40i', $response->get_data()['vehicle'] );
	}

	/**
	 * Import endpoint creates draft post.
	 */
	public function test_import_endpoint(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

		$request = new WP_REST_Request( 'POST', '/revit-publisher/v1/article-packages/import' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $this->valid_package ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 201, $response->get_status() );
		$this->assertTrue( $response->get_data()['success'] );
	}

	/**
	 * Duplicate import returns existing_article response.
	 */
	public function test_duplicate_import_response(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

		$request = new WP_REST_Request( 'POST', '/revit-publisher/v1/article-packages/import' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $this->valid_package ) );
		rest_get_server()->dispatch( $request );

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 409, $response->get_status() );
		$this->assertSame( 'existing_article', $response->get_data()['status'] );
	}

	/**
	 * Unauthorized users are denied.
	 */
	public function test_unauthorized_denied(): void {
		wp_set_current_user( 0 );

		$request = new WP_REST_Request( 'POST', '/revit-publisher/v1/article-packages/import' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $this->valid_package ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 401, $response->get_status() );
	}
}
