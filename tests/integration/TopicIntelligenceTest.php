<?php
/**
 * Topic intelligence integration tests.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

require_once __DIR__ . '/GraphTestHelper.php';

class TopicIntelligenceTest extends WP_UnitTestCase {

	use RevIt_Graph_Test_Helper;

	public function set_up(): void {
		parent::set_up();
		$this->set_up_graph_importer();
	}

	public function test_high_overlap_detection(): void {
		$this->import_graph_package( 'x3-coolant-loss.json' );
		$overlap = $this->load_graph_package( 'x3-water-pump.json' );
		$overlap->seo->primary_topic = 'BMW X3 M40i cooling problems';
		$this->graph_importer->import( $overlap );

		$fingerprint = new RevIt_Publisher_Topic_Fingerprint();
		$this->assertContains(
			$fingerprint->classify( 'BMW X3 M40i coolant loss', 'BMW X3 M40i cooling problems' ),
			array( 'high_overlap', 'moderate_overlap', 'exact' )
		);

		$overlaps = RevIt_Publisher_Services::topic_overlaps()->find_overlaps( true );
		$this->assertNotEmpty( $overlaps );
	}

	public function test_seo_score_explainability(): void {
		$post_id = $this->import_graph_package( 'x3-coolant-loss.json' );
		$analysis = RevIt_Publisher_Services::seo_score()->analyze( $post_id );
		$this->assertArrayHasKey( 'categories', $analysis );
		$this->assertArrayHasKey( 'metadata', $analysis['categories'] );
		$this->assertGreaterThan( 0, $analysis['total_score'] );
	}
}
