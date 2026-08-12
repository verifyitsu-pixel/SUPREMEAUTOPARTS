<?php
/** Capture safe diagnostics from WordPress's database failure path. */

global $wpdb;

$probe = array(
	'failure_hook_loaded' => true,
	'class'               => is_object( $wpdb ) ? get_class( $wpdb ) : 'none',
	'ready'               => is_object( $wpdb ) && ! empty( $wpdb->ready ),
	'error_code'          => (int) mysqli_connect_errno(),
	'error_set'           => is_object( $wpdb ) && ! empty( $wpdb->error ),
	'error_type'          => is_object( $wpdb ) && is_object( $wpdb->error ) ? get_class( $wpdb->error ) : gettype( is_object( $wpdb ) ? $wpdb->error : null ),
	'error_hash'          => is_object( $wpdb ) && ! is_object( $wpdb->error ) ? hash( 'sha256', (string) $wpdb->error ) : '',
	'last_error_set'      => is_object( $wpdb ) && '' !== (string) $wpdb->last_error,
	'last_error_hash'     => is_object( $wpdb ) ? hash( 'sha256', (string) $wpdb->last_error ) : '',
);

$probe['trace'] = array_map(
	static function ( $frame ) {
		return ( isset( $frame['class'] ) ? $frame['class'] . ( $frame['type'] ?? '' ) : '' ) . ( $frame['function'] ?? 'unknown' );
	},
	array_slice( debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS ), 0, 10 )
);

if ( is_object( $wpdb ) ) {
	$probe['host_matches_environment']     = hash_equals( (string) getenv( 'WORDPRESS_DB_HOST' ), (string) $wpdb->dbhost );
	$probe['user_matches_environment']     = hash_equals( (string) getenv( 'WORDPRESS_DB_USER' ), (string) $wpdb->dbuser );
	$probe['password_matches_environment'] = hash_equals( (string) getenv( 'WORDPRESS_DB_PASSWORD' ), (string) $wpdb->dbpassword );
	$probe['name_matches_environment']     = hash_equals( (string) getenv( 'WORDPRESS_DB_NAME' ), (string) $wpdb->dbname );
}

@file_put_contents( '/tmp/supreme-wpdb-failure.json', json_encode( $probe, JSON_UNESCAPED_SLASHES ) );

http_response_code( 503 );
header( 'Content-Type: text/html; charset=utf-8' );
header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
echo '<!doctype html><html><head><meta charset="utf-8"><title>Supreme Autoparts</title></head><body><h1>Supreme Autoparts is starting</h1><p>Please retry shortly.</p></body></html>';
