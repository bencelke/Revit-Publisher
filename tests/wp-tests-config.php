<?php
/**
 * WordPress test suite configuration for Docker/local integration tests.
 *
 * @package RevIt_Publisher
 */

define( 'DB_NAME', getenv( 'WP_TESTS_DB_NAME' ) ?: 'wordpress_test' );
define( 'DB_USER', getenv( 'WP_TESTS_DB_USER' ) ?: 'wordpress' );
define( 'DB_PASSWORD', getenv( 'WP_TESTS_DB_PASSWORD' ) ?: 'wordpress' );
define( 'DB_HOST', getenv( 'WP_TESTS_DB_HOST' ) ?: 'db' );
define( 'DB_CHARSET', 'utf8' );
define( 'DB_COLLATE', '' );

$wp_core_dir = getenv( 'WP_CORE_DIR' ) ?: dirname( __DIR__ ) . '/tmp/wordpress';
$wp_core_dir = rtrim( $wp_core_dir, '/' ) . '/';

define( 'ABSPATH', $wp_core_dir );

define( 'WP_TESTS_DOMAIN', 'example.org' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'Test Blog' );
define( 'WP_PHP_BINARY', 'php' );
define( 'WPLANG', '' );
define( 'WP_DEBUG', true );
define( 'WP_TESTS_FORCE_KNOWN_BUGS', true );
define( 'WP_TESTS_MULTISITE', false );

$table_prefix = 'wptests_';
