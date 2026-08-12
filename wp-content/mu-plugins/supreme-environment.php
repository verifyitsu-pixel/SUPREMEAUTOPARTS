<?php
/** Environment and production hardening loaded before regular plugins. */
defined( 'ABSPATH' ) || exit;

foreach ( array( 'WP_HOME', 'WP_SITEURL' ) as $constant ) {
	$value = getenv( $constant );
	if ( $value && ! defined( $constant ) ) define( $constant, rtrim( $value, '/' ) );
}
if ( ! defined( 'DISALLOW_FILE_EDIT' ) ) define( 'DISALLOW_FILE_EDIT', true );
if ( ! defined( 'WP_AUTO_UPDATE_CORE' ) ) define( 'WP_AUTO_UPDATE_CORE', 'minor' );
if ( ! defined( 'WP_MEMORY_LIMIT' ) ) define( 'WP_MEMORY_LIMIT', getenv( 'WP_MEMORY_LIMIT' ) ?: '256M' );
if ( ! defined( 'WP_MAX_MEMORY_LIMIT' ) ) define( 'WP_MAX_MEMORY_LIMIT', getenv( 'WP_MAX_MEMORY_LIMIT' ) ?: '512M' );
if ( ! defined( 'EMPTY_TRASH_DAYS' ) ) define( 'EMPTY_TRASH_DAYS', 14 );
if ( ! defined( 'WP_POST_REVISIONS' ) ) define( 'WP_POST_REVISIONS', 10 );

add_filter( 'xmlrpc_enabled', '__return_false' );
add_filter( 'auto_update_plugin', function ( $update, $item ) { return isset( $item->slug ) && 'woocommerce' === $item->slug ? false : $update; }, 10, 2 );
add_filter( 'wp_is_application_passwords_available', '__return_false' );

add_action( 'send_headers', function (): void {
	header( 'X-Content-Type-Options: nosniff' );
	header( 'Referrer-Policy: strict-origin-when-cross-origin' );
} );
