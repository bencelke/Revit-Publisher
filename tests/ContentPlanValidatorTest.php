<?php
/**
 * Content plan validator unit tests.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

class ContentPlanValidatorTest extends PHPUnit\Framework\TestCase {

	private RevIt_Publisher_Content_Plan_Validator $validator;

	protected function setUp(): void {
		require_once dirname( __DIR__ ) . '/includes/content-plan/class-content-plan-validator.php';
		$this->validator = new RevIt_Publisher_Content_Plan_Validator();
	}

	public function test_valid_plan(): void {
		$json = file_get_contents( dirname( __DIR__ ) . '/examples/content-plan-valid.json' );
		$result = $this->validator->validate( json_decode( (string) $json, false ) );
		$this->assertTrue( $result['valid'] );
	}

	public function test_invalid_plan(): void {
		$json = file_get_contents( dirname( __DIR__ ) . '/examples/content-plan-invalid.json' );
		$result = $this->validator->validate( json_decode( (string) $json, false ) );
		$this->assertFalse( $result['valid'] );
	}
}
