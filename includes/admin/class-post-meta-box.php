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
 * Displays RevIt metadata and SEO health in the post editor.
 */
class RevIt_Publisher_Post_Meta_Box {

	private static ?self $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function init(): void {
		add_action( 'add_meta_boxes', array( $this, 'register_meta_box' ) );
	}

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

	public function render_meta_box( WP_Post $post ): void {
		if ( ! RevIt_Publisher_Services::resolver()->is_managed( $post->ID ) ) {
			echo '<p>' . esc_html__( 'This post was not imported by RevIt Publisher.', 'revit-publisher' ) . '</p>';
			return;
		}

		$graph    = RevIt_Publisher_Services::graph();
		$analysis = RevIt_Publisher_Services::seo_score()->analyze( $post->ID );
		$health   = RevIt_Publisher_Services::health_service()->get_post_health( $post->ID );
		$resolved = count( $graph->get_resolved_links( $post->ID ) );
		$unresolved = count( $graph->get_unresolved_links( $post->ID ) );
		$inbound  = count( $graph->get_inbound_relationships( $post->ID ) );
		$pillar   = $graph->get_pillar_article( $post->ID );
		$review   = (string) get_post_meta( $post->ID, RevIt_Publisher_Post_Meta_Keys::REVIEW_STATUS, true );

		echo '<div class="revit-publisher-metabox">';

		printf(
			'<p style="margin:0 0 8px;"><strong>%s</strong><br /><span style="font-size:18px;">%d / %d</span></p>',
			esc_html( (string) ( $analysis['label'] ?? __( 'RevIt SEO Health', 'revit-publisher' ) ) ),
			(int) ( $analysis['total_score'] ?? 0 ),
			(int) ( $analysis['max_score'] ?? 100 )
		);
		echo '<p class="description">' . esc_html__( 'Internal site-quality metric — not a Google ranking score.', 'revit-publisher' ) . '</p>';

		if ( '' !== $review && 'healthy' !== $review ) {
			$this->row( __( 'Review Status', 'revit-publisher' ), ucwords( str_replace( '_', ' ', $review ) ) );
		}

		echo '<hr /><strong>' . esc_html__( 'Metadata', 'revit-publisher' ) . '</strong>';
		$this->row( __( 'SEO title', 'revit-publisher' ), empty( $health['missing_seo_title'] ) ? '✓' : '⚠' );
		$this->row( __( 'Meta description', 'revit-publisher' ), empty( $health['missing_meta_description'] ) ? '✓' : '⚠' );

		echo '<hr /><strong>' . esc_html__( 'Internal Linking', 'revit-publisher' ) . '</strong>';
		$this->row( __( 'Outbound resolved', 'revit-publisher' ), (string) $resolved );
		$this->row( __( 'Unresolved', 'revit-publisher' ), (string) $unresolved );
		$this->row( __( 'Inbound links', 'revit-publisher' ), (string) $inbound );

		echo '<hr /><strong>' . esc_html__( 'Cluster', 'revit-publisher' ) . '</strong>';
		$this->row( __( 'Vehicle', 'revit-publisher' ), $graph->get_vehicle_label( $post->ID ) );
		$this->row( __( 'Cluster', 'revit-publisher' ), $this->get_cluster_label( $post->ID ) );
		if ( is_array( $pillar ) ) {
			if ( 'resolved' === ( $pillar['status'] ?? '' ) ) {
				$this->row( __( 'Pillar', 'revit-publisher' ), '✓ ' . (string) ( $pillar['title'] ?? '' ) );
			} else {
				$this->row( __( 'Pillar', 'revit-publisher' ), esc_html__( 'Pillar planned — not imported yet', 'revit-publisher' ) );
			}
		}

		$recommendations = is_array( $analysis['recommendations'] ?? null ) ? $analysis['recommendations'] : array();
		if ( ! empty( $recommendations ) ) {
			echo '<hr /><strong>' . esc_html__( 'Topic', 'revit-publisher' ) . '</strong>';
			foreach ( array_slice( $recommendations, 0, 2 ) as $rec ) {
				if ( ! is_array( $rec ) ) {
					continue;
				}
				$prefix = 'high' === ( $rec['severity'] ?? '' ) ? '⚠ ' : '• ';
				printf(
					'<p style="margin:4px 0;font-size:12px;">%s</p>',
					esc_html( $prefix . (string) ( $rec['message'] ?? '' ) )
				);
			}
		}

		echo '<hr />';
		$this->row( __( 'Package hash', 'revit-publisher' ), substr( (string) get_post_meta( $post->ID, RevIt_Publisher_Post_Meta_Keys::PACKAGE_HASH, true ), 0, 12 ) . '…' );
		$this->row( __( 'Imported', 'revit-publisher' ), (string) get_post_meta( $post->ID, RevIt_Publisher_Post_Meta_Keys::IMPORTED_AT, true ) );

		printf(
			'<p><a href="%s">%s</a></p>',
			esc_url( admin_url( 'admin.php?page=' . RevIt_Publisher_Admin::MENU_SLUG . '-seo-health' ) ),
			esc_html__( 'View Full Analysis', 'revit-publisher' )
		);
		printf(
			'<p><a href="%s">%s</a></p>',
			esc_url( admin_url( 'admin.php?page=' . RevIt_Publisher_Admin::MENU_SLUG . '-graph' ) ),
			esc_html__( 'View Content Graph', 'revit-publisher' )
		);

		echo '</div>';
	}

	private function row( string $label, string $value ): void {
		if ( '' === $value ) {
			return;
		}
		printf(
			'<p style="margin:4px 0;"><strong>%s</strong><br />%s</p>',
			esc_html( $label ),
			esc_html( $value )
		);
	}

	private function get_cluster_label( int $post_id ): string {
		$terms = wp_get_post_terms( $post_id, RevIt_Publisher_Taxonomies::CLUSTER, array( 'fields' => 'names' ) );
		return ( ! is_wp_error( $terms ) && ! empty( $terms ) ) ? (string) $terms[0] : '';
	}
}
