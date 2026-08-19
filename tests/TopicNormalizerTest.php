<?php
/**
 * Topic normalizer unit tests.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

/**
 * Tests for RevIt_Publisher_Topic_Normalizer.
 */
class TopicNormalizerTest extends PHPUnit\Framework\TestCase {

	/**
	 * Normalizer instance.
	 *
	 * @var RevIt_Publisher_Topic_Normalizer
	 */
	private RevIt_Publisher_Topic_Normalizer $normalizer;

	/**
	 * Set up.
	 */
	protected function setUp(): void {
		require_once dirname( __DIR__ ) . '/includes/graph/class-topic-normalizer.php';
		$this->normalizer = new RevIt_Publisher_Topic_Normalizer();
	}

	/**
	 * Case and punctuation normalize to same value.
	 */
	public function test_normalize_duplicate_topics(): void {
		$a = $this->normalizer->normalize( 'BMW X3 M40i Coolant Loss' );
		$b = $this->normalizer->normalize( 'bmw x3 m40i coolant loss' );

		$this->assertSame( $a, $b );
	}
}
