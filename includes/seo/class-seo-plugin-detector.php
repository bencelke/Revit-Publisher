<?php
/**
 * Detect conflicting SEO plugins.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Detects Yoast SEO and Rank Math to avoid duplicate metadata.
 */
class RevIt_Publisher_SEO_Plugin_Detector {

	/**
	 * Check whether a known conflicting SEO plugin is active.
	 */
	public static function has_active_conflict(): bool {
		return null !== self::get_active_plugin_name();
	}

	/**
	 * Get active conflicting plugin human name.
	 */
	public static function get_active_plugin_name(): ?string {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		if ( is_plugin_active( 'wordpress-seo/wp-seo.php' ) || is_plugin_active( 'wordpress-seo-premium/wp-seo-premium.php' ) ) {
			return 'Yoast SEO';
		}

		if ( is_plugin_active( 'seo-by-rank-math/rank-math.php' ) ) {
			return 'Rank Math';
		}

		return null;
	}

	/**
	 * Admin-facing conflict message.
	 */
	public static function get_conflict_message(): ?string {
		$name = self::get_active_plugin_name();
		if ( null === $name ) {
			return null;
		}

		return sprintf(
			/* translators: %s: SEO plugin name */
			__( '%s is active. RevIt Publisher public SEO output is disabled to avoid duplicate metadata.', 'revit-publisher' ),
			$name
		);
	}
}
