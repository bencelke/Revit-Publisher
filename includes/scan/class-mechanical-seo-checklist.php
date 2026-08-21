<?php
/**
 * Mechanical SEO checklist — compliance with the RevIt standard, not rankings.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Evaluates scan snapshots against the RevIt mechanical SEO checklist.
 */
class RevIt_Publisher_Mechanical_Seo_Checklist {

	public const MIN_TITLE_LEN = 15;
	public const MAX_TITLE_LEN = 70;
	public const MIN_DESC_LEN  = 50;
	public const MAX_DESC_LEN  = 180;

	/**
	 * @param array<string, mixed> $scan Post scan snapshot.
	 * @return array<string, mixed>
	 */
	public function evaluate( array $scan ): array {
		$issues = array();
		$issues = array_merge( $issues, $this->metadata( $scan ) );
		$issues = array_merge( $issues, $this->structure( $scan ) );
		$issues = array_merge( $issues, $this->internal_linking( $scan ) );
		$issues = array_merge( $issues, $this->vehicle_context( $scan ) );
		$issues = array_merge( $issues, $this->media( $scan ) );
		$issues = array_merge( $issues, $this->technical( $scan ) );

		$by_category = array(
			'metadata'         => 0,
			'structure'        => 0,
			'internal_linking' => 0,
			'vehicle_context'  => 0,
			'media'            => 0,
			'technical'        => 0,
		);
		foreach ( $issues as $issue ) {
			$cat = (string) ( $issue['category'] ?? '' );
			if ( isset( $by_category[ $cat ] ) ) {
				++$by_category[ $cat ];
			}
		}

		$compliant = 0 === count(
			array_filter(
				$issues,
				static fn( array $issue ): bool => 'error' === ( $issue['severity'] ?? '' ) || 'warning' === ( $issue['severity'] ?? '' )
			)
		);

		return array(
			'mechanical_compliant' => $compliant,
			'issue_count'          => count( $issues ),
			'by_category'          => $by_category,
			'issues'               => $issues,
			'editorial_quality'    => array(
				'status'  => 'separate',
				'message' => 'Editorial writing quality is not scored here. Mechanical SEO compliance only.',
			),
		);
	}

	/**
	 * @param array<string, mixed> $scan Scan.
	 * @return array<int, array<string, mixed>>
	 */
	private function metadata( array $scan ): array {
		$issues    = array();
		$seo_title = trim( (string) ( $scan['seo_title'] ?? '' ) );
		$meta_desc = trim( (string) ( $scan['meta_description'] ?? '' ) );
		$slug      = trim( (string) ( $scan['slug'] ?? '' ) );
		$canonical = trim( (string) ( $scan['canonical'] ?? '' ) );
		$index     = (bool) ( $scan['index'] ?? true );
		$follow    = (bool) ( $scan['follow'] ?? true );

		if ( '' === $seo_title ) {
			$issues[] = $this->issue( 'metadata', 'missing_seo_title', 'error', 'SEO title is missing.', true );
		} elseif ( strlen( $seo_title ) < self::MIN_TITLE_LEN || strlen( $seo_title ) > self::MAX_TITLE_LEN ) {
			$issues[] = $this->issue( 'metadata', 'seo_title_length', 'warning', 'SEO title length is outside 15–70 characters.', false );
		}

		if ( '' === $meta_desc ) {
			$issues[] = $this->issue( 'metadata', 'missing_meta_description', 'error', 'Meta description is missing.', true );
		} elseif ( strlen( $meta_desc ) < self::MIN_DESC_LEN || strlen( $meta_desc ) > self::MAX_DESC_LEN ) {
			$issues[] = $this->issue( 'metadata', 'meta_description_length', 'warning', 'Meta description length is outside 50–180 characters.', false );
		}

		if ( '' === $slug || ! preg_match( '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug ) ) {
			$issues[] = $this->issue( 'metadata', 'invalid_slug', 'warning', 'Slug is missing or not a valid lowercase hyphenated slug.', false );
		}

		if ( '' === $canonical ) {
			$issues[] = $this->issue( 'metadata', 'missing_canonical', 'warning', 'Canonical is empty; restore auto canonical.', true );
		}

		if ( ! $index || ! $follow ) {
			$issues[] = $this->issue( 'metadata', 'index_follow', 'warning', 'Index/follow is not set to index,follow.', false );
		}

		return $issues;
	}

	/**
	 * @param array<string, mixed> $scan Scan.
	 * @return array<int, array<string, mixed>>
	 */
	private function structure( array $scan ): array {
		$heading = is_array( $scan['heading_audit'] ?? null ) ? $scan['heading_audit'] : array();
		$issues  = array();
		foreach ( (array) ( $heading['issues'] ?? array() ) as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$issues[] = $this->issue(
				'structure',
				(string) ( $item['code'] ?? 'heading' ),
				(string) ( $item['severity'] ?? 'warning' ),
				(string) ( $item['message'] ?? 'Heading issue.' ),
				(bool) ( $item['safe_fix'] ?? false )
			);
		}

		if ( empty( $heading['has_section_structure'] ) ) {
			$issues[] = $this->issue( 'structure', 'weak_section_structure', 'warning', 'Article lacks useful H2/H3 section structure.', false );
		}

		return $issues;
	}

	/**
	 * @param array<string, mixed> $scan Scan.
	 * @return array<int, array<string, mixed>>
	 */
	private function internal_linking( array $scan ): array {
		$issues   = array();
		$inbound  = (int) ( $scan['inbound_count'] ?? 0 );
		$outbound = (int) ( $scan['outbound_internal_count'] ?? 0 );
		$broken   = (int) ( $scan['broken_internal_count'] ?? 0 );
		$orphan   = ! empty( $scan['is_orphan'] );

		if ( $orphan || 0 === $inbound ) {
			$issues[] = $this->issue( 'internal_linking', 'orphan', 'warning', 'Article has no meaningful inbound internal links.', false );
		}
		if ( 0 === $outbound ) {
			$issues[] = $this->issue( 'internal_linking', 'no_outbound_internal', 'warning', 'Article has no outbound internal links.', false );
		}
		if ( $broken > 0 ) {
			$issues[] = $this->issue( 'internal_linking', 'broken_internal_links', 'error', 'Broken internal links were found in the article body.', false );
		}

		return $issues;
	}

	/**
	 * @param array<string, mixed> $scan Scan.
	 * @return array<int, array<string, mixed>>
	 */
	private function vehicle_context( array $scan ): array {
		$issues  = array();
		$vehicle = is_array( $scan['vehicle'] ?? null ) ? $scan['vehicle'] : array();
		if ( '' === trim( (string) ( $vehicle['manufacturer'] ?? '' ) ) ) {
			$issues[] = $this->issue( 'vehicle_context', 'missing_manufacturer', 'error', 'Manufacturer is missing.', true );
		}
		if ( '' === trim( (string) ( $vehicle['model'] ?? '' ) ) ) {
			$issues[] = $this->issue( 'vehicle_context', 'missing_model', 'error', 'Model is missing.', true );
		}
		if ( '' === trim( (string) ( $scan['article_type'] ?? '' ) ) ) {
			$issues[] = $this->issue( 'vehicle_context', 'missing_article_type', 'error', 'Article type is missing.', true );
		}
		if ( '' === trim( (string) ( $scan['cluster'] ?? '' ) ) ) {
			$issues[] = $this->issue( 'vehicle_context', 'missing_cluster', 'warning', 'Cluster association is missing.', true );
		}

		return $issues;
	}

	/**
	 * @param array<string, mixed> $scan Scan.
	 * @return array<int, array<string, mixed>>
	 */
	private function media( array $scan ): array {
		$issues = array();
		if ( empty( $scan['featured_image'] ) ) {
			$issues[] = $this->issue( 'media', 'missing_featured_image', 'warning', 'Featured image is missing.', false );
		}
		if ( (int) ( $scan['images_missing_alt'] ?? 0 ) > 0 ) {
			$issues[] = $this->issue( 'media', 'missing_image_alt', 'warning', 'One or more images are missing ALT text.', false );
		}
		if ( (int) ( $scan['broken_media_count'] ?? 0 ) > 0 ) {
			$issues[] = $this->issue( 'media', 'broken_media', 'error', 'Broken media references were found.', false );
		}

		return $issues;
	}

	/**
	 * @param array<string, mixed> $scan Scan.
	 * @return array<int, array<string, mixed>>
	 */
	private function technical( array $scan ): array {
		$issues = array();
		$schema = is_array( $scan['structured_data'] ?? null ) ? $scan['structured_data'] : array();
		if ( empty( $schema['article'] ) ) {
			$issues[] = $this->issue( 'technical', 'missing_article_schema_intent', 'warning', 'Article schema intent is not set.', true );
		}
		if ( empty( $schema['breadcrumbs'] ) ) {
			$issues[] = $this->issue( 'technical', 'missing_breadcrumb_schema_intent', 'warning', 'Breadcrumb schema intent is not set.', true );
		}

		$rendered = is_array( $scan['rendered'] ?? null ) ? $scan['rendered'] : array();
		if ( ! empty( $rendered['skipped'] ) ) {
			return $issues;
		}
		if ( isset( $rendered['http_status'] ) && (int) $rendered['http_status'] > 0 && 200 !== (int) $rendered['http_status'] ) {
			$issues[] = $this->issue( 'technical', 'public_http_status', 'error', 'Public page did not return HTTP 200.', false );
		}

		return $issues;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function issue( string $category, string $code, string $severity, string $message, bool $safe_fix ): array {
		return array(
			'category' => $category,
			'code'     => $code,
			'severity' => $severity,
			'message'  => $message,
			'safe_fix' => $safe_fix,
			'review'   => ! $safe_fix,
		);
	}
}
