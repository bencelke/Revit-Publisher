<?php
/**
 * Public vehicle hub custom post type.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RevIt_Publisher_Vehicle_Hub_Post_Type {

	public const POST_TYPE = 'revit_vehicle';

	public static function init(): void {
		add_action( 'init', array( self::class, 'register' ) );
		add_action( 'init', array( self::class, 'register_rewrites' ), 20 );
	}

	public static function register(): void {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'          => __( 'Vehicle Hubs', 'revit-publisher' ),
					'singular_name' => __( 'Vehicle Hub', 'revit-publisher' ),
				),
				'public'              => true,
				'publicly_queryable'  => true,
				'show_ui'             => true,
				'show_in_menu'        => false,
				'show_in_rest'        => true,
				'has_archive'         => 'vehicles',
				'rewrite'             => array(
					'slug'       => 'vehicles',
					'with_front' => false,
				),
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
				'supports'            => array( 'title', 'editor', 'excerpt', 'thumbnail', 'revisions' ),
				'template_lock'       => false,
			)
		);
	}

	public static function register_rewrites(): void {
		add_rewrite_rule(
			'^vehicles/manufacturer/([^/]+)/?$',
			'index.php?revit_manufacturer_hub=$matches[1]',
			'top'
		);
		add_rewrite_tag( '%revit_manufacturer_hub%', '([^&]+)' );
	}
}
