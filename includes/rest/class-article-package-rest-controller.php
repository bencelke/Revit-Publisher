<?php
/**
 * REST API controller for article package validation.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Exposes validation-only endpoints for article packages.
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
	 * Constructor.
	 *
	 * @param RevIt_Publisher_Article_Package_Validator $validator Validator instance.
	 */
	public function __construct( RevIt_Publisher_Article_Package_Validator $validator ) {
		$this->validator = $validator;
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
				'args'                => array(),
			)
		);
	}

	/**
	 * Permission check for validation endpoint.
	 *
	 * @param WP_REST_Request $request Request object.
	 */
	public function permissions_check( WP_REST_Request $request ): bool {
		unset( $request );

		return current_user_can( 'edit_posts' );
	}

	/**
	 * Validate submitted article package JSON.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function validate_package( WP_REST_Request $request ) {
		$params = $request->get_json_params();

		if ( null === $params ) {
			return new WP_REST_Response(
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
			);
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
}
