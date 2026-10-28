# Store Kit — quy tắc giao diện dùng lại

## Nguồn cấu hình

- Theme `store-kit-child` là lớp thiết kế trên **Blocksy Free**. Plugin `store-kit` giữ nội dung và chức năng; không sửa theme/plugin bên thứ ba.
- Cấu hình của từng store nằm ở `store_kit_settings.design`; thay phiên bản code không ghi đè bộ cấu hình đã lưu.
- Vào **Store Kit → Design**. Chọn preset để điền form, chỉnh token rồi **Lưu Design**. Chọn preset chưa phải xuất bản; chỉ nút lưu áp dụng thay đổi.
- Ba bảng màu khởi đầu: **Neutral** (xanh trầm), **PIGVET** (đỏ trầm/kem), **Blue** (xanh dương). Tên preset không tự nhập tên doanh nghiệp, liên hệ, logo hoặc hồ sơ pháp lý.
- Không nhận CSS/PHP tùy ý từ form. Thay bố cục hoặc thêm component do bên phát triển thực hiện trong source có phiên bản.

## Token và giới hạn

| Nhóm | Khóa | Phạm vi |
|---|---|---|
| Màu | `primary`, `primary_hover`, `heading`, `text`, `muted`, `tile`, `soft`, `deep`, `border`, `accent`, `sale` | Mã `#RRGGBB` |
| Chữ tiêu đề | `font_heading` | Bricolage Grotesque, Be Vietnam Pro, system |
| Chữ nội dung | `font_body` | Be Vietnam Pro hoặc system |
| Bo ảnh/panel | `radius_image` | 0–32 px |
| Bo nút/ô nhập | `radius_button` | 0–40 px |
| Chiều rộng nội dung | `content_width` | 960–1600 px; vẫn co xuống theo màn hình |
| Khoảng section desktop | `section_desktop` | 32–140 px |
| Khoảng section mobile | `section_mobile` | 20–80 px |
| Khoảng lưới | `grid_gap` | 8–40 px |
| Cỡ chữ nền | `base_font` | 14–20 px |

`inc/design.php` là schema/validator; `assets/site.css` giữ quy tắc component. Theme nạp các biến đã kiểm tra sau stylesheet. Plugin dùng cùng biến, kể cả nút/chip/thẻ video; không tạo một bộ màu độc lập cho từng phần.

## Component và hành vi không đổi theo preset

- Sản phẩm nằm trên nền sáng, ảnh `contain` để không cắt nhãn; giá, tên, tồn kho và nút do WooCommerce cung cấp.
- Nút/ô nhập/chip dùng token bo nút; ảnh/thẻ/panel dùng token bo ảnh. Nút tròn mang nghĩa điều khiển (đóng popup, mũi tên) giữ hình tròn.
- Cỡ nút và trạng thái focus ưu tiên thao tác được bằng chuột, bàn phím và chạm. Kiểm tra lại tương phản sau khi đổi màu; validator kiểm tra định dạng chứ không chứng nhận độ tương phản của mọi cặp màu.
- Font tự lưu trên site, có SIL Open Font License kèm theo trong `assets/fonts/`; không tải Google Fonts lúc khách mở trang. Provenance tại `assets/fonts/SOURCES.txt`.
- Desktop và mobile dùng chung dữ liệu. Chip danh mục có thể cuộn ngang trên điện thoại; trang không được tràn ngang. Điều hướng nhanh tự chia đều theo số nút liên hệ đang có.
- TikTok chỉ tải trình phát khi bấm. Ảnh bìa do chủ store chọn; lượt xem là nội dung nhập tay, không phải số đo tự đồng bộ.
- Footer có một bản duy nhất từ Store Kit, không phụ thuộc content block Pro. Logo chọn qua Thư viện; không chọn ảnh thì dùng tên thương hiệu dạng chữ.
- CSS DevVN chỉ là lớp tương thích nếu chủ store tự cài plugin có quyền sử dụng hợp lệ. Bộ phát hành không kèm DevVN và không tự bật chức năng mua nhanh.
- Mẫu chính sách luôn chờ chủ store hoàn thiện/phê duyệt. Bảng màu, preset và bản sao website không thay thế xác minh pháp lý.

## Thêm mẫu cho ngành khác

1. Dùng preset hiện tại làm cấu trúc, thay nội dung chung/bảng màu và tên danh mục phù hợp; không chép dữ liệu khách.
2. Nếu có nghiệp vụ riêng (như thuốc kê đơn), đặt vào module riêng. Không thêm điều kiện ngành hàng vào lõi hoặc CSS.
3. Dùng SKU/slug trong cấu hình chuyển store. Không ghi cứng ID sản phẩm, trang, danh mục hoặc media từ store nguồn.
4. Kiểm tra mẫu ở desktop và mobile, catalog/sản phẩm/giỏ, logo và footer; chạy `wp store-kit doctor` trước bàn giao.

## Quy tắc cập nhật

- Sửa source trong `components/`, tăng phiên bản cho release mới, cập nhật changelog rồi đóng gói lại.
- Không sửa plugin/theme vendor hoặc bản code đã cài để dùng làm nguồn mẫu mới.
- Sao lưu cả file và database của store trước cập nhật; cài thử trên staging. Không chạy setup để áp lại preset lên store đang có dữ liệu thật.
- Bản export layout không phải backup toàn website. Nó không chứa tài khoản, đơn hàng, dữ liệu cá nhân, thanh toán hoặc toàn bộ ảnh.
