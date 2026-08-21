<?php
/**
 * Find natural in-body anchors for internal links.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Prefers existing readable phrases over stuffed exact-match SEO anchors.
 */
final class RevIt_Publisher_Natural_Anchor_Finder {

	/**
	 * Awkward SEO-stuffed patterns.
	 */
	private const STUFFED = '/\bbest\b.+\b(reliability|problems|review)\b/i';

	/**
	 * @param string[] $candidates Preferred phrases, longest first after scoring.
	 * @return array{anchor: string, matched: bool}|null
	 */
	public function find( string $haystack, array $candidates ): ?array {
		$text = $this->plain_text( $haystack );
		if ( '' === $text ) {
			return null;
		}

		usort(
			$candidates,
			static function ( string $a, string $b ): int {
				return strlen( $b ) <=> strlen( $a );
			}
		);

		foreach ( $candidates as $candidate ) {
			$candidate = trim( $candidate );
			if ( '' === $candidate || ! $this->is_natural( $candidate ) ) {
				continue;
			}
			if ( $this->contains_phrase( $text, $candidate ) ) {
				return array(
					'anchor'  => $this->matched_casing( $haystack, $candidate ),
					'matched' => true,
				);
			}
		}

		return null;
	}

	/**
	 * Whether an anchor is acceptable to insert.
	 */
	public function is_natural( string $anchor ): bool {
		$anchor = trim( $anchor );
		$words  = preg_split( '/\s+/', $anchor ) ?: array();
		if ( '' === $anchor || count( $words ) > 8 ) {
			return false;
		}
		if ( preg_match( self::STUFFED, $anchor ) ) {
			return false;
		}

		$brands = 0;
		foreach ( array( 'bmw', 'toyota', 'honda', 'ford', 'subaru', 'porsche', 'hyundai', 'audi', 'volkswagen', 'vw' ) as $brand ) {
			if ( preg_match( '/\b' . preg_quote( $brand, '/' ) . '\b/i', $anchor ) ) {
				++$brands;
			}
		}

		return $brands <= 1;
	}

	/**
	 * Case-insensitive whole-phrase match.
	 */
	public function contains_phrase( string $text, string $phrase ): bool {
		$pattern = '/(?<![\p{L}\p{N}])' . preg_quote( $phrase, '/' ) . '(?![\p{L}\p{N}])/iu';

		return 1 === preg_match( $pattern, $text );
	}

	/**
	 * Strip tags / Gutenberg comments.
	 */
	public function plain_text( string $html ): string {
		$html = preg_replace( '/<!--.*?-->/s', ' ', $html ) ?? $html;
		$text = function_exists( 'wp_strip_all_tags' ) ? wp_strip_all_tags( $html, true ) : strip_tags( $html );
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text = preg_replace( '/\s+/', ' ', $text ) ?? $text;

		return trim( $text );
	}

	/**
	 * Return the actual casing from the source HTML when possible.
	 */
	private function matched_casing( string $haystack, string $phrase ): string {
		$plain = $this->plain_text( $haystack );
		if ( preg_match( '/(?<![\p{L}\p{N}])(' . preg_quote( $phrase, '/' ) . ')(?![\p{L}\p{N}])/iu', $plain, $matches ) ) {
			return $matches[1];
		}

		return $phrase;
	}
}
