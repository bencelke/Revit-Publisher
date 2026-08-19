<?php
/**
 * Public SEO output integration tests.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

require_once __DIR__ . '/GraphTestHelper.php';

/**
 * Tests for Phase 2 public SEO output.
 */
class SeoOutputTest extends WP_UnitTestCase {

	use RevIt_Graph_Test_Helper;

	/**
	 * Set up.
	 */
	public function set_up(): void {
		parent::set_up();
		$this->set_up_graph_importer();

		$settings = RevIt_Publisher_Services::settings();
		$resolver = RevIt_Publisher_Services::resolver();
		$graph    = RevIt_Publisher_Services::graph();

		( new RevIt_Publisher_Public_SEO_Output( $settings, $resolver ) )->init();
		( new RevIt_Publisher_Structured_Data_Output( $settings, $resolver, $graph ) )->init();
	}

	/**
	 * Meta description output for managed post.
	 */
	public function test_meta_description_output(): void {
		$post_id = $this->import_graph_package( 'x3-coolant-loss.json' );
		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'publish',
			)
		);

		$this->go_to( get_permalink( $post_id ) );

		ob_start();
		do_action( 'wp_head' );
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'name="description"', $html );
		$this->assertStringContainsString( 'B58 engine', $html );
	}

	/**
	 * Canonical auto uses permalink.
	 */
	public function test_canonical_auto(): void {
		$post_id = $this->import_graph_package( 'x3-coolant-loss.json' );
		wp_update_post( array( 'ID' => $post_id, 'post_status' => 'publish' ) );

		$this->go_to( get_permalink( $post_id ) );

		ob_start();
		do_action( 'wp_head' );
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'rel="canonical"', $html );
		$this->assertStringContainsString( esc_url( get_permalink( $post_id ) ), $html );
	}

	/**
	 * Robots index/follow output.
	 */
	public function test_robots_output(): void {
		$post_id = $this->import_graph_package( 'x3-coolant-loss.json' );
		wp_update_post( array( 'ID' => $post_id, 'post_status' => 'publish' ) );

		$this->go_to( get_permalink( $post_id ) );

		ob_start();
		do_action( 'wp_head' );
		$html = (string) ob_get_clean();

		$this->assertMatchesRegularExpression( '/robots|index|follow/i', $html );
	}

	/**
	 * Unmanaged posts get no RevIt SEO output.
	 */
	public function test_no_output_for_unmanaged_post(): void {
		$post_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		$this->go_to( get_permalink( $post_id ) );

		ob_start();
		do_action( 'wp_head' );
		$html = (string) ob_get_clean();

		$this->assertStringNotContainsString( 'application/ld+json', $html );
	}

	/**
	 * Article JSON-LD shape.
	 */
	public function test_article_json_ld(): void {
		$post_id = $this->import_graph_package( 'x3-coolant-loss.json' );
		wp_update_post( array( 'ID' => $post_id, 'post_status' => 'publish' ) );

		$this->go_to( get_permalink( $post_id ) );

		ob_start();
		do_action( 'wp_head' );
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( '"@type":"Article"', $html );
		$this->assertStringContainsString( 'headline', $html );
	}

	/**
	 * SEO plugin conflict disables output.
	 */
	public function test_seo_plugin_conflict_disables_output(): void {
		update_option(
			'active_plugins',
			array( 'wordpress-seo/wp-seo.php' )
		);

		$post_id = $this->import_graph_package( 'x3-coolant-loss.json' );
		wp_update_post( array( 'ID' => $post_id, 'post_status' => 'publish' ) );

		$this->assertFalse( RevIt_Publisher_Services::settings()->public_seo_output_enabled() );
	}
}
