<?php
/**
 * Site-wide and per-article SEO scan orchestration.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Scan RevIt-managed posts, score mechanical SEO, and discover links.
 */
class RevIt_Publisher_Site_Seo_Scan_Service {

	public const OPTION = 'revit_last_site_seo_scan';

	private RevIt_Publisher_Post_Content_Scanner $scanner;
	private RevIt_Publisher_Rendered_Page_Validator $rendered;
	private RevIt_Publisher_Mechanical_Seo_Checklist $checklist;
	private RevIt_Publisher_Link_Opportunity_Discovery $discovery;
	private RevIt_Publisher_Seo_Fix_Service $fixes;

	public function __construct(
		?RevIt_Publisher_Post_Content_Scanner $scanner = null,
		?RevIt_Publisher_Rendered_Page_Validator $rendered = null,
		?RevIt_Publisher_Mechanical_Seo_Checklist $checklist = null,
		?RevIt_Publisher_Link_Opportunity_Discovery $discovery = null,
		?RevIt_Publisher_Seo_Fix_Service $fixes = null
	) {
		$this->scanner   = $scanner ?? new RevIt_Publisher_Post_Content_Scanner();
		$this->rendered  = $rendered ?? new RevIt_Publisher_Rendered_Page_Validator();
		$this->checklist = $checklist ?? new RevIt_Publisher_Mechanical_Seo_Checklist();
		$this->discovery = $discovery ?? new RevIt_Publisher_Link_Opportunity_Discovery();
		$this->fixes     = $fixes ?? new RevIt_Publisher_Seo_Fix_Service();
	}

	/**
	 * @return array<string, mixed>
	 */
	public function scan_site(): array {
		$ids      = RevIt_Publisher_Services::registry()->get_managed_post_ids();
		$articles = array();
		$scans    = array();
		$healthy  = 0;
		$orphans  = 0;
		$missing_meta = 0;
		$heading_issues = 0;
		$media_issues = 0;

		foreach ( $ids as $post_id ) {
			$scan = $this->scan_post( (int) $post_id, 'publish' === (string) get_post_status( (int) $post_id ) );
			if ( empty( $scan ) ) {
				continue;
			}
			$scans[]    = $scan;
			$articles[] = $scan;
			$check      = is_array( $scan['checklist'] ?? null ) ? $scan['checklist'] : array();
			if ( ! empty( $check['mechanical_compliant'] ) ) {
				++$healthy;
			}
			if ( ! empty( $scan['is_orphan'] ) ) {
				++$orphans;
			}
			$by = is_array( $check['by_category'] ?? null ) ? $check['by_category'] : array();
			$missing_meta   += (int) ( $by['metadata'] ?? 0 );
			$heading_issues += (int) ( $by['structure'] ?? 0 );
			$media_issues   += (int) ( $by['media'] ?? 0 );
		}

		$opportunities = $this->discovery->discover( $articles );
		$needs         = count( $scans ) - $healthy;

		$result = array(
			'scanned_at'            => gmdate( 'c' ),
			'articles_scanned'      => count( $scans ),
			'seo_compliant'         => $healthy,
			'needs_improvement'     => max( 0, $needs ),
			'orphan_articles'       => $orphans,
			'internal_link_ideas'   => count( $opportunities ),
			'missing_metadata'      => $missing_meta,
			'heading_issues'        => $heading_issues,
			'media_issues'          => $media_issues,
			'opportunities'         => $opportunities,
			'articles'              => array_map(
				static function ( array $scan ): array {
					return array(
						'post_id'     => $scan['post_id'] ?? 0,
						'title'       => $scan['title'] ?? '',
						'status'      => $scan['status'] ?? '',
						'vehicle'     => $scan['vehicle_label'] ?? '',
						'orphan'      => ! empty( $scan['is_orphan'] ),
						'compliant'   => ! empty( $scan['checklist']['mechanical_compliant'] ),
						'issue_count' => (int) ( $scan['checklist']['issue_count'] ?? 0 ),
						'inbound'     => (int) ( $scan['inbound_count'] ?? 0 ),
						'outbound'    => (int) ( $scan['outbound_internal_count'] ?? 0 ),
					);
				},
				$scans
			),
		);

		update_option( self::OPTION, $result, false );

		return $result;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function get_last_site_scan(): array {
		$stored = get_option( self::OPTION, array() );

		return is_array( $stored ) ? $stored : array();
	}

	/**
	 * @return array<string, mixed>
	 */
	public function scan_post( int $post_id, bool $include_rendered = true ): array {
		$scan = $this->scanner->scan_post( $post_id );
		if ( empty( $scan ) ) {
			return array();
		}

		if ( $include_rendered ) {
			try {
				$scan['rendered'] = $this->rendered->validate_post( $post_id );
			} catch ( Throwable $e ) {
				$scan['rendered'] = array(
					'ok'      => false,
					'error'   => 'validator_failed',
					'message' => $e->getMessage(),
				);
			}
		}

		$scan['checklist'] = $this->checklist->evaluate( $scan );
		$scan['safe_fixes'] = $this->fixes->propose( $scan, (array) ( $scan['checklist']['issues'] ?? array() ) );

		return $scan;
	}

	/**
	 * Optimize one article: scan + link ideas involving it.
	 *
	 * @return array<string, mixed>
	 */
	public function optimize_article( int $post_id ): array {
		$scan = $this->scan_post( $post_id, true );
		if ( empty( $scan ) ) {
			return array();
		}

		$corpus = array();
		foreach ( RevIt_Publisher_Services::registry()->get_managed_post_ids() as $id ) {
			$row = $this->scanner->scan_post( (int) $id );
			if ( ! empty( $row ) ) {
				$corpus[] = $row;
			}
		}

		$all  = $this->discovery->discover( $corpus );
		$mine = array_values(
			array_filter(
				$all,
				static function ( array $row ) use ( $post_id ): bool {
					return (int) ( $row['source_post_id'] ?? 0 ) === $post_id
						|| (int) ( $row['target_post_id'] ?? 0 ) === $post_id;
				}
			)
		);

		$inbound_ideas = array_values(
			array_filter(
				$mine,
				static fn( array $row ): bool => (int) ( $row['target_post_id'] ?? 0 ) === $post_id
			)
		);

		return array(
			'scan'                 => $scan,
			'checklist'            => $scan['checklist'] ?? array(),
			'safe_fixes'           => $scan['safe_fixes'] ?? array(),
			'link_opportunities'   => $mine,
			'inbound_opportunities'=> $inbound_ideas,
			'inbound_count'        => (int) ( $scan['inbound_count'] ?? 0 ),
			'outbound_count'       => (int) ( $scan['outbound_internal_count'] ?? 0 ),
			'post_status'          => (string) ( $scan['status'] ?? '' ),
		);
	}

	public function fixes(): RevIt_Publisher_Seo_Fix_Service {
		return $this->fixes;
	}

	public function discovery(): RevIt_Publisher_Link_Opportunity_Discovery {
		return $this->discovery;
	}
}
