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
}
