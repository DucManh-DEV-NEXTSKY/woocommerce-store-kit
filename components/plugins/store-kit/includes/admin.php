<?php
/** Vietnamese, tab-scoped management for the Store Kit storefront. */
namespace StoreKit\Admin;

use StoreKit\Settings;

defined( 'ABSPATH' ) || exit;

const CAPABILITY = 'manage_woocommerce';
const PAGE = 'store-kit-settings';
const CHANNEL = 'store_kit_channel_videos';
const COVERS = 'store_kit_video_covers';
const NO_EMBED = 'store_kit_video_noembed';

add_action( 'admin_menu', __NAMESPACE__ . '\\menu' );
add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\\assets' );
add_action( 'admin_post_store_kit_save_settings', __NAMESPACE__ . '\\save' );

function tabs(): array {
	return apply_filters( 'store_kit_settings_tabs', [
		'store' => 'Cửa hàng', 'contact' => 'Liên hệ', 'home' => 'Trang chủ',
		'problems' => 'Nhóm vấn đề', 'videos' => 'Video TikTok',
		'legal' => 'Pháp lý & chính sách', 'operations' => 'Vận hành',
	] );
}

function menu(): void {
	add_menu_page( 'Cài đặt Store Kit', 'Cài đặt Store Kit', CAPABILITY, PAGE, __NAMESPACE__ . '\\render', 'dashicons-store', 56 );
}

function assets( string $hook ): void {
	if ( 'toplevel_page_' . PAGE !== $hook ) {
		return;
	}
	$dir = dirname( __DIR__ ) . '/assets/';
	wp_enqueue_media();
	wp_enqueue_style( 'sk-admin', STORE_KIT_URL . 'assets/admin.css', [], (string) filemtime( $dir . 'admin.css' ) );
	wp_enqueue_script( 'sk-admin', STORE_KIT_URL . 'assets/admin.js', [ 'jquery', 'jquery-ui-sortable' ], (string) filemtime( $dir . 'admin.js' ), true );
}

function tab_url( string $tab ): string {
	return add_query_arg( [ 'page' => PAGE, 'tab' => $tab ], admin_url( 'admin.php' ) );
}

/** Enrich old rows for editing without writing or removing disabled entries. */
function channel_rows(): array {
	$rows = get_option( CHANNEL, [] );
	if ( ! is_array( $rows ) ) {
		return [];
	}
	foreach ( $rows as $index => &$row ) {
		if ( ! is_array( $row ) ) {
			$row = [ 'id' => (string) $row ];
		}
		$id = (string) ( $row['id'] ?? '' );
		$row += [
			'id' => $id, 'url' => \StoreKit\Videos\video_url( $id ),
			'title' => '', 'views' => '', 'tie' => '', 'enabled' => true,
			'hero' => $index < 2, 'section' => $index >= 2,
		];
	}
	unset( $row );
	return array_values( $rows );
}

/** Read a scalar without allowing nested form data to be silently discarded. */
function scalar( array $data, string $key, string $label, array &$errors ): string {
	if ( ! array_key_exists( $key, $data ) || ! is_scalar( $data[ $key ] ) ) {
		$errors[] = $label . ': thiếu dữ liệu hợp lệ. Tải lại trang rồi thử lại.';
		return '';
	}
	return trim( (string) $data[ $key ] );
}

function text( array $data, string $key, string $label, array &$errors, bool $multiline = false ): string {
	$value = scalar( $data, $key, $label, $errors );
	if ( strlen( $value ) > 10000 ) {
		$errors[] = $label . ': nội dung quá dài (tối đa 10.000 byte).';
	}
	return $multiline ? sanitize_textarea_field( $value ) : sanitize_text_field( $value );
}

function url( string $value, string $label, array &$errors, bool $relative = false ): string {
	if ( '' === $value ) {
		return '';
	}
	if ( $relative && preg_match( '~^(?:/(?!/)|#[a-zA-Z0-9_-])[^\s<>"\x00-\x1f]*$~', $value ) ) {
		return esc_url_raw( $value );
	}
	$parts = wp_parse_url( $value );
	if ( ! $parts || ! in_array( strtolower( $parts['scheme'] ?? '' ), [ 'http', 'https' ], true ) || empty( $parts['host'] ) || isset( $parts['user'] ) || isset( $parts['pass'] ) || ! filter_var( $value, FILTER_VALIDATE_URL ) ) {
		$errors[] = $label . ': nhập địa chỉ đầy đủ bắt đầu bằng https:// hoặc http://.';
		return '';
	}
	return esc_url_raw( $value, [ 'http', 'https' ] );
}

function integer( string $value, int $min, int $max, string $label, array &$errors ): int {
	if ( ! preg_match( '/^\d+$/D', $value ) || strlen( $value ) > 10 || (int) $value < $min || (int) $value > $max ) {
		$errors[] = sprintf( '%s: nhập số nguyên từ %d đến %d.', $label, $min, $max );
		return $min;
	}
	return (int) $value;
}

function checked_value( array $data, string $key, array &$errors ): bool {
	if ( ! isset( $data[ $key ] ) ) {
		return false;
	}
	if ( ! is_scalar( $data[ $key ] ) || '1' !== (string) $data[ $key ] ) {
		$errors[] = 'Giá trị ô đánh dấu không hợp lệ.';
		return false;
	}
	return true;
}

function rows( array $data, string $key, array &$errors ): array {
	if ( ! isset( $data[ $key ] ) ) {
		return [];
	}
	if ( ! is_array( $data[ $key ] ) ) {
		$errors[] = 'Danh sách không hợp lệ. Tải lại trang rồi thử lại.';
		return [];
	}
	foreach ( $data[ $key ] as $row ) {
		if ( ! is_array( $row ) ) {
			$errors[] = 'Một dòng trong danh sách không hợp lệ.';
			return [];
		}
	}
	return array_values( $data[ $key ] );
}

function product_id( string $value, string $label, array &$errors, bool $optional = false ): int {
	if ( $optional && ( '' === $value || '0' === $value ) ) {
		return 0;
	}
	$id = integer( $value, 1, PHP_INT_MAX > 2147483647 ? 2147483647 : PHP_INT_MAX, $label, $errors );
	if ( 'product' !== get_post_type( $id ) || 'publish' !== get_post_status( $id ) ) {
		$errors[] = $label . ': chọn sản phẩm đang được xuất bản.';
	}
	return $id;
}

function category_id( string $value, string $label, array &$errors, bool $optional = false ): int {
	if ( $optional && ( '' === $value || '0' === $value ) ) {
		return 0;
	}
	$id = integer( $value, 1, 2147483647, $label, $errors );
	$term = get_term( $id, 'product_cat' );
	if ( ! $term || is_wp_error( $term ) ) {
		$errors[] = $label . ': chọn danh mục sản phẩm có sẵn.';
	}
	return $id;
}

/** Returns only this tab's keys. No writes occur until the entire tab validates. */
function validate_tab( string $tab, array $data, array &$errors ): array {
	$result = [];
	$fields = [];
	if ( 'store' === $tab ) {
		$fields = [ 'brand' => 'Tên thương hiệu', 'legal_name' => 'Tên pháp lý', 'tax_id' => 'Mã số thuế', 'footer_description' => 'Giới thiệu chân trang' ];
	} elseif ( 'contact' === $tab ) {
		$fields = [ 'address' => 'Địa chỉ', 'returns_address' => 'Địa chỉ nhận đổi trả', 'email' => 'Email', 'hours' => 'Giờ làm việc', 'phone' => 'Hotline chính', 'phone2' => 'Hotline phụ', 'zalo_url' => 'Zalo', 'facebook_url' => 'Facebook', 'tiktok_url' => 'TikTok', 'youtube_url' => 'YouTube' ];
	} elseif ( 'home' === $tab ) {
		$fields = [ 'hero_title' => 'Tiêu đề đầu trang', 'hero_lede' => 'Mô tả đầu trang', 'hero_badge' => 'Nhãn đầu trang', 'announce_before' => 'Thông báo trước hotline', 'announce_after' => 'Thông báo sau hotline' ];
	} elseif ( 'legal' === $tab ) {
		$fields = [ 'bct_url' => 'Liên kết Bộ Công Thương' ];
	}
	foreach ( $fields as $key => $label ) {
		$result[ $key ] = text( $data, $key, $label, $errors, in_array( $key, [ 'footer_description', 'hero_lede', 'address', 'returns_address' ], true ) );
		if ( str_ends_with( $key, '_url' ) ) {
			$result[ $key ] = url( scalar( $data, $key, $label, $errors ), $label, $errors );
		}
	}
	if ( 'store' === $tab ) {
		if ( '' === $result['brand'] ) {
			$errors[] = 'Tên thương hiệu không được để trống.';
		}
		foreach ( [ 'logo_id', 'footer_logo_id' ] as $key ) {
			$id = integer( scalar( $data, $key, 'Logo', $errors ), 0, 2147483647, 'Logo', $errors );
			if ( $id && ( 'attachment' !== get_post_type( $id ) || ! wp_attachment_is_image( $id ) ) ) {
				$errors[] = 'Logo: chọn ảnh có trong Thư viện.';
			}
			$result[ $key ] = $id;
		}
		$result['tax_id'] = scalar( $data, 'tax_id', 'Mã số thuế', $errors );
		if ( '' !== $result['tax_id'] && ! preg_match( '/^(?:\d{10}|\d{12}|\d{10}-\d{3})$/D', $result['tax_id'] ) ) {
			$errors[] = 'Mã số thuế: dùng 10 hoặc 12 chữ số, hoặc 10 chữ số kèm hậu tố chi nhánh -XXX. Số 0 đầu được giữ nguyên.';
		}
	}
	if ( 'contact' === $tab ) {
		$email = scalar( $data, 'email', 'Email', $errors );
		if ( '' !== $email && ( ! is_email( $email ) || sanitize_email( $email ) !== $email ) ) {
			$errors[] = 'Email chưa đúng định dạng.';
		}
		$result['email'] = $email;
		foreach ( [ 'phone', 'phone2' ] as $key ) {
			$phone = scalar( $data, $key, 'Hotline', $errors );
			$digits = preg_replace( '/\D/', '', $phone );
			if ( '' !== $phone && ( ! preg_match( '/^\+?[0-9 ().-]+$/D', $phone ) || strlen( $digits ) < 8 || strlen( $digits ) > 15 ) ) {
				$errors[] = 'Hotline: dùng 8–15 chữ số, có thể có dấu + đầu số, khoảng trắng, dấu chấm, gạch nối hoặc ngoặc.';
			}
			$result[ $key ] = $phone;
		}
		if ( '' !== $result['tiktok_url'] && ! preg_match( '~^https?://(?:www\.)?tiktok\.com/@[a-zA-Z0-9._]+/?$~D', $result['tiktok_url'] ) ) {
			$errors[] = 'TikTok: nhập liên kết hồ sơ, ví dụ https://www.tiktok.com/@tenkenh (không dùng liên kết rút gọn).';
		}
	}
	if ( 'home' === $tab ) {
		$result['video_section_limit'] = integer( scalar( $data, 'video_section_limit', 'Số video', $errors ), 1, 50, 'Số video', $errors );
		$result['posts_count'] = integer( scalar( $data, 'posts_count', 'Số bài viết', $errors ), 1, 30, 'Số bài viết', $errors );
		$result['featured_products'] = [];
		foreach ( rows( $data, 'featured_products', $errors ) as $row ) {
			$value = scalar( $row, 'product_id', 'Sản phẩm nổi bật', $errors );
			if ( in_array( $value, [ '', '0' ], true ) && ! empty( $row['existing'] ) ) {
				$errors[] = 'Sản phẩm nổi bật: chọn sản phẩm hoặc dùng nút Xóa để bỏ dòng hiện có.';
			}
			if ( '' !== $value && '0' !== $value ) {
				$id = product_id( $value, 'Sản phẩm nổi bật', $errors );
				if ( in_array( $id, $result['featured_products'], true ) ) {
					$errors[] = 'Danh sách sản phẩm nổi bật có sản phẩm trùng lặp.';
				}
				$result['featured_products'][] = $id;
			}
		}
		$result['sections'] = [];
		$sections = isset( $data['sections'] ) && is_array( $data['sections'] ) ? $data['sections'] : [];
		foreach ( section_names() as $key => $name ) {
			$section = isset( $sections[ $key ] ) && is_array( $sections[ $key ] ) ? $sections[ $key ] : [];
			$clean = [ 'enabled' => checked_value( $section, 'enabled', $errors ) ];
			foreach ( [ 'title', 'lede', 'eyebrow', 'link_text', 'link_url' ] as $field ) {
				$clean[ $field ] = text( $section, $field, $name . ' / ' . $field, $errors, 'lede' === $field );
			}
			$clean['link_url'] = url( scalar( $section, 'link_url', $name . ' / liên kết', $errors ), $name . ' / liên kết', $errors, true );
			$result['sections'][ $key ] = $clean;
		}
		$result['steps'] = [];
		foreach ( rows( $data, 'steps', $errors ) as $row ) {
			$title = text( $row, 'title', 'Tiêu đề bước', $errors );
			$description = text( $row, 'description', 'Mô tả bước', $errors, true );
			if ( '' === $title && '' === $description && empty( $row['existing'] ) ) {
				continue;
			}
			if ( '' === $title ) {
				$errors[] = 'Mỗi bước đặt hàng cần có tiêu đề; dùng nút Xóa nếu không cần dòng này.';
			}
			$result['steps'][] = compact( 'title', 'description' );
		}
	}
	if ( 'problems' === $tab ) {
		$result['problems'] = [];
		foreach ( rows( $data, 'problems', $errors ) as $row ) {
			$title = text( $row, 'title', 'Tên nhóm vấn đề', $errors );
			$description = text( $row, 'description', 'Mô tả vấn đề', $errors, true );
			$chip = text( $row, 'chip', 'Nhãn vấn đề', $errors );
			$category = scalar( $row, 'category_id', 'Danh mục', $errors );
			if ( '' === $title && '' === $description && '' === $chip && in_array( $category, [ '', '0' ], true ) && empty( $row['existing'] ) ) {
				continue;
			}
			if ( '' === $title ) {
				$errors[] = 'Mỗi nhóm vấn đề cần có tên; dùng nút Xóa nếu không cần dòng này.';
			}
			$result['problems'][] = [ 'title' => $title, 'description' => $description, 'category_id' => category_id( $category, 'Nhóm vấn đề “' . $title . '”', $errors ), 'chip' => $chip, 'enabled' => checked_value( $row, 'enabled', $errors ) ];
		}
	}
	if ( 'legal' === $tab ) {
		$result['bct_confirmed'] = checked_value( $data, 'bct_confirmed', $errors );
		if ( '' !== $result['bct_url'] && ! preg_match( '~^https://(?:www\.)?online\.gov\.vn/(?:Home/WebDetails/[0-9]+|nen-tang/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})/?(?:\?[^#\s]*)?$~iD', $result['bct_url'] ) ) {
			$errors[] = 'Bộ Công Thương: chỉ nhận liên kết HTTPS tra cứu chính thức trên online.gov.vn, dạng /Home/WebDetails/12345 hoặc /nen-tang/mã-hồ-sơ-UUID. Để trống nếu chưa được xác nhận.';
		}
		if ( $result['bct_confirmed'] && '' === $result['bct_url'] ) {
			$errors[] = 'Chỉ xác nhận Bộ Công Thương khi đã có liên kết tra cứu chính thức.';
		}
		$result['policy_pages'] = [];
		$policies = isset( $data['policy_pages'] ) && is_array( $data['policy_pages'] ) ? $data['policy_pages'] : [];
		foreach ( policy_names() as $key => $label ) {
			$value = scalar( $policies, $key, $label, $errors );
			$id = integer( $value, 0, 2147483647, $label, $errors );
			if ( $id && ! \StoreKit\SiteContent\public_policy( $id ) ) {
				$errors[] = $label . ': chỉ chọn trang đã xuất bản. Hãy hoàn thiện và xuất bản trang trong mục Trang trước.';
			}
			$result['policy_pages'][ $key ] = $id;
		}
	}
	$filtered = apply_filters( 'store_kit_validate_settings_tab', [ 'values' => $result, 'errors' => $errors ], $tab, $data );
	if ( ! is_array( $filtered ) || ! isset( $filtered['values'], $filtered['errors'] ) || ! is_array( $filtered['values'] ) || ! is_array( $filtered['errors'] ) ) {
		$errors[] = 'Tiện ích cài đặt trả về dữ liệu không hợp lệ.';
		return [];
	}
	$errors = $filtered['errors'];
	return $filtered['values'];
}

function validate_videos( array $data, array &$errors ): array {
	$videos = [];
	$covers = get_option( COVERS, [] );
	$covers = is_array( $covers ) ? $covers : [];
	$external = get_option( NO_EMBED, [] );
	$external = is_array( $external ) ? array_map( 'strval', $external ) : [];
	$seen = [];
	foreach ( rows( $data, 'videos', $errors ) as $index => $row ) {
		$label = 'Video ' . ( $index + 1 );
		$link = scalar( $row, 'url', $label . ' / liên kết', $errors );
		$title = text( $row, 'title', $label . ' / tiêu đề', $errors );
		$views = text( $row, 'views', $label . ' / lượt xem', $errors );
		$tie = scalar( $row, 'tie', $label . ' / liên kết tư vấn', $errors );
		$cover_value = scalar( $row, 'cover', $label . ' / ảnh bìa', $errors );
		$original = scalar( $row, 'original_id', $label . ' / dữ liệu gốc', $errors );
		if ( '' === $link && '' === $title && '' === $views && '' === $tie && in_array( $cover_value, [ '', '0' ], true ) && '' === $original && empty( $row['external'] ) ) {
			continue;
		}
		if ( ! preg_match( '~^https://www\.tiktok\.com/@([a-zA-Z0-9._]+)/video/(\d{15,20})/?(?:\?[^\s#<>]*)?$~D', $link, $match ) ) {
			$errors[] = $label . ': dán liên kết đầy đủ https://www.tiktok.com/@tenkenh/video/1234567890123456789. Không dùng ID đơn lẻ hoặc liên kết vm.tiktok.com / vt.tiktok.com.';
			continue;
		}
		$id = $match[2]; // Keep as a string: TikTok IDs exceed PHP/JavaScript integer precision.
		if ( in_array( $id, $seen, true ) ) {
			$errors[] = $label . ': video này đã có trong danh sách.';
		}
		$seen[] = $id;
		if ( '' !== $tie && 'zalo' !== $tie ) {
			if ( preg_match( '/^product:(\d+)$/D', $tie, $target ) ) {
				product_id( $target[1], $label . ' / sản phẩm', $errors );
			} elseif ( preg_match( '/^cat:(\d+)$/D', $tie, $target ) ) {
				category_id( $target[1], $label . ' / danh mục', $errors );
			} else {
				$errors[] = $label . ': chọn liên kết tư vấn trong danh sách.';
			}
		}
		$cover = integer( '' === $cover_value ? '0' : $cover_value, 0, 2147483647, $label . ' / ảnh bìa', $errors );
		if ( $cover && ( 'attachment' !== get_post_type( $cover ) || ! wp_attachment_is_image( $cover ) ) ) {
			$errors[] = $label . ': ảnh bìa phải là ảnh có trong Thư viện.';
		}
		if ( $cover ) {
			$covers[ $id ] = $cover;
		} else {
			unset( $covers[ $id ] );
		}
		$external = array_values( array_filter( $external, static fn( $value ) => $value !== $id ) );
		if ( checked_value( $row, 'external', $errors ) ) {
			$external[] = $id;
		}
		$videos[] = [
			'id' => $id, 'url' => esc_url_raw( $link, [ 'https' ] ), 'title' => $title, 'views' => $views, 'tie' => $tie,
			'enabled' => checked_value( $row, 'enabled', $errors ), 'hero' => checked_value( $row, 'hero', $errors ), 'section' => checked_value( $row, 'section', $errors ),
		];
	}
	return [ $videos, $covers, array_values( array_unique( $external, SORT_STRING ) ) ];
}

function save(): void {
	if ( ! current_user_can( CAPABILITY ) ) {
		wp_die( 'Bạn không có quyền thay đổi cài đặt Store Kit.', '', [ 'response' => 403 ] );
	}
	if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
		wp_die( 'Chỉ chấp nhận yêu cầu lưu qua biểu mẫu.', '', [ 'response' => 405 ] );
	}
	$tab = isset( $_POST['tab'] ) && is_string( $_POST['tab'] ) ? sanitize_key( wp_unslash( $_POST['tab'] ) ) : '';
	if ( ! isset( tabs()[ $tab ] ) || ! apply_filters( 'store_kit_settings_tab_has_form', 'operations' !== $tab, $tab ) ) {
		wp_die( 'Mục cài đặt không hợp lệ.', '', [ 'response' => 400 ] );
	}
	check_admin_referer( 'store_kit_save_settings_' . $tab, 'store_kit_settings_nonce' );
	$data = isset( $_POST['data'] ) && is_array( $_POST['data'] ) ? wp_unslash( $_POST['data'] ) : [];
	$errors = [];
	if ( ! isset( $_POST['form_complete'] ) || '1' !== $_POST['form_complete'] ) {
		$errors[] = 'Biểu mẫu chưa được gửi đầy đủ. Không có dữ liệu nào được thay đổi. Giảm số dòng hoặc liên hệ quản trị để tăng giới hạn max_input_vars.';
	}
	$values = 'videos' === $tab ? validate_videos( $data, $errors ) : validate_tab( $tab, $data, $errors );
	if ( $errors ) {
		set_transient( 'store_kit_settings_feedback_' . get_current_user_id(), [ 'tab' => $tab, 'errors' => array_values( array_unique( $errors ) ), 'data' => $data ], 10 * MINUTE_IN_SECONDS );
		wp_safe_redirect( add_query_arg( 'result', 'error', tab_url( $tab ) ) );
		exit;
	}
	if ( 'videos' === $tab ) {
		update_option( CHANNEL, $values[0], false );
		update_option( COVERS, $values[1], false );
		update_option( NO_EMBED, $values[2], false );
	} else {
		update_option( Settings\OPTION, array_replace( Settings\all(), $values ), false );
	}
	do_action( 'store_kit_settings_saved', $tab, $values );
	delete_transient( 'store_kit_settings_feedback_' . get_current_user_id() );
	do_action( 'litespeed_purge_all' );
	wp_safe_redirect( add_query_arg( 'result', 'saved', tab_url( $tab ) ) );
	exit;
}

function section_names(): array {
	return [ 'van-de' => 'Nhóm vấn đề', 'san-pham' => 'Sản phẩm nổi bật', 'video' => 'Video hướng dẫn', 'kien-thuc' => 'Thông tin hữu ích', 'dat-hang' => 'Cách đặt hàng' ];
}

function policy_names(): array {
	return [ 'privacy' => 'Bảo mật thông tin', 'shipping' => 'Vận chuyển', 'returns' => 'Đổi trả', 'payment' => 'Thanh toán', 'warranty' => 'Bảo hành', 'terms' => 'Điều khoản', 'rules' => 'Quy chế hoạt động', 'complaints' => 'Giải quyết khiếu nại' ];
}

function products(): array {
	static $products;
	if ( null === $products ) {
		$products = get_posts( [ 'post_type' => 'product', 'post_status' => 'publish', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC', 'suppress_filters' => false ] );
	}
	return $products;
}

function categories(): array {
	static $categories;
	if ( null === $categories ) {
		$categories = get_terms( [ 'taxonomy' => 'product_cat', 'hide_empty' => false, 'orderby' => 'name', 'order' => 'ASC' ] );
		if ( is_wp_error( $categories ) ) {
			$categories = [];
		}
	}
	return $categories;
}

/** Safe display for failed forms, including deliberately malformed requests. */
function value( array $data, string $key, string $default = '' ): string {
	return isset( $data[ $key ] ) && is_scalar( $data[ $key ] ) ? (string) $data[ $key ] : $default;
}

function field( string $name, string $label, $value, string $type = 'text', string $help = '' ): void {
	$id = 'sk-' . sanitize_html_class( str_replace( [ '[', ']' ], '-', $name ) );
	$value = is_scalar( $value ) ? (string) $value : '';
	echo '<div class="sk-field"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label>';
	if ( 'textarea' === $type ) {
		echo '<textarea class="large-text" rows="3" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">' . esc_textarea( $value ) . '</textarea>';
	} else {
		echo '<input class="regular-text" type="' . esc_attr( $type ) . '" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '"' . ( 'number' === $type ? ' min="1" step="1"' : '' ) . '>';
	}
	if ( '' !== $help ) {
		echo '<p class="description">' . esc_html( $help ) . '</p>';
	}
	echo '</div>';
}

function checkbox( string $name, string $label, $value ): void {
	echo '<label class="sk-check"><input type="checkbox" name="' . esc_attr( $name ) . '" value="1" ' . checked( ! empty( $value ), true, false ) . '> ' . esc_html( $label ) . '</label>';
}

function select_field( string $name, string $label, string $selected, array $choices ): void {
	$id = 'sk-' . sanitize_html_class( str_replace( [ '[', ']' ], '-', $name ) );
	echo '<div class="sk-field"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label><select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">';
	if ( '' !== $selected && ! array_key_exists( $selected, $choices ) ) {
		echo '<option value="' . esc_attr( $selected ) . '" selected>Không còn khả dụng — chọn lại hoặc xóa dòng này</option>';
	}
	foreach ( $choices as $key => $title ) {
		echo '<option value="' . esc_attr( $key ) . '" ' . selected( $selected, (string) $key, false ) . '>' . esc_html( $title ) . '</option>';
	}
	echo '</select></div>';
}

function product_choices(): array {
	$choices = [ '0' => '— Chọn sản phẩm —' ];
	foreach ( products() as $product ) {
		$choices[ $product->ID ] = $product->post_title;
	}
	return $choices;
}

function category_choices(): array {
	$choices = [ '0' => '— Chọn danh mục —' ];
	foreach ( categories() as $category ) {
		$choices[ $category->term_id ] = $category->name;
	}
	return $choices;
}

function tie_choices(): array {
	$choices = [ '' => 'Không có liên kết', 'zalo' => 'Tư vấn qua Zalo' ];
	foreach ( products() as $product ) {
		$choices[ 'product:' . $product->ID ] = 'Sản phẩm: ' . $product->post_title;
	}
	foreach ( categories() as $category ) {
		$choices[ 'cat:' . $category->term_id ] = 'Danh mục: ' . $category->name;
	}
	return $choices;
}

function row_actions( string $title ): void {
	echo '<div class="sk-row-bar"><span class="sk-drag dashicons dashicons-move" aria-hidden="true" title="Kéo để đổi thứ tự"></span><strong class="sk-row-title">' . esc_html( $title ) . '</strong><div class="sk-row-actions"><button type="button" class="button sk-up" aria-label="Chuyển dòng lên">Lên</button> <button type="button" class="button sk-down" aria-label="Chuyển dòng xuống">Xuống</button> <button type="button" class="button-link-delete sk-remove">Xóa</button></div></div>';
}

function repeater_row( string $kind, $index, array $row = [], bool $new = false ): void {
	$name = 'data[' . $kind . '][' . $index . ']';
	$title = value( $row, 'title', 'Dòng mới' );
	echo '<div class="sk-row" data-kind="' . esc_attr( $kind ) . '">';
	row_actions( '' !== $title ? $title : 'Dòng mới' );
	if ( in_array( $kind, [ 'steps', 'problems', 'featured_products' ], true ) ) {
		echo '<input type="hidden" name="' . esc_attr( $name . '[existing]' ) . '" value="' . ( $new ? '0' : esc_attr( value( $row, 'existing', '1' ) ) ) . '">';
	}
	if ( 'featured_products' === $kind ) {
		select_field( $name . '[product_id]', 'Sản phẩm', value( $row, 'product_id', '0' ), product_choices() );
	} elseif ( 'steps' === $kind || 'problems' === $kind ) {
		field( $name . '[title]', 'steps' === $kind ? 'Tiêu đề bước' : 'Tên nhóm vấn đề', value( $row, 'title' ) );
		field( $name . '[description]', 'Mô tả', value( $row, 'description' ), 'textarea' );
		if ( 'problems' === $kind ) {
			select_field( $name . '[category_id]', 'Danh mục sản phẩm', value( $row, 'category_id', '0' ), category_choices() );
			field( $name . '[chip]', 'Nhãn ngắn', value( $row, 'chip' ), 'text', 'Ví dụ: Phụ kiện, Quà tặng. Có thể để trống.' );
			checkbox( $name . '[enabled]', 'Hiển thị nhóm vấn đề này', $row['enabled'] ?? true );
		}
	} elseif ( 'videos' === $kind ) {
		$id = value( $row, 'original_id', value( $row, 'id' ) );
		$covers = get_option( COVERS, [] );
		$external = get_option( NO_EMBED, [] );
		$cover = value( $row, 'cover', (string) ( is_array( $covers ) ? ( $covers[ $id ] ?? 0 ) : 0 ) );
		$poster = ctype_digit( $cover ) && (int) $cover ? wp_get_attachment_image_url( (int) $cover, 'medium' ) : '';
		echo '<input type="hidden" name="' . esc_attr( $name . '[original_id]' ) . '" value="' . esc_attr( $id ) . '">';
		echo '<details class="sk-video-details"' . ( $new || 0 === $index ? ' open' : '' ) . '><summary>Chỉnh video, ảnh bìa và vị trí hiển thị</summary>';
		echo '<div class="sk-video-grid"><div class="sk-cover"><img class="sk-cover-preview" src="' . esc_url( $poster ?: '' ) . '" alt="Ảnh bìa video"' . ( $poster ? '' : ' hidden' ) . '><span class="sk-cover-empty"' . ( $poster ? ' hidden' : '' ) . '>Chưa có ảnh bìa</span><input class="sk-cover-id" type="hidden" name="' . esc_attr( $name . '[cover]' ) . '" value="' . esc_attr( $cover ) . '"><p><button type="button" class="button sk-media">Chọn ảnh bìa</button> <button type="button" class="button-link sk-clear-media">Bỏ ảnh</button></p></div><div>';
		field( $name . '[url]', 'Liên kết TikTok đầy đủ', value( $row, 'url' ), 'url', 'Dùng https://www.tiktok.com/@tenkenh/video/…; mở liên kết chia sẻ trong trình duyệt để lấy URL đầy đủ. Không cần nhập ID.' );
		field( $name . '[title]', 'Tiêu đề video', value( $row, 'title' ) );
		field( $name . '[views]', 'Lượt xem hiển thị', value( $row, 'views' ), 'text', 'Chỉ là nhãn nhập thủ công, ví dụ 12,5K. Không tự lấy dữ liệu TikTok.' );
		select_field( $name . '[tie]', 'Liên kết tư vấn dưới video', value( $row, 'tie' ), tie_choices() );
		echo '<div class="sk-checkboxes">';
		checkbox( $name . '[enabled]', 'Bật video', $row['enabled'] ?? true );
		checkbox( $name . '[hero]', 'Hiện ở đầu trang', $row['hero'] ?? false );
		checkbox( $name . '[section]', 'Hiện trong mục video trang chủ', $row['section'] ?? true );
		checkbox( $name . '[external]', 'Mở TikTok bên ngoài, không nhúng', $row['external'] ?? ( is_array( $external ) && in_array( $id, array_map( 'strval', $external ), true ) ) );
		echo '</div>';
		$link = value( $row, 'url' );
		if ( '' !== $link ) {
			echo '<p><a class="sk-preview-link" href="' . esc_url( $link ) . '" target="_blank" rel="noopener noreferrer">Xem video trên TikTok</a></p>';
		}
		echo '</div></div>';
		echo '</details>';
	}
	echo '</div>';
}

function repeater( string $kind, array $rows, string $add_label ): void {
	echo '<div class="sk-repeater" data-repeater="' . esc_attr( $kind ) . '"><div class="sk-rows">';
	foreach ( array_values( $rows ) as $index => $row ) {
		if ( is_array( $row ) ) {
			repeater_row( $kind, $index, $row );
		}
	}
	echo '</div><p class="sk-empty"' . ( $rows ? ' hidden' : '' ) . '>Chưa có dòng nào. Chọn nút bên dưới để thêm.</p><button type="button" class="button sk-add">' . esc_html( $add_label ) . '</button><template class="sk-row-template">';
	repeater_row( $kind, '__INDEX__', [], true );
	echo '</template></div>';
}

function panel_start( string $title, string $description = '' ): void {
	echo '<section class="sk-panel"><h2>' . esc_html( $title ) . '</h2>';
	if ( '' !== $description ) {
		echo '<p class="sk-panel-intro">' . esc_html( $description ) . '</p>';
	}
}

function render_store( array $data ): void {
	panel_start( 'Thông tin cửa hàng', 'Thông tin này được dùng ở chân trang và phần liên hệ. Không thay đổi thông tin thanh toán WooCommerce.' );
	field( 'data[brand]', 'Tên thương hiệu', value( $data, 'brand' ) );
	media_field( 'logo_id', 'Logo đầu trang', $data );
	media_field( 'footer_logo_id', 'Logo chân trang', $data );
	field( 'data[legal_name]', 'Tên pháp lý / chủ thể kinh doanh', value( $data, 'legal_name' ) );
	field( 'data[tax_id]', 'Mã số thuế', value( $data, 'tax_id' ), 'text', 'Giữ nguyên số 0 đầu. Nhập 10 hoặc 12 chữ số, hoặc mã chi nhánh dạng 0123456789-001.' );
	field( 'data[footer_description]', 'Giới thiệu ngắn ở chân trang', value( $data, 'footer_description' ), 'textarea' );
	echo '</section>';
}

function render_contact( array $data ): void {
	panel_start( 'Địa chỉ & tư vấn', 'Hotline giữ định dạng bạn nhập để hiển thị; liên kết gọi điện tự bỏ khoảng trắng và dấu phân cách.' );
	foreach ( [ 'address' => 'Địa chỉ cửa hàng', 'returns_address' => 'Địa chỉ nhận đổi trả (nếu khác)', 'hours' => 'Giờ làm việc', 'phone' => 'Hotline chính', 'phone2' => 'Hotline phụ', 'email' => 'Email' ] as $key => $label ) {
		field( 'data[' . $key . ']', $label, value( $data, $key ), 'email' === $key ? 'email' : ( in_array( $key, [ 'address', 'returns_address' ], true ) ? 'textarea' : 'text' ) );
	}
	echo '</section>';
	panel_start( 'Kênh liên hệ', 'Nhập liên kết đầy đủ; để trống để không hiển thị kênh đó. Hồ sơ TikTok cũng cung cấp tên kênh cho video trên trang sản phẩm.' );
	foreach ( [ 'zalo_url' => 'Zalo', 'facebook_url' => 'Facebook', 'tiktok_url' => 'Hồ sơ TikTok', 'youtube_url' => 'YouTube' ] as $key => $label ) {
		field( 'data[' . $key . ']', $label, value( $data, $key ), 'url', 'tiktok_url' === $key ? 'Ví dụ: https://www.tiktok.com/@tenkenh. URL từng video được giữ riêng, không đổi tài khoản theo hồ sơ này.' : '' );
	}
	echo '</section>';
}

function render_home( array $data ): void {
	panel_start( 'Đầu trang & thông báo' );
	foreach ( [ 'hero_badge' => 'Nhãn đầu trang', 'hero_title' => 'Tiêu đề đầu trang', 'hero_lede' => 'Mô tả đầu trang', 'announce_before' => 'Thông báo trước hotline', 'announce_after' => 'Thông báo sau hotline' ] as $key => $label ) {
		field( 'data[' . $key . ']', $label, value( $data, $key ), 'hero_lede' === $key ? 'textarea' : 'text' );
	}
	echo '</section>';
	panel_start( 'Các mục trên trang chủ', 'Bật / tắt và sửa nội dung từng mục. Bố cục, màu sắc và thứ tự các mục được giữ thống nhất với giao diện hiện tại.' );
	$sections = isset( $data['sections'] ) && is_array( $data['sections'] ) ? $data['sections'] : [];
	foreach ( section_names() as $key => $title ) {
		$section = isset( $sections[ $key ] ) && is_array( $sections[ $key ] ) ? $sections[ $key ] : [];
		$name = 'data[sections][' . $key . ']';
		echo '<details class="sk-section" open><summary>' . esc_html( $title ) . '</summary><div class="sk-section-body">';
		checkbox( $name . '[enabled]', 'Hiển thị mục này', $section['enabled'] ?? false );
		foreach ( [ 'eyebrow' => 'Dòng nhãn', 'title' => 'Tiêu đề', 'lede' => 'Mô tả', 'link_text' => 'Chữ trên liên kết', 'link_url' => 'Địa chỉ liên kết' ] as $field => $label ) {
			field( $name . '[' . $field . ']', $label, value( $section, $field ), 'lede' === $field ? 'textarea' : 'text', 'link_url' === $field ? 'Chấp nhận URL https://…, đường dẫn /… hoặc neo #…; để trống nếu không dùng.' : '' );
		}
		echo '</div></details>';
	}
	field( 'data[video_section_limit]', 'Số video tối đa trong mục video', value( $data, 'video_section_limit', '8' ), 'number', 'Từ 1 đến 50. Video xuất hiện theo thứ tự ở tab Video TikTok, có bật “Hiện trong mục video trang chủ”.' );
	field( 'data[posts_count]', 'Số bài viết kiến thức', value( $data, 'posts_count', '5' ), 'number', 'Từ 1 đến 30 bài đã xuất bản.' );
	echo '</section>';
	panel_start( 'Sản phẩm nổi bật', 'Chọn theo tên. Kéo biểu tượng di chuyển hoặc dùng Lên / Xuống để sắp xếp đúng thứ tự hiển thị.' );
	$products = [];
	foreach ( (array) ( $data['featured_products'] ?? [] ) as $product ) {
		$products[] = is_array( $product ) ? $product : [ 'product_id' => $product ];
	}
	repeater( 'featured_products', $products, 'Thêm sản phẩm' );
	echo '</section>';
	panel_start( 'Các bước đặt hàng', 'Kéo để sắp xếp hoặc dùng Lên / Xuống. Để trống toàn bộ một dòng mới để bỏ qua.' );
	repeater( 'steps', isset( $data['steps'] ) && is_array( $data['steps'] ) ? $data['steps'] : [], 'Thêm bước đặt hàng' );
	echo '</section>';
}

function render_problems( array $data ): void {
	panel_start( 'Nhóm sản phẩm theo nhu cầu', 'Chọn danh mục theo tên để khách xem đúng nhóm sản phẩm. Tắt hiển thị sẽ giữ nguyên dòng và nội dung để dùng lại; Xóa sẽ bỏ dòng sau khi lưu.' );
	repeater( 'problems', isset( $data['problems'] ) && is_array( $data['problems'] ) ? $data['problems'] : [], 'Thêm nhóm vấn đề' );
	echo '</section>';
}

function render_videos( array $data ): void {
	panel_start( 'Video TikTok', 'Bật video để xuất hiện trong danh sách video. Hai lựa chọn vị trí trang chủ độc lập với nhau; video đã tắt không xuất hiện ở cả hai vị trí. Kéo để sắp xếp hoặc dùng Lên / Xuống. Không tự thu thập video từ TikTok.' );
	echo '<p class="description">Ảnh bìa và lựa chọn mở bên ngoài dùng chung với video sản phẩm. Chỉ những video có trong biểu mẫu này được cập nhật; ảnh và cài đặt của các video sản phẩm khác được giữ nguyên. Xóa một dòng không xóa ảnh trong Thư viện hoặc cài đặt video sản phẩm.</p>';
	repeater( 'videos', isset( $data['videos'] ) && is_array( $data['videos'] ) ? $data['videos'] : [], 'Thêm video TikTok' );
	echo '</section>';
}

function render_legal( array $data ): void {
	panel_start( 'Các trang chính sách', 'Chỉ trang đã xuất bản mới có thể chọn và xuất hiện ở chân trang. Lưu tại đây không tự xuất bản, viết nội dung trang, hoặc đổi trang điều khoản / quyền riêng tư trong WooCommerce và WordPress.' );
	$pages = get_posts( [ 'post_type' => 'page', 'post_status' => 'publish', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC' ] );
	$choices = [ '0' => '— Không hiển thị liên kết —' ];
	foreach ( $pages as $page ) {
		if ( \StoreKit\SiteContent\public_policy( $page->ID ) ) {
			$choices[ $page->ID ] = $page->post_title;
		}
	}
	$policies = isset( $data['policy_pages'] ) && is_array( $data['policy_pages'] ) ? $data['policy_pages'] : [];
	foreach ( policy_names() as $key => $label ) {
		select_field( 'data[policy_pages][' . $key . ']', $label, value( $policies, $key, '0' ), $choices );
		$id = value( $policies, $key, '0' );
		if ( ctype_digit( $id ) && (int) $id && current_user_can( 'edit_post', (int) $id ) ) {
			echo '<p class="sk-edit-link"><a href="' . esc_url( get_edit_post_link( (int) $id, 'raw' ) ) . '">Sửa nội dung ' . esc_html( $label ) . '</a></p>';
		}
	}
	echo '<p><a class="button" href="' . esc_url( admin_url( 'edit.php?post_type=page' ) ) . '">Quản lý tất cả trang chính sách</a></p></section>';
	panel_start( 'Thông báo Bộ Công Thương', 'Chỉ hiển thị liên kết khi đã được xác nhận và có URL tra cứu hợp lệ trên online.gov.vn. Không tạo dấu chứng nhận hoặc hiển thị thông báo đã đăng ký khi chưa được phê duyệt.' );
	field( 'data[bct_url]', 'Liên kết tra cứu chính thức', value( $data, 'bct_url' ), 'url', 'Dùng hồ sơ HTTPS trên online.gov.vn: /Home/WebDetails/12345 hoặc /nen-tang/UUID. Để trống khi chưa được xác nhận.' );
	checkbox( 'data[bct_confirmed]', 'Tôi xác nhận website đã được Bộ Công Thương xác nhận, và liên kết trên là hồ sơ của website này.', $data['bct_confirmed'] ?? false );
	echo '</section>';
}

function render_operations(): void {
	panel_start( 'Quản lý bằng công cụ có sẵn', 'Sản phẩm, đơn hàng, thanh toán, vận chuyển và nội dung trang tiếp tục được quản lý tại WordPress / WooCommerce. Không có dữ liệu vận hành nào bị ghi đè khi lưu các tab khác.' );
	$links = [
		[ 'Sản phẩm', 'Thêm, sửa giá, tồn kho, hình ảnh và video từng sản phẩm.', 'edit.php?post_type=product' ],
		[ 'Danh mục sản phẩm', 'Đổi tên và sắp xếp danh mục dùng cho nhóm vấn đề.', 'edit-tags.php?taxonomy=product_cat&post_type=product' ],
		[ 'Đơn hàng', 'Xử lý đơn hàng và xem thông tin khách hàng.', function_exists( 'wc_get_container' ) && class_exists( '\\Automattic\\WooCommerce\\Utilities\\OrderUtil' ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ? 'admin.php?page=wc-orders' : 'edit.php?post_type=shop_order' ],
		[ 'Thanh toán', 'Cấu hình phương thức nhận tiền trong WooCommerce.', 'admin.php?page=wc-settings&tab=checkout' ],
		[ 'Vận chuyển', 'Cấu hình khu vực và phí giao hàng.', 'admin.php?page=wc-settings&tab=shipping' ],
		[ 'Trang & chính sách', 'Viết nội dung, xem trước và xuất bản trang.', 'edit.php?post_type=page' ],
		[ 'Bài viết kiến thức', 'Đăng bài kiến thức xuất hiện trên trang chủ.', 'edit.php' ],
	];
	echo '<dl class="sk-operation-links">';
	foreach ( $links as [ $label, $description, $path ] ) {
		echo '<div><dt><a href="' . esc_url( admin_url( $path ) ) . '">' . esc_html( $label ) . '</a></dt><dd>' . esc_html( $description ) . '</dd></div>';
	}
	echo '</dl></section>';
}

function render(): void {
	if ( ! current_user_can( CAPABILITY ) ) {
		wp_die( 'Bạn không có quyền xem cài đặt Store Kit.', '', [ 'response' => 403 ] );
	}
	$tab = isset( $_GET['tab'] ) && is_string( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'store';
	if ( ! isset( tabs()[ $tab ] ) ) {
		$tab = 'store';
	}
	$data = Settings\all();
	if ( 'videos' === $tab ) {
		$data = [ 'videos' => channel_rows() ];
	}
	$feedback = get_transient( 'store_kit_settings_feedback_' . get_current_user_id() );
	$failed = is_array( $feedback ) && ( $feedback['tab'] ?? '' ) === $tab && isset( $_GET['result'] ) && 'error' === $_GET['result'];
	if ( $failed ) {
		$data = is_array( $feedback['data'] ?? null ) ? $feedback['data'] : $data;
		// An unchecked checkbox is absent from POST, not a request to restore its old value.
		foreach ( [ 'videos' => [ 'enabled', 'hero', 'section', 'external' ], 'problems' => [ 'enabled' ] ] as $list => $flags ) {
			if ( ! isset( $data[ $list ] ) || ! is_array( $data[ $list ] ) ) {
				continue;
			}
			foreach ( $data[ $list ] as &$row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				foreach ( $flags as $flag ) {
					$row[ $flag ] = isset( $row[ $flag ] ) && is_scalar( $row[ $flag ] ) && '1' === (string) $row[ $flag ];
				}
			}
			unset( $row );
		}
	}
	echo '<div class="wrap sk-settings"><h1>Cài đặt Store Kit</h1><p class="sk-subtitle">Thông tin cửa hàng, nội dung trang chủ và các kênh tư vấn — quản lý tại một nơi.</p>';
	if ( $failed ) {
		echo '<div class="notice notice-error" role="alert"><p><strong>Chưa lưu thay đổi. Dữ liệu đang dùng được giữ nguyên.</strong></p><ul>';
		foreach ( (array) ( $feedback['errors'] ?? [] ) as $error ) {
			echo '<li>' . esc_html( $error ) . '</li>';
		}
		echo '</ul><p>Nội dung bạn vừa nhập được giữ lại bên dưới để sửa và lưu lại.</p></div>';
	} elseif ( isset( $_GET['result'] ) && 'saved' === $_GET['result'] ) {
		echo '<div class="notice notice-success is-dismissible" role="status"><p>Đã lưu cài đặt ' . esc_html( tabs()[ $tab ] ) . '. Các tab khác được giữ nguyên.</p></div>';
	}
	echo '<nav class="nav-tab-wrapper sk-tabs" aria-label="Các mục cài đặt">';
	foreach ( tabs() as $key => $label ) {
		echo '<a class="nav-tab' . ( $key === $tab ? ' nav-tab-active' : '' ) . '" href="' . esc_url( tab_url( $key ) ) . '"' . ( $key === $tab ? ' aria-current="page"' : '' ) . '>' . esc_html( $label ) . '</a>';
	}
	echo '</nav>';
	$has_form = (bool) apply_filters( 'store_kit_settings_tab_has_form', 'operations' !== $tab, $tab );
	if ( $has_form ) {
		echo '<form class="sk-settings-form" data-unsaved="' . ( $failed ? '1' : '0' ) . '" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="store_kit_save_settings"><input type="hidden" name="tab" value="' . esc_attr( $tab ) . '">';
		wp_nonce_field( 'store_kit_save_settings_' . $tab, 'store_kit_settings_nonce' );
	}
	if ( 'operations' === $tab ) {
		render_operations();
	} elseif ( in_array( $tab, [ 'store', 'contact', 'home', 'problems', 'videos', 'legal' ], true ) ) {
		$render = __NAMESPACE__ . '\\render_' . $tab;
		$render( $data );
	} else {
		do_action( 'store_kit_render_settings_tab', $tab, $data );
	}
	if ( $has_form ) {
		echo '<div class="sk-save-bar"><div><strong>' . esc_html( tabs()[ $tab ] ) . '</strong><span>Chỉ lưu thay đổi trong tab này.</span></div><button type="submit" class="button button-primary button-large">Lưu ' . esc_html( tabs()[ $tab ] ) . '</button></div><input type="hidden" name="form_complete" value="1"></form>';
	}
	echo '<p class="screen-reader-text sk-live" aria-live="polite"></p></div>';
}

/** Media picker reuses the same native image control as video posters. */
function media_field( string $key, string $label, array $data ): void {
	$id = absint( value( $data, $key, '0' ) );
	$preview = $id ? wp_get_attachment_image_url( $id, 'medium' ) : '';
	echo '<div class="sk-field sk-cover"><strong>' . esc_html( $label ) . '</strong><p class="description">Không chọn ảnh: hiển thị tên thương hiệu.</p><img class="sk-cover-preview" src="' . esc_url( $preview ?: '' ) . '" alt=""' . ( $preview ? '' : ' hidden' ) . '><span class="sk-cover-empty"' . ( $preview ? ' hidden' : '' ) . '>Dùng tên thương hiệu</span><input class="sk-cover-id" type="hidden" name="data[' . esc_attr( $key ) . ']" value="' . esc_attr( (string) $id ) . '"><p><button type="button" class="button sk-media">Chọn logo</button><button type="button" class="button-link sk-clear-media">Bỏ ảnh</button></p></div>';
}
