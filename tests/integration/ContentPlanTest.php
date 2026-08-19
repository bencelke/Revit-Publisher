<?php
/**
 * Content plan integration tests.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

require_once __DIR__ . '/GraphTestHelper.php';

class ContentPlanTest extends WP_UnitTestCase {

	use RevIt_Graph_Test_Helper;

	public function set_up(): void {
		parent::set_up();
		RevIt_Publisher_Content_Plan_Post_Type::register();
		$this->set_up_graph_importer();
	}

	public function test_plan_import_and_reconciliation(): void {
		$this->import_graph_package( 'x3-cooling-pillar.json' );
		$this->import_graph_package( 'x3-coolant-loss.json' );
		$this->import_graph_package( 'x3-water-pump.json' );

		$json     = file_get_contents( REVIT_PUBLISHER_PLUGIN_DIR . 'examples/content-plan-valid.json' );
		$importer = new RevIt_Publisher_Content_Plan_Importer(
			new RevIt_Publisher_Content_Plan_Validator(),
			new RevIt_Publisher_Package_Hash()
		);
		$result = $importer->import( json_decode( (string) $json, false ) );
		$this->assertTrue( $result['success'] );

		$coverage = RevIt_Publisher_Services::plan_service()->get_coverage( (int) $result['plan_id'] );
		$this->assertSame( 13, $coverage['summary']['planned_articles'] );
		$this->assertGreaterThanOrEqual( 3, $coverage['summary']['existing_articles'] );
		$this->assertNotEmpty( $coverage['missing'] );
	}

	public function test_article_request_export(): void {
		$json     = file_get_contents( REVIT_PUBLISHER_PLUGIN_DIR . 'examples/content-plan-valid.json' );
		$importer = new RevIt_Publisher_Content_Plan_Importer(
			new RevIt_Publisher_Content_Plan_Validator(),
			new RevIt_Publisher_Package_Hash()
		);
		$result = $importer->import( json_decode( (string) $json, false ) );
		$export = ( new RevIt_Publisher_Article_Request_Exporter( RevIt_Publisher_Services::plan_service() ) )
			->export_single( (int) $result['plan_id'], 'bmw-x3-g01-m40i-thermostat-problems' );

		$this->assertSame( 'revit-article-request-v1', $export['request_type'] );
		$this->assertSame( 'bmw-x3-g01-m40i-thermostat-problems', $export['article_key'] );
	}
}
