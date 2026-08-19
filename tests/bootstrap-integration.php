<?php
/**
 * WordPress integration test bootstrap.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

$plugin_dir   = dirname( __DIR__ );
$wp_tests_dir = getenv( 'WP_TESTS_DIR' ) ?: $plugin_dir . '/vendor/wp-phpunit/wp-phpunit';
$config_file  = $plugin_dir . '/tests/wp-tests-config.php';

putenv( 'WP_PHPUNIT__TESTS_CONFIG=' . $config_file );

if ( ! file_exists( "{$wp_tests_dir}/includes/functions.php" ) ) {
	echo "Could not find {$wp_tests_dir}/includes/functions.php. Run composer install.\n";
	exit( 1 );
}

require_once "{$wp_tests_dir}/includes/functions.php";

tests_add_filter(
	'muplugins_loaded',
	static function () use ( $plugin_dir ): void {
		require $plugin_dir . '/revit-publisher.php';
	}
);

require "{$wp_tests_dir}/includes/bootstrap.php";
