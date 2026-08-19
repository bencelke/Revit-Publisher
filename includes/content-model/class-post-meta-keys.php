<?php
/**
 * RevIt Publisher post meta key constants.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Canonical post meta keys for imported RevIt articles.
 */
final class RevIt_Publisher_Post_Meta_Keys {

	public const ARTICLE_KEY           = '_revit_article_key';
	public const SCHEMA_VERSION        = '_revit_schema_version';
	public const IMPORTED_AT           = '_revit_imported_at';
	public const PACKAGE_HASH          = '_revit_package_hash';
	public const PILLAR_ARTICLE_KEY    = '_revit_pillar_article_key';
	public const INTERNAL_LINKS        = '_revit_internal_links';
	public const RELATED_ARTICLES      = '_revit_related_articles';
	public const PRIMARY_TOPIC         = '_revit_primary_topic';
	public const SECONDARY_TOPICS      = '_revit_secondary_topics';
	public const SEARCH_INTENT         = '_revit_search_intent';
	public const SEO_TITLE             = '_revit_seo_title';
	public const META_DESCRIPTION      = '_revit_meta_description';
	public const CANONICAL             = '_revit_canonical';
	public const INDEX                 = '_revit_index';
	public const FOLLOW                = '_revit_follow';
	public const SOURCES               = '_revit_sources';
	public const STRUCTURED_DATA       = '_revit_structured_data';
	public const VEHICLE_MANUFACTURER  = '_revit_vehicle_manufacturer';
	public const VEHICLE_MODEL         = '_revit_vehicle_model';
	public const VEHICLE_GENERATION    = '_revit_vehicle_generation';
	public const VEHICLE_TRIM          = '_revit_vehicle_trim';
	public const VEHICLE_START_YEAR    = '_revit_vehicle_start_year';
	public const VEHICLE_END_YEAR      = '_revit_vehicle_end_year';
	public const VEHICLE_ENGINES       = '_revit_vehicle_engines';
	public const CLUSTER_KEY           = '_revit_cluster_key';
	public const MANAGED               = '_revit_managed';

	/**
	 * Prevent instantiation.
	 */
	private function __construct() {}
}
