<?php
/**
 * Search Console REST API controller.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RevIt_Publisher_GSC_Rest_Controller {

	private const REST_NAMESPACE = 'revit-publisher/v1';

	public function register_routes(): void {
		$routes = array(
			array( '/search-console/status', WP_REST_Server::READABLE, 'get_status', 'can_edit' ),
			array( '/search-console/connect', WP_REST_Server::CREATABLE, 'connect', 'can_manage' ),
			array( '/search-console/disconnect', WP_REST_Server::CREATABLE, 'disconnect', 'can_manage' ),
			array( '/search-console/properties', WP_REST_Server::READABLE, 'get_properties', 'can_manage' ),
			array( '/search-console/property', WP_REST_Server::EDITABLE, 'set_property', 'can_manage' ),
			array( '/search-console/sync', WP_REST_Server::CREATABLE, 'sync', 'can_manage' ),
			array( '/search-console/summary', WP_REST_Server::READABLE, 'get_summary', 'can_edit' ),
			array( '/search-console/pages', WP_REST_Server::READABLE, 'get_pages', 'can_edit' ),
			array( '/search-console/opportunities', WP_REST_Server::READABLE, 'get_opportunities', 'can_edit' ),
			array( '/search-console/sitemaps', WP_REST_Server::READABLE, 'get_sitemaps', 'can_edit' ),
			array( '/search-console/sitemaps/submit', WP_REST_Server::CREATABLE, 'submit_sitemap', 'can_manage' ),
			array( '/search-console/vehicles', WP_REST_Server::READABLE, 'get_vehicles', 'can_edit' ),
			array( '/search-console/clusters', WP_REST_Server::READABLE, 'get_clusters', 'can_edit' ),
		);

		foreach ( $routes as $route ) {
			register_rest_route(
				self::REST_NAMESPACE,
				$route[0],
				array(
					'methods'             => $route[1],
					'callback'            => array( $this, $route[2] ),
					'permission_callback' => array( $this, $route[3] ),
				)
			);
		}

		register_rest_route(
			self::REST_NAMESPACE,
			'/search-console/posts/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_post_performance' ),
				'permission_callback' => array( $this, 'can_edit_post' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/search-console/posts/(?P<id>\d+)/queries',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_post_queries' ),
				'permission_callback' => array( $this, 'can_edit_post' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/search-console/posts/(?P<id>\d+)/inspect',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'inspect_post' ),
				'permission_callback' => array( $this, 'can_edit_post' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/search-console/posts/(?P<id>\d+)/refresh-export',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'refresh_export' ),
				'permission_callback' => array( $this, 'can_edit_post' ),
			)
		);
	}

	public function can_edit(): bool {
		return current_user_can( 'edit_posts' );
	}

	public function can_manage(): bool {
		return current_user_can( 'manage_options' );
	}

	public function can_edit_post( WP_REST_Request $request ): bool {
		return current_user_can( 'edit_post', (int) $request['id'] );
	}

	public function get_status( WP_REST_Request $request ): WP_REST_Response {
		unset( $request );
		return new WP_REST_Response( RevIt_Publisher_Services::gsc_auth()->get_status(), 200 );
	}

	public function connect( WP_REST_Request $request ): WP_REST_Response {
		$params = $request->get_json_params() ?: array();
		if ( ! empty( $params['use_fixture'] ) ) {
			RevIt_Publisher_Services::gsc_auth()->connect_fixture();
			return new WP_REST_Response( RevIt_Publisher_Services::gsc_auth()->get_status(), 200 );
		}
		$url = RevIt_Publisher_Services::gsc_auth()->get_oauth_url();
		if ( '' === $url ) {
			return new WP_REST_Response( array( 'message' => __( 'Configure Google OAuth credentials first.', 'revit-publisher' ) ), 400 );
		}
		return new WP_REST_Response( array( 'oauth_url' => $url ), 200 );
	}

	public function disconnect( WP_REST_Request $request ): WP_REST_Response {
		unset( $request );
		RevIt_Publisher_Services::gsc_auth()->disconnect();
		return new WP_REST_Response( array( 'success' => true ), 200 );
	}

	public function get_properties( WP_REST_Request $request ): WP_REST_Response {
		unset( $request );
		try {
			$sites = RevIt_Publisher_Services::gsc_client()->list_sites();
			return new WP_REST_Response( $sites, 200 );
		} catch ( Throwable $e ) {
			return new WP_REST_Response( array( 'message' => sanitize_text_field( $e->getMessage() ) ), 500 );
		}
	}

	public function set_property( WP_REST_Request $request ): WP_REST_Response {
		$params   = $request->get_json_params() ?: array();
		$property = sanitize_text_field( (string) ( $params['property'] ?? '' ) );
		if ( '' === $property ) {
			return new WP_REST_Response( array( 'message' => __( 'Property is required.', 'revit-publisher' ) ), 400 );
		}
		update_option( RevIt_Publisher_Settings::GSC_PROPERTY, $property );
		return new WP_REST_Response( RevIt_Publisher_Services::gsc_auth()->get_status(), 200 );
	}

	public function sync( WP_REST_Request $request ): WP_REST_Response {
		unset( $request );
		$result = RevIt_Publisher_Services::gsc_sync()->sync( true );
		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response( array( 'message' => $result->get_error_message() ), 409 );
		}
		return new WP_REST_Response( $result, 200 );
	}

	public function get_summary( WP_REST_Request $request ): WP_REST_Response {
		$window = sanitize_key( (string) ( $request->get_param( 'window' ) ?? '28d' ) );
		return new WP_REST_Response( RevIt_Publisher_Services::gsc_insights()->get_summary_with_comparison( $window ), 200 );
	}

	public function get_pages( WP_REST_Request $request ): WP_REST_Response {
		$window = sanitize_key( (string) ( $request->get_param( 'window' ) ?? '28d' ) );
		$filters = array(
			'vehicle'      => sanitize_text_field( (string) ( $request->get_param( 'vehicle' ) ?? '' ) ),
			'article_type' => sanitize_key( (string) ( $request->get_param( 'article_type' ) ?? '' ) ),
		);
		$pages = RevIt_Publisher_Services::gsc_data_store()->get_top_pages( $window, 50, array_filter( $filters ) );
		foreach ( $pages as &$page ) {
			$post_id = (int) ( $page['post_id'] ?? 0 );
			if ( $post_id > 0 ) {
				$page['title'] = get_the_title( $post_id );
			}
		}
		return new WP_REST_Response( $pages, 200 );
	}

	public function get_post_performance( WP_REST_Request $request ): WP_REST_Response {
		$window = sanitize_key( (string) ( $request->get_param( 'window' ) ?? '28d' ) );
		return new WP_REST_Response(
			RevIt_Publisher_Services::gsc_insights()->get_post_performance( (int) $request['id'], $window ),
			200
		);
	}

	public function get_post_queries( WP_REST_Request $request ): WP_REST_Response {
		$window = sanitize_key( (string) ( $request->get_param( 'window' ) ?? '28d' ) );
		return new WP_REST_Response(
			RevIt_Publisher_Services::gsc_data_store()->get_post_queries( (int) $request['id'], $window, 20 ),
			200
		);
	}

	public function inspect_post( WP_REST_Request $request ): WP_REST_Response {
		$result = RevIt_Publisher_Services::gsc_inspections()->inspect_post( (int) $request['id'] );
		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response( array( 'message' => $result->get_error_message() ), 400 );
		}
		return new WP_REST_Response( $result, 200 );
	}

	public function get_opportunities( WP_REST_Request $request ): WP_REST_Response {
		$window = sanitize_key( (string) ( $request->get_param( 'window' ) ?? '28d' ) );
		return new WP_REST_Response( RevIt_Publisher_Services::gsc_opportunities()->list_opportunities( $window ), 200 );
	}

	public function get_sitemaps( WP_REST_Request $request ): WP_REST_Response {
		unset( $request );
		return new WP_REST_Response(
			array(
				'sitemaps'   => RevIt_Publisher_Services::gsc_sitemaps()->list_sitemaps(),
				'submitted'  => RevIt_Publisher_Services::gsc_sitemaps()->is_wordpress_sitemap_submitted(),
			),
			200
		);
	}

	public function submit_sitemap( WP_REST_Request $request ): WP_REST_Response {
		unset( $request );
		$result = RevIt_Publisher_Services::gsc_sitemaps()->submit_wordpress_sitemap();
		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response( array( 'message' => $result->get_error_message() ), 400 );
		}
		return new WP_REST_Response( $result, 200 );
	}

	public function get_vehicles( WP_REST_Request $request ): WP_REST_Response {
		$window = sanitize_key( (string) ( $request->get_param( 'window' ) ?? '28d' ) );
		return new WP_REST_Response( RevIt_Publisher_Services::gsc_insights()->get_vehicle_performance( $window ), 200 );
	}

	public function get_clusters( WP_REST_Request $request ): WP_REST_Response {
		$window = sanitize_key( (string) ( $request->get_param( 'window' ) ?? '28d' ) );
		return new WP_REST_Response( RevIt_Publisher_Services::gsc_insights()->get_cluster_performance( $window ), 200 );
	}

	public function refresh_export( WP_REST_Request $request ): WP_REST_Response {
		$reason = sanitize_key( (string) ( $request->get_param( 'reason' ) ?? 'page2_opportunity' ) );
		$result = RevIt_Publisher_Services::gsc_refresh_export()->export_for_post( (int) $request['id'], $reason );
		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response( array( 'message' => $result->get_error_message() ), 400 );
		}
		return new WP_REST_Response( $result, 200 );
	}
}
