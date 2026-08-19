<?php
/**
 * Article importer integration tests.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

/**
 * Tests for RevIt_Publisher_Article_Importer.
 */
class ArticleImporterTest extends WP_UnitTestCase {

	/**
	 * Importer instance.
	 *
	 * @var RevIt_Publisher_Article_Importer
	 */
	private RevIt_Publisher_Article_Importer $importer;

	/**
	 * Load example JSON.
	 */
	private function load_valid_package( string $suffix = '' ): object {
		$json = file_get_contents( REVIT_PUBLISHER_PLUGIN_DIR . 'examples/article-valid.json' );
		$this->assertNotFalse( $json );
		$package = json_decode( (string) $json, false );
		$this->assertIsObject( $package );

		if ( '' !== $suffix ) {
			$package->article->article_key = $package->article->article_key . '-' . $suffix;
			$package->article->slug        = $package->article->slug . '-' . $suffix;
		}

		return $package;
	}

	/**
	 * Set up importer.
	 */
	public function set_up(): void {
		parent::set_up();

		RevIt_Publisher_Taxonomies::register();
		RevIt_Publisher_Taxonomies::ensure_article_type_terms();

		$this->importer = new RevIt_Publisher_Article_Importer(
			new RevIt_Publisher_Article_Package_Validator(),
			new RevIt_Publisher_Article_Registry(),
			new RevIt_Publisher_Vehicle_Taxonomy_Service(),
			new RevIt_Publisher_Cluster_Service(),
			new RevIt_Publisher_Content_Renderer(),
			new RevIt_Publisher_Package_Hash()
		);
	}

	/**
	 * Valid package creates draft post.
	 */
	public function test_valid_package_creates_draft(): void {
		$result = $this->importer->import( $this->load_valid_package( 'draft' ) );

		$this->assertTrue( $result['success'] );
		$this->assertSame( 'created', $result['status'] );
		$this->assertSame( 'draft', $result['post_status'] );

		$post = get_post( $result['post_id'] );
		$this->assertInstanceOf( WP_Post::class, $post );
		$this->assertSame( 'draft', $post->post_status );
	}

	/**
	 * Article key is stored on imported post.
	 */
	public function test_article_key_stored(): void {
		$result = $this->importer->import( $this->load_valid_package( 'key' ) );

		$this->assertSame(
			'bmw-x3-g01-m40i-coolant-loss-key',
			get_post_meta( $result['post_id'], RevIt_Publisher_Post_Meta_Keys::ARTICLE_KEY, true )
		);
	}

	/**
	 * Duplicate article key is blocked.
	 */
	public function test_duplicate_article_key_blocked(): void {
		$package = $this->load_valid_package( 'dupe' );
		$this->importer->import( $package );

		$result = $this->importer->import( $package );

		$this->assertFalse( $result['success'] );
		$this->assertSame( 'existing_article', $result['status'] );
	}

	/**
	 * Publish status is blocked defensively.
	 */
	public function test_publish_status_blocked(): void {
		$package = $this->load_valid_package( 'publish' );
		$package->publishing->status = 'publish';

		$result = $this->importer->import( $package );

		$this->assertFalse( $result['success'] );
		$this->assertSame( 'validation_failed', $result['status'] );
	}

	/**
	 * Vehicle metadata is stored.
	 */
	public function test_vehicle_metadata_stored(): void {
		$result = $this->importer->import( $this->load_valid_package( 'vehicle' ) );

		$this->assertSame( 'BMW', get_post_meta( $result['post_id'], RevIt_Publisher_Post_Meta_Keys::VEHICLE_MANUFACTURER, true ) );
		$this->assertSame( 'X3', get_post_meta( $result['post_id'], RevIt_Publisher_Post_Meta_Keys::VEHICLE_MODEL, true ) );
		$this->assertSame( array( 'B58' ), get_post_meta( $result['post_id'], RevIt_Publisher_Post_Meta_Keys::VEHICLE_ENGINES, true ) );
	}

	/**
	 * Cluster metadata is stored.
	 */
	public function test_cluster_metadata_stored(): void {
		$result = $this->importer->import( $this->load_valid_package( 'cluster' ) );

		$this->assertSame(
			'bmw-x3-g01-m40i-cooling',
			get_post_meta( $result['post_id'], RevIt_Publisher_Post_Meta_Keys::CLUSTER_KEY, true )
		);
	}

	/**
	 * SEO metadata is stored.
	 */
	public function test_seo_metadata_stored(): void {
		$result = $this->importer->import( $this->load_valid_package( 'seo' ) );

		$this->assertSame(
			'BMW X3 M40i coolant loss',
			get_post_meta( $result['post_id'], RevIt_Publisher_Post_Meta_Keys::PRIMARY_TOPIC, true )
		);
		$this->assertSame(
			'informational',
			get_post_meta( $result['post_id'], RevIt_Publisher_Post_Meta_Keys::SEARCH_INTENT, true )
		);
	}

	/**
	 * Sources are stored.
	 */
	public function test_sources_stored(): void {
		$result = $this->importer->import( $this->load_valid_package( 'sources' ) );
		$sources = get_post_meta( $result['post_id'], RevIt_Publisher_Post_Meta_Keys::SOURCES, true );

		$this->assertIsArray( $sources );
		$this->assertNotEmpty( $sources );
	}

	/**
	 * Relationships are stored.
	 */
	public function test_relationships_stored(): void {
		$result = $this->importer->import( $this->load_valid_package( 'relations' ) );

		$links = get_post_meta( $result['post_id'], RevIt_Publisher_Post_Meta_Keys::INTERNAL_LINKS, true );
		$this->assertIsArray( $links );
		$this->assertCount( 2, $links );

		$related = get_post_meta( $result['post_id'], RevIt_Publisher_Post_Meta_Keys::RELATED_ARTICLES, true );
		$this->assertIsArray( $related );
		$this->assertCount( 2, $related );
	}

	/**
	 * Package hash is stored.
	 */
	public function test_package_hash_stored(): void {
		$result = $this->importer->import( $this->load_valid_package( 'hash' ) );
		$hash   = get_post_meta( $result['post_id'], RevIt_Publisher_Post_Meta_Keys::PACKAGE_HASH, true );

		$this->assertNotEmpty( $hash );
		$this->assertSame( 64, strlen( (string) $hash ) );
	}

	/**
	 * Taxonomies are assigned to imported post.
	 */
	public function test_taxonomies_assigned(): void {
		$result = $this->importer->import( $this->load_valid_package( 'tax' ) );

		$manufacturers = wp_get_post_terms( $result['post_id'], RevIt_Publisher_Taxonomies::MANUFACTURER, array( 'fields' => 'names' ) );
		$this->assertContains( 'BMW', $manufacturers );

		$types = wp_get_post_terms( $result['post_id'], RevIt_Publisher_Taxonomies::ARTICLE_TYPE, array( 'fields' => 'slugs' ) );
		$this->assertContains( 'problem', $types );
	}
}
