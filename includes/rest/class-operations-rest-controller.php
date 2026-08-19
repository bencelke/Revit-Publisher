<?php
/**
 * Operations REST API (audits, issues, redirects, consolidation, 404).
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST endpoints for SEO operations and maintenance.
 */
class RevIt_Publisher_Operations_Rest_Controller {

	private const REST_NAMESPACE = 'revit-publisher/v1';

	public function register_routes(): void {
		register_rest_route( self::REST_NAMESPACE, '/audits/run', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'run_audit' ),
			'permission_callback' => array( $this, 'can_manage' ),
		) );
		register_rest_route( self::REST_NAMESPACE, '/audits', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'list_audits' ),
			'permission_callback' => array( $this, 'can_edit' ),
		) );
		register_rest_route( self::REST_NAMESPACE, '/audits/(?P<id>\d+)', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_audit' ),
			'permission_callback' => array( $this, 'can_edit' ),
		) );
		register_rest_route( self::REST_NAMESPACE, '/issues', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'list_issues' ),
			'permission_callback' => array( $this, 'can_edit' ),
		) );
		register_rest_route( self::REST_NAMESPACE, '/issues/(?P<id>\d+)', array(
			'methods'             => WP_REST_Server::EDITABLE,
			'callback'            => array( $this, 'update_issue' ),
			'permission_callback' => array( $this, 'can_edit' ),
		) );
		register_rest_route( self::REST_NAMESPACE, '/redirects', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'list_redirects' ),
				'permission_callback' => array( $this, 'can_manage' ),
			),
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create_redirect' ),
				'permission_callback' => array( $this, 'can_manage' ),
			),
		) );
		register_rest_route( self::REST_NAMESPACE, '/redirects/(?P<id>\d+)', array(
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'update_redirect' ),
				'permission_callback' => array( $this, 'can_manage' ),
			),
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'disable_redirect' ),
				'permission_callback' => array( $this, 'can_manage' ),
			),
		) );
		register_rest_route( self::REST_NAMESPACE, '/consolidations/preview', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'preview_consolidation' ),
			'permission_callback' => array( $this, 'can_manage' ),
		) );
		register_rest_route( self::REST_NAMESPACE, '/consolidations/apply', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'apply_consolidation' ),
			'permission_callback' => array( $this, 'can_manage' ),
		) );
		register_rest_route( self::REST_NAMESPACE, '/404s', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'list_404s' ),
			'permission_callback' => array( $this, 'can_manage' ),
		) );
		register_rest_route( self::REST_NAMESPACE, '/404s/(?P<id>\d+)/ignore', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'ignore_404' ),
			'permission_callback' => array( $this, 'can_manage' ),
		) );
		register_rest_route( self::REST_NAMESPACE, '/404s/(?P<id>\d+)/resolve', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'resolve_404' ),
			'permission_callback' => array( $this, 'can_manage' ),
		) );
		register_rest_route( self::REST_NAMESPACE, '/cluster-links/(?P<cluster_key>[a-z0-9\-]+)', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_cluster_links' ),
			'permission_callback' => array( $this, 'can_edit' ),
		) );
		register_rest_route( self::REST_NAMESPACE, '/cluster-links/apply', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'apply_cluster_links' ),
			'permission_callback' => array( $this, 'can_edit' ),
		) );
		register_rest_route( self::REST_NAMESPACE, '/link-changes/(?P<id>\d+)/undo', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'undo_link' ),
			'permission_callback' => array( $this, 'can_edit' ),
		) );
		register_rest_route( self::REST_NAMESPACE, '/vehicles', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'list_vehicles' ),
			'permission_callback' => array( $this, 'can_edit' ),
		) );
		register_rest_route( self::REST_NAMESPACE, '/vehicles/detail', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_vehicle_detail' ),
			'permission_callback' => array( $this, 'can_edit' ),
		) );
		register_rest_route( self::REST_NAMESPACE, '/overlap-decisions', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'record_overlap_decision' ),
			'permission_callback' => array( $this, 'can_edit' ),
		) );
	}

	public function can_edit(): bool {
		return current_user_can( 'edit_posts' );
	}

	public function can_manage(): bool {
		return current_user_can( 'manage_options' );
	}

	public function run_audit( WP_REST_Request $request ) {
		unset( $request );
		$result = RevIt_Publisher_Services::site_audit()->run( true );
		$status = ! empty( $result['success'] ) ? 200 : 409;
		return new WP_REST_Response( $result, $status );
	}

	public function list_audits( WP_REST_Request $request ) {
		return new WP_REST_Response( RevIt_Publisher_Services::site_audit()->list_snapshots( (int) ( $request->get_param( 'limit' ) ?? 30 ) ), 200 );
	}

	public function get_audit( WP_REST_Request $request ) {
		$snapshot = RevIt_Publisher_Services::site_audit()->get_snapshot( (int) $request['id'] );
		if ( null === $snapshot ) {
			return new WP_REST_Response( array( 'message' => 'Not found.' ), 404 );
		}
		return new WP_REST_Response( $snapshot, 200 );
	}

	public function list_issues( WP_REST_Request $request ) {
		return new WP_REST_Response(
			RevIt_Publisher_Services::issues()->list_issues(
				array(
					'status'   => (string) ( $request->get_param( 'status' ) ?? '' ),
					'severity' => (string) ( $request->get_param( 'severity' ) ?? '' ),
					'limit'    => (int) ( $request->get_param( 'limit' ) ?? 100 ),
				)
			),
			200
		);
	}

	public function update_issue( WP_REST_Request $request ) {
		$params = $request->get_json_params() ?: array();
		$result = RevIt_Publisher_Services::issues()->update_status( (int) $request['id'], sanitize_key( (string) ( $params['status'] ?? '' ) ) );
		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response( array( 'message' => $result->get_error_message() ), 400 );
		}
		return new WP_REST_Response( $result, 200 );
	}

	public function list_redirects( WP_REST_Request $request ) {
		unset( $request );
		return new WP_REST_Response( RevIt_Publisher_Services::redirects()->list_redirects(), 200 );
	}

	public function create_redirect( WP_REST_Request $request ) {
		$params = $request->get_json_params() ?: array();
		$result = RevIt_Publisher_Services::redirects()->create( $params );
		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response( array( 'message' => $result->get_error_message() ), 400 );
		}
		return new WP_REST_Response( $result, 201 );
	}

	public function update_redirect( WP_REST_Request $request ) {
		$params = $request->get_json_params() ?: array();
		$result = RevIt_Publisher_Services::redirects()->update( (int) $request['id'], $params );
		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response( array( 'message' => $result->get_error_message() ), 400 );
		}
		return new WP_REST_Response( $result, 200 );
	}

	public function disable_redirect( WP_REST_Request $request ) {
		RevIt_Publisher_Services::redirects()->disable( (int) $request['id'] );
		return new WP_REST_Response( array( 'success' => true ), 200 );
	}

	public function preview_consolidation( WP_REST_Request $request ) {
		$params = $request->get_json_params() ?: array();
		$result = RevIt_Publisher_Services::consolidation()->preview(
			(int) ( $params['source_post_id'] ?? 0 ),
			(int) ( $params['destination_post_id'] ?? 0 )
		);
		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response( array( 'message' => $result->get_error_message() ), 400 );
		}
		return new WP_REST_Response( $result, 200 );
	}

	public function apply_consolidation( WP_REST_Request $request ) {
		$params = $request->get_json_params() ?: array();
		$result = RevIt_Publisher_Services::consolidation()->apply(
			(int) ( $params['source_post_id'] ?? 0 ),
			(int) ( $params['destination_post_id'] ?? 0 ),
			sanitize_key( (string) ( $params['source_status'] ?? 'draft' ) )
		);
		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response( array( 'message' => $result->get_error_message() ), 400 );
		}
		return new WP_REST_Response( $result, 200 );
	}

	public function list_404s( WP_REST_Request $request ) {
		if ( ! RevIt_Publisher_Services::settings()->enable_404_monitor() ) {
			return new WP_REST_Response( array( 'enabled' => false, 'entries' => array() ), 200 );
		}
		return new WP_REST_Response(
			array(
				'enabled' => true,
				'entries' => RevIt_Publisher_Services::not_found_monitor()->list_entries( (int) ( $request->get_param( 'limit' ) ?? 100 ) ),
			),
			200
		);
	}

	public function ignore_404( WP_REST_Request $request ) {
		RevIt_Publisher_Services::not_found_monitor()->update_status( (int) $request['id'], RevIt_Publisher_404_Monitor::STATUS_IGNORED );
		return new WP_REST_Response( array( 'success' => true ), 200 );
	}

	public function resolve_404( WP_REST_Request $request ) {
		RevIt_Publisher_Services::not_found_monitor()->update_status( (int) $request['id'], RevIt_Publisher_404_Monitor::STATUS_RESOLVED );
		return new WP_REST_Response( array( 'success' => true ), 200 );
	}

	public function get_cluster_links( WP_REST_Request $request ) {
		$key = sanitize_text_field( (string) $request['cluster_key'] );
		return new WP_REST_Response(
			array(
				'coverage'    => RevIt_Publisher_Services::pillar_links()->get_cluster_coverage( $key ),
				'suggestions' => RevIt_Publisher_Services::pillar_links()->generate_suggestions( $key ),
			),
			200
		);
	}

	public function apply_cluster_links( WP_REST_Request $request ) {
		$params = $request->get_json_params() ?: array();
		$suggestions = is_array( $params['suggestions'] ?? null ) ? $params['suggestions'] : array();
		return new WP_REST_Response( RevIt_Publisher_Services::pillar_links()->apply_cluster_links( $suggestions ), 200 );
	}

	public function undo_link( WP_REST_Request $request ) {
		$result = RevIt_Publisher_Services::link_undo()->undo( (int) $request['id'] );
		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response( array( 'message' => $result->get_error_message() ), 400 );
		}
		return new WP_REST_Response( array( 'success' => true ), 200 );
	}

	public function list_vehicles( WP_REST_Request $request ) {
		unset( $request );
		return new WP_REST_Response( RevIt_Publisher_Services::vehicle_health()->get_all_vehicle_summaries(), 200 );
	}

	public function get_vehicle_detail( WP_REST_Request $request ) {
		$vehicle = sanitize_text_field( (string) ( $request->get_param( 'vehicle' ) ?? '' ) );
		$detail  = RevIt_Publisher_Services::vehicle_health()->get_vehicle_detail( $vehicle );
		if ( null === $detail ) {
			return new WP_REST_Response( array( 'message' => 'Vehicle not found.' ), 404 );
		}
		return new WP_REST_Response( $detail, 200 );
	}

	public function record_overlap_decision( WP_REST_Request $request ) {
		$params = $request->get_json_params() ?: array();
		$ok = RevIt_Publisher_Services::consolidation()->record_overlap_decision(
			(int) ( $params['post_id_a'] ?? 0 ),
			(int) ( $params['post_id_b'] ?? 0 ),
			sanitize_key( (string) ( $params['decision'] ?? '' ) )
		);
		return new WP_REST_Response( array( 'success' => $ok ), $ok ? 200 : 400 );
	}
}
