<?php
/**
 * WordPress admin integration.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once REVIT_PUBLISHER_PLUGIN_DIR . 'includes/admin/class-admin-assets.php';

/**
 * Registers admin menus and pages.
 */
class RevIt_Publisher_Admin {

	/**
	 * Admin instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Parent menu slug.
	 */
	public const MENU_SLUG = 'revit-publisher';

	/**
	 * Get admin instance.
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Initialize admin hooks.
	 */
	public function init(): void {
		add_action( 'admin_menu', array( $this, 'register_menus' ) );
		add_action( 'admin_notices', array( $this, 'maybe_show_seo_conflict_notice' ) );
		add_action( 'admin_notices', array( $this, 'maybe_show_audit_notice' ) );
		RevIt_Publisher_Admin_Assets::instance()->init();
	}

	/**
	 * Register top-level admin menu and subpages.
	 */
	public function register_menus(): void {
		add_menu_page(
			__( 'RevIt Publisher', 'revit-publisher' ),
			__( 'RevIt Publisher', 'revit-publisher' ),
			'edit_posts',
			self::MENU_SLUG,
			array( $this, 'render_dashboard_page' ),
			'dashicons-car',
			58
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Dashboard', 'revit-publisher' ),
			__( 'Dashboard', 'revit-publisher' ),
			'edit_posts',
			self::MENU_SLUG,
			array( $this, 'render_dashboard_page' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Import', 'revit-publisher' ),
			__( 'Import', 'revit-publisher' ),
			'edit_posts',
			self::MENU_SLUG . '-import',
			array( $this, 'render_import_page' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Content Planner', 'revit-publisher' ),
			__( 'Content Planner', 'revit-publisher' ),
			'edit_posts',
			self::MENU_SLUG . '-planner',
			array( $this, 'render_planner_page' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'SEO Health', 'revit-publisher' ),
			__( 'SEO Health', 'revit-publisher' ),
			'edit_posts',
			self::MENU_SLUG . '-seo-health',
			array( $this, 'render_seo_health_page' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Editorial Queue', 'revit-publisher' ),
			__( 'Editorial Queue', 'revit-publisher' ),
			'edit_posts',
			self::MENU_SLUG . '-editorial',
			array( $this, 'render_editorial_page' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Search Performance', 'revit-publisher' ),
			__( 'Search Performance', 'revit-publisher' ),
			'edit_posts',
			self::MENU_SLUG . '-search-performance',
			array( $this, 'render_search_performance_page' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Needs Attention', 'revit-publisher' ),
			__( 'Needs Attention', 'revit-publisher' ),
			'edit_posts',
			self::MENU_SLUG . '-attention',
			array( $this, 'render_attention_page' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Audits', 'revit-publisher' ),
			__( 'Audits', 'revit-publisher' ),
			'edit_posts',
			self::MENU_SLUG . '-audits',
			array( $this, 'render_audits_page' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Vehicles', 'revit-publisher' ),
			__( 'Vehicles', 'revit-publisher' ),
			'edit_posts',
			self::MENU_SLUG . '-vehicles',
			array( $this, 'render_vehicles_page' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Redirects', 'revit-publisher' ),
			__( 'Redirects', 'revit-publisher' ),
			'manage_options',
			self::MENU_SLUG . '-redirects',
			array( $this, 'render_redirects_page' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( '404 Monitor', 'revit-publisher' ),
			__( '404 Monitor', 'revit-publisher' ),
			'manage_options',
			self::MENU_SLUG . '-404',
			array( $this, 'render_404_page' )
		);

		add_action( 'admin_menu', array( $this, 'add_attention_badge' ), 999 );

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Content Graph', 'revit-publisher' ),
			__( 'Content Graph', 'revit-publisher' ),
			'edit_posts',
			self::MENU_SLUG . '-graph',
			array( $this, 'render_graph_page' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'System Health', 'revit-publisher' ),
			__( 'System Health', 'revit-publisher' ),
			'manage_options',
			self::MENU_SLUG . '-system-health',
			array( $this, 'render_system_health_page' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Settings', 'revit-publisher' ),
			__( 'Settings', 'revit-publisher' ),
			'manage_options',
			self::MENU_SLUG . '-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Show SEO plugin conflict notice on RevIt admin pages.
	 */
	public function maybe_show_seo_conflict_notice(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || ! str_contains( (string) $screen->id, 'revit-publisher' ) ) {
			return;
		}

		$message = RevIt_Publisher_SEO_Plugin_Detector::get_conflict_message();
		if ( null === $message ) {
			return;
		}

		printf(
			'<div class="notice notice-warning"><p>%s <a href="%s">%s</a></p></div>',
			esc_html( $message ),
			esc_url( admin_url( 'admin.php?page=' . self::MENU_SLUG . '-settings' ) ),
			esc_html__( 'Settings', 'revit-publisher' )
		);
	}

	/**
	 * Render dashboard mount point.
	 */
	public function render_dashboard_page(): void {
		$this->render_app_shell( 'revit-publisher-dashboard', 'edit_posts' );
	}

	/**
	 * Render import mount point.
	 */
	public function render_import_page(): void {
		$this->render_app_shell( 'revit-publisher-import', 'edit_posts' );
	}

	/**
	 * Render content graph mount point.
	 */
	public function render_graph_page(): void {
		$this->render_app_shell( 'revit-publisher-graph', 'edit_posts' );
	}

	/**
	 * Render content planner mount point.
	 */
	public function render_planner_page(): void {
		$this->render_app_shell( 'revit-publisher-planner', 'edit_posts' );
	}

	/**
	 * Render SEO health mount point.
	 */
	public function render_seo_health_page(): void {
		$this->render_app_shell( 'revit-publisher-seo-health', 'edit_posts' );
	}

	public function render_editorial_page(): void {
		$this->render_app_shell( 'revit-publisher-editorial', 'edit_posts' );
	}

	public function render_system_health_page(): void {
		$this->render_app_shell( 'revit-publisher-system-health', 'manage_options' );
	}

	public function render_search_performance_page(): void {
		$this->render_app_shell( 'revit-publisher-search-performance', 'edit_posts' );
	}

	public function render_attention_page(): void {
		$this->render_app_shell( 'revit-publisher-attention', 'edit_posts' );
	}

	public function render_audits_page(): void {
		$this->render_app_shell( 'revit-publisher-audits', 'edit_posts' );
	}

	public function render_vehicles_page(): void {
		$this->render_app_shell( 'revit-publisher-vehicles', 'edit_posts' );
	}

	public function render_redirects_page(): void {
		$this->render_app_shell( 'revit-publisher-redirects', 'manage_options' );
	}

	public function render_404_page(): void {
		$this->render_app_shell( 'revit-publisher-404', 'manage_options' );
	}

	/**
	 * Add open issue count badge to Needs Attention menu.
	 */
	public function add_attention_badge(): void {
		global $submenu;
		if ( ! is_array( $submenu ) || ! isset( $submenu[ self::MENU_SLUG ] ) ) {
			return;
		}
		$count = RevIt_Publisher_Services::issues()->count_open();
		if ( $count <= 0 ) {
			return;
		}
		foreach ( $submenu[ self::MENU_SLUG ] as &$item ) {
			if ( ( $item[2] ?? '' ) === self::MENU_SLUG . '-attention' ) {
				$item[0] .= ' <span class="awaiting-mod">' . (int) $count . '</span>';
				break;
			}
		}
		unset( $item );
	}

	/**
	 * Show latest audit summary on dashboard pages.
	 */
	public function maybe_show_audit_notice(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'toplevel_page_revit-publisher' !== $screen->id ) {
			return;
		}
		$audits = RevIt_Publisher_Services::site_audit()->list_snapshots( 1 );
		if ( empty( $audits ) ) {
			return;
		}
		$summary = (array) ( $audits[0]['summary'] ?? array() );
		$high    = (int) RevIt_Publisher_Services::issues()->count_open();
		printf(
			'<div class="notice notice-info"><p><strong>%s</strong> %s <a href="%s">%s</a></p></div>',
			esc_html__( 'Latest audit found:', 'revit-publisher' ),
			esc_html(
				sprintf(
					'%d open issues, %d unresolved links, %d content gaps.',
					$high,
					(int) ( $summary['unresolved_link_count'] ?? 0 ),
					(int) ( $summary['missing_content_count'] ?? 0 )
				)
			),
			esc_url( admin_url( 'admin.php?page=' . self::MENU_SLUG . '-attention' ) ),
			esc_html__( 'Review', 'revit-publisher' )
		);
	}

	/**
	 * Render settings mount point.
	 */
	public function render_settings_page(): void {
		$this->render_app_shell( 'revit-publisher-settings', 'manage_options' );
	}

	/**
	 * Output app mount shell.
	 */
	private function render_app_shell( string $element_id, string $capability ): void {
		if ( ! current_user_can( $capability ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'revit-publisher' ) );
		}

		echo '<div class="wrap">';
		printf( '<div id="%s" class="revit-publisher-app"></div>', esc_attr( $element_id ) );
		echo '</div>';
	}
}
