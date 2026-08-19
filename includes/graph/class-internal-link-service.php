<?php
/**
 * Safe internal link insertion for Gutenberg content.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Suggests and applies contextual internal links in block content.
 */
class RevIt_Publisher_Internal_Link_Service {

	public const META_APPLIED_LINKS = '_revit_applied_links';

	/**
	 * Article resolver.
	 *
	 * @var RevIt_Publisher_Article_Resolver
	 */
	private RevIt_Publisher_Article_Resolver $resolver;

	/**
	 * Content graph.
	 *
	 * @var RevIt_Publisher_Content_Graph
	 */
	private RevIt_Publisher_Content_Graph $graph;

	/**
	 * Plugin settings.
	 *
	 * @var RevIt_Publisher_Settings
	 */
	private RevIt_Publisher_Settings $settings;

	/**
	 * Constructor.
	 */
	public function __construct(
		RevIt_Publisher_Article_Resolver $resolver,
		RevIt_Publisher_Content_Graph $graph,
		RevIt_Publisher_Settings $settings
	) {
		$this->resolver = $resolver;
		$this->graph    = $graph;
		$this->settings = $settings;
	}

	/**
	 * Get link suggestions for a post.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_suggestions( int $post_id ): array {
		$outbound  = $this->graph->get_outbound_relationships( $post_id );
		$applied   = $this->get_applied_targets( $post_id );
		$max       = $this->settings->max_suggested_links();
		$suggestions = array();

		foreach ( $outbound as $link ) {
			if ( 'resolved' !== ( $link['status'] ?? '' ) ) {
				continue;
			}

			$target_id = (int) ( $link['target_post_id'] ?? 0 );
			if ( $target_id <= 0 ) {
				continue;
			}

			if ( $this->settings->avoid_duplicate_target() && in_array( $target_id, $applied, true ) ) {
				continue;
			}

			if ( $this->content_already_links_to( $post_id, $target_id ) ) {
				continue;
			}

			$anchor   = (string) ( $link['preferred_anchor'] ?? '' );
			$location = $this->find_anchor_location( $post_id, $anchor );
			if ( null === $location ) {
				continue;
			}

			$suggestions[] = array(
				'target_article_key' => (string) ( $link['target_article_key'] ?? '' ),
				'target_post_id'     => $target_id,
				'target_title'       => (string) ( $link['target_title'] ?? '' ),
				'target_permalink'   => (string) ( $link['target_permalink'] ?? '' ),
				'anchor'             => $anchor,
				'relationship'       => (string) ( $link['relationship'] ?? '' ),
				'status'             => 'available',
				'block_index'        => $location['block_index'],
				'paragraph_label'    => sprintf(
					/* translators: %d: paragraph number */
					__( 'paragraph %d', 'revit-publisher' ),
					$location['paragraph_number']
				),
			);

			if ( count( $suggestions ) >= $max ) {
				break;
			}
		}

		return $suggestions;
	}

	/**
	 * Get backlink opportunities for newly imported target.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_backlink_opportunities( int $post_id ): array {
		$inbound = $this->graph->get_inbound_relationships( $post_id );
		$opportunities = array();

		foreach ( $inbound as $link ) {
			$source_id = (int) ( $link['source_post_id'] ?? 0 );
			if ( $source_id <= 0 ) {
				continue;
			}

			if ( $this->content_already_links_to( $source_id, $post_id ) ) {
				continue;
			}

			$anchor   = (string) ( $link['preferred_anchor'] ?? '' );
			$location = $this->find_anchor_location( $source_id, $anchor );
			if ( null === $location ) {
				continue;
			}

			$opportunities[] = array(
				'source_post_id'  => $source_id,
				'source_title'    => (string) ( $link['source_title'] ?? '' ),
				'target_post_id'  => $post_id,
				'anchor'          => $anchor,
				'relationship'    => (string) ( $link['relationship'] ?? '' ),
				'block_index'     => $location['block_index'],
				'paragraph_label' => sprintf(
					__( 'paragraph %d', 'revit-publisher' ),
					$location['paragraph_number']
				),
			);
		}

		return $opportunities;
	}

	/**
	 * Apply a specific link suggestion.
	 *
	 * @param array<string, mixed> $suggestion Suggestion payload.
	 * @return true|WP_Error
	 */
	public function apply_link( int $post_id, array $suggestion ) {
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new WP_Error( 'revit_forbidden', __( 'You cannot edit this post.', 'revit-publisher' ), array( 'status' => 403 ) );
		}

		$target_id = (int) ( $suggestion['target_post_id'] ?? 0 );
		$anchor    = sanitize_text_field( (string) ( $suggestion['anchor'] ?? '' ) );

		if ( $target_id <= 0 || '' === $anchor ) {
			return new WP_Error( 'revit_invalid_suggestion', __( 'Invalid link suggestion.', 'revit-publisher' ) );
		}

		if ( ! $this->resolver->is_managed( $target_id ) ) {
			return new WP_Error( 'revit_invalid_target', __( 'Target must be a RevIt-managed post.', 'revit-publisher' ) );
		}

		$permalink = get_permalink( $target_id );
		if ( ! is_string( $permalink ) || '' === $permalink ) {
			return new WP_Error( 'revit_invalid_target', __( 'Target permalink unavailable.', 'revit-publisher' ) );
		}

		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return new WP_Error( 'revit_invalid_post', __( 'Post not found.', 'revit-publisher' ) );
		}

		$blocks = parse_blocks( $post->post_content );
		$applied = $this->insert_link_in_blocks( $blocks, $anchor, $permalink, (int) ( $suggestion['block_index'] ?? -1 ) );

		if ( ! $applied ) {
			return new WP_Error( 'revit_link_not_applied', __( 'Could not apply link safely.', 'revit-publisher' ) );
		}

		$updated = wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => serialize_blocks( $blocks ),
			),
			true
		);

		if ( is_wp_error( $updated ) ) {
			return $updated;
		}

		$this->record_applied_link( $post_id, $target_id, $anchor );

		return true;
	}

	/**
	 * Check if post content already links to target.
	 */
	public function content_already_links_to( int $post_id, int $target_id ): bool {
		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return false;
		}

		$permalink = get_permalink( $target_id );
		if ( ! is_string( $permalink ) || '' === $permalink ) {
			return false;
		}

		return str_contains( $post->post_content, esc_url( $permalink ) );
	}

	/**
	 * Find eligible anchor location in blocks.
	 *
	 * @return array{block_index: int, paragraph_number: int}|null
	 */
	public function find_anchor_location( int $post_id, string $anchor ): ?array {
		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post || '' === trim( $anchor ) ) {
			return null;
		}

		$blocks           = parse_blocks( $post->post_content );
		$paragraph_number = 0;

		foreach ( $blocks as $index => $block ) {
			$result = $this->find_in_block( $block, $anchor, $index, $paragraph_number );
			if ( null !== $result ) {
				return $result;
			}
		}

		return null;
	}

	/**
	 * @param array<string, mixed> $block Block data.
	 * @return array{block_index: int, paragraph_number: int}|null
	 */
	private function find_in_block( array $block, string $anchor, int $index, int &$paragraph_number ): ?array {
		$name = (string) ( $block['blockName'] ?? '' );

		if ( in_array( $name, array( 'core/heading', 'core/code', 'core/preformatted', 'core/quote' ), true ) ) {
			return null;
		}

		if ( 'core/paragraph' === $name || 'core/list' === $name ) {
			++$paragraph_number;
			$html = (string) ( $block['innerHTML'] ?? '' );
			if ( $this->can_insert_anchor( $html, $anchor ) ) {
				return array(
					'block_index'      => $index,
					'paragraph_number' => $paragraph_number,
				);
			}
		}

		if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
			foreach ( $block['innerBlocks'] as $child_index => $child ) {
				$result = $this->find_in_block( $child, $anchor, $index, $paragraph_number );
				if ( null !== $result ) {
					return $result;
				}
				unset( $child_index );
			}
		}

		return null;
	}

	/**
	 * Insert link into blocks at optional specific index.
	 *
	 * @param array<int, array<string, mixed>> $blocks Blocks array (by reference).
	 */
	private function insert_link_in_blocks( array &$blocks, string $anchor, string $url, int $block_index ): bool {
		foreach ( $blocks as $index => &$block ) {
			if ( $block_index >= 0 && $index !== $block_index ) {
				continue;
			}

			$name = (string) ( $block['blockName'] ?? '' );
			if ( in_array( $name, array( 'core/heading', 'core/code', 'core/preformatted' ), true ) ) {
				continue;
			}

			if ( in_array( $name, array( 'core/paragraph', 'core/list' ), true ) ) {
				$html = (string) ( $block['innerHTML'] ?? '' );
				if ( ! $this->can_insert_anchor( $html, $anchor ) ) {
					continue;
				}

				$new_html = $this->wrap_anchor( $html, $anchor, $url );
				if ( null === $new_html ) {
					continue;
				}

				$block['innerHTML'] = $new_html;
				if ( isset( $block['innerContent'][0] ) ) {
					$block['innerContent'][0] = $new_html;
				}

				return true;
			}

			if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
				if ( $this->insert_link_in_blocks( $block['innerBlocks'], $anchor, $url, $block_index ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Whether anchor can be inserted in HTML fragment.
	 */
	private function can_insert_anchor( string $html, string $anchor ): bool {
		if ( str_contains( $html, '<a ' ) ) {
			// Allow if anchor is outside existing links — simplified: skip blocks with any link.
			return false;
		}

		return false !== stripos( wp_strip_all_tags( $html ), $anchor );
	}

	/**
	 * Wrap first case-insensitive anchor occurrence.
	 */
	private function wrap_anchor( string $html, string $anchor, string $url ): ?string {
		$pattern = '/' . preg_quote( $anchor, '/' ) . '/i';
		$count   = 0;
		$result  = preg_replace_callback(
			$pattern,
			static function ( array $matches ) use ( $url, &$count ): string {
				if ( $count > 0 ) {
					return $matches[0];
				}
				++$count;
				return '<a href="' . esc_url( $url ) . '">' . esc_html( $matches[0] ) . '</a>';
			},
			$html,
			1
		);

		return ( 1 === $count && is_string( $result ) ) ? $result : null;
	}

	/**
	 * @return int[]
	 */
	private function get_applied_targets( int $post_id ): array {
		$applied = get_post_meta( $post_id, self::META_APPLIED_LINKS, true );
		if ( ! is_array( $applied ) ) {
			return array();
		}

		return array_map( 'intval', array_column( $applied, 'target_post_id' ) );
	}

	/**
	 * Record applied link in post meta.
	 */
	private function record_applied_link( int $post_id, int $target_id, string $anchor ): void {
		$applied   = get_post_meta( $post_id, self::META_APPLIED_LINKS, true );
		$applied   = is_array( $applied ) ? $applied : array();
		$applied[] = array(
			'target_post_id' => $target_id,
			'anchor'         => $anchor,
			'applied_at'     => gmdate( 'c' ),
		);
		update_post_meta( $post_id, self::META_APPLIED_LINKS, $applied );
	}

	/**
	 * Apply multiple link suggestions with per-item validation.
	 *
	 * @param array<int, array<string, mixed>> $suggestions Suggestion payloads with source_post_id.
	 * @return array{applied: int, skipped: int, results: array<int, array<string, mixed>>}
	 */
	public function apply_batch( array $suggestions ): array {
		$max     = RevIt_Publisher_Services::settings()->max_batch_links();
		$results = array();
		$applied = 0;
		$skipped = 0;

		foreach ( array_slice( $suggestions, 0, $max ) as $index => $suggestion ) {
			$post_id = (int) ( $suggestion['source_post_id'] ?? 0 );
			if ( $post_id <= 0 ) {
				++$skipped;
				$results[] = array(
					'index'   => $index,
					'success' => false,
					'message' => __( 'Missing source post.', 'revit-publisher' ),
				);
				continue;
			}

			$fresh = null;
			foreach ( $this->get_suggestions( $post_id ) as $candidate ) {
				if ( (int) ( $candidate['target_post_id'] ?? 0 ) === (int) ( $suggestion['target_post_id'] ?? -1 )
					&& (string) ( $candidate['anchor'] ?? '' ) === (string) ( $suggestion['anchor'] ?? '' )
				) {
					$fresh = $candidate;
					break;
				}
			}

			if ( null === $fresh ) {
				++$skipped;
				$results[] = array(
					'index'    => $index,
					'post_id'  => $post_id,
					'success'  => false,
					'message'  => __( 'Suggestion is stale or invalid.', 'revit-publisher' ),
				);
				continue;
			}

			$result = $this->apply_link( $post_id, $fresh );
			if ( is_wp_error( $result ) ) {
				++$skipped;
				$results[] = array(
					'index'   => $index,
					'post_id' => $post_id,
					'success' => false,
					'message' => $result->get_error_message(),
				);
				continue;
			}

			++$applied;
			$results[] = array(
				'index'   => $index,
				'post_id' => $post_id,
				'success' => true,
			);
		}

		return array(
			'applied' => $applied,
			'skipped' => $skipped,
			'results' => $results,
		);
	}
}
