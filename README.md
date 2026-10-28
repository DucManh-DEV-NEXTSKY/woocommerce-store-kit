# WooCommerce Store Kit 1.0.0

Bộ dựng store mới gồm plugin quản lý, child theme Blocksy Free, hệ thống style token, preset và module thú y tùy chọn. Đây là một sản phẩm riêng; không thay plugin/theme đang chạy của website khách hiện tại.

## Gói phát hành

| File trong `dist/` | Mục đích |
|---|---|
| `store-kit-1.0.0.zip` | Plugin nội dung, cài đặt, video, setup và chuyển layout |
| `store-kit-veterinary-1.0.0.zip` | Module thú y, chỉ kích hoạt khi cần |
| `store-kit-child-1.0.0.zip` | Child theme; cần theme cha Blocksy Free |
| `store-kit-source-1.0.0.zip` | Toàn bộ source component, công cụ build/setup, lock và hướng dẫn để phát triển tiếp |
| `wordpress-store-kit-1.0.0.zip` | WordPress sạch + WooCommerce/Blocksy + bộ kit + công cụ cài đặt |
| `manifest.json` | Phiên bản, nguồn dependency, dung lượng và SHA256 của các gói |

Bản WordPress mẫu **không có database/config/tài khoản cài sẵn**. Dữ liệu nền được tạo bằng recipe khi cài: mỗi store có database, mật khẩu và salts riêng. Cách này tránh clone cả dữ liệu khách hoặc khóa truy cập. Không dùng một store đã vận hành để tạo bản mẫu mới.

## Cách nhanh nhất trên hosting đã có WordPress

1. Cài/kích hoạt **WooCommerce** và cài theme **Blocksy Free** từ WordPress.org.
2. Upload `store-kit-1.0.0.zip` trong Plugins, kích hoạt. Upload/kích hoạt `store-kit-child-1.0.0.zip` trong Giao diện.
3. Nếu dựng store thú y, cài thêm module Veterinary. Store ngành khác không cần module này.
4. Vào **Store Kit → Thiết lập / Setup**, chọn `neutral` hoặc `pigvet`; chọn dữ liệu DEMO nếu cần xem bố cục và tạo nền store.
5. Điền tên, logo, liên hệ; chọn **Design** để chọn/chỉnh style. Thêm sản phẩm thật rồi chọn chúng trong trang chủ/nhóm nhu cầu/video.
6. Hoàn thiện và duyệt chính sách, thuế/hóa đơn, thanh toán và giao hàng; xóa DEMO trước khi mở bán.

Thiết lập chỉ dành cho store mới hoặc tài nguyên do kit tạo. Nếu có dữ liệu kinh doanh nhưng chưa được kit quản lý, công cụ từ chối thay vì ghi đè. Với store cũ, sao lưu và dùng **import layout** có kiểm tra ánh xạ.

## Cài từ bản WordPress sạch

Giải nén `wordpress-store-kit-1.0.0.zip` vào thư mục mới. Tạo database MySQL trống trên hosting. Yêu cầu: Python 3.10+, PHP CLI 8.1+ và các extension WordPress/WooCommerce; web server/PHP/MySQL được hosting cấu hình bình thường. WP-CLI đi kèm có license MIT.

Cấp biến môi trường cho phiên cài đặt (không đưa vào source/git):

| Biến | Nội dung |
|---|---|
| `STORE_KIT_DB_NAME` | Tên database mới |
| `STORE_KIT_DB_USER` | User database |
| `STORE_KIT_DB_PASSWORD` | Mật khẩu database |
| `STORE_KIT_DB_HOST` | Host database; mặc định `localhost` |
| `STORE_KIT_ADMIN_PASSWORD` | Tùy chọn: mật khẩu admin mới, ít nhất 16 ký tự; bỏ qua để nhập kín qua prompt |

Từ thư mục WordPress vừa giải nén:

```text
python store-kit/tools/setup.py --path . --url https://shop.example.com --title "Tên store mới" --admin-user storeadmin --admin-email owner@example.com --preset neutral
```

`shop.example.com` và email trong ví dụ phải đổi thành thông tin người nhận. Tạo tài khoản mới, không dùng chung tài khoản/mật khẩu giữa khách. Script không in mật khẩu hoặc ghi vào file phát hành. Không truyền mật khẩu trong tham số dòng lệnh.

Script tạo config/salts mới, cài WordPress, kích hoạt WooCommerce/plugin/theme, tạo nền store, tắt theo dõi WooCommerce, giữ site không index và bật coming-soon trên môi trường production. Hoàn thiện thông tin và kiểm tra xong mới chủ động mở cửa hàng/index. Chạy lại trên instance do công cụ tạo giữ nội dung đã chỉnh; không đổi URL hoặc đặt lại mật khẩu một cách ngầm định.

Nếu hosting không có Python/SSH, dùng cách upload component + màn hình Setup ở trên. Không cần bản backup của website khách.

Setup chọn permalink `/%postname%/` khi store mới chưa có cấu trúc, giữ nguyên lựa chọn của store đã thiết lập. Apache cần bật rewrite và cho phép `.htaccess`/AllowOverride phù hợp; công cụ thử sinh rule bằng WordPress khi có quyền ghi. Nginx cần rule WordPress trong cấu hình server:

```nginx
location / {
    try_files $uri $uri/ /index.php?$args;
}
```

Không chép đè cấu hình hosting đang có. Sau cài phải mở thử các liên kết shop/video/bài viết; preview loopback dùng router đi kèm.

## Xem thử cục bộ trên Windows

Từ **source `store-kit/`**:

```text
python tools/build.py --fetch-only
python tools/setup.py --path .work/demo-store --url http://127.0.0.1:8793 --title "Demo Store" --admin-user storeadmin --admin-email owner@example.test --database sqlite --preset neutral --demo --serve
```

Trên Windows không có PHP, setup tải PHP portable x64 từ nguồn PHP chính thức và xác minh SHA256. Môi trường này riêng trong `.work/`, không thay PHP hệ thống. Trên Linux/macOS cần PHP CLI sẵn có. SQLite là chế độ preview loopback; production dùng MySQL.

`--serve` khởi động PHP development server bằng đúng runtime/extension của môi trường này; Ctrl+C để dừng. Bỏ cờ này nếu chỉ cần tạo dữ liệu, chưa mở server. Trong bản WordPress ZIP, chạy `store-kit/tools/setup.py --path .` với cùng các lựa chọn preview thay cho đường dẫn source ở ví dụ trên.

Mở `http://127.0.0.1:8793/` và `/wp-admin/`, đăng nhập bằng mật khẩu vừa nhập. Chỉ bind `127.0.0.1`; PHP development server không dùng làm server production. Database preview nằm ở thư mục riêng cạnh webroot, không nằm trong archive phát hành.

## Nội dung nền và preset

- `neutral`: retail chung, không bật quy tắc ngành thú y.
- `pigvet`: màu đỏ trầm/kem và tổ chức nội dung theo ngành; cần module Veterinary đã cài. **Không chứa tên pháp lý, MST, số điện thoại, email, social, logo hoặc sản phẩm của một khách cụ thể.**
- Tạo trang chủ, cửa hàng/giỏ/thanh toán/tài khoản theo WooCommerce, Giới thiệu, Video, Kiến thức, menu native và tám trang chính sách chờ duyệt.
- `--demo` tạo bốn sản phẩm minh họa, có sản phẩm biến thể. Chúng có nhãn DEMO và bị chặn mua; không phải hàng hóa sẵn sàng bán.
- Không tự cấu hình tài khoản nhận tiền, nhà vận chuyển, SMTP, license, xác nhận BCT hoặc công bố chính sách.

Đọc `STYLE-RULES.md` trước khi tùy chỉnh token/component. Preset style có thể thay độc lập với nội dung hoặc nghiệp vụ.

## Các lệnh vận hành

Dùng WP-CLI trong thư mục site (hoặc `php /đường/dẫn/wp-cli.phar --path=/đường/dẫn/site ...`):

```text
wp store-kit setup --preset=neutral --demo
wp store-kit export --file=/private/layout.json
wp store-kit import --file=/private/layout.json
wp store-kit doctor
wp store-kit demo-purge
```

- **Setup:** tạo nền idempotent; chạy lại không ép preset lên dữ liệu đã chỉnh.
- **Export/import:** chỉ layout có version, design, nội dung trang chủ, video và tham chiếu theo SKU/slug. Không xuất thông tin chủ store, tài khoản, đơn hàng, khóa dịch vụ hoặc toàn bộ option blobs. File đích export phải chưa tồn tại.
- Store nhận cần có sản phẩm/danh mục/trang tương ứng; thiếu tham chiếu sẽ báo lỗi trước khi ghi, không đoán ID khác. Ảnh/logo cần chọn lại; không chuyển ID/URL media của store nguồn sang store nhận.
- Import giữ thông tin liên hệ/pháp lý/BCT của store nhận, các lựa chọn mở video ngoài và hàng rào mua hàng. Nó không tự xuất bản chính sách.
- **Doctor:** báo trạng thái kỹ thuật và các mục còn thiếu, không chứng nhận website đủ điều kiện pháp luật.
- **Demo purge:** chỉ xóa tài nguyên demo do kit tạo và không được dùng trong đơn hàng/tài nguyên thật. Trường hợp cần giữ được báo rõ, không xóa dữ liệu khách hàng.
- Nếu ghi dữ liệu bị gián đoạn và còn khóa `store_kit_blueprint_lock`, phải kiểm tra instance/backup trước khi quản trị viên gỡ khóa. Không tự xóa khóa để thử lại liên tiếp.

## Module Veterinary

Kích hoạt chỉ cho store cần nghiệp vụ thú y. Cấu hình danh mục hạn chế ở tab Veterinary, mặc định slug `thuoc-ke-don`; danh mục con cũng chịu quy tắc. Module thêm trường “Dùng cho”/“Liều theo nhãn”, nhãn và thông báo ngành hàng, chặn thuốc thuộc danh mục hạn chế qua simple/variation/cart/Store API và adapter DevVN.

Không để sản phẩm cần hạn chế nằm ngoài phân loại. Không coi nút Zalo là căn cứ hợp thức hóa bán thuốc hoặc coi module là xác minh giấy phép. Chỉ người có trách nhiệm mới được thay danh mục/quy tắc.

## Build, cập nhật và quyền sử dụng

Source chuẩn nằm trong `components/`. Không dùng thư mục website đã cài làm nguồn release.

```text
python tools/build.py --fetch-only
python tools/build.py
```

Lệnh thường dùng dependency đúng phiên bản/SHA256 trong `dependencies.lock.json`; chỉ chạy `--refresh-lock --fetch-only` khi chủ động nâng dependency và kiểm tra lại. Build đóng gói code/recipe, không đọc database của instance. `STORE_KIT_AUDIT_DENYLIST` có thể trỏ tới file JSON riêng chứa các chuỗi nhạy cảm cần chặn, nằm ngoài gói phát hành.

Trước cập nhật store thật: backup file+database, cài thử trên staging, kiểm tra checkout và dữ liệu, rồi cập nhật component tương ứng. Cấu hình từng khách giữ trong database; không phát lại preset khi cập nhật. Bộ kit không có dịch vụ cập nhật SaaS/telemetry riêng.

Các dependency công khai có giấy phép kèm theo; xem `THIRD-PARTY-NOTICES.md`. Không phân phối Blocksy Pro, DevVN, license hoặc khóa dịch vụ của khách. Plugin trả phí nếu cần phải được chủ store mua/cấp quyền riêng.
