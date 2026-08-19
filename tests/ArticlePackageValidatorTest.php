<?php
/**
 * Article package validator tests.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Tests for RevIt_Publisher_Article_Package_Validator.
 */
class ArticlePackageValidatorTest extends TestCase {

	/**
	 * Validator instance.
	 *
	 * @var RevIt_Publisher_Article_Package_Validator
	 */
	private RevIt_Publisher_Article_Package_Validator $validator;

	/**
	 * Set up test case.
	 */
	protected function setUp(): void {
		$this->validator = new RevIt_Publisher_Article_Package_Validator();
	}

	/**
	 * Load example JSON file.
	 */
	private function load_example( string $filename ): string {
		$path = REVIT_PUBLISHER_PLUGIN_DIR . 'examples/' . $filename;
		$this->assertFileExists( $path );

		$contents = file_get_contents( $path );
		$this->assertNotFalse( $contents );

		return $contents;
	}

	/**
	 * Valid package should pass validation.
	 */
	public function test_valid_article_package(): void {
		$result = $this->validator->validate_json( $this->load_example( 'article-valid.json' ) );

		$this->assertTrue( $result['valid'] );
		$this->assertSame( 'revit-article-v1', $result['schema_version'] );
		$this->assertSame( 'bmw-x3-g01-m40i-coolant-loss', $result['article_key'] );
		$this->assertSame( array(), $result['errors'] );
	}

	/**
	 * Invalid example should fail validation.
	 */
	public function test_invalid_example_package_fails(): void {
		$result = $this->validator->validate_json( $this->load_example( 'article-invalid.json' ) );

		$this->assertFalse( $result['valid'] );
		$this->assertNotEmpty( $result['errors'] );
	}

	/**
	 * Wrong schema version should fail early.
	 */
	public function test_invalid_schema_version(): void {
		$payload = json_decode( $this->load_example( 'article-valid.json' ), true );
		$this->assertIsArray( $payload );
		$payload['schema_version'] = 'revit-article-v0';

		$result = $this->validator->validate( $payload );

		$this->assertFalse( $result['valid'] );
		$this->assertSame( 'schema_version', $result['errors'][0]['path'] );
	}

	/**
	 * Missing article_key should fail validation.
	 */
	public function test_missing_article_key(): void {
		$payload = json_decode( $this->load_example( 'article-valid.json' ), true );
		$this->assertIsArray( $payload );
		unset( $payload['article']['article_key'] );

		$result = $this->validator->validate( $payload );

		$this->assertFalse( $result['valid'] );
		$this->assertNotEmpty( $result['errors'] );
	}

	/**
	 * Invalid article type should fail validation.
	 */
	public function test_invalid_article_type(): void {
		$payload = json_decode( $this->load_example( 'article-valid.json' ), true );
		$this->assertIsArray( $payload );
		$payload['article']['article_type'] = 'unknown_type';

		$result = $this->validator->validate( $payload );

		$this->assertFalse( $result['valid'] );
		$this->assertTrue(
			$this->errors_contain_path( $result['errors'], 'article.article_type' )
		);
	}

	/**
	 * Invalid vehicle years should fail business rule validation.
	 */
	public function test_invalid_vehicle_years(): void {
		$payload = json_decode( $this->load_example( 'article-valid.json' ), true );
		$this->assertIsArray( $payload );
		$payload['vehicle']['start_year'] = 2025;
		$payload['vehicle']['end_year']   = 2018;

		$result = $this->validator->validate( $payload );

		$this->assertFalse( $result['valid'] );
		$this->assertTrue(
			$this->errors_contain_path( $result['errors'], 'vehicle.end_year' )
		);
	}

	/**
	 * Invalid source URL should fail validation.
	 */
	public function test_invalid_url(): void {
		$payload = json_decode( $this->load_example( 'article-valid.json' ), true );
		$this->assertIsArray( $payload );
		$payload['sources'][0]['url'] = 'not-a-url';

		$result = $this->validator->validate( $payload );

		$this->assertFalse( $result['valid'] );
		$this->assertNotEmpty( $result['errors'] );
	}

	/**
	 * Invalid internal link relationship should fail validation.
	 */
	public function test_invalid_internal_link_relationship(): void {
		$payload = json_decode( $this->load_example( 'article-valid.json' ), true );
		$this->assertIsArray( $payload );
		$payload['internal_links'][0]['relationship'] = 'invalid_relationship';

		$result = $this->validator->validate( $payload );

		$this->assertFalse( $result['valid'] );
		$this->assertTrue(
			$this->errors_contain_path( $result['errors'], 'internal_links.0.relationship' )
		);
	}

	/**
	 * Unsupported publishing status should fail validation.
	 */
	public function test_unsupported_publishing_status(): void {
		$payload = json_decode( $this->load_example( 'article-valid.json' ), true );
		$this->assertIsArray( $payload );
		$payload['publishing']['status'] = 'publish';

		$result = $this->validator->validate( $payload );

		$this->assertFalse( $result['valid'] );
		$this->assertTrue(
			$this->errors_contain_path( $result['errors'], 'publishing.status' )
		);
	}

	/**
	 * REST-style valid response shape can be derived from validator output.
	 */
	public function test_valid_rest_validation_response_shape(): void {
		$result = $this->validator->validate_json( $this->load_example( 'article-valid.json' ) );

		$response = array(
			'valid'          => $result['valid'],
			'schema_version' => $result['schema_version'] ?? null,
			'article_key'    => $result['article_key'] ?? null,
			'warnings'       => $result['warnings'],
		);

		$this->assertTrue( $response['valid'] );
		$this->assertSame( 'revit-article-v1', $response['schema_version'] );
		$this->assertSame( 'bmw-x3-g01-m40i-coolant-loss', $response['article_key'] );
		$this->assertIsArray( $response['warnings'] );
	}

	/**
	 * REST-style invalid response shape can be derived from validator output.
	 */
	public function test_invalid_rest_validation_response_shape(): void {
		$result = $this->validator->validate_json( $this->load_example( 'article-invalid.json' ) );

		$response = array(
			'valid'  => $result['valid'],
			'errors' => $result['errors'],
		);

		$this->assertFalse( $response['valid'] );
		$this->assertIsArray( $response['errors'] );
		$this->assertNotEmpty( $response['errors'] );
		foreach ( $response['errors'] as $error ) {
			$this->assertArrayHasKey( 'path', $error );
			$this->assertArrayHasKey( 'message', $error );
		}
	}

	/**
	 * Check whether errors contain a given path.
	 *
	 * @param array<int, array{path: string, message: string}> $errors Errors.
	 */
	private function errors_contain_path( array $errors, string $path ): bool {
		foreach ( $errors as $error ) {
			if ( $path === $error['path'] ) {
				return true;
			}
		}

		return false;
	}
}
