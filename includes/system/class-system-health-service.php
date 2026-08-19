<?php
/**
 * Production diagnostics and self-test.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RevIt_Publisher_System_Health_Service {

	/**
	 * @return array<string, mixed>
	 */
	public function get_diagnostics(): array {
		return array(
			'wordpress'       => $this->wordpress_info(),
			'plugin'          => $this->plugin_info(),
			'composer'        => $this->composer_info(),
			'cron'            => $this->cron_info(),
			'search_console'  => $this->gsc_info(),
			'storage'         => $this->storage_counts(),
			'rewrite'         => $this->rewrite_info(),
			'recent_events'   => RevIt_Publisher_Services::event_logger()->get_recent( 20 ),
			'checks'          => $this->run_checks(),
		);
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function run_checks(): array {
		$checks = array();
		$checks[] = $this->check( 'php_version', version_compare( PHP_VERSION, '8.2', '>=' ), 'PHP 8.2+ required', PHP_VERSION );
		$checks[] = $this->check( 'wp_version', version_compare( get_bloginfo( 'version' ), '6.0', '>=' ), 'WordPress 6.0+ required', get_bloginfo( 'version' ) );
		$checks[] = $this->check(
			'gsc_tables',
			RevIt_Publisher_GSC_Schema::tables_exist(),
			'GSC database tables present',
			RevIt_Publisher_GSC_Schema::tables_exist() ? 'ok' : 'missing'
		);
		$checks[] = $this->check(
			'google_client',
			class_exists( 'Google\Client' ) || class_exists( 'RevIt_Publisher_GSC_Fake_Client' ),
			'Google API client or fixture available',
			class_exists( 'Google\Client' ) ? 'google/apiclient' : 'fixture'
		);
		$checks[] = $this->check(
			'json_schema',
			file_exists( REVIT_PUBLISHER_PLUGIN_DIR . 'vendor/justinrainbow/json-schema/src/JsonSchema/Validator.php' )
				|| class_exists( 'JsonSchema\\Validator' ),
			'JSON Schema validator available',
			'ok'
		);
		$checks[] = $this->check(
			'audit_cron',
			(bool) wp_next_scheduled( RevIt_Publisher_Audit_Service::CRON_HOOK ),
			'Audit cron scheduled',
			wp_next_scheduled( RevIt_Publisher_Audit_Service::CRON_HOOK ) ? gmdate( 'c', (int) wp_next_scheduled( RevIt_Publisher_Audit_Service::CRON_HOOK ) ) : 'not scheduled'
		);
		$checks[] = $this->check(
			'gsc_cron',
			(bool) wp_next_scheduled( RevIt_Publisher_GSC_Cron::CRON_HOOK ),
			'GSC sync cron scheduled',
			wp_next_scheduled( RevIt_Publisher_GSC_Cron::CRON_HOOK ) ? gmdate( 'c', (int) wp_next_scheduled( RevIt_Publisher_GSC_Cron::CRON_HOOK ) ) : 'not scheduled'
		);
		$checks[] = $this->check(
			'schemas',
			file_exists( REVIT_PUBLISHER_PLUGIN_DIR . 'schemas/revit-article-v1.schema.json' )
				&& file_exists( REVIT_PUBLISHER_PLUGIN_DIR . 'schemas/revit-content-plan-v1.schema.json' ),
			'Contract schema files present',
			'ok'
		);
		$checks[] = $this->check(
			'admin_dist',
			file_exists( REVIT_PUBLISHER_PLUGIN_DIR . 'admin/dist/assets/main.js' ),
			'Built admin assets present',
			'ok'
		);
		$dup_keys = $this->find_duplicate_article_keys();
		$checks[] = $this->check( 'duplicate_keys', empty( $dup_keys ), 'No duplicate article keys', empty( $dup_keys ) ? 'ok' : implode( ', ', $dup_keys ) );
		$loops = $this->find_redirect_loops();
		$checks[] = $this->check( 'redirect_loops', empty( $loops ), 'No redirect loops detected', empty( $loops ) ? 'ok' : count( $loops ) . ' loops' );
		return $checks;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function wordpress_info(): array {
		global $wp_rewrite;
		return array(
			'version'   => get_bloginfo( 'version' ),
			'php'       => PHP_VERSION,
			'permalink' => $wp_rewrite instanceof WP_Rewrite ? $wp_rewrite->permalink_structure : '',
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function plugin_info(): array {
		return array(
			'version'     => REVIT_PUBLISHER_VERSION,
			'db_version'  => RevIt_Publisher_Services::migrations()->get_installed_version(),
			'db_target'   => RevIt_Publisher_Migration_Service::TARGET_VERSION,
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function composer_info(): array {
		return array(
			'autoload'     => file_exists( REVIT_PUBLISHER_PLUGIN_DIR . 'vendor/autoload.php' ),
			'google_api'   => class_exists( 'Google\Client' ),
			'json_schema'  => class_exists( 'JsonSchema\\Validator' ),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function cron_info(): array {
		return array(
			'audit_next'    => wp_next_scheduled( RevIt_Publisher_Audit_Service::CRON_HOOK )
				? gmdate( 'c', (int) wp_next_scheduled( RevIt_Publisher_Audit_Service::CRON_HOOK ) ) : null,
			'gsc_next'      => wp_next_scheduled( RevIt_Publisher_GSC_Cron::CRON_HOOK )
				? gmdate( 'c', (int) wp_next_scheduled( RevIt_Publisher_GSC_Cron::CRON_HOOK ) ) : null,
			'retention_next'=> wp_next_scheduled( RevIt_Publisher_Issue_Retention_Cron::CRON_HOOK )
				? gmdate( 'c', (int) wp_next_scheduled( RevIt_Publisher_Issue_Retention_Cron::CRON_HOOK ) ) : null,
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function gsc_info(): array {
		$auth = RevIt_Publisher_Services::gsc_auth();
		$status = $auth->get_status();
		$diag = 'disconnected';
		if ( ! empty( $status['connected'] ) ) {
			$diag = empty( $status['property'] ) ? 'property_not_selected' : 'connected';
		} elseif ( ! RevIt_Publisher_Services::settings()->gsc_has_credentials() ) {
			$diag = 'credentials_missing';
		}
		return array(
			'connected'  => ! empty( $status['connected'] ),
			'property'   => (string) ( $status['property'] ?? '' ),
			'last_sync'  => (string) ( $status['last_sync'] ?? '' ),
			'diagnostic' => $diag,
			'last_error' => get_option( 'revit_gsc_last_error', null ),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function storage_counts(): array {
		global $wpdb;
		$page_table = RevIt_Publisher_GSC_Schema::page_table();
		$gsc_pages  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$page_table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return array(
			'gsc_page_metrics' => $gsc_pages,
			'content_plans'    => (int) wp_count_posts( RevIt_Publisher_Content_Plan_Post_Type::POST_TYPE )->private,
			'articles'         => count( RevIt_Publisher_Services::registry()->get_managed_post_ids() ),
			'redirects'        => (int) wp_count_posts( RevIt_Publisher_Operations_Post_Types::REDIRECT )->private,
			'issues'           => RevIt_Publisher_Services::issues()->count_open(),
			'queue_items'      => count(
				RevIt_Publisher_Services::editorial_queue()->list_items( array( 'limit' => 500 ) )
			),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function rewrite_info(): array {
		return array(
			'vehicle_routes' => post_type_exists( RevIt_Publisher_Vehicle_Hub_Post_Type::POST_TYPE ),
			'flush_needed'   => false,
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function check( string $id, bool $pass, string $label, string $detail ): array {
		return array(
			'id'     => $id,
			'status' => $pass ? 'pass' : ( str_contains( $id, 'cron' ) ? 'warning' : 'fail' ),
			'label'  => $label,
			'detail' => $detail,
		);
	}

	/**
	 * @return string[]
	 */
	private function find_duplicate_article_keys(): array {
		global $wpdb;
		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT meta_value, COUNT(*) AS cnt FROM {$wpdb->postmeta}
				WHERE meta_key = %s AND meta_value != '' GROUP BY meta_value HAVING cnt > 1 LIMIT 10",
				RevIt_Publisher_Post_Meta_Keys::ARTICLE_KEY
			),
			ARRAY_A
		);
		return array_map( static fn( $r ) => (string) ( $r['meta_value'] ?? '' ), $rows ?: array() );
	}

	/**
	 * @return string[]
	 */
	private function find_redirect_loops(): array {
		$loops = array();
		$redirects = RevIt_Publisher_Services::redirects()->list_redirects();
		foreach ( $redirects as $redirect ) {
			$source = (string) ( $redirect['source_path'] ?? '' );
			$target = (string) ( $redirect['target_url'] ?? '' );
			if ( '' !== $source && str_contains( $target, $source ) ) {
				$loops[] = $source;
			}
		}
		return $loops;
	}
}
