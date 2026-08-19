<?php
/**
 * Admin asset loading for React screens.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enqueues Vite-built admin assets on plugin pages only.
 */
class RevIt_Publisher_Admin_Assets {

	/**
	 * Asset handle prefix.
	 */
	private const HANDLE = 'revit-publisher-admin';

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Get instance.
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Register hooks.
	 */
	public function init(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	/**
	 * Enqueue scripts and styles on RevIt Publisher admin pages.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 */
	public function enqueue_admin_assets( string $hook_suffix ): void {
		$allowed_hooks = array(
			'toplevel_page_revit-publisher',
			'revit-publisher_page_revit-publisher-import',
			'revit-publisher_page_revit-publisher-graph',
			'revit-publisher_page_revit-publisher-settings',
		);

		if ( ! in_array( $hook_suffix, $allowed_hooks, true ) ) {
			return;
		}

		$manifest_path = REVIT_PUBLISHER_PLUGIN_DIR . 'admin/dist/.vite/manifest.json';
		$script_path   = REVIT_PUBLISHER_PLUGIN_DIR . 'admin/dist/assets/index.js';
		$style_path    = REVIT_PUBLISHER_PLUGIN_DIR . 'admin/dist/assets/index.css';

		if ( file_exists( $manifest_path ) ) {
			$this->enqueue_from_manifest( $manifest_path );
			return;
		}

		if ( ! file_exists( $script_path ) ) {
			add_action(
				'admin_notices',
				static function (): void {
					echo '<div class="notice notice-warning"><p>';
					echo esc_html__( 'RevIt Publisher admin assets are not built. Run npm install && npm run build in the plugin directory.', 'revit-publisher' );
					echo '</p></div>';
				}
			);
			return;
		}

		wp_enqueue_style(
			self::HANDLE,
			REVIT_PUBLISHER_PLUGIN_URL . 'admin/dist/assets/index.css',
			array(),
			REVIT_PUBLISHER_VERSION
		);

		wp_enqueue_script(
			self::HANDLE,
			REVIT_PUBLISHER_PLUGIN_URL . 'admin/dist/assets/index.js',
			array(),
			REVIT_PUBLISHER_VERSION,
			true
		);

		$this->localize_script( self::HANDLE );
	}

	/**
	 * Enqueue assets using Vite manifest.
	 *
	 * @param string $manifest_path Absolute manifest path.
	 */
	private function enqueue_from_manifest( string $manifest_path ): void {
		$manifest_raw = file_get_contents( $manifest_path );
		if ( false === $manifest_raw ) {
			return;
		}

		$manifest = json_decode( $manifest_raw, true );
		if ( ! is_array( $manifest ) ) {
			return;
		}

		$entry = $manifest['admin/src/main.tsx'] ?? $manifest['src/main.tsx'] ?? null;
		if ( null === $entry ) {
			return;
		}

		if ( isset( $entry['css'] ) && is_array( $entry['css'] ) ) {
			foreach ( $entry['css'] as $index => $css_file ) {
				wp_enqueue_style(
					self::HANDLE . '-css-' . $index,
					REVIT_PUBLISHER_PLUGIN_URL . 'admin/dist/' . ltrim( $css_file, '/' ),
					array(),
					REVIT_PUBLISHER_VERSION
				);
			}
		}

		wp_enqueue_script(
			self::HANDLE,
			REVIT_PUBLISHER_PLUGIN_URL . 'admin/dist/' . ltrim( $entry['file'], '/' ),
			array(),
			REVIT_PUBLISHER_VERSION,
			true
		);

		$this->localize_script( self::HANDLE );
	}

	/**
	 * Pass REST config to the admin app.
	 *
	 * @param string $handle Script handle.
	 */
	private function localize_script( string $handle ): void {
		wp_localize_script(
			$handle,
			'revitPublisherAdmin',
			array(
				'version'       => REVIT_PUBLISHER_VERSION,
				'schemaVersion' => RevIt_Publisher_Article_Package_Validator::SCHEMA_VERSION,
				'restUrl'       => esc_url_raw( rest_url( RevIt_Publisher_Article_Package_Rest_Controller::REST_NAMESPACE ) ),
				'nonce'         => wp_create_nonce( 'wp_rest' ),
				'pages'         => array(
					'dashboard' => RevIt_Publisher_Admin::MENU_SLUG,
					'import'    => RevIt_Publisher_Admin::MENU_SLUG . '-import',
					'graph'     => RevIt_Publisher_Admin::MENU_SLUG . '-graph',
					'settings'  => RevIt_Publisher_Admin::MENU_SLUG . '-settings',
				),
			)
		);
	}
}
