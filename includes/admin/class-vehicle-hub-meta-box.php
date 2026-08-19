<?php
/**
 * Vehicle hub editor meta panel.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RevIt_Publisher_Vehicle_Hub_Meta_Box {

	public static function init(): void {
		add_action( 'add_meta_boxes', array( self::class, 'register' ) );
		add_action( 'save_post_' . RevIt_Publisher_Vehicle_Hub_Post_Type::POST_TYPE, array( self::class, 'save' ), 10, 2 );
	}

	public static function register(): void {
		add_meta_box(
			'revit_vehicle_hub_panel',
			__( 'RevIt Vehicle Hub', 'revit-publisher' ),
			array( self::class, 'render' ),
			RevIt_Publisher_Vehicle_Hub_Post_Type::POST_TYPE,
			'normal',
			'high'
		);
	}

	public static function render( WP_Post $post ): void {
		wp_nonce_field( 'revit_vehicle_hub_save', 'revit_vehicle_hub_nonce' );
		$hub_id   = (int) $post->ID;
		$hubs     = RevIt_Publisher_Services::vehicle_hubs();
		$identity = $hubs->get_identity( $hub_id );
		$coverage = $hubs->get_coverage( $hub_id );
		$health   = RevIt_Publisher_Services::hub_seo_health()->evaluate( $hub_id );
		$intro    = (string) get_post_meta( $hub_id, RevIt_Publisher_Vehicle_Hub_Meta_Keys::INTRO, true );
		$label    = RevIt_Publisher_Vehicle_Identity::label(
			(string) ( $identity['manufacturer'] ?? '' ),
			(string) ( $identity['model'] ?? '' ),
			(string) ( $identity['generation'] ?? '' ),
			(string) ( $identity['trim'] ?? '' )
		);
		$permalink = get_permalink( $hub_id );
		$score     = 100 - ( count( (array) ( $health['warnings'] ?? array() ) ) * 4 );
		$score     = max( 0, min( 100, $score ) );
		?>
		<div class="revit-vehicle-hub-panel">
			<p><strong><?php esc_html_e( 'Vehicle:', 'revit-publisher' ); ?></strong> <?php echo esc_html( $label ); ?></p>
			<p><strong><?php esc_html_e( 'Published Articles:', 'revit-publisher' ); ?></strong> <?php echo esc_html( (string) ( $coverage['published_articles'] ?? 0 ) ); ?></p>
			<p><strong><?php esc_html_e( 'Clusters:', 'revit-publisher' ); ?></strong> <?php echo esc_html( (string) ( $coverage['clusters'] ?? 0 ) ); ?></p>
			<p><strong><?php esc_html_e( 'Content Plan:', 'revit-publisher' ); ?></strong> <?php echo esc_html( (string) ( $coverage['plan_coverage'] ?? 0 ) ); ?>%</p>
			<p><strong><?php esc_html_e( 'SEO Health:', 'revit-publisher' ); ?></strong> <?php echo esc_html( (string) $score ); ?> / 100</p>
			<?php if ( is_string( $permalink ) && 'publish' === $post->post_status ) : ?>
				<p><a class="button button-secondary" href="<?php echo esc_url( $permalink ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View Public Hub', 'revit-publisher' ); ?></a></p>
			<?php endif; ?>
			<p>
				<label for="revit_hub_intro"><strong><?php esc_html_e( 'Hub introduction', 'revit-publisher' ); ?></strong></label><br />
				<textarea id="revit_hub_intro" name="revit_hub_intro" rows="4" class="large-text"><?php echo esc_textarea( $intro ); ?></textarea>
			</p>
			<p class="description"><?php esc_html_e( 'Article sections on the public hub are generated automatically from RevIt relationships.', 'revit-publisher' ); ?></p>
		</div>
		<?php
	}

	public static function save( int $post_id, WP_Post $post ): void {
		unset( $post );
		if ( ! isset( $_POST['revit_vehicle_hub_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_POST['revit_vehicle_hub_nonce'] ) ), 'revit_vehicle_hub_save' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( isset( $_POST['revit_hub_intro'] ) ) {
			update_post_meta(
				$post_id,
				RevIt_Publisher_Vehicle_Hub_Meta_Keys::INTRO,
				wp_kses_post( wp_unslash( (string) $_POST['revit_hub_intro'] ) )
			);
		}
		RevIt_Publisher_Services::hub_cache()->invalidate_hub( $post_id );
	}
}
