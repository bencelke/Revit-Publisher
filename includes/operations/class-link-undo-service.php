<?php
/**
 * Undo RevIt-applied internal links.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reverts link changes recorded in the change log.
 */
class RevIt_Publisher_Link_Undo_Service {

	/**
	 * @return true|WP_Error
	 */
	public function undo( int $log_id ) {
		$entry = RevIt_Publisher_Services::link_change_log()->get_entry( $log_id );
		if ( null === $entry || ! empty( $entry['undone'] ) ) {
			return new WP_Error( 'revit_invalid_log', __( 'Link change log entry not found or already undone.', 'revit-publisher' ) );
		}

		$source_id = (int) ( $entry['source_post_id'] ?? 0 );
		if ( ! current_user_can( 'edit_post', $source_id ) ) {
			return new WP_Error( 'revit_forbidden', __( 'You cannot edit this post.', 'revit-publisher' ) );
		}

		$post = get_post( $source_id );
		if ( ! $post instanceof WP_Post ) {
			return new WP_Error( 'revit_invalid_post', __( 'Post not found.', 'revit-publisher' ) );
		}

		$current_hash = hash( 'sha256', $post->post_content );
		$logged_hash  = (string) ( $entry['post_hash'] ?? '' );

		if ( '' !== $logged_hash && $current_hash !== $logged_hash ) {
			return new WP_Error(
				'revit_content_changed',
				__( 'Content has changed since the link was applied. Undo manually or use WordPress revisions.', 'revit-publisher' )
			);
		}

		$anchor = (string) ( $entry['anchor'] ?? '' );
		$target_id = (int) ( $entry['target_post_id'] ?? 0 );
		$permalink = get_permalink( $target_id );
		if ( ! is_string( $permalink ) || '' === $permalink || '' === $anchor ) {
			return new WP_Error( 'revit_undo_failed', __( 'Unable to locate link for removal.', 'revit-publisher' ) );
		}

		$new_content = $this->remove_link( $post->post_content, $anchor, $permalink );
		if ( null === $new_content ) {
			return new WP_Error( 'revit_undo_failed', __( 'Link anchor not found in current content.', 'revit-publisher' ) );
		}

		wp_save_post_revision( $source_id );
		wp_update_post(
			array(
				'ID'           => $source_id,
				'post_content' => $new_content,
			)
		);

		RevIt_Publisher_Services::link_change_log()->mark_undone( $log_id );
		RevIt_Publisher_Services::event_logger()->log( 'link_undone', array( 'log_id' => $log_id ) );

		return true;
	}

	private function remove_link( string $content, string $anchor, string $url ): ?string {
		$escaped_url = preg_quote( esc_url( $url ), '/' );
		$escaped_anchor = preg_quote( $anchor, '/' );
		$pattern = '/<a\\s+[^>]*href=["\']' . $escaped_url . '["\'][^>]*>' . $escaped_anchor . '<\\/a>/i';
		$result  = preg_replace( $pattern, $anchor, $content, 1, $count );
		return ( 1 === $count && is_string( $result ) ) ? $result : null;
	}
}
