<?php
/**
 * Content renderer tests.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

/**
 * Tests for RevIt_Publisher_Content_Renderer.
 */
class ContentRendererTest extends WP_UnitTestCase {

	/**
	 * Renderer instance.
	 *
	 * @var RevIt_Publisher_Content_Renderer
	 */
	private RevIt_Publisher_Content_Renderer $renderer;

	/**
	 * Set up test case.
	 */
	public function set_up(): void {
		parent::set_up();
		$this->renderer = new RevIt_Publisher_Content_Renderer();
	}

	/**
	 * Heading block renders Gutenberg markup.
	 */
	public function test_heading_block(): void {
		$output = $this->renderer->render_block(
			(object) array(
				'type'  => 'heading',
				'level' => 2,
				'text'  => 'Test Heading',
			)
		);

		$this->assertStringContainsString( 'wp:heading', $output );
		$this->assertStringContainsString( 'Test Heading', $output );
	}

	/**
	 * Paragraph block renders Gutenberg markup.
	 */
	public function test_paragraph_block(): void {
		$output = $this->renderer->render_block(
			(object) array(
				'type' => 'paragraph',
				'text' => 'Test paragraph.',
			)
		);

		$this->assertStringContainsString( 'wp:paragraph', $output );
		$this->assertStringContainsString( 'Test paragraph.', $output );
	}

	/**
	 * Bullet list block renders Gutenberg markup.
	 */
	public function test_bullet_list_block(): void {
		$output = $this->renderer->render_block(
			(object) array(
				'type'  => 'bullet_list',
				'items' => array( 'One', 'Two' ),
			)
		);

		$this->assertStringContainsString( 'wp:list', $output );
		$this->assertStringContainsString( '<li>One</li>', $output );
	}

	/**
	 * Numbered list block renders ordered list markup.
	 */
	public function test_numbered_list_block(): void {
		$output = $this->renderer->render_block(
			(object) array(
				'type'  => 'numbered_list',
				'items' => array( 'First', 'Second' ),
			)
		);

		$this->assertStringContainsString( '"ordered":true', $output );
		$this->assertStringContainsString( '<ol', $output );
	}

	/**
	 * Table block renders table markup.
	 */
	public function test_table_block(): void {
		$output = $this->renderer->render_block(
			(object) array(
				'type'    => 'table',
				'headers' => array( 'Col A' ),
				'rows'    => array( array( 'Value' ) ),
			)
		);

		$this->assertStringContainsString( 'wp:table', $output );
		$this->assertStringContainsString( 'Col A', $output );
	}

	/**
	 * Quote block renders quote markup.
	 */
	public function test_quote_block(): void {
		$output = $this->renderer->render_block(
			(object) array(
				'type'        => 'quote',
				'text'        => 'Quoted text',
				'attribution' => 'Source',
			)
		);

		$this->assertStringContainsString( 'wp:quote', $output );
		$this->assertStringContainsString( 'Quoted text', $output );
	}

	/**
	 * Callout block renders group markup.
	 */
	public function test_callout_block(): void {
		$output = $this->renderer->render_block(
			(object) array(
				'type'    => 'callout',
				'variant' => 'warning',
				'text'    => 'Be careful',
			)
		);

		$this->assertStringContainsString( 'revit-callout--warning', $output );
		$this->assertStringContainsString( 'Be careful', $output );
	}

	/**
	 * Image placeholder renders editor placeholder text.
	 */
	public function test_image_placeholder_block(): void {
		$output = $this->renderer->render_block(
			(object) array(
				'type' => 'image_placeholder',
				'alt'  => 'BMW X3 M40i cooling system',
				'caption' => '',
			)
		);

		$this->assertStringContainsString( '[RevIt image needed: BMW X3 M40i cooling system]', $output );
	}

	/**
	 * FAQ section appends heading and Q/A blocks.
	 */
	public function test_faq_section(): void {
		$output = $this->renderer->render(
			(object) array(
				'intro'  => 'Intro text.',
				'blocks' => array(),
				'faq'    => array(
					(object) array(
						'question' => 'Question one?',
						'answer'   => 'Answer one.',
					),
				),
			)
		);

		$this->assertStringContainsString( 'Frequently Asked Questions', $output );
		$this->assertStringContainsString( 'Question one?', $output );
		$this->assertStringContainsString( 'Answer one.', $output );
	}
}
