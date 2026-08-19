<?php
/**
 * System health REST API controller.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RevIt_Publisher_System_Health_Rest_Controller {

	private const REST_NAMESPACE = 'revit-publisher/v1';

	public function register_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			'/system-health',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_health' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/system-health/run',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'run_checks' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);
	}

	public function can_manage(): bool {
		return current_user_can( 'manage_options' );
	}

	public function get_health(): WP_REST_Response {
		return new WP_REST_Response( RevIt_Publisher_Services::system_health()->get_diagnostics(), 200 );
	}

	public function run_checks(): WP_REST_Response {
		return new WP_REST_Response(
			array(
				'checks' => RevIt_Publisher_Services::system_health()->run_checks(),
				'profiler' => RevIt_Publisher_Services::profiler()->get_recent( 10 ),
			),
			200
		);
	}
}
