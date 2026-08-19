<?php
/**
 * Vehicle hub page template.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$hub_id   = get_queried_object_id();
$hubs     = RevIt_Publisher_Services::vehicle_hubs();
$sections = $hubs->get_articles_by_section( $hub_id );
$clusters = $hubs->get_clusters_for_hub( $hub_id );
$coverage = $hubs->get_coverage( $hub_id );
$intro    = (string) get_post_meta( $hub_id, RevIt_Publisher_Vehicle_Hub_Meta_Keys::INTRO, true );
$engines  = $hubs->get_engine_related_articles( $hub_id );

$section_labels = array(
	'common_problems'  => __( 'Common Problems', 'revit-publisher' ),
	'maintenance'      => __( 'Maintenance', 'revit-publisher' ),
	'modifications'    => __( 'Modifications', 'revit-publisher' ),
	'reliability'      => __( 'Reliability', 'revit-publisher' ),
	'buying'           => __( 'Buying Guides', 'revit-publisher' ),
	'recently_updated' => __( 'Recently Updated', 'revit-publisher' ),
);
?>
<main class="revit-publisher-hub revit-publisher-hub--vehicle">
	<header class="revit-publisher-hub__header">
		<h1 class="revit-publisher-hub__title"><?php echo esc_html( get_the_title( $hub_id ) ); ?></h1>
		<?php if ( '' !== trim( $intro ) ) : ?>
			<div class="revit-publisher-hub__intro"><?php echo wp_kses_post( wpautop( $intro ) ); ?></div>
		<?php endif; ?>
		<p class="revit-publisher-hub__meta">
			<?php
			printf(
				/* translators: 1: published article count, 2: cluster count */
				esc_html__( '%1$d articles · %2$d clusters', 'revit-publisher' ),
				(int) ( $coverage['published_articles'] ?? 0 ),
				(int) ( $coverage['clusters'] ?? 0 )
			);
			?>
		</p>
	</header>

	<?php foreach ( $section_labels as $section_key => $label ) : ?>
		<?php
		$articles = $sections[ $section_key ] ?? array();
		if ( empty( $articles ) ) {
			continue;
		}
		?>
		<section class="revit-publisher-hub__section revit-publisher-hub__section--<?php echo esc_attr( $section_key ); ?>">
			<h2 class="revit-publisher-hub__section-title"><?php echo esc_html( $label ); ?></h2>
			<ul class="revit-publisher-hub__article-list">
				<?php foreach ( $articles as $article ) : ?>
					<li class="revit-publisher-hub__article-item">
						<a href="<?php echo esc_url( (string) ( $article['permalink'] ?? '' ) ); ?>">
							<?php echo esc_html( (string) ( $article['title'] ?? '' ) ); ?>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		</section>
	<?php endforeach; ?>

	<?php if ( ! empty( $clusters ) ) : ?>
		<section class="revit-publisher-hub__section revit-publisher-hub__section--clusters">
			<h2 class="revit-publisher-hub__section-title"><?php esc_html_e( 'Topic Clusters', 'revit-publisher' ); ?></h2>
			<ul class="revit-publisher-hub__cluster-list">
				<?php foreach ( $clusters as $cluster ) : ?>
					<?php if ( empty( $cluster['is_public'] ) || empty( $cluster['canonical_url'] ) ) : ?>
						<?php continue; ?>
					<?php endif; ?>
					<li class="revit-publisher-hub__cluster-item">
						<a href="<?php echo esc_url( (string) $cluster['canonical_url'] ); ?>">
							<?php echo esc_html( (string) ( $cluster['name'] ?? '' ) ); ?>
						</a>
						<span class="revit-publisher-hub__cluster-count">
							<?php
							printf(
								/* translators: %d: article count */
								esc_html__( '%d articles', 'revit-publisher' ),
								(int) ( $cluster['article_count'] ?? 0 )
							);
							?>
						</span>
					</li>
				<?php endforeach; ?>
			</ul>
		</section>
	<?php endif; ?>

	<?php if ( ! empty( $engines ) ) : ?>
		<section class="revit-publisher-hub__section revit-publisher-hub__section--engines">
			<h2 class="revit-publisher-hub__section-title"><?php esc_html_e( 'Related by Engine', 'revit-publisher' ); ?></h2>
			<ul class="revit-publisher-hub__article-list">
				<?php foreach ( $engines as $article ) : ?>
					<li class="revit-publisher-hub__article-item">
						<a href="<?php echo esc_url( (string) ( $article['permalink'] ?? '' ) ); ?>">
							<?php echo esc_html( (string) ( $article['title'] ?? '' ) ); ?>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		</section>
	<?php endif; ?>
</main>
<style>
.revit-publisher-hub { max-width: 960px; margin: 0 auto; padding: 2rem 1rem; }
.revit-publisher-hub__title { font-size: 2rem; margin-bottom: 0.5rem; }
.revit-publisher-hub__intro { color: #444; margin-bottom: 1rem; }
.revit-publisher-hub__meta { color: #666; font-size: 0.95rem; }
.revit-publisher-hub__section { margin-top: 2rem; }
.revit-publisher-hub__section-title { font-size: 1.35rem; border-bottom: 1px solid #e5e5e5; padding-bottom: 0.35rem; }
.revit-publisher-hub__article-list, .revit-publisher-hub__cluster-list { list-style: none; padding: 0; margin: 1rem 0 0; }
.revit-publisher-hub__article-item, .revit-publisher-hub__cluster-item { padding: 0.35rem 0; }
.revit-publisher-hub__cluster-count { color: #888; font-size: 0.85rem; margin-left: 0.5rem; }
</style>
<?php
get_footer();
