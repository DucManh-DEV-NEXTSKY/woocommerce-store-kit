<?php
/** Demo data is presentation-only, including saved carts and direct-order integrations. */
namespace StoreKit\Demo;

defined( 'ABSPATH' ) || exit;

const META = '_store_kit_demo';
const MESSAGE = 'Sản phẩm DEMO chỉ để xem bố cục, không được đặt hàng. Vui lòng xóa sản phẩm DEMO khỏi giỏ để tiếp tục.';

add_filter( 'woocommerce_is_purchasable', __NAMESPACE__ . '\purchasable', PHP_INT_MAX, 2 );
add_filter( 'woocommerce_variation_is_purchasable', __NAMESPACE__ . '\purchasable', PHP_INT_MAX, 2 );
add_filter( 'woocommerce_add_to_cart_validation', __NAMESPACE__ . '\validate_add', PHP_INT_MAX, 4 );
add_action( 'woocommerce_check_cart_items', __NAMESPACE__ . '\check_cart' );
add_action( 'woocommerce_store_api_cart_errors', __NAMESPACE__ . '\store_api_errors', 10, 2 );
add_action( 'woocommerce_single_product_summary', __NAMESPACE__ . '\product_notice', 29 );
add_action( 'admin_notices', __NAMESPACE__ . '\admin_notice' );
add_filter( 'store_kit_product_badges', __NAMESPACE__ . '\badge', 10, 2 );
add_filter( 'pre_do_shortcode_tag', __NAMESPACE__ . '\quickbuy_shortcode', PHP_INT_MAX, 3 );
foreach ( [ 'devvn_quickbuy', 'devvn_form_quickbuy' ] as $action ) {
	add_action( 'wp_ajax_' . $action, __NAMESPACE__ . '\quickbuy_guard', 0 );
	add_action( 'wp_ajax_nopriv_' . $action, __NAMESPACE__ . '\quickbuy_guard', 0 );
}

function is_demo( int $id ): bool {
	if ( $id <= 0 ) {
		return false;
	}
	if ( get_post_meta( $id, META, true ) ) {
		return true;
	}
	return 'product_variation' === get_post_type( $id ) && (bool) get_post_meta( (int) wp_get_post_parent_id( $id ), META, true );
}

function purchasable( bool $allowed, \WC_Product $product ): bool {
	return $allowed && ! is_demo( $product->get_id() );
}

function validate_add( bool $passed, int $id, $quantity, int $variation_id = 0 ): bool {
	if ( $passed && ( is_demo( $id ) || is_demo( $variation_id ) ) ) {
		if ( ! wc_has_notice( MESSAGE, 'error' ) ) {
			wc_add_notice( MESSAGE, 'error' );
		}
		return false;
	}
	return $passed;
}

function cart_has_demo( \WC_Cart $cart ): bool {
	foreach ( $cart->get_cart() as $item ) {
		$id = isset( $item['data'] ) && $item['data'] instanceof \WC_Product ? $item['data']->get_id() : (int) ( ! empty( $item['variation_id'] ) ? $item['variation_id'] : ( $item['product_id'] ?? 0 ) );
		if ( is_demo( $id ) || is_demo( (int) ( $item['product_id'] ?? 0 ) ) ) {
			return true;
		}
	}
	return false;
}

function check_cart(): void {
	if ( function_exists( 'WC' ) && WC()->cart instanceof \WC_Cart && cart_has_demo( WC()->cart ) && ! wc_has_notice( MESSAGE, 'error' ) ) {
		wc_add_notice( MESSAGE, 'error' );
	}
}

function store_api_errors( \WP_Error $errors, \WC_Cart $cart ): void {
	if ( cart_has_demo( $cart ) ) {
		$errors->add( 'store_kit_demo_purchase_unavailable', MESSAGE );
	}
}

function product_notice(): void {
	if ( is_demo( get_the_ID() ) ) {
		echo '<p class="sk-demo-note">' . esc_html( MESSAGE ) . '</p>';
	}
}

function badge( string $html, int $id ): string {
	return is_demo( $id ) ? '<span class="sk-badge sk-badge--demo">DEMO — không bán</span>' . $html : $html;
}

function admin_notice(): void {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		return;
	}
	$ids = get_posts( [ 'post_type' => [ 'product', 'product_variation' ], 'post_status' => 'any', 'posts_per_page' => 1, 'fields' => 'ids', 'meta_key' => META, 'meta_value' => '1' ] );
	if ( $ids ) {
		echo '<div class="notice notice-warning"><p>Store Kit: sản phẩm DEMO đang được bật, không thể mua. <a href="' . esc_url( \StoreKit\Admin\tab_url( 'setup' ) ) . '#store-kit-demo-purge">Mở Thiết lập để xóa dữ liệu DEMO</a> trước khi đưa cửa hàng vào hoạt động.</p></div>';
	}
}

function quickbuy_shortcode( $output, string $tag, $attributes ) {
	if ( ! in_array( $tag, [ 'devvn_quickbuy', 'devvn_quickbuy_form' ], true ) ) {
		return $output;
	}
	global $product;
	$id = is_array( $attributes ) && ! empty( $attributes['id'] ) ? absint( $attributes['id'] ) : ( $product instanceof \WC_Product ? $product->get_id() : 0 );
	return is_demo( $id ) ? '' : $output;
}

/** Vendor creates orders directly, so reject before its AJAX order handler. */
function quickbuy_guard(): void {
	$ids = [ $_POST['prod_id'] ?? 0, $_POST['prodid'] ?? 0, $_POST['product_id'] ?? 0, $_POST['variation_id'] ?? 0 ];
	if ( isset( $_POST['product_info'] ) && is_string( $_POST['product_info'] ) ) {
		parse_str( wp_unslash( $_POST['product_info'] ), $info );
		foreach ( [ 'variation_id', 'product_id', 'add-to-cart' ] as $key ) {
			$ids[] = $info[ $key ] ?? 0;
		}
	}
	foreach ( $ids as $id ) {
		if ( is_scalar( $id ) && is_demo( absint( $id ) ) ) {
			wp_send_json_error( [ 'code' => 'store_kit_demo_purchase_unavailable', 'message' => MESSAGE ], 403 );
		}
	}
}
