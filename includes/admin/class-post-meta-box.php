<?php
/**
 * Post editor RevIt Publisher meta box.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Displays read-only RevIt metadata in the post editor.
 */
class RevIt_Publisher_Post_Meta_Box {

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
		add_action( 'add_meta_boxes', array( $this, 'register_meta_box' ) );
	}

	/**
	 * Register sidebar meta box.
	 */
	public function register_meta_box(): void {
		add_meta_box(
			'revit-publisher-info',
			__( 'RevIt Publisher', 'revit-publisher' ),
			array( $this, 'render_meta_box' ),
			'post',
			'side',
			'high'
		);
	}

	/**
	 * Render meta box content.
	 *
	 * @param WP_Post $post Current post.
	 */
	public function render_meta_box( WP_Post $post ): void {
		if ( ! get_post_meta( $post->ID, RevIt_Publisher_Post_Meta_Keys::MANAGED, true ) ) {
			echo '<p>' . esc_html__( 'This post was not imported by RevIt Publisher.', 'revit-publisher' ) . '</p>';
			return;
		}

		$vehicle_parts = array_filter(
			array(
				get_post_meta( $post->ID, RevIt_Publisher_Post_Meta_Keys::VEHICLE_MANUFACTURER, true ),
				get_post_meta( $post->ID, RevIt_Publisher_Post_Meta_Keys::VEHICLE_MODEL, true ),
				get_post_meta( $post->ID, RevIt_Publisher_Post_Meta_Keys::VEHICLE_GENERATION, true ),
				get_post_meta( $post->ID, RevIt_Publisher_Post_Meta_Keys::VEHICLE_TRIM, true ),
			)
		);

		$engines = get_post_meta( $post->ID, RevIt_Publisher_Post_Meta_Keys::VEHICLE_ENGINES, true );
		if ( ! is_array( $engines ) ) {
			$engines = array();
		}

		$internal_links   = get_post_meta( $post->ID, RevIt_Publisher_Post_Meta_Keys::INTERNAL_LINKS, true );
		$related_articles = get_post_meta( $post->ID, RevIt_Publisher_Post_Meta_Keys::RELATED_ARTICLES, true );
		$article_type     = $this->get_article_type_label( $post->ID );
		$cluster_name     = $this->get_cluster_label( $post->ID );

		$rows = array(
			__( 'Article Key', 'revit-publisher' )     => get_post_meta( $post->ID, RevIt_Publisher_Post_Meta_Keys::ARTICLE_KEY, true ),
			__( 'Vehicle', 'revit-publisher' )         => implode( ' ', $vehicle_parts ),
			__( 'Engine', 'revit-publisher' )          => implode( ', ', $engines ),
			__( 'Article Type', 'revit-publisher' )    => $article_type,
			__( 'Cluster', 'revit-publisher' )         => $cluster_name,
			__( 'Primary Topic', 'revit-publisher' )   => get_post_meta( $post->ID, RevIt_Publisher_Post_Meta_Keys::PRIMARY_TOPIC, true ),
			__( 'Pillar', 'revit-publisher' )          => get_post_meta( $post->ID, RevIt_Publisher_Post_Meta_Keys::PILLAR_ARTICLE_KEY, true ),
			__( 'Planned Links', 'revit-publisher' )     => is_array( $internal_links ) ? (string) count( $internal_links ) : '0',
			__( 'Related Articles', 'revit-publisher' ) => is_array( $related_articles ) ? (string) count( $related_articles ) : '0',
		);

		echo '<div class="revit-publisher-metabox">';
		echo '<table class="widefat striped" style="border:none;">';
		foreach ( $rows as $label => $value ) {
			if ( '' === (string) $value ) {
				continue;
			}
			printf(
				'<tr><th style="width:40%%;padding:6px 0;">%s</th><td style="padding:6px 0;">%s</td></tr>',
				esc_html( $label ),
				esc_html( (string) $value )
			);
		}
		echo '</table>';
		echo '</div>';
	}

	/**
	 * Get article type label from taxonomy.
	 */
	private function get_article_type_label( int $post_id ): string {
		$terms = wp_get_post_terms( $post_id, RevIt_Publisher_Taxonomies::ARTICLE_TYPE, array( 'fields' => 'names' ) );
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return '';
		}

		return (string) $terms[0];
	}

	/**
	 * Get cluster label from taxonomy.
	 */
	private function get_cluster_label( int $post_id ): string {
		$terms = wp_get_post_terms( $post_id, RevIt_Publisher_Taxonomies::CLUSTER, array( 'fields' => 'names' ) );
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return '';
		}

		return (string) $terms[0];
	}
}
