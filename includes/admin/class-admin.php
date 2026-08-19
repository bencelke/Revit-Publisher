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
	}

	/**
	 * Render dashboard mount point.
	 */
	public function render_dashboard_page(): void {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'revit-publisher' ) );
		}

		echo '<div class="wrap">';
		echo '<div id="revit-publisher-dashboard" class="revit-publisher-app"></div>';
		echo '</div>';
	}

	/**
	 * Render import mount point.
	 */
	public function render_import_page(): void {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'revit-publisher' ) );
		}

		echo '<div class="wrap">';
		echo '<div id="revit-publisher-import" class="revit-publisher-app"></div>';
		echo '</div>';
	}
}
