<?php
/**
 * REST API controller for article package operations.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Exposes validation, preview, and import endpoints for article packages.
 */
class RevIt_Publisher_Article_Package_Rest_Controller {

	public const REST_NAMESPACE = 'revit-publisher/v1';

	/**
	 * Validator service.
	 *
	 * @var RevIt_Publisher_Article_Package_Validator
	 */
	private RevIt_Publisher_Article_Package_Validator $validator;

	/**
	 * Preview service.
	 *
	 * @var RevIt_Publisher_Package_Preview
	 */
	private RevIt_Publisher_Package_Preview $preview;

	/**
	 * Importer service.
	 *
	 * @var RevIt_Publisher_Article_Importer
	 */
	private RevIt_Publisher_Article_Importer $importer;

	/**
	 * Article registry.
	 *
	 * @var RevIt_Publisher_Article_Registry
	 */
	private RevIt_Publisher_Article_Registry $registry;

	/**
	 * Vehicle service.
	 *
	 * @var RevIt_Publisher_Vehicle_Taxonomy_Service
	 */
	private RevIt_Publisher_Vehicle_Taxonomy_Service $vehicle_service;

	/**
	 * Cluster service.
	 *
	 * @var RevIt_Publisher_Cluster_Service
	 */
	private RevIt_Publisher_Cluster_Service $cluster_service;

	/**
	 * Constructor.
	 */
	public function __construct(
		RevIt_Publisher_Article_Package_Validator $validator,
		RevIt_Publisher_Package_Preview $preview,
		RevIt_Publisher_Article_Importer $importer,
		RevIt_Publisher_Article_Registry $registry,
		RevIt_Publisher_Vehicle_Taxonomy_Service $vehicle_service,
		RevIt_Publisher_Cluster_Service $cluster_service
	) {
		$this->validator        = $validator;
		$this->preview          = $preview;
		$this->importer         = $importer;
		$this->registry         = $registry;
		$this->vehicle_service  = $vehicle_service;
		$this->cluster_service  = $cluster_service;
	}

	/**
	 * Register routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			'/article-packages/validate',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'validate_package' ),
				'permission_callback' => array( $this, 'permissions_check' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/article-packages/preview',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'preview_package' ),
				'permission_callback' => array( $this, 'permissions_check' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/article-packages/import',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'import_package' ),
				'permission_callback' => array( $this, 'permissions_check' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/article-packages/update-preview',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'update_preview' ),
				'permission_callback' => array( $this, 'permissions_check' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/article-packages/update',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'update_package' ),
				'permission_callback' => array( $this, 'permissions_check' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/stats',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_stats' ),
				'permission_callback' => array( $this, 'permissions_check' ),
			)
		);
	}

	/**
	 * Permission check.
	 *
	 * @param WP_REST_Request $request Request object.
	 */
	public function permissions_check( WP_REST_Request $request ): bool {
		unset( $request );

		return current_user_can( 'edit_posts' );
	}

	/**
	 * Parse JSON body or return error response.
	 *
	 * @return array{0: mixed|null, 1: WP_REST_Response|null}
	 */
	private function parse_json_body( WP_REST_Request $request ): array {
		$params = $request->get_json_params();

		if ( null === $params ) {
			return array(
				null,
				new WP_REST_Response(
					array(
						'valid'  => false,
						'errors' => array(
							array(
								'path'    => '',
								'message' => __( 'Request body must be valid JSON.', 'revit-publisher' ),
							),
						),
					),
					400
				),
			);
		}

		return array( $params, null );
	}

	/**
	 * Validate submitted article package JSON.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function validate_package( WP_REST_Request $request ) {
		list( $params, $error ) = $this->parse_json_body( $request );
		if ( null !== $error ) {
			return $error;
		}

		$result = $this->validator->validate( $params );

		if ( ! $result['valid'] ) {
			return new WP_REST_Response(
				array(
					'valid'  => false,
					'errors' => $result['errors'],
				),
				400
			);
		}

		return new WP_REST_Response(
			array(
				'valid'          => true,
				'schema_version' => $result['schema_version'],
				'article_key'    => $result['article_key'],
				'warnings'       => $result['warnings'],
			),
			200
		);
	}

	/**
	 * Preview package without creating content.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function preview_package( WP_REST_Request $request ) {
		list( $params, $error ) = $this->parse_json_body( $request );
		if ( null !== $error ) {
			return $error;
		}

		$result = $this->validator->validate( $params );
		if ( ! $result['valid'] ) {
			return new WP_REST_Response(
				array(
					'valid'  => false,
					'errors' => $result['errors'],
				),
				400
			);
		}

		$package = is_array( $params ) ? json_decode( wp_json_encode( $params ), false ) : $params;

		return new WP_REST_Response( $this->preview->build( $package ), 200 );
	}

	/**
	 * Import package as WordPress post.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function import_package( WP_REST_Request $request ) {
		list( $params, $error ) = $this->parse_json_body( $request );
		if ( null !== $error ) {
			return $error;
		}

		$result = $this->importer->import( $params );

		if ( 'validation_failed' === ( $result['status'] ?? '' ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'valid'   => false,
					'errors'  => $result['errors'] ?? array(),
				),
				400
			);
		}

		if ( 'existing_article' === ( $result['status'] ?? '' ) ) {
			return new WP_REST_Response( $result, 409 );
		}

		if ( empty( $result['success'] ) ) {
			return new WP_REST_Response( $result, 500 );
		}

		return new WP_REST_Response( $result, 201 );
	}

	/**
	 * Preview update diff for existing article.
	 */
	public function update_preview( WP_REST_Request $request ) {
		list( $params, $error ) = $this->parse_json_body( $request );
		if ( null !== $error ) {
			return $error;
		}

		$post_id = (int) ( $params['post_id'] ?? 0 );
		$mode    = sanitize_key( (string) ( $params['mode'] ?? RevIt_Publisher_Article_Update_Service::MODE_FULL ) );
		unset( $params['post_id'], $params['mode'] );

		if ( $post_id <= 0 || ! current_user_can( 'edit_post', $post_id ) ) {
			return new WP_REST_Response( array( 'valid' => false, 'message' => 'Invalid post.' ), 403 );
		}

		$updater = $this->get_updater();
		$result  = $updater->preview_update( $post_id, $params, $mode );

		return new WP_REST_Response( $result, ! empty( $result['valid'] ) ? 200 : 400 );
	}

	/**
	 * Apply approved update.
	 */
	public function update_package( WP_REST_Request $request ) {
		list( $params, $error ) = $this->parse_json_body( $request );
		if ( null !== $error ) {
			return $error;
		}

		$post_id = (int) ( $params['post_id'] ?? 0 );
		$mode    = sanitize_key( (string) ( $params['mode'] ?? RevIt_Publisher_Article_Update_Service::MODE_FULL ) );
		unset( $params['post_id'], $params['mode'] );

		if ( $post_id <= 0 || ! current_user_can( 'edit_post', $post_id ) ) {
			return new WP_REST_Response( array( 'success' => false, 'message' => 'Invalid post.' ), 403 );
		}

		$updater = $this->get_updater();
		$result  = $updater->apply_update( $post_id, $params, $mode );

		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response(
				array( 'success' => false, 'message' => $result->get_error_message() ),
				(int) ( $result->get_error_data()['status'] ?? 400 )
			);
		}

		return new WP_REST_Response( $result, 200 );
	}

	private function get_updater(): RevIt_Publisher_Article_Update_Service {
		return new RevIt_Publisher_Article_Update_Service(
			$this->validator,
			$this->registry,
			$this->vehicle_service,
			$this->cluster_service,
			new RevIt_Publisher_Content_Renderer(),
			new RevIt_Publisher_Package_Hash()
		);
	}

	/**
	 * Dashboard statistics.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_stats( WP_REST_Request $request ) {
		unset( $request );

		$health   = RevIt_Publisher_Services::health_service()->get_summary();
		$audit    = RevIt_Publisher_Services::audit_service()->get_audit();
		$overlaps = RevIt_Publisher_Services::topic_overlaps()->find_overlaps();
		$plans    = RevIt_Publisher_Services::plan_service()->list_plans();

		$missing_content = 0;
		foreach ( $plans as $plan ) {
			$missing_content += (int) ( $plan['summary']['missing_articles'] ?? 0 );
		}

		$audits = RevIt_Publisher_Services::site_audit()->list_snapshots( 1 );
		$latest_audit = ! empty( $audits ) ? $audits[0] : null;
		$open_issues  = RevIt_Publisher_Services::issues()->count_open();

		return new WP_REST_Response(
			array(
				'version'           => REVIT_PUBLISHER_VERSION,
				'schema_version'    => RevIt_Publisher_Article_Package_Validator::SCHEMA_VERSION,
				'imported_articles' => $this->registry->count_managed_articles(),
				'vehicle_models'    => $this->vehicle_service->count_models(),
				'clusters'          => $this->cluster_service->count_clusters(),
				'content_plans'     => count( $plans ),
				'seo_health'        => $health,
				'content_graph'     => array(
					'vehicles'       => count( RevIt_Publisher_Services::graph()->get_vehicle_summaries() ),
					'clusters'       => count( RevIt_Publisher_Services::graph()->get_cluster_summaries() ),
					'resolved_links' => (int) ( $audit['resolved'] ?? 0 ),
					'pending_links'  => (int) ( $audit['unresolved'] ?? 0 ),
				),
				'intelligence'      => array(
					'missing_content'    => $missing_content,
					'topic_overlaps'     => count( $overlaps ),
					'open_issues'        => $open_issues,
					'latest_audit'       => $latest_audit,
					'vehicle_health'     => RevIt_Publisher_Services::vehicle_health()->get_all_vehicle_summaries(),
					'needs_attention'    => array(
						'orphans'          => (int) ( $health['orphan_articles'] ?? 0 ),
						'topic_overlaps'   => count( array_filter( $overlaps, static fn( $o ) => 'high' === ( $o['risk'] ?? '' ) ) ),
						'missing_meta'     => (int) ( $health['missing_meta'] ?? 0 ),
						'unresolved_links' => (int) ( $health['unresolved_links'] ?? 0 ),
						'open_issues'      => $open_issues,
					),
				),
			),
			200
		);
	}
}
