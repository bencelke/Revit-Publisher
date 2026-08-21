<?php
/**
 * Public rendered-page validation for published articles.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fetches public HTML for published posts. Drafts are skipped. Failures never throw.
 */
class RevIt_Publisher_Rendered_Page_Validator {

	private RevIt_Publisher_Rendered_Html_Analyzer $analyzer;

	public function __construct( ?RevIt_Publisher_Rendered_Html_Analyzer $analyzer = null ) {
		$this->analyzer = $analyzer ?? new RevIt_Publisher_Rendered_Html_Analyzer();
	}

	/**
	 * @return array<string, mixed>
	 */
	public function validate_post( int $post_id ): array {
		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return array(
				'skipped' => true,
				'reason'  => 'missing',
			);
		}

		if ( 'publish' !== $post->post_status ) {
			return array(
				'skipped' => true,
				'reason'  => 'not_public',
				'status'  => $post->post_status,
			);
		}

		$permalink = get_permalink( $post_id );
		if ( ! is_string( $permalink ) || '' === $permalink ) {
			return array(
				'skipped' => true,
				'reason'  => 'no_permalink',
			);
		}

		try {
			$response = wp_remote_get(
				$permalink,
				array(
					'timeout'     => 8,
					'redirection' => 2,
					'sslverify'   => false,
				)
			);
			if ( is_wp_error( $response ) ) {
				return array(
					'ok'      => false,
					'error'   => $response->get_error_message(),
					'checks'  => array(),
				);
			}

			$code = (int) wp_remote_retrieve_response_code( $response );
			$html = (string) wp_remote_retrieve_body( $response );
			$checks = $this->analyzer->analyze( $html, $code );

			return array(
				'ok'       => 200 === $code,
				'skipped'  => false,
				'permalink'=> $permalink,
				'checks'   => $checks,
				'http_status' => $code,
			);
		} catch ( Throwable $e ) {
			return array(
				'ok'      => false,
				'error'   => 'validator_failed',
				'message' => $e->getMessage(),
				'checks'  => array(),
			);
		}
	}
}
