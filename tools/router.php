<?php
/** Router for PHP's loopback-only development server; never a production web server. */
$root = realpath( $_SERVER['DOCUMENT_ROOT'] ?? '' );
$path = rawurldecode( (string) parse_url( $_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH ) );
if ( ! $root || preg_match( '~(?:^|/)(?:\.[^/]*|wp-config\.php|database)(?:/|$)|^/store-kit(?:/|$)|\.(?:sql|sqlite|sqlite3|db|env)$~i', $path ) ) {
	http_response_code( 403 );
	exit( 'Forbidden' );
}
$file = realpath( $root . DIRECTORY_SEPARATOR . ltrim( $path, '/' ) );
if ( $file && str_starts_with( $file, $root . DIRECTORY_SEPARATOR ) && is_file( $file ) ) {
	return false;
}
if ( $file && str_starts_with( $file, $root . DIRECTORY_SEPARATOR ) && is_dir( $file ) && is_file( $file . '/index.php' ) ) {
	$_SERVER['SCRIPT_NAME'] = rtrim( $path, '/' ) . '/index.php';
	$_SERVER['PHP_SELF'] = $_SERVER['SCRIPT_NAME'];
	require $file . '/index.php';
	return true;
}
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['PHP_SELF'] = '/index.php';
require $root . '/index.php';
