<?php
/**
 * Vehicle hub post meta keys.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class RevIt_Publisher_Vehicle_Hub_Meta_Keys {

	public const VEHICLE_KEY       = '_revit_vehicle_key';
	public const MANUFACTURER      = '_revit_hub_manufacturer';
	public const MODEL             = '_revit_hub_model';
	public const GENERATION        = '_revit_hub_generation';
	public const TRIM              = '_revit_hub_trim';
	public const START_YEAR        = '_revit_hub_start_year';
	public const END_YEAR          = '_revit_hub_end_year';
	public const ENGINES           = '_revit_hub_engines';
	public const INTRO             = '_revit_hub_intro';
	public const CONTENT_PLAN_ID   = '_revit_hub_content_plan_id';

	private function __construct() {}
}
