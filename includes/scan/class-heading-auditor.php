<?php
/**
 * Deterministic heading-structure audit.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Audits H1–H4 structure from parsed headings.
 */
final class RevIt_Publisher_Heading_Auditor {

	/**
	 * @param array<int, array{level: int, text: string}> $headings Content headings (not the theme title).
	 * @return array<string, mixed>
	 */
	public function audit( array $headings, string $post_title = '' ): array {
		$issues          = array();
		$content_h1      = 0;
		$empty           = 0;
		$seen            = array();
		$duplicates      = array();
		$skipped_levels  = false;
		$previous_level  = 0;

		foreach ( $headings as $heading ) {
			$level = (int) ( $heading['level'] ?? 0 );
			$text  = trim( (string) ( $heading['text'] ?? '' ) );

			if ( $level < 1 || $level > 6 ) {
				continue;
			}

			if ( 1 === $level ) {
				++$content_h1;
			}

			if ( '' === $text ) {
				++$empty;
				$issues[] = array(
					'code'     => 'empty_heading',
					'severity' => 'warning',
					'message'  => 'Empty heading found.',
					'safe_fix' => false,
				);
			}

			$key = strtolower( $text );
			if ( '' !== $key ) {
				if ( isset( $seen[ $key ] ) ) {
					$duplicates[ $key ] = true;
				}
				$seen[ $key ] = true;
			}

			if ( $previous_level > 0 && $level > $previous_level + 1 ) {
				$skipped_levels = true;
			}
			$previous_level = $level;
		}

		$effective_h1 = $content_h1 + ( '' !== trim( $post_title ) ? 1 : 0 );
		if ( $content_h1 > 0 ) {
			$issues[] = array(
				'code'     => 'extra_content_h1',
				'severity' => 'warning',
				'message'  => 'Article body contains H1 heading(s). The post title is the effective article H1; extra H1s should be H2.',
				'safe_fix' => true,
			);
		}

		if ( $skipped_levels ) {
			$issues[] = array(
				'code'     => 'skipped_heading_level',
				'severity' => 'warning',
				'message'  => 'Heading levels skip a rank (for example H2 to H4).',
				'safe_fix' => false,
			);
		}

		if ( ! empty( $duplicates ) ) {
			$issues[] = array(
				'code'     => 'duplicate_headings',
				'severity' => 'warning',
				'message'  => 'Duplicate heading text detected.',
				'safe_fix' => false,
			);
		}

		$has_structure = count( $headings ) >= 2;

		return array(
			'content_h1_count'   => $content_h1,
			'effective_h1_count' => $effective_h1,
			'heading_count'      => count( $headings ),
			'has_section_structure' => $has_structure,
			'skipped_levels'     => $skipped_levels,
			'empty_headings'     => $empty,
			'duplicate_headings' => array_keys( $duplicates ),
			'issues'             => $issues,
		);
	}
}
