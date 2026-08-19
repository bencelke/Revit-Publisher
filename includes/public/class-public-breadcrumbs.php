<?php
/**
 * Public breadcrumb navigation and JSON-LD data.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds navigable breadcrumb trails for RevIt public pages.
 */
class RevIt_Publisher_Public_Breadcrumbs {

	public const MANUFACTURER_PAGE_THRESHOLD = 2;

	public function init(): void {
		add_action( 'wp_body_open', array( $this, 'render_nav' ), 5 );
	}

	public function render_nav(): void {
		if ( ! RevIt_Publisher_Services::settings()->public_seo_output_enabled() ) {
			return;
		}
		if ( ! RevIt_Publisher_Public_Template_Loader::is_revit_public_page() ) {
			return;
		}

		$trail = $this->get_trail();
		if ( count( $trail ) < 2 ) {
			return;
		}

		echo '<nav class="revit-publisher-breadcrumbs" aria-label="' . esc_attr__( 'Breadcrumb', 'revit-publisher' ) . '">';
		echo '<ol class="revit-publisher-breadcrumbs__list">';
		$last = count( $trail ) - 1;
		foreach ( $trail as $index => $crumb ) {
			$name = (string) ( $crumb['name'] ?? '' );
			$url  = isset( $crumb['url'] ) ? (string) $crumb['url'] : '';
			echo '<li class="revit-publisher-breadcrumbs__item">';
			if ( $index < $last && '' !== $url ) {
				printf(
					'<a class="revit-publisher-breadcrumbs__link" href="%s">%s</a>',
					esc_url( $url ),
					esc_html( $name )
				);
			} else {
				echo '<span class="revit-publisher-breadcrumbs__current" aria-current="page">' . esc_html( $name ) . '</span>';
			}
			echo '</li>';
		}
		echo '</ol></nav>';
		static $styles_printed = false;
		if ( ! $styles_printed ) {
			echo '<style>.revit-publisher-breadcrumbs{margin:1rem auto;max-width:960px;padding:0 1rem;font-size:.9rem}.revit-publisher-breadcrumbs__list{display:flex;flex-wrap:wrap;gap:.35rem;list-style:none;margin:0;padding:0}.revit-publisher-breadcrumbs__item:not(:last-child)::after{content:"/";margin-left:.35rem;color:#999}.revit-publisher-breadcrumbs__link{text-decoration:none}.revit-publisher-breadcrumbs__current{color:#555}</style>';
			$styles_printed = true;
		}
	}

	/**
	 * @return array<int, array{name: string, url?: string}>
	 */
	public function get_trail(): array {
		if ( is_singular( RevIt_Publisher_Vehicle_Hub_Post_Type::POST_TYPE ) ) {
			return $this->trail_for_hub( get_queried_object_id() );
		}
		if ( is_post_type_archive( RevIt_Publisher_Vehicle_Hub_Post_Type::POST_TYPE ) ) {
			return $this->trail_for_vehicles_index();
		}
		$manufacturer_slug = (string) get_query_var( 'revit_manufacturer_hub' );
		if ( '' !== $manufacturer_slug ) {
			return $this->trail_for_manufacturer( $manufacturer_slug );
		}
		if ( is_singular( 'post' ) ) {
			return $this->trail_for_article( get_queried_object_id() );
		}
		return array();
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function get_json_ld_items(): array {
		$items    = array();
		$position = 1;
		foreach ( $this->get_trail() as $crumb ) {
			$url = isset( $crumb['url'] ) ? (string) $crumb['url'] : '';
			if ( '' === $url ) {
				continue;
			}
			$items[] = array(
				'@type'    => 'ListItem',
				'position' => $position++,
				'name'     => (string) ( $crumb['name'] ?? '' ),
				'item'     => $url,
			);
		}
		return $items;
	}

	/**
	 * @return array<int, array{name: string, url?: string}>
	 */
	private function trail_for_vehicles_index(): array {
		return array(
			$this->home_crumb(),
			array(
				'name' => __( 'Vehicles', 'revit-publisher' ),
				'url'  => $this->vehicles_archive_url(),
			),
		);
	}

	/**
	 * @return array<int, array{name: string, url?: string}>
	 */
	private function trail_for_manufacturer( string $manufacturer_slug ): array {
		$manufacturer = $this->resolve_manufacturer_name( $manufacturer_slug );
		$trail        = array(
			$this->home_crumb(),
			array(
				'name' => __( 'Vehicles', 'revit-publisher' ),
				'url'  => $this->vehicles_archive_url(),
			),
		);
		if ( $this->manufacturer_meets_threshold( $manufacturer_slug ) ) {
			$trail[] = array(
				'name' => $manufacturer,
				'url'  => $this->manufacturer_url( $manufacturer_slug ),
			);
		}
		return $trail;
	}

	/**
	 * @return array<int, array{name: string, url?: string}>
	 */
	public function get_trail_for_hub( int $hub_id ): array {
		return $this->trail_for_hub( $hub_id );
	}

	/**
	 * @return array<int, array{name: string, url?: string}>
	 */
	private function trail_for_hub( int $hub_id ): array {
		$hubs         = RevIt_Publisher_Services::vehicle_hubs();
		$identity     = $hubs->get_identity( $hub_id );
		$manufacturer = (string) ( $identity['manufacturer'] ?? '' );
		$slug         = RevIt_Publisher_Vehicle_Identity::slug( $manufacturer );
		$trail        = array(
			$this->home_crumb(),
			array(
				'name' => __( 'Vehicles', 'revit-publisher' ),
				'url'  => $this->vehicles_archive_url(),
			),
		);
		if ( '' !== $manufacturer && $this->manufacturer_meets_threshold( $slug ) ) {
			$trail[] = array(
				'name' => $manufacturer,
				'url'  => $this->manufacturer_url( $slug ),
			);
		}
		$permalink = get_permalink( $hub_id );
		$trail[]   = array(
			'name' => get_the_title( $hub_id ),
			'url'  => is_string( $permalink ) ? $permalink : '',
		);
		return $trail;
	}

	/**
	 * @return array<int, array{name: string, url?: string}>
	 */
	private function trail_for_article( int $post_id ): array {
		if ( ! RevIt_Publisher_Services::resolver()->is_managed( $post_id ) ) {
			return array();
		}

		$vehicle_key = RevIt_Publisher_Vehicle_Identity::from_post( $post_id );
		$hub_id      = '' !== $vehicle_key ? RevIt_Publisher_Services::vehicle_hubs()->find_by_key( $vehicle_key ) : null;
		$trail       = array(
			$this->home_crumb(),
			array(
				'name' => __( 'Vehicles', 'revit-publisher' ),
				'url'  => $this->vehicles_archive_url(),
			),
		);

		if ( null !== $hub_id ) {
			$identity     = RevIt_Publisher_Services::vehicle_hubs()->get_identity( $hub_id );
			$manufacturer = (string) ( $identity['manufacturer'] ?? '' );
			$slug         = RevIt_Publisher_Vehicle_Identity::slug( $manufacturer );
			if ( '' !== $manufacturer && $this->manufacturer_meets_threshold( $slug ) ) {
				$trail[] = array(
					'name' => $manufacturer,
					'url'  => $this->manufacturer_url( $slug ),
				);
			}
			$hub_permalink = get_permalink( $hub_id );
			if ( is_string( $hub_permalink ) && 'publish' === get_post_status( $hub_id ) ) {
				$trail[] = array(
					'name' => get_the_title( $hub_id ),
					'url'  => $hub_permalink,
				);
			}
		}

		$cluster_url = $this->cluster_url_for_post( $post_id );
		if ( null !== $cluster_url ) {
			$trail[] = $cluster_url;
		}

		$permalink = get_permalink( $post_id );
		$trail[]   = array(
			'name' => get_the_title( $post_id ),
			'url'  => is_string( $permalink ) ? $permalink : '',
		);

		return $trail;
	}

	/**
	 * @return array{name: string, url: string}|null
	 */
	private function cluster_url_for_post( int $post_id ): ?array {
		$terms = wp_get_post_terms( $post_id, RevIt_Publisher_Taxonomies::CLUSTER );
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return null;
		}
		$term = $terms[0];
		if ( ! $term instanceof WP_Term ) {
			return null;
		}

		$vehicle_key = RevIt_Publisher_Vehicle_Identity::from_post( $post_id );
		$hub_id      = '' !== $vehicle_key ? RevIt_Publisher_Services::vehicle_hubs()->find_by_key( $vehicle_key ) : null;
		if ( null === $hub_id ) {
			return null;
		}

		foreach ( RevIt_Publisher_Services::vehicle_hubs()->get_clusters_for_hub( $hub_id ) as $cluster ) {
			if ( (int) ( $cluster['term_id'] ?? 0 ) !== (int) $term->term_id ) {
				continue;
			}
			if ( empty( $cluster['is_public'] ) || empty( $cluster['canonical_url'] ) ) {
				return null;
			}
			return array(
				'name' => (string) ( $cluster['name'] ?? $term->name ),
				'url'  => (string) $cluster['canonical_url'],
			);
		}
		return null;
	}

	/**
	 * @return array{name: string, url: string}
	 */
	private function home_crumb(): array {
		return array(
			'name' => __( 'Home', 'revit-publisher' ),
			'url'  => home_url( '/' ),
		);
	}

	public function vehicles_archive_url(): string {
		$link = get_post_type_archive_link( RevIt_Publisher_Vehicle_Hub_Post_Type::POST_TYPE );
		return is_string( $link ) ? $link : home_url( '/vehicles/' );
	}

	public function manufacturer_url( string $manufacturer_slug ): string {
		return home_url( '/vehicles/manufacturer/' . rawurlencode( sanitize_title( $manufacturer_slug ) ) . '/' );
	}

	public function manufacturer_meets_threshold( string $manufacturer_slug ): bool {
		return $this->count_published_hubs_for_manufacturer( $manufacturer_slug ) >= self::MANUFACTURER_PAGE_THRESHOLD;
	}

	public function count_published_hubs_for_manufacturer( string $manufacturer_slug ): int {
		$slug  = sanitize_title( $manufacturer_slug );
		$count = 0;
		foreach ( RevIt_Publisher_Services::vehicle_hubs()->list_published_hubs() as $hub ) {
			$hub_slug = RevIt_Publisher_Vehicle_Identity::slug( (string) ( $hub['manufacturer'] ?? '' ) );
			if ( $hub_slug === $slug ) {
				++$count;
			}
		}
		return $count;
	}

	private function resolve_manufacturer_name( string $manufacturer_slug ): string {
		$slug = sanitize_title( $manufacturer_slug );
		foreach ( RevIt_Publisher_Services::vehicle_hubs()->list_published_hubs() as $hub ) {
			if ( RevIt_Publisher_Vehicle_Identity::slug( (string) ( $hub['manufacturer'] ?? '' ) ) === $slug ) {
				return (string) ( $hub['manufacturer'] ?? ucwords( str_replace( '-', ' ', $slug ) ) );
			}
		}
		return ucwords( str_replace( '-', ' ', $slug ) );
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function list_manufacturers_with_hubs(): array {
		$groups = array();
		foreach ( RevIt_Publisher_Services::vehicle_hubs()->list_published_hubs() as $hub ) {
			$manufacturer = (string) ( $hub['manufacturer'] ?? '' );
			if ( '' === $manufacturer ) {
				continue;
			}
			$slug = RevIt_Publisher_Vehicle_Identity::slug( $manufacturer );
			if ( ! isset( $groups[ $slug ] ) ) {
				$groups[ $slug ] = array(
					'slug'  => $slug,
					'name'  => $manufacturer,
					'hubs'  => array(),
					'count' => 0,
				);
			}
			$groups[ $slug ]['hubs'][] = $hub;
			++$groups[ $slug ]['count'];
		}

		$out = array();
		foreach ( $groups as $group ) {
			if ( (int) ( $group['count'] ?? 0 ) < self::MANUFACTURER_PAGE_THRESHOLD ) {
				continue;
			}
			$group['url'] = $this->manufacturer_url( (string) $group['slug'] );
			$out[]        = $group;
		}
		usort( $out, static fn( $a, $b ) => strcmp( (string) ( $a['name'] ?? '' ), (string) ( $b['name'] ?? '' ) ) );
		return $out;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function get_hubs_for_manufacturer( string $manufacturer_slug ): array {
		$slug = sanitize_title( $manufacturer_slug );
		$hubs = array();
		foreach ( RevIt_Publisher_Services::vehicle_hubs()->list_published_hubs() as $hub ) {
			if ( RevIt_Publisher_Vehicle_Identity::slug( (string) ( $hub['manufacturer'] ?? '' ) ) === $slug ) {
				$hubs[] = $hub;
			}
		}
		usort( $hubs, static fn( $a, $b ) => strcmp( (string) ( $a['title'] ?? '' ), (string) ( $b['title'] ?? '' ) ) );
		return $hubs;
	}
}
