<?php
/**
 * Vehicle taxonomy synchronization.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Syncs vehicle package data to taxonomies and post meta.
 */
class RevIt_Publisher_Vehicle_Taxonomy_Service {

	/**
	 * Assign vehicle taxonomies and store post meta.
	 *
	 * @param int    $post_id Post ID.
	 * @param object $vehicle Vehicle object from package.
	 * @return true|WP_Error
	 */
	public function sync_post( int $post_id, object $vehicle ) {
		$manufacturer = $this->nullable_string( $vehicle->manufacturer ?? null );
		$model        = $this->nullable_string( $vehicle->model ?? null );
		$generation   = $this->nullable_string( $vehicle->generation ?? null );
		$trim         = $this->nullable_string( $vehicle->trim ?? null );
		$engines      = isset( $vehicle->engines ) && is_array( $vehicle->engines )
			? array_values( array_filter( array_map( 'strval', $vehicle->engines ) ) )
			: array();

		update_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::VEHICLE_MANUFACTURER, $manufacturer ?? '' );
		update_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::VEHICLE_MODEL, $model ?? '' );
		update_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::VEHICLE_GENERATION, $generation ?? '' );
		update_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::VEHICLE_TRIM, $trim ?? '' );
		update_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::VEHICLE_START_YEAR, $vehicle->start_year ?? '' );
		update_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::VEHICLE_END_YEAR, $vehicle->end_year ?? '' );
		update_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::VEHICLE_ENGINES, $engines );

		$term_ids = array();

		if ( null !== $manufacturer ) {
			$manufacturer_term = $this->ensure_term(
				RevIt_Publisher_Taxonomies::MANUFACTURER,
				$this->slug_part( $manufacturer ),
				$manufacturer,
				0
			);
			if ( is_wp_error( $manufacturer_term ) ) {
				return $manufacturer_term;
			}
			$term_ids[ RevIt_Publisher_Taxonomies::MANUFACTURER ] = array( (int) $manufacturer_term['term_id'] );
			$manufacturer_term_id                               = (int) $manufacturer_term['term_id'];
		} else {
			$manufacturer_term_id = 0;
		}

		if ( null !== $manufacturer && null !== $model ) {
			$model_slug = $this->vehicle_slug( $manufacturer, $model );
			$model_term = $this->ensure_term(
				RevIt_Publisher_Taxonomies::MODEL,
				$model_slug,
				$model,
				$manufacturer_term_id
			);
			if ( is_wp_error( $model_term ) ) {
				return $model_term;
			}
			$term_ids[ RevIt_Publisher_Taxonomies::MODEL ] = array( (int) $model_term['term_id'] );
			$model_term_id                                 = (int) $model_term['term_id'];
		} else {
			$model_term_id = 0;
		}

		if ( null !== $manufacturer && null !== $model && null !== $generation ) {
			$generation_slug = $this->vehicle_slug( $manufacturer, $model, $generation );
			$generation_term = $this->ensure_term(
				RevIt_Publisher_Taxonomies::GENERATION,
				$generation_slug,
				$generation,
				$model_term_id
			);
			if ( is_wp_error( $generation_term ) ) {
				return $generation_term;
			}
			$term_ids[ RevIt_Publisher_Taxonomies::GENERATION ] = array( (int) $generation_term['term_id'] );
			$generation_term_id                                 = (int) $generation_term['term_id'];
		} else {
			$generation_term_id = 0;
		}

		if ( null !== $manufacturer && null !== $model && null !== $generation && null !== $trim ) {
			$trim_slug = $this->vehicle_slug( $manufacturer, $model, $generation, $trim );
			$trim_term = $this->ensure_term(
				RevIt_Publisher_Taxonomies::TRIM,
				$trim_slug,
				$trim,
				$generation_term_id
			);
			if ( is_wp_error( $trim_term ) ) {
				return $trim_term;
			}
			$term_ids[ RevIt_Publisher_Taxonomies::TRIM ] = array( (int) $trim_term['term_id'] );
		}

		if ( ! empty( $engines ) ) {
			$engine_term_ids = array();
			foreach ( $engines as $engine ) {
				$engine_slug = $this->slug_part( $engine );
				$engine_term = $this->ensure_term(
					RevIt_Publisher_Taxonomies::ENGINE,
					$engine_slug,
					$engine,
					0
				);
				if ( is_wp_error( $engine_term ) ) {
					return $engine_term;
				}
				$engine_term_ids[] = (int) $engine_term['term_id'];
			}
			$term_ids[ RevIt_Publisher_Taxonomies::ENGINE ] = $engine_term_ids;
		}

		foreach ( $term_ids as $taxonomy => $ids ) {
			$result = wp_set_object_terms( $post_id, $ids, $taxonomy, false );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		return true;
	}

	/**
	 * Count distinct model taxonomy terms.
	 */
	public function count_models(): int {
		return $this->count_terms( RevIt_Publisher_Taxonomies::MODEL );
	}

	/**
	 * Build human-readable vehicle label.
	 */
	public function format_vehicle_label( object $vehicle ): string {
		$parts = array_filter(
			array(
				$this->nullable_string( $vehicle->manufacturer ?? null ),
				$this->nullable_string( $vehicle->model ?? null ),
				$this->nullable_string( $vehicle->generation ?? null ),
				$this->nullable_string( $vehicle->trim ?? null ),
			)
		);

		return implode( ' ', $parts );
	}

	/**
	 * Find or create a taxonomy term by deterministic identity slug.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	private function ensure_term( string $taxonomy, string $identity_slug, string $name, int $parent = 0 ) {
		$existing = $this->find_term_by_identity_slug( $taxonomy, $identity_slug );
		if ( null !== $existing ) {
			return array(
				'term_id' => $existing,
				'term_taxonomy_id' => $existing,
			);
		}

		$result = wp_insert_term(
			$name,
			$taxonomy,
			array(
				'slug'   => $identity_slug,
				'parent' => $parent,
			)
		);

		if ( is_wp_error( $result ) ) {
			if ( 'term_exists' === $result->get_error_code() ) {
				$term_id = (int) $result->get_error_data();
				update_term_meta( $term_id, RevIt_Publisher_Taxonomies::TERM_IDENTITY_SLUG, $identity_slug );
				return array(
					'term_id' => $term_id,
					'term_taxonomy_id' => $term_id,
				);
			}
			return $result;
		}

		update_term_meta( (int) $result['term_id'], RevIt_Publisher_Taxonomies::TERM_IDENTITY_SLUG, $identity_slug );

		return $result;
	}

	/**
	 * Find term by stored identity slug meta.
	 */
	private function find_term_by_identity_slug( string $taxonomy, string $identity_slug ): ?int {
		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => RevIt_Publisher_Taxonomies::TERM_IDENTITY_SLUG,
						'value' => $identity_slug,
					),
				),
				'number'     => 1,
			)
		);

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			$term = get_term_by( 'slug', $identity_slug, $taxonomy );
			return $term instanceof WP_Term ? (int) $term->term_id : null;
		}

		return (int) $terms[0]->term_id;
	}

	/**
	 * Build deterministic vehicle slug segments.
	 */
	private function vehicle_slug( string ...$parts ): string {
		return implode(
			'-',
			array_map(
				fn( string $part ): string => $this->slug_part( $part ),
				$parts
			)
		);
	}

	/**
	 * Normalize a slug segment.
	 */
	private function slug_part( string $value ): string {
		return sanitize_title( $value );
	}

	/**
	 * Normalize nullable string.
	 */
	private function nullable_string( mixed $value ): ?string {
		if ( null === $value || '' === $value ) {
			return null;
		}

		return sanitize_text_field( (string) $value );
	}

	/**
	 * Count taxonomy terms.
	 */
	private function count_terms( string $taxonomy ): int {
		$count = wp_count_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
			)
		);

		return is_wp_error( $count ) ? 0 : (int) $count;
	}
}
