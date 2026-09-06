# MAXSIM — Bộ UI tách riêng

## File UI
- `01_form_danh_gia_khach.html`: Form khách đánh giá cuối cùng.
- `02_popup_thong_bao_noi_bo.html`: Popup realtime + modal chi tiết + xử lý phản hồi.
- `03_thong_ke_nhan_su.html`: Trang thống kê nhân sự.
- `04_thong_ke_quan_ly.html`: Trang thống kê quản lý.
- `05_thong_ke_admin.html`: Trang thống kê Admin.
- `00_danh_sach_ui.html`: Trang mở nhanh toàn bộ UI.

## Dữ liệu hiển thị
Các màn thống kê và popup chỉ hiển thị:
- Nhân sự được đánh giá
- Mức Tốt / Trung bình / Tệ
- Ghi chú
- Thời gian
- Trạng thái thưởng

Không có số phòng, tên khách hoặc mã khách.

## PWA
Các file dùng chung `manifest.webmanifest` và `sw.js`. Chạy bằng localhost/HTTPS để cài hoặc cache PWA.
