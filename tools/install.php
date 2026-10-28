<?php
/** Fresh installation through WordPress's native API; credentials arrive only on stdin. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit;
}
try {
	$input = json_decode( stream_get_contents( STDIN ), true, 16, JSON_THROW_ON_ERROR );
	if ( ! is_array( $input ) || array_diff( [ 'root', 'url', 'title', 'user', 'email', 'password' ], array_keys( $input ) ) ) {
		WP_CLI::error( 'Incomplete fresh-install input.' );
	}
	$root = realpath( $input['root'] );
	if ( ! $root || ! is_file( $root . '/wp-load.php' ) || ! is_file( $root . '/.store-kit-instance.json' ) ) {
		WP_CLI::error( 'Fresh install requires an owned Store Kit instance.' );
	}
	$parts = parse_url( $input['url'] );
	if ( ! is_array( $parts ) || empty( $parts['host'] ) || ! in_array( $parts['scheme'] ?? '', [ 'http', 'https' ], true ) ) {
		WP_CLI::error( 'Invalid installation URL.' );
	}
	$_SERVER['HTTP_HOST'] = $parts['host'] . ( isset( $parts['port'] ) ? ':' . $parts['port'] : '' );
	$_SERVER['SERVER_NAME'] = $parts['host'];
	$_SERVER['REQUEST_URI'] = ( $parts['path'] ?? '' ) . '/';
	$_SERVER['REQUEST_METHOD'] = 'GET';
	$_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
	$_SERVER['HTTPS'] = 'https' === $parts['scheme'] ? 'on' : '';
	define( 'WP_INSTALLING', true );
	define( 'WP_HOME', $input['url'] );
	define( 'WP_SITEURL', $input['url'] );
	require $root . '/wp-load.php';
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	if ( is_blog_installed() ) {
		WP_CLI::error( 'WordPress is already installed; refusing to reset its administrator.' );
	}
	if ( ! is_email( $input['email'] ) || strlen( $input['password'] ) < 16 ) {
		WP_CLI::error( 'Use a valid email and an administrator password of at least 16 characters.' );
	}
	// Match --skip-email during fresh setup; no SMTP or welcome mail leaves the setup process.
	add_filter( 'pre_wp_mail', static fn() => true );
	$result = wp_install( $input['title'], $input['user'], $input['email'], false, '', $input['password'] );
	if ( empty( $result['user_id'] ) ) {
		WP_CLI::error( 'WordPress did not create the administrator.' );
	}
	WP_CLI::success( 'WordPress installed with a new private administrator account.' );
} catch ( Throwable $error ) {
	WP_CLI::error( 'Fresh installation failed: ' . $error->getMessage() );
}
