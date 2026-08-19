<?php
/**
 * Content graph and linking integration tests.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

require_once __DIR__ . '/GraphTestHelper.php';

/**
 * Tests for Phase 2 graph services.
 */
class GraphServicesTest extends WP_UnitTestCase {

	use RevIt_Graph_Test_Helper;

	/**
	 * Set up.
	 */
	public function set_up(): void {
		parent::set_up();
		$this->set_up_graph_importer();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	/**
	 * Article resolver finds imported article.
	 */
	public function test_resolver_finds_existing_article(): void {
		$post_id = $this->import_graph_package( 'x3-coolant-loss.json' );
		$resolved = RevIt_Publisher_Services::resolver()->resolve( 'bmw-x3-g01-m40i-coolant-loss' );

		$this->assertIsArray( $resolved );
		$this->assertSame( $post_id, $resolved['post_id'] );
		$this->assertNotEmpty( $resolved['permalink'] );
	}

	/**
	 * Missing article returns null.
	 */
	public function test_resolver_missing_article(): void {
		$this->assertNull( RevIt_Publisher_Services::resolver()->resolve( 'does-not-exist' ) );
	}

	/**
	 * Non-managed post is rejected.
	 */
	public function test_resolver_rejects_non_managed_post(): void {
		$post_id = self::factory()->post->create();
		$this->assertNull( RevIt_Publisher_Services::resolver()->resolve_post( $post_id ) );
	}

	/**
	 * Pillar unresolved before import.
	 */
	public function test_pillar_unresolved_before_import(): void {
		$post_id = $this->import_graph_package( 'x3-coolant-loss.json' );
		$pillar  = RevIt_Publisher_Services::graph()->get_pillar_article( $post_id );

		$this->assertSame( 'pillar_planned', $pillar['status'] );
	}

	/**
	 * Pillar resolves after import.
	 */
	public function test_pillar_resolves_after_import(): void {
		$support_id = $this->import_graph_package( 'x3-coolant-loss.json' );
		$this->import_graph_package( 'x3-cooling-pillar.json' );

		$pillar = RevIt_Publisher_Services::graph()->get_pillar_article( $support_id );
		$this->assertSame( 'resolved', $pillar['status'] );
		$this->assertSame( 'bmw-x3-g01-m40i-cooling-guide', $pillar['article_key'] );
	}

	/**
	 * Outbound and inbound relationships.
	 */
	public function test_inbound_outbound_relationships(): void {
		$coolant_id = $this->import_graph_package( 'x3-coolant-loss.json' );
		$pump_id    = $this->import_graph_package( 'x3-water-pump.json' );

		$outbound = RevIt_Publisher_Services::graph()->get_outbound_relationships( $coolant_id );
		$this->assertNotEmpty( $outbound );

		$inbound = RevIt_Publisher_Services::graph()->get_inbound_relationships( $pump_id );
		$this->assertNotEmpty( $inbound );
	}

	/**
	 * Cluster grouping works.
	 */
	public function test_cluster_grouping(): void {
		$this->import_graph_package( 'x3-coolant-loss.json' );
		$this->import_graph_package( 'x3-water-pump.json' );

		$clusters = RevIt_Publisher_Services::graph()->get_cluster_summaries();
		$this->assertNotEmpty( $clusters );
		$this->assertGreaterThanOrEqual( 2, $clusters[0]['article_count'] );
	}

	/**
	 * Vehicle grouping works.
	 */
	public function test_vehicle_grouping(): void {
		$this->import_graph_package( 'x3-coolant-loss.json' );
		$vehicles = RevIt_Publisher_Services::graph()->get_vehicle_summaries();
		$this->assertNotEmpty( $vehicles );
		$this->assertStringContainsString( 'BMW', $vehicles[0]['label'] );
	}

	/**
	 * Link suggestion and safe insertion preserves blocks.
	 */
	public function test_link_insertion_preserves_gutenberg(): void {
		$coolant_id = $this->import_graph_package( 'x3-coolant-loss.json' );
		$this->import_graph_package( 'x3-water-pump.json' );

		$suggestions = RevIt_Publisher_Services::link_service()->get_suggestions( $coolant_id );
		$this->assertNotEmpty( $suggestions );

		$result = RevIt_Publisher_Services::link_service()->apply_link( $coolant_id, $suggestions[0] );
		$this->assertTrue( $result );

		$post = get_post( $coolant_id );
		$this->assertIsString( $post->post_content );
		$this->assertStringContainsString( '<a href=', $post->post_content );
		$this->assertNotEmpty( parse_blocks( $post->post_content ) );
	}

	/**
	 * Headings are not modified when inserting links.
	 */
	public function test_link_insertion_skips_headings(): void {
		$coolant_id = $this->import_graph_package( 'x3-coolant-loss.json' );
		$post       = get_post( $coolant_id );
		$this->assertStringContainsString( 'Common Symptoms', $post->post_content );
		$this->assertStringNotContainsString( '<a href=', $post->post_content );
	}

	/**
	 * Orphan detection.
	 */
	public function test_orphan_detection(): void {
		$post_id = $this->import_graph_package( 'x3-coolant-loss.json' );
		$health  = RevIt_Publisher_Services::health_service()->get_post_health( $post_id );
		$this->assertTrue( $health['is_orphan'] );
	}

	/**
	 * Duplicate topic detection.
	 */
	public function test_duplicate_topic_detection(): void {
		$this->import_graph_package( 'x3-coolant-loss.json' );
		$dupe = $this->load_graph_package( 'x3-water-pump.json' );
		$dupe->seo->primary_topic = 'bmw x3 m40i coolant loss';

		$result = $this->graph_importer->import( $dupe );
		$this->assertTrue( $result['success'] );

		$duplicates = RevIt_Publisher_Services::health_service()->find_duplicate_topics();
		$this->assertNotEmpty( $duplicates );
	}

	/**
	 * Package hash comparison.
	 */
	public function test_package_hash_compare(): void {
		$post_id = $this->import_graph_package( 'x3-coolant-loss.json' );
		$package = $this->load_graph_package( 'x3-coolant-loss.json' );

		$same = RevIt_Publisher_Services::health_service()->compare_package_hash( $post_id, $package );
		$this->assertSame( 'unchanged', $same['status'] );

		$package->article->title = 'Changed title';
		$changed = RevIt_Publisher_Services::health_service()->compare_package_hash( $post_id, $package );
		$this->assertSame( 'changed', $changed['status'] );
	}
}
