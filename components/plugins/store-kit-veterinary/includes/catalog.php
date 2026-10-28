<?php
/** Veterinary product label data exists only while this optional module is active. */
namespace StoreKit\Veterinary\Catalog;

defined( 'ABSPATH' ) || exit;

const FOR_META = '_store_kit_for';
const DOSE_META = '_store_kit_dose';

add_action( 'init', __NAMESPACE__ . '\register_meta' );
add_action( 'woocommerce_product_options_general_product_data', __NAMESPACE__ . '\admin_fields' );
add_action( 'woocommerce_admin_process_product_object', __NAMESPACE__ . '\admin_save' );
add_action( 'woocommerce_variation_options_pricing', __NAMESPACE__ . '\variation_fields', 10, 3 );
add_action( 'woocommerce_save_product_variation', __NAMESPACE__ . '\variation_save', 10, 2 );
add_filter( 'store_kit_product_badges', __NAMESPACE__ . '\badges', 10, 2 );
add_action( 'store_kit_product_card_details', __NAMESPACE__ . '\card_details' );
add_action( 'store_kit_product_single_details', __NAMESPACE__ . '\single_details' );
add_filter( 'store_kit_footer_notice', __NAMESPACE__ . '\footer_notice' );
add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\assets', 30 );

function register_meta(): void {
	foreach ( [ 'product', 'product_variation' ] as $type ) {
		foreach ( [ FOR_META, DOSE_META ] as $key ) {
			register_post_meta( $type, $key, [ 'type' => 'string', 'single' => true, 'show_in_rest' => true, 'sanitize_callback' => 'sanitize_text_field', 'auth_callback' => static fn() => current_user_can( 'edit_products' ) ] );
		}
	}
}

function admin_fields(): void {
	echo '<div class="options_group">';
	woocommerce_wp_text_input( [ 'id' => FOR_META, 'label' => 'Dùng cho', 'desc_tip' => true, 'description' => 'Đối tượng sử dụng được ghi trên nhãn sản phẩm.' ] );
	woocommerce_wp_text_input( [ 'id' => DOSE_META, 'label' => 'Liều theo nhãn (ngắn)', 'desc_tip' => true, 'description' => 'Chép theo nhãn được phê duyệt; không thay thế chỉ định của bác sĩ thú y.' ] );
	echo '</div>';
}

function admin_save( \WC_Product $product ): void {
	foreach ( [ FOR_META, DOSE_META ] as $key ) {
		$value = isset( $_POST[ $key ] ) && is_string( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';
		'' === $value ? $product->delete_meta_data( $key ) : $product->update_meta_data( $key, $value );
	}
}

function variation_fields( int $index, array $variation_data, \WP_Post $variation ): void {
	foreach ( [ FOR_META => 'Dùng cho', DOSE_META => 'Liều theo nhãn (ngắn)' ] as $key => $label ) {
		woocommerce_wp_text_input( [ 'id' => $key . '_' . $index, 'name' => $key . '[' . $index . ']', 'label' => $label, 'value' => get_post_meta( $variation->ID, $key, true ), 'wrapper_class' => 'form-row form-row-full', 'description' => 'Để trống để dùng nội dung của sản phẩm cha.' ] );
	}
}

function variation_save( int $id, int $index ): void {
	if ( ! current_user_can( 'edit_post', $id ) ) {
		return;
	}
	foreach ( [ FOR_META, DOSE_META ] as $key ) {
		$value = isset( $_POST[ $key ][ $index ] ) && is_string( $_POST[ $key ][ $index ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ][ $index ] ) ) : '';
		'' === $value ? delete_post_meta( $id, $key ) : update_post_meta( $id, $key, $value );
	}
}

function label( int $id, string $key ): string {
	$value = (string) get_post_meta( $id, $key, true );
	if ( '' === $value && 'product_variation' === get_post_type( $id ) ) {
		$value = (string) get_post_meta( (int) wp_get_post_parent_id( $id ), $key, true );
	}
	return $value;
}

function badges( string $html, int $id ): string {
	return \StoreKit\Veterinary\is_rx( $id ) ? '<span class="sk-badge sk-badge--rx">Thuốc kê đơn</span>' . $html : $html;
}

function card_details( int $id ): void {
	$for = label( $id, FOR_META );
	$dose = label( $id, DOSE_META );
	if ( '' !== $for ) {
		echo '<span class="sk-for">' . esc_html( $for ) . '</span>';
	}
	if ( '' !== $dose ) {
		echo '<p class="sk-dose"><b>Liều:</b> ' . esc_html( $dose ) . '</p>';
	}
}

function single_details( int $id ): void {
	$for = label( $id, FOR_META );
	$dose = label( $id, DOSE_META );
	if ( '' !== $for ) {
		echo '<span class="sk-for">' . esc_html( $for ) . '</span>';
	}
	if ( '' !== $dose ) {
		echo '<p class="sk-dose sk-dose--single"><b>Liều theo nhãn:</b> ' . esc_html( $dose ) . '</p>';
	}
	if ( \StoreKit\Veterinary\is_rx( $id ) ) {
		echo '<p class="sk-rx-note">Thuốc kê đơn: chỉ dùng khi có chẩn đoán và chỉ định của bác sĩ thú y.</p>';
	}
}

function footer_notice( string $notice ): string {
	return $notice . ( $notice ? ' ' : '' ) . 'Thuốc kê đơn chỉ dùng theo chỉ định của bác sĩ thú y. Liều trên website ghi theo nhãn sản phẩm để đối chiếu.';
}

function assets(): void {
	wp_enqueue_style( 'sk-veterinary', plugin_dir_url( dirname( __DIR__ ) . '/store-kit-veterinary.php' ) . 'assets/veterinary.css', [ 'sk-front' ], '1.0.0' );
}
