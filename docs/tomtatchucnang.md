# Tóm Tắt Chức Năng ChamCongV2

Tài liệu này tổng hợp chức năng theo code hiện tại. Trạng thái chỉ ghi "đã xong" khi logic đã có trong code; các luồng cần thiết bị/camera/mobile hoặc cần test end-to-end được ghi "cần test thêm".

## 1. Đăng nhập / đăng xuất

- Role được dùng: guest, tất cả user đã đăng nhập.
- File chính: `routes/auth.php`, `app/Http/Controllers/Auth/AuthenticatedSessionController.php`, `resources/views/auth/login.blade.php`, `resources/views/layouts/app.blade.php`.
- Logic chính: guest vào `/login`; POST login dùng `LoginRequest`; sau login redirect dashboard; logout POST `/logout`; layout có check session status và redirect `/session-expired` khi mất session.
- Trạng thái hiện tại: cần test thêm. Lý do: từng có lỗi 419/Page Expired, cần test mobile/PWA/session thực tế.

## 2. Phân quyền admin / manager / staff / cashier

- Role được dùng: admin, manager, staff, cashier.
- File chính: `routes/web.php`, `app/Http/Middleware/RoleMiddleware.php`, `app/Models/User.php`.
- Logic chính: middleware `role:*` chặn route theo role; admin quản trị hệ thống; manager quản lý chi nhánh; staff chấm công cá nhân; cashier có quyền QR/giao ca/chấm công theo route hiện tại.
- Trạng thái hiện tại: cần test thêm. Lý do: route đã có, nhưng cần test từng role bằng tài khoản thật.

## 3. Quản lý user / nhân sự

- Role được dùng: admin, manager.
- File chính: `app/Http/Controllers/UserController.php`, `resources/views/users/index.blade.php`, `resources/views/users/create.blade.php`, `resources/views/users/edit.blade.php`, `app/Models/User.php`.
- Logic chính: admin xem/sửa/tạo user nhiều role; manager chỉ quản lý staff/cashier cùng chi nhánh; user mới mặc định `employment_status = probation`; admin được sửa Zalo, CCCD, ngày vào làm, employment status; manager bị giới hạn hơn.
- Trạng thái hiện tại: cần test thêm. Lý do: có logic mới liên quan thông tin nhân sự và lịch sử công tác cần test form thực tế.

## 4. Quản lý chi nhánh

- Role được dùng: admin/manager/cashier/staff dùng gián tiếp.
- File chính: `app/Models/Branch.php`, migration `create_branches_table`, `QrController`, `AttendanceController`, `UserController`.
- Logic chính: chi nhánh được gán cho user, QR token, GPS radius; attendance hiện tính chi nhánh gần nhất theo tọa độ user khi checkin/checkout.
- Trạng thái hiện tại: cần test thêm. Lý do: chưa thấy CRUD riêng cho chi nhánh trong routes/views; chỉ có model/bảng và dùng trong các module khác.

## 5. Gán ca

- Role được dùng: admin, manager, cashier.
- File chính: `app/Http/Controllers/ShiftAssignmentController.php`, `resources/views/shift_assignments/index.blade.php`, `app/Models/ShiftAssignment.php`.
- Logic chính: admin gán ca cho staff/cashier; manager gán staff/cashier cùng chi nhánh; cashier gán cho bản thân và staff cùng chi nhánh; không cho gán ca trước `start_work_date`; `updateOrCreate` theo `user_id + work_date`.
- Trạng thái hiện tại: cần test thêm.

## 6. Quản lý ca làm

- Role được dùng: admin.
- File chính: `app/Http/Controllers/ShiftController.php`, `resources/views/shifts/*.blade.php`, `app/Models/Shift.php`.
- Logic chính: tạo/sửa ca với tên ca, giờ bắt đầu, giờ kết thúc, ngưỡng trễ `late_after_minutes`; hỗ trợ ca qua đêm khi giờ kết thúc <= giờ bắt đầu trong các hàm tính giờ.
- Trạng thái hiện tại: cần test thêm.

## 7. QR chấm công

- Role được dùng: QR display cho admin/manager/cashier; scan cho staff/cashier/manager.
- File chính: `app/Http/Controllers/QrController.php`, `app/Http/Controllers/AttendanceController.php`, `resources/views/qr/show.blade.php`, `resources/views/attendance/scanner.blade.php`.
- Logic chính: QR tạo token random 40 ký tự, gán `branch_id`, hết hạn sau 12 giây; scan token hợp lệ thì lưu session `qr_token`, `qr_branch_id`, `qr_scanned_at`; session QR chỉ cho phép trong 180 giây.
- Trạng thái hiện tại: cần test thêm.

## 8. GPS

- Role được dùng: staff, cashier, manager khi chấm công.
- File chính: `AttendanceController@store`, `app/Models/Branch.php`.
- Logic chính: bắt buộc latitude/longitude; lấy tất cả branch có tọa độ; tính branch gần nhất bằng Haversine; cho phép nếu distance <= `gps_radius`, fallback 20m; sai thì báo ngoài phạm vi.
- Trạng thái hiện tại: cần test thêm. Lý do: phụ thuộc GPS thiết bị và tọa độ branch.

## 9. Camera QR

- Role được dùng: staff, cashier, manager.
- File chính: `resources/views/attendance/scanner.blade.php`, `resources/css/face-camera.css`.
- Logic chính: dùng `html5-qrcode`; ưu tiên `facingMode environment`; fallback camera khả dụng; nút đổi camera toggle environment/user; stop stream cũ trước khi đổi.
- Trạng thái hiện tại: cần test thêm. Lý do: phụ thuộc trình duyệt, quyền camera, thiết bị nhiều/ít camera.

## 10. Đăng ký gương mặt

- Role được dùng: staff, cashier, manager.
- File chính: `app/Http/Controllers/FaceController.php`, `resources/views/face/create.blade.php`, migration `add_face_descriptor_to_users_table`.
- Logic chính: frontend dùng face-api detect 1 mặt và liveness nhìn thẳng/quay trái/quay phải/nhìn thẳng; lấy descriptor 128 chiều; backend validate descriptor là mảng numeric rồi lưu vào `users.face_descriptor` của user đang đăng nhập.
- Trạng thái hiện tại: cần test thêm. Lý do: phụ thuộc camera/model AI và luồng liveness từng có lỗi.

## 11. Xác minh gương mặt

- Role được dùng: staff, cashier, manager.
- File chính: `AttendanceController@create/store`, `FaceController@verifyPass`, `resources/views/attendance/checkin.blade.php`.
- Logic chính: lấy descriptor của `auth()->user()`; face-api so khớp distance với threshold 0.5; chỉ pass khi đúng 1 khuôn mặt và distance hợp lệ; backend lưu session `face_verified_user_id`, `face_verified_at`, `face_verified_distance`; attendance store yêu cầu `face_verified=1` và session còn hạn 120 giây.
- Trạng thái hiện tại: cần test thêm.

## 12. Checkin / checkout

- Role được dùng: staff, cashier, manager.
- File chính: `AttendanceController.php`, `resources/views/attendance/checkin.blade.php`, `resources/views/attendance/history.blade.php`, `app/Models/Attendance.php`.
- Logic chính: phải có face descriptor, QR session, face session, GPS hợp lệ; nếu có attendance chưa checkout và còn trong giờ cho phép thì checkout; nếu quá giờ cho phép thì đánh dấu `missing_checkout` và tạo checkin mới; checkout tính worked minutes và overtime nếu có ca.
- Trạng thái hiện tại: cần test thêm. Lý do: cần test end-to-end QR + face + GPS + DB.

## 13. Ca cố định

- Role được dùng: staff/cashier/manager khi chấm công; admin/manager/cashier khi gán ca.
- File chính: `ShiftAssignmentController`, `AttendanceController`, `DashboardController`, `AttendanceStatisticController`, `PayrollController`.
- Logic chính: nếu có `ShiftAssignment` đúng ngày thì attendance gán `shift_id`; checkin sớm tối đa 1 tiếng trước ca; đi trễ tính sau `start_at + late_after_minutes`; checkout sau `end_at` mới tính tăng ca; ca qua đêm được cộng ngày kết thúc.
- Trạng thái hiện tại: cần test thêm.

## 14. Ca gãy / ca tự do

- Role được dùng: staff, cashier, manager.
- File chính: `AttendanceController`, migration `make_shift_id_nullable_in_attendances_table`.
- Logic chính: nếu không có assignment hôm nay thì `shift_id = null`; vẫn cho checkin/checkout; checkout limit mặc định 24h từ checkin. Lưu ý: code hiện tại checkout ca tự do đang để overtime = 0.
- Trạng thái hiện tại: đang lỗi / cần sửa thêm. Lý do: yêu cầu trước đó "ca gãy toàn bộ worked_minutes = overtime_minutes" nhưng code hiện tại chưa tính overtime cho case không có shift.

## 15. Tăng ca

- Role được dùng: staff, cashier, manager; admin/manager xem báo cáo.
- File chính: `AttendanceController`, `AttendanceReportController`, `AttendanceStatisticController`, `PayrollController`.
- Logic chính: với ca cố định, chỉ tính overtime khi `checkout_at > shift end`; `overtime_minutes` là phút vượt giờ kết thúc; `overtime_hours = round(overtime_minutes / 60, 2)` khi lưu attendance.
- Trạng thái hiện tại: cần test thêm. Lưu ý: ca tự do chưa tính overtime theo yêu cầu cũ.

## 16. Tính công

- Role được dùng: tất cả role xem tùy quyền.
- File chính: `DashboardController`, `AttendanceStatisticController`, `PayrollController`, `AttendanceReportController`.
- Logic chính: worked minutes tính từ checkin đến checkout; work unit = worked/required shift minutes, tối đa 1 trong dashboard/report; checkout sớm/không checkout được xem thiếu công; payroll tổng hợp công, trễ, về sớm, tăng ca, vắng.
- Trạng thái hiện tại: cần test thêm. Lý do: logic tính công nằm rải rác ở nhiều controller.

## 17. Nghỉ không phép

- Role được dùng: admin/manager xem, staff/cashier thấy trên dashboard cá nhân.
- File chính: `DashboardController`, `AttendanceStatisticController`, `PayrollController`.
- Logic chính: có shift assignment nhưng không có attendance trong ngày qua/đến hiện tại thì hiện `absent` / `Không phép`; payroll cộng `unauthorized_absence_days = 1`.
- Trạng thái hiện tại: cần test thêm.

## 18. Đơn xin phép / OFF

- Role được dùng: staff/cashier/manager tạo đơn; admin/manager duyệt/từ chối.
- File chính: `LeaveRequestController`, `resources/views/leave_requests/*.blade.php`, `app/Models/LeaveRequest.php`.
- Logic chính: chỉ cho gửi đơn từ hôm nay trở đi; end date >= start date; status ban đầu `pending`; admin duyệt toàn bộ, manager duyệt staff/cashier cùng chi nhánh; đơn pending quá 3 ngày tự động reject khi vào module.
- Trạng thái hiện tại: cần test thêm.

## 19. Bổ sung công

- Role được dùng: staff/cashier/manager/admin tạo; admin/manager duyệt/từ chối.
- File chính: `AttendanceSupplementRequestController`, `resources/views/attendance_supplements/*.blade.php`, `app/Models/AttendanceSupplementRequest.php`.
- Logic chính: tạo đơn bổ sung/cập nhật công theo ngày; staff/cashier chỉ tạo cho mình; manager tạo/duyệt trong chi nhánh; admin toàn bộ; manager/admin tạo có thể auto approve; khi approve thì tạo/cập nhật attendance.
- Trạng thái hiện tại: cần test thêm.

## 20. Dashboard

- Role được dùng: admin, manager, staff, cashier.
- File chính: `DashboardController`, `resources/views/dashboard/admin.blade.php`, `resources/views/dashboard/staff.blade.php`.
- Logic chính: staff/manager/cashier dùng dashboard lịch cá nhân theo tháng; admin xem tổng quan nhân sự, checkin hôm nay, trễ, nghỉ phép, thiếu checkout, đơn chờ duyệt, cảnh báo chưa có face.
- Trạng thái hiện tại: cần test thêm. Lưu ý: manager hiện đang dùng view staff theo code, không phải `dashboard/manager.blade.php`.

## 21. Thống kê / báo cáo công

- Role được dùng: admin, manager.
- File chính: `AttendanceReportController`, `AttendanceStatisticController`, `resources/views/attendance_reports/*.blade.php`, `resources/views/attendance_statistics/index.blade.php`.
- Logic chính: attendance reports liệt kê/sửa công; manager chỉ thấy user cùng chi nhánh; statistics tổng hợp theo tháng, lọc chi nhánh/search, tính status từng ngày, overtime, trễ, vắng.
- Trạng thái hiện tại: cần test thêm.

## 22. Bảng lương / export

- Role được dùng: admin, manager.
- File chính: `PayrollController`, `resources/views/payrolls/index.blade.php`, `resources/views/payrolls/export.blade.php`.
- Logic chính: tổng hợp công theo tháng, số ngày công, vắng không phép, trễ, về sớm, tăng ca; các cột tiền hiện mặc định 0; export Excel đang trả về HTML/XLS với UTF-8 header.
- Trạng thái hiện tại: cần test thêm. Lý do: cần mở file export bằng Excel để xác nhận font/format.

## 23. PWA / mobile

- Role được dùng: tất cả user.
- File chính: `resources/views/layouts/app.blade.php`, `resources/views/layouts/partials/assets.blade.php`, `resources/views/components/mobile-nav.blade.php`, `public/manifest.json`, `public/sw.js`, `resources/css/navigation.css`, `resources/css/face-camera.css`.
- Logic chính: manifest standalone portrait; service worker skip waiting, claim clients, xóa cache cũ khi activate; asset version hiện `maxsim-4`; mobile bottom nav có panel cho Chấm Công, Đơn Từ, Tài Khoản; CSS riêng cho camera/QR mobile.
- Trạng thái hiện tại: cần test thêm. Lý do: cần test trên mobile browser/PWA thật.

## 24. Lịch sử công tác

- Role được dùng: admin ghi lịch sử khi sửa user.
- File chính: `UserController`, `app/Models/WorkHistory.php`, migration `2026_06_07_000005_create_work_histories_table.php`, `app/Models/User.php`.
- Logic chính: khi admin update user và có đổi `branch_id` hoặc `role`, hệ thống tạo row `work_histories` với old/new branch, old/new role, effective date hôm nay, changed_by là admin; department fields hiện null vì project chưa có department.
- Trạng thái hiện tại: cần test thêm. Lý do: đã có model/migration/controller, chưa có UI xem lịch sử.

## 25. Thử việc lên chính thức

- Role được dùng: admin cấu hình/sửa tay; user được update tự động sau checkout.
- File chính: `EmploymentStatusService`, `SettingController`, `UserController`, `settings/edit.blade.php`.
- Logic chính: user mới mặc định probation; số ngày công hợp lệ cấu hình bằng setting `probation_required_workdays`, mặc định 3; sau checkout completed thì đếm số ngày attendance completed có checkin/checkout/worked > 0, mỗi ngày tính 1 lần; đủ điều kiện thì update official.
- Trạng thái hiện tại: cần test thêm.

## 26. Thông báo đơn chờ duyệt

- Role được dùng: admin, manager.
- File chính: `routes/web.php`, `App\Support\AppNavigation`, `resources/views/layouts/app.blade.php`, `resources/views/layouts/navigation.blade.php`, `resources/views/components/mobile-nav.blade.php`.
- Logic chính: endpoint `/notifications/pending-count` trả tổng đơn bổ sung công và đơn off đang pending; layout poll mỗi 5 giây và cập nhật badge nav.
- Trạng thái hiện tại: cần test thêm.

## Phần còn thiếu / cần lưu ý

- Chưa thấy CRUD riêng cho chi nhánh trong route/view hiện tại.
- Chưa có UI xem `work_histories`; mới có ghi DB khi admin đổi branch/role.
- Ca gãy/ca tự do hiện chưa tính `worked_minutes = overtime_minutes` theo yêu cầu nghiệp vụ cũ.
- Test runner tự động chưa khả dụng trong project theo lần kiểm tra trước: không có `vendor/bin/phpunit.bat`, `php artisan test` không được định nghĩa.
- Nhiều luồng phụ thuộc thiết bị thật: camera QR, face-api, GPS, PWA cache, mobile responsive.
