<?php
/**
 * Post meta keys for RevIt operations CPTs.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Meta keys for audit snapshots, issues, redirects, link changes, and 404 entries.
 */
final class RevIt_Publisher_Operations_Meta_Keys {

	// Audit snapshot.
	public const SNAPSHOT_DATA       = '_revit_snapshot_data';
	public const SNAPSHOT_CREATED_AT = '_revit_snapshot_created_at';

	// Issue queue.
	public const ISSUE_TYPE          = '_revit_issue_type';
	public const ISSUE_SEVERITY      = '_revit_issue_severity';
	public const ISSUE_STATUS        = '_revit_issue_status';
	public const ISSUE_FINGERPRINT   = '_revit_issue_fingerprint';
	public const ISSUE_VEHICLE       = '_revit_issue_vehicle';
	public const ISSUE_POST_ID       = '_revit_issue_post_id';
	public const ISSUE_ARTICLE_KEY   = '_revit_issue_article_key';
	public const ISSUE_CLUSTER_KEY   = '_revit_issue_cluster_key';
	public const ISSUE_EXPLANATION   = '_revit_issue_explanation';
	public const ISSUE_ACTION        = '_revit_issue_recommended_action';
	public const ISSUE_FIRST_SEEN    = '_revit_issue_first_detected';
	public const ISSUE_LAST_SEEN     = '_revit_issue_last_detected';
	public const ISSUE_RESOLVED_AT   = '_revit_issue_resolved_at';
	public const ISSUE_CONTEXT       = '_revit_issue_context';

	// Redirect.
	public const REDIRECT_SOURCE     = '_revit_redirect_source_path';
	public const REDIRECT_TARGET_ID  = '_revit_redirect_target_post_id';
	public const REDIRECT_TARGET_URL = '_revit_redirect_target_url';
	public const REDIRECT_TYPE       = '_revit_redirect_type';
	public const REDIRECT_REASON     = '_revit_redirect_reason';
	public const REDIRECT_STATUS     = '_revit_redirect_status';
	public const REDIRECT_CREATED_AT = '_revit_redirect_created_at';
	public const REDIRECT_CREATED_BY = '_revit_redirect_created_by';

	// Link change log.
	public const LINK_LOG_SOURCE     = '_revit_link_log_source_post_id';
	public const LINK_LOG_TARGET     = '_revit_link_log_target_post_id';
	public const LINK_LOG_ACTION     = '_revit_link_log_action';
	public const LINK_LOG_ANCHOR     = '_revit_link_log_anchor';
	public const LINK_LOG_RELATION   = '_revit_link_log_relationship';
	public const LINK_LOG_PRE_HASH   = '_revit_link_log_pre_content_hash';
	public const LINK_LOG_POST_HASH  = '_revit_link_log_post_content_hash';
	public const LINK_LOG_USER       = '_revit_link_log_user_id';
	public const LINK_LOG_UNDONE     = '_revit_link_log_undone';

	// 404 monitor.
	public const NOT_FOUND_PATH      = '_revit_404_path';
	public const NOT_FOUND_HITS      = '_revit_404_hits';
	public const NOT_FOUND_LAST      = '_revit_404_last_seen';
	public const NOT_FOUND_REFERRER  = '_revit_404_referrer';
	public const NOT_FOUND_STATUS    = '_revit_404_status';
	public const NOT_FOUND_MATCH     = '_revit_404_has_match';
}
