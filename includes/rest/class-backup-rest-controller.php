<?php
/**
 * Backup REST API controller.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RevIt_Publisher_Backup_Rest_Controller {

	private const REST_NAMESPACE = 'revit-publisher/v1';

	public function register_routes(): void {
		$routes = array(
			array( '/backups/export', 'export', WP_REST_Server::CREATABLE ),
			array( '/backups/validate', 'validate', WP_REST_Server::CREATABLE ),
			array( '/backups/import-preview', 'import_preview', WP_REST_Server::CREATABLE ),
			array( '/backups/import', 'import', WP_REST_Server::CREATABLE ),
		);
		foreach ( $routes as $route ) {
			register_rest_route(
				self::REST_NAMESPACE,
				$route[0],
				array(
					'methods'             => $route[2],
					'callback'            => array( $this, $route[1] ),
					'permission_callback' => array( $this, 'can_manage' ),
				)
			);
		}
	}

	public function can_manage(): bool {
		return current_user_can( 'manage_options' );
	}

	public function export( WP_REST_Request $request ): WP_REST_Response {
		$params = (array) $request->get_json_params();
		$sections = (array) ( $params['sections'] ?? array() );
		return new WP_REST_Response( RevIt_Publisher_Services::backup()->export( $sections ), 200 );
	}

	public function validate( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$data = (array) $request->get_json_params();
		$result = RevIt_Publisher_Services::backup()->validate( $data );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return new WP_REST_Response( array( 'valid' => true ), 200 );
	}

	public function import_preview( WP_REST_Request $request ): WP_REST_Response {
		return new WP_REST_Response(
			RevIt_Publisher_Services::backup()->import_preview( (array) $request->get_json_params() ),
			200
		);
	}

	public function import( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$result = RevIt_Publisher_Services::backup()->import_safe( (array) $request->get_json_params() );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return new WP_REST_Response( $result, 200 );
	}
}
