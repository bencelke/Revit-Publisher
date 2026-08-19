<?php
/**
 * RevIt Publisher plugin settings.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages WordPress options for SEO and linking behavior.
 */
class RevIt_Publisher_Settings {

	public const OPTION_GROUP = 'revit_publisher_settings';

	public const ENABLE_META_DESCRIPTION   = 'revit_publisher_enable_meta_description';
	public const ENABLE_CANONICAL          = 'revit_publisher_enable_canonical';
	public const ENABLE_ROBOTS             = 'revit_publisher_enable_robots';
	public const ENABLE_ARTICLE_SCHEMA     = 'revit_publisher_enable_article_schema';
	public const ENABLE_BREADCRUMB_SCHEMA  = 'revit_publisher_enable_breadcrumb_schema';
	public const INTERNAL_LINK_MODE        = 'revit_publisher_internal_link_mode';
	public const MAX_SUGGESTED_LINKS         = 'revit_publisher_max_suggested_links';
	public const AVOID_DUPLICATE_TARGET    = 'revit_publisher_avoid_duplicate_target';
	public const ORG_NAME                  = 'revit_publisher_org_name';
	public const ORG_LOGO_URL              = 'revit_publisher_org_logo_url';
	public const REVIEW_AFTER_MONTHS       = 'revit_publisher_review_after_months';
	public const MAX_BATCH_LINKS           = 'revit_publisher_max_batch_links';
	public const SCHEDULED_AUDIT_ENABLED   = 'revit_publisher_scheduled_audit_enabled';
	public const AUDIT_FREQUENCY           = 'revit_publisher_audit_frequency';
	public const ENABLE_404_MONITOR        = 'revit_publisher_enable_404_monitor';
	public const EXTERNAL_REDIRECTS        = 'revit_publisher_external_redirects_allowed';
	public const MAX_CLUSTER_LINKS         = 'revit_publisher_max_cluster_links_per_article';
	public const ISSUE_RETENTION_DAYS      = 'revit_publisher_issue_retention_days';

	public const GSC_PROPERTY              = 'revit_gsc_property';
	public const GSC_CLIENT_ID             = 'revit_gsc_client_id';
	public const GSC_CLIENT_SECRET         = 'revit_gsc_client_secret';
	public const GSC_SYNC_ENABLED          = 'revit_gsc_sync_enabled';
	public const GSC_SYNC_FREQUENCY        = 'revit_gsc_sync_frequency';
	public const GSC_MAX_ROWS              = 'revit_gsc_max_rows';
	public const GSC_INSPECTION_DAILY_MAX  = 'revit_gsc_inspection_daily_max';
	public const GSC_SITEMAP_WRITE         = 'revit_gsc_sitemap_write_enabled';
	public const GSC_MIN_IMPRESSIONS       = 'revit_gsc_min_impressions';
	public const GSC_PAGE2_MIN             = 'revit_gsc_page2_min';
	public const GSC_PAGE2_MAX             = 'revit_gsc_page2_max';
	public const GSC_ZERO_VISIBILITY_DAYS  = 'revit_gsc_zero_visibility_days';
	public const GSC_DECLINE_THRESHOLD_PCT = 'revit_gsc_decline_threshold_pct';

	public const EDITORIAL_COOLDOWN_DAYS   = 'revit_editorial_cooldown_days';
	public const DELETE_DATA_ON_UNINSTALL  = 'revit_delete_data_on_uninstall';

	public const LINK_MODE_SUGGEST = 'suggest_only';

	/**
	 * Register settings on admin init.
	 */
	public static function register(): void {
		register_setting( self::OPTION_GROUP, self::ENABLE_META_DESCRIPTION, array(
			'type'              => 'boolean',
			'sanitize_callback' => array( self::class, 'sanitize_bool' ),
			'default'           => true,
		) );
		register_setting( self::OPTION_GROUP, self::ENABLE_CANONICAL, array(
			'type'              => 'boolean',
			'sanitize_callback' => array( self::class, 'sanitize_bool' ),
			'default'           => true,
		) );
		register_setting( self::OPTION_GROUP, self::ENABLE_ROBOTS, array(
			'type'              => 'boolean',
			'sanitize_callback' => array( self::class, 'sanitize_bool' ),
			'default'           => true,
		) );
		register_setting( self::OPTION_GROUP, self::ENABLE_ARTICLE_SCHEMA, array(
			'type'              => 'boolean',
			'sanitize_callback' => array( self::class, 'sanitize_bool' ),
			'default'           => true,
		) );
		register_setting( self::OPTION_GROUP, self::ENABLE_BREADCRUMB_SCHEMA, array(
			'type'              => 'boolean',
			'sanitize_callback' => array( self::class, 'sanitize_bool' ),
			'default'           => true,
		) );
		register_setting( self::OPTION_GROUP, self::INTERNAL_LINK_MODE, array(
			'type'              => 'string',
			'sanitize_callback' => array( self::class, 'sanitize_link_mode' ),
			'default'           => self::LINK_MODE_SUGGEST,
		) );
		register_setting( self::OPTION_GROUP, self::MAX_SUGGESTED_LINKS, array(
			'type'              => 'integer',
			'sanitize_callback' => array( self::class, 'sanitize_max_links' ),
			'default'           => 5,
		) );
		register_setting( self::OPTION_GROUP, self::AVOID_DUPLICATE_TARGET, array(
			'type'              => 'boolean',
			'sanitize_callback' => array( self::class, 'sanitize_bool' ),
			'default'           => true,
		) );
		register_setting( self::OPTION_GROUP, self::ORG_NAME, array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '',
		) );
		register_setting( self::OPTION_GROUP, self::ORG_LOGO_URL, array(
			'type'              => 'string',
			'sanitize_callback' => 'esc_url_raw',
			'default'           => '',
		) );
		register_setting( self::OPTION_GROUP, self::REVIEW_AFTER_MONTHS, array(
			'type'              => 'integer',
			'sanitize_callback' => array( self::class, 'sanitize_review_months' ),
			'default'           => 12,
		) );
		register_setting( self::OPTION_GROUP, self::MAX_BATCH_LINKS, array(
			'type'              => 'integer',
			'sanitize_callback' => array( self::class, 'sanitize_batch_links' ),
			'default'           => 50,
		) );
		register_setting( self::OPTION_GROUP, self::SCHEDULED_AUDIT_ENABLED, array(
			'type'              => 'boolean',
			'sanitize_callback' => array( self::class, 'sanitize_bool' ),
			'default'           => true,
		) );
		register_setting( self::OPTION_GROUP, self::AUDIT_FREQUENCY, array(
			'type'              => 'string',
			'sanitize_callback' => array( self::class, 'sanitize_audit_frequency' ),
			'default'           => 'daily',
		) );
		register_setting( self::OPTION_GROUP, self::ENABLE_404_MONITOR, array(
			'type'              => 'boolean',
			'sanitize_callback' => array( self::class, 'sanitize_bool' ),
			'default'           => false,
		) );
		register_setting( self::OPTION_GROUP, self::EXTERNAL_REDIRECTS, array(
			'type'              => 'boolean',
			'sanitize_callback' => array( self::class, 'sanitize_bool' ),
			'default'           => false,
		) );
		register_setting( self::OPTION_GROUP, self::MAX_CLUSTER_LINKS, array(
			'type'              => 'integer',
			'sanitize_callback' => array( self::class, 'sanitize_cluster_links' ),
			'default'           => 5,
		) );
		register_setting( self::OPTION_GROUP, self::ISSUE_RETENTION_DAYS, array(
			'type'              => 'integer',
			'sanitize_callback' => array( self::class, 'sanitize_retention_days' ),
			'default'           => 365,
		) );
		register_setting( self::OPTION_GROUP, self::GSC_PROPERTY, array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '',
		) );
		register_setting( self::OPTION_GROUP, self::GSC_CLIENT_ID, array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '',
		) );
		register_setting( self::OPTION_GROUP, self::GSC_CLIENT_SECRET, array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '',
		) );
		register_setting( self::OPTION_GROUP, self::GSC_SYNC_ENABLED, array(
			'type'              => 'boolean',
			'sanitize_callback' => array( self::class, 'sanitize_bool' ),
			'default'           => true,
		) );
		register_setting( self::OPTION_GROUP, self::GSC_SYNC_FREQUENCY, array(
			'type'              => 'string',
			'sanitize_callback' => array( self::class, 'sanitize_gsc_frequency' ),
			'default'           => 'daily',
		) );
		register_setting( self::OPTION_GROUP, self::GSC_MAX_ROWS, array(
			'type'              => 'integer',
			'sanitize_callback' => array( self::class, 'sanitize_gsc_max_rows' ),
			'default'           => 1000,
		) );
		register_setting( self::OPTION_GROUP, self::GSC_INSPECTION_DAILY_MAX, array(
			'type'              => 'integer',
			'sanitize_callback' => array( self::class, 'sanitize_gsc_inspection_max' ),
			'default'           => 20,
		) );
		register_setting( self::OPTION_GROUP, self::GSC_SITEMAP_WRITE, array(
			'type'              => 'boolean',
			'sanitize_callback' => array( self::class, 'sanitize_bool' ),
			'default'           => false,
		) );
		register_setting( self::OPTION_GROUP, self::GSC_MIN_IMPRESSIONS, array(
			'type'              => 'integer',
			'sanitize_callback' => array( self::class, 'sanitize_gsc_min_impressions' ),
			'default'           => 1000,
		) );
		register_setting( self::OPTION_GROUP, self::GSC_PAGE2_MIN, array(
			'type'              => 'integer',
			'sanitize_callback' => array( self::class, 'sanitize_gsc_page2' ),
			'default'           => 11,
		) );
		register_setting( self::OPTION_GROUP, self::GSC_PAGE2_MAX, array(
			'type'              => 'integer',
			'sanitize_callback' => array( self::class, 'sanitize_gsc_page2' ),
			'default'           => 20,
		) );
		register_setting( self::OPTION_GROUP, self::GSC_ZERO_VISIBILITY_DAYS, array(
			'type'              => 'integer',
			'sanitize_callback' => array( self::class, 'sanitize_gsc_grace_days' ),
			'default'           => 90,
		) );
		register_setting( self::OPTION_GROUP, self::GSC_DECLINE_THRESHOLD_PCT, array(
			'type'              => 'integer',
			'sanitize_callback' => array( self::class, 'sanitize_gsc_decline_pct' ),
			'default'           => 20,
		) );
	}

	/**
	 * Get all settings as array for REST/admin.
	 *
	 * @return array<string, mixed>
	 */
	public function all(): array {
		return array(
			'enable_meta_description'  => $this->enable_meta_description(),
			'enable_canonical'           => $this->enable_canonical(),
			'enable_robots'              => $this->enable_robots(),
			'enable_article_schema'      => $this->enable_article_schema(),
			'enable_breadcrumb_schema'   => $this->enable_breadcrumb_schema(),
			'internal_link_mode'         => $this->internal_link_mode(),
			'max_suggested_links'        => $this->max_suggested_links(),
			'avoid_duplicate_target'     => $this->avoid_duplicate_target(),
			'org_name'                   => $this->org_name(),
			'org_logo_url'               => $this->org_logo_url(),
			'review_after_months'        => $this->review_after_months(),
			'max_batch_links'            => $this->max_batch_links(),
			'scheduled_audit_enabled'    => $this->scheduled_audit_enabled(),
			'audit_frequency'            => $this->audit_frequency(),
			'enable_404_monitor'         => $this->enable_404_monitor(),
			'external_redirects_allowed' => $this->external_redirects_allowed(),
			'max_cluster_links_per_article' => $this->max_cluster_links_per_article(),
			'issue_retention_days'       => $this->issue_retention_days(),
			'gsc_property'               => $this->gsc_property(),
			'gsc_sync_enabled'           => $this->gsc_sync_enabled(),
			'gsc_sync_frequency'         => $this->gsc_sync_frequency(),
			'gsc_max_rows'               => $this->gsc_max_rows(),
			'gsc_inspection_daily_max'   => $this->gsc_inspection_daily_max(),
			'gsc_sitemap_write_enabled'  => $this->gsc_sitemap_write_enabled(),
			'gsc_min_impressions'        => $this->gsc_min_impressions(),
			'gsc_page2_min'              => $this->gsc_page2_min(),
			'gsc_page2_max'              => $this->gsc_page2_max(),
			'gsc_zero_visibility_days'   => $this->gsc_zero_visibility_days(),
			'gsc_decline_threshold_pct'  => $this->gsc_decline_threshold_pct(),
			'gsc_client_id_configured'   => '' !== $this->gsc_client_id(),
			'public_seo_output_enabled'  => $this->public_seo_output_enabled(),
			'seo_plugin_conflict'        => RevIt_Publisher_SEO_Plugin_Detector::get_conflict_message(),
		);
	}

	/**
	 * Whether public SEO output is allowed (no conflicting plugin).
	 */
	public function public_seo_output_enabled(): bool {
		return ! RevIt_Publisher_SEO_Plugin_Detector::has_active_conflict();
	}

	public function enable_meta_description(): bool {
		return $this->public_seo_output_enabled() && (bool) get_option( self::ENABLE_META_DESCRIPTION, true );
	}

	public function enable_canonical(): bool {
		return $this->public_seo_output_enabled() && (bool) get_option( self::ENABLE_CANONICAL, true );
	}

	public function enable_robots(): bool {
		return $this->public_seo_output_enabled() && (bool) get_option( self::ENABLE_ROBOTS, true );
	}

	public function enable_article_schema(): bool {
		return $this->public_seo_output_enabled() && (bool) get_option( self::ENABLE_ARTICLE_SCHEMA, true );
	}

	public function enable_breadcrumb_schema(): bool {
		return $this->public_seo_output_enabled() && (bool) get_option( self::ENABLE_BREADCRUMB_SCHEMA, true );
	}

	public function internal_link_mode(): string {
		return (string) get_option( self::INTERNAL_LINK_MODE, self::LINK_MODE_SUGGEST );
	}

	public function max_suggested_links(): int {
		return (int) get_option( self::MAX_SUGGESTED_LINKS, 5 );
	}

	public function avoid_duplicate_target(): bool {
		return (bool) get_option( self::AVOID_DUPLICATE_TARGET, true );
	}

	public function org_name(): string {
		return (string) get_option( self::ORG_NAME, get_bloginfo( 'name' ) );
	}

	public function org_logo_url(): string {
		return (string) get_option( self::ORG_LOGO_URL, '' );
	}

	public function review_after_months(): int {
		return (int) get_option( self::REVIEW_AFTER_MONTHS, 12 );
	}

	public function max_batch_links(): int {
		return (int) get_option( self::MAX_BATCH_LINKS, 50 );
	}

	public function scheduled_audit_enabled(): bool {
		return (bool) get_option( self::SCHEDULED_AUDIT_ENABLED, true );
	}

	public function audit_frequency(): string {
		$freq = (string) get_option( self::AUDIT_FREQUENCY, 'daily' );
		return in_array( $freq, array( 'daily', 'revit_twice_daily', 'weekly' ), true ) ? $freq : 'daily';
	}

	public function enable_404_monitor(): bool {
		return (bool) get_option( self::ENABLE_404_MONITOR, false );
	}

	public function external_redirects_allowed(): bool {
		return (bool) get_option( self::EXTERNAL_REDIRECTS, false );
	}

	public function max_cluster_links_per_article(): int {
		return (int) get_option( self::MAX_CLUSTER_LINKS, 5 );
	}

	public function issue_retention_days(): int {
		return (int) get_option( self::ISSUE_RETENTION_DAYS, 365 );
	}

	public function gsc_property(): string {
		return (string) get_option( self::GSC_PROPERTY, '' );
	}

	public function gsc_client_id(): string {
		if ( defined( 'REVIT_GSC_CLIENT_ID' ) ) {
			return (string) REVIT_GSC_CLIENT_ID;
		}
		return (string) get_option( self::GSC_CLIENT_ID, '' );
	}

	public function gsc_client_secret(): string {
		if ( defined( 'REVIT_GSC_CLIENT_SECRET' ) ) {
			return (string) REVIT_GSC_CLIENT_SECRET;
		}
		return (string) get_option( self::GSC_CLIENT_SECRET, '' );
	}

	public function gsc_sync_enabled(): bool {
		return (bool) get_option( self::GSC_SYNC_ENABLED, true );
	}

	public function gsc_sync_frequency(): string {
		$freq = (string) get_option( self::GSC_SYNC_FREQUENCY, 'daily' );
		return in_array( $freq, array( 'daily', 'weekly' ), true ) ? $freq : 'daily';
	}

	public function gsc_max_rows(): int {
		return (int) get_option( self::GSC_MAX_ROWS, 1000 );
	}

	public function gsc_inspection_daily_max(): int {
		return (int) get_option( self::GSC_INSPECTION_DAILY_MAX, 20 );
	}

	public function gsc_sitemap_write_enabled(): bool {
		return (bool) get_option( self::GSC_SITEMAP_WRITE, false );
	}

	public function gsc_min_impressions(): int {
		return (int) get_option( self::GSC_MIN_IMPRESSIONS, 1000 );
	}

	public function gsc_page2_min(): int {
		return (int) get_option( self::GSC_PAGE2_MIN, 11 );
	}

	public function gsc_page2_max(): int {
		return (int) get_option( self::GSC_PAGE2_MAX, 20 );
	}

	public function gsc_zero_visibility_days(): int {
		return (int) get_option( self::GSC_ZERO_VISIBILITY_DAYS, 90 );
	}

	public function gsc_decline_threshold_pct(): int {
		return (int) get_option( self::GSC_DECLINE_THRESHOLD_PCT, 20 );
	}

	public function editorial_cooldown_days(): int {
		return (int) get_option( self::EDITORIAL_COOLDOWN_DAYS, 30 );
	}

	public function delete_data_on_uninstall(): bool {
		return (bool) get_option( self::DELETE_DATA_ON_UNINSTALL, false );
	}

	public function gsc_has_credentials(): bool {
		return '' !== $this->gsc_client_id() && '' !== $this->gsc_client_secret();
	}

	/**
	 * @return array<string, mixed>
	 */
	public function get_all(): array {
		return array(
			'enable_meta_description'   => $this->enable_meta_description(),
			'enable_canonical'          => $this->enable_canonical(),
			'enable_robots'             => $this->enable_robots(),
			'enable_article_schema'     => $this->enable_article_schema(),
			'enable_breadcrumb_schema'  => $this->enable_breadcrumb_schema(),
			'internal_link_mode'        => $this->internal_link_mode(),
			'max_suggested_links'       => $this->max_suggested_links(),
			'avoid_duplicate_target'    => $this->avoid_duplicate_target(),
			'org_name'                  => $this->org_name(),
			'org_logo_url'              => $this->org_logo_url(),
			'review_after_months'       => $this->review_after_months(),
			'scheduled_audit_enabled'   => $this->scheduled_audit_enabled(),
			'audit_frequency'           => $this->audit_frequency(),
			'enable_404_monitor'        => $this->enable_404_monitor(),
			'external_redirects'        => $this->external_redirects_allowed(),
			'issue_retention_days'      => $this->issue_retention_days(),
			'editorial_cooldown_days'   => $this->editorial_cooldown_days(),
			'gsc_property'              => $this->gsc_property(),
			'gsc_sync_enabled'          => $this->gsc_sync_enabled(),
			'gsc_min_impressions'       => $this->gsc_min_impressions(),
		);
	}

	/**
	 * Restore safe settings from backup (no secrets).
	 *
	 * @param array<string, mixed> $settings
	 */
	public function import_safe( array $settings ): void {
		$map = array(
			'enable_meta_description'  => self::ENABLE_META_DESCRIPTION,
			'enable_canonical'         => self::ENABLE_CANONICAL,
			'enable_robots'            => self::ENABLE_ROBOTS,
			'enable_article_schema'    => self::ENABLE_ARTICLE_SCHEMA,
			'enable_breadcrumb_schema' => self::ENABLE_BREADCRUMB_SCHEMA,
			'internal_link_mode'       => self::INTERNAL_LINK_MODE,
			'max_suggested_links'      => self::MAX_SUGGESTED_LINKS,
			'avoid_duplicate_target'   => self::AVOID_DUPLICATE_TARGET,
			'org_name'                 => self::ORG_NAME,
			'org_logo_url'             => self::ORG_LOGO_URL,
			'review_after_months'      => self::REVIEW_AFTER_MONTHS,
			'scheduled_audit_enabled'  => self::SCHEDULED_AUDIT_ENABLED,
			'audit_frequency'          => self::AUDIT_FREQUENCY,
			'enable_404_monitor'       => self::ENABLE_404_MONITOR,
			'external_redirects'       => self::EXTERNAL_REDIRECTS,
			'issue_retention_days'     => self::ISSUE_RETENTION_DAYS,
			'editorial_cooldown_days'  => self::EDITORIAL_COOLDOWN_DAYS,
			'gsc_property'             => self::GSC_PROPERTY,
			'gsc_sync_enabled'         => self::GSC_SYNC_ENABLED,
			'gsc_min_impressions'      => self::GSC_MIN_IMPRESSIONS,
		);
		foreach ( $settings as $key => $value ) {
			if ( isset( $map[ $key ] ) ) {
				update_option( $map[ $key ], $value );
			}
		}
	}

	/**
	 * @param mixed $value Raw value.
	 */
	public static function sanitize_bool( mixed $value ): bool {
		return filter_var( $value, FILTER_VALIDATE_BOOLEAN );
	}

	/**
	 * @param mixed $value Raw value.
	 */
	public static function sanitize_link_mode( mixed $value ): string {
		return self::LINK_MODE_SUGGEST;
	}

	/**
	 * @param mixed $value Raw value.
	 */
	public static function sanitize_max_links( mixed $value ): int {
		$max = (int) $value;
		return max( 1, min( 20, $max ) );
	}

	public static function sanitize_review_months( mixed $value ): int {
		$months = (int) $value;
		return max( 0, min( 60, $months ) );
	}

	public static function sanitize_batch_links( mixed $value ): int {
		$max = (int) $value;
		return max( 1, min( 50, $max ) );
	}

	public static function sanitize_audit_frequency( mixed $value ): string {
		$freq = sanitize_key( (string) $value );
		return in_array( $freq, array( 'daily', 'revit_twice_daily', 'weekly' ), true ) ? $freq : 'daily';
	}

	public static function sanitize_cluster_links( mixed $value ): int {
		$max = (int) $value;
		return max( 1, min( 20, $max ) );
	}

	public static function sanitize_retention_days( mixed $value ): int {
		$days = (int) $value;
		return max( 30, min( 730, $days ) );
	}

	public static function sanitize_gsc_frequency( mixed $value ): string {
		$freq = sanitize_key( (string) $value );
		return in_array( $freq, array( 'daily', 'weekly' ), true ) ? $freq : 'daily';
	}

	public static function sanitize_gsc_max_rows( mixed $value ): int {
		$rows = (int) $value;
		return max( 100, min( 5000, $rows ) );
	}

	public static function sanitize_gsc_inspection_max( mixed $value ): int {
		$max = (int) $value;
		return max( 1, min( 100, $max ) );
	}

	public static function sanitize_gsc_min_impressions( mixed $value ): int {
		$min = (int) $value;
		return max( 100, min( 50000, $min ) );
	}

	public static function sanitize_gsc_page2( mixed $value ): int {
		$pos = (int) $value;
		return max( 1, min( 100, $pos ) );
	}

	public static function sanitize_gsc_grace_days( mixed $value ): int {
		$days = (int) $value;
		return max( 14, min( 365, $days ) );
	}

	public static function sanitize_gsc_decline_pct( mixed $value ): int {
		$pct = (int) $value;
		return max( 5, min( 90, $pct ) );
	}
}
