<?php
/**
 * Meta keys for editorial queue items.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class RevIt_Publisher_Editorial_Meta_Keys {

	public const ACTION_TYPE      = '_revit_editorial_action_type';
	public const PRIORITY_LEVEL   = '_revit_editorial_priority_level';
	public const PRIORITY_SCORE   = '_revit_editorial_priority_score';
	public const STATUS           = '_revit_editorial_status';
	public const FINGERPRINT      = '_revit_editorial_fingerprint';
	public const VEHICLE          = '_revit_editorial_vehicle';
	public const POST_ID          = '_revit_editorial_post_id';
	public const ARTICLE_KEY      = '_revit_editorial_article_key';
	public const CLUSTER_KEY      = '_revit_editorial_cluster_key';
	public const PLAN_ID          = '_revit_editorial_plan_id';
	public const EXPLANATION      = '_revit_editorial_explanation';
	public const REASONS          = '_revit_editorial_reasons';
	public const SIGNALS          = '_revit_editorial_signals';
	public const NEXT_STEP        = '_revit_editorial_next_step';
	public const NOTES            = '_revit_editorial_notes';
	public const DEFERRED_UNTIL   = '_revit_editorial_deferred_until';
	public const COMPLETED_AT     = '_revit_editorial_completed_at';
	public const COOLDOWN_UNTIL   = '_revit_editorial_cooldown_until';
	public const MANUAL           = '_revit_editorial_manual';
	public const DUE_DATE         = '_revit_editorial_due_date';
	public const CREATED_AT       = '_revit_editorial_created_at';
	public const UPDATED_AT       = '_revit_editorial_updated_at';
	public const TITLE_LABEL      = '_revit_editorial_title_label';
}
