<?php
/**
 * Search performance classification for content planning.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RevIt_Publisher_GSC_Content_Status {

	private RevIt_Publisher_GSC_Data_Store $store;
	private RevIt_Publisher_Settings $settings;

	public function __construct( RevIt_Publisher_GSC_Data_Store $store, RevIt_Publisher_Settings $settings ) {
		$this->store    = $store;
		$this->settings = $settings;
	}

	/**
	 * Classify a planned article's Search Console performance state.
	 *
	 * Returns null when GSC is not connected or article is not published.
	 */
	public function classify_article( array $article ): ?string {
		if ( ! RevIt_Publisher_Services::gsc_auth()->is_connected() ) {
			return null;
		}

		$site_status = (string) ( $article['site_status'] ?? '' );
		if ( 'missing' === $site_status ) {
			return 'missing_content';
		}
		if ( 'publish' !== $site_status ) {
			return null;
		}

		$post_id = (int) ( $article['post_id'] ?? 0 );
		if ( $post_id <= 0 ) {
			return null;
		}

		$metrics = $this->store->get_post_metrics( $post_id, '28d' );
		if ( ! is_array( $metrics ) ) {
			return 'published_no_visibility';
		}

		$impressions = (int) ( $metrics['impressions'] ?? 0 );
		$clicks      = (int) ( $metrics['clicks'] ?? 0 );

		if ( 0 === $impressions ) {
			$grace = $this->settings->gsc_zero_visibility_days();
			$published = get_post_time( 'U', true, $post_id );
			if ( $published && ( time() - (int) $published ) <= ( $grace * DAY_IN_SECONDS ) ) {
				return null;
			}
			return 'published_no_visibility';
		}

		$recent = $this->store->get_post_metrics( $post_id, 'prev_28d' );
		if ( is_array( $recent ) ) {
			$prev_impr = (int) ( $recent['impressions'] ?? 0 );
			if ( $prev_impr > 0 && $impressions > 0 ) {
				$change = ( ( $impressions - $prev_impr ) / $prev_impr ) * 100;
				if ( $change >= $this->settings->gsc_decline_threshold_pct() ) {
					return 'emerging_content';
				}
				if ( $change <= -$this->settings->gsc_decline_threshold_pct() ) {
					return 'declining_content';
				}
			}
		}

		if ( $clicks >= 10 || $impressions >= $this->settings->gsc_min_impressions() ) {
			return 'established_content';
		}

		return 'emerging_content';
	}

	/**
	 * @param array<int, array<string, mixed>> $articles
	 * @return array<string, mixed>
	 */
	public function summarize_plan_articles( array $articles ): array {
		$groups = array(
			'missing_content'          => array(),
			'published_no_visibility'  => array(),
			'emerging_content'         => array(),
			'established_content'      => array(),
			'declining_content'        => array(),
		);

		if ( ! RevIt_Publisher_Services::gsc_auth()->is_connected() ) {
			return array(
				'connected' => false,
				'groups'    => $groups,
			);
		}

		foreach ( $articles as $article ) {
			$status = $this->classify_article( $article );
			if ( null === $status || ! isset( $groups[ $status ] ) ) {
				continue;
			}
			$groups[ $status ][] = array(
				'article_key' => (string) ( $article['article_key'] ?? '' ),
				'title'       => (string) ( $article['title'] ?? '' ),
				'cluster_key' => (string) ( $article['cluster_key'] ?? '' ),
				'post_id'     => (int) ( $article['post_id'] ?? 0 ),
			);
		}

		return array(
			'connected' => true,
			'groups'    => $groups,
		);
	}
}
