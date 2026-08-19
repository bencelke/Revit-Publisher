<?php
/**
 * Vehicles index archive template.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$breadcrumbs  = new RevIt_Publisher_Public_Breadcrumbs();
$manufacturers = $breadcrumbs->list_manufacturers_with_hubs();
$hubs         = RevIt_Publisher_Services::vehicle_hubs()->list_published_hubs();
?>
<main class="revit-publisher-index revit-publisher-index--vehicles">
	<header class="revit-publisher-index__header">
		<h1 class="revit-publisher-index__title"><?php esc_html_e( 'Vehicles', 'revit-publisher' ); ?></h1>
		<p class="revit-publisher-index__description">
			<?php
			printf(
				/* translators: %d: hub count */
				esc_html__( 'Browse %d vehicle hubs with guides, problems, and maintenance coverage.', 'revit-publisher' ),
				count( $hubs )
			);
			?>
		</p>
	</header>

	<?php if ( ! empty( $manufacturers ) ) : ?>
		<section class="revit-publisher-index__section revit-publisher-index__section--manufacturers">
			<h2 class="revit-publisher-index__section-title"><?php esc_html_e( 'Manufacturers', 'revit-publisher' ); ?></h2>
			<ul class="revit-publisher-index__manufacturer-list">
				<?php foreach ( $manufacturers as $manufacturer ) : ?>
					<li class="revit-publisher-index__manufacturer-item">
						<a href="<?php echo esc_url( (string) ( $manufacturer['url'] ?? '' ) ); ?>">
							<?php echo esc_html( (string) ( $manufacturer['name'] ?? '' ) ); ?>
						</a>
						<span class="revit-publisher-index__count">
							<?php
							printf(
								/* translators: %d: vehicle count */
								esc_html__( '%d vehicles', 'revit-publisher' ),
								(int) ( $manufacturer['count'] ?? 0 )
							);
							?>
						</span>
					</li>
				<?php endforeach; ?>
			</ul>
		</section>
	<?php endif; ?>

	<section class="revit-publisher-index__section revit-publisher-index__section--hubs">
		<h2 class="revit-publisher-index__section-title"><?php esc_html_e( 'All Vehicle Hubs', 'revit-publisher' ); ?></h2>
		<ul class="revit-publisher-index__hub-list">
			<?php foreach ( $hubs as $hub ) : ?>
				<li class="revit-publisher-index__hub-item">
					<a href="<?php echo esc_url( (string) ( $hub['permalink'] ?? '' ) ); ?>">
						<?php echo esc_html( (string) ( $hub['title'] ?? '' ) ); ?>
					</a>
					<?php if ( ! empty( $hub['manufacturer'] ) ) : ?>
						<span class="revit-publisher-index__manufacturer-label"><?php echo esc_html( (string) $hub['manufacturer'] ); ?></span>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
	</section>
</main>
<style>
.revit-publisher-index { max-width: 960px; margin: 0 auto; padding: 2rem 1rem; }
.revit-publisher-index__title { font-size: 2rem; margin-bottom: 0.5rem; }
.revit-publisher-index__description { color: #555; }
.revit-publisher-index__section { margin-top: 2rem; }
.revit-publisher-index__section-title { font-size: 1.35rem; border-bottom: 1px solid #e5e5e5; padding-bottom: 0.35rem; }
.revit-publisher-index__manufacturer-list, .revit-publisher-index__hub-list { list-style: none; padding: 0; margin: 1rem 0 0; }
.revit-publisher-index__manufacturer-item, .revit-publisher-index__hub-item { padding: 0.35rem 0; }
.revit-publisher-index__count, .revit-publisher-index__manufacturer-label { color: #888; font-size: 0.85rem; margin-left: 0.5rem; }
</style>
<?php
get_footer();
