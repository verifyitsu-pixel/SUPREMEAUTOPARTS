<?php
/**
 * Railway database adapter.
 *
 * The official WordPress loader supports this standard drop-in before it
 * creates the global wpdb instance. Reading Railway's runtime variables here
 * avoids stale or externally generated DB constants while retaining the core
 * wpdb implementation.
 */

defined( 'ABSPATH' ) || exit;

$sa_db_user     = (string) getenv( 'WORDPRESS_DB_USER' );
$sa_db_password = (string) getenv( 'WORDPRESS_DB_PASSWORD' );
$sa_db_name     = (string) getenv( 'WORDPRESS_DB_NAME' );
$sa_db_host     = (string) getenv( 'WORDPRESS_DB_HOST' );

if ( '' === $sa_db_user && defined( 'DB_USER' ) ) {
	$sa_db_user = DB_USER;
}
if ( '' === $sa_db_password && defined( 'DB_PASSWORD' ) ) {
	$sa_db_password = DB_PASSWORD;
}
if ( '' === $sa_db_name && defined( 'DB_NAME' ) ) {
	$sa_db_name = DB_NAME;
}
if ( '' === $sa_db_host && defined( 'DB_HOST' ) ) {
	$sa_db_host = DB_HOST;
}

$wpdb = new wpdb( $sa_db_user, $sa_db_password, $sa_db_name, $sa_db_host );

unset( $sa_db_user, $sa_db_password, $sa_db_name, $sa_db_host );
