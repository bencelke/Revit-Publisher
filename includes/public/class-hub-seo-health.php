<?php
/**
 * Vehicle hub SEO health signals.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Evaluates publish readiness and SEO completeness for vehicle hubs.
 */
class RevIt_Publisher_Hub_SEO_Health {

	private RevIt_Publisher_Vehicle_Hub_Service $hubs;
	private RevIt_Publisher_Sitemap_Service $sitemap;

	public function __construct(
		RevIt_Publisher_Vehicle_Hub_Service $hubs,
		RevIt_Publisher_Sitemap_Service $sitemap
	) {
		$this->hubs    = $hubs;
		$this->sitemap = $sitemap;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function evaluate( int $hub_id ): array {
		$post = get_post( $hub_id );
		if ( ! $post instanceof WP_Post || RevIt_Publisher_Vehicle_Hub_Post_Type::POST_TYPE !== $post->post_type ) {
			return array(
				'hub_id'  => $hub_id,
				'valid'   => false,
				'message' => __( 'Vehicle hub not found.', 'revit-publisher' ),
			);
		}

		$coverage   = $this->hubs->get_coverage( $hub_id );
		$clusters   = $this->hubs->get_clusters_for_hub( $hub_id );
		$permalink  = get_permalink( $hub_id );
		$intro      = (string) get_post_meta( $hub_id, RevIt_Publisher_Vehicle_Hub_Meta_Keys::INTRO, true );
		$breadcrumbs = new RevIt_Publisher_Public_Breadcrumbs();
		$trail       = $breadcrumbs->get_trail_for_hub( $hub_id );

		$incomplete_clusters = array();
		$missing_planned     = (int) ( $coverage['missing_articles'] ?? 0 );

		foreach ( $clusters as $cluster ) {
			if ( empty( $cluster['is_public'] ) ) {
				$incomplete_clusters[] = array(
					'cluster_key'   => (string) ( $cluster['cluster_key'] ?? '' ),
					'name'          => (string) ( $cluster['name'] ?? '' ),
					'article_count' => (int) ( $cluster['article_count'] ?? 0 ),
					'reason'        => __( 'Cluster below public threshold and no pillar.', 'revit-publisher' ),
				);
			}
		}

		$signals = array(
			'published'            => 'publish' === $post->post_status,
			'canonical'            => is_string( $permalink ) && '' !== $permalink,
			'breadcrumbs'          => count( $trail ) >= 3,
			'sitemap'              => $this->sitemap->is_post_indexable( $hub_id, RevIt_Publisher_Vehicle_Hub_Post_Type::POST_TYPE ),
			'articles'             => (int) ( $coverage['published_articles'] ?? 0 ) >= 3,
			'clusters'             => count( $clusters ) > 0,
			'incomplete_clusters'  => count( $incomplete_clusters ),
			'missing_planned'      => $missing_planned,
			'intro'                => '' !== trim( $intro ),
			'title'                => '' !== trim( get_the_title( $hub_id ) ),
		);

		$warnings = array();
		foreach ( $signals as $key => $ok ) {
			if ( is_bool( $ok ) && ! $ok ) {
				$warnings[] = $key;
			}
		}
		if ( $signals['incomplete_clusters'] > 0 ) {
			$warnings[] = 'incomplete_clusters';
		}
		if ( $signals['missing_planned'] > 0 ) {
			$warnings[] = 'missing_planned';
		}

		return array(
			'hub_id'              => $hub_id,
			'valid'               => true,
			'post_status'         => $post->post_status,
			'vehicle_key'         => $this->hubs->get_vehicle_key( $hub_id ),
			'signals'             => $signals,
			'warnings'            => array_values( array_unique( $warnings ) ),
			'incomplete_clusters' => $incomplete_clusters,
			'coverage'            => $coverage,
			'needs_attention'     => ! empty( $warnings ),
		);
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function evaluate_all_published(): array {
		$results = array();
		foreach ( $this->hubs->list_published_hubs() as $hub ) {
			$results[] = $this->evaluate( (int) ( $hub['hub_id'] ?? 0 ) );
		}
		return $results;
	}
}
