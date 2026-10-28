<?php
/**
 * Plugin Name: Store Kit — Veterinary
 * Description: Opt-in veterinary label fields and prescription purchase restrictions for Store Kit.
 * Version: 1.0.0
 * Author: Store Kit Contributors
 * License: GPL-2.0-or-later
 * Requires at least: 6.5
 * Requires PHP: 8.1
 * Requires Plugins: woocommerce, store-kit
 * Text Domain: store-kit-veterinary
 */
namespace StoreKit\Veterinary;

defined( 'ABSPATH' ) || exit;

const OPTION = 'store_kit_veterinary';
const DEFAULT_CATEGORIES = [ 'thuoc-ke-don' ];

/** Invalid stored configuration must not turn off the default prescription rule. */
function category_slugs(): array {
	$option = get_option( OPTION, [] );
	$slugs = is_array( $option ) ? ( $option['restricted_category_slugs'] ?? [] ) : [];
	if ( ! is_array( $slugs ) || ! $slugs ) {
		return DEFAULT_CATEGORIES;
	}
	foreach ( $slugs as $slug ) {
		if ( ! is_string( $slug ) || '' === $slug || strlen( $slug ) > 200 || sanitize_title( $slug ) !== $slug ) {
			return DEFAULT_CATEGORIES;
		}
	}
	return array_values( array_unique( $slugs ) );
}

/** Variations inherit parent categories; category descendants are also restricted. */
function is_rx( int $id ): bool {
	if ( 'product_variation' === get_post_type( $id ) ) {
		$id = (int) wp_get_post_parent_id( $id );
	}
	if ( $id <= 0 ) {
		return false;
	}
	$terms = get_the_terms( $id, 'product_cat' );
	if ( ! is_array( $terms ) ) {
		return false;
	}
	$restricted = [];
	foreach ( category_slugs() as $slug ) {
		$term = get_term_by( 'slug', $slug, 'product_cat' );
		if ( $term instanceof \WP_Term ) {
			$restricted[] = (int) $term->term_id;
		}
	}
	foreach ( $terms as $term ) {
		if ( in_array( (int) $term->term_id, $restricted, true ) || array_intersect( $restricted, get_ancestors( $term->term_id, 'product_cat', 'taxonomy' ) ) ) {
			return true;
		}
	}
	return false;
}

add_action( 'plugins_loaded', static function (): void {
	if ( ! defined( 'STORE_KIT_VERSION' ) || ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', static function (): void {
			if ( current_user_can( 'activate_plugins' ) ) {
				echo '<div class="notice notice-error"><p>Store Kit — Veterinary cần Store Kit và WooCommerce được kích hoạt. Quy tắc thuốc kê đơn chưa được áp dụng.</p></div>';
			}
		} );
		return;
	}
	require __DIR__ . '/includes/catalog.php';
	require __DIR__ . '/includes/restrictions.php';
	require __DIR__ . '/includes/admin.php';
}, 20 );
