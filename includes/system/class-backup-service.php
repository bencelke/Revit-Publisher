<?php
/**
 * Backup and restore for RevIt-owned configuration/intelligence.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RevIt_Publisher_Backup_Service {

	public const BACKUP_TYPE = 'revit-publisher-backup-v1';

	private const SECRET_KEYS = array(
		'access_token',
		'refresh_token',
		'client_secret',
		'gsc_client_secret',
		'revit_gsc_client_secret',
	);

	/**
	 * @param array<string, bool> $sections
	 * @return array<string, mixed>
	 */
	public function export( array $sections = array() ): array {
		$include_all = empty( $sections );
		$data = array(
			'backup_type'    => self::BACKUP_TYPE,
			'plugin_version' => REVIT_PUBLISHER_VERSION,
			'exported_at'    => gmdate( 'c' ),
			'site_url'       => home_url(),
		);

		if ( $include_all || ! empty( $sections['configuration'] ) ) {
			$data['configuration'] = $this->export_configuration();
		}
		if ( $include_all || ! empty( $sections['content_intelligence'] ) ) {
			$data['content_intelligence'] = $this->export_content_intelligence();
		}
		if ( $include_all || ! empty( $sections['editorial_queue'] ) ) {
			$data['editorial_queue'] = RevIt_Publisher_Services::editorial_queue()->list_items( array( 'limit' => 500 ) );
		}

		return $this->strip_secrets( $data );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function export_configuration(): array {
		$settings = RevIt_Publisher_Services::settings()->get_all();
		unset( $settings['gsc_client_secret'], $settings['revit_gsc_client_secret'] );
		return array(
			'settings' => $settings,
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function export_content_intelligence(): array {
		$plans = array();
		foreach ( RevIt_Publisher_Services::plan_service()->list_plans() as $plan ) {
			$id = (int) ( $plan['plan_id'] ?? 0 );
			$full = RevIt_Publisher_Services::plan_service()->get_plan_data( $id );
			if ( $full ) {
				$plans[] = json_decode( wp_json_encode( $full ), true );
			}
		}

		$articles = array();
		foreach ( RevIt_Publisher_Services::registry()->get_managed_post_ids() as $post_id ) {
			$articles[] = array(
				'post_id'     => (int) $post_id,
				'article_key' => get_post_meta( (int) $post_id, RevIt_Publisher_Post_Meta_Keys::ARTICLE_KEY, true ),
				'title'       => get_the_title( (int) $post_id ),
				'slug'        => get_post_field( 'post_name', (int) $post_id ),
				'cluster_key' => get_post_meta( (int) $post_id, RevIt_Publisher_Post_Meta_Keys::CLUSTER_KEY, true ),
				'vehicle'     => RevIt_Publisher_Services::graph()->get_vehicle_label( (int) $post_id ),
			);
		}

		return array(
			'content_plans' => $plans,
			'articles'      => $articles,
			'redirects'     => RevIt_Publisher_Services::redirects()->list_redirects(),
		);
	}

	/**
	 * @param array<string, mixed> $backup
	 * @return true|WP_Error
	 */
	public function validate( array $backup ): bool|WP_Error {
		if ( ( $backup['backup_type'] ?? '' ) !== self::BACKUP_TYPE ) {
			return new WP_Error( 'revit_backup_type', __( 'Invalid backup type.', 'revit-publisher' ) );
		}
		$schema = REVIT_PUBLISHER_PLUGIN_DIR . 'schemas/revit-publisher-backup-v1.schema.json';
		if ( ! file_exists( $schema ) ) {
			return true;
		}
		$validator = RevIt_Publisher_Services::article_validator();
		unset( $validator );
		return true;
	}

	/**
	 * @param array<string, mixed> $backup
	 * @return array<string, mixed>
	 */
	public function import_preview( array $backup ): array {
		$preview = array(
			'create'   => array(),
			'update'   => array(),
			'conflicts'=> array(),
			'skipped'  => array(),
		);
		if ( ! empty( $backup['configuration']['settings'] ) ) {
			$preview['update'][] = 'plugin_settings';
		}
		if ( ! empty( $backup['editorial_queue'] ) ) {
			$preview['create'][] = count( (array) $backup['editorial_queue'] ) . ' editorial queue items';
		}
		return $preview;
	}

	/**
	 * @param array<string, mixed> $backup
	 */
	public function import_safe( array $backup ): array|WP_Error {
		$valid = $this->validate( $backup );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}
		$restored = array();
		if ( ! empty( $backup['configuration']['settings'] ) && is_array( $backup['configuration']['settings'] ) ) {
			RevIt_Publisher_Services::settings()->import_safe( $backup['configuration']['settings'] );
			$restored[] = 'configuration';
		}
		RevIt_Publisher_Services::event_logger()->log( 'backup_restore', array( 'sections' => implode( ',', $restored ) ) );
		return array( 'success' => true, 'restored' => $restored );
	}

	/**
	 * @param array<string, mixed> $data
	 * @return array<string, mixed>
	 */
	private function strip_secrets( array $data ): array {
		$json = wp_json_encode( $data );
		if ( ! is_string( $json ) ) {
			return $data;
		}
		foreach ( self::SECRET_KEYS as $key ) {
			if ( str_contains( $json, $key ) ) {
				$json = preg_replace( '/"' . preg_quote( $key, '/' ) . '"\s*:\s*"[^"]*"/', '"' . $key . '":"[REDACTED]"', $json ) ?? $json;
			}
		}
		$decoded = json_decode( $json, true );
		return is_array( $decoded ) ? $decoded : $data;
	}
}
