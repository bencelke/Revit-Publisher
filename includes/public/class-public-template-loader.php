<?php
/**
 * Public template loader for vehicle hub pages.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Loads RevIt Publisher templates with theme override support.
 */
class RevIt_Publisher_Public_Template_Loader {

	public function init(): void {
		add_filter( 'template_include', array( $this, 'filter_template' ), 99 );
	}

	public function filter_template( string $template ): string {
		$revit_template = null;

		if ( is_singular( RevIt_Publisher_Vehicle_Hub_Post_Type::POST_TYPE ) ) {
			$revit_template = 'vehicle-hub.php';
		} elseif ( is_post_type_archive( RevIt_Publisher_Vehicle_Hub_Post_Type::POST_TYPE ) ) {
			$revit_template = 'vehicles-index.php';
		} elseif ( '' !== (string) get_query_var( 'revit_manufacturer_hub' ) ) {
			$revit_template = 'manufacturer-hub.php';
		}

		if ( null === $revit_template ) {
			return $template;
		}

		$located = $this->locate_template( $revit_template );
		return false !== $located ? $located : $template;
	}

	/**
	 * Theme override first, then plugin templates directory.
	 */
	public function locate_template( string $template_name ): string|false {
		$theme_path = trailingslashit( get_stylesheet_directory() ) . 'revit-publisher/' . $template_name;
		if ( file_exists( $theme_path ) ) {
			return $theme_path;
		}

		$plugin_path = REVIT_PUBLISHER_PLUGIN_DIR . 'templates/' . $template_name;
		if ( file_exists( $plugin_path ) ) {
			return $plugin_path;
		}

		return false;
	}

	/**
	 * Whether the current request is a RevIt public vehicle page.
	 */
	public static function is_revit_public_page(): bool {
		if ( is_singular( RevIt_Publisher_Vehicle_Hub_Post_Type::POST_TYPE ) ) {
			return true;
		}
		if ( is_post_type_archive( RevIt_Publisher_Vehicle_Hub_Post_Type::POST_TYPE ) ) {
			return true;
		}
		if ( '' !== (string) get_query_var( 'revit_manufacturer_hub' ) ) {
			return true;
		}
		if ( is_singular( 'post' ) ) {
			$post_id = get_queried_object_id();
			return RevIt_Publisher_Services::resolver()->is_managed( $post_id );
		}
		return false;
	}
}
