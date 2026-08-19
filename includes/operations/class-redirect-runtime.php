<?php
/**
 * Frontend redirect runtime.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles RevIt 301 redirects on frontend requests.
 */
class RevIt_Publisher_Redirect_Runtime {

	public function init(): void {
		add_action( 'template_redirect', array( $this, 'maybe_redirect' ), 1 );
	}

	public function maybe_redirect(): void {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}

		$path = RevIt_Publisher_Services::redirects()->normalize_path(
			(string) ( wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ) ?? '' )
			. ( wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_QUERY )
				? '?' . wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_QUERY )
				: '' )
		);

		if ( str_starts_with( $path, '/wp-' ) ) {
			return;
		}

		$redirect = RevIt_Publisher_Services::redirects()->lookup( $path );
		if ( null === $redirect || RevIt_Publisher_Redirect_Service::STATUS_ACTIVE !== ( $redirect['status'] ?? '' ) ) {
			return;
		}

		$target = (string) ( $redirect['target_url'] ?? '' );
		$target_post_id = (int) ( $redirect['target_post_id'] ?? 0 );
		if ( $target_post_id > 0 ) {
			$permalink = get_permalink( $target_post_id );
			$target = is_string( $permalink ) ? $permalink : $target;
		}

		if ( '' === $target ) {
			return;
		}

		wp_safe_redirect( $target, 301 );
		exit;
	}
}
