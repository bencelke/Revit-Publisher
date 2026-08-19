<?php
/**
 * SEO graph and linking REST controller.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST endpoints for content graph, linking, health, and settings.
 */
class RevIt_Publisher_SEO_Graph_Rest_Controller {

	public const REST_NAMESPACE = 'revit-publisher/v1';

	/**
	 * Register routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			'/seo-health',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_seo_health' ),
				'permission_callback' => array( $this, 'can_edit_posts' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/content-graph/vehicles',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_vehicles' ),
				'permission_callback' => array( $this, 'can_edit_posts' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/content-graph/clusters',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_clusters' ),
				'permission_callback' => array( $this, 'can_edit_posts' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/content-graph/orphans',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_orphans' ),
				'permission_callback' => array( $this, 'can_edit_posts' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/content-graph/link-opportunities',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_link_opportunities' ),
				'permission_callback' => array( $this, 'can_edit_posts' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/link-audit',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_link_audit' ),
					'permission_callback' => array( $this, 'can_edit_posts' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'run_link_audit' ),
					'permission_callback' => array( $this, 'can_edit_posts' ),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/settings',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_settings' ),
					'permission_callback' => array( $this, 'can_manage_options' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_settings' ),
					'permission_callback' => array( $this, 'can_manage_options' ),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/posts/(?P<id>\d+)/link-suggestions',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_link_suggestions' ),
				'permission_callback' => array( $this, 'can_edit_post' ),
				'args'                => array(
					'id' => array(
						'validate_callback' => static fn( $value ): bool => is_numeric( $value ),
					),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/posts/(?P<id>\d+)/apply-link',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'apply_link' ),
				'permission_callback' => array( $this, 'can_edit_post' ),
				'args'                => array(
					'id' => array(
						'validate_callback' => static fn( $value ): bool => is_numeric( $value ),
					),
				),
			)
		);
	}

	/**
	 * @param WP_REST_Request $request Request.
	 */
	public function can_edit_posts( WP_REST_Request $request ): bool {
		unset( $request );
		return current_user_can( 'edit_posts' );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 */
	public function can_manage_options( WP_REST_Request $request ): bool {
		unset( $request );
		return current_user_can( 'manage_options' );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 */
	public function can_edit_post( WP_REST_Request $request ): bool {
		$post_id = (int) $request->get_param( 'id' );
		return current_user_can( 'edit_post', $post_id );
	}

	/**
	 * SEO health summary.
	 */
	public function get_seo_health( WP_REST_Request $request ) {
		unset( $request );
		$health = RevIt_Publisher_Services::health_service()->get_summary();
		$audit  = RevIt_Publisher_Services::audit_service()->get_audit();

		return new WP_REST_Response(
			array(
				'seo_health'    => $health,
				'content_graph' => array(
					'vehicles'       => count( RevIt_Publisher_Services::graph()->get_vehicle_summaries() ),
					'clusters'       => count( RevIt_Publisher_Services::graph()->get_cluster_summaries() ),
					'resolved_links' => (int) ( $audit['resolved'] ?? 0 ),
					'pending_links'  => (int) ( $audit['unresolved'] ?? 0 ),
				),
			),
			200
		);
	}

	/**
	 * Vehicle graph summaries.
	 */
	public function get_vehicles( WP_REST_Request $request ) {
		unset( $request );
		return new WP_REST_Response( RevIt_Publisher_Services::graph()->get_vehicle_summaries(), 200 );
	}

	/**
	 * Cluster graph summaries.
	 */
	public function get_clusters( WP_REST_Request $request ) {
		unset( $request );
		return new WP_REST_Response( RevIt_Publisher_Services::graph()->get_cluster_summaries(), 200 );
	}

	/**
	 * Orphan articles.
	 */
	public function get_orphans( WP_REST_Request $request ) {
		unset( $request );
		return new WP_REST_Response( RevIt_Publisher_Services::health_service()->get_orphans(), 200 );
	}

	/**
	 * Link opportunities across site.
	 */
	public function get_link_opportunities( WP_REST_Request $request ) {
		unset( $request );
		$opportunities = array();
		$posts = get_posts(
			array(
				'post_type'      => 'post',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => RevIt_Publisher_Post_Meta_Keys::MANAGED,
						'value' => '1',
					),
				),
			)
		);

		foreach ( $posts as $post_id ) {
			foreach ( RevIt_Publisher_Services::link_service()->get_suggestions( (int) $post_id ) as $suggestion ) {
				$opportunities[] = array_merge(
					$suggestion,
					array(
						'source_post_id' => (int) $post_id,
						'source_title'   => get_the_title( (int) $post_id ),
					)
				);
			}
		}

		return new WP_REST_Response( $opportunities, 200 );
	}

	/**
	 * Cached link audit.
	 */
	public function get_link_audit( WP_REST_Request $request ) {
		unset( $request );
		return new WP_REST_Response( RevIt_Publisher_Services::audit_service()->get_audit(), 200 );
	}

	/**
	 * Run fresh link audit.
	 */
	public function run_link_audit( WP_REST_Request $request ) {
		unset( $request );
		return new WP_REST_Response( RevIt_Publisher_Services::audit_service()->audit_all_links(), 200 );
	}

	/**
	 * Get plugin settings.
	 */
	public function get_settings( WP_REST_Request $request ) {
		unset( $request );
		return new WP_REST_Response( RevIt_Publisher_Services::settings()->all(), 200 );
	}

	/**
	 * Update settings from REST (admin UI).
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public function update_settings( WP_REST_Request $request ) {
		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			return new WP_REST_Response( array( 'message' => 'Invalid JSON.' ), 400 );
		}

		$map = array(
			'enable_meta_description' => RevIt_Publisher_Settings::ENABLE_META_DESCRIPTION,
			'enable_canonical'        => RevIt_Publisher_Settings::ENABLE_CANONICAL,
			'enable_robots'           => RevIt_Publisher_Settings::ENABLE_ROBOTS,
			'enable_article_schema'   => RevIt_Publisher_Settings::ENABLE_ARTICLE_SCHEMA,
			'enable_breadcrumb_schema'=> RevIt_Publisher_Settings::ENABLE_BREADCRUMB_SCHEMA,
			'max_suggested_links'     => RevIt_Publisher_Settings::MAX_SUGGESTED_LINKS,
			'avoid_duplicate_target'  => RevIt_Publisher_Settings::AVOID_DUPLICATE_TARGET,
			'org_name'                => RevIt_Publisher_Settings::ORG_NAME,
			'org_logo_url'            => RevIt_Publisher_Settings::ORG_LOGO_URL,
		);

		foreach ( $map as $key => $option ) {
			if ( array_key_exists( $key, $params ) ) {
				update_option( $option, $params[ $key ] );
			}
		}

		return new WP_REST_Response( RevIt_Publisher_Services::settings()->all(), 200 );
	}

	/**
	 * Link suggestions for one post.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public function get_link_suggestions( WP_REST_Request $request ) {
		$post_id = (int) $request->get_param( 'id' );

		return new WP_REST_Response(
			array(
				'post_id'     => $post_id,
				'suggestions' => RevIt_Publisher_Services::link_service()->get_suggestions( $post_id ),
				'backlinks'   => RevIt_Publisher_Services::link_service()->get_backlink_opportunities( $post_id ),
			),
			200
		);
	}

	/**
	 * Apply approved link.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public function apply_link( WP_REST_Request $request ) {
		$post_id    = (int) $request->get_param( 'id' );
		$suggestion = $request->get_json_params();

		if ( ! is_array( $suggestion ) ) {
			return new WP_REST_Response( array( 'success' => false, 'message' => 'Invalid JSON.' ), 400 );
		}

		$result = RevIt_Publisher_Services::link_service()->apply_link( $post_id, $suggestion );
		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => $result->get_error_message(),
				),
				(int) ( $result->get_error_data()['status'] ?? 400 )
			);
		}

		return new WP_REST_Response( array( 'success' => true ), 200 );
	}
}
