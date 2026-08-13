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
	'config_file_exists' => file_exists( __DIR__ . '/wp-config.php' ),
	'db_dropin_exists'   => file_exists( __DIR__ . '/wp-content/db.php' ),
	'db_dropin_current'  => file_exists( __DIR__ . '/wp-content/db.php' )
		&& file_exists( '/usr/src/wordpress/wp-content/db.php' )
		&& hash_file( 'sha256', __DIR__ . '/wp-content/db.php' ) === hash_file( 'sha256', '/usr/src/wordpress/wp-content/db.php' ),
	'vehicle_data_exists' => is_readable( '/opt/supreme/data/vehicle-hierarchy.json' ),
	'file_overrides_set' => array(
		'host'     => false !== getenv( 'WORDPRESS_DB_HOST_FILE' ),
		'user'     => false !== getenv( 'WORDPRESS_DB_USER_FILE' ),
		'password' => false !== getenv( 'WORDPRESS_DB_PASSWORD_FILE' ),
		'name'     => false !== getenv( 'WORDPRESS_DB_NAME_FILE' ),
	),
);

if ( $response['vehicle_data_exists'] ) {
	$vehicle_probe = json_decode( (string) @file_get_contents( '/opt/supreme/data/vehicle-hierarchy.json' ), true );
	$response['vehicle_data_valid'] = is_array( $vehicle_probe ) && isset( $vehicle_probe['makes'] );
	$response['vehicle_make_count'] = is_array( $vehicle_probe['makes'] ?? null ) ? count( $vehicle_probe['makes'] ) : 0;
}

if ( $response['configuration_set'] && $response['password_set'] ) {
	mysqli_report( MYSQLI_REPORT_OFF );
	$connection = @new mysqli( $host, $user, $password, $database, $port );
	if ( 0 === $connection->connect_errno ) {
		$response['query_ok'] = false !== $connection->query( 'SELECT 1' );
		$tables = $connection->query( 'SHOW TABLES' );
		$response['table_count'] = $tables instanceof mysqli_result ? $tables->num_rows : -1;
		$prefix = preg_replace( '/[^A-Za-z0-9_]/', '', (string) ( getenv( 'WORDPRESS_TABLE_PREFIX' ) ?: 'wp_' ) );
		$options = $connection->query( "SHOW TABLES LIKE '" . $connection->real_escape_string( $prefix . 'options' ) . "'" );
		$response['options_table'] = $options instanceof mysqli_result && 1 === $options->num_rows;
		if ( $response['options_table'] ) {
			$option_count = $connection->query( 'SELECT COUNT(*) AS total FROM `' . $prefix . 'options`' );
			$option_row = $option_count instanceof mysqli_result ? $option_count->fetch_assoc() : array();
			$response['options_row_count'] = isset( $option_row['total'] ) ? (int) $option_row['total'] : -1;
			$siteurl = $connection->query( "SELECT option_value FROM `{$prefix}options` WHERE option_name = 'siteurl' LIMIT 1" );
			$siteurl_row = $siteurl instanceof mysqli_result ? $siteurl->fetch_assoc() : null;
			$response['siteurl_present'] = is_array( $siteurl_row ) && '' !== (string) ( $siteurl_row['option_value'] ?? '' );
		}
		foreach ( array( 'users', 'posts' ) as $core_table ) {
			$count_result = $connection->query( 'SELECT COUNT(*) AS total FROM `' . $prefix . $core_table . '`' );
			$count_row = $count_result instanceof mysqli_result ? $count_result->fetch_assoc() : array();
			$response[ $core_table . '_row_count' ] = isset( $count_row['total'] ) ? (int) $count_row['total'] : -1;
		}
		$response['status'] = $response['query_ok'] ? 'ok' : 'unavailable';
		$connection->close();
	} else {
		// The numeric code is enough to distinguish DNS/network/auth/database failures.
		$response['error_code'] = (int) $connection->connect_errno;
	}
}

// Match the exact host form WordPress receives so mysqlnd parsing differences
// are visible without exposing the hostname or credentials.
if ( $response['configuration_set'] && $response['password_set'] ) {
	$raw_connection = @new mysqli( $host_value, $user, $password, $database );
	$response['raw_host_error_code'] = (int) $raw_connection->connect_errno;
	if ( 0 === $raw_connection->connect_errno ) {
		$response['raw_host_query_ok'] = false !== $raw_connection->query( 'SELECT 1' );
		$raw_connection->close();
	}
}

// Mirror wpdb::db_connect(): initialize, connect without selecting a database,
// then select it. This catches differences hidden by new mysqli(..., $database).
if ( $response['configuration_set'] && $response['password_set'] ) {
	$wpdb_connection = mysqli_init();
	$wpdb_connected  = @mysqli_real_connect( $wpdb_connection, $host, $user, $password, null, $port, null, 0 );
	$response['wpdb_style_connect_ok'] = (bool) $wpdb_connected;
	$response['wpdb_style_error_code'] = (int) mysqli_connect_errno();
	if ( $wpdb_connected ) {
		$response['wpdb_style_select_ok'] = @mysqli_select_db( $wpdb_connection, $database );
		$response['wpdb_style_query_ok']  = false !== @$wpdb_connection->query( 'SELECT option_value FROM `' . $prefix . "options` WHERE option_name = 'siteurl' LIMIT 1" );
		$wpdb_connection->close();
	}
}

// Identify whether the generated container config is still the official
// environment-driven file, without returning its contents or any secret.
if ( $response['config_file_exists'] ) {
	$config = (string) @file_get_contents( __DIR__ . '/wp-config.php' );
	$response['config_uses_environment'] = false !== strpos( $config, 'getenv_docker' );
	$response['config_defines_db']       = false !== strpos( $config, "define( 'DB_HOST'" );
	$response['config_has_setup_guard']  = false !== strpos( $config, 'WP_SETUP_CONFIG' );
}

$runtime_probe = @file_get_contents( '/tmp/supreme-wpdb-runtime.json' );
if ( false !== $runtime_probe ) {
	$runtime_probe = json_decode( $runtime_probe, true );
	if ( is_array( $runtime_probe ) ) {
		$response['wordpress_runtime'] = $runtime_probe;
	}
}

$failure_probe = @file_get_contents( '/tmp/supreme-wpdb-failure.json' );
if ( false !== $failure_probe ) {
	$failure_probe = json_decode( $failure_probe, true );
	if ( is_array( $failure_probe ) ) {
		$response['wordpress_failure'] = $failure_probe;
	}
}

$bootstrap_probe = @file_get_contents( '/tmp/supreme-bootstrap-status.json' );
if ( false !== $bootstrap_probe ) {
	$bootstrap_probe = json_decode( $bootstrap_probe, true );
	if ( is_array( $bootstrap_probe ) ) {
		$response['bootstrap'] = $bootstrap_probe;
	}
}

http_response_code( 'ok' === $response['status'] ? 200 : 503 );
echo json_encode( $response, JSON_UNESCAPED_SLASHES );
