<?php
/**
 * Editorial queue REST API controller.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RevIt_Publisher_Editorial_Rest_Controller {

	private const REST_NAMESPACE = 'revit-publisher/v1';

	public function register_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			'/editorial-queue',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_items' ),
					'permission_callback' => array( $this, 'can_edit' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'can_edit' ),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/editorial-queue/reconcile',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'reconcile' ),
				'permission_callback' => array( $this, 'can_edit' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/editorial-queue/today',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'today_summary' ),
				'permission_callback' => array( $this, 'can_edit' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/editorial-queue/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'update_item' ),
				'permission_callback' => array( $this, 'can_edit' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/vehicles/(?P<id>\d+)/opportunity',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'vehicle_opportunity' ),
				'permission_callback' => array( $this, 'can_edit' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/clusters/(?P<key>[a-zA-Z0-9_-]+)/opportunity',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'cluster_opportunity' ),
				'permission_callback' => array( $this, 'can_edit' ),
			)
		);
	}

	public function can_edit(): bool {
		return current_user_can( 'edit_posts' );
	}

	public function list_items( WP_REST_Request $request ): WP_REST_Response {
		$filters = array(
			'status'      => $request->get_param( 'status' ),
			'action_type' => $request->get_param( 'action_type' ),
			'vehicle'     => $request->get_param( 'vehicle' ),
			'cluster'     => $request->get_param( 'cluster' ),
			'priority'    => $request->get_param( 'priority' ),
			'today'       => $request->get_param( 'today' ),
			'limit'       => (int) ( $request->get_param( 'limit' ) ?? 100 ),
		);
		return new WP_REST_Response(
			RevIt_Publisher_Services::editorial_queue()->list_items( array_filter( $filters ) ),
			200
		);
	}

	public function create_item( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$result = RevIt_Publisher_Services::editorial_queue()->create_manual( (array) $request->get_json_params() );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$item = RevIt_Publisher_Services::editorial_queue()->get_item( (int) $result );
		return new WP_REST_Response( $item, 201 );
	}

	public function update_item( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$result = RevIt_Publisher_Services::editorial_queue()->update_item(
			(int) $request->get_param( 'id' ),
			(array) $request->get_json_params()
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return new WP_REST_Response( $result, 200 );
	}

	public function reconcile(): WP_REST_Response {
		return new WP_REST_Response( RevIt_Publisher_Services::editorial_reconciler()->reconcile(), 200 );
	}

	public function today_summary(): WP_REST_Response {
		$items = RevIt_Publisher_Services::editorial_queue()->list_items( array( 'today' => true, 'limit' => 10 ) );
		return new WP_REST_Response(
			array(
				'counts'  => RevIt_Publisher_Services::editorial_queue()->count_by_priority( 'today' ),
				'items'   => $items,
			),
			200
		);
	}

	public function vehicle_opportunity( WP_REST_Request $request ): WP_REST_Response {
		return new WP_REST_Response(
			RevIt_Publisher_Services::vehicle_opportunity()->for_hub_id( (int) $request->get_param( 'id' ) ),
			200
		);
	}

	public function cluster_opportunity( WP_REST_Request $request ): WP_REST_Response {
		$key = sanitize_key( (string) $request->get_param( 'key' ) );
		return new WP_REST_Response(
			RevIt_Publisher_Services::cluster_opportunity()->summarize_cluster( $key ),
			200
		);
	}
}
