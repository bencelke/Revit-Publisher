<?php
/**
 * Vehicle article counts and SEO scan integration tests.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

require_once __DIR__ . '/GraphTestHelper.php';

class VehicleCountAndSeoScanTest extends WP_UnitTestCase {

	use RevIt_Graph_Test_Helper;

	public function set_up(): void {
		parent::set_up();
		$this->set_up_graph_importer();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	public function test_vehicle_health_counts_draft_articles(): void {
		$coolant = $this->load_graph_package( 'x3-coolant-loss.json' );
		$pump    = $this->load_graph_package( 'x3-water-pump.json' );
		$pump->vehicle->model = 'M340i';
		$pump->article->article_key = 'bmw-m340i-water-pump';

		$first  = $this->graph_importer->import( $coolant, array( 'batch_id' => 'multi-1' ) );
		$second = $this->graph_importer->import( $pump, array( 'batch_id' => 'multi-1' ) );
		$this->assertTrue( $first['success'] );
		$this->assertTrue( $second['success'] );
		$this->assertSame( 'draft', $first['post_status'] );

		$summaries = RevIt_Publisher_Services::vehicle_health()->get_all_vehicle_summaries();
		$by_label  = array();
		foreach ( $summaries as $row ) {
			$by_label[ (string) $row['label'] ] = $row;
		}

		$x3 = null;
		$m340i = null;
		foreach ( $by_label as $label => $row ) {
			if ( str_contains( $label, 'X3' ) || str_contains( $label, 'x3' ) ) {
				$x3 = $row;
			}
			if ( str_contains( $label, 'M340i' ) ) {
				$m340i = $row;
			}
		}

		$this->assertNotNull( $x3 );
		$this->assertNotNull( $m340i );
		$this->assertSame( 1, (int) $x3['articles'] );
		$this->assertSame( 1, (int) $m340i['articles'] );
		$this->assertSame( 1, (int) $x3['draft'] );
		$this->assertSame( 0, (int) $x3['published'] );
	}

	public function test_seo_scan_orphans_links_safe_fix_no_publish_and_undo(): void {
		$coolant_id = $this->import_graph_package( 'x3-coolant-loss.json' );
		$pump_id    = $this->import_graph_package( 'x3-water-pump.json' );

		$scan = RevIt_Publisher_Services::site_seo_scan()->scan_site();
		$this->assertSame( 2, $scan['articles_scanned'] );
		$this->assertGreaterThanOrEqual( 1, $scan['orphan_articles'] );

		$optimize = RevIt_Publisher_Services::site_seo_scan()->optimize_article( $coolant_id );
		$this->assertSame( 'draft', $optimize['post_status'] );
		$this->assertNotEmpty( $optimize['checklist'] );

		update_post_meta( $coolant_id, RevIt_Publisher_Post_Meta_Keys::SEO_TITLE, '' );
		$fixes = RevIt_Publisher_Services::site_seo_scan()->fixes()->apply( $coolant_id, array( 'missing_seo_title' ) );
		$this->assertTrue( $fixes['success'] );
		$this->assertContains( 'missing_seo_title', $fixes['applied'] );
		$this->assertSame( 'draft', $fixes['post_status'] );
		$this->assertNotSame( 'publish', get_post_status( $coolant_id ) );
		$this->assertNotEmpty( get_post_meta( $coolant_id, RevIt_Publisher_Post_Meta_Keys::SEO_TITLE, true ) );

		$original = get_post( $coolant_id )->post_content;
		$suggestions = RevIt_Publisher_Services::link_service()->get_suggestions( $coolant_id );
		if ( empty( $suggestions ) ) {
			$location = RevIt_Publisher_Services::link_service()->find_anchor_location( $coolant_id, 'water pump' );
			if ( null !== $location ) {
				$suggestions[] = array(
					'target_post_id' => $pump_id,
					'anchor'         => 'water pump',
					'relationship'   => 'related',
					'block_index'    => $location['block_index'],
				);
			}
		}
		$this->assertNotEmpty( $suggestions );
		$log_id = RevIt_Publisher_Services::link_service()->apply_link_logged( $coolant_id, $suggestions[0] );
		$this->assertIsInt( $log_id );
		$linked = get_post( $coolant_id )->post_content;
		$this->assertStringContainsString( '<a href=', $linked );
		$this->assertNotEmpty( parse_blocks( $linked ) );
		$this->assertSame( 'draft', get_post_status( $coolant_id ) );

		$undo = RevIt_Publisher_Services::link_undo()->undo( $log_id );
		$this->assertTrue( $undo );
		$this->assertSame( $original, get_post( $coolant_id )->post_content );
		$this->assertSame( 'draft', get_post_status( $coolant_id ) );
	}
}
