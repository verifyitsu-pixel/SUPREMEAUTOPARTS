<?php
header( 'Content-Type: application/json; charset=utf-8' );
header( 'Cache-Control: no-store' );
$response = array( 'status' => 'ok', 'service' => 'supreme-autoparts', 'time' => gmdate( 'c' ) );
if ( file_exists( __DIR__ . '/wp-config.php' ) ) {
	define( 'WP_USE_THEMES', false );
	require_once __DIR__ . '/wp-load.php';
	global $wpdb;
	if ( ! $wpdb || false === $wpdb->get_var( 'SELECT 1' ) ) {
		http_response_code( 503 );
		$response['status'] = 'database-unavailable';
	}
}
echo json_encode( $response, JSON_UNESCAPED_SLASHES );
