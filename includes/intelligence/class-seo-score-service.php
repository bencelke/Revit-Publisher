<?php
/**
 * RevIt SEO Health score (internal content-quality metric).
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Computes transparent 0-100 SEO health scores for RevIt-managed articles.
 */
class RevIt_Publisher_SEO_Score_Service {

	private RevIt_Publisher_Content_Graph $graph;
	private RevIt_Publisher_Internal_Link_Service $link_service;
	private RevIt_Publisher_Topic_Overlap_Service $overlap_service;

	public function __construct(
		RevIt_Publisher_Content_Graph $graph,
		RevIt_Publisher_Internal_Link_Service $link_service,
		RevIt_Publisher_Topic_Overlap_Service $overlap_service
	) {
		$this->graph           = $graph;
		$this->link_service    = $link_service;
		$this->overlap_service = $overlap_service;
	}

	/**
	 * Full SEO analysis for one post.
	 *
	 * @return array<string, mixed>
	 */
	public function analyze( int $post_id ): array {
		$categories = array(
			'metadata'            => $this->score_metadata( $post_id ),
			'structure'           => $this->score_structure( $post_id ),
			'internal_linking'    => $this->score_internal_linking( $post_id ),
			'cluster_integration' => $this->score_cluster_integration( $post_id ),
			'topic_uniqueness'    => $this->score_topic_uniqueness( $post_id ),
			'source_support'      => $this->score_source_support( $post_id ),
		);

		$total = 0;
		$max   = 0;
		foreach ( $categories as $category ) {
			$total += (int) ( $category['score'] ?? 0 );
			$max   += (int) ( $category['max'] ?? 0 );
		}

		return array(
			'post_id'     => $post_id,
			'total_score' => $total,
			'max_score'   => $max,
			'label'       => __( 'RevIt SEO Health', 'revit-publisher' ),
			'disclaimer'  => __( 'RevIt SEO Health is an internal site-quality metric and is not a Google ranking score.', 'revit-publisher' ),
			'categories'  => $categories,
			'signals'     => $this->collect_signals( $post_id ),
			'recommendations' => RevIt_Publisher_Services::optimization()->get_recommendations( $post_id ),
		);
	}

	/**
	 * @return array{score: int, max: int, details: string[]}
	 */
	private function score_metadata( int $post_id ): array {
		$max     = 20;
		$score   = 0;
		$details = array();

		$seo_title = (string) get_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::SEO_TITLE, true );
		$meta_desc = (string) get_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::META_DESCRIPTION, true );
		$topic     = (string) get_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::PRIMARY_TOPIC, true );

		if ( '' !== $seo_title ) {
			$score += 7;
			$details[] = __( 'SEO title present.', 'revit-publisher' );
			$len = strlen( $seo_title );
			if ( $len >= 30 && $len <= 65 ) {
				$score += 3;
				$details[] = __( 'SEO title length is within recommended range.', 'revit-publisher' );
			} else {
				$details[] = __( 'SEO title length could be optimized.', 'revit-publisher' );
			}
		} else {
			$details[] = __( 'Missing SEO title.', 'revit-publisher' );
		}

		if ( '' !== $meta_desc ) {
			$score += 5;
			$details[] = __( 'Meta description present.', 'revit-publisher' );
			$len = strlen( $meta_desc );
			if ( $len >= 120 && $len <= 160 ) {
				$score += 3;
				$details[] = __( 'Meta description length is within recommended range.', 'revit-publisher' );
			}
		} else {
			$details[] = __( 'Missing meta description.', 'revit-publisher' );
		}

		if ( '' !== $topic ) {
			$score += 2;
			$details[] = __( 'Primary topic present.', 'revit-publisher' );
		}

		return array(
			'score'   => min( $score, $max ),
			'max'     => $max,
			'label'   => __( 'Metadata', 'revit-publisher' ),
			'details' => $details,
		);
	}

	/**
	 * @return array{score: int, max: int, details: string[]}
	 */
	private function score_structure( int $post_id ): array {
		$max   = 15;
		$score = 0;
		$details = array();
		$post  = get_post( $post_id );

		if ( ! $post instanceof WP_Post ) {
			return array( 'score' => 0, 'max' => $max, 'label' => __( 'Structure', 'revit-publisher' ), 'details' => array() );
		}

		$blocks = parse_blocks( $post->post_content );
		$has_h2 = false;
		$paragraphs = 0;

		foreach ( $blocks as $block ) {
			if ( 'core/heading' === ( $block['blockName'] ?? '' ) ) {
				$has_h2 = true;
			}
			if ( 'core/paragraph' === ( $block['blockName'] ?? '' ) ) {
				++$paragraphs;
			}
		}

		if ( $has_h2 ) {
			$score += 5;
			$details[] = __( 'H2 headings present.', 'revit-publisher' );
		}
		if ( $paragraphs >= 3 ) {
			$score += 5;
			$details[] = sprintf(
				/* translators: %d: paragraph count */
				__( '%d paragraphs detected.', 'revit-publisher' ),
				$paragraphs
			);
		}
		if ( strlen( wp_strip_all_tags( $post->post_content ) ) >= 500 ) {
			$score += 5;
			$details[] = __( 'Content length meets minimum threshold.', 'revit-publisher' );
		}

		return array(
			'score'   => min( $score, $max ),
			'max'     => $max,
			'label'   => __( 'Structure', 'revit-publisher' ),
			'details' => $details,
		);
	}

	/**
	 * @return array{score: int, max: int, details: string[]}
	 */
	private function score_internal_linking( int $post_id ): array {
		$max       = 25;
		$score     = 0;
		$details   = array();
		$outbound  = $this->graph->get_outbound_relationships( $post_id );
		$resolved  = array_filter( $outbound, static fn( array $l ): bool => 'resolved' === ( $l['status'] ?? '' ) );
		$unresolved = count( $outbound ) - count( $resolved );

		if ( count( $resolved ) >= 1 ) {
			$score += 10;
			$details[] = sprintf(
				/* translators: %d: link count */
				__( '%d resolved outbound links.', 'revit-publisher' ),
				count( $resolved )
			);
		}
		if ( count( $resolved ) >= 3 ) {
			$score += 5;
		}
		if ( 0 === $unresolved ) {
			$score += 5;
			$details[] = __( 'No unresolved planned links.', 'revit-publisher' );
		} else {
			$details[] = sprintf(
				/* translators: %d: unresolved count */
				__( '%d unresolved planned links.', 'revit-publisher' ),
				$unresolved
			);
		}

		$inbound = 0;
		foreach ( $this->graph->get_inbound_relationships( $post_id ) as $link ) {
			$source = (int) ( $link['source_post_id'] ?? 0 );
			if ( $source > 0 && $this->link_service->content_already_links_to( $source, $post_id ) ) {
				++$inbound;
			}
		}
		if ( $inbound >= 1 ) {
			$score += 5;
			$details[] = sprintf(
				/* translators: %d: inbound count */
				__( '%d inbound contextual links.', 'revit-publisher' ),
				$inbound
			);
		} else {
			$details[] = __( 'No resolved inbound links.', 'revit-publisher' );
		}

		return array(
			'score'   => min( $score, $max ),
			'max'     => $max,
			'label'   => __( 'Internal Linking', 'revit-publisher' ),
			'details' => $details,
		);
	}

	/**
	 * @return array{score: int, max: int, details: string[]}
	 */
	private function score_cluster_integration( int $post_id ): array {
		$max     = 20;
		$score   = 0;
		$details = array();

		$cluster = (string) get_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::CLUSTER_KEY, true );
		if ( '' !== $cluster ) {
			$score += 8;
			$details[] = __( 'Cluster assigned.', 'revit-publisher' );
		}

		$pillar = $this->graph->get_pillar_article( $post_id );
		if ( is_array( $pillar ) && 'resolved' === ( $pillar['status'] ?? '' ) ) {
			$score += 8;
			$details[] = __( 'Pillar relationship resolved.', 'revit-publisher' );
		} elseif ( is_array( $pillar ) && 'pillar_planned' === ( $pillar['status'] ?? '' ) ) {
			$details[] = __( 'Pillar planned but not imported.', 'revit-publisher' );
		}

		$vehicle = $this->graph->get_vehicle_label( $post_id );
		if ( '' !== $vehicle ) {
			$score += 4;
			$details[] = __( 'Vehicle context present.', 'revit-publisher' );
		}

		return array(
			'score'   => min( $score, $max ),
			'max'     => $max,
			'label'   => __( 'Cluster Integration', 'revit-publisher' ),
			'details' => $details,
		);
	}

	/**
	 * @return array{score: int, max: int, details: string[]}
	 */
	private function score_topic_uniqueness( int $post_id ): array {
		$max     = 10;
		$score   = 10;
		$details = array( __( 'No significant topic overlap detected.', 'revit-publisher' ) );

		foreach ( $this->overlap_service->get_post_overlaps( $post_id ) as $overlap ) {
			$risk = (string) ( $overlap['risk'] ?? 'low' );
			if ( 'high' === $risk ) {
				$score = 2;
				$details = array(
					sprintf(
						/* translators: %s: article title */
						__( 'High overlap with: %s', 'revit-publisher' ),
						(string) ( $overlap['title_b'] ?? $overlap['title_a'] ?? '' )
					),
				);
				break;
			}
			if ( 'medium' === $risk && $score > 5 ) {
				$score = 5;
				$details = array( __( 'Moderate topic overlap detected.', 'revit-publisher' ) );
			}
		}

		return array(
			'score'   => $score,
			'max'     => $max,
			'label'   => __( 'Topic Uniqueness', 'revit-publisher' ),
			'details' => $details,
		);
	}

	/**
	 * @return array{score: int, max: int, details: string[]}
	 */
	private function score_source_support( int $post_id ): array {
		$max     = 10;
		$sources = get_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::SOURCES, true );
		$count   = is_array( $sources ) ? count( $sources ) : 0;
		$score   = min( $count * 3, $max );

		return array(
			'score'   => $score,
			'max'     => $max,
			'label'   => __( 'Source Support', 'revit-publisher' ),
			'details' => array(
				sprintf(
					/* translators: %d: source count */
					__( '%d source references.', 'revit-publisher' ),
					$count
				),
			),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function collect_signals( int $post_id ): array {
		$health = RevIt_Publisher_Services::health_service()->get_post_health( $post_id );

		return array(
			'seo_title_length'        => strlen( (string) get_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::SEO_TITLE, true ) ),
			'meta_description_length' => strlen( (string) get_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::META_DESCRIPTION, true ) ),
			'outbound_links'          => count( $this->graph->get_outbound_relationships( $post_id ) ),
			'unresolved_links'        => (int) ( $health['unresolved_links'] ?? 0 ),
			'is_orphan'               => ! empty( $health['is_orphan'] ),
			'topic_overlaps'          => count( $this->overlap_service->get_post_overlaps( $post_id ) ),
		);
	}
}
