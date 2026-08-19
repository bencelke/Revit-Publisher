<?php
/**
 * Service factory for RevIt Publisher graph/SEO/intelligence services.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lazy singletons for graph, SEO, and intelligence services.
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

	private function __construct() {}
}
