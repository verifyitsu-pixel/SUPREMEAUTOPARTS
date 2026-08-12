<?php
/** Safe production database diagnostic: no hosts, users, passwords, or messages are exposed. */
header( 'Content-Type: application/json; charset=utf-8' );
header( 'Cache-Control: no-store' );

$host_value = (string) getenv( 'WORDPRESS_DB_HOST' );
$user       = (string) getenv( 'WORDPRESS_DB_USER' );
$password   = (string) getenv( 'WORDPRESS_DB_PASSWORD' );
$database   = (string) getenv( 'WORDPRESS_DB_NAME' );
$host       = $host_value;
$port       = 3306;

if ( preg_match( '/^\[([^]]+)](?::(\d+))?$/', $host_value, $matches ) ) {
	$host = $matches[1];
	$port = isset( $matches[2] ) ? (int) $matches[2] : $port;
} elseif ( 1 === substr_count( $host_value, ':' ) ) {
	[ $candidate_host, $candidate_port ] = explode( ':', $host_value, 2 );
	if ( ctype_digit( $candidate_port ) ) {
		$host = $candidate_host;
		$port = (int) $candidate_port;
	}
}

$response = array(
	'status'            => 'unavailable',
	'configuration_set' => '' !== $host && '' !== $user && '' !== $database,
	'password_set'      => '' !== $password,
);

if ( $response['configuration_set'] && $response['password_set'] ) {
	mysqli_report( MYSQLI_REPORT_OFF );
	$connection = @new mysqli( $host, $user, $password, $database, $port );
	if ( 0 === $connection->connect_errno ) {
		$response['status'] = 'ok';
		$connection->close();
	} else {
		// The numeric code is enough to distinguish DNS/network/auth/database failures.
		$response['error_code'] = (int) $connection->connect_errno;
	}
}

http_response_code( 'ok' === $response['status'] ? 200 : 503 );
echo json_encode( $response, JSON_UNESCAPED_SLASHES );
