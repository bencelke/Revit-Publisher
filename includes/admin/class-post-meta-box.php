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

		$graph   = RevIt_Publisher_Services::graph();
		$health  = RevIt_Publisher_Services::health_service()->get_post_health( $post->ID );
		$outbound = $graph->get_outbound_relationships( $post->ID );
		$resolved = count( $graph->get_resolved_links( $post->ID ) );
		$unresolved = count( $graph->get_unresolved_links( $post->ID ) );
		$inbound  = count( $graph->get_inbound_relationships( $post->ID ) );
		$pillar   = $graph->get_pillar_article( $post->ID );

		echo '<div class="revit-publisher-metabox">';

		$this->row( __( 'Vehicle', 'revit-publisher' ), $graph->get_vehicle_label( $post->ID ) );
		$this->row( __( 'Cluster', 'revit-publisher' ), $this->get_cluster_label( $post->ID ) );

		if ( is_array( $pillar ) ) {
			if ( 'resolved' === ( $pillar['status'] ?? '' ) ) {
				$this->row( __( 'Pillar', 'revit-publisher' ), '✓ ' . (string) ( $pillar['title'] ?? '' ) );
			} else {
				$this->row( __( 'Pillar', 'revit-publisher' ), esc_html__( 'Pillar planned — not imported yet', 'revit-publisher' ) );
			}
		}

		echo '<hr /><strong>' . esc_html__( 'SEO', 'revit-publisher' ) . '</strong>';
		$this->row( __( 'Primary Topic', 'revit-publisher' ), (string) get_post_meta( $post->ID, RevIt_Publisher_Post_Meta_Keys::PRIMARY_TOPIC, true ) );
		$this->row( __( 'SEO Title', 'revit-publisher' ), empty( $health['missing_seo_title'] ) ? '✓' : '⚠' );
		$this->row( __( 'Meta Description', 'revit-publisher' ), empty( $health['missing_meta_description'] ) ? '✓' : '⚠' );

		echo '<hr /><strong>' . esc_html__( 'Internal Linking', 'revit-publisher' ) . '</strong>';
		$this->row( __( 'Outbound planned', 'revit-publisher' ), (string) count( $outbound ) );
		$this->row( __( 'Resolved', 'revit-publisher' ), (string) $resolved );
		$this->row( __( 'Unresolved', 'revit-publisher' ), (string) $unresolved );
		$this->row( __( 'Inbound links', 'revit-publisher' ), (string) $inbound );

		echo '<hr /><strong>' . esc_html__( 'SEO Health', 'revit-publisher' ) . '</strong>';
		$this->row( __( 'Vehicle context', 'revit-publisher' ), empty( $health['missing_vehicle'] ) ? '✓' : '⚠' );
		$this->row( __( 'Pillar linked', 'revit-publisher' ), empty( $health['missing_pillar'] ) ? '✓' : '⚠' );
		if ( (int) $unresolved > 0 ) {
			$this->row( __( 'Unresolved links', 'revit-publisher' ), '⚠ ' . (string) $unresolved );
		}

		echo '<hr />';
		$this->row( __( 'Package hash', 'revit-publisher' ), substr( (string) get_post_meta( $post->ID, RevIt_Publisher_Post_Meta_Keys::PACKAGE_HASH, true ), 0, 12 ) . '…' );
		$this->row( __( 'Imported', 'revit-publisher' ), (string) get_post_meta( $post->ID, RevIt_Publisher_Post_Meta_Keys::IMPORTED_AT, true ) );

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
