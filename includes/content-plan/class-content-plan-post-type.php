<?php
/**
 * Content plan custom post type registration.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers private revit_content_plan post type.
 */
class RevIt_Publisher_Content_Plan_Post_Type {

	public const POST_TYPE = 'revit_content_plan';

	/**
	 * Register hooks.
	 */
	public static function init(): void {
		add_action( 'init', array( self::class, 'register' ) );
	}

	/**
	 * Register post type.
	 */
	public static function register(): void {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'          => __( 'Content Plans', 'revit-publisher' ),
					'singular_name' => __( 'Content Plan', 'revit-publisher' ),
				),
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => false,
				'show_in_rest'        => false,
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
				'supports'            => array( 'title', 'revisions' ),
				'rewrite'             => false,
				'delete_with_user'    => false,
			)
		);
	}
}
