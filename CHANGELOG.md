# Changelog

## 1.0.0

- Tách bộ Store Kit khỏi website khách: plugin lõi dùng chung, child theme Blocksy Free và module Veterinary tùy chọn; không thay bản site hiện hữu.
- Menu cài đặt tập trung cho thương hiệu/logo, liên hệ, trang chủ, nhóm nhu cầu, video, pháp lý, vận hành, giao diện và thiết lập/chuyển cấu hình.
- Style tokens có validation: màu, font self-hosted, bo góc, chiều rộng, spacing, cỡ chữ; ba preset hình thức và hai preset khởi tạo store.
- Foundation setup idempotent, nhận diện tài nguyên do kit tạo, từ chối ghi đè store cũ, tạo policy dạng nháp và demo bị chặn mua.
- Chuyển layout theo SKU/slug và full TikTok URL thay ID; giữ identity/contact/BCT/media/safety của store nhận, báo tham chiếu thiếu trước khi ghi.
- Demo cleanup kiểm tra tham chiếu đơn hàng và biến thể để không làm mất lịch sử hoặc dữ liệu thật.
- Module Veterinary tách trường/liều/nhãn/nghiệp vụ ngành hàng, gồm bảo vệ sản phẩm đơn/biến thể, native cart, Store API và adapter DevVN.
- Recipe cài WordPress mới bằng MySQL hoặc preview loopback SQLite, tài khoản/salts mới; mật khẩu chỉ qua prompt/env/stdin, không nhúng vào archive hoặc dòng lệnh.
- Dependency lock SHA256, gói ZIP riêng từng component, source đầy đủ và gói WordPress không kèm DB/config/uploads/tài khoản. Chỉ dependency công khai được phép phân phối; giữ license upstream.
- Đã chạy thực tế hai preset trên hai WordPress riêng; đổi style qua form rồi quan sát frontend; setup lặp giữ chỉnh sửa; import cross-ID giữ thông tin store đích; cấu hình sai bị từ chối; demo không mua được; RX bị chặn qua Store API/direct-order; demo purge giữ đơn lịch sử và sản phẩm thật.

Chi tiết sử dụng: `README.md`. Quy tắc thiết kế: `STYLE-RULES.md`. Phiên bản/nguồn dependency: `dependencies.lock.json`. Bằng chứng smoke của bản phát hành được lưu ngoài các component để không trộn dữ liệu kiểm thử vào gói cài.
