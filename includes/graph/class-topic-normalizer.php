<?php
/**
 * Primary topic normalization for duplicate detection.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Normalizes topic strings for deterministic duplicate comparison.
 */
class RevIt_Publisher_Topic_Normalizer {

	/**
	 * Normalize a primary topic string.
	 */
	public function normalize( string $topic ): string {
		$topic = strtolower( $topic );
		$topic = preg_replace( '/[^\p{L}\p{N}\s]/u', '', $topic ) ?? '';
		$topic = preg_replace( '/\s+/', ' ', $topic ) ?? '';

		return trim( $topic );
	}
}
