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
			__( 'Content Graph', 'revit-publisher' ),
			__( 'Content Graph', 'revit-publisher' ),
			'edit_posts',
			self::MENU_SLUG . '-graph',
			array( $this, 'render_graph_page' )
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
