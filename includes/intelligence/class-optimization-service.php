<?php
/**
 * Deterministic optimization recommendations.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Generates actionable recommendations for RevIt-managed articles.
 */
class RevIt_Publisher_Optimization_Service {

	/**
	 * Get recommendations for a post.
	 *
	 * @return array<int, array<string, string>>
	 */
	public function get_recommendations( int $post_id ): array {
		$recommendations = array();
		$graph  = RevIt_Publisher_Services::graph();
		$health = RevIt_Publisher_Services::health_service()->get_post_health( $post_id );

		if ( ! empty( $health['missing_seo_title'] ) ) {
			$recommendations[] = array(
				'severity' => 'high',
				'message'  => __( 'Add an SEO title.', 'revit-publisher' ),
			);
		}
		if ( ! empty( $health['missing_meta_description'] ) ) {
			$recommendations[] = array(
				'severity' => 'high',
				'message'  => __( 'Add a meta description.', 'revit-publisher' ),
			);
		}
		if ( (int) ( $health['unresolved_links'] ?? 0 ) > 0 ) {
			$recommendations[] = array(
				'severity' => 'medium',
				'message'  => sprintf(
					/* translators: %d: link count */
					__( 'Resolve %d planned internal links.', 'revit-publisher' ),
					(int) $health['unresolved_links']
				),
			);
		}
		if ( ! empty( $health['is_orphan'] ) ) {
			$recommendations[] = array(
				'severity' => 'medium',
				'message'  => __( 'Article has no resolved inbound links.', 'revit-publisher' ),
			);
		}
		if ( ! empty( $health['missing_pillar'] ) ) {
			$recommendations[] = array(
				'severity' => 'medium',
				'message'  => __( 'Pillar article is planned but not imported.', 'revit-publisher' ),
			);
		}

		foreach ( RevIt_Publisher_Services::topic_overlaps()->get_post_overlaps( $post_id ) as $overlap ) {
			if ( in_array( (string) ( $overlap['risk'] ?? '' ), array( 'high', 'medium' ), true ) ) {
				$other_title = (int) ( $overlap['post_id_a'] ?? 0 ) === $post_id
					? (string) ( $overlap['title_b'] ?? '' )
					: (string) ( $overlap['title_a'] ?? '' );
				$recommendations[] = array(
					'severity' => (string) ( $overlap['risk'] ?? 'medium' ),
					'message'  => sprintf(
						/* translators: %s: article title */
						__( 'Potential topic overlap with: %s', 'revit-publisher' ),
						$other_title
					),
				);
				break;
			}
		}

		$pillar = $graph->get_pillar_article( $post_id );
		if ( is_array( $pillar ) && 'resolved' === ( $pillar['status'] ?? '' ) ) {
			$supporting = $graph->get_supporting_articles( (int) ( $pillar['post_id'] ?? 0 ) );
			$unlinked   = 0;
			foreach ( $supporting as $support ) {
				$support_id = (int) ( $support['post_id'] ?? 0 );
				if ( $support_id > 0 && ! RevIt_Publisher_Services::link_service()->content_already_links_to( (int) $pillar['post_id'], $support_id ) ) {
					++$unlinked;
				}
			}
			if ( $unlinked > 0 ) {
				$recommendations[] = array(
					'severity' => 'low',
					'message'  => sprintf(
						/* translators: %d: article count */
						__( 'Pillar has no links to %d supporting articles.', 'revit-publisher' ),
						$unlinked
					),
				);
			}
		}

		return $recommendations;
	}
}
