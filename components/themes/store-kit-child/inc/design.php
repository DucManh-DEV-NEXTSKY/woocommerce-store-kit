<?php
/** Validated, portable design tokens. No merchant CSS or executable input. */
namespace StoreKitTheme;

defined( 'ABSPATH' ) || exit;

function schema(): array {
	return [
		'primary' => [ 'label' => 'Màu chính / nút bấm', 'type' => 'color' ],
		'primary_hover' => [ 'label' => 'Màu chính khi rê chuột', 'type' => 'color' ],
		'heading' => [ 'label' => 'Màu tiêu đề', 'type' => 'color' ],
		'text' => [ 'label' => 'Màu nội dung', 'type' => 'color' ],
		'muted' => [ 'label' => 'Màu chữ phụ', 'type' => 'color' ],
		'tile' => [ 'label' => 'Nền ô nội dung', 'type' => 'color' ],
		'soft' => [ 'label' => 'Nền nhạt', 'type' => 'color' ],
		'deep' => [ 'label' => 'Nền chân trang', 'type' => 'color' ],
		'border' => [ 'label' => 'Màu đường viền', 'type' => 'color' ],
		'accent' => [ 'label' => 'Nền khối bài viết', 'type' => 'color' ],
		'sale' => [ 'label' => 'Nhãn giảm giá', 'type' => 'color' ],
		'font_heading' => [ 'label' => 'Phông tiêu đề', 'type' => 'select', 'choices' => [ 'bricolage' => 'Bricolage Grotesque', 'be-vietnam' => 'Be Vietnam Pro', 'system' => 'Phông hệ thống' ] ],
		'font_body' => [ 'label' => 'Phông nội dung', 'type' => 'select', 'choices' => [ 'be-vietnam' => 'Be Vietnam Pro', 'system' => 'Phông hệ thống' ] ],
		'radius_image' => [ 'label' => 'Bo góc ảnh / khối (px)', 'type' => 'number', 'min' => 0, 'max' => 32 ],
		'radius_button' => [ 'label' => 'Bo góc nút / ô nhập (px)', 'type' => 'number', 'min' => 0, 'max' => 40 ],
		'content_width' => [ 'label' => 'Chiều rộng nội dung tối đa (px)', 'type' => 'number', 'min' => 960, 'max' => 1600 ],
		'section_desktop' => [ 'label' => 'Khoảng cách phần trên máy tính (px)', 'type' => 'number', 'min' => 32, 'max' => 140 ],
		'section_mobile' => [ 'label' => 'Khoảng cách phần trên điện thoại (px)', 'type' => 'number', 'min' => 20, 'max' => 80 ],
		'grid_gap' => [ 'label' => 'Khoảng cách ô lưới (px)', 'type' => 'number', 'min' => 8, 'max' => 40 ],
		'base_font' => [ 'label' => 'Cỡ chữ cơ bản (px)', 'type' => 'number', 'min' => 14, 'max' => 20 ],
	];
}

function defaults(): array {
	return [
		'primary' => '#22594f', 'primary_hover' => '#174239', 'heading' => '#202b29',
		'text' => '#3e4a47', 'muted' => '#65736f', 'tile' => '#f3f6f5',
		'soft' => '#e5eeeb', 'deep' => '#192e29', 'border' => '#d8e1de',
		'accent' => '#eef3f1', 'sale' => '#a72837',
		'font_heading' => 'bricolage', 'font_body' => 'be-vietnam',
		'radius_image' => 16, 'radius_button' => 24, 'content_width' => 1280,
		'section_desktop' => 88, 'section_mobile' => 48, 'grid_gap' => 22, 'base_font' => 16,
	];
}

function presets(): array {
	$base = defaults();
	return [
		'neutral' => $base,
		'pigvet' => array_replace( $base, [
			'primary' => '#b3232b', 'primary_hover' => '#8e1b22', 'heading' => '#2e1f1f',
			'text' => '#4a3f3d', 'muted' => '#6f6461', 'tile' => '#fbf6f0',
			'soft' => '#f7e6e4', 'deep' => '#2b1a1b', 'border' => '#e9e0d9',
			'accent' => '#f7f2ec', 'sale' => '#b3232b', 'radius_image' => 20, 'radius_button' => 30,
		] ),
		'blue' => array_replace( $base, [
			'primary' => '#215aa0', 'primary_hover' => '#174378', 'heading' => '#1d2d43',
			'text' => '#3b4b60', 'muted' => '#607087', 'tile' => '#f1f5fa',
			'soft' => '#e2edf9', 'deep' => '#172c47', 'border' => '#d5e0ed',
			'accent' => '#edf3fa', 'sale' => '#a52b43', 'radius_image' => 8, 'radius_button' => 8,
		] ),
	];
}

/** Partial maps merge with defaults; unknown keys and malformed values fail explicitly. */
function validate( array $input ): array {
	$values = defaults();
	$errors = [];
	$schema = schema();
	foreach ( $input as $key => $value ) {
		if ( ! isset( $schema[ $key ] ) ) {
			$errors[] = 'Thông số giao diện không hỗ trợ: ' . (string) $key;
			continue;
		}
		$field = $schema[ $key ];
		$valid = false;
		if ( 'color' === $field['type'] ) {
			$valid = is_string( $value ) && 1 === preg_match( '/^#[0-9a-fA-F]{6}$/D', $value );
			if ( $valid ) {
				$value = strtolower( $value );
			}
		} elseif ( 'select' === $field['type'] ) {
			$valid = is_string( $value ) && isset( $field['choices'][ $value ] );
		} else {
			$valid = ( is_int( $value ) || ( is_string( $value ) && 1 === preg_match( '/^(?:0|[1-9][0-9]{0,3})$/D', $value ) ) ) && $value >= $field['min'] && $value <= $field['max'];
			if ( $valid ) {
				$value = (int) $value;
			}
		}
		if ( ! $valid ) {
			$errors[] = $field['label'] . ': ' . ( 'number' === $field['type'] ? 'nhập số nguyên từ ' . $field['min'] . ' đến ' . $field['max'] . '.' : ( 'color' === $field['type'] ? 'nhập mã màu sáu chữ số, ví dụ #22594f.' : 'chọn phông có trong danh sách.' ) );
			continue;
		}
		$values[ $key ] = $value;
	}
	return [ 'values' => $values, 'errors' => $errors ];
}

function current_design(): array {
	$input = function_exists( '\StoreKit\Settings\get' ) ? \StoreKit\Settings\get( 'design' ) : [];
	return validate( is_array( $input ) ? $input : [] )['values'];
}

function design_css( array $design ): string {
	$d = validate( $design )['values'];
	$variables = [];
	foreach ( [ 'primary', 'primary_hover', 'heading', 'text', 'muted', 'tile', 'soft', 'deep', 'border', 'sale' ] as $key ) {
		$variables[] = '--c-' . str_replace( '_', '-', $key ) . ':' . $d[ $key ];
	}
	$variables[] = '--c-accent-2:' . $d['accent'];
	$fonts = [ 'bricolage' => '"Bricolage Grotesque", "Be Vietnam Pro", system-ui, sans-serif', 'be-vietnam' => '"Be Vietnam Pro", system-ui, sans-serif', 'system' => 'system-ui, -apple-system, "Segoe UI", sans-serif' ];
	$variables[] = '--f-serif:' . $fonts[ $d['font_heading'] ];
	$variables[] = '--f-sans:' . $fonts[ $d['font_body'] ];
	foreach ( [ 'radius_image' => 'r-img', 'radius_button' => 'r-pill', 'content_width' => 'sk-content-width', 'section_desktop' => 'sk-section-desktop', 'section_mobile' => 'sk-section-mobile', 'grid_gap' => 'grid-gap', 'base_font' => 'theme-font-size' ] as $key => $variable ) {
		$variables[] = '--' . $variable . ':' . $d[ $key ] . 'px';
	}
	return ':root:root{' . implode( ';', $variables ) . ';}';
}

add_filter( 'store_kit_settings_tabs', static function ( array $tabs ): array {
	$tabs['design'] = 'Giao diện';
	return $tabs;
} );
add_filter( 'store_kit_validate_settings_tab', static function ( array $result, string $tab, array $data ): array {
	if ( 'design' !== $tab ) {
		return $result;
	}
	if ( ! isset( $data['design'] ) || ! is_array( $data['design'] ) || count( array_diff_key( schema(), $data['design'] ) ) ) {
		$result['errors'][] = 'Thiếu thông số giao diện. Tải lại trang rồi thử lại.';
		return $result;
	}
	$validated = validate( $data['design'] );
	$result['values']['design'] = $validated['values'];
	$result['errors'] = array_merge( $result['errors'], $validated['errors'] );
	return $result;
}, 10, 3 );

add_action( 'store_kit_render_settings_tab', static function ( string $tab, array $data ): void {
	if ( 'design' !== $tab ) {
		return;
	}
	$design = validate( isset( $data['design'] ) && is_array( $data['design'] ) ? $data['design'] : [] )['values'];
	echo '<h2>Giao diện cửa hàng</h2><p>Chọn mẫu màu rồi điều chỉnh các thông số bên dưới. Mẫu chỉ điền biểu mẫu; bấm Lưu Giao diện để áp dụng. Thương hiệu, nội dung và liên hệ không thay đổi.</p>';
	echo '<p><label for="sk-design-preset">Mẫu giao diện</label> <select id="sk-design-preset"><option value="">Giữ cài đặt hiện tại</option><option value="neutral">Neutral — xanh trầm</option><option value="pigvet">PIGVET — đỏ rượu</option><option value="blue">Blue — xanh dương</option></select> <button type="button" class="button" id="sk-apply-design">Điền theo mẫu</button></p>';
	echo '<p class="description">Phông được lưu trên máy chủ với giấy phép SIL Open Font License. Màu dùng mã #RRGGBB. Kiểm tra độ tương phản chữ/nền khi đổi màu; không nhận CSS tùy ý.</p><table class="form-table" role="presentation"><tbody>';
	foreach ( schema() as $key => $field ) {
		$id = 'sk-design-' . $key;
		printf( '<tr><th scope="row"><label for="%s">%s</label></th><td>', esc_attr( $id ), esc_html( $field['label'] ) );
		if ( 'select' === $field['type'] ) {
			printf( '<select id="%s" name="data[design][%s]">', esc_attr( $id ), esc_attr( $key ) );
			foreach ( $field['choices'] as $value => $label ) {
				printf( '<option value="%s" %s>%s</option>', esc_attr( $value ), selected( $design[ $key ], $value, false ), esc_html( $label ) );
			}
			echo '</select>';
		} elseif ( 'number' === $field['type'] ) {
			printf( '<input type="number" class="small-text" id="%s" name="data[design][%s]" value="%d" min="%d" max="%d" step="1" required><p class="description">%d–%d px</p>', esc_attr( $id ), esc_attr( $key ), $design[ $key ], $field['min'], $field['max'], $field['min'], $field['max'] );
		} else {
			printf( '<input type="text" class="regular-text" id="%s" name="data[design][%s]" value="%s" pattern="#[0-9a-fA-F]{6}" maxlength="7" required spellcheck="false" aria-describedby="%s-help"><span id="%s-help" class="description"> #RRGGBB</span>', esc_attr( $id ), esc_attr( $key ), esc_attr( $design[ $key ] ), esc_attr( $id ), esc_attr( $id ) );
		}
		echo '</td></tr>';
	}
	echo '</tbody></table>';
} , 10, 2 );

add_action( 'admin_enqueue_scripts', static function ( string $hook ): void {
	if ( 'toplevel_page_store-kit-settings' !== $hook ) {
		return;
	}
	wp_enqueue_script( 'store-kit-design', get_stylesheet_directory_uri() . '/assets/design.js', [], '1.0.0', true );
	wp_add_inline_script( 'store-kit-design', 'window.storeKitDesignPresets=' . wp_json_encode( presets(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ) . ';', 'before' );
} );
