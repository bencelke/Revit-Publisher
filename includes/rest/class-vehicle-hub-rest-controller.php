<?php
/**
 * Vehicle hub REST API controller.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST endpoints for vehicle hubs, health, sitemap, and cluster link matrix.
 */
class RevIt_Publisher_Vehicle_Hub_Rest_Controller {

	private const REST_NAMESPACE = 'revit-publisher/v1';

	private RevIt_Publisher_Vehicle_Hub_Service $hubs;

	public function __construct( RevIt_Publisher_Vehicle_Hub_Service $hubs ) {
		$this->hubs = $hubs;
	}

	public function register_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			'/vehicle-hubs/preview',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_create_preview' ),
				'permission_callback' => array( $this, 'can_edit' ),
				'args'                => array(
					'vehicle' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/serp-preview',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_serp_preview' ),
				'permission_callback' => array( $this, 'can_edit' ),
				'args'                => array(
					'post_id'   => array(
						'required' => true,
						'type'     => 'integer',
					),
					'post_type' => array(
						'type'              => 'string',
						'default'           => 'article',
						'sanitize_callback' => 'sanitize_key',
					),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/vehicle-hubs',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_hubs' ),
					'permission_callback' => array( $this, 'can_edit' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_hub' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/vehicle-hubs/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_hub' ),
					'permission_callback' => array( $this, 'can_edit' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_hub' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/vehicle-hubs/(?P<id>\d+)/health',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_hub_health' ),
				'permission_callback' => array( $this, 'can_edit' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/sitemap-health',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_sitemap_health' ),
				'permission_callback' => array( $this, 'can_edit' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/clusters/(?P<id>\d+)/link-matrix',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_cluster_link_matrix' ),
				'permission_callback' => array( $this, 'can_edit' ),
			)
		);
	}

	public function can_edit(): bool {
		return current_user_can( 'edit_posts' );
	}

	public function can_manage(): bool {
		return current_user_can( 'manage_options' );
	}

	public function list_hubs( WP_REST_Request $request ): WP_REST_Response {
		unset( $request );
		$hubs = array();
		foreach ( $this->hubs->list_published_hubs() as $hub ) {
			$hubs[] = $this->format_hub_summary( (int) ( $hub['hub_id'] ?? 0 ) );
		}

		$drafts = get_posts(
			array(
				'post_type'      => RevIt_Publisher_Vehicle_Hub_Post_Type::POST_TYPE,
				'post_status'    => array( 'draft', 'pending', 'private' ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);
		foreach ( $drafts as $hub_id ) {
			$hubs[] = $this->format_hub_summary( (int) $hub_id );
		}

		return new WP_REST_Response( $hubs, 200 );
	}

	public function get_create_preview( WP_REST_Request $request ): WP_REST_Response {
		$label = sanitize_text_field( (string) $request->get_param( 'vehicle' ) );
		if ( '' === $label ) {
			return new WP_REST_Response( array( 'message' => __( 'vehicle label is required.', 'revit-publisher' ) ), 400 );
		}

		$identity = $this->hubs->identity_from_label( $label );
		if ( null === $identity ) {
			return new WP_REST_Response( array( 'message' => __( 'No articles found for vehicle.', 'revit-publisher' ) ), 404 );
		}

		$vehicle_key = (string) ( $identity['vehicle_key'] ?? '' );
		$existing    = $this->hubs->find_by_key( $vehicle_key );
		$published   = 0;
		foreach ( $this->hubs->get_article_ids_for_hub( $existing ?? 0, true ) as $id ) {
			++$published;
		}
		if ( null === $existing ) {
			$published = count(
				array_filter(
					RevIt_Publisher_Services::registry()->get_managed_post_ids(),
					static function ( int $post_id ) use ( $vehicle_key ): bool {
						return RevIt_Publisher_Vehicle_Identity::from_post( $post_id ) === $vehicle_key
							&& 'publish' === get_post_status( $post_id );
					}
				)
			);
		}

		$clusters = array();
		foreach ( RevIt_Publisher_Services::graph()->get_cluster_summaries() as $cluster ) {
			$clusters[] = $cluster;
		}

		return new WP_REST_Response(
			array(
				'vehicle_label' => $label,
				'identity'      => $identity,
				'years'         => $this->format_years( $identity ),
				'engines'       => (array) ( $identity['engines'] ?? array() ),
				'published'     => $published,
				'clusters'      => count( array_unique( wp_list_pluck( $clusters, 'name' ) ) ),
				'hub_exists'    => null !== $existing,
				'hub_id'        => $existing,
			),
			200
		);
	}

	public function get_serp_preview( WP_REST_Request $request ): WP_REST_Response {
		$post_id   = (int) $request->get_param( 'post_id' );
		$post_type = sanitize_key( (string) $request->get_param( 'post_type' ) );
		if ( 'hub' === $post_type ) {
			return new WP_REST_Response( RevIt_Publisher_Services::serp_preview()->preview_hub( $post_id ), 200 );
		}
		return new WP_REST_Response( RevIt_Publisher_Services::serp_preview()->preview_article( $post_id ), 200 );
	}

	public function create_hub( WP_REST_Request $request ): WP_REST_Response {
		$params        = $request->get_json_params() ?: array();
		$vehicle_label = sanitize_text_field( (string) ( $params['vehicle_label'] ?? '' ) );
		$vehicle_key   = sanitize_text_field( (string) ( $params['vehicle_key'] ?? '' ) );
		$identity      = is_array( $params['identity'] ?? null ) ? $params['identity'] : array();

		if ( '' !== $vehicle_label && empty( $identity ) ) {
			$from_label = $this->hubs->identity_from_label( $vehicle_label );
			if ( null === $from_label ) {
				return new WP_REST_Response( array( 'message' => __( 'No articles found for vehicle.', 'revit-publisher' ) ), 404 );
			}
			$identity    = $from_label;
			$vehicle_key = (string) ( $from_label['vehicle_key'] ?? '' );
		}

		if ( '' === $vehicle_key && ! empty( $identity ) ) {
			$vehicle_key = RevIt_Publisher_Vehicle_Identity::build_key(
				(string) ( $identity['manufacturer'] ?? '' ),
				(string) ( $identity['model'] ?? '' ),
				(string) ( $identity['generation'] ?? '' ),
				(string) ( $identity['trim'] ?? '' )
			);
		}

		if ( '' === $vehicle_key ) {
			return new WP_REST_Response( array( 'message' => __( 'vehicle_key or identity is required.', 'revit-publisher' ) ), 400 );
		}

		$result = $this->hubs->create_draft( $vehicle_key, $identity );
		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response(
				array(
					'message' => $result->get_error_message(),
					'data'    => $result->get_error_data(),
				),
				409
			);
		}

		return new WP_REST_Response( $this->format_hub( (int) $result ), 201 );
	}

	public function get_hub( WP_REST_Request $request ): WP_REST_Response {
		$hub = $this->format_hub( (int) $request['id'] );
		if ( null === $hub ) {
			return new WP_REST_Response( array( 'message' => __( 'Hub not found.', 'revit-publisher' ) ), 404 );
		}
		return new WP_REST_Response( $hub, 200 );
	}

	public function update_hub( WP_REST_Request $request ): WP_REST_Response {
		$hub_id = (int) $request['id'];
		$post   = get_post( $hub_id );
		if ( ! $post instanceof WP_Post || RevIt_Publisher_Vehicle_Hub_Post_Type::POST_TYPE !== $post->post_type ) {
			return new WP_REST_Response( array( 'message' => __( 'Hub not found.', 'revit-publisher' ) ), 404 );
		}

		$params = $request->get_json_params() ?: array();
		$update = array( 'ID' => $hub_id );

		if ( isset( $params['title'] ) ) {
			$update['post_title'] = sanitize_text_field( (string) $params['title'] );
		}
		if ( isset( $params['status'] ) ) {
			$status = sanitize_key( (string) $params['status'] );
			if ( in_array( $status, array( 'draft', 'publish', 'pending', 'private' ), true ) ) {
				$update['post_status'] = $status;
			}
		}
		if ( isset( $params['intro'] ) ) {
			update_post_meta( $hub_id, RevIt_Publisher_Vehicle_Hub_Meta_Keys::INTRO, wp_kses_post( (string) $params['intro'] ) );
		}
		if ( is_array( $params['identity'] ?? null ) ) {
			$this->hubs->save_identity_meta(
				$hub_id,
				$this->hubs->get_vehicle_key( $hub_id ) ?: RevIt_Publisher_Vehicle_Identity::build_key(
					(string) ( $params['identity']['manufacturer'] ?? '' ),
					(string) ( $params['identity']['model'] ?? '' ),
					(string) ( $params['identity']['generation'] ?? '' ),
					(string) ( $params['identity']['trim'] ?? '' )
				),
				$params['identity']
			);
		}

		wp_update_post( $update );
		RevIt_Publisher_Services::hub_cache()->invalidate_hub( $hub_id );

		$hub = $this->format_hub( $hub_id );
		return new WP_REST_Response( $hub, 200 );
	}

	public function get_hub_health( WP_REST_Request $request ): WP_REST_Response {
		$health = RevIt_Publisher_Services::hub_seo_health()->evaluate( (int) $request['id'] );
		if ( empty( $health['valid'] ) ) {
			return new WP_REST_Response( array( 'message' => __( 'Hub not found.', 'revit-publisher' ) ), 404 );
		}
		return new WP_REST_Response( $health, 200 );
	}

	public function get_sitemap_health( WP_REST_Request $request ): WP_REST_Response {
		unset( $request );
		return new WP_REST_Response( RevIt_Publisher_Services::sitemap_health()->get_audit(), 200 );
	}

	public function get_cluster_link_matrix( WP_REST_Request $request ): WP_REST_Response {
		$matrix = RevIt_Publisher_Services::cluster_link_matrix()->build_for_term( (int) $request['id'] );
		if ( empty( $matrix['valid'] ) ) {
			return new WP_REST_Response( array( 'message' => __( 'Cluster not found.', 'revit-publisher' ) ), 404 );
		}
		return new WP_REST_Response( $matrix, 200 );
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private function format_hub( int $hub_id ): ?array {
		$post = get_post( $hub_id );
		if ( ! $post instanceof WP_Post || RevIt_Publisher_Vehicle_Hub_Post_Type::POST_TYPE !== $post->post_type ) {
			return null;
		}

		return array_merge(
			$this->format_hub_summary( $hub_id ),
			array(
				'intro'    => (string) get_post_meta( $hub_id, RevIt_Publisher_Vehicle_Hub_Meta_Keys::INTRO, true ),
				'identity' => $this->hubs->get_identity( $hub_id ),
				'sections' => $this->hubs->get_articles_by_section( $hub_id ),
				'clusters' => $this->hubs->get_clusters_for_hub( $hub_id ),
				'coverage' => $this->hubs->get_coverage( $hub_id ),
				'serp'     => RevIt_Publisher_Services::serp_preview()->preview_hub( $hub_id ),
			)
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	/**
	 * @param array<string, mixed> $identity
	 */
	private function format_years( array $identity ): string {
		$start = (string) ( $identity['start_year'] ?? '' );
		$end   = (string) ( $identity['end_year'] ?? '' );
		if ( '' === $start && '' === $end ) {
			return '';
		}
		if ( '' === $end || $start === $end ) {
			return $start;
		}
		return $start . '–' . $end;
	}

	private function format_hub_summary( int $hub_id ): array {
		$permalink = get_permalink( $hub_id );
		return array(
			'hub_id'      => $hub_id,
			'title'       => get_the_title( $hub_id ),
			'status'      => get_post_status( $hub_id ),
			'vehicle_key' => $this->hubs->get_vehicle_key( $hub_id ),
			'permalink'   => is_string( $permalink ) ? $permalink : '',
			'manufacturer'=> (string) get_post_meta( $hub_id, RevIt_Publisher_Vehicle_Hub_Meta_Keys::MANUFACTURER, true ),
		);
	}
}
