<?php
/**
 * Service factory for RevIt Publisher graph/SEO/intelligence/operations services.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lazy singletons for graph, SEO, intelligence, and operations services.
 */
final class RevIt_Publisher_Services {

	private static ?RevIt_Publisher_Article_Registry $registry = null;
	private static ?RevIt_Publisher_Article_Resolver $resolver = null;
	private static ?RevIt_Publisher_Content_Graph $graph = null;
	private static ?RevIt_Publisher_Settings $settings = null;
	private static ?RevIt_Publisher_Internal_Link_Service $link_service = null;
	private static ?RevIt_Publisher_SEO_Health_Service $health_service = null;
	private static ?RevIt_Publisher_Link_Audit_Service $audit_service = null;
	private static ?RevIt_Publisher_Content_Plan_Service $plan_service = null;
	private static ?RevIt_Publisher_Topic_Overlap_Service $topic_overlaps = null;
	private static ?RevIt_Publisher_SEO_Score_Service $seo_score = null;
	private static ?RevIt_Publisher_Optimization_Service $optimization = null;
	private static ?RevIt_Publisher_Review_Status_Service $review_status = null;
	private static ?RevIt_Publisher_Audit_Service $site_audit = null;
	private static ?RevIt_Publisher_Issue_Service $issues = null;
	private static ?RevIt_Publisher_Event_Logger $event_logger = null;
	private static ?RevIt_Publisher_Link_Change_Log $link_change_log = null;
	private static ?RevIt_Publisher_Redirect_Service $redirects = null;
	private static ?RevIt_Publisher_Consolidation_Service $consolidation = null;
	private static ?RevIt_Publisher_404_Monitor $not_found_monitor = null;
	private static ?RevIt_Publisher_Pillar_Link_Policy_Service $pillar_links = null;
	private static ?RevIt_Publisher_Link_Undo_Service $link_undo = null;
	private static ?RevIt_Publisher_Vehicle_Health_Service $vehicle_health = null;
	private static ?RevIt_Publisher_Vehicle_Hub_Service $vehicle_hubs = null;
	private static ?RevIt_Publisher_Hub_Cache $hub_cache = null;
	private static ?RevIt_Publisher_Sitemap_Service $sitemap = null;
	private static ?RevIt_Publisher_Sitemap_Health_Service $sitemap_health = null;
	private static ?RevIt_Publisher_Cluster_Link_Matrix $cluster_link_matrix = null;
	private static ?RevIt_Publisher_Hub_SEO_Health $hub_seo_health = null;
	private static ?RevIt_Publisher_SERP_Preview_Service $serp_preview = null;
	private static ?RevIt_Publisher_GSC_Token_Store $gsc_tokens = null;
	private static ?RevIt_Publisher_GSC_Auth_Service $gsc_auth = null;
	private static ?RevIt_Publisher_GSC_Page_Mapper $gsc_mapper = null;
	private static ?RevIt_Publisher_GSC_Data_Store $gsc_data_store = null;
	private static ?RevIt_Publisher_GSC_Sync_Service $gsc_sync = null;
	private static ?RevIt_Publisher_GSC_Opportunity_Service $gsc_opportunities = null;
	private static ?RevIt_Publisher_GSC_URL_Inspection_Service $gsc_inspections = null;
	private static ?RevIt_Publisher_GSC_Sitemap_Service $gsc_sitemaps = null;
	private static ?RevIt_Publisher_GSC_Insights_Service $gsc_insights = null;
	private static ?RevIt_Publisher_GSC_Query_Coverage_Service $gsc_query_coverage = null;
	private static ?RevIt_Publisher_GSC_Content_Status $gsc_content_status = null;
	private static ?RevIt_Publisher_GSC_Refresh_Export $gsc_refresh_export = null;
	private static ?RevIt_Publisher_Migration_Service $migrations = null;
	private static ?RevIt_Publisher_Editorial_Priority_Service $editorial_priority = null;
	private static ?RevIt_Publisher_Editorial_Queue_Service $editorial_queue = null;
	private static ?RevIt_Publisher_Editorial_Queue_Reconciler $editorial_reconciler = null;
	private static ?RevIt_Publisher_Vehicle_Opportunity_Service $vehicle_opportunity = null;
	private static ?RevIt_Publisher_Cluster_Opportunity_Service $cluster_opportunity = null;
	private static ?RevIt_Publisher_System_Health_Service $system_health = null;
	private static ?RevIt_Publisher_Backup_Service $backup = null;
	private static ?RevIt_Publisher_Performance_Profiler $profiler = null;
	private static ?RevIt_Publisher_Fixture_Generator $fixture_generator = null;

	public static function registry(): RevIt_Publisher_Article_Registry {
		return self::$registry ??= new RevIt_Publisher_Article_Registry();
	}

	public static function resolver(): RevIt_Publisher_Article_Resolver {
		return self::$resolver ??= new RevIt_Publisher_Article_Resolver( self::registry() );
	}

	public static function settings(): RevIt_Publisher_Settings {
		return self::$settings ??= new RevIt_Publisher_Settings();
	}

	public static function graph(): RevIt_Publisher_Content_Graph {
		return self::$graph ??= new RevIt_Publisher_Content_Graph( self::resolver() );
	}

	public static function link_service(): RevIt_Publisher_Internal_Link_Service {
		return self::$link_service ??= new RevIt_Publisher_Internal_Link_Service(
			self::resolver(),
			self::graph(),
			self::settings()
		);
	}

	public static function health_service(): RevIt_Publisher_SEO_Health_Service {
		return self::$health_service ??= new RevIt_Publisher_SEO_Health_Service(
			self::graph(),
			self::link_service(),
			new RevIt_Publisher_Topic_Normalizer()
		);
	}

	public static function audit_service(): RevIt_Publisher_Link_Audit_Service {
		return self::$audit_service ??= new RevIt_Publisher_Link_Audit_Service(
			self::graph(),
			self::link_service(),
			self::health_service()
		);
	}

	public static function plan_service(): RevIt_Publisher_Content_Plan_Service {
		return self::$plan_service ??= new RevIt_Publisher_Content_Plan_Service( self::registry() );
	}

	public static function topic_overlaps(): RevIt_Publisher_Topic_Overlap_Service {
		return self::$topic_overlaps ??= new RevIt_Publisher_Topic_Overlap_Service(
			new RevIt_Publisher_Topic_Fingerprint()
		);
	}

	public static function seo_score(): RevIt_Publisher_SEO_Score_Service {
		return self::$seo_score ??= new RevIt_Publisher_SEO_Score_Service(
			self::graph(),
			self::link_service(),
			self::topic_overlaps()
		);
	}

	public static function optimization(): RevIt_Publisher_Optimization_Service {
		return self::$optimization ??= new RevIt_Publisher_Optimization_Service();
	}

	public static function review_status(): RevIt_Publisher_Review_Status_Service {
		return self::$review_status ??= new RevIt_Publisher_Review_Status_Service();
	}

	public static function site_audit(): RevIt_Publisher_Audit_Service {
		return self::$site_audit ??= new RevIt_Publisher_Audit_Service();
	}

	public static function issues(): RevIt_Publisher_Issue_Service {
		return self::$issues ??= new RevIt_Publisher_Issue_Service();
	}

	public static function event_logger(): RevIt_Publisher_Event_Logger {
		return self::$event_logger ??= new RevIt_Publisher_Event_Logger();
	}

	public static function link_change_log(): RevIt_Publisher_Link_Change_Log {
		return self::$link_change_log ??= new RevIt_Publisher_Link_Change_Log();
	}

	public static function redirects(): RevIt_Publisher_Redirect_Service {
		return self::$redirects ??= new RevIt_Publisher_Redirect_Service();
	}

	public static function consolidation(): RevIt_Publisher_Consolidation_Service {
		return self::$consolidation ??= new RevIt_Publisher_Consolidation_Service();
	}

	public static function not_found_monitor(): RevIt_Publisher_404_Monitor {
		return self::$not_found_monitor ??= new RevIt_Publisher_404_Monitor();
	}

	public static function pillar_links(): RevIt_Publisher_Pillar_Link_Policy_Service {
		return self::$pillar_links ??= new RevIt_Publisher_Pillar_Link_Policy_Service();
	}

	public static function link_undo(): RevIt_Publisher_Link_Undo_Service {
		return self::$link_undo ??= new RevIt_Publisher_Link_Undo_Service();
	}

	public static function vehicle_health(): RevIt_Publisher_Vehicle_Health_Service {
		return self::$vehicle_health ??= new RevIt_Publisher_Vehicle_Health_Service();
	}

	public static function vehicle_hubs(): RevIt_Publisher_Vehicle_Hub_Service {
		return self::$vehicle_hubs ??= new RevIt_Publisher_Vehicle_Hub_Service(
			self::resolver(),
			self::graph(),
			self::hub_cache()
		);
	}

	public static function hub_cache(): RevIt_Publisher_Hub_Cache {
		return self::$hub_cache ??= new RevIt_Publisher_Hub_Cache();
	}

	public static function sitemap(): RevIt_Publisher_Sitemap_Service {
		return self::$sitemap ??= new RevIt_Publisher_Sitemap_Service();
	}

	public static function sitemap_health(): RevIt_Publisher_Sitemap_Health_Service {
		return self::$sitemap_health ??= new RevIt_Publisher_Sitemap_Health_Service( self::sitemap() );
	}

	public static function cluster_link_matrix(): RevIt_Publisher_Cluster_Link_Matrix {
		return self::$cluster_link_matrix ??= new RevIt_Publisher_Cluster_Link_Matrix(
			self::graph(),
			self::link_service()
		);
	}

	public static function hub_seo_health(): RevIt_Publisher_Hub_SEO_Health {
		return self::$hub_seo_health ??= new RevIt_Publisher_Hub_SEO_Health(
			self::vehicle_hubs(),
			self::sitemap()
		);
	}

	public static function serp_preview(): RevIt_Publisher_SERP_Preview_Service {
		return self::$serp_preview ??= new RevIt_Publisher_SERP_Preview_Service( self::sitemap() );
	}

	public static function gsc_tokens(): RevIt_Publisher_GSC_Token_Store {
		return self::$gsc_tokens ??= new RevIt_Publisher_GSC_Token_Store();
	}

	public static function gsc_auth(): RevIt_Publisher_GSC_Auth_Service {
		return self::$gsc_auth ??= new RevIt_Publisher_GSC_Auth_Service( self::gsc_tokens(), self::settings() );
	}

	public static function gsc_client(): RevIt_Publisher_GSC_Client_Interface {
		return RevIt_Publisher_GSC_Client::create();
	}

	public static function gsc_mapper(): RevIt_Publisher_GSC_Page_Mapper {
		return self::$gsc_mapper ??= new RevIt_Publisher_GSC_Page_Mapper();
	}

	public static function gsc_data_store(): RevIt_Publisher_GSC_Data_Store {
		return self::$gsc_data_store ??= new RevIt_Publisher_GSC_Data_Store( self::gsc_mapper() );
	}

	public static function gsc_sync(): RevIt_Publisher_GSC_Sync_Service {
		return self::$gsc_sync ??= new RevIt_Publisher_GSC_Sync_Service(
			self::gsc_client(),
			self::gsc_data_store(),
			self::gsc_auth(),
			self::settings()
		);
	}

	public static function gsc_opportunities(): RevIt_Publisher_GSC_Opportunity_Service {
		return self::$gsc_opportunities ??= new RevIt_Publisher_GSC_Opportunity_Service(
			self::gsc_data_store(),
			self::settings()
		);
	}

	public static function gsc_inspections(): RevIt_Publisher_GSC_URL_Inspection_Service {
		return self::$gsc_inspections ??= new RevIt_Publisher_GSC_URL_Inspection_Service(
			self::gsc_client(),
			self::gsc_data_store(),
			self::settings()
		);
	}

	public static function gsc_sitemaps(): RevIt_Publisher_GSC_Sitemap_Service {
		return self::$gsc_sitemaps ??= new RevIt_Publisher_GSC_Sitemap_Service(
			self::gsc_client(),
			self::settings()
		);
	}

	public static function gsc_insights(): RevIt_Publisher_GSC_Insights_Service {
		return self::$gsc_insights ??= new RevIt_Publisher_GSC_Insights_Service( self::gsc_data_store() );
	}

	public static function gsc_query_coverage(): RevIt_Publisher_GSC_Query_Coverage_Service {
		return self::$gsc_query_coverage ??= new RevIt_Publisher_GSC_Query_Coverage_Service( self::gsc_data_store() );
	}

	public static function gsc_content_status(): RevIt_Publisher_GSC_Content_Status {
		return self::$gsc_content_status ??= new RevIt_Publisher_GSC_Content_Status(
			self::gsc_data_store(),
			self::settings()
		);
	}

	public static function gsc_refresh_export(): RevIt_Publisher_GSC_Refresh_Export {
		return self::$gsc_refresh_export ??= new RevIt_Publisher_GSC_Refresh_Export();
	}

	public static function migrations(): RevIt_Publisher_Migration_Service {
		return self::$migrations ??= new RevIt_Publisher_Migration_Service();
	}

	public static function editorial_priority(): RevIt_Publisher_Editorial_Priority_Service {
		return self::$editorial_priority ??= new RevIt_Publisher_Editorial_Priority_Service();
	}

	public static function editorial_queue(): RevIt_Publisher_Editorial_Queue_Service {
		return self::$editorial_queue ??= new RevIt_Publisher_Editorial_Queue_Service();
	}

	public static function editorial_reconciler(): RevIt_Publisher_Editorial_Queue_Reconciler {
		return self::$editorial_reconciler ??= new RevIt_Publisher_Editorial_Queue_Reconciler();
	}

	public static function vehicle_opportunity(): RevIt_Publisher_Vehicle_Opportunity_Service {
		return self::$vehicle_opportunity ??= new RevIt_Publisher_Vehicle_Opportunity_Service();
	}

	public static function cluster_opportunity(): RevIt_Publisher_Cluster_Opportunity_Service {
		return self::$cluster_opportunity ??= new RevIt_Publisher_Cluster_Opportunity_Service();
	}

	public static function system_health(): RevIt_Publisher_System_Health_Service {
		return self::$system_health ??= new RevIt_Publisher_System_Health_Service();
	}

	public static function backup(): RevIt_Publisher_Backup_Service {
		return self::$backup ??= new RevIt_Publisher_Backup_Service();
	}

	public static function profiler(): RevIt_Publisher_Performance_Profiler {
		return self::$profiler ??= new RevIt_Publisher_Performance_Profiler();
	}

	public static function fixture_generator(): RevIt_Publisher_Fixture_Generator {
		return self::$fixture_generator ??= new RevIt_Publisher_Fixture_Generator();
	}

	private function __construct() {}
}
