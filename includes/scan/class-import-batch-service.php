<?php
/**
 * Import-batch summaries from WordPress post meta (source of truth).
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Groups imported articles into batch summaries with real vehicle counts.
 */
class RevIt_Publisher_Import_Batch_Service {

	public const OPTION = 'revit_import_batches';

	/**
	 * Build summaries from article records without using the first vehicle as the batch label.
	 *
	 * @param array<int, array<string, mixed>> $articles Article rows.
	 * @return array<int, array<string, mixed>>
	 */
	public static function group_articles( array $articles ): array {
		$groups = array();

		foreach ( $articles as $article ) {
			$batch_id = strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) ( $article['batch_id'] ?? '' ) ) ?? '' );
			$imported = (string) ( $article['imported_at'] ?? '' );
			$key      = '' !== $batch_id ? 'id:' . $batch_id : 'date:' . substr( $imported, 0, 10 );
			if ( 'date:' === $key ) {
				$key = 'ungrouped';
			}

			if ( ! isset( $groups[ $key ] ) ) {
				$groups[ $key ] = array(
					'id'         => '' !== $batch_id ? $batch_id : $key,
					'vehicles'   => array(),
					'clusters'   => array(),
					'articles'   => 0,
					'imported_at'=> $imported,
					'post_ids'   => array(),
				);
			}

			++$groups[ $key ]['articles'];
			$vehicle = trim( (string) ( $article['vehicle_label'] ?? '' ) );
			$cluster = trim( (string) ( $article['cluster'] ?? '' ) );
			if ( '' !== $vehicle ) {
				$groups[ $key ]['vehicles'][ $vehicle ] = ( $groups[ $key ]['vehicles'][ $vehicle ] ?? 0 ) + 1;
			}
			if ( '' !== $cluster ) {
				$groups[ $key ]['clusters'][ $cluster ] = true;
			}
			if ( ! empty( $article['post_id'] ) ) {
				$groups[ $key ]['post_ids'][] = (int) $article['post_id'];
			}
			if ( $imported > (string) $groups[ $key ]['imported_at'] ) {
				$groups[ $key ]['imported_at'] = $imported;
			}
		}

		$out = array();
		foreach ( $groups as $group ) {
			$vehicle_labels = array_keys( $group['vehicles'] );
			$vehicle_count  = count( $vehicle_labels );
			$label          = 1 === $vehicle_count ? (string) $vehicle_labels[0] : $vehicle_count . ' vehicles';

			$out[] = array(
				'id'             => (string) $group['id'],
				'vehicle_label'  => $label,
				'vehicle_count'  => $vehicle_count,
				'vehicle_labels' => $vehicle_labels,
				'article_count'  => (int) $group['articles'],
				'cluster_count'  => count( $group['clusters'] ),
				'imported_at'    => (string) $group['imported_at'],
				'status'         => (string) ( $group['status'] ?? 'Imported' ),
				'post_ids'       => $group['post_ids'],
			);
		}

		usort(
			$out,
			static fn( array $a, array $b ): int => strcmp( (string) $b['imported_at'], (string) $a['imported_at'] )
		);

		return $out;
	}

	/**
	 * Persist a completed batch after import.
	 *
	 * @param array<string, mixed> $batch Batch payload.
	 */
	public function record( array $batch ): array {
		$id = sanitize_key( (string) ( $batch['id'] ?? wp_generate_uuid4() ) );
		$rows = $this->collect_articles_for_batch( $id );
		$summaries = self::group_articles( $rows );
		$summary   = $summaries[0] ?? array(
			'id'            => $id,
			'vehicle_label' => '0 vehicles',
			'vehicle_count' => 0,
			'article_count' => 0,
			'cluster_count' => 0,
			'imported_at'   => gmdate( 'c' ),
		);
		$summary['status'] = sanitize_text_field( (string) ( $batch['status'] ?? 'Imported' ) );
		$summary['id']     = $id;

		$stored = get_option( self::OPTION, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		$stored = array_values(
			array_filter(
				$stored,
				static fn( $row ): bool => ! is_array( $row ) || (string) ( $row['id'] ?? '' ) !== $id
			)
		);
		array_unshift( $stored, $summary );
		update_option( self::OPTION, array_slice( $stored, 0, 20 ), false );

		return $summary;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function list_recent( int $limit = 8 ): array {
		$stored = get_option( self::OPTION, array() );
		if ( is_array( $stored ) && ! empty( $stored ) ) {
			return array_slice( $stored, 0, $limit );
		}

		return array_slice( self::group_articles( $this->collect_all_imported_articles() ), 0, $limit );
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private function collect_articles_for_batch( string $batch_id ): array {
		$out = array();
		foreach ( RevIt_Publisher_Services::registry()->get_managed_post_ids() as $post_id ) {
			$stored = (string) get_post_meta( (int) $post_id, RevIt_Publisher_Post_Meta_Keys::IMPORT_BATCH_ID, true );
			if ( $stored !== $batch_id ) {
				continue;
			}
			$out[] = $this->article_row( (int) $post_id );
		}

		return $out;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private function collect_all_imported_articles(): array {
		$out = array();
		foreach ( RevIt_Publisher_Services::registry()->get_managed_post_ids() as $post_id ) {
			$out[] = $this->article_row( (int) $post_id );
		}

		return $out;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function article_row( int $post_id ): array {
		return array(
			'post_id'       => $post_id,
			'batch_id'      => (string) get_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::IMPORT_BATCH_ID, true ),
			'vehicle_label' => RevIt_Publisher_Services::graph()->get_vehicle_label( $post_id ),
			'cluster'       => (string) get_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::CLUSTER_KEY, true ),
			'imported_at'   => (string) get_post_meta( $post_id, RevIt_Publisher_Post_Meta_Keys::IMPORTED_AT, true ),
		);
	}
}
