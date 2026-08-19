<?php
/**
 * Deterministic topic fingerprinting.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Normalizes topics into comparable token fingerprints.
 */
class RevIt_Publisher_Topic_Fingerprint {

	/**
	 * Common English stopwords for automotive topics.
	 *
	 * @var string[]
	 */
	private const STOPWORDS = array(
		'a', 'an', 'the', 'and', 'or', 'for', 'to', 'of', 'in', 'on', 'with', 'is', 'are', 'was', 'were',
		'how', 'what', 'why', 'when', 'your', 'my', 'its', 'it', 'at', 'by', 'from', 'as', 'be', 'this', 'that',
	);

	/**
	 * Tokenize and normalize a topic string.
	 *
	 * @return string[]
	 */
	public function tokens( string $topic ): array {
		$topic = strtolower( $topic );
		$topic = preg_replace( '/[^\p{L}\p{N}\s]/u', ' ', $topic ) ?? '';
		$topic = preg_replace( '/\s+/', ' ', $topic ) ?? '';
		$parts = array_filter( array_map( 'trim', explode( ' ', trim( $topic ) ) ) );

		$tokens = array();
		foreach ( $parts as $part ) {
			if ( in_array( $part, self::STOPWORDS, true ) ) {
				continue;
			}
			$tokens[] = $this->normalize_token( $part );
		}

		return array_values( array_unique( array_filter( $tokens ) ) );
	}

	/**
	 * Jaccard similarity between two topics (0-1).
	 */
	public function similarity( string $topic_a, string $topic_b ): float {
		$a = $this->tokens( $topic_a );
		$b = $this->tokens( $topic_b );

		if ( empty( $a ) || empty( $b ) ) {
			return 0.0;
		}

		$intersection = array_intersect( $a, $b );
		$union        = array_unique( array_merge( $a, $b ) );

		return count( $union ) > 0 ? count( $intersection ) / count( $union ) : 0.0;
	}

	/**
	 * Classify overlap level.
	 *
	 * @return 'exact'|'high_overlap'|'moderate_overlap'|'distinct'
	 */
	public function classify( string $topic_a, string $topic_b ): string {
		$normalizer = new RevIt_Publisher_Topic_Normalizer();
		if ( $normalizer->normalize( $topic_a ) === $normalizer->normalize( $topic_b ) ) {
			return 'exact';
		}

		$score = $this->similarity( $topic_a, $topic_b );
		if ( $score >= 0.7 ) {
			return 'high_overlap';
		}
		if ( $score >= 0.4 ) {
			return 'moderate_overlap';
		}

		return 'distinct';
	}

	private function normalize_token( string $token ): string {
		if ( str_ends_with( $token, 'ies' ) && strlen( $token ) > 4 ) {
			return substr( $token, 0, -3 ) . 'y';
		}
		if ( str_ends_with( $token, 's' ) && strlen( $token ) > 3 && ! str_ends_with( $token, 'ss' ) ) {
			return rtrim( $token, 's' );
		}

		return $token;
	}
}
