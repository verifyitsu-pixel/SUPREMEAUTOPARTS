<?php
http_response_code( 200 );
header( 'Content-Type: application/json; charset=utf-8' );
header( 'Cache-Control: no-store' );
$response = array( 'status' => 'ok', 'service' => 'supreme-autoparts', 'time' => gmdate( 'c' ) );
$bootstrap = json_decode( (string) @file_get_contents( '/tmp/supreme-bootstrap-status.json' ), true );
if ( is_array( $bootstrap ) ) {
	$response['bootstrap'] = array( 'stage' => preg_replace( '/[^A-Za-z0-9_-]/', '', (string) ( $bootstrap['stage'] ?? '' ) ), 'ok' => (bool) ( $bootstrap['ok'] ?? false ) );
}
echo json_encode( $response, JSON_UNESCAPED_SLASHES );
