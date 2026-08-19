<?php
/**
 * Sitemap health audit signals.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Counts indexable and excluded content for operations dashboards.
 */
class RevIt_Publisher_Sitemap_Health_Service {

	private RevIt_Publisher_Sitemap_Service $sitemap;

	public function __construct( RevIt_Publisher_Sitemap_Service $sitemap ) {
		$this->sitemap = $sitemap;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function get_audit(): array {
		$indexable_hubs     = 0;
		$indexable_articles = 0;
		$excluded_drafts    = 0;
		$excluded_noindex   = 0;
		$excluded_operational = 0;

		$hubs = get_posts(
			array(
				'post_type'      => RevIt_Publisher_Vehicle_Hub_Post_Type::POST_TYPE,
				'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);
		foreach ( $hubs as $hub_id ) {
			if ( $this->sitemap->is_post_indexable( (int) $hub_id, RevIt_Publisher_Vehicle_Hub_Post_Type::POST_TYPE ) ) {
				++$indexable_hubs;
			} elseif ( 'publish' !== get_post_status( (int) $hub_id ) ) {
				++$excluded_drafts;
			}
		}

		foreach ( RevIt_Publisher_Services::registry()->get_managed_post_ids() as $post_id ) {
			if ( $this->sitemap->is_post_indexable( (int) $post_id, 'post' ) ) {
				++$indexable_articles;
				continue;
			}
			if ( 'publish' !== get_post_status( (int) $post_id ) ) {
				++$excluded_drafts;
			} elseif ( '1' !== (string) get_post_meta( (int) $post_id, RevIt_Publisher_Post_Meta_Keys::INDEX, true ) ) {
				++$excluded_noindex;
			}
		}

		$excluded_operational = $this->count_operational_posts();

		$signals = array();
		if ( $excluded_noindex > 0 ) {
			$signals[] = array(
				'type'    => 'noindex_articles',
				'count'   => $excluded_noindex,
				'message' => __( 'Published managed articles excluded from sitemap due to noindex.', 'revit-publisher' ),
			);
		}
		if ( $excluded_drafts > 0 ) {
			$signals[] = array(
				'type'    => 'draft_content',
				'count'   => $excluded_drafts,
				'message' => __( 'Draft or non-published RevIt content excluded from sitemap.', 'revit-publisher' ),
			);
		}

		return array(
			'indexable_hubs'         => $indexable_hubs,
			'indexable_articles'     => $indexable_articles,
			'indexable_total'        => $indexable_hubs + $indexable_articles,
			'excluded_drafts'        => $excluded_drafts,
			'excluded_noindex'       => $excluded_noindex,
			'excluded_operational'   => $excluded_operational,
			'needs_attention_signals'=> $signals,
			'generated_at'           => gmdate( 'c' ),
		);
	}

	private function count_operational_posts(): int {
		$types = array(
			RevIt_Publisher_Operations_Post_Types::AUDIT_SNAPSHOT,
			RevIt_Publisher_Operations_Post_Types::ISSUE,
			RevIt_Publisher_Operations_Post_Types::REDIRECT,
			RevIt_Publisher_Operations_Post_Types::LINK_CHANGE,
			RevIt_Publisher_Operations_Post_Types::NOT_FOUND,
			RevIt_Publisher_Content_Plan_Post_Type::POST_TYPE,
		);
		$total = 0;
		foreach ( $types as $type ) {
			$counts = wp_count_posts( $type );
			if ( is_object( $counts ) ) {
				foreach ( get_object_vars( $counts ) as $count ) {
					$total += (int) $count;
				}
			}
		}
		return $total;
	}
}
