<?php
/** Safe, identity-free foundation provisioning and portable layout transfer. */
namespace StoreKit\Blueprints;

use StoreKit\Settings;
use StoreKit\Admin;
use StoreKit\Videos;

defined( 'ABSPATH' ) || exit;

const STATE = 'store_kit_blueprint';
const OWNED = '_store_kit_owned';
const DEMO = '_store_kit_demo';
const LOCK = 'store_kit_blueprint_lock';
const MAX_BYTES = 1048576;

add_filter( 'store_kit_settings_tabs', __NAMESPACE__ . '\\tabs' );
add_filter( 'store_kit_settings_tab_has_form', __NAMESPACE__ . '\\has_form', 10, 2 );
add_action( 'store_kit_render_settings_tab', __NAMESPACE__ . '\\render', 10, 2 );
add_action( 'admin_post_store_kit_blueprint', __NAMESPACE__ . '\\admin_action' );

function tabs( array $tabs ): array {
	$tabs['setup'] = 'Thiết lập & chuyển giao';
	return $tabs;
}

function has_form( bool $has_form, string $tab ): bool {
	return 'setup' === $tab ? false : $has_form;
}

function fail( string $message ): void {
	throw new \RuntimeException( $message );
}

function cli(): bool {
	return defined( 'WP_CLI' ) && WP_CLI;
}

function require_dependencies(): void {
	if ( ! class_exists( 'WooCommerce' ) || ! function_exists( 'wc_get_product' ) ) {
		fail( 'Activate WooCommerce before using Store Kit setup or layout transfer.' );
	}
}

/** Reject unknown fields, including identity/safety options, rather than silently dropping them. */
function keys( array $value, array $allowed, string $label ): void {
	$unknown = array_diff( array_keys( $value ), $allowed );
	if ( $unknown ) {
		fail( $label . ': unknown fields: ' . implode( ', ', $unknown ) );
	}
}

function object_value( $value, string $label ): array {
	if ( ! is_array( $value ) || ( $value && array_is_list( $value ) ) ) {
		fail( $label . ' must be a JSON object.' );
	}
	return $value;
}

function list_value( $value, string $label, int $max = 200 ): array {
	if ( ! is_array( $value ) || ! array_is_list( $value ) || count( $value ) > $max ) {
		fail( $label . ' must be a list with at most ' . $max . ' entries.' );
	}
	return $value;
}

function plain( $value, string $label, int $max = 10000 ): string {
	if ( ! is_string( $value ) || strlen( $value ) > $max || sanitize_textarea_field( $value ) !== $value ) {
		fail( $label . ': expected plain text, at most ' . $max . ' bytes, without HTML.' );
	}
	return $value;
}

function boolean( $value, string $label ): bool {
	if ( ! is_bool( $value ) ) {
		fail( $label . ' must be true or false.' );
	}
	return $value;
}

function slug( $value, string $label ): string {
	if ( ! is_string( $value ) || '' === $value || strlen( $value ) > 200 || sanitize_title( $value ) !== $value ) {
		fail( $label . ': expected a nonempty WordPress slug, not an ID or URL.' );
	}
	return $value;
}

function relative_link( $value, string $label ): string {
	$value = plain( $value, $label, 1000 );
	if ( '' !== $value && ( ! preg_match( '~^/(?!/)[^\\\\\s<>]*$~D', $value ) || false !== strpos( $value, '..' ) || false !== strpos( $value, ':' ) ) ) {
		fail( $label . ': only a site-relative path beginning with / is portable.' );
	}
	return $value;
}

function decode( string $json ): array {
	if ( strlen( $json ) > MAX_BYTES ) {
		fail( 'Configuration exceeds the 1 MiB limit.' );
	}
	try {
		$value = json_decode( $json, true, 32, JSON_THROW_ON_ERROR );
	} catch ( \JsonException $error ) {
		fail( 'Invalid JSON: ' . $error->getMessage() );
	}
	return object_value( $value, 'Configuration' );
}

function read_json( string $file ): array {
	if ( ! is_file( $file ) || ! is_readable( $file ) || filesize( $file ) > MAX_BYTES ) {
		fail( 'Choose a readable JSON file no larger than 1 MiB.' );
	}
	$json = file_get_contents( $file );
	if ( false === $json ) {
		fail( 'Unable to read configuration.' );
	}
	return decode( $json );
}

function encode( array $value ): string {
	$json = wp_json_encode( $value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	if ( false === $json ) {
		fail( 'Unable to encode portable configuration.' );
	}
	return $json . "\n";
}

function home_keys(): array {
	return [ 'hero_title', 'hero_lede', 'hero_badge', 'announce_before', 'announce_after', 'video_section_limit', 'posts_count', 'sections', 'steps', 'featured_products' ];
}

function policy_slugs(): array {
	return [ 'privacy' => 'privacy-policy', 'shipping' => 'shipping-policy', 'returns' => 'returns-policy', 'payment' => 'payment-policy', 'warranty' => 'warranty-policy', 'terms' => 'terms-of-sale', 'rules' => 'store-rules', 'complaints' => 'complaints-policy' ];
}

function design( $value ): array {
	$value = object_value( $value, 'design' );
	if ( ! function_exists( '\\StoreKitTheme\\validate' ) ) {
		fail( 'Activate the Store Kit child theme before applying a layout or preset.' );
	}
	$allowed = [ 'primary', 'primary_hover', 'heading', 'text', 'muted', 'tile', 'soft', 'deep', 'border', 'accent', 'sale', 'font_heading', 'font_body', 'radius_image', 'radius_button', 'content_width', 'section_desktop', 'section_mobile', 'grid_gap', 'base_font' ];
	keys( $value, $allowed, 'design' );
	if ( count( $value ) !== count( $allowed ) ) {
		fail( 'design must contain all supported design tokens.' );
	}
	$result = \StoreKitTheme\validate( $value );
	if ( ! empty( $result['errors'] ) ) {
		fail( 'Design: ' . implode( ' ', $result['errors'] ) );
	}
	return $result['values'];
}

/** Validate the portable form, then reuse the same sanitizer as the native Home tab. */
function validate_home( $value ): array {
	$value = object_value( $value, 'home' );
	keys( $value, home_keys(), 'home' );
	foreach ( home_keys() as $key ) {
		if ( ! array_key_exists( $key, $value ) ) {
			fail( 'home.' . $key . ' is required.' );
		}
	}
	$data = $value;
	foreach ( [ 'hero_title', 'hero_lede', 'hero_badge', 'announce_before', 'announce_after' ] as $key ) {
		plain( $value[ $key ], 'home.' . $key );
	}
	foreach ( [ 'video_section_limit' => 50, 'posts_count' => 30 ] as $key => $max ) {
		if ( ! is_int( $value[ $key ] ) || $value[ $key ] < 1 || $value[ $key ] > $max ) {
			fail( 'home.' . $key . ': out-of-range integer.' );
		}
		$data[ $key ] = (string) $value[ $key ];
	}
	$sections = object_value( $value['sections'], 'home.sections' );
	$section_keys = [ 'van-de', 'san-pham', 'video', 'kien-thuc', 'dat-hang' ];
	keys( $sections, $section_keys, 'home.sections' );
	foreach ( $section_keys as $key ) {
		$section = object_value( $sections[ $key ] ?? null, 'section ' . $key );
		keys( $section, [ 'enabled', 'title', 'lede', 'eyebrow', 'link_text', 'link_url' ], 'section ' . $key );
		foreach ( [ 'title', 'lede', 'eyebrow', 'link_text' ] as $field ) {
			plain( $section[ $field ] ?? null, 'section ' . $key . '.' . $field );
		}
		relative_link( $section['link_url'] ?? null, 'section ' . $key . '.link_url' );
		boolean( $section['enabled'] ?? null, 'section ' . $key . '.enabled' );
		$data['sections'][ $key ]['enabled'] = $section['enabled'] ? '1' : null;
	}
	foreach ( list_value( $value['steps'], 'home.steps', 20 ) as $index => $row ) {
		$row = object_value( $row, 'step' );
		keys( $row, [ 'title', 'description' ], 'step' );
		plain( $row['title'] ?? null, 'step title' );
		plain( $row['description'] ?? null, 'step description' );
	}
	$data['featured_products'] = [];
	$seen = [];
	foreach ( list_value( $value['featured_products'], 'home.featured_products', 100 ) as $sku ) {
		$sku = plain( $sku, 'Product SKU', 200 );
		$id = '' !== $sku ? (int) wc_get_product_id_by_sku( $sku ) : 0;
		$product = $id ? wc_get_product( $id ) : false;
		if ( ! $product || 'publish' !== $product->get_status() || $product->is_type( 'variation' ) || in_array( $sku, $seen, true ) ) {
			fail( 'Unresolved, nonpublic, variation, or duplicate featured product SKU: ' . $sku );
		}
		$seen[] = $sku;
		$data['featured_products'][] = [ 'product_id' => (string) $id ];
	}
	$errors = [];
	$result = Admin\validate_tab( 'home', $data, $errors );
	if ( $errors ) {
		fail( implode( ' ', $errors ) );
	}
	return $result;
}

function preset( string $id ): array {
	if ( ! in_array( $id, [ 'neutral', 'pigvet' ], true ) ) {
		fail( 'Preset must be neutral or pigvet.' );
	}
	$value = read_json( dirname( __DIR__ ) . '/presets/' . $id . '.json' );
	keys( $value, [ 'version', 'id', 'name', 'settings', 'design' ], 'Preset' );
	if ( 1 !== ( $value['version'] ?? null ) || $id !== ( $value['id'] ?? null ) ) {
		fail( 'Unsupported preset version or identity.' );
	}
	plain( $value['name'] ?? null, 'Preset name', 200 );
	$settings = object_value( $value['settings'] ?? null, 'Preset settings' );
	keys( $settings, array_diff( home_keys(), [ 'featured_products' ] ), 'Preset settings' );
	$value['settings'] = validate_home( $settings + [ 'featured_products' => [] ] );
	$value['design'] = design( $value['design'] ?? null );
	return $value;
}

function lock(): void {
	if ( ! add_option( LOCK, time(), '', false ) ) {
		fail( 'Another Store Kit write operation is running. If a previous process crashed, an administrator must inspect and remove the store_kit_blueprint_lock option before retrying.' );
	}
}

function unlock(): void {
	delete_option( LOCK );
}

function state(): array {
	$value = get_option( STATE, [] );
	return is_array( $value ) ? $value : [];
}

function assert_fresh(): void {
	if ( state() ) {
		return;
	}
	$products = get_posts( [ 'post_type' => [ 'product', 'product_variation' ], 'post_status' => array_keys( get_post_stati() ), 'fields' => 'ids', 'numberposts' => 1 ] );
	$orders = wc_get_orders( [ 'limit' => 1, 'return' => 'ids', 'status' => array_merge( array_keys( wc_get_order_statuses() ), [ 'trash', 'auto-draft', 'wc-checkout-draft' ] ) ] );
	$customers = get_users( [ 'role__in' => [ 'customer' ], 'number' => 1, 'fields' => 'ID' ] );
	if ( $products || $orders || $customers || false !== get_option( Settings\OPTION, false ) ) {
		fail( 'Setup refused: this is an unowned existing store (products, orders, customers, or Store Kit settings exist). Back it up and use explicit layout import instead. No store data has been changed.' );
	}
}

function foundation_pages(): array {
	return [
		'home' => [ 'Trang chủ', '[store_kit_home]' ],
		'about' => [ 'Giới thiệu & liên hệ', '[store_kit_contact]' ],
		'videos' => [ 'Video', '[store_kit_videos limit="0" class="sk-reels--page"]' ],
		'knowledge' => [ 'Kiến thức', '[store_kit_posts]' ],
		'shop' => [ 'Cửa hàng', '' ],
		'cart' => [ 'Giỏ hàng', '[woocommerce_cart]' ],
		'checkout' => [ 'Thanh toán', '[woocommerce_checkout]' ],
		'my-account' => [ 'Tài khoản', '[woocommerce_my_account]' ],
	];
}

function policy_titles(): array {
	return [ 'privacy' => 'Chính sách bảo mật', 'shipping' => 'Chính sách giao hàng', 'returns' => 'Chính sách đổi trả', 'payment' => 'Chính sách thanh toán', 'warranty' => 'Chính sách bảo hành', 'terms' => 'Điều khoản mua bán', 'rules' => 'Quy định cửa hàng', 'complaints' => 'Giải quyết khiếu nại' ];
}

/** Only untouched core installation seeds, with their fixed IDs, may leave view. */
function retire_core_seeds(): void {
	if ( is_multisite() ) {
		return; // Network administrators can customize the installation seed.
	}
	$hello = "<!-- wp:paragraph -->\n<p>" . __( 'Welcome to WordPress. This is your first post. Edit or delete it, then start writing!' ) . "</p>\n<!-- /wp:paragraph -->";
	$sample = "<!-- wp:paragraph -->\n<p>" . __( "This is an example page. It's different from a blog post because it will stay in one place and will show up in your site navigation (in most themes). Most people start with an About page that introduces them to potential site visitors. It might say something like this:" ) . "</p>\n<!-- /wp:paragraph -->\n\n";
	$sample .= "<!-- wp:quote -->\n<blockquote class=\"wp-block-quote\">\n<!-- wp:paragraph -->\n<p>" . __( "Hi there! I'm a bike messenger by day, aspiring actor by night, and this is my website. I live in Los Angeles, have a great dog named Jack, and I like pi&#241;a coladas. (And gettin' caught in the rain.)" ) . "</p>\n<!-- /wp:paragraph -->\n</blockquote>\n<!-- /wp:quote -->\n\n";
	$sample .= "<!-- wp:paragraph -->\n<p>" . __( '...or something like this:' ) . "</p>\n<!-- /wp:paragraph -->\n\n";
	$sample .= "<!-- wp:quote -->\n<blockquote class=\"wp-block-quote\">\n<!-- wp:paragraph -->\n<p>" . __( 'The XYZ Doohickey Company was founded in 1971, and has been providing quality doohickeys to the public ever since. Located in Gotham City, XYZ employs over 2,000 people and does all kinds of awesome things for the Gotham community.' ) . "</p>\n<!-- /wp:paragraph -->\n</blockquote>\n<!-- /wp:quote -->\n\n";
	$sample .= "<!-- wp:paragraph -->\n<p>" . sprintf( __( 'As a new WordPress user, you should go to <a href="%s">your dashboard</a> to delete this page and create new pages for your content. Have fun!' ), admin_url() ) . "</p>\n<!-- /wp:paragraph -->";
	$seeds = [
		1 => [ 'type' => 'post', 'title' => __( 'Hello world!' ), 'slug' => sanitize_title( _x( 'hello-world', 'Default post slug' ) ), 'content' => $hello ],
		2 => [ 'type' => 'page', 'title' => __( 'Sample Page' ), 'slug' => __( 'sample-page' ), 'content' => $sample ],
	];
	foreach ( $seeds as $id => $seed ) {
		$post = get_post( $id );
		if ( ! $post || $post->post_type !== $seed['type'] || 'publish' !== $post->post_status || $post->post_title !== $seed['title'] || $post->post_name !== $seed['slug'] || $post->post_content !== $seed['content'] || $post->post_modified !== $post->post_date || '' !== $post->post_excerpt || '' !== $post->post_password || wp_get_post_revisions( $id ) ) {
			continue;
		}
		if ( in_array( $id, [ (int) get_option( 'page_on_front' ), (int) get_option( 'page_for_posts' ), (int) get_option( 'wp_page_for_privacy_policy' ) ], true ) ) {
			continue;
		}
		wp_trash_post( $id ); // Recoverable; never remove merchant content permanently.
	}
}

function policy_content( string $key ): string {
	$guidance = [
		'privacy' => 'Ghi rõ đơn vị chịu trách nhiệm và kênh liên hệ; dữ liệu thực sự thu thập, mục đích, căn cứ xử lý, bên nhận dữ liệu, thời hạn lưu trữ, bảo mật và quyền của khách hàng. Đối chiếu với thanh toán, phân tích, cookie và dịch vụ video đang sử dụng; mô tả cách gửi yêu cầu về dữ liệu cá nhân.',
		'shipping' => 'Điền khu vực giao hàng, đơn vị vận chuyển, thời gian chuẩn bị và dự kiến nhận hàng, biểu phí và cách tính, theo dõi đơn, giao không thành công, kiểm hàng và xử lý hư hỏng. Chỉ công bố dịch vụ và cam kết mà cửa hàng thực sự cung cấp.',
		'returns' => 'Xác định thời hạn đổi trả hoặc hủy đơn, điều kiện hàng hóa, ngoại lệ phù hợp pháp luật, bên chịu phí vận chuyển, địa chỉ nhận trả thực tế, quy trình kiểm tra, phương thức và thời gian hoàn tiền; hướng dẫn riêng cho hàng sai hoặc lỗi.',
		'payment' => 'Liệt kê phương thức thanh toán đã kích hoạt và kiểm tra, đơn vị tiền tệ, tổng giá, thuế và phí áp dụng, thời điểm thanh toán, xác nhận, giao dịch lỗi và hoàn tiền. Chủ cửa hàng phải kiểm tra mọi thông tin tài khoản trước khi công bố.',
		'warranty' => 'Nêu sản phẩm thực sự được bảo hành, đơn vị chịu trách nhiệm, thời hạn, phạm vi, ngoại lệ hợp pháp, chứng từ, quy trình tiếp nhận và chi phí. Không tự khẳng định bảo hành, chất lượng hoặc chứng nhận chưa được xác minh.',
		'terms' => 'Điền thông tin người bán thực tế, quy trình đặt và chấp nhận đơn, thông tin hàng hóa và tồn kho, giá/thuế, thanh toán, giao nhận, hủy đơn, quyền người tiêu dùng và giải quyết tranh chấp. Rà soát trách nhiệm và pháp luật áp dụng với chuyên gia phù hợp.',
		'rules' => 'Mô tả cách sử dụng tài khoản và website, quyền sở hữu nội dung, điều kiện mua hàng khi có, hành vi bị cấm, hạn chế theo từng sản phẩm, bảo mật tài khoản và cách thông báo thay đổi. Đối chiếu với danh mục thực tế và quy định hiện hành.',
		'complaints' => 'Điền kênh và người tiếp nhận khiếu nại thực tế, thông tin đơn hàng cần cung cấp, thời gian xác nhận và xử lý có thể đáp ứng, quy trình chuyển cấp, bảo mật hồ sơ và cơ chế giải quyết tranh chấp phù hợp pháp luật.',
	];
	return '<!-- Store Kit: bản nháp cần chủ cửa hàng rà soát, không phải chính sách đã được phê duyệt. -->' . "\n<h2>BẢN NHÁP — CHƯA PHẢI CHÍNH SÁCH CÔNG BỐ</h2>\n<p>Nội dung này chỉ hướng dẫn soạn thảo, không phải tư vấn pháp lý, chứng nhận tuân thủ hoặc mô tả hoạt động đã được xác minh. Hãy điền thông tin kinh doanh thật, rà soát chuyên môn khi cần, chủ động xuất bản rồi chọn trang tại Store Kit → Pháp lý & chính sách.</p>\n<p>" . esc_html( $guidance[ $key ] ) . '</p>';
}

/** Resolve stored resources by ID so renamed pages are not recreated on rerun. */
function recorded_page( string $slug ): int {
	$stored = state();
	$id = (int) ( $stored['pages'][ $slug ] ?? 0 );
	$policy_key = array_search( $slug, policy_slugs(), true );
	if ( false !== $policy_key ) {
		$id = (int) ( $stored['policies'][ $policy_key ] ?? 0 );
	}
	return $id && 'page' === get_post_type( $id ) ? $id : 0;
}

/** Existing pages are never edited; a conflicting foundation slug is refused before any writes. */
function preflight_pages(): void {
	$woo = [ 'shop' => 'woocommerce_shop_page_id', 'cart' => 'woocommerce_cart_page_id', 'checkout' => 'woocommerce_checkout_page_id', 'my-account' => 'woocommerce_myaccount_page_id' ];
	foreach ( array_keys( foundation_pages() ) as $slug ) {
		if ( recorded_page( $slug ) ) {
			continue;
		}
		$mapped = isset( $woo[ $slug ] ) ? (int) get_option( $woo[ $slug ], 0 ) : 0;
		$page = get_page_by_path( $slug, OBJECT, 'page' );
		if ( $page && ! get_post_meta( $page->ID, OWNED, true ) && $page->ID !== $mapped ) {
			fail( 'Setup refused: existing unowned page uses the foundation slug ' . $slug . '. Rename that page or use layout import; it will not be overwritten.' );
		}
	}
	foreach ( policy_slugs() as $slug ) {
		if ( recorded_page( $slug ) ) {
			continue;
		}
		$page = get_page_by_path( $slug, OBJECT, 'page' );
		if ( $page && ! get_post_meta( $page->ID, OWNED, true ) && ! ( 'privacy-policy' === $slug && 'draft' === $page->post_status && (int) get_option( 'wp_page_for_privacy_policy', 0 ) === $page->ID ) ) {
			fail( 'Setup refused: unowned policy slug ' . $slug . ' already exists. It will not be replaced.' );
		}
	}
}

function create_page( string $slug, string $title, string $content, string $status ): int {
	$recorded = recorded_page( $slug );
	if ( $recorded ) {
		return $recorded;
	}
	$page = get_page_by_path( $slug, OBJECT, 'page' );
	if ( $page ) {
		return (int) $page->ID;
	}
	$meta = [ OWNED => '1', '_store_kit_review_required' => 'draft' === $status ? '1' : '0' ];
	if ( 'home' === $slug ) {
		$meta['blocksy_post_meta_options'] = [ 'has_hero_section' => 'disabled', 'page_structure_type' => 'type-4', 'content_style_source' => 'custom', 'content_style' => 'wide', 'vertical_spacing_source' => 'custom', 'content_area_spacing' => 'none' ];
	}
	$id = wp_insert_post( [ 'post_type' => 'page', 'post_status' => $status, 'post_name' => $slug, 'post_title' => $title, 'post_content' => $content, 'meta_input' => $meta ], true );
	if ( is_wp_error( $id ) ) {
		fail( 'Cannot create page ' . $slug . ': ' . $id->get_error_message() );
	}
	return (int) $id;
}

function create_category( string $slug, string $title ): int {
	$term = get_term_by( 'slug', $slug, 'product_cat' );
	if ( $term ) {
		return (int) $term->term_id;
	}
	$result = wp_insert_term( $title, 'product_cat', [ 'slug' => $slug ] );
	if ( is_wp_error( $result ) ) {
		fail( $result->get_error_message() );
	}
	$id = (int) $result['term_id'];
	update_term_meta( $id, OWNED, '1' );
	return $id;
}

function demo_specs(): array {
	return [
		[ 'sku' => 'SK-DEMO-001', 'slug' => 'sk-demo-organizer', 'name' => 'DEMO — Khay sắp xếp đồ', 'variable' => false ],
		[ 'sku' => 'SK-DEMO-002', 'slug' => 'sk-demo-travel-pouch', 'name' => 'DEMO — Túi đựng đồ', 'variable' => false ],
		[ 'sku' => 'SK-DEMO-003', 'slug' => 'sk-demo-storage-box', 'name' => 'DEMO — Hộp đựng đồ', 'variable' => false ],
		[ 'sku' => 'SK-DEMO-004', 'slug' => 'sk-demo-accessory', 'name' => 'DEMO — Phụ kiện chọn kích thước', 'variable' => true ],
	];
}

function preflight_demo(): void {
	foreach ( demo_specs() as $spec ) {
		$id = (int) wc_get_product_id_by_sku( $spec['sku'] );
		$post = get_page_by_path( $spec['slug'], OBJECT, 'product' );
		if ( ( $id && ! get_post_meta( $id, DEMO, true ) ) || ( $post && ! get_post_meta( $post->ID, DEMO, true ) ) || ( $id && $post && $id !== (int) $post->ID ) ) {
			fail( 'Demo refused: SKU or slug is already used by a non-kit product: ' . $spec['sku'] );
		}
		foreach ( [ 'small', 'large' ] as $size ) {
			$variation_id = (int) wc_get_product_id_by_sku( $spec['sku'] . '-' . strtoupper( $size ) );
			if ( $spec['variable'] && $variation_id && ! get_post_meta( $variation_id, DEMO, true ) ) {
				fail( 'Demo variation SKU collision: ' . $spec['sku'] . '-' . strtoupper( $size ) );
			}
		}
	}
}

function create_demos( int $category ): array {
	$ids = [];
	foreach ( demo_specs() as $spec ) {
		$id = (int) wc_get_product_id_by_sku( $spec['sku'] );
		$existing = get_page_by_path( $spec['slug'], OBJECT, 'product' );
		if ( $id || $existing ) {
			$ids[] = $id ?: (int) $existing->ID;
			continue; // An edited demo is still the merchant's edit: never reset it.
		}
		$product = $spec['variable'] ? new \WC_Product_Variable() : new \WC_Product_Simple();
		$product->set_name( $spec['name'] );
		$product->set_slug( $spec['slug'] );
		$product->set_sku( $spec['sku'] );
		$product->set_status( 'publish' );
		$product->set_description( 'SẢN PHẨM MINH HỌA — KHÔNG BÁN. Nội dung mẫu không khẳng định công dụng y tế, hiệu quả, chứng nhận, bảo hành hay tình trạng còn hàng. Thay bằng thông tin hàng hóa thực tế đã xác minh hoặc xóa danh mục demo trước khi mở bán.' );
		$product->set_short_description( 'DEMO / KHÔNG BÁN. Chưa chọn ảnh sản phẩm.' );
		$product->set_category_ids( [ $category ] );
		$product->update_meta_data( OWNED, '1' );
		$product->update_meta_data( DEMO, '1' );
		if ( $spec['variable'] ) {
			$attribute = new \WC_Product_Attribute();
			$attribute->set_name( 'Size' );
			$attribute->set_options( [ 'Small', 'Large' ] );
			$attribute->set_visible( true );
			$attribute->set_variation( true );
			$product->set_attributes( [ $attribute ] );
		} else {
			$product->set_regular_price( '10' );
		}
		$id = $product->save();
		$ids[] = $id;
		if ( $spec['variable'] ) {
			foreach ( [ 'small', 'large' ] as $size ) {
				$variation = new \WC_Product_Variation();
				$variation->set_parent_id( $id );
				$variation->set_sku( $spec['sku'] . '-' . strtoupper( $size ) );
				$variation->set_attributes( [ 'size' => ucfirst( $size ) ] );
				$variation->set_regular_price( '10' );
				$variation->set_status( 'publish' );
				$variation->update_meta_data( OWNED, '1' );
				$variation->update_meta_data( DEMO, '1' );
				$variation->save();
			}
			\WC_Product_Variable::sync( $id );
		}
	}
	return $ids;
}

function create_menu( array $pages ): void {
	$stored = state();
	if ( ! empty( $stored['menu_initialized'] ) ) {
		return;
	}
	$locations = get_theme_mod( 'nav_menu_locations', [] );
	$locations = is_array( $locations ) ? $locations : [];
	$available = get_registered_nav_menus();
	if ( 'store-kit-child' === get_stylesheet() ) {
		// These are the free Blocksy desktop/mobile slots, also on the first
		// request that switched themes before Blocksy's hooks can run.
		$available += [ 'menu_1' => 'Header menu', 'menu_mobile' => 'Mobile menu' ];
	}
	$target = [];
	foreach ( [ 'menu_1', 'menu_mobile', 'primary' ] as $location ) {
		if ( isset( $available[ $location ] ) && empty( $locations[ $location ] ) ) {
			$target[] = $location;
		}
	}
	if ( ! $target ) {
		return; // Never replace an existing native menu assignment.
	}
	$menu_id = (int) ( $stored['menu_id'] ?? 0 );
	if ( ! $menu_id || ! wp_get_nav_menu_object( $menu_id ) ) {
		$menu_id = wp_create_nav_menu( 'Điều hướng cửa hàng' );
		if ( is_wp_error( $menu_id ) ) {
			fail( 'Cannot create native navigation: ' . $menu_id->get_error_message() );
		}
		$stored['menu_id'] = (int) $menu_id;
		update_option( STATE, $stored, false );
		update_term_meta( $menu_id, OWNED, '1' );
	}
	$existing = wp_get_nav_menu_items( $menu_id );
	$objects = array_map( static fn( $item ) => (int) $item->object_id, $existing ?: [] );
	foreach ( [ 'home', 'shop', 'videos', 'knowledge', 'about' ] as $slug ) {
		if ( in_array( $pages[ $slug ], $objects, true ) ) {
			continue;
		}
		$item = wp_update_nav_menu_item( $menu_id, 0, [ 'menu-item-object-id' => $pages[ $slug ], 'menu-item-object' => 'page', 'menu-item-type' => 'post_type', 'menu-item-status' => 'publish' ] );
		if ( is_wp_error( $item ) ) {
			fail( $item->get_error_message() );
		}
		update_post_meta( $item, OWNED, '1' );
	}
	foreach ( $target as $location ) {
		$locations[ $location ] = (int) $menu_id;
	}
	set_theme_mod( 'nav_menu_locations', $locations );
	$stored['menu_initialized'] = true;
	update_option( STATE, $stored, false );
}

/** The only destructive operation is the separate, explicitly requested demo purge. */
function setup( string $preset_id = 'neutral', bool $demo = false ): array {
	require_dependencies();
	lock();
	try {
		assert_fresh();
		$stored = state();
		$initial = empty( $stored );
		if ( ! $initial && $preset_id !== ( $stored['preset'] ?? '' ) ) {
			fail( 'This store already owns the ' . ( $stored['preset'] ?? 'unknown' ) . ' preset. Repeated setup preserves merchant edits; use explicit layout import to change layout.' );
		}
		$theme = wp_get_theme( 'store-kit-child' );
		if ( ! $theme->exists() || ! wp_get_theme( 'blocksy' )->exists() ) {
			fail( 'Install the Store Kit child theme and the free Blocksy parent first.' );
		}
		if ( $initial && 'store-kit-child' !== get_stylesheet() && ! cli() && ! current_user_can( 'switch_themes' ) ) {
			fail( 'An administrator with permission to switch themes must run initial setup.' );
		}
		preflight_pages();
		if ( $demo ) {
			preflight_demo();
		}
		$vet = 'store-kit-veterinary/store-kit-veterinary.php';
		if ( 'pigvet' === $preset_id ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
			if ( ! file_exists( WP_PLUGIN_DIR . '/' . $vet ) ) {
				fail( 'The pigvet preset requires the installed Store Kit — Veterinary module. No plugins are downloaded by setup.' );
			}
			if ( ! is_plugin_active( $vet ) && ! cli() && ! current_user_can( 'activate_plugins' ) ) {
				fail( 'An administrator must activate Store Kit — Veterinary before selecting pigvet.' );
			}
		}
		if ( ! in_array( $preset_id, [ 'neutral', 'pigvet' ], true ) ) {
			fail( 'Preset must be neutral or pigvet.' );
		}
		// Theme functions are loaded on the next request after switch_theme; load its
		// entry point once here so CLI and the first admin setup share token validation.
		if ( $initial && 'store-kit-child' !== get_stylesheet() ) {
			switch_theme( 'store-kit-child' );
			if ( ! function_exists( '\\StoreKitTheme\\validate' ) ) {
				require_once $theme->get_stylesheet_directory() . '/functions.php';
			}
		}
		$blueprint = preset( $preset_id );
		if ( $initial ) {
			$stored = [ 'version' => 1, 'preset' => $preset_id, 'complete' => false ];
			update_option( STATE, $stored, false );
			retire_core_seeds();
			if ( '' === (string) get_option( 'permalink_structure', '' ) ) {
				global $wp_rewrite;
				$wp_rewrite->set_permalink_structure( '/%postname%/' );
			}
		}
		if ( 'pigvet' === $preset_id && ! is_plugin_active( $vet ) ) {
			$result = activate_plugin( $vet );
			if ( is_wp_error( $result ) ) {
				fail( 'Cannot activate veterinary module: ' . $result->get_error_message() );
			}
		}
		$pages = [];
		$woo_options = [ 'shop' => 'woocommerce_shop_page_id', 'cart' => 'woocommerce_cart_page_id', 'checkout' => 'woocommerce_checkout_page_id', 'my-account' => 'woocommerce_myaccount_page_id' ];
		foreach ( foundation_pages() as $slug => $spec ) {
			$mapped = isset( $woo_options[ $slug ] ) ? (int) get_option( $woo_options[ $slug ], 0 ) : 0;
			$pages[ $slug ] = $mapped && 'page' === get_post_type( $mapped ) ? $mapped : create_page( $slug, $spec[0], $spec[1], 'publish' );
			if ( isset( $woo_options[ $slug ] ) && ! $mapped ) {
				update_option( $woo_options[ $slug ], $pages[ $slug ] );
			}
		}
		$policies = [];
		foreach ( policy_slugs() as $key => $slug ) {
			$policies[ $key ] = create_page( $slug, policy_titles()[ $key ] . ' — bản nháp cần rà soát', policy_content( $key ), 'draft' );
		}
		$categories = [ 'collection' => create_category( 'collection', 'Bộ sưu tập' ) ];
		if ( 'pigvet' === $preset_id ) {
			foreach ( [ 'animal-care' => 'Vật tư chăm sóc vật nuôi', 'equipment' => 'Dụng cụ', 'thuoc-ke-don' => 'Thuốc kê đơn — không mua trực tuyến' ] as $slug => $title ) {
				$categories[ $slug ] = create_category( $slug, $title );
			}
			if ( $initial || empty( $stored['complete'] ) ) {
				$rules = get_option( 'store_kit_veterinary', [] );
				$rules = is_array( $rules ) ? $rules : [];
				$slugs = isset( $rules['restricted_category_slugs'] ) && is_array( $rules['restricted_category_slugs'] ) ? $rules['restricted_category_slugs'] : [];
				$rules['restricted_category_slugs'] = array_values( array_unique( array_merge( $slugs, [ 'thuoc-ke-don' ] ) ) );
				update_option( 'store_kit_veterinary', $rules, false );
			}
		}
		$demo_ids = $demo ? create_demos( $categories['collection'] ) : [];
		if ( false === get_option( Settings\OPTION, false ) ) {
			$settings = array_replace( Settings\defaults(), $blueprint['settings'] );
			$settings['brand'] = sanitize_text_field( get_bloginfo( 'name' ) );
			$settings['design'] = $blueprint['design'];
			$settings['featured_products'] = $demo_ids;
			$settings['problems'] = [];
			foreach ( $categories as $slug => $id ) {
				if ( 'thuoc-ke-don' === $slug ) {
					continue;
				}
				$term = get_term( $id, 'product_cat' );
				$settings['problems'][] = [ 'title' => $term->name, 'description' => 'Khám phá danh mục và đọc kỹ thông tin của từng sản phẩm.', 'category_id' => $id, 'chip' => $term->name, 'enabled' => true ];
			}
			// Draft legal pages are deliberately not public footer/checkout links.
			$settings['policy_pages'] = array_fill_keys( array_keys( policy_slugs() ), 0 );
			update_option( Settings\OPTION, $settings, false );
		}
		$stored = state();
		if ( empty( $stored['front_initialized'] ) ) {
			if ( ! get_option( 'page_on_front' ) ) {
				update_option( 'page_on_front', $pages['home'] );
				update_option( 'show_on_front', 'page' );
			}
			$stored['front_initialized'] = true;
		}
		$stored['pages'] = $pages;
		$stored['policies'] = $policies;
		update_option( STATE, $stored, false );
		create_menu( $pages );
		$stored = state();
		$stored['complete'] = true;
		update_option( STATE, $stored, false );
		if ( $initial ) {
			// Hard flush also writes Apache rules when WordPress can manage
			// .htaccess. Nginx still requires the host's WordPress try_files rule.
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/misc.php';
			flush_rewrite_rules( true );
		}
		return [ 'Foundation ready. Existing owned content and merchant settings were preserved.', 'Eight policy review pages are available; setup never publishes them. Review, deliberately publish, and select final public policies in Legal.', 'No logo, product photography, business identity, contact, payment, shipping, tax, or legal approval has been invented.', $demo ? 'Kit DEMO products are blocked from purchase. Choose real product images separately; purge demos before launch.' : 'Demo catalog was not requested; add real products and select their images.' ];
	} finally {
		unlock();
	}
}

function product_sku( int $id ): string {
	$product = wc_get_product( $id );
	if ( ! $product || '' === $product->get_sku() ) {
		fail( 'Product ' . $id . ' has no transferable SKU. Assign a unique SKU before exporting.' );
	}
	return $product->get_sku();
}

function category_slug( int $id ): string {
	$term = get_term( $id, 'product_cat' );
	if ( ! $term || is_wp_error( $term ) ) {
		fail( 'A selected product category no longer exists: ' . $id );
	}
	return $term->slug;
}

function portable_tie( string $tie ): array {
	if ( '' === $tie || 'zalo' === $tie ) {
		return [ 'type' => '' === $tie ? 'none' : 'contact', 'value' => '' ];
	}
	if ( preg_match( '/^product:(\d+)$/D', $tie, $match ) ) {
		return [ 'type' => 'product', 'value' => product_sku( (int) $match[1] ) ];
	}
	if ( preg_match( '/^cat:(\d+)$/D', $tie, $match ) ) {
		return [ 'type' => 'category', 'value' => category_slug( (int) $match[1] ) ];
	}
	fail( 'A video contains an invalid link target. Correct it in Videos before exporting.' );
}

function resolve_tie( $value ): string {
	$value = object_value( $value, 'Video target' );
	keys( $value, [ 'type', 'value' ], 'Video target' );
	$type = plain( $value['type'] ?? null, 'Video target type', 20 );
	$reference = plain( $value['value'] ?? null, 'Video target reference', 200 );
	if ( in_array( $type, [ 'none', 'contact' ], true ) && '' === $reference ) {
		return 'contact' === $type ? 'zalo' : '';
	}
	if ( 'product' === $type && '' !== $reference ) {
		$id = (int) wc_get_product_id_by_sku( $reference );
		$product = $id ? wc_get_product( $id ) : false;
		if ( $product && 'publish' === $product->get_status() && ! $product->is_type( 'variation' ) ) {
			return 'product:' . $id;
		}
	}
	if ( 'category' === $type ) {
		$term = get_term_by( 'slug', slug( $reference, 'Video category slug' ), 'product_cat' );
		if ( $term ) {
			return 'cat:' . $term->term_id;
		}
	}
	fail( 'Unresolved or invalid video target: ' . $type . ' / ' . $reference );
}

function video_url( $value ): string {
	$value = plain( $value, 'Video URL', 1000 );
	if ( ! preg_match( '~^https://www\.tiktok\.com/@[a-zA-Z0-9._]+/video/\d{15,20}/?(?:\?[^\s#<>]*)?$~D', $value ) ) {
		fail( 'Videos require a full HTTPS TikTok author/video URL; bare IDs and short links are not portable.' );
	}
	return $value;
}

/** Only explicit layout fields leave the site. No option blobs, IDs, media URLs or contacts. */
function export_layout(): array {
	require_dependencies();
	$settings = Settings\all();
	$media = [];
	foreach ( [ 'logo_id', 'footer_logo_id' ] as $key ) {
		if ( ! empty( $settings[ $key ] ) ) {
			$media[] = $key . ': select an attachment in the destination media library';
		}
	}
	$home = array_intersect_key( $settings, array_flip( home_keys() ) );
	$home['featured_products'] = array_map( __NAMESPACE__ . '\\product_sku', array_map( 'intval', $home['featured_products'] ?? [] ) );
	foreach ( $home['sections'] as $key => &$section ) {
		$url = (string) ( $section['link_url'] ?? '' );
		if ( '' !== $url && '/' !== substr( $url, 0, 1 ) ) {
			$media[] = 'section ' . $key . ': external link omitted; select a destination-relative link';
			$section['link_url'] = '';
		}
	}
	unset( $section );
	$groups = [];
	foreach ( $settings['problems'] ?? [] as $group ) {
		$groups[] = [
			'title' => (string) $group['title'], 'description' => (string) $group['description'],
			'category_slug' => category_slug( (int) $group['category_id'] ),
			'chip' => (string) $group['chip'], 'enabled' => (bool) $group['enabled'],
		];
	}
	$policies = [];
	$stored = state();
	foreach ( policy_slugs() as $key => $fallback ) {
		$id = (int) ( $settings['policy_pages'][ $key ] ?? 0 );
		if ( ! $id ) {
			$id = (int) ( $stored['policies'][ $key ] ?? 0 );
		}
		$page = $id ? get_post( $id ) : null;
		$policies[ $key ] = $page && 'page' === $page->post_type ? $page->post_name : '';
	}
	$covers = get_option( Videos\COVERS, [] );
	$external = array_map( 'strval', (array) get_option( Videos\NO_EMBED, [] ) );
	$videos = [];
	foreach ( (array) get_option( Videos\CHANNEL, [] ) as $row ) {
		if ( ! is_array( $row ) ) {
			fail( 'Correct malformed channel video data before exporting.' );
		}
		$videos[] = [
			'url' => video_url( $row['url'] ?? null ), 'title' => (string) ( $row['title'] ?? '' ),
			'views' => (string) ( $row['views'] ?? '' ), 'tie' => portable_tie( (string) ( $row['tie'] ?? '' ) ),
			'enabled' => (bool) ( $row['enabled'] ?? true ), 'hero' => (bool) ( $row['hero'] ?? true ),
			'section' => (bool) ( $row['section'] ?? true ), 'external' => in_array( (string) $row['id'], $external, true ),
		];
		if ( ! empty( $covers[ $row['id'] ] ) ) {
			$media[] = 'video ' . $row['id'] . ': select a cover in the destination media library';
		}
	}
	$product_videos = [];
	$video_products = get_posts( [ 'post_type' => 'product', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids', 'meta_key' => Videos\META ] );
	foreach ( $video_products as $id ) {
		$rows = Videos\get_videos( (int) $id );
		if ( ! $rows ) {
			continue;
		}
		$portable = [];
		foreach ( $rows as $row ) {
			$portable[] = [ 'url' => video_url( $row['url'] ?? null ), 'title' => (string) ( $row['title'] ?? '' ) ];
		}
		$product_videos[] = [ 'sku' => product_sku( (int) $id ), 'videos' => $portable ];
	}
	foreach ( $settings['featured_products'] ?? [] as $id ) {
		$product = wc_get_product( $id );
		if ( $product && ( $product->get_image_id() || $product->get_gallery_image_ids() ) ) {
			$media[] = 'product SKU ' . $product->get_sku() . ': product images are not transferred; select destination images';
		}
	}
	$layout = [
		'version' => 1, 'kind' => 'store-kit-layout', 'design' => $settings['design'] ?? \StoreKitTheme\defaults(),
		'home' => $home, 'groups' => $groups, 'policies' => $policies, 'videos' => $videos,
		'product_videos' => $product_videos, 'media_required' => array_values( array_unique( $media ) ),
	];
	// Also prevent exporting a broken reference that could not be imported here.
	validate_layout( $layout );
	return $layout;
}

/** Full validation and reference resolution happens before a single option/meta write. */
function validate_layout( array $layout ): array {
	keys( $layout, [ 'version', 'kind', 'design', 'home', 'groups', 'policies', 'videos', 'product_videos', 'media_required' ], 'Layout' );
	if ( 1 !== ( $layout['version'] ?? null ) || 'store-kit-layout' !== ( $layout['kind'] ?? null ) ) {
		fail( 'Unsupported layout kind or version. Expected store-kit-layout version 1.' );
	}
	$values = validate_home( $layout['home'] ?? null );
	$values['design'] = design( $layout['design'] ?? null );
	$group_data = [];
	foreach ( list_value( $layout['groups'] ?? null, 'groups', 100 ) as $row ) {
		$row = object_value( $row, 'Group' );
		keys( $row, [ 'title', 'description', 'category_slug', 'chip', 'enabled' ], 'Group' );
		foreach ( [ 'title', 'description', 'chip' ] as $key ) {
			plain( $row[ $key ] ?? null, 'Group ' . $key );
		}
		$category_slug = slug( $row['category_slug'] ?? null, 'Group category_slug' );
		$term = get_term_by( 'slug', $category_slug, 'product_cat' );
		if ( ! $term ) {
			fail( 'Unresolved category slug: ' . $category_slug . '. Create this category before importing.' );
		}
		$group_data[] = [
			'title' => $row['title'], 'description' => $row['description'], 'chip' => $row['chip'],
			'category_id' => (string) $term->term_id, 'enabled' => boolean( $row['enabled'] ?? null, 'Group enabled' ) ? '1' : null,
		];
	}
	$errors = [];
	$groups = Admin\validate_tab( 'problems', [ 'problems' => $group_data ], $errors );
	$values['problems'] = $groups['problems'] ?? [];
	$policies = object_value( $layout['policies'] ?? null, 'policies' );
	keys( $policies, array_keys( policy_slugs() ), 'policies' );
	$policy_ids = [];
	$warnings = [];
	foreach ( policy_slugs() as $key => $unused ) {
		$reference = plain( $policies[ $key ] ?? null, 'Policy ' . $key, 200 );
		$policy_ids[ $key ] = 0;
		if ( '' === $reference ) {
			continue;
		}
		slug( $reference, 'Policy ' . $key );
		$page = get_page_by_path( $reference, OBJECT, 'page' );
		if ( ! $page || in_array( $page->post_status, [ 'trash', 'auto-draft' ], true ) ) {
			fail( 'Unresolved policy page slug: ' . $reference . '. Create a reviewed page or draft first.' );
		}
		if ( 'publish' === $page->post_status && '' === $page->post_password && '' !== trim( wp_strip_all_tags( $page->post_content ) ) ) {
			$policy_ids[ $key ] = (int) $page->ID;
		} else {
			$warnings[] = 'Policy ' . $reference . ' is not a populated, public, nonpassword page; its existing legal link is preserved. Review, publish and select it explicitly.';
		}
	}
	// Use the core legal validator only for public references. Never import BCT data.
	$legal_data = [ 'bct_url' => '', 'policy_pages' => array_map( 'strval', $policy_ids ) ];
	Admin\validate_tab( 'legal', $legal_data, $errors );
	$values['policy_pages'] = $policy_ids;
	$video_data = [];
	foreach ( list_value( $layout['videos'] ?? null, 'videos', 200 ) as $row ) {
		$row = object_value( $row, 'Video' );
		keys( $row, [ 'url', 'title', 'views', 'tie', 'enabled', 'hero', 'section', 'external' ], 'Video' );
		$clean = [
			'url' => video_url( $row['url'] ?? null ), 'title' => plain( $row['title'] ?? null, 'Video title' ),
			'views' => plain( $row['views'] ?? null, 'Video views', 200 ), 'tie' => resolve_tie( $row['tie'] ?? null ),
			'cover' => '0', 'original_id' => '',
		];
		foreach ( [ 'enabled', 'hero', 'section', 'external' ] as $key ) {
			$clean[ $key ] = boolean( $row[ $key ] ?? null, 'Video ' . $key ) ? '1' : null;
		}
		$video_data[] = $clean;
	}
	$video_values = Admin\validate_videos( [ 'videos' => $video_data ], $errors );
	$product_videos = [];
	foreach ( list_value( $layout['product_videos'] ?? null, 'product_videos', 500 ) as $row ) {
		$row = object_value( $row, 'Product videos' );
		keys( $row, [ 'sku', 'videos' ], 'Product videos' );
		$sku = plain( $row['sku'] ?? null, 'Product videos SKU', 200 );
		$id = '' !== $sku ? (int) wc_get_product_id_by_sku( $sku ) : 0;
		$product = $id ? wc_get_product( $id ) : false;
		if ( ! $product || $product->is_type( 'variation' ) || isset( $product_videos[ $id ] ) ) {
			fail( 'Unresolved, variation, or duplicate product video SKU: ' . $sku );
		}
		$rows = [];
		foreach ( list_value( $row['videos'] ?? null, 'Product video rows', 100 ) as $video ) {
			$video = object_value( $video, 'Product video' );
			keys( $video, [ 'url', 'title' ], 'Product video' );
			$url = video_url( $video['url'] ?? null );
			preg_match( '~/video/(\d{15,20})~', $url, $match );
			if ( isset( $rows[ $match[1] ] ) ) {
				fail( 'Duplicate product video for SKU: ' . $sku );
			}
			$rows[ $match[1] ] = [ 'id' => $match[1], 'url' => $url, 'title' => plain( $video['title'] ?? null, 'Product video title' ) ];
		}
		$product_videos[ $id ] = array_values( $rows );
	}
	foreach ( list_value( $layout['media_required'] ?? null, 'media_required', 1000 ) as $message ) {
		$warnings[] = plain( $message, 'Media selection report', 1000 );
	}
	if ( $errors ) {
		fail( implode( ' ', array_unique( $errors ) ) );
	}
	return [ 'settings' => $values, 'videos' => $video_values, 'product_videos' => $product_videos, 'warnings' => $warnings ];
}

function import_layout( array $layout ): array {
	require_dependencies();
	$validated = validate_layout( $layout );
	lock();
	try {
		$current = Settings\all();
		// An empty/draft imported reference cannot unhook an already reviewed policy.
		foreach ( $validated['settings']['policy_pages'] as $key => &$id ) {
			if ( ! $id ) {
				$id = (int) ( $current['policy_pages'][ $key ] ?? 0 );
			}
		}
		unset( $id );
		update_option( Settings\OPTION, array_replace( $current, $validated['settings'] ), false );
		update_option( Videos\CHANNEL, $validated['videos'][0], false );
		// Keep destination media selections; an import never moves foreign IDs or
		// turns off an existing explicit external-only / privacy choice.
		$external = array_merge( (array) get_option( Videos\NO_EMBED, [] ), $validated['videos'][2] );
		update_option( Videos\NO_EMBED, array_values( array_unique( array_map( 'strval', $external ) ) ), false );
		foreach ( $validated['product_videos'] as $id => $rows ) {
			update_post_meta( $id, Videos\META, $rows );
		}
		do_action( 'litespeed_purge_all' );
		return array_merge( [ 'Layout imported. Business identity, contacts, BCT, media, checkout settings, demo markers and veterinary restrictions were not imported or weakened.' ], $validated['warnings'], [ 'Media files are never transferred. Review logo, product images and video covers in the destination media library before launch.' ] );
	} finally {
		unlock();
	}
}

function order_references( int $id ): bool {
	global $wpdb;
	// Order item tables are shared by both legacy storage and HPOS. Keep every
	// historical order/item untouched, including cancelled and refunded orders.
	$table = $wpdb->prefix . 'woocommerce_order_itemmeta';
	return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT meta_id FROM {$table} WHERE meta_key IN ('_product_id','_variation_id') AND meta_value = %s LIMIT 1", (string) $id ) );
}

function purge_demo(): array {
	require_dependencies();
	lock();
	try {
		$products = get_posts( [
			'post_type' => 'product', 'post_status' => array_keys( get_post_stati() ), 'numberposts' => -1, 'fields' => 'ids',
			'meta_query' => [ [ 'key' => OWNED, 'value' => '1' ], [ 'key' => DEMO, 'value' => '1' ] ],
		] );
		$deleted = 0;
		$warnings = [];
		foreach ( $products as $id ) {
			$children = get_posts( [ 'post_type' => 'product_variation', 'post_parent' => $id, 'post_status' => array_keys( get_post_stati() ), 'numberposts' => -1, 'fields' => 'ids' ] );
			$safe = ! order_references( (int) $id );
			foreach ( $children as $child ) {
				if ( ! get_post_meta( $child, OWNED, true ) || ! get_post_meta( $child, DEMO, true ) || order_references( (int) $child ) ) {
					$safe = false;
				}
			}
			if ( ! $safe ) {
				$warnings[] = 'Retained demo product ' . $id . ': an order references it or a variation is not a kit-owned demo. Order history is never removed.';
				continue;
			}
			$product = wc_get_product( $id );
			if ( ! $product || ! $product->delete( true ) ) {
				$warnings[] = 'Could not delete kit demo product ' . $id . '.';
				continue;
			}
			$deleted++;
		}
		// We never download sample media. If a kit-owned demo attachment was added,
		// delete it only when there is no remaining parent, content or media reference.
		$attachments = get_posts( [
			'post_type' => 'attachment', 'post_status' => 'inherit', 'numberposts' => -1, 'fields' => 'ids',
			'meta_query' => [ [ 'key' => OWNED, 'value' => '1' ], [ 'key' => DEMO, 'value' => '1' ] ],
		] );
		$media_deleted = 0;
		foreach ( $attachments as $id ) {
			if ( attachment_in_use( (int) $id ) ) {
				$warnings[] = 'Retained demo attachment ' . $id . ': another resource still references it.';
				continue;
			}
			if ( wp_delete_attachment( $id, true ) ) {
				$media_deleted++;
			}
		}
		$settings = get_option( Settings\OPTION, [] );
		if ( $deleted && is_array( $settings ) && isset( $settings['featured_products'] ) ) {
			$demo_ids = array_map( 'intval', $products );
			$settings['featured_products'] = array_values( array_filter( $settings['featured_products'], static fn( $id ) => ! in_array( (int) $id, $demo_ids, true ) || (bool) wc_get_product( $id ) ) );
			update_option( Settings\OPTION, $settings, false );
		}
		do_action( 'litespeed_purge_all' );
		return array_merge( [ sprintf( 'Purged %d kit demo products and %d unreferenced kit demo attachments. Merchant products, pages, categories, customers and orders were not deleted.', $deleted, $media_deleted ) ], $warnings );
	} finally {
		unlock();
	}
}

function attachment_in_use( int $id ): bool {
	global $wpdb;
	$post = get_post( $id );
	if ( ! $post || ( $post->post_parent && get_post( $post->post_parent ) ) ) {
		return true;
	}
	$settings = Settings\all();
	if ( in_array( $id, [ (int) ( $settings['logo_id'] ?? 0 ), (int) ( $settings['footer_logo_id'] ?? 0 ), (int) get_theme_mod( 'custom_logo', 0 ) ], true ) || in_array( $id, array_map( 'intval', (array) get_option( Videos\COVERS, [] ) ), true ) ) {
		return true;
	}
	$meta = $wpdb->get_var( $wpdb->prepare( "SELECT meta_id FROM {$wpdb->postmeta} WHERE (meta_key = '_thumbnail_id' AND meta_value = %s) OR (meta_key = '_product_image_gallery' AND FIND_IN_SET(%s, meta_value)) LIMIT 1", (string) $id, (string) $id ) );
	if ( $meta ) {
		return true;
	}
	$url = wp_get_attachment_url( $id );
	$pattern = '%' . $wpdb->esc_like( 'wp-image-' . $id ) . '%';
	$url_pattern = '%' . $wpdb->esc_like( $url ?: 'attachment_id=' . $id ) . '%';
	return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE ID <> %d AND (post_content LIKE %s OR post_content LIKE %s) LIMIT 1", $id, $pattern, $url_pattern ) );
}

/** Readiness diagnostics only; no writes and no claim of legal certification. */
function doctor(): array {
	$items = [];
	$add = static function ( string $level, string $message ) use ( &$items ): void {
		$items[] = [ 'level' => $level, 'message' => $message ];
	};
	$woo = class_exists( 'WooCommerce' );
	$add( $woo ? 'ok' : 'error', $woo ? 'WooCommerce is active.' : 'WooCommerce is not active.' );
	$add( 'store-kit-child' === get_stylesheet() ? 'ok' : 'error', 'Active theme: ' . get_stylesheet() . '; Store Kit requires store-kit-child with free Blocksy.' );
	$stored = state();
	$add( ! empty( $stored['complete'] ) ? 'ok' : 'warning', ! empty( $stored['complete'] ) ? 'Owned foundation setup completed (' . ( $stored['preset'] ?? 'unknown' ) . ').' : 'No completed kit-owned foundation setup was recorded.' );
	$settings = Settings\all();
	foreach ( [ 'brand' => 'brand name', 'legal_name' => 'legal seller name', 'tax_id' => 'tax identity where applicable', 'address' => 'business address', 'returns_address' => 'returns address', 'email' => 'contact email', 'phone' => 'contact phone' ] as $key => $label ) {
		if ( empty( $settings[ $key ] ) ) {
			$add( 'warning', 'Merchant configuration missing: ' . $label . '.' );
		}
	}
	foreach ( [ 'logo_id' => 'header logo', 'footer_logo_id' => 'footer logo' ] as $key => $label ) {
		$id = (int) ( $settings[ $key ] ?? 0 );
		$add( $id && wp_attachment_is_image( $id ) ? 'ok' : 'warning', $id && wp_attachment_is_image( $id ) ? ucfirst( $label ) . ' selected.' : 'No valid ' . $label . ' selected; text branding is used. Choose media explicitly if needed.' );
	}
	foreach ( policy_slugs() as $key => $slug ) {
		$id = (int) ( $settings['policy_pages'][ $key ] ?? 0 );
		$page = $id ? get_post( $id ) : null;
		$ready = $page && 'page' === $page->post_type && 'publish' === $page->post_status && '' === $page->post_password && '' !== trim( wp_strip_all_tags( $page->post_content ) );
		$add( $ready ? 'ok' : 'warning', 'Policy ' . $key . ': ' . ( $ready ? 'public link selected; merchant remains responsible for content and compliance.' : 'review a draft, publish the final policy, and select its public page in Legal.' ) );
	}
	if ( empty( $settings['bct_confirmed'] ) || empty( $settings['bct_url'] ) ) {
		$add( 'warning', 'BCT registration is not confirmed. No registration badge is implied; confirm only with a real official lookup when applicable.' );
	}
	$demo = get_posts( [ 'post_type' => 'product', 'post_status' => array_keys( get_post_stati() ), 'numberposts' => 1, 'fields' => 'ids', 'meta_key' => DEMO, 'meta_value' => '1' ] );
	$add( $demo ? 'warning' : 'ok', $demo ? 'DEMO products remain and are blocked from purchase. Use Demo purge before launch.' : 'No kit demo products remain.' );
	if ( $woo ) {
		foreach ( [ 'shop', 'cart', 'checkout', 'myaccount' ] as $key ) {
			$id = (int) wc_get_page_id( $key );
			$add( $id > 0 && 'publish' === get_post_status( $id ) ? 'ok' : 'warning', 'WooCommerce ' . $key . ' page: ' . ( $id > 0 && 'publish' === get_post_status( $id ) ? 'mapped and published.' : 'missing or not published.' ) );
		}
		$gateways = WC()->payment_gateways()->payment_gateways();
		$enabled = array_filter( $gateways, static fn( $gateway ) => 'yes' === $gateway->enabled );
		$add( $enabled ? 'ok' : 'warning', $enabled ? 'At least one payment gateway is enabled; verify its real credentials and checkout behavior.' : 'No payment gateway is enabled. Configure and verify real payment methods.' );
		$add( 'warning', 'Merchant must configure and verify delivery zones/rates, taxes, stock, currency, transactional email and consent for the actual business. Setup invents none of these.' );
		$missing_image = get_posts( [ 'post_type' => 'product', 'post_status' => 'publish', 'numberposts' => 1, 'fields' => 'ids', 'meta_query' => [ [ 'key' => '_thumbnail_id', 'compare' => 'NOT EXISTS' ] ] ] );
		if ( $missing_image ) {
			$add( 'warning', 'Some published products have no selected product image. Choose merchant-owned media; no source/client photographs are included.' );
		}
	}
	$vet_active = function_exists( '\\StoreKit\\Veterinary\\category_slugs' );
	// Plugin activity is authoritative even if the module keeps its APIs private.
	if ( ! function_exists( 'is_plugin_active' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	$vet_active = $vet_active || is_plugin_active( 'store-kit-veterinary/store-kit-veterinary.php' );
	$needs_vet = 'pigvet' === ( $stored['preset'] ?? '' );
	$add( $needs_vet && ! $vet_active ? 'error' : 'ok', $vet_active ? 'Optional veterinary module is active; restricted categories must remain correctly configured.' : ( $needs_vet ? 'Veterinary preset requires its missing/inactive safety module.' : 'Optional veterinary module is not active (neutral store).' ) );
	if ( $vet_active ) {
		$rules = get_option( 'store_kit_veterinary', [] );
		if ( empty( $rules['restricted_category_slugs'] ) ) {
			$add( 'warning', 'Veterinary category settings were not explicitly saved. The module retains its default fail-safe prescription category; review the real catalog and save its restricted slugs before launch.' );
		}
	}
	$covers = (array) get_option( Videos\COVERS, [] );
	foreach ( (array) get_option( Videos\CHANNEL, [] ) as $row ) {
		if ( is_array( $row ) && ! empty( $row['enabled'] ) && ( empty( $covers[ $row['id'] ] ) || ! wp_attachment_is_image( (int) $covers[ $row['id'] ] ) ) ) {
			$add( 'warning', 'Video ' . $row['id'] . ' needs an explicitly selected cover image.' );
		}
	}
	$add( 'notice', 'Readiness report only: not a security audit, legal approval, veterinary authorization, or regulatory certification.' );
	return $items;
}

function render_form_start( string $operation, bool $upload = false ): void {
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"' . ( $upload ? ' enctype="multipart/form-data"' : '' ) . '>';
	echo '<input type="hidden" name="action" value="store_kit_blueprint"><input type="hidden" name="operation" value="' . esc_attr( $operation ) . '">';
	wp_nonce_field( 'store_kit_blueprint_' . $operation, 'store_kit_blueprint_nonce' );
}

function render( string $tab, array $data = [] ): void {
	if ( 'setup' !== $tab || ! current_user_can( 'manage_woocommerce' ) ) {
		return;
	}
	$feedback = get_transient( 'store_kit_blueprint_feedback_' . get_current_user_id() );
	delete_transient( 'store_kit_blueprint_feedback_' . get_current_user_id() );
	if ( is_array( $feedback ) ) {
		echo '<div class="notice ' . ( ! empty( $feedback['error'] ) ? 'notice-error' : 'notice-success' ) . '"><ul>';
		foreach ( $feedback['messages'] ?? [] as $message ) {
			echo '<li>' . esc_html( $message ) . '</li>';
		}
		echo '</ul></div>';
	}
	echo '<h2>Tạo nền cửa hàng sạch</h2><p>Lần thiết lập đầu tạo trang, menu, liên kết trang WooCommerce và tám bản nháp chính sách cần rà soát. Không tự điền danh tính doanh nghiệp, đăng ký, thông tin thanh toán hay xác nhận pháp lý. Cửa hàng có dữ liệu nhưng chưa do bộ kit thiết lập sẽ bị từ chối. Chạy lại giữ nguyên nội dung đã chỉnh sửa.</p><p>Trang mới dùng đường dẫn đẹp nếu website chưa có cấu trúc đường dẫn. WordPress thử ghi quy tắc Apache khi có quyền; máy chủ Nginx cần cấu hình chuyển đường dẫn về <code>index.php</code> theo hướng dẫn WordPress.</p>';
	render_form_start( 'setup' );
	$current_preset = state()['preset'] ?? 'neutral';
	echo '<p><label>Mẫu thiết kế <select name="preset"><option value="neutral"' . selected( $current_preset, 'neutral', false ) . '>Cửa hàng trung tính</option><option value="pigvet"' . selected( $current_preset, 'pigvet', false ) . '>Cửa hàng thú y (cần mô-đun tùy chọn)</option></select></label></p>';
	echo '<p><label><input type="checkbox" name="demo" value="1"> Thêm bốn sản phẩm DEMO, gồm sản phẩm có biến thể; tất cả đều bị chặn mua</label></p>';
	submit_button( 'Thiết lập nền cửa hàng', 'primary', 'submit', false );
	echo '</form><hr><h2>Chuyển giao bố cục</h2><p>Tệp xuất chỉ chứa thiết kế, nội dung trang chủ, slug danh mục/trang chính sách, SKU sản phẩm và URL video đầy đủ. Không xuất danh tính, liên hệ, mã số thuế, BCT, mật khẩu, giấy phép, đơn hàng, người dùng hay tệp ảnh. Kiểm tra nội dung tự nhập trước khi chia sẻ. Website nhận phải có sẵn sản phẩm, danh mục và trang chính sách tương ứng; mọi trường và tham chiếu được kiểm tra trước khi ghi. Tham chiếu thiếu được báo rõ, không tự thay bằng dữ liệu khác.</p>';
	render_form_start( 'export' );
	submit_button( 'Tải bố cục JSON', 'secondary', 'submit', false );
	echo '</form>';
	render_form_start( 'import', true );
	echo '<p><label>Tệp bố cục JSON (tối đa 1 MiB) <input type="file" name="layout_file" accept=".json,application/json" required></label></p><p><label><input type="checkbox" name="confirm_import" value="1" required> Thay các trường bố cục; giữ nguyên danh tính doanh nghiệp, bảo vệ pháp lý/quyền riêng tư, ảnh và hạn chế mua hàng.</label></p>';
	submit_button( 'Nhập bố cục', 'secondary', 'submit', false );
	echo '</form><hr><h2 id="store-kit-demo-purge">Xóa dữ liệu minh họa</h2><p>Chỉ xóa sản phẩm DEMO do bộ kit sở hữu và ảnh demo không còn được tham chiếu. Sản phẩm liên quan đến lịch sử đơn hàng hoặc có biến thể không phải demo sẽ được giữ và báo lại. Không xóa đơn hàng, khách hàng, sản phẩm thật hay trang chính sách.</p>';
	render_form_start( 'purge' );
	echo '<p><label><input type="checkbox" name="confirm_purge" value="1" required> Xóa vĩnh viễn dữ liệu DEMO của bộ kit</label></p>';
	submit_button( 'Xóa DEMO của bộ kit', 'delete', 'submit', false );
	echo '</form><hr><h2>Kiểm tra sẵn sàng</h2><p>Báo cáo kỹ thuật bên dưới không phải phê duyệt pháp lý hay chứng nhận tuân thủ.</p><ul>';
	foreach ( doctor() as $item ) {
		echo '<li><strong>' . esc_html( strtoupper( $item['level'] ) ) . '</strong> — ' . esc_html( $item['message'] ) . '</li>';
	}
	echo '</ul><p>CLI: <code>wp store-kit setup --preset=neutral --demo</code>, <code>wp store-kit export --file=layout.json</code>, <code>wp store-kit import --file=layout.json</code>, <code>wp store-kit doctor</code>, <code>wp store-kit demo-purge</code>.</p>';
}

function admin_action(): void {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( 'You do not have permission to manage Store Kit.', '', [ 'response' => 403 ] );
	}
	if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
		wp_die( 'POST required.', '', [ 'response' => 405 ] );
	}
	$operation = isset( $_POST['operation'] ) && is_string( $_POST['operation'] ) ? wp_unslash( $_POST['operation'] ) : '';
	if ( ! in_array( $operation, [ 'setup', 'export', 'import', 'purge' ], true ) ) {
		wp_die( 'Unknown blueprint action.', '', [ 'response' => 400 ] );
	}
	check_admin_referer( 'store_kit_blueprint_' . $operation, 'store_kit_blueprint_nonce' );
	$error = false;
	try {
		if ( 'setup' === $operation ) {
			$preset = isset( $_POST['preset'] ) && is_string( $_POST['preset'] ) ? wp_unslash( $_POST['preset'] ) : '';
			$messages = setup( $preset, isset( $_POST['demo'] ) && '1' === $_POST['demo'] );
		} elseif ( 'export' === $operation ) {
			$json = encode( export_layout() );
			nocache_headers();
			header( 'Content-Type: application/json; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename="store-kit-layout.json"' );
			header( 'X-Content-Type-Options: nosniff' );
			echo $json; // Validated JSON download, not rendered HTML.
			exit;
		} elseif ( 'import' === $operation ) {
			if ( ! isset( $_POST['confirm_import'] ) || '1' !== $_POST['confirm_import'] ) {
				fail( 'Explicit layout replacement confirmation is required.' );
			}
			$file = $_FILES['layout_file'] ?? null;
			if ( ! is_array( $file ) || ! isset( $file['error'], $file['tmp_name'] ) || UPLOAD_ERR_OK !== $file['error'] || ! is_string( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
				fail( 'Upload a valid JSON file; check the server upload size limit.' );
			}
			$messages = import_layout( read_json( $file['tmp_name'] ) );
		} else {
			if ( ! isset( $_POST['confirm_purge'] ) || '1' !== $_POST['confirm_purge'] ) {
				fail( 'Explicit permanent demo deletion confirmation is required.' );
			}
			$messages = purge_demo();
		}
	} catch ( \Throwable $exception ) {
		$error = true;
		$messages = [ $exception->getMessage() ];
	}
	set_transient( 'store_kit_blueprint_feedback_' . get_current_user_id(), [ 'error' => $error, 'messages' => $messages ], 10 * MINUTE_IN_SECONDS );
	wp_safe_redirect( Admin\tab_url( 'setup' ) );
	exit;
}

/** Registered only when WP-CLI is actually present; web requests need no CLI library. */
if ( defined( 'WP_CLI' ) && WP_CLI && class_exists( 'WP_CLI' ) ) {
	\WP_CLI::add_command( 'store-kit setup', __NAMESPACE__ . '\\cli_setup' );
	\WP_CLI::add_command( 'store-kit export', __NAMESPACE__ . '\\cli_export' );
	\WP_CLI::add_command( 'store-kit import', __NAMESPACE__ . '\\cli_import' );
	\WP_CLI::add_command( 'store-kit doctor', __NAMESPACE__ . '\\cli_doctor' );
	\WP_CLI::add_command( 'store-kit demo-purge', __NAMESPACE__ . '\\cli_purge' );
}

function cli_messages( callable $action ): void {
	try {
		foreach ( $action() as $message ) {
			\WP_CLI::log( $message );
		}
		\WP_CLI::success( 'Store Kit operation completed.' );
	} catch ( \Throwable $exception ) {
		\WP_CLI::error( $exception->getMessage() );
	}
}

/**
 * Create an owned, idempotent foundation.
 *
 * ## OPTIONS
 *
 * [--preset=<preset>]
 * : neutral or pigvet. Default: neutral.
 *
 * [--demo]
 * : Add clearly marked demo products that cannot be purchased.
 */
function cli_setup( array $args, array $assoc ): void {
	cli_messages( static fn() => setup( (string) ( $assoc['preset'] ?? 'neutral' ), isset( $assoc['demo'] ) ) );
}

/**
 * Export identity-free layout JSON.
 *
 * ## OPTIONS
 *
 * --file=<path>
 * : New destination JSON path. Existing files are never overwritten.
 */
function cli_export( array $args, array $assoc ): void {
	cli_messages( static function () use ( $assoc ): array {
		$file = $assoc['file'] ?? '';
		if ( ! is_string( $file ) || '' === $file || false !== strpos( $file, '://' ) ) {
			fail( '--file must be a local output path.' );
		}
		$layout = export_layout();
		$json = encode( $layout );
		$handle = @fopen( $file, 'x' );
		if ( ! $handle ) {
			fail( 'Cannot create export file. Choose a writable path that does not already exist.' );
		}
		try {
			if ( strlen( $json ) !== fwrite( $handle, $json ) ) {
				fail( 'Export write was incomplete. Remove the incomplete file and retry.' );
			}
		} finally {
			fclose( $handle );
		}
		return array_merge( [ 'Exported portable layout to ' . $file . '. No business identity or media files are included.' ], $layout['media_required'] );
	} );
}

/**
 * Validate every field/reference, then import a layout.
 *
 * ## OPTIONS
 *
 * --file=<path>
 * : Local version-1 layout JSON path.
 */
function cli_import( array $args, array $assoc ): void {
	cli_messages( static function () use ( $assoc ): array {
		$file = $assoc['file'] ?? '';
		if ( ! is_string( $file ) || '' === $file || false !== strpos( $file, '://' ) ) {
			fail( '--file must be a local JSON path.' );
		}
		return import_layout( read_json( $file ) );
	} );
}

/** Report readiness; not legal or regulatory certification. */
function cli_doctor( array $args, array $assoc ): void {
	try {
		$has_error = false;
		foreach ( doctor() as $item ) {
			\WP_CLI::log( strtoupper( $item['level'] ) . ': ' . $item['message'] );
			$has_error = $has_error || 'error' === $item['level'];
		}
		if ( $has_error ) {
			\WP_CLI::error( 'Required dependencies or safety configuration need attention.' );
		}
	} catch ( \Throwable $exception ) {
		\WP_CLI::error( $exception->getMessage() );
	}
}

/** Permanently delete only safe, kit-owned demo objects. */
function cli_purge( array $args, array $assoc ): void {
	cli_messages( __NAMESPACE__ . '\\purge_demo' );
}
