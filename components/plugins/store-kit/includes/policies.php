<?php
/** Policy presentation follows configured pages, never client-specific routes. */
namespace StoreKit\Policies;

defined( 'ABSPATH' ) || exit;

add_action( 'woocommerce_review_order_before_submit', __NAMESPACE__ . '\checkout_links' );
add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\assets', 20 );
add_filter( 'body_class', __NAMESPACE__ . '\body_classes' );

function checkout_links(): void {
	$links = [];
	$pages = (array) \StoreKit\Settings\get( 'policy_pages' );
	foreach ( [ 'shipping' => 'Chính sách vận chuyển, giao hàng', 'returns' => 'Chính sách đổi trả, hoàn tiền' ] as $key => $label ) {
		$id = (int) ( $pages[ $key ] ?? 0 );
		if ( $id && \StoreKit\SiteContent\public_policy( $id ) ) {
			$links[] = '<a href="' . esc_url( get_permalink( $id ) ) . '">' . esc_html( $label ) . '</a>';
		}
	}
	if ( $links ) {
		echo '<p class="sk-checkout-policy-links">Trước khi gửi yêu cầu đặt hàng, vui lòng xem: ' . implode( '; ', $links ) . '.</p>';
	}
}

function is_policy_page(): bool {
	if ( ! is_singular( 'page' ) ) {
		return false;
	}
	$id = get_queried_object_id();
	if ( in_array( $id, array_map( 'intval', (array) \StoreKit\Settings\get( 'policy_pages' ) ), true ) ) {
		return true;
	}
	$post = get_queried_object();
	return $post instanceof \WP_Post && 1 === preg_match( '/\bclass\s*=\s*(["\'])[^"\']*(?<![\w-])sk-policy(?![\w-])[^"\']*\1/i', $post->post_content );
}

function assets(): void {
	if ( is_policy_page() ) {
		wp_enqueue_style( 'sk-policies', STORE_KIT_URL . 'assets/policies.css', [ 'sk-front' ], STORE_KIT_VERSION );
	}
}

function body_classes( array $classes ): array {
	if ( is_policy_page() ) {
		$classes[] = 'sk-policy-page';
	}
	return $classes;
}
