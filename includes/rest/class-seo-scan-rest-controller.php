<?php
/**
 * REST: site SEO scan, article optimize, import batches.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Scan and optimize endpoints.
 */
class RevIt_Publisher_Seo_Scan_Rest_Controller {

	public const REST_NAMESPACE = 'revit-publisher/v1';

	public function register_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			'/import-batches',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_batches' ),
					'permission_callback' => array( $this, 'permissions_check' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'record_batch' ),
					'permission_callback' => array( $this, 'permissions_check' ),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/seo-scan/site',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_site_scan' ),
					'permission_callback' => array( $this, 'permissions_check' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'run_site_scan' ),
					'permission_callback' => array( $this, 'permissions_check' ),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/seo-scan/articles/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_article_scan' ),
				'permission_callback' => array( $this, 'permissions_check' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/seo-scan/articles/(?P<id>\d+)/apply-safe',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'apply_safe_fixes' ),
				'permission_callback' => array( $this, 'permissions_check' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/seo-scan/apply-link',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'apply_link' ),
				'permission_callback' => array( $this, 'permissions_check' ),
			)
		);
	}

	public function permissions_check( WP_REST_Request $request ): bool {
		unset( $request );

		return current_user_can( 'edit_posts' );
	}

	public function list_batches( WP_REST_Request $request ) {
		unset( $request );

		return new WP_REST_Response( RevIt_Publisher_Services::import_batches()->list_recent(), 200 );
	}

	public function record_batch( WP_REST_Request $request ) {
		$params = $request->get_json_params() ?: array();
		$summary = RevIt_Publisher_Services::import_batches()->record( is_array( $params ) ? $params : array() );

		return new WP_REST_Response( $summary, 201 );
	}

	public function get_site_scan( WP_REST_Request $request ) {
		unset( $request );

		return new WP_REST_Response( RevIt_Publisher_Services::site_seo_scan()->get_last_site_scan(), 200 );
	}

	public function run_site_scan( WP_REST_Request $request ) {
		unset( $request );

		return new WP_REST_Response( RevIt_Publisher_Services::site_seo_scan()->scan_site(), 200 );
	}

	public function get_article_scan( WP_REST_Request $request ) {
		$post_id = (int) $request->get_param( 'id' );
		if ( $post_id <= 0 || ! current_user_can( 'edit_post', $post_id ) ) {
			return new WP_REST_Response( array( 'message' => 'Invalid post.' ), 403 );
		}

		$result = RevIt_Publisher_Services::site_seo_scan()->optimize_article( $post_id );
		if ( empty( $result ) ) {
			return new WP_REST_Response( array( 'message' => 'Post not found.' ), 404 );
		}

		return new WP_REST_Response( $result, 200 );
	}

	public function apply_safe_fixes( WP_REST_Request $request ) {
		$post_id = (int) $request->get_param( 'id' );
		if ( $post_id <= 0 || ! current_user_can( 'edit_post', $post_id ) ) {
			return new WP_REST_Response( array( 'message' => 'Invalid post.' ), 403 );
		}

		$params = $request->get_json_params() ?: array();
		$codes  = is_array( $params['codes'] ?? null ) ? $params['codes'] : array();
		$result = RevIt_Publisher_Services::site_seo_scan()->fixes()->apply( $post_id, $codes );
		$result['optimize'] = RevIt_Publisher_Services::site_seo_scan()->optimize_article( $post_id );

		return new WP_REST_Response( $result, 200 );
	}

	public function apply_link( WP_REST_Request $request ) {
		$params    = $request->get_json_params() ?: array();
		$source_id = (int) ( $params['source_post_id'] ?? 0 );
		$target_id = (int) ( $params['target_post_id'] ?? 0 );
		$anchor    = sanitize_text_field( (string) ( $params['anchor'] ?? '' ) );

		if ( $source_id <= 0 || $target_id <= 0 || '' === $anchor || ! current_user_can( 'edit_post', $source_id ) ) {
			return new WP_REST_Response( array( 'message' => 'Invalid link request.' ), 400 );
		}

		$source = get_post( $source_id );
		$status_before = $source instanceof WP_Post ? $source->post_status : '';

		$location = RevIt_Publisher_Services::link_service()->find_anchor_location( $source_id, $anchor );
		$suggestion = array(
			'target_post_id' => $target_id,
			'anchor'         => $anchor,
			'relationship'   => sanitize_key( (string) ( $params['relationship'] ?? 'related' ) ),
			'block_index'    => is_array( $location ) ? (int) $location['block_index'] : -1,
		);

		$log_id = RevIt_Publisher_Services::link_service()->apply_link_logged( $source_id, $suggestion );
		if ( is_wp_error( $log_id ) ) {
			return new WP_REST_Response(
				array( 'success' => false, 'message' => $log_id->get_error_message() ),
				400
			);
		}

		$after = get_post( $source_id );

		return new WP_REST_Response(
			array(
				'success'     => true,
				'log_id'      => (int) $log_id,
				'post_status' => $after instanceof WP_Post ? $after->post_status : $status_before,
				'published'   => false,
			),
			200
		);
	}
}
