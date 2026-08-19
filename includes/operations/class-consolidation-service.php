<?php
/**
 * Cannibalization consolidation workflow.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles operator-approved article consolidation without merging body content.
 */
class RevIt_Publisher_Consolidation_Service {

	/**
	 * Preview consolidation from source to destination.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public function preview( int $source_post_id, int $destination_post_id ) {
		if ( $source_post_id === $destination_post_id ) {
			return new WP_Error( 'revit_same_post', __( 'Source and destination must differ.', 'revit-publisher' ) );
		}

		foreach ( array( $source_post_id, $destination_post_id ) as $post_id ) {
			if ( ! RevIt_Publisher_Services::resolver()->is_managed( $post_id ) ) {
				return new WP_Error( 'revit_unmanaged', __( 'Both articles must be RevIt-managed.', 'revit-publisher' ) );
			}
		}

		$inbound     = RevIt_Publisher_Services::graph()->get_inbound_relationships( $source_post_id );
		$retarget    = array();

		foreach ( $inbound as $link ) {
			$retarget[] = array(
				'source_post_id'  => (int) ( $link['source_post_id'] ?? 0 ),
				'source_title'    => (string) ( $link['source_title'] ?? '' ),
				'target_article_key' => (string) get_post_meta( $destination_post_id, RevIt_Publisher_Post_Meta_Keys::ARTICLE_KEY, true ),
			);
		}

		return array(
			'source_post_id'       => $source_post_id,
			'destination_post_id'  => $destination_post_id,
			'source_title'         => get_the_title( $source_post_id ),
			'destination_title'    => get_the_title( $destination_post_id ),
			'proposed_redirect'    => array(
				'source_path'    => RevIt_Publisher_Services::redirects()->get_post_path( $source_post_id ),
				'target_post_id' => $destination_post_id,
				'reason'         => __( 'Content consolidation', 'revit-publisher' ),
			),
			'inbound_links'        => count( $inbound ),
			'retarget_suggestions' => $retarget,
			'note'                 => __( 'Article body content will not be merged automatically.', 'revit-publisher' ),
		);
	}

	/**
	 * Apply approved consolidation.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public function apply( int $source_post_id, int $destination_post_id, string $source_status = 'draft' ) {
		$preview = $this->preview( $source_post_id, $destination_post_id );
		if ( is_wp_error( $preview ) ) {
			return $preview;
		}

		$redirect = RevIt_Publisher_Services::redirects()->create(
			array(
				'source_path'    => (string) ( $preview['proposed_redirect']['source_path'] ?? '' ),
				'target_post_id' => $destination_post_id,
				'reason'         => (string) ( $preview['proposed_redirect']['reason'] ?? '' ),
				'status'         => RevIt_Publisher_Redirect_Service::STATUS_ACTIVE,
			)
		);

		if ( is_wp_error( $redirect ) ) {
			return $redirect;
		}

		$retargeted = $this->retarget_inbound_links( $source_post_id, $destination_post_id );

		$allowed_status = in_array( $source_status, array( 'draft', 'private' ), true ) ? $source_status : 'draft';
		wp_update_post(
			array(
				'ID'          => $source_post_id,
				'post_status' => $allowed_status,
			)
		);

		update_post_meta( $source_post_id, '_revit_consolidation_destination', $destination_post_id );
		update_post_meta( $source_post_id, '_revit_consolidation_at', gmdate( 'c' ) );

		RevIt_Publisher_Services::event_logger()->log(
			'consolidation_applied',
			array(
				'source'      => $source_post_id,
				'destination' => $destination_post_id,
				'redirect_id' => (int) ( $redirect['redirect_id'] ?? 0 ),
			)
		);

		return array(
			'success'           => true,
			'source_post_id'    => $source_post_id,
			'destination_post_id'=> $destination_post_id,
			'redirect'          => $redirect,
			'retargeted_links'  => $retargeted,
			'source_status'     => $allowed_status,
		);
	}

	/**
	 * Record overlap decision without consolidation.
	 */
	public function record_overlap_decision( int $post_id_a, int $post_id_b, string $decision ): bool {
		$allowed = array( 'keep_both', 'different_intent', 'ignore', 'merge_into_a', 'merge_into_b' );
		if ( ! in_array( $decision, $allowed, true ) ) {
			return false;
		}
		$key = $this->overlap_key( $post_id_a, $post_id_b );
		update_option( 'revit_overlap_decision_' . $key, array(
			'decision'   => $decision,
			'decided_at' => gmdate( 'c' ),
			'user_id'    => get_current_user_id(),
		), false );
		return true;
	}

	private function overlap_key( int $a, int $b ): string {
		$ids = array( $a, $b );
		sort( $ids );
		return md5( implode( ':', $ids ) );
	}

	private function retarget_inbound_links( int $source_post_id, int $destination_post_id ): int {
		$dest_key  = (string) get_post_meta( $destination_post_id, RevIt_Publisher_Post_Meta_Keys::ARTICLE_KEY, true );
		$source_key = (string) get_post_meta( $source_post_id, RevIt_Publisher_Post_Meta_Keys::ARTICLE_KEY, true );
		$count     = 0;

		foreach ( RevIt_Publisher_Services::graph()->get_inbound_relationships( $source_post_id ) as $link ) {
			$source_id = (int) ( $link['source_post_id'] ?? 0 );
			if ( $source_id <= 0 ) {
				continue;
			}
			$links = get_post_meta( $source_id, RevIt_Publisher_Post_Meta_Keys::INTERNAL_LINKS, true );
			if ( ! is_array( $links ) ) {
				continue;
			}
			$changed = false;
			foreach ( $links as &$item ) {
				if ( (string) ( $item['target_article_key'] ?? '' ) === $source_key ) {
					$item['target_article_key'] = $dest_key;
					$changed = true;
				}
			}
			unset( $item );
			if ( $changed ) {
				update_post_meta( $source_id, RevIt_Publisher_Post_Meta_Keys::INTERNAL_LINKS, $links );
				++$count;
			}
		}

		return $count;
	}
}
