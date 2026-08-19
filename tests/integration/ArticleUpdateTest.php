<?php
/**
 * Article update workflow integration tests.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

require_once __DIR__ . '/GraphTestHelper.php';

class ArticleUpdateTest extends WP_UnitTestCase {

	use RevIt_Graph_Test_Helper;

	public function set_up(): void {
		parent::set_up();
		$this->set_up_graph_importer();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	private function get_updater(): RevIt_Publisher_Article_Update_Service {
		return new RevIt_Publisher_Article_Update_Service(
			new RevIt_Publisher_Article_Package_Validator(),
			new RevIt_Publisher_Article_Registry(),
			new RevIt_Publisher_Vehicle_Taxonomy_Service(),
			new RevIt_Publisher_Cluster_Service(),
			new RevIt_Publisher_Content_Renderer(),
			new RevIt_Publisher_Package_Hash()
		);
	}

	public function test_unchanged_hash(): void {
		$post_id = $this->import_graph_package( 'x3-coolant-loss.json' );
		$package = $this->load_graph_package( 'x3-coolant-loss.json' );
		$result  = $this->get_updater()->preview_update( $post_id, $package );
		$this->assertSame( 'unchanged', $result['status'] );
	}

	public function test_seo_only_update(): void {
		$post_id = $this->import_graph_package( 'x3-coolant-loss.json' );
		$package = $this->load_graph_package( 'x3-coolant-loss.json' );
		$package->seo->seo_title = 'Updated SEO Title Only';
		$original_content = get_post_field( 'post_content', $post_id );

		$result = $this->get_updater()->apply_update(
			$post_id,
			$package,
			RevIt_Publisher_Article_Update_Service::MODE_SEO
		);
		$this->assertTrue( $result['success'] );
		$this->assertSame( $original_content, get_post_field( 'post_content', $post_id ) );
		$this->assertSame( 'Updated SEO Title Only', get_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::SEO_TITLE, true ) );
	}

	public function test_preserves_post_id(): void {
		$post_id = $this->import_graph_package( 'x3-coolant-loss.json' );
		$package = $this->load_graph_package( 'x3-coolant-loss.json' );
		$package->article->title = 'Updated Title Full';
		$result = $this->get_updater()->apply_update( $post_id, $package );
		$this->assertSame( $post_id, (int) $result['post_id'] );
	}
}
