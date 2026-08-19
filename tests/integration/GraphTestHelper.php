<?php
/**
 * Shared helpers for graph integration tests.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

/**
 * Imports example packages for graph tests.
 */
trait RevIt_Graph_Test_Helper {

	/**
	 * Article importer.
	 *
	 * @var RevIt_Publisher_Article_Importer
	 */
	private RevIt_Publisher_Article_Importer $graph_importer;

	/**
	 * Set up importer and taxonomies.
	 */
	protected function set_up_graph_importer(): void {
		RevIt_Publisher_Taxonomies::register();
		RevIt_Publisher_Taxonomies::ensure_article_type_terms();

		$this->graph_importer = new RevIt_Publisher_Article_Importer(
			new RevIt_Publisher_Article_Package_Validator(),
			new RevIt_Publisher_Article_Registry(),
			new RevIt_Publisher_Vehicle_Taxonomy_Service(),
			new RevIt_Publisher_Cluster_Service(),
			new RevIt_Publisher_Content_Renderer(),
			new RevIt_Publisher_Package_Hash()
		);
	}

	/**
	 * Load graph example JSON.
	 */
	protected function load_graph_package( string $filename ): object {
		$path = REVIT_PUBLISHER_PLUGIN_DIR . 'examples/graph/' . $filename;
		$json = file_get_contents( $path );
		$this->assertNotFalse( $json );
		$package = json_decode( (string) $json, false );
		$this->assertIsObject( $package );

		return $package;
	}

	/**
	 * Import graph package and return post ID.
	 */
	protected function import_graph_package( string $filename ): int {
		$result = $this->graph_importer->import( $this->load_graph_package( $filename ) );
		$this->assertTrue( $result['success'], wp_json_encode( $result ) );

		return (int) $result['post_id'];
	}
}
