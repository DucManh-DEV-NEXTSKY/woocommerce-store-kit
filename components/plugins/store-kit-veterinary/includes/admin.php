<?php
/** Independent opt-in industry settings, exposed through Store Kit's tab extension API. */
namespace StoreKit\Veterinary\Admin;

defined( 'ABSPATH' ) || exit;

add_filter( 'store_kit_settings_tabs', static function ( array $tabs ): array {
	$tabs['veterinary'] = 'Thú y';
	return $tabs;
} );
add_filter( 'store_kit_settings_tab_has_form', static fn( bool $has_form, string $tab ): bool => 'veterinary' === $tab ? false : $has_form, 10, 2 );
add_action( 'store_kit_render_settings_tab', __NAMESPACE__ . '\render', 10, 2 );
add_action( 'admin_post_store_kit_save_veterinary', __NAMESPACE__ . '\save' );

function render( string $tab, array $data ): void {
	if ( 'veterinary' !== $tab ) {
		return;
	}
	$feedback = get_transient( 'store_kit_veterinary_feedback_' . get_current_user_id() );
	if ( is_array( $feedback ) ) {
		echo '<div class="notice notice-error"><ul>';
		foreach ( $feedback['errors'] as $error ) {
			echo '<li>' . esc_html( $error ) . '</li>';
		}
		echo '</ul></div>';
		delete_transient( 'store_kit_veterinary_feedback_' . get_current_user_id() );
	}
	$slugs = is_array( $feedback ) ? $feedback['input'] : implode( "\n", \StoreKit\Veterinary\category_slugs() );
	echo '<section class="sk-panel"><h2>Quy tắc thuốc kê đơn</h2><p>Sản phẩm trong các danh mục dưới đây và danh mục con không thể đặt trực tuyến, kể cả biến thể, giỏ hàng đã lưu, Store API và đặt nhanh DevVN. Tắt plugin này sẽ gỡ các quy tắc ngành thú y; chỉ thực hiện khi cửa hàng không cần các quy tắc đó.</p><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="store_kit_save_veterinary">';
	wp_nonce_field( 'store_kit_save_veterinary', 'store_kit_veterinary_nonce' );
	echo '<p><label for="sk-veterinary-slugs"><strong>Slug danh mục hạn chế (mỗi dòng một slug)</strong></label></p><textarea id="sk-veterinary-slugs" class="large-text" rows="6" name="restricted_category_slugs">' . esc_textarea( $slugs ) . '</textarea><p class="description">Phải có ít nhất một danh mục sản phẩm tồn tại. Không được để trống để vô tình tắt bảo vệ. Danh mục mặc định: thuoc-ke-don.</p><p><a href="' . esc_url( admin_url( 'edit-tags.php?taxonomy=product_cat&post_type=product' ) ) . '">Quản lý danh mục sản phẩm</a></p><button class="button button-primary" type="submit">Lưu danh mục hạn chế</button></form></section>';
}

function save(): void {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( 'Không đủ quyền.', '', [ 'response' => 403 ] );
	}
	if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
		wp_die( 'Chỉ nhận POST.', '', [ 'response' => 405 ] );
	}
	check_admin_referer( 'store_kit_save_veterinary', 'store_kit_veterinary_nonce' );
	$input = $_POST['restricted_category_slugs'] ?? null;
	$errors = [];
	if ( ! is_string( $input ) ) {
		$input = '';
		$errors[] = 'Danh sách danh mục không hợp lệ.';
	}
	$input = trim( wp_unslash( $input ) );
	$slugs = array_values( array_unique( array_filter( array_map( 'trim', preg_split( '/\R/', $input ) ), 'strlen' ) ) );
	if ( ! $slugs ) {
		$errors[] = 'Phải chọn ít nhất một danh mục hạn chế. Cài đặt cũ được giữ nguyên.';
	}
	foreach ( $slugs as $slug ) {
		if ( strlen( $slug ) > 200 || sanitize_title( $slug ) !== $slug || ! get_term_by( 'slug', $slug, 'product_cat' ) ) {
			$errors[] = 'Slug không hợp lệ hoặc danh mục chưa tồn tại: ' . $slug;
		}
	}
	if ( $errors ) {
		set_transient( 'store_kit_veterinary_feedback_' . get_current_user_id(), [ 'errors' => $errors, 'input' => $input ], 10 * MINUTE_IN_SECONDS );
	} else {
		update_option( \StoreKit\Veterinary\OPTION, [ 'restricted_category_slugs' => $slugs ], false );
		do_action( 'store_kit_settings_saved', 'veterinary', [ 'restricted_category_slugs' => $slugs ] );
		do_action( 'litespeed_purge_all' );
	}
	$url = \StoreKit\Admin\tab_url( 'veterinary' );
	wp_safe_redirect( $errors ? $url : add_query_arg( 'result', 'saved', $url ) );
	exit;
}
