<?php
/**
 * Content plan import and registry.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Imports content plans into revit_content_plan CPT.
 */
class RevIt_Publisher_Content_Plan_Importer {

	private RevIt_Publisher_Content_Plan_Validator $validator;
	private RevIt_Publisher_Package_Hash $hash;

	public function __construct(
		RevIt_Publisher_Content_Plan_Validator $validator,
		RevIt_Publisher_Package_Hash $hash
	) {
		$this->validator = $validator;
		$this->hash      = $hash;
	}

	/**
	 * Preview reconciliation without importing.
	 *
	 * @return array<string, mixed>
	 */
	public function preview( mixed $data ): array {
		$validation = $this->validator->validate( $data );
		if ( ! $validation['valid'] ) {
			return array(
				'valid'  => false,
				'errors' => $validation['errors'],
			);
		}

		$plan    = $this->normalize_payload( $data );
		$service = new RevIt_Publisher_Content_Plan_Service( RevIt_Publisher_Services::registry() );

		return array(
			'valid'   => true,
			'plan_key'=> (string) $plan->plan_key,
			'vehicle' => RevIt_Publisher_Content_Plan_Service::format_vehicle_label( $plan->vehicle ),
			'summary' => $service->summarize_plan_data( $plan ),
		);
	}

	/**
	 * Import content plan.
	 *
	 * @return array<string, mixed>
	 */
	public function import( mixed $data ): array {
		$validation = $this->validator->validate( $data );
		if ( ! $validation['valid'] ) {
			return array(
				'success' => false,
				'status'  => 'validation_failed',
				'errors'  => $validation['errors'],
			);
		}

		$plan     = $this->normalize_payload( $data );
		$plan_key = (string) $plan->plan_key;
		$existing = $this->find_plan_post_id( $plan_key );

		$vehicle_label = RevIt_Publisher_Content_Plan_Service::format_vehicle_label( $plan->vehicle );
		$post_data     = array(
			'post_type'   => RevIt_Publisher_Content_Plan_Post_Type::POST_TYPE,
			'post_status' => 'private',
			'post_title'  => $vehicle_label . ' — ' . $plan_key,
		);

		if ( null !== $existing ) {
			$post_data['ID'] = $existing;
			$plan_id         = wp_update_post( wp_slash( $post_data ), true );
		} else {
			$plan_id = wp_insert_post( wp_slash( $post_data ), true );
		}

		if ( is_wp_error( $plan_id ) ) {
			return array(
				'success' => false,
				'status'  => 'import_failed',
				'errors'  => array(
					array(
						'path'    => '',
						'message' => $plan_id->get_error_message(),
					),
				),
			);
		}

		$plan_id = (int) $plan_id;
		$this->store_plan_meta( $plan_id, $plan );

		return array(
			'success'  => true,
			'status'   => null !== $existing ? 'updated' : 'created',
			'plan_id'  => $plan_id,
			'plan_key' => $plan_key,
			'edit_url' => get_edit_post_link( $plan_id, 'raw' ),
		);
	}

	/**
	 * Find existing plan post by plan_key.
	 */
	public function find_plan_post_id( string $plan_key ): ?int {
		$posts = get_posts(
			array(
				'post_type'      => RevIt_Publisher_Content_Plan_Post_Type::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => RevIt_Publisher_Content_Plan_Meta_Keys::PLAN_KEY,
						'value' => sanitize_text_field( $plan_key ),
					),
				),
			)
		);

		return ! empty( $posts ) ? (int) $posts[0] : null;
	}

	/**
	 * @return object
	 */
	private function normalize_payload( mixed $data ): object {
		return is_array( $data ) ? json_decode( wp_json_encode( $data ), false ) : $data;
	}

	private function store_plan_meta( int $plan_id, object $plan ): void {
		update_post_meta( $plan_id, RevIt_Publisher_Content_Plan_Meta_Keys::PLAN_KEY, sanitize_text_field( (string) $plan->plan_key ) );
		update_post_meta( $plan_id, RevIt_Publisher_Content_Plan_Meta_Keys::SCHEMA_VERSION, RevIt_Publisher_Content_Plan_Validator::SCHEMA_VERSION );
		update_post_meta( $plan_id, RevIt_Publisher_Content_Plan_Meta_Keys::VEHICLE, json_decode( wp_json_encode( $plan->vehicle ), true ) );
		update_post_meta( $plan_id, RevIt_Publisher_Content_Plan_Meta_Keys::PLAN_DATA, json_decode( wp_json_encode( $plan ), true ) );
		update_post_meta( $plan_id, RevIt_Publisher_Content_Plan_Meta_Keys::PACKAGE_HASH, $this->hash->compute( $plan ) );
		update_post_meta( $plan_id, RevIt_Publisher_Content_Plan_Meta_Keys::IMPORTED_AT, gmdate( 'c' ) );
	}
}
