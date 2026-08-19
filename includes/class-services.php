<?php
/**
 * Service factory for RevIt Publisher graph/SEO services.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lazy singletons for graph and SEO services.
 */
final class RevIt_Publisher_Services {

	private static ?RevIt_Publisher_Article_Registry $registry = null;
	private static ?RevIt_Publisher_Article_Resolver $resolver = null;
	private static ?RevIt_Publisher_Content_Graph $graph = null;
	private static ?RevIt_Publisher_Settings $settings = null;
	private static ?RevIt_Publisher_Internal_Link_Service $link_service = null;
	private static ?RevIt_Publisher_SEO_Health_Service $health_service = null;
	private static ?RevIt_Publisher_Link_Audit_Service $audit_service = null;

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

	private function __construct() {}
}
