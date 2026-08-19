<?php
/**
 * Manufacturer hub archive template.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$manufacturer_slug = sanitize_title( (string) get_query_var( 'revit_manufacturer_hub' ) );
$breadcrumbs       = new RevIt_Publisher_Public_Breadcrumbs();

if ( ! $breadcrumbs->manufacturer_meets_threshold( $manufacturer_slug ) ) {
	status_header( 404 );
	nocache_headers();
	?>
	<main class="revit-publisher-index revit-publisher-index--manufacturer revit-publisher-index--404">
		<h1><?php esc_html_e( 'Manufacturer not found', 'revit-publisher' ); ?></h1>
		<p><a href="<?php echo esc_url( $breadcrumbs->vehicles_archive_url() ); ?>"><?php esc_html_e( 'Back to vehicles', 'revit-publisher' ); ?></a></p>
	</main>
	<?php
	get_footer();
	return;
}

$hubs = $breadcrumbs->get_hubs_for_manufacturer( $manufacturer_slug );
$name = '';
foreach ( $hubs as $hub ) {
	$name = (string) ( $hub['manufacturer'] ?? '' );
	if ( '' !== $name ) {
		break;
	}
}
if ( '' === $name ) {
	$name = ucwords( str_replace( '-', ' ', $manufacturer_slug ) );
}
?>
<main class="revit-publisher-index revit-publisher-index--manufacturer">
	<header class="revit-publisher-index__header">
		<h1 class="revit-publisher-index__title"><?php echo esc_html( $name ); ?></h1>
		<p class="revit-publisher-index__description">
			<?php
			echo esc_html(
				sprintf(
					/* translators: 1: manufacturer name, 2: hub count */
					__( '%1$s vehicle hubs — %2$d models covered.', 'revit-publisher' ),
					$name,
					count( $hubs )
				)
			);
			?>
		</p>
	</header>

	<ul class="revit-publisher-index__hub-list">
		<?php foreach ( $hubs as $hub ) : ?>
			<li class="revit-publisher-index__hub-item">
				<a href="<?php echo esc_url( (string) ( $hub['permalink'] ?? '' ) ); ?>">
					<?php echo esc_html( (string) ( $hub['title'] ?? '' ) ); ?>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>
</main>
<style>
.revit-publisher-index { max-width: 960px; margin: 0 auto; padding: 2rem 1rem; }
.revit-publisher-index__title { font-size: 2rem; margin-bottom: 0.5rem; }
.revit-publisher-index__description { color: #555; }
.revit-publisher-index__hub-list { list-style: none; padding: 0; margin: 1.5rem 0 0; }
.revit-publisher-index__hub-item { padding: 0.35rem 0; }
</style>
<?php
get_footer();
