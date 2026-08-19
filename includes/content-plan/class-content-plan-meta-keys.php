<?php
/**
 * Content plan post meta key constants.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Meta keys for revit_content_plan custom post type.
 */
final class RevIt_Publisher_Content_Plan_Meta_Keys {

	public const PLAN_KEY        = '_revit_plan_key';
	public const SCHEMA_VERSION  = '_revit_plan_schema_version';
	public const VEHICLE         = '_revit_plan_vehicle';
	public const PLAN_DATA       = '_revit_plan_data';
	public const PACKAGE_HASH    = '_revit_plan_hash';
	public const IMPORTED_AT     = '_revit_plan_imported_at';

	private function __construct() {}
}
