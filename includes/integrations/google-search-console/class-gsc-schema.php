<?php
/**
 * GSC custom database tables.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RevIt_Publisher_GSC_Schema {

	public static function install(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$page    = $wpdb->prefix . 'revit_gsc_page_metrics';
		$query   = $wpdb->prefix . 'revit_gsc_query_metrics';
		$inspect = $wpdb->prefix . 'revit_gsc_inspections';

		dbDelta(
			"CREATE TABLE {$page} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				property varchar(255) NOT NULL,
				page_url varchar(2048) NOT NULL,
				post_id bigint(20) unsigned DEFAULT 0,
				hub_id bigint(20) unsigned DEFAULT 0,
				metric_date date NOT NULL,
				window_key varchar(20) NOT NULL DEFAULT '28d',
				clicks int(11) NOT NULL DEFAULT 0,
				impressions int(11) NOT NULL DEFAULT 0,
				ctr decimal(8,6) NOT NULL DEFAULT 0,
				position decimal(8,4) NOT NULL DEFAULT 0,
				synced_at datetime NOT NULL,
				PRIMARY KEY (id),
				KEY page_url (page_url(191)),
				KEY metric_date (metric_date),
				KEY page_date (page_url(191), metric_date),
				KEY post_id (post_id),
				KEY window_key (window_key)
			) {$charset};"
		);

		dbDelta(
			"CREATE TABLE {$query} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				property varchar(255) NOT NULL,
				page_url varchar(2048) NOT NULL,
				query_text varchar(512) NOT NULL,
				metric_date date NOT NULL,
				window_key varchar(20) NOT NULL DEFAULT '28d',
				clicks int(11) NOT NULL DEFAULT 0,
				impressions int(11) NOT NULL DEFAULT 0,
				ctr decimal(8,6) NOT NULL DEFAULT 0,
				position decimal(8,4) NOT NULL DEFAULT 0,
				synced_at datetime NOT NULL,
				PRIMARY KEY (id),
				KEY page_url (page_url(191)),
				KEY query_page (query_text(191), page_url(191)),
				KEY metric_date (metric_date)
			) {$charset};"
		);

		dbDelta(
			"CREATE TABLE {$inspect} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				property varchar(255) NOT NULL,
				page_url varchar(2048) NOT NULL,
				post_id bigint(20) unsigned DEFAULT 0,
				indexed tinyint(1) NOT NULL DEFAULT 0,
				result_json longtext NOT NULL,
				inspected_at datetime NOT NULL,
				PRIMARY KEY (id),
				KEY page_url (page_url(191)),
				KEY post_id (post_id)
			) {$charset};"
		);
	}

	public static function maybe_install(): void {
		global $wpdb;
		$table = $wpdb->prefix . 'revit_gsc_page_metrics';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( $table !== $exists ) {
			self::install();
		}
	}

	public static function page_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'revit_gsc_page_metrics';
	}

	public static function query_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'revit_gsc_query_metrics';
	}

	public static function inspection_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'revit_gsc_inspections';
	}
}
