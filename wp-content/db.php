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

/**
 * Initialize wpdb even if a retained wp-config.php contains the temporary
 * WP_SETUP_CONFIG flag. Core's constructor otherwise returns before connecting.
 */
final class Supreme_Railway_WPDB extends wpdb {
	private function write_runtime_probe( array $values ) {
		$current = json_decode( (string) @file_get_contents( '/tmp/supreme-wpdb-runtime.json' ), true );
		$current = is_array( $current ) ? $current : array();
		@file_put_contents(
			'/tmp/supreme-wpdb-runtime.json',
			json_encode( array_merge( $current, $values ), JSON_UNESCAPED_SLASHES )
		);
	}

	public function __construct( $dbuser, #[\SensitiveParameter] $dbpassword, $dbname, $dbhost ) {
		$this->dbuser     = $dbuser;
		$this->dbpassword = $dbpassword;
		$this->dbname     = $dbname;
		$this->dbhost     = $dbhost;
		$connected = $this->db_connect( false );
		if ( $connected && $this->ready && 0 === mysqli_connect_errno() ) {
			// A failed early Railway readiness attempt can leave wpdb->error set
			// even after the subsequent connection and database selection succeed.
			$this->error = null;
		}
		$this->write_runtime_probe(
			array(
				'loaded'        => true,
				'connected'     => (bool) $connected,
				'ready'         => (bool) $this->ready,
				'error_code'    => (int) mysqli_connect_errno(),
				'host_has_port' => 1 === substr_count( $dbhost, ':' ),
			)
		);
	}

	public function query( $query ) {
		$result = parent::query( $query );
		if ( false === $result || '' !== (string) $this->last_error ) {
			$operation = 'unknown';
			if ( preg_match( '/^\s*([a-z]+)/i', (string) $query, $matches ) ) {
				$operation = strtolower( $matches[1] );
			}
			$this->write_runtime_probe(
				array(
					'query_failure' => array(
						'operation'  => $operation,
						'error_code' => $this->dbh instanceof mysqli ? (int) mysqli_errno( $this->dbh ) : 0,
						'error_hash' => hash( 'sha256', (string) $this->last_error ),
					),
				)
			);
		}
		return $result;
	}
}

$wpdb = new Supreme_Railway_WPDB( $sa_db_user, $sa_db_password, $sa_db_name, $sa_db_host );

// WordPress checks wpdb->error immediately after this drop-in returns. Confirm
// the live handle at that boundary and discard only a stale bootstrap error.
$sa_connection_probe = $wpdb->get_var( 'SELECT 1' );
if ( '1' === (string) $sa_connection_probe && '' === (string) $wpdb->last_error ) {
	$wpdb->error = null;
	$wpdb->ready = true;
}

unset( $sa_db_user, $sa_db_password, $sa_db_name, $sa_db_host, $sa_connection_probe );
