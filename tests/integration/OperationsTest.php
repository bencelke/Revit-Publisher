<?php
/**
 * Operations workflow integration tests.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

require_once __DIR__ . '/GraphTestHelper.php';

class OperationsTest extends WP_UnitTestCase {

	use RevIt_Graph_Test_Helper;

	public function set_up(): void {
		parent::set_up();
		$this->set_up_graph_importer();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	public function test_redirect_loop_rejection(): void {
		$a = $this->import_graph_package( 'x3-coolant-loss.json' );
		$path = wp_parse_url( (string) get_permalink( $a ), PHP_URL_PATH );
		$result = RevIt_Publisher_Services::redirects()->create(
			array(
				'source_path'    => $path,
				'target_post_id' => $a,
			)
		);
		$this->assertInstanceOf( WP_Error::class, $result );
	}

	public function test_audit_creates_snapshot(): void {
		$this->import_graph_package( 'x3-coolant-loss.json' );
		$result = RevIt_Publisher_Services::site_audit()->run( true );
		$this->assertTrue( ! empty( $result['success'] ) || 'running' === ( $result['status'] ?? '' ) );
	}

	public function test_issue_reconciliation(): void {
		$post_id = $this->import_graph_package( 'x3-coolant-loss.json' );
		RevIt_Publisher_Services::issues()->reconcile(
			array(
				array(
					'issue_type'         => 'orphan',
					'title'              => 'Test',
					'post_id'            => $post_id,
					'article_key'        => 'bmw-x3-g01-m40i-coolant-loss',
					'vehicle'            => 'BMW X3',
					'cluster_key'        => '',
					'explanation'        => 'Test orphan',
					'recommended_action' => 'Fix',
					'context'            => array(),
				),
			)
		);
		$issues = RevIt_Publisher_Services::issues()->list_issues();
		$this->assertNotEmpty( $issues );
	}

	public function test_consolidation_preview_no_merge(): void {
		$a = $this->import_graph_package( 'x3-coolant-loss.json' );
		$b = $this->import_graph_package( 'x3-water-pump.json' );
		$preview = RevIt_Publisher_Services::consolidation()->preview( $b, $a );
		$this->assertIsArray( $preview );
		$this->assertArrayHasKey( 'proposed_redirect', $preview );
	}

	public function test_severity_model(): void {
		$this->assertSame(
			RevIt_Publisher_Severity::HIGH,
			RevIt_Publisher_Severity::for_issue( 'topic_overlap', array( 'risk' => 'high' ) )
		);
		$this->assertSame(
			RevIt_Publisher_Severity::LOW,
			RevIt_Publisher_Severity::for_issue( 'missing_content' )
		);
	}

	public function test_audit_lock(): void {
		set_transient( RevIt_Publisher_Audit_Service::LOCK_KEY, array( 'started' => time() ), 60 );
		$result = RevIt_Publisher_Services::site_audit()->run( true );
		$this->assertSame( 'running', $result['status'] ?? '' );
		delete_transient( RevIt_Publisher_Audit_Service::LOCK_KEY );
	}
}
