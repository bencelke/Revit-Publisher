<?php
/**
 * REST permission expectations (WordPress environment required for full tests).
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Documents REST permission requirements for Phase 0.
 */
class RestPermissionTest extends TestCase {

	/**
	 * Full REST permission integration tests require wp-phpunit.
	 */
	public function test_rest_permission_requires_edit_posts_capability(): void {
		$this->markTestSkipped(
			'WordPress test environment not configured. Expected: users without edit_posts receive REST 403 on POST /revit-publisher/v1/article-packages/validate.'
		);
	}
}
