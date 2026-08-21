<?php
/**
 * Parse public HTML for mechanical SEO signals.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * DOM-free HTML checks used by rendered-page validation.
 */
final class RevIt_Publisher_Rendered_Html_Analyzer {

	/**
	 * @return array<string, mixed>
	 */
	public function analyze( string $html, int $http_status = 0 ): array {
		$title = $this->first_match( '/<title[^>]*>(.*?)<\/title>/is', $html );
		$meta_description = $this->meta_content( $html, 'description' );
		$robots           = $this->meta_content( $html, 'robots' );
		$canonical        = $this->first_match( '/<link[^>]+rel=["\']canonical["\'][^>]+href=["\']([^"\']+)["\']/i', $html );
		if ( '' === $canonical ) {
			$canonical = $this->first_match( '/<link[^>]+href=["\']([^"\']+)["\'][^>]+rel=["\']canonical["\']/i', $html );
		}

		preg_match_all( '/<h1\b[^>]*>(.*?)<\/h1>/is', $html, $h1 );
		preg_match_all( '/<h2\b[^>]*>(.*?)<\/h2>/is', $html, $h2 );
		preg_match_all( '/<h3\b[^>]*>(.*?)<\/h3>/is', $html, $h3 );

		preg_match_all( '/<a\b[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is', $html, $anchors );
		preg_match_all( '/<img\b[^>]*>/i', $html, $imgs );

		$missing_alt = 0;
		foreach ( $imgs[0] as $img ) {
			if ( ! preg_match( '/\balt=/i', $img ) ) {
				++$missing_alt;
			}
		}

		$has_json_ld    = (bool) preg_match( '/application\/ld\+json/i', $html );
		$has_breadcrumb = (bool) preg_match( '/BreadcrumbList/i', $html ) || (bool) preg_match( '/breadcrumb/i', $html );

		return array(
			'http_status'          => $http_status,
			'title'                => $this->plain( $title ),
			'meta_description'     => $meta_description,
			'canonical'            => $canonical,
			'robots'               => $robots,
			'h1_count'             => count( $h1[0] ?? array() ),
			'h2_count'             => count( $h2[0] ?? array() ),
			'h3_count'             => count( $h3[0] ?? array() ),
			'internal_link_count'  => count( $anchors[1] ?? array() ),
			'image_count'          => count( $imgs[0] ?? array() ),
			'images_missing_alt'   => $missing_alt,
			'has_json_ld'          => $has_json_ld,
			'has_breadcrumb_output'=> $has_breadcrumb,
		);
	}

	private function meta_content( string $html, string $name ): string {
		$pattern = '/<meta[^>]+name=["\']' . preg_quote( $name, '/' ) . '["\'][^>]+content=["\']([^"\']*)["\']/i';
		$value   = $this->first_match( $pattern, $html );
		if ( '' !== $value ) {
			return $value;
		}

		$alt = '/<meta[^>]+content=["\']([^"\']*)["\'][^>]+name=["\']' . preg_quote( $name, '/' ) . '["\']/i';

		return $this->first_match( $alt, $html );
	}

	private function first_match( string $pattern, string $html ): string {
		if ( ! preg_match( $pattern, $html, $matches ) ) {
			return '';
		}

		return $this->plain( (string) ( $matches[1] ?? '' ) );
	}

	private function plain( string $value ): string {
		$value = html_entity_decode( strip_tags( $value ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		return trim( preg_replace( '/\s+/', ' ', $value ) ?? $value );
	}
}
