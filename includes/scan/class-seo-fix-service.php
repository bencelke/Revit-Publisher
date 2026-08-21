<?php
/**
 * Apply only mechanical SEO fixes that are safe without rewriting.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Safe automatic fixes vs human-review-required changes.
 */
class RevIt_Publisher_Seo_Fix_Service {

	/**
	 * Codes that may be applied without rewriting prose.
	 */
	public const SAFE_CODES = array(
		'missing_seo_title',
		'missing_meta_description',
		'missing_canonical',
		'missing_article_schema_intent',
		'missing_breadcrumb_schema_intent',
		'missing_manufacturer',
		'missing_model',
		'missing_article_type',
		'missing_cluster',
		'extra_content_h1',
	);

	/**
	 * @param array<string, mixed> $scan Scan snapshot.
	 * @param array<int, array<string, mixed>> $issues Checklist issues.
	 * @return array<int, array<string, mixed>>
	 */
	public function propose( array $scan, array $issues ): array {
		$proposed = array();
		foreach ( $issues as $issue ) {
			$code = (string) ( $issue['code'] ?? '' );
			if ( empty( $issue['safe_fix'] ) && ! in_array( $code, self::SAFE_CODES, true ) ) {
				continue;
			}
			$before = $this->describe_before( $scan, $code );
			$after  = $this->describe_after( $scan, $code );
			if ( null === $after ) {
				continue;
			}
			$proposed[] = array(
				'code'   => $code,
				'label'  => (string) ( $issue['message'] ?? $code ),
				'before' => $before,
				'after'  => $after,
			);
		}

		return $proposed;
	}

	/**
	 * Apply safe fixes. Never publishes. Never rewrites paragraphs.
	 *
	 * @param string[] $codes Codes to apply.
	 * @return array<string, mixed>
	 */
	public function apply( int $post_id, array $codes ): array {
		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return array( 'success' => false, 'message' => 'Post not found.' );
		}

		$status_before = $post->post_status;
		$applied       = array();

		foreach ( $codes as $code ) {
			$code = sanitize_key( (string) $code );
			if ( ! in_array( $code, self::SAFE_CODES, true ) ) {
				continue;
			}
			if ( $this->apply_code( $post_id, $code ) ) {
				$applied[] = $code;
			}
		}

		$after = get_post( $post_id );

		return array(
			'success'          => true,
			'applied'          => $applied,
			'post_status'      => $after instanceof WP_Post ? $after->post_status : $status_before,
			'published'        => false,
			'status_unchanged' => $after instanceof WP_Post && $after->post_status === $status_before,
		);
	}

	private function apply_code( int $post_id, string $code ): bool {
		switch ( $code ) {
			case 'missing_seo_title':
				$current = (string) get_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::SEO_TITLE, true );
				if ( '' !== $current ) {
					return false;
				}
				$title = get_the_title( $post_id );
				update_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::SEO_TITLE, sanitize_text_field( (string) $title ) );
				return true;

			case 'missing_meta_description':
				$current = (string) get_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::META_DESCRIPTION, true );
				if ( '' !== $current ) {
					return false;
				}
				$post = get_post( $post_id );
				$excerpt = $post instanceof WP_Post && '' !== $post->post_excerpt
					? $post->post_excerpt
					: wp_trim_words( wp_strip_all_tags( $post instanceof WP_Post ? $post->post_content : '' ), 28 );
				update_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::META_DESCRIPTION, sanitize_textarea_field( $excerpt ) );
				return true;

			case 'missing_canonical':
				$current = (string) get_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::CANONICAL, true );
				if ( '' !== $current ) {
					return false;
				}
				update_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::CANONICAL, 'auto' );
				return true;

			case 'missing_article_schema_intent':
			case 'missing_breadcrumb_schema_intent':
				$schema = get_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::STRUCTURED_DATA, true );
				if ( ! is_array( $schema ) ) {
					$schema = array();
				}
				$schema['article']     = true;
				$schema['breadcrumbs'] = true;
				update_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::STRUCTURED_DATA, $schema );
				return true;

			case 'missing_manufacturer':
			case 'missing_model':
			case 'missing_article_type':
			case 'missing_cluster':
				return $this->repair_taxonomy( $post_id );

			case 'extra_content_h1':
				return $this->demote_content_h1( $post_id );
		}

		return false;
	}

	private function repair_taxonomy( int $post_id ): bool {
		$package_vehicle = array(
			'manufacturer' => get_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::VEHICLE_MANUFACTURER, true ),
			'model'        => get_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::VEHICLE_MODEL, true ),
			'generation'   => get_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::VEHICLE_GENERATION, true ),
			'trim'         => get_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::VEHICLE_TRIM, true ),
		);
		$service = new RevIt_Publisher_Vehicle_Taxonomy_Service();
		$service->sync_post( $post_id, (object) $package_vehicle );

		$cluster_key = (string) get_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::CLUSTER_KEY, true );
		if ( '' !== $cluster_key ) {
			( new RevIt_Publisher_Cluster_Service() )->sync_post(
				$post_id,
				(object) array(
					'cluster_key' => $cluster_key,
					'name'        => $cluster_key,
				)
			);
		}

		return true;
	}

	private function demote_content_h1( int $post_id ): bool {
		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return false;
		}

		$blocks = parse_blocks( $post->post_content );
		$changed = $this->demote_heading_blocks( $blocks );
		if ( ! $changed ) {
			return false;
		}

		wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => serialize_blocks( $blocks ),
				'post_status'  => $post->post_status,
			)
		);

		return true;
	}

	/**
	 * @param array<int, array<string, mixed>> $blocks Blocks.
	 */
	private function demote_heading_blocks( array &$blocks ): bool {
		$changed = false;
		foreach ( $blocks as &$block ) {
			if ( 'core/heading' === ( $block['blockName'] ?? '' ) ) {
				$level = (int) ( $block['attrs']['level'] ?? 2 );
				$html  = (string) ( $block['innerHTML'] ?? '' );
				if ( 1 === $level || preg_match( '/<h1\b/i', $html ) ) {
					$block['attrs']['level'] = 2;
					$block['innerHTML']      = preg_replace( '/<(\/?)h1\b/i', '<$1h2', $html ) ?? $html;
					if ( isset( $block['innerContent'][0] ) ) {
						$block['innerContent'][0] = $block['innerHTML'];
					}
					$changed = true;
				}
			}
			if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
				if ( $this->demote_heading_blocks( $block['innerBlocks'] ) ) {
					$changed = true;
				}
			}
		}

		return $changed;
	}

	/**
	 * @param array<string, mixed> $scan Scan.
	 */
	private function describe_before( array $scan, string $code ): string {
		return match ( $code ) {
			'missing_seo_title' => (string) ( $scan['seo_title'] ?? '' ),
			'missing_meta_description' => (string) ( $scan['meta_description'] ?? '' ),
			'missing_canonical' => (string) ( $scan['canonical'] ?? '' ),
			'extra_content_h1' => 'Content H1 count: ' . (int) ( $scan['heading_audit']['content_h1_count'] ?? 0 ),
			default => 'missing',
		};
	}

	/**
	 * @param array<string, mixed> $scan Scan.
	 */
	private function describe_after( array $scan, string $code ): ?string {
		return match ( $code ) {
			'missing_seo_title' => (string) ( $scan['title'] ?? '' ),
			'missing_meta_description' => 'Use excerpt / first paragraph',
			'missing_canonical' => 'auto',
			'missing_article_schema_intent' => 'article schema intent = true',
			'missing_breadcrumb_schema_intent' => 'breadcrumb schema intent = true',
			'missing_manufacturer', 'missing_model', 'missing_article_type', 'missing_cluster' => 'Restore from stored RevIt vehicle/cluster meta',
			'extra_content_h1' => 'Demote body H1 headings to H2',
			default => null,
		};
	}
}
