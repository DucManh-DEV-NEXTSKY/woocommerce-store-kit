<?php
/** Generic product presentation. Industry fields are supplied only by extension hooks. */
namespace StoreKit\Catalog;

defined( 'ABSPATH' ) || exit;

add_action( 'woocommerce_before_shop_loop_item_title', __NAMESPACE__ . '\\card_badges', 20 );
add_action( 'woocommerce_after_shop_loop_item_title', __NAMESPACE__ . '\\card_details', 4 );
add_action( 'woocommerce_single_product_summary', __NAMESPACE__ . '\\single_badges', 4 );
add_action( 'woocommerce_single_product_summary', __NAMESPACE__ . '\\single_details', 15 );
add_action( 'woocommerce_single_product_summary', __NAMESPACE__ . '\\single_contact', 31 );
add_filter( 'woocommerce_product_add_to_cart_text', __NAMESPACE__ . '\\card_button_text', 10, 2 );
add_filter( 'the_content', __NAMESPACE__ . '\\spec_sections', 20 );
add_action( 'woocommerce_before_shop_loop', __NAMESPACE__ . '\\category_chips', 5 );

function card_button_text( string $text, \WC_Product $product ): string {
	return $product->is_type( 'simple' ) && $product->is_purchasable() && $product->is_in_stock() ? 'Thêm vào giỏ' : $text;
}

function badges_html( int $product_id ): string {
	$html = '';
	if ( get_post_meta( $product_id, \StoreKit\Videos\META, true ) ) {
		$html .= '<span class="sk-badge sk-badge--video"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 4.5v15l12-7.5z"/></svg>Có video</span>';
	}
	$html = (string) apply_filters( 'store_kit_product_badges', $html, $product_id );
	return $html ? '<span class="sk-badges">' . $html . '</span>' : '';
}

function card_badges(): void {
	echo badges_html( get_the_ID() ); // Static markup and trusted extension callbacks escape their own values.
}

function card_details(): void {
	do_action( 'store_kit_product_card_details', get_the_ID() );
}

function single_badges(): void {
	$html = badges_html( get_the_ID() );
	if ( $html ) {
		echo '<div class="sk-single-tags">' . $html . '</div>'; // Escaped above.
	}
}

function single_details(): void {
	do_action( 'store_kit_product_single_details', get_the_ID() );
}

function single_contact(): void {
	$links = '';
	if ( '' !== (string) \StoreKit\Settings\get( 'zalo_url' ) ) {
		$links .= '<a class="sk-btn sk-btn--ghost" href="' . esc_url( \StoreKit\Settings\get( 'zalo_url' ) ) . '" target="_blank" rel="noopener">Hỏi cửa hàng qua Zalo</a>';
	}
	if ( '' !== \StoreKit\Settings\phone() ) {
		$links .= '<a class="sk-link" href="tel:' . esc_attr( \StoreKit\Settings\phone() ) . '">Gọi ' . esc_html( \StoreKit\Settings\get( 'phone' ) ) . '</a>';
	}
	if ( $links ) {
		echo '<p class="sk-ask">' . $links . '</p>'; // Escaped above.
	}
}

/** Split product description heading blocks into readable panels. */
function spec_sections( string $content ): string {
	if ( ! is_singular( 'product' ) || ! in_the_loop() || false === stripos( $content, '<h2' ) ) {
		return $content;
	}
	$parts = preg_split( '/(?=<h2[\s>])/i', $content );
	$intro = '';
	$cards = '';
	foreach ( $parts as $part ) {
		$part = trim( $part );
		if ( '' === $part ) {
			continue;
		}
		if ( 0 !== stripos( $part, '<h2' ) ) {
			$intro .= $part;
			continue;
		}
		$wide   = false !== stripos( $part, '<table' ) || substr_count( $part, '<li' ) > 6 || mb_strlen( wp_strip_all_tags( $part ) ) > 700;
		$cards .= '<section class="sk-spec' . ( $wide ? ' sk-spec--wide' : '' ) . '">' . $part . '</section>';
	}
	return $intro . '<div class="sk-spec-grid">' . $cards . '</div>';
}

/** Category chips above the product grid on the shop page and category archives. */
function category_chips(): void {
	if ( ! is_shop() && ! is_product_category() ) {
		return;
	}
	$terms = get_terms(
		[
			'taxonomy'   => 'product_cat',
			'parent'     => 0,
			'hide_empty' => true,
			'exclude'    => [ (int) get_option( 'default_product_cat' ) ],
			'orderby'    => 'count',
			'order'      => 'DESC',
		]
	);
	if ( is_wp_error( $terms ) || ! $terms ) {
		return;
	}
	$current = is_product_category() ? get_queried_object_id() : 0;
	$links   = sprintf( '<a href="%s"%s>Tất cả</a>', esc_url( wc_get_page_permalink( 'shop' ) ), $current ? '' : ' aria-current="page"' );
	foreach ( $terms as $term ) {
		$links .= sprintf( '<a href="%s"%s>%s</a>', esc_url( get_term_link( $term ) ), $term->term_id === $current ? ' aria-current="page"' : '', esc_html( $term->name ) );
	}
	echo '<nav class="sk-cats" aria-label="Danh mục sản phẩm">' . $links . '</nav>'; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above.
}
