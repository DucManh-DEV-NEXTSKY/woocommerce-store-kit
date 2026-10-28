<?php
/** Shared storefront settings. Read access never changes stored options. */
namespace StoreKit\Settings;

defined( 'ABSPATH' ) || exit;

const OPTION = 'store_kit_settings';

function defaults(): array {
	static $defaults;
	if ( null === $defaults ) {
		$defaults = require __DIR__ . '/settings-defaults.php';
	}
	return $defaults;
}

function all(): array {
	$stored = get_option( OPTION, [] );
	return ( is_array( $stored ) ? $stored : [] ) + defaults();
}

function get( string $key ) {
	$settings = all();
	return $settings[ $key ] ?? null;
}

function phone( bool $secondary = false ): string {
	return preg_replace( '/[^0-9+]/', '', (string) get( $secondary ? 'phone2' : 'phone' ) );
}

function tiktok_user(): string {
	$path = (string) wp_parse_url( (string) get( 'tiktok_url' ), PHP_URL_PATH );
	return preg_match( '~^/@([a-zA-Z0-9._]+)(?:/|$)~', $path, $match ) ? $match[1] : '';
}
