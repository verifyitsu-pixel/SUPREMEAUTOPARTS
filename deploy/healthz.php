<?php
http_response_code( 200 );
header( 'Content-Type: application/json; charset=utf-8' );
header( 'Cache-Control: no-store' );
$response = array( 'status' => 'ok', 'service' => 'supreme-autoparts', 'time' => gmdate( 'c' ) );
echo json_encode( $response, JSON_UNESCAPED_SLASHES );
