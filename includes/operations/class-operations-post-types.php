<?php
/**
 * Operations custom post types.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers private CPTs for audits, issues, redirects, link logs, and 404 entries.
 */
class RevIt_Publisher_Operations_Post_Types {

	public const AUDIT_SNAPSHOT = 'revit_audit_snapshot';
	public const ISSUE          = 'revit_issue';
	public const REDIRECT       = 'revit_redirect';
	public const LINK_CHANGE    = 'revit_link_change';
	public const NOT_FOUND      = 'revit_404_entry';
	public const EDITORIAL      = 'revit_editorial_item';

	public static function init(): void {
		add_action( 'init', array( self::class, 'register' ) );
	}

	public static function register(): void {
		$private = array(
			'public'           => false,
			'show_ui'          => true,
			'show_in_menu'     => false,
			'show_in_rest'     => false,
			'capability_type'  => 'post',
			'map_meta_cap'     => true,
			'supports'         => array( 'title' ),
			'rewrite'          => false,
			'delete_with_user' => false,
		);

		register_post_type(
			self::AUDIT_SNAPSHOT,
			array_merge(
				$private,
				array(
					'labels' => array(
						'name'          => __( 'Audit Snapshots', 'revit-publisher' ),
						'singular_name' => __( 'Audit Snapshot', 'revit-publisher' ),
					),
				)
			)
		);

		register_post_type(
			self::ISSUE,
			array_merge(
				$private,
				array(
					'labels' => array(
						'name'          => __( 'Issues', 'revit-publisher' ),
						'singular_name' => __( 'Issue', 'revit-publisher' ),
					),
				)
			)
		);

		register_post_type(
			self::REDIRECT,
			array_merge(
				$private,
				array(
					'labels' => array(
						'name'          => __( 'Redirects', 'revit-publisher' ),
						'singular_name' => __( 'Redirect', 'revit-publisher' ),
					),
				)
			)
		);

		register_post_type(
			self::LINK_CHANGE,
			array_merge(
				$private,
				array(
					'labels' => array(
						'name'          => __( 'Link Changes', 'revit-publisher' ),
						'singular_name' => __( 'Link Change', 'revit-publisher' ),
					),
				)
			)
		);

		register_post_type(
			self::NOT_FOUND,
			array_merge(
				$private,
				array(
					'labels' => array(
						'name'          => __( '404 Entries', 'revit-publisher' ),
						'singular_name' => __( '404 Entry', 'revit-publisher' ),
					),
				)
			)
		);

		register_post_type(
			self::EDITORIAL,
			array_merge(
				$private,
				array(
					'labels' => array(
						'name'          => __( 'Editorial Queue', 'revit-publisher' ),
						'singular_name' => __( 'Editorial Item', 'revit-publisher' ),
					),
				)
			)
		);
	}
}
