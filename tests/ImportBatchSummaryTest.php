<?php
/**
 * Unit tests for import-batch grouping.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

require_once dirname( __DIR__ ) . '/includes/scan/class-import-batch-service.php';

class ImportBatchSummaryTest extends PHPUnit\Framework\TestCase {

	public function test_multi_vehicle_batch_does_not_collapse_to_first_vehicle(): void {
		$vehicles = array(
			'BMW M340i',
			'Toyota GR Supra',
			'VW Golf R Mk8',
			'Audi S4 B9',
			'Honda Civic Type R FK8',
			'Ford Mustang GT',
			'Subaru WRX VB',
			'Porsche 718 Cayman',
			'Hyundai Elantra N',
			'Toyota GR86',
		);

		$articles = array();
		foreach ( $vehicles as $i => $label ) {
			$articles[] = array(
				'batch_id'      => 'batch-10',
				'vehicle_label' => $label,
				'cluster'       => 0 === $i % 2 ? 'reliability' : 'cooling',
				'imported_at'   => '2026-08-21T12:00:00Z',
				'post_id'       => $i + 1,
			);
		}

		$groups = RevIt_Publisher_Import_Batch_Service::group_articles( $articles );
		$this->assertCount( 1, $groups );
		$batch = $groups[0];
		$this->assertSame( 10, $batch['article_count'] );
		$this->assertSame( 10, $batch['vehicle_count'] );
		$this->assertSame( 2, $batch['cluster_count'] );
		$this->assertSame( '10 vehicles', $batch['vehicle_label'] );
		$this->assertNotSame( 'BMW M340i', $batch['vehicle_label'] );
	}

	public function test_single_vehicle_batch_keeps_vehicle_label(): void {
		$articles = array(
			array(
				'batch_id'      => 'one',
				'vehicle_label' => 'BMW M340i',
				'cluster'       => 'reliability',
				'imported_at'   => '2026-08-21T12:00:00Z',
			),
			array(
				'batch_id'      => 'one',
				'vehicle_label' => 'BMW M340i',
				'cluster'       => 'cooling',
				'imported_at'   => '2026-08-21T12:00:00Z',
			),
		);

		$batch = RevIt_Publisher_Import_Batch_Service::group_articles( $articles )[0];
		$this->assertSame( 'BMW M340i', $batch['vehicle_label'] );
		$this->assertSame( 1, $batch['vehicle_count'] );
		$this->assertSame( 2, $batch['article_count'] );
		$this->assertSame( 2, $batch['cluster_count'] );
	}
}
