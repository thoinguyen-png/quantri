# CAMERA CHANGES LAST 3 DAYS - ChamCongV2

Ngày audit: 2026-06-15  
Phạm vi: Camera / QR / GPS / Face / permission / PWA cache / route-controller liên quan chấm công.  
Lưu ý: workspace hiện tại không có Git metadata (`git status` trả về `fatal: not a git repository`), nên phần "3 ngày gần đây" được xác định theo `LastWriteTime` của file và nội dung code hiện tại.

Mốc 3 ngày gần đây dùng trong báo cáo: từ 2026-06-12 đến 2026-06-15.

## 1. Danh sách file liên quan

| STT | File | Chức năng | Có sửa gần đây không | Mức độ nguy hiểm |
| --- | ---- | --------- | -------------------- | ---------------- |
| 1 | `resources/views/attendance/scanner.blade.php` | UI/JS quét QR, mở camera QR, đổi camera, redirect QR | Có - 2026-06-14 23:53:33 | Cao |
| 2 | `resources/views/attendance/checkin.blade.php` | UI/JS xác minh mặt, face-api, GPS, submit attendance | Có - 2026-06-14 23:52:39 | Cao |
| 3 | `resources/views/face/create.blade.php` | UI/JS đăng ký gương mặt, lấy descriptor | Có - 2026-06-14 23:52:55 | Cao |
| 4 | `resources/views/components/zalo-camera-warning.blade.php` | Cảnh báo Zalo/Facebook/Messenger/in-app browser | Có - 2026-06-14 23:53:07 | Thấp |
| 5 | `app/Http/Controllers/AttendanceController.php` | QR session, face session, GPS radius, checkin/checkout | Có - 2026-06-13 22:04:38 | Trung bình |
| 6 | `app/Http/Controllers/FaceController.php` | Lưu face descriptor, session verify-pass | Không trong 3 ngày - 2026-06-06 16:04:23 | Trung bình |
| 7 | `app/Http/Controllers/QrController.php` | Tạo QR token, refresh QR display | Không trong 3 ngày - 2026-06-06 16:08:54 | Trung bình |
| 8 | `routes/web.php` | Route attendance/face/qr/session | Có - 2026-06-13 16:10:53 | Trung bình |
| 9 | `public/sw.js` | Service worker/PWA cache | Có - 2026-06-14 23:53:16 | Trung bình |
| 10 | `resources/views/layouts/app.blade.php` | Service worker register, session status/keepalive | Có - 2026-06-14 22:21:40 | Trung bình |
| 11 | `resources/views/layouts/partials/assets.blade.php` | Vite assets/App version input cho SW | Không trong 3 ngày - 2026-06-10 21:36:55 | Trung bình |
| 12 | `resources/css/face-camera.css` | CSS camera frame, QR reader, mobile UI | Không trong 3 ngày - 2026-06-07 18:23:20 | Thấp |
| 13 | `app/Models/User.php` | `face_descriptor`, status/casts user | Không trong 3 ngày - 2026-06-08 22:35:15 | Trung bình |
| 14 | `app/Models/Attendance.php` | Fillable/casts attendance, work_date scopes | Có - 2026-06-13 16:18:07 | Trung bình |
| 15 | `app/Models/QrToken.php` | Model QR token/branch relation | Không trong 3 ngày - 2026-06-06 16:08:45 | Trung bình |
| 16 | `app/Support/AppNavigation.php` | Link mobile/nav đến scanner/QR | Không trong 3 ngày - 2026-06-05 15:04:04 | Thấp |
| 17 | `database/migrations/2026_05_25_164044_add_face_descriptor_to_users_table.php` | Migration `users.face_descriptor` | Không trong 3 ngày - 2026-05-25 16:41:05 | Trung bình |
| 18 | `database/migrations/2026_05_25_073334_create_qr_tokens_table.php` | Migration `qr_tokens` | Không trong 3 ngày - 2026-05-25 14:33:54 | Trung bình |
| 19 | `database/migrations/2026_06_06_000001_add_branch_id_to_qr_tokens_table.php` | Migration `qr_tokens.branch_id` | Không trong 3 ngày - 2026-06-06 16:09:04 | Trung bình |

## 2. File đã bị sửa trong 3 ngày gần đây

### `resources/views/attendance/scanner.blade.php`

- Chức năng: Trang quét QR chấm công.
- Đoạn chính:
  - `initScanner()`
  - `requestCameraPermission()`
  - `startQrCamera()`
  - `stopScanner()`
  - `onScanSuccess()`
  - `switchCamera()`
- Logic camera hiện tại:
  - Dùng `Html5Qrcode`.
  - Không còn dùng `navigator.mediaDevices.getUserMedia()` trực tiếp trong QR scanner.
  - `Html5Qrcode.start()` là owner mở camera.
  - Camera mode lưu vào `localStorage` key `chamcongv2_qr_camera_mode`.
  - Ưu tiên `environment`, fallback config đơn giản, fallback `{}`, rồi fallback `Html5Qrcode.getCameras()` theo deviceId.
- Thay đổi đáng chú ý:
  - QR tự gọi mở camera trong `initScanner()`.
  - Đã bỏ flow mở camera 2 lần bằng preview stream tạm.
  - Redirect QR dùng `targetUrl.pathname + targetUrl.search` nếu path bắt đầu `/attendance/scan/`.
- Rủi ro có thể gây bug:
  - `Html5Qrcode.start()` với config `{}` có thể không ổn trên một số version library; fallback deviceId phía sau vẫn có nhưng cần test thật.
  - Nếu CDN `html5-qrcode` không tải được, QR không chạy.
  - Nếu browser trả lỗi khác ngoài danh sách fallback, UI sẽ báo lỗi chung.
  - QR sai domain nhưng đúng path `/attendance/scan/{token}` vẫn được chấp nhận theo path local; đây là chủ đích tránh domain cũ, nhưng cần reviewer xác nhận.

### `resources/views/attendance/checkin.blade.php`

- Chức năng: Xác minh gương mặt, lấy GPS, submit attendance.
- Đoạn chính:
  - `init()`
  - `startFaceCamera()`
  - `openFaceStream()`
  - `waitForVideoReady()`
  - `checkLivenessAndFace()`
  - `markFaceVerified()`
  - `acquireGpsPosition()`
  - `getGpsAndSubmit()`
  - `requestGpsPermission()`
- Logic camera/GPS hiện tại:
  - Camera face dùng `navigator.mediaDevices.getUserMedia()`.
  - Camera mode lưu vào `localStorage` key `chamcongv2_face_verify_camera_mode`.
  - Ưu tiên `facingMode: { ideal: faceCameraMode }`, fallback `video: true`.
  - Có `faceCheckRunId` để hủy vòng detect cũ khi đổi camera.
  - Có `waitForVideoReady()` trước detect.
  - GPS dùng `navigator.geolocation.getCurrentPosition()`.
  - GPS lấy nhanh trước: `enableHighAccuracy: false`, `timeout: 5000`, `maximumAge: 300000`.
  - Fallback chính xác hơn: `enableHighAccuracy: true`, `timeout: 10000`, `maximumAge: 0`.
- Thay đổi đáng chú ý:
  - Nếu chưa có `face_descriptor`, không mở camera và hiện popup đăng ký.
  - `init()` không còn lấy GPS trước khi chạy face; GPS chỉ lấy sau khi face/liveness pass.
  - Nếu GPS fail sau khi `face_verified=1`, `requestGpsPermission()` có thể lấy GPS lại và submit tiếp.
  - Message `NotReadableError` đã mềm hơn, không khẳng định chắc chắn camera bị app khác dùng.
- Rủi ro có thể gây bug:
  - `startPermissionFlow()` hiện chủ yếu mở camera, text UI vẫn cần kiểm tra có khớp wording không.
  - Nếu `face.verify-pass` đã set session nhưng GPS fail, cần test retry GPS trong 120 giây.
  - Face-api tải từ CDN; nếu mạng yếu/PWA offline, verify không chạy.
  - Vòng `checkLivenessAndFace()` dùng `setTimeout`, cần test đổi camera nhanh để chắc runId hủy đủ.

### `resources/views/face/create.blade.php`

- Chức năng: Đăng ký/cập nhật face descriptor.
- Đoạn chính:
  - `initRegisterFace()`
  - `startRegisterCamera()`
  - `openRegisterStream()`
  - `waitForVideoReady()`
  - `detectAndRegisterFace()`
  - `averageDescriptors()`
  - `submitFaceDescriptor()`
- Logic hiện tại:
  - Dùng `navigator.mediaDevices.getUserMedia()`.
  - Camera mode lưu vào `localStorage` key `chamcongv2_register_face_camera_mode`.
  - Ưu tiên camera trước (`user`), fallback `video: true`.
  - Lấy 3 samples (`REQUIRED_SAMPLES = 3`) rồi average descriptor.
  - Submit hidden input `face_descriptor` về `face.store`.
- Thay đổi đáng chú ý:
  - Có lock `startingCameraPromise`, `isStartingCamera`, `isRequestingCamera`, `isSwitchingCamera`.
  - Có disable nút trước submit để giảm double submit.
  - Message `NotReadableError` đã mềm hơn.
- Rủi ro có thể gây bug:
  - Face-api/model vẫn tải từ CDN.
  - Có cả `permissionPanel` và `camera-required-modal`; reviewer nên kiểm tra UI không bị chồng modal trên mobile.
  - `localStorage` chỉ nhớ camera mode, không kết luận permission, đúng yêu cầu.

### `resources/views/components/zalo-camera-warning.blade.php`

- Chức năng: Cảnh báo in-app browser.
- Thay đổi đáng chú ý:
  - Detect thêm `zalo`, `fbav`, `fb_iab`, `messenger`, `instagram`, `line`.
  - Không redirect tự động.
  - Có nút mở bằng trình duyệt và copy link.
- Rủi ro:
  - `target="_blank"` trong một số webview có thể vẫn mở trong webview, nên copy link là fallback chính.

### `app/Http/Controllers/AttendanceController.php`

- Chức năng:
  - `scanner()` trả view scanner.
  - `scan($token)` validate QR token, lưu session QR.
  - `store()` validate face session, QR session, GPS, checkin/checkout.
- Đoạn liên quan:
  - `qr_scanned_at` được lưu trong `scan()`.
  - QR session hạn 180 giây trong `store()`.
  - Face session kiểm tra `face_verified_user_id`, `face_verified_at`, hạn 120 giây.
  - GPS tìm branch gần nhất có tọa độ, so với `gps_radius`, fallback 20m.
- Rủi ro:
  - Nếu session bị mất do PWA/webview/cookie, sẽ bị quay lại scanner hoặc báo verify face.
  - Nếu `qr_tokens` bị xóa sau khi scan, code vẫn lấy `qrBranchId` từ session hoặc token; cần reviewer xác nhận nghiệp vụ có cần bắt token DB còn tồn tại ở `store()` không.

### `routes/web.php`

- Chức năng: Route auth/attendance/face/QR.
- Route liên quan tồn tại:
  - `attendance.scanner`
  - `attendance.scan`
  - `attendance.checkin`
  - `attendance.store`
  - `face.register`
  - `face.store`
  - `face.verify-pass`
  - `qr.unsupported`
  - `qr.show`
  - `qr.generate`
- Rủi ro:
  - Route attendance/face nằm trong middleware `auth` và role `staff,cashier,manager`; admin không đi flow attendance scanner trừ khi có route khác.

### `public/sw.js`

- Chức năng: Service worker/PWA cache.
- Đoạn chính:
  - `APP_VERSION`
  - `CACHE_SCHEMA_VERSION = 'camera-flow-v2'`
  - `DYNAMIC_PATHS` gồm `/attendance`, `/face`, `/qr`, `/login`, `/dashboard`, route CSRF/session.
  - Navigation/HTML/dynamic path dùng `fetch(request, { cache: 'no-store' })`.
- Thay đổi đáng chú ý:
  - Bump cache namespace để thiết bị lấy JS/CSS mới hơn.
- Rủi ro:
  - CDN `html5-qrcode` và `face-api` không thuộc origin nên service worker không quản lý cache/version.
  - Nếu browser/PWA giữ service worker cũ chưa activate, user cần reload hoặc unregister.

### `resources/views/layouts/app.blade.php`

- Chức năng:
  - Đăng ký service worker `/sw.js?v=...`.
  - Theo dõi session status/keepalive.
  - Fetch session endpoints với `cache: 'no-store'`.
- Rủi ro:
  - Nếu `appVersion` không đổi khi deploy, SW query version có thể không đổi; tuy `CACHE_SCHEMA_VERSION` trong `sw.js` đã đổi nhưng cần đảm bảo browser fetch lại SW file.

### `app/Models/Attendance.php`

- Chức năng: fillable/casts/relationships/scopes cho attendance.
- Liên quan camera/GPS:
  - Có các cột `checkin_latitude`, `checkin_longitude`, `checkout_latitude`, `checkout_longitude`.
  - Có `work_date` để không lấy checkout làm ngày công.
- Rủi ro:
  - Không mở camera trực tiếp nhưng dữ liệu do camera/GPS flow submit sẽ lưu vào model này.

## 3. So sánh logic camera hiện tại

### QR Scanner

- File xử lý: `resources/views/attendance/scanner.blade.php`.
- Mở camera bằng: `Html5Qrcode.start()`.
- Có `getUserMedia` không: không gọi trực tiếp trong file QR hiện tại.
- Có `Html5Qrcode` không: có.
- Có mở camera 2 lần không: hiện tại không còn flow tự mở `getUserMedia()` rồi mới gọi `Html5Qrcode.start()`.
- Có stop camera trước redirect không: có `stopScanner().finally(() => window.location.href = targetPath)`.
- Có chống spam scan không: có biến `scanned`.
- Có đổi camera không: có `switchCamera()`.
- Có lưu camera trước/sau không: có `localStorage` key `chamcongv2_qr_camera_mode`.

### Face Verify

- File xử lý: `resources/views/attendance/checkin.blade.php`.
- Mở camera bằng: `navigator.mediaDevices.getUserMedia()`.
- Load face-api từ:
  - `https://cdn.jsdelivr.net/npm/@vladmandic/face-api/dist/face-api.js`
  - model từ `https://cdn.jsdelivr.net/npm/@vladmandic/face-api/model/`
- Có chống spam detect không:
  - Có `faceCheckRunId`.
  - Có `submitted`.
  - Có `isStartingFaceCamera`, `isSwitchingCamera`.
- Có stop camera trước submit không: có `stopFaceCameraTracks()` trước `getGpsAndSubmit()`.
- Có tách lỗi camera/GPS không:
  - Đã tách tốt hơn: GPS chỉ chạy sau face pass, `requestGpsPermission()` riêng.
  - Vẫn cần test UI vì `permissionPanel` dùng chung một phần.
- Có thể báo nhầm lỗi không:
  - Ít hơn trước, nhưng vẫn có khả năng nếu catch ngoài cùng `init()` nhận lỗi load model/CDN rồi gọi camera permission UI. Nên reviewer kiểm tra message khi CDN face-api fail.

### Face Register

- File xử lý: `resources/views/face/create.blade.php`.
- Mở camera bằng: `navigator.mediaDevices.getUserMedia()`.
- Có lấy descriptor không: có `.withFaceDescriptors()`.
- Có lấy 1 mẫu hay nhiều mẫu: nhiều mẫu, `REQUIRED_SAMPLES = 3`, dùng `averageDescriptors()`.
- Có stop camera trước submit không: có `stopRegisterCameraTracks()`.
- Có chống double submit không: có `submitted`, `detectionRunId`, và disable nút trước submit.

## 4. Các lỗi nghiêm trọng/rủi ro đang thấy trong code hiện tại

1. CDN là điểm yếu production.
   - `html5-qrcode` và `face-api` đang tải từ CDN.
   - Nếu mạng yếu, bị chặn, hoặc PWA offline, QR/Face sẽ không hoạt động.

2. QR fallback `{}` cần test thật.
   - `Html5Qrcode.start({})` có thể không ổn tùy version library.
   - Nếu lỗi, fallback deviceId phía sau có thể cứu, nhưng reviewer nên test Android/iPhone thực tế.

3. Face verify khi model load fail có thể hiện nhầm lỗi camera.
   - `init()` catch chung cuối cùng gọi `showCameraPermissionHelp(error)`.
   - Nếu face-api/model CDN fail, user có thể thấy card camera thay vì lỗi AI/model rõ ràng.

4. PWA vẫn có thể dính service worker cũ nếu browser chưa activate bản mới.
   - `sw.js` đã có `skipWaiting()`/`clients.claim()` và cache version mới, nhưng thiết bị đã cài PWA có thể cần reload/hard refresh/unregister nếu đang kẹt bản rất cũ.

5. In-app browser warning chỉ cảnh báo.
   - Không tự redirect là an toàn, nhưng Zalo/Facebook có thể vẫn chặn camera.
   - Cần test copy link/mở browser thực tế.

6. QR path local hóa domain cũ.
   - QR sai origin nhưng đúng path `/attendance/scan/{token}` sẽ được chuyển sang domain hiện tại.
   - Đây có thể là fix domain cũ, nhưng cần reviewer xác nhận có muốn chấp nhận QR từ domain khác cùng path không.

7. Session/CSRF vẫn là điểm cần test.
   - `face.verify-pass` fetch đã gửi `X-CSRF-TOKEN` và `credentials: 'same-origin'`.
   - Form attendance có `@csrf`.
   - Nếu PWA/webview làm mất cookie, backend vẫn chặn bằng 419/session.

8. GPS retry sau face pass phụ thuộc thời hạn face session.
   - Backend face session hạn 120 giây.
   - Nếu user retry GPS lâu hơn, phải verify face lại.

## 5. Danh sách file cần gửi đi review

### Nhóm bắt buộc

Các file nếu thiếu thì không thể review/fix đúng:

- `resources/views/attendance/scanner.blade.php`
- `resources/views/attendance/checkin.blade.php`
- `resources/views/face/create.blade.php`
- `app/Http/Controllers/AttendanceController.php`
- `app/Http/Controllers/FaceController.php`
- `app/Http/Controllers/QrController.php`
- `routes/web.php`
- `public/sw.js`
- `resources/views/layouts/app.blade.php`
- `resources/views/components/zalo-camera-warning.blade.php`
- `resources/css/face-camera.css`

### Nhóm nên gửi thêm

Các file hỗ trợ hiểu route/session/cache/UI/database:

- `resources/views/layouts/partials/assets.blade.php`
- `app/Models/User.php`
- `app/Models/Attendance.php`
- `app/Models/QrToken.php`
- `app/Support/AppNavigation.php`
- `database/migrations/2026_05_25_164044_add_face_descriptor_to_users_table.php`
- `database/migrations/2026_05_25_073334_create_qr_tokens_table.php`
- `database/migrations/2026_06_06_000001_add_branch_id_to_qr_tokens_table.php`

## 6. Không sửa code

Bước này chỉ audit, tổng hợp và gom file. Không refactor, không đổi route, không đổi database, không format lại file chức năng.

File mới được tạo trong bước này:

- `docs/camera-changes-last-3-days.md`

Thư mục gom file được tạo trong bước này:

- `storage/camera_review_files/current/`

## 7. Ghi chú gom file

Các file hiện tại sẽ được copy sang:

- `storage/camera_review_files/current/`

Tên file copy dùng dạng dễ gửi:

- `resources_views_attendance_scanner.blade.php`
- `resources_views_attendance_checkin.blade.php`
- `resources_views_face_create.blade.php`
- `resources_views_components_zalo-camera-warning.blade.php`
- `app_Http_Controllers_AttendanceController.php`
- `app_Http_Controllers_FaceController.php`
- `app_Http_Controllers_QrController.php`
- `routes_web.php`
- `public_sw.js`
- `resources_views_layouts_app.blade.php`
- `resources_views_layouts_partials_assets.blade.php`
- `resources_css_face-camera.css`
- `app_Models_User.php`
- `app_Models_Attendance.php`
- `app_Models_QrToken.php`
- `app_Support_AppNavigation.php`
- `database_migrations_2026_05_25_164044_add_face_descriptor_to_users_table.php`
- `database_migrations_2026_05_25_073334_create_qr_tokens_table.php`
- `database_migrations_2026_06_06_000001_add_branch_id_to_qr_tokens_table.php`

## 8. Checklist gửi reviewer

- Kiểm tra QR scanner trên Chrome desktop, Android Chrome, Android PWA, iPhone Safari/PWA.
- Kiểm tra QR denied permission, retry permission, switch camera, QR sai domain.
- Kiểm tra Face Verify: chưa đăng ký, sai mặt, nhiều mặt, đúng mặt, GPS OK, GPS denied.
- Kiểm tra Face Register: không thấy mặt, nhiều mặt, mặt lệch/quá xa/quá gần, lưu descriptor.
- Kiểm tra PWA: unregister/hard refresh, không dùng JS cũ.
- Kiểm tra session/419: submit `face.verify-pass` và `attendance.store` sau khi camera/GPS chạy lâu.

