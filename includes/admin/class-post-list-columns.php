<?php
/**
 * Post list column enhancements for RevIt articles.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds RevIt-specific columns to the posts list table.
 */
class RevIt_Publisher_Post_List_Columns {

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Get instance.
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Register hooks.
	 */
	public function init(): void {
		add_filter( 'manage_post_posts_columns', array( $this, 'add_columns' ) );
		add_action( 'manage_post_posts_custom_column', array( $this, 'render_column' ), 10, 2 );
	}

	/**
	 * Add custom columns.
	 *
	 * @param array<string, string> $columns Existing columns.
	 * @return array<string, string>
	 */
	public function add_columns( array $columns ): array {
		$new = array();

		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new['revit_vehicle'] = __( 'Vehicle', 'revit-publisher' );
				$new['revit_type']    = __( 'RevIt Type', 'revit-publisher' );
				$new['revit_cluster'] = __( 'Cluster', 'revit-publisher' );
				$new['revit_topic']   = __( 'Primary Topic', 'revit-publisher' );
			}
		}

		return $new;
	}

	/**
	 * Render custom column value.
	 *
	 * @param string $column Column key.
	 * @param int    $post_id Post ID.
	 */
	public function render_column( string $column, int $post_id ): void {
		if ( ! get_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::MANAGED, true ) ) {
			if ( str_starts_with( $column, 'revit_' ) ) {
				echo '<span aria-hidden="true">—</span>';
			}
			return;
		}

		switch ( $column ) {
			case 'revit_vehicle':
				$parts = array_filter(
					array(
						get_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::VEHICLE_MANUFACTURER, true ),
						get_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::VEHICLE_MODEL, true ),
						get_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::VEHICLE_GENERATION, true ),
						get_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::VEHICLE_TRIM, true ),
					)
				);
				echo esc_html( implode( ' ', $parts ) );
				break;

			case 'revit_type':
				$terms = wp_get_post_terms( $post_id, RevIt_Publisher_Taxonomies::ARTICLE_TYPE, array( 'fields' => 'names' ) );
				echo esc_html( ( ! is_wp_error( $terms ) && ! empty( $terms ) ) ? (string) $terms[0] : '' );
				break;

			case 'revit_cluster':
				$terms = wp_get_post_terms( $post_id, RevIt_Publisher_Taxonomies::CLUSTER, array( 'fields' => 'names' ) );
				echo esc_html( ( ! is_wp_error( $terms ) && ! empty( $terms ) ) ? (string) $terms[0] : '' );
				break;

			case 'revit_topic':
				echo esc_html( (string) get_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::PRIMARY_TOPIC, true ) );
				break;
		}
	}
}
