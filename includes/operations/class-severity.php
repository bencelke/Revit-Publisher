<?php
/**
 * Deterministic issue severity model.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Severity constants and assignment helpers for the Needs Attention queue.
 *
 * @see docs/needs-attention.md
 */
final class RevIt_Publisher_Severity {

	public const CRITICAL = 'critical';
	public const HIGH     = 'high';
	public const MEDIUM   = 'medium';
	public const LOW      = 'low';

	/**
	 * @return string[]
	 */
	public static function all(): array {
		return array( self::CRITICAL, self::HIGH, self::MEDIUM, self::LOW );
	}

	/**
	 * Assign severity from issue type and context.
	 *
	 * @param array<string, mixed> $context
	 */
	public static function for_issue( string $type, array $context = array() ): string {
		switch ( $type ) {
			case 'broken_relationship':
			case 'duplicate_key_error':
				return self::CRITICAL;

			case 'topic_overlap':
				return 'high' === ( $context['risk'] ?? '' ) ? self::HIGH : self::MEDIUM;

			case 'orphan':
				return ! empty( $context['high_priority'] ) ? self::HIGH : self::MEDIUM;

			case 'missing_pillar':
				return self::HIGH;

			case 'unresolved_link':
			case 'missing_meta':
			case 'review_due':
			case 'newly_resolvable_link':
				return self::MEDIUM;

			case 'missing_content':
			case 'cluster_gap':
			default:
				return self::LOW;
		}
	}
}
