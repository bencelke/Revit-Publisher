<?php
/**
 * Mechanical SEO checklist unit tests.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

require_once dirname( __DIR__ ) . '/includes/scan/class-heading-auditor.php';
require_once dirname( __DIR__ ) . '/includes/scan/class-mechanical-seo-checklist.php';

class MechanicalSeoChecklistTest extends PHPUnit\Framework\TestCase {

	public function test_heading_audit_flags_extra_h1_and_duplicates(): void {
		$auditor = new RevIt_Publisher_Heading_Auditor();
		$result  = $auditor->audit(
			array(
				array( 'level' => 1, 'text' => 'Title' ),
				array( 'level' => 3, 'text' => 'Symptoms' ),
				array( 'level' => 3, 'text' => 'Symptoms' ),
				array( 'level' => 2, 'text' => '' ),
			),
			'Post Title'
		);

		$codes = array_column( $result['issues'], 'code' );
		$this->assertContains( 'extra_content_h1', $codes );
		$this->assertContains( 'skipped_heading_level', $codes );
		$this->assertContains( 'duplicate_headings', $codes );
		$this->assertContains( 'empty_heading', $codes );
	}

	public function test_metadata_and_taxonomy_audit(): void {
		$checklist = new RevIt_Publisher_Mechanical_Seo_Checklist();
		$result    = $checklist->evaluate(
			array(
				'seo_title'        => '',
				'meta_description' => '',
				'slug'             => 'Bad Slug',
				'canonical'        => '',
				'index'            => true,
				'follow'           => true,
				'heading_audit'    => array( 'issues' => array(), 'has_section_structure' => true ),
				'inbound_count'    => 1,
				'outbound_internal_count' => 1,
				'broken_internal_count' => 1,
				'vehicle'          => array( 'manufacturer' => '', 'model' => '' ),
				'article_type'     => '',
				'cluster'          => '',
				'featured_image'   => false,
				'images_missing_alt' => 1,
				'structured_data'  => array(),
			)
		);

		$codes = array_column( $result['issues'], 'code' );
		$this->assertContains( 'missing_seo_title', $codes );
		$this->assertContains( 'missing_meta_description', $codes );
		$this->assertContains( 'invalid_slug', $codes );
		$this->assertContains( 'missing_canonical', $codes );
		$this->assertContains( 'broken_internal_links', $codes );
		$this->assertContains( 'missing_manufacturer', $codes );
		$this->assertContains( 'missing_article_type', $codes );
		$this->assertFalse( $result['mechanical_compliant'] );
		$this->assertSame( 'separate', $result['editorial_quality']['status'] );
	}
}
