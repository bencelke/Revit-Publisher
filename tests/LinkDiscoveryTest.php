<?php
/**
 * Natural-anchor and link-discovery unit tests.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

require_once dirname( __DIR__ ) . '/includes/scan/class-engine-family.php';
require_once dirname( __DIR__ ) . '/includes/scan/class-natural-anchor-finder.php';
require_once dirname( __DIR__ ) . '/includes/scan/class-link-opportunity-discovery.php';
require_once dirname( __DIR__ ) . '/includes/scan/class-rendered-html-analyzer.php';

class LinkDiscoveryTest extends PHPUnit\Framework\TestCase {

	public function test_prefers_natural_b58_anchor_and_rejects_stuffed_phrase(): void {
		$finder = new RevIt_Publisher_Natural_Anchor_Finder();
		$html   = '<p>Owners often blame the B58 engine when oil use appears.</p>';
		$found  = $finder->find(
			$html,
			array(
				'best BMW B58 Supra M340i reliability problems',
				'the B58 engine',
				'B58',
			)
		);

		$this->assertNotNull( $found );
		$this->assertSame( 'the B58 engine', $found['anchor'] );
		$this->assertFalse( $finder->is_natural( 'best BMW B58 Supra M340i reliability problems' ) );
	}

	public function test_related_engine_link_and_irrelevant_suppression(): void {
		$discovery = new RevIt_Publisher_Link_Opportunity_Discovery();

		$m340i = array(
			'post_id'       => 1,
			'title'         => 'BMW M340i Reliability',
			'content_text'  => 'The B58 engine is shared across several modern BMWs and the Supra.',
			'engines'       => array( 'B58' ),
			'manufacturer'  => 'BMW',
			'model'         => 'M340i',
			'vehicle_label' => 'BMW M340i',
			'cluster'       => 'reliability',
			'primary_topic' => 'bmw m340i reliability',
			'linked_post_ids' => array(),
		);
		$supra = array(
			'post_id'       => 2,
			'title'         => 'Toyota GR Supra 3.0 Reliability',
			'content_text'  => 'Toyota’s GR Supra 3.0 uses the B58 engine from BMW.',
			'engines'       => array( 'B58B30' ),
			'manufacturer'  => 'Toyota',
			'model'         => 'GR Supra',
			'vehicle_label' => 'Toyota GR Supra',
			'cluster'       => 'reliability',
			'primary_topic' => 'toyota gr supra reliability',
			'linked_post_ids' => array(),
		);
		$mustang = array(
			'post_id'       => 3,
			'title'         => 'Ford Mustang GT Reliability',
			'content_text'  => 'The Coyote 5.0 remains a staple of Mustang GT ownership.',
			'engines'       => array( 'Coyote 5.0' ),
			'manufacturer'  => 'Ford',
			'model'         => 'Mustang GT',
			'vehicle_label' => 'Ford Mustang GT',
			'cluster'       => 'reliability',
			'primary_topic' => 'mustang gt reliability',
			'linked_post_ids' => array(),
		);
		$elantra = array(
			'post_id'       => 4,
			'title'         => 'Hyundai Elantra N Reliability',
			'content_text'  => 'Elantra N uses a 2.0T and is a fun enthusiast compact.',
			'engines'       => array( 'G4KH' ),
			'manufacturer'  => 'Hyundai',
			'model'         => 'Elantra N',
			'vehicle_label' => 'Hyundai Elantra N',
			'cluster'       => 'reliability',
			'primary_topic' => 'elantra n reliability',
			'linked_post_ids' => array(),
		);
		$wrx = array(
			'post_id'       => 5,
			'title'         => 'Subaru WRX VB Reliability',
			'content_text'  => 'The WRX VB is a turbocharged sedan with typical ownership costs.',
			'engines'       => array( 'FA24DIT' ),
			'manufacturer'  => 'Subaru',
			'model'         => 'WRX',
			'vehicle_label' => 'Subaru WRX VB',
			'cluster'       => 'reliability',
			'primary_topic' => 'wrx vb reliability',
			'linked_post_ids' => array(),
		);
		$gr86 = array(
			'post_id'       => 6,
			'title'         => 'Toyota GR86 Reliability',
			'content_text'  => 'GR86 ownership is about chassis balance and driver feel.',
			'engines'       => array( 'FA24' ),
			'manufacturer'  => 'Toyota',
			'model'         => 'GR86',
			'vehicle_label' => 'Toyota GR86',
			'cluster'       => 'reliability',
			'primary_topic' => 'gr86 reliability',
			'linked_post_ids' => array(),
		);

		$related = $discovery->evaluate_pair( $m340i, $supra );
		$this->assertNotNull( $related );
		$this->assertSame( 'shared_engine', $related['relationship'] );
		$this->assertSame( 'high', $related['confidence'] );
		$this->assertTrue( $related['safe_to_auto_apply'] );
		$this->assertSame( 'The B58 engine', $related['anchor'] );

		$this->assertNull( $discovery->evaluate_pair( $mustang, $elantra ) );
		$this->assertNull( $discovery->evaluate_pair( $wrx, $gr86 ) );
	}

	public function test_existing_body_link_is_suppressed(): void {
		$discovery = new RevIt_Publisher_Link_Opportunity_Discovery();
		$source    = array(
			'post_id'         => 213,
			'title'           => 'BMW M340i Reliability',
			'content_text'    => 'The B58 engine is shared with the GR Supra.',
			'engines'         => array( 'B58' ),
			'manufacturer'    => 'BMW',
			'model'           => 'M340i',
			'vehicle_label'   => 'BMW M340i',
			'cluster'         => 'reliability',
			'primary_topic'   => 'bmw m340i reliability',
			'linked_post_ids' => array( 214 ),
		);
		$target    = array(
			'post_id'         => 214,
			'title'           => 'Toyota GR Supra 3.0 Reliability',
			'content_text'    => 'The GR Supra 3.0 uses the B58 engine.',
			'engines'         => array( 'B58' ),
			'manufacturer'    => 'Toyota',
			'model'           => 'GR Supra',
			'vehicle_label'   => 'Toyota GR Supra',
			'cluster'         => 'reliability',
			'primary_topic'   => 'toyota gr supra reliability',
			'linked_post_ids' => array(),
		);

		$this->assertNull( $discovery->evaluate_pair( $source, $target ) );
	}

	public function test_inbound_natural_mention(): void {
		$discovery = new RevIt_Publisher_Link_Opportunity_Discovery();
		$older     = array(
			'post_id'       => 10,
			'title'         => 'BMW M340i Reliability',
			'content_text'  => 'Watch the water pump and cooling system during ownership.',
			'engines'       => array( 'B58' ),
			'manufacturer'  => 'BMW',
			'model'         => 'M340i',
			'vehicle_label' => 'BMW M340i',
			'cluster'       => 'cooling',
			'primary_topic' => 'bmw m340i reliability',
			'linked_post_ids' => array(),
		);
		$newer     = array(
			'post_id'       => 11,
			'title'         => 'BMW M340i Water Pump Problems',
			'content_text'  => 'This article covers water pump failures on the M340i.',
			'engines'       => array( 'B58' ),
			'manufacturer'  => 'BMW',
			'model'         => 'M340i',
			'vehicle_label' => 'BMW M340i',
			'cluster'       => 'cooling',
			'primary_topic' => 'bmw m340i water pump problems',
			'linked_post_ids' => array(),
		);

		$found = $discovery->evaluate_pair( $older, $newer );
		$this->assertNotNull( $found );
		$this->assertSame( 'water pump', $found['anchor'] );
	}

	public function test_rendered_html_analyzer(): void {
		$analyzer = new RevIt_Publisher_Rendered_Html_Analyzer();
		$html     = '<html><head><title>Test</title><meta name="description" content="Desc"><link rel="canonical" href="https://example.com/a"><meta name="robots" content="index,follow"><script type="application/ld+json">{"@type":"Article"}</script></head><body><h1>One</h1><h2>Two</h2><img src="x.jpg"><nav class="breadcrumb">Home</nav></body></html>';
		$checks   = $analyzer->analyze( $html, 200 );
		$this->assertSame( 200, $checks['http_status'] );
		$this->assertSame( 'Test', $checks['title'] );
		$this->assertSame( 1, $checks['h1_count'] );
		$this->assertTrue( $checks['has_json_ld'] );
		$this->assertSame( 1, $checks['images_missing_alt'] );
	}
}
