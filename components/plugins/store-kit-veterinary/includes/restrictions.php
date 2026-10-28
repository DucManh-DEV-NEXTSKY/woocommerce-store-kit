<?php
/** Fail-safe prescription guards for native, Store API and direct DevVN purchases. */
namespace StoreKit\Veterinary\Restrictions;

use function StoreKit\Veterinary\is_rx;

defined( 'ABSPATH' ) || exit;

const RX_MESSAGE = 'Thuốc kê đơn không nhận đặt hàng trực tuyến. Chỉ sử dụng theo chỉ định của bác sĩ thú y; liên hệ cửa hàng để được hướng dẫn.';
const RX_CART_MESSAGE = 'Giỏ hàng có thuốc kê đơn không thể đặt trực tuyến. Vui lòng xóa thuốc kê đơn khỏi giỏ để tiếp tục với các sản phẩm khác.';

// Registering hooks is safe when WooCommerce is inactive; no WooCommerce API runs at load time.
add_filter( 'woocommerce_is_purchasable', __NAMESPACE__ . '\\purchasable', PHP_INT_MAX, 2 );
add_filter( 'woocommerce_variation_is_purchasable', __NAMESPACE__ . '\\purchasable', PHP_INT_MAX, 2 );
add_filter( 'woocommerce_add_to_cart_validation', __NAMESPACE__ . '\\validate_add_to_cart', PHP_INT_MAX, 4 );
add_action( 'woocommerce_check_cart_items', __NAMESPACE__ . '\\check_cart_items' );
add_action( 'woocommerce_store_api_cart_errors', __NAMESPACE__ . '\\store_api_cart_errors', 10, 2 );
add_action( 'woocommerce_single_product_summary', __NAMESPACE__ . '\\single_purchase_notice', 29 );
// DevVN 2.1.3 creates WC_Order directly instead of using native cart validation.
add_filter( 'pre_do_shortcode_tag', __NAMESPACE__ . '\\quickbuy_shortcode', PHP_INT_MAX, 3 );
add_action( 'wp', __NAMESPACE__ . '\\remove_restricted_quickbuy_popup' );
foreach ( [ 'devvn_quickbuy', 'devvn_form_quickbuy' ] as $action ) {
	add_action( 'wp_ajax_' . $action, __NAMESPACE__ . '\\validate_quickbuy', 0 );
	add_action( 'wp_ajax_nopriv_' . $action, __NAMESPACE__ . '\\validate_quickbuy', 0 );
}

/** Variations inherit their parent's product categories, not variation terms. */
function restricted_product( int $product_id ): bool {
	if ( 'product_variation' === get_post_type( $product_id ) ) {
		$product_id = (int) wp_get_post_parent_id( $product_id );
	}

	return $product_id > 0 && is_rx( $product_id );
}

/** Never make an otherwise unavailable product purchasable. */
function purchasable( bool $purchasable, \WC_Product $product ): bool {
	return $purchasable && ! restricted_product( $product->get_id() );
}

function validate_add_to_cart( bool $passed, int $product_id, $quantity, int $variation_id = 0 ): bool {
	if ( ! $passed ) {
		return false;
	}
	if ( restricted_product( $variation_id ?: $product_id ) ) {
		if ( ! wc_has_notice( RX_MESSAGE, 'error' ) ) {
			wc_add_notice( RX_MESSAGE, 'error' );
		}
		return false;
	}

	return $passed;
}

/** Existing sessions must not become a way around the add-to-cart restriction. */
function cart_has_restricted_product( \WC_Cart $cart ): bool {
	foreach ( $cart->get_cart() as $item ) {
		$product_id = isset( $item['data'] ) && $item['data'] instanceof \WC_Product
			? $item['data']->get_id()
			: (int) ( ! empty( $item['variation_id'] ) ? $item['variation_id'] : ( $item['product_id'] ?? 0 ) );
		if ( restricted_product( $product_id ) ) {
			return true;
		}
	}

	return false;
}

function check_cart_items(): void {
	if ( ! function_exists( 'WC' ) || ! WC()->cart instanceof \WC_Cart ) {
		return;
	}
	if ( cart_has_restricted_product( WC()->cart ) && ! wc_has_notice( RX_CART_MESSAGE, 'error' ) ) {
		wc_add_notice( RX_CART_MESSAGE, 'error' );
	}
}

/**
 * Native Blocks/Store API error channel, in addition to core is_purchasable checks.
 * Core CartController validates purchasability for both new and existing items;
 * it may reject those items before this additional validation action is reached.
 * No non-RX cart is blocked, and this does not add a policy-consent checkbox.
 */
function store_api_cart_errors( \WP_Error $errors, \WC_Cart $cart ): void {
	if ( cart_has_restricted_product( $cart ) ) {
		$errors->add( 'store_kit_rx_online_purchase_unavailable', RX_CART_MESSAGE );
	}
}

function single_purchase_notice(): void {
	if ( restricted_product( get_the_ID() ) ) {
		echo '<p class="sk-rx-note"><strong>Chưa thể đặt hàng trực tuyến.</strong> ' . esc_html( RX_MESSAGE ) . '</p>';
	}
}

function quickbuy_shortcode( $output, string $tag, $attributes ) {
	if ( ! in_array( $tag, [ 'devvn_quickbuy', 'devvn_quickbuy_form' ], true ) ) {
		return $output;
	}
	global $product;
	$id = is_array( $attributes ) && ! empty( $attributes['id'] )
		? absint( $attributes['id'] )
		: ( $product instanceof \WC_Product ? $product->get_id() : 0 );

	return restricted_product( $id ) ? '' : $output;
}

function remove_restricted_quickbuy_popup(): void {
	if ( ! function_exists( 'is_product' ) || ! is_product() || ! restricted_product( get_queried_object_id() ) ) {
		return;
	}
	global $devvn_quickbuy;
	if ( is_object( $devvn_quickbuy ) ) {
		remove_action( 'woocommerce_after_single_product', [ $devvn_quickbuy, 'quick_buy_popup_content_single' ] );
	}
}

function validate_quickbuy(): void {
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- Guard runs before the vendor's own handler.
	$ids = [ $_POST['prod_id'] ?? 0, $_POST['prodid'] ?? 0, $_POST['product_id'] ?? 0, $_POST['variation_id'] ?? 0 ];
	if ( isset( $_POST['product_info'] ) && is_string( $_POST['product_info'] ) ) {
		parse_str( wp_unslash( $_POST['product_info'] ), $product_info );
		foreach ( [ 'variation_id', 'product_id', 'add-to-cart' ] as $key ) {
			$ids[] = $product_info[ $key ] ?? 0;
		}
	}
	// phpcs:enable WordPress.Security.NonceVerification.Missing
	foreach ( $ids as $id ) {
		if ( is_scalar( $id ) && restricted_product( absint( $id ) ) ) {
			wp_send_json_error( [ 'code' => 'store_kit_rx_online_purchase_unavailable', 'message' => RX_MESSAGE ], 403 );
		}
	}
}
