<?php
/**
 * Gutenberg content renderer for article packages.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Converts structured package content into WordPress block markup.
 */
class RevIt_Publisher_Content_Renderer {

	/**
	 * Render full post content from package content object.
	 */
	public function render( object $content ): string {
		$blocks = array();

		if ( ! empty( $content->intro ) ) {
			$blocks[] = $this->render_paragraph( (string) $content->intro );
		}

		if ( isset( $content->blocks ) && is_array( $content->blocks ) ) {
			foreach ( $content->blocks as $block ) {
				if ( ! is_object( $block ) ) {
					continue;
				}
				$rendered = $this->render_block( $block );
				if ( '' !== $rendered ) {
					$blocks[] = $rendered;
				}
			}
		}

		if ( isset( $content->faq ) && is_array( $content->faq ) && ! empty( $content->faq ) ) {
			$blocks[] = $this->render_faq_section( $content->faq );
		}

		return implode( "\n\n", array_filter( $blocks ) );
	}

	/**
	 * Render a single content block.
	 */
	public function render_block( object $block ): string {
		$type = (string) ( $block->type ?? '' );

		return match ( $type ) {
			'heading'            => $this->render_heading( $block ),
			'paragraph'          => $this->render_paragraph( (string) ( $block->text ?? '' ) ),
			'bullet_list'        => $this->render_list( $block, false ),
			'numbered_list'      => $this->render_list( $block, true ),
			'table'              => $this->render_table( $block ),
			'callout'            => $this->render_callout( $block ),
			'quote'              => $this->render_quote( $block ),
			'image_placeholder'  => $this->render_image_placeholder( $block ),
			default              => '',
		};
	}

	/**
	 * Render heading block.
	 */
	private function render_heading( object $block ): string {
		$level = max( 2, min( 4, (int) ( $block->level ?? 2 ) ) );
		$text  = esc_html( (string) ( $block->text ?? '' ) );

		return sprintf(
			"<!-- wp:heading {\"level\":%d} -->\n<h%d class=\"wp-block-heading\">%s</h%d>\n<!-- /wp:heading -->",
			$level,
			$level,
			$text,
			$level
		);
	}

	/**
	 * Render paragraph block.
	 */
	private function render_paragraph( string $text ): string {
		return sprintf(
			"<!-- wp:paragraph -->\n<p>%s</p>\n<!-- /wp:paragraph -->",
			esc_html( $text )
		);
	}

	/**
	 * Render list block.
	 */
	private function render_list( object $block, bool $ordered ): string {
		$items = isset( $block->items ) && is_array( $block->items ) ? $block->items : array();
		if ( empty( $items ) ) {
			return '';
		}

		$tag      = $ordered ? 'ol' : 'ul';
		$list_tag = $ordered ? 'ol' : 'ul';
		$class    = $ordered ? 'wp-block-list' : 'wp-block-list';
		$items_html = '';

		foreach ( $items as $item ) {
			$items_html .= sprintf( '<li>%s</li>', esc_html( (string) $item ) );
		}

		if ( $ordered ) {
			return sprintf(
				"<!-- wp:list {\"ordered\":true} -->\n<%s class=\"%s\">%s</%s>\n<!-- /wp:list -->",
				$list_tag,
				esc_attr( $class ),
				$items_html,
				$list_tag
			);
		}

		return sprintf(
			"<!-- wp:list -->\n<%s class=\"%s\">%s</%s>\n<!-- /wp:list -->",
			$tag,
			esc_attr( $class ),
			$items_html,
			$tag
		);
	}

	/**
	 * Render table block.
	 */
	private function render_table( object $block ): string {
		$headers = isset( $block->headers ) && is_array( $block->headers ) ? $block->headers : array();
		$rows    = isset( $block->rows ) && is_array( $block->rows ) ? $block->rows : array();

		$thead = '';
		if ( ! empty( $headers ) ) {
			$cells = '';
			foreach ( $headers as $header ) {
				$cells .= sprintf( '<th>%s</th>', esc_html( (string) $header ) );
			}
			$thead = sprintf( '<thead><tr>%s</tr></thead>', $cells );
		}

		$tbody = '';
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$cells = '';
			foreach ( $row as $cell ) {
				$cells .= sprintf( '<td>%s</td>', esc_html( (string) $cell ) );
			}
			$tbody .= sprintf( '<tr>%s</tr>', $cells );
		}

		return sprintf(
			"<!-- wp:table -->\n<figure class=\"wp-block-table\"><table>%s<tbody>%s</tbody></table></figure>\n<!-- /wp:table -->",
			$thead,
			$tbody
		);
	}

	/**
	 * Render callout using a group + paragraph composition.
	 */
	private function render_callout( object $block ): string {
		$variant = sanitize_key( (string) ( $block->variant ?? 'info' ) );
		$text    = esc_html( (string) ( $block->text ?? '' ) );
		$class   = esc_attr( $variant );
		$label   = esc_html( ucfirst( $variant ) );

		return '<!-- wp:group {"className":"revit-callout revit-callout--' . $class . '","layout":{"type":"constrained"}} -->' . "\n"
			. '<div class="wp-block-group revit-callout revit-callout--' . $class . '"><!-- wp:paragraph -->' . "\n"
			. '<p><strong>' . $label . ':</strong> ' . $text . '</p>' . "\n"
			. '<!-- /wp:paragraph --></div>' . "\n"
			. '<!-- /wp:group -->';
	}

	/**
	 * Render quote block.
	 */
	private function render_quote( object $block ): string {
		$text         = esc_html( (string) ( $block->text ?? '' ) );
		$attribution  = isset( $block->attribution ) ? esc_html( (string) $block->attribution ) : '';

		if ( '' !== $attribution ) {
			return sprintf(
				"<!-- wp:quote -->\n<blockquote class=\"wp-block-quote\"><p>%s</p><cite>%s</cite></blockquote>\n<!-- /wp:quote -->",
				$text,
				$attribution
			);
		}

		return sprintf(
			"<!-- wp:quote -->\n<blockquote class=\"wp-block-quote\"><p>%s</p></blockquote>\n<!-- /wp:quote -->",
			$text
		);
	}

	/**
	 * Render image placeholder as editor-visible paragraph.
	 */
	private function render_image_placeholder( object $block ): string {
		$alt = (string) ( $block->alt ?? 'Image needed' );
		$text = sprintf( '[RevIt image needed: %s]', $alt );

		return $this->render_paragraph( $text );
	}

	/**
	 * Render FAQ section.
	 *
	 * @param array<int, object> $faq_items FAQ items.
	 */
	private function render_faq_section( array $faq_items ): string {
		$blocks = array(
			$this->render_heading(
				(object) array(
					'level' => 2,
					'text'  => 'Frequently Asked Questions',
				)
			),
		);

		foreach ( $faq_items as $item ) {
			if ( ! is_object( $item ) ) {
				continue;
			}
			$blocks[] = $this->render_heading(
				(object) array(
					'level' => 3,
					'text'  => (string) ( $item->question ?? '' ),
				)
			);
			$blocks[] = $this->render_paragraph( (string) ( $item->answer ?? '' ) );
		}

		return implode( "\n\n", $blocks );
	}
}
