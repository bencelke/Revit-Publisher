<?php
/**
 * Content plan REST controller.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST endpoints for content plan operations.
 */
class RevIt_Publisher_Content_Plan_Rest_Controller {

	public const REST_NAMESPACE = 'revit-publisher/v1';

	private RevIt_Publisher_Content_Plan_Validator $validator;
	private RevIt_Publisher_Content_Plan_Importer $importer;
	private RevIt_Publisher_Content_Plan_Service $plan_service;
	private RevIt_Publisher_Article_Request_Exporter $exporter;

	public function __construct() {
		$this->validator    = new RevIt_Publisher_Content_Plan_Validator();
		$this->importer     = new RevIt_Publisher_Content_Plan_Importer( $this->validator, new RevIt_Publisher_Package_Hash() );
		$this->plan_service = RevIt_Publisher_Services::plan_service();
		$this->exporter     = new RevIt_Publisher_Article_Request_Exporter( $this->plan_service );
	}

	public function register_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			'/content-plans/validate',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'validate_plan' ),
				'permission_callback' => array( $this, 'can_edit_posts' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/content-plans/preview',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'preview_plan' ),
				'permission_callback' => array( $this, 'can_edit_posts' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/content-plans/import',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'import_plan' ),
				'permission_callback' => array( $this, 'can_edit_posts' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/content-plans',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'list_plans' ),
				'permission_callback' => array( $this, 'can_edit_posts' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/content-plans/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_plan' ),
				'permission_callback' => array( $this, 'can_edit_posts' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/content-plans/(?P<id>\d+)/coverage',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_coverage' ),
				'permission_callback' => array( $this, 'can_edit_posts' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/content-plans/(?P<id>\d+)/missing-articles',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_missing_articles' ),
				'permission_callback' => array( $this, 'can_edit_posts' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/content-plans/(?P<id>\d+)/article-request',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'export_article_request' ),
				'permission_callback' => array( $this, 'can_edit_posts' ),
				'args'                => array(
					'article_key'  => array( 'type' => 'string' ),
					'cluster_key'  => array( 'type' => 'string' ),
					'scope'        => array( 'type' => 'string', 'default' => 'single' ),
				),
			)
		);
	}

	public function can_edit_posts( WP_REST_Request $request ): bool {
		unset( $request );
		return current_user_can( 'edit_posts' );
	}

	public function validate_plan( WP_REST_Request $request ) {
		$params = $request->get_json_params();
		if ( null === $params ) {
			return new WP_REST_Response( array( 'valid' => false, 'errors' => array() ), 400 );
		}
		$result = $this->validator->validate( $params );
		return new WP_REST_Response( $result, $result['valid'] ? 200 : 400 );
	}

	public function preview_plan( WP_REST_Request $request ) {
		$params = $request->get_json_params();
		if ( null === $params ) {
			return new WP_REST_Response( array( 'valid' => false ), 400 );
		}
		return new WP_REST_Response( $this->importer->preview( $params ), 200 );
	}

	public function import_plan( WP_REST_Request $request ) {
		$params = $request->get_json_params();
		if ( null === $params ) {
			return new WP_REST_Response( array( 'success' => false ), 400 );
		}
		$result = $this->importer->import( $params );
		return new WP_REST_Response( $result, ! empty( $result['success'] ) ? 201 : 400 );
	}

	public function list_plans( WP_REST_Request $request ) {
		unset( $request );
		return new WP_REST_Response( $this->plan_service->list_plans(), 200 );
	}

	public function get_plan( WP_REST_Request $request ) {
		$plan_id = (int) $request->get_param( 'id' );
		$plan    = $this->plan_service->get_plan_data( $plan_id );
		if ( null === $plan ) {
			return new WP_REST_Response( array( 'message' => 'Plan not found.' ), 404 );
		}
		return new WP_REST_Response(
			array(
				'plan_id'  => $plan_id,
				'plan_key' => (string) $plan->plan_key,
				'vehicle'  => RevIt_Publisher_Content_Plan_Service::format_vehicle_label( $plan->vehicle ),
				'plan'     => json_decode( wp_json_encode( $plan ), true ),
			),
			200
		);
	}

	public function get_coverage( WP_REST_Request $request ) {
		$plan_id  = (int) $request->get_param( 'id' );
		$coverage = $this->plan_service->get_coverage( $plan_id );
		if ( empty( $coverage ) ) {
			return new WP_REST_Response( array( 'message' => 'Plan not found.' ), 404 );
		}
		return new WP_REST_Response( $coverage, 200 );
	}

	public function get_missing_articles( WP_REST_Request $request ) {
		$plan_id  = (int) $request->get_param( 'id' );
		$coverage = $this->plan_service->get_coverage( $plan_id );
		return new WP_REST_Response( $coverage['missing'] ?? array(), 200 );
	}

	public function export_article_request( WP_REST_Request $request ) {
		$plan_id     = (int) $request->get_param( 'id' );
		$scope       = sanitize_key( (string) $request->get_param( 'scope' ) );
		$article_key = sanitize_text_field( (string) $request->get_param( 'article_key' ) );
		$cluster_key = sanitize_text_field( (string) $request->get_param( 'cluster_key' ) );

		if ( 'vehicle' === $scope ) {
			return new WP_REST_Response( $this->exporter->export_vehicle( $plan_id ), 200 );
		}
		if ( 'cluster' === $scope && '' !== $cluster_key ) {
			return new WP_REST_Response( $this->exporter->export_cluster( $plan_id, $cluster_key ), 200 );
		}
		if ( '' === $article_key ) {
			return new WP_REST_Response( array( 'message' => 'article_key required.' ), 400 );
		}

		$export = $this->exporter->export_single( $plan_id, $article_key );
		if ( null === $export ) {
			return new WP_REST_Response( array( 'message' => 'Article not found in plan.' ), 404 );
		}

		return new WP_REST_Response( $export, 200 );
	}
}
