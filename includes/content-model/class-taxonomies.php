<?php
/**
 * RevIt Publisher taxonomy registration.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers automotive and organizational taxonomies.
 */
class RevIt_Publisher_Taxonomies {

	public const MANUFACTURER  = 'revit_manufacturer';
	public const MODEL         = 'revit_model';
	public const GENERATION    = 'revit_generation';
	public const TRIM          = 'revit_trim';
	public const ENGINE        = 'revit_engine';
	public const ARTICLE_TYPE  = 'revit_article_type';
	public const CLUSTER       = 'revit_cluster';

	public const TERM_IDENTITY_SLUG = '_revit_identity_slug';
	public const TERM_CLUSTER_KEY   = '_revit_cluster_key';
	public const TERM_PILLAR_KEY    = '_revit_pillar_article_key';
	public const TERM_PARENT_CLUSTER_KEY = '_revit_parent_cluster_key';

	/**
	 * Article type slugs matching schema enum.
	 *
	 * @var string[]
	 */
	public const ARTICLE_TYPES = array(
		'vehicle_hub',
		'pillar',
		'problem',
		'maintenance',
		'modification',
		'product',
		'fitment',
		'buying',
		'reliability',
		'comparison',
		'guide',
		'faq',
		'other',
	);

	/**
	 * Register hooks.
	 */
	public static function init(): void {
		add_action( 'init', array( self::class, 'register' ) );
	}

	/**
	 * Register all RevIt taxonomies.
	 */
	public static function register(): void {
		$common = array(
			'object_type' => array( 'post' ),
			'public'      => false,
			'show_ui'     => true,
			'show_in_rest'=> true,
			'hierarchical'=> false,
		);

		register_taxonomy(
			self::MANUFACTURER,
			'post',
			array_merge(
				$common,
				array(
					'labels' => self::labels( __( 'Manufacturers', 'revit-publisher' ), __( 'Manufacturer', 'revit-publisher' ) ),
				)
			)
		);

		register_taxonomy(
			self::MODEL,
			'post',
			array_merge(
				$common,
				array(
					'hierarchical' => true,
					'labels'       => self::labels( __( 'Models', 'revit-publisher' ), __( 'Model', 'revit-publisher' ) ),
				)
			)
		);

		register_taxonomy(
			self::GENERATION,
			'post',
			array_merge(
				$common,
				array(
					'hierarchical' => true,
					'labels'       => self::labels( __( 'Generations', 'revit-publisher' ), __( 'Generation', 'revit-publisher' ) ),
				)
			)
		);

		register_taxonomy(
			self::TRIM,
			'post',
			array_merge(
				$common,
				array(
					'hierarchical' => true,
					'labels'       => self::labels( __( 'Trims', 'revit-publisher' ), __( 'Trim', 'revit-publisher' ) ),
				)
			)
		);

		register_taxonomy(
			self::ENGINE,
			'post',
			array_merge(
				$common,
				array(
					'labels' => self::labels( __( 'Engines', 'revit-publisher' ), __( 'Engine', 'revit-publisher' ) ),
				)
			)
		);

		register_taxonomy(
			self::ARTICLE_TYPE,
			'post',
			array_merge(
				$common,
				array(
					'labels' => self::labels( __( 'RevIt Article Types', 'revit-publisher' ), __( 'RevIt Article Type', 'revit-publisher' ) ),
				)
			)
		);

		register_taxonomy(
			self::CLUSTER,
			'post',
			array_merge(
				$common,
				array(
					'hierarchical' => true,
					'labels'       => self::labels( __( 'Clusters', 'revit-publisher' ), __( 'Cluster', 'revit-publisher' ) ),
				)
			)
		);

		self::ensure_article_type_terms();
	}

	/**
	 * Ensure article type taxonomy terms exist.
	 */
	public static function ensure_article_type_terms(): void {
		foreach ( self::ARTICLE_TYPES as $type ) {
			if ( ! term_exists( $type, self::ARTICLE_TYPE ) ) {
				wp_insert_term(
					ucwords( str_replace( '_', ' ', $type ) ),
					self::ARTICLE_TYPE,
					array( 'slug' => $type )
				);
			}
		}
	}

	/**
	 * Build taxonomy labels.
	 *
	 * @param string $plural Plural label.
	 * @param string $singular Singular label.
	 * @return array<string, string>
	 */
	private static function labels( string $plural, string $singular ): array {
		return array(
			'name'          => $plural,
			'singular_name' => $singular,
			'search_items'  => sprintf(
				/* translators: %s: taxonomy plural label */
				__( 'Search %s', 'revit-publisher' ),
				$plural
			),
			'all_items'     => sprintf(
				/* translators: %s: taxonomy plural label */
				__( 'All %s', 'revit-publisher' ),
				$plural
			),
			'edit_item'     => sprintf(
				/* translators: %s: taxonomy singular label */
				__( 'Edit %s', 'revit-publisher' ),
				$singular
			),
			'update_item'   => sprintf(
				/* translators: %s: taxonomy singular label */
				__( 'Update %s', 'revit-publisher' ),
				$singular
			),
			'add_new_item'  => sprintf(
				/* translators: %s: taxonomy singular label */
				__( 'Add New %s', 'revit-publisher' ),
				$singular
			),
			'new_item_name' => sprintf(
				/* translators: %s: taxonomy singular label */
				__( 'New %s Name', 'revit-publisher' ),
				$singular
			),
			'menu_name'     => $plural,
		);
	}
}
