# CAMERA FLOW AUDIT - ChamCongV2

Tài liệu này audit riêng luồng Camera / GPS / QR / Face hiện tại của ChamCongV2. Phạm vi chỉ là kiểm tra và đề xuất hướng sửa an toàn, không thay đổi code chức năng.

## 1. Tổng quan flow hiện tại

### Flow A - QR Scan

- Route vào màn quét: `GET /attendance/scanner` - `attendance.scanner`.
- View chính: `resources/views/attendance/scanner.blade.php`.
- Controller trả view: `AttendanceController@scanner`.
- Library quét QR: `Html5Qrcode` từ CDN `https://cdn.jsdelivr.net/npm/html5-qrcode/html5-qrcode.min.js`.
- Camera mặc định: biến JS `qrCameraMode = 'environment'`, tức ưu tiên camera sau.
- Flow hiện tại:
  1. User mở trang QR scanner.
  2. Trang hiển thị card xin quyền camera và nút `Cho phép mở Camera`.
  3. Khi bấm nút, JS gọi `startQrCamera()`.
  4. JS gọi `navigator.mediaDevices.getUserMedia()` trước để xin/mở camera thật.
  5. Nếu mở được, JS gắn stream vào preview video tạm.
  6. Sau đó stop stream tạm, xóa `reader.innerHTML`, rồi gọi `Html5Qrcode.start()`.
  7. Khi quét được QR hợp lệ `/attendance/scan/{token}`, JS stop scanner và redirect sang link scan.
  8. Nếu QR ngoài hệ thống hoặc URL sai, redirect sang `qr.unsupported`.
- Route xử lý QR sau khi scan: `GET /attendance/scan/{token}` - `AttendanceController@scan`.
- Session sau scan:
  - `qr_token`
  - `qr_branch_id`
  - `qr_scanned_at`
- Thời hạn session QR trong `AttendanceController@store`: 180 giây tính từ `qr_scanned_at`.
- Lưu ý: QR display hiện tạo token ngắn hạn trong `QrController@generate`, nhưng sau khi scan thành công thì backend dùng `qr_scanned_at` để tính 180 giây cho flow chấm công.

### Flow B - Face Verify khi chấm công

- Route vào màn xác minh: `GET /attendance/checkin` - `attendance.checkin`.
- View chính: `resources/views/attendance/checkin.blade.php`.
- Controller trả view: `AttendanceController@create`.
- Library nhận diện mặt: `@vladmandic/face-api` từ CDN.
- Camera mặc định: biến JS `faceCameraMode = 'user'`, tức ưu tiên camera trước.
- Dữ liệu descriptor:
  - Hidden input `saved_descriptor` lấy từ `auth()->user()->face_descriptor`.
  - Cột lưu DB: `users.face_descriptor`.
- Flow hiện tại:
  1. Nếu user chưa có descriptor, hiện popup yêu cầu đăng ký khuôn mặt.
  2. JS load model face-api từ CDN.
  3. JS kiểm tra permission state cho `geolocation` và `camera` bằng Permissions API nếu browser hỗ trợ.
  4. Nếu cả GPS và Camera đã `granted`, hệ thống lấy GPS, mở camera trước, rồi bắt đầu liveness/face check.
  5. Nếu chưa granted, UI yêu cầu user bấm nút cấp quyền.
  6. Face verify dùng `faceapi.euclideanDistance()` so sánh descriptor trong camera với descriptor của user đang đăng nhập.
  7. Threshold frontend và backend hiện dùng `0.5`.
  8. Nếu match, chạy liveness: nhìn thẳng -> quay trái -> quay phải -> nhìn thẳng.
  9. Khi pass, JS POST `face.verify-pass` để lưu session xác minh mặt.
  10. Sau đó set hidden `face_verified=1`, lấy GPS và submit form `attendance.store`.
- Backend xác minh:
  - `FaceController@verifyPass` validate `distance <= 0.5`.
  - Lưu session `face_verified_user_id`, `face_verified_at`, `face_verified_distance`.
  - `AttendanceController@store` kiểm tra session face còn trong 120 giây và đúng user hiện tại.

### Flow C - Face Register

- Route vào màn đăng ký: `GET /face/register` - `face.register`.
- View chính: `resources/views/face/create.blade.php`.
- Controller lưu: `FaceController@store`.
- Camera mặc định: `registerCameraMode` lấy từ `localStorage` key `chamcongv2_register_face_camera_mode`, fallback `user`.
- Flow hiện tại:
  1. JS load face-api model từ CDN.
  2. Kiểm tra `navigator.mediaDevices.getUserMedia` và secure context.
  3. Kiểm tra permission camera bằng Permissions API nếu browser hỗ trợ.
  4. Nếu không denied, tự gọi `requestCameraPermission(true)` để mở camera.
  5. Camera ưu tiên `facingMode: { ideal: registerCameraMode }`, fallback `video: true`.
  6. Detect đúng 1 mặt, kiểm tra kích thước/vị trí mặt trong khung.
  7. Lấy 3 mẫu descriptor (`REQUIRED_SAMPLES = 3`) rồi lấy trung bình.
  8. Submit form POST `face.store`.
  9. Backend validate JSON descriptor và lưu vào `users.face_descriptor`.

## 2. Bảng file liên quan

| Chức năng | File | Route | Controller/Method | Mô tả vai trò |
| --- | --- | --- | --- | --- |
| QR scanner | `resources/views/attendance/scanner.blade.php` | `attendance.scanner` | `AttendanceController@scanner` | UI/JS mở camera sau, quét QR, redirect link scan |
| QR scan token | n/a | `attendance.scan` | `AttendanceController@scan` | Validate QR token, lưu session QR, chuyển sang face verify |
| QR display | `resources/views/qr/show.blade.php` | `qr.show`, `qr.generate` | `QrController@show`, `QrController@generate` | Tạo QR token theo chi nhánh và refresh QR |
| Face verify | `resources/views/attendance/checkin.blade.php` | `attendance.checkin` | `AttendanceController@create` | UI/JS mở face camera, match descriptor, lấy GPS, submit attendance |
| Face verify session | `resources/views/attendance/checkin.blade.php` | `face.verify-pass` | `FaceController@verifyPass` | Backend ghi session face verified sau khi distance hợp lệ |
| Attendance submit | `resources/views/attendance/checkin.blade.php` | `attendance.store` | `AttendanceController@store` | Kiểm tra face session, QR session, GPS, sau đó checkin/checkout |
| Face register | `resources/views/face/create.blade.php` | `face.register`, `face.store` | `FaceController@create`, `FaceController@store` | UI/JS đăng ký descriptor và backend lưu vào user |
| Zalo warning | `resources/views/components/zalo-camera-warning.blade.php` | n/a | n/a | Hiện cảnh báo khi user agent có `zalo` |
| PWA cache | `public/sw.js` | `/sw.js` | n/a | Không cache HTML động, chỉ cache asset tĩnh |
| Layout/PWA register | `resources/views/layouts/app.blade.php` | n/a | n/a | Đăng ký service worker và xử lý update PWA |
| CSS camera | `resources/css/face-camera.css` | n/a | n/a | Frame camera, mobile responsive, button/action UI |
| User model | `app/Models/User.php` | n/a | n/a | `face_descriptor` fillable, date/status casts |
| Face descriptor migration | `database/migrations/2026_05_25_164044_add_face_descriptor_to_users_table.php` | n/a | n/a | Thêm cột `users.face_descriptor` |

## 3. Thuật toán mở camera hiện tại

### QR scanner

- Có state chống spam:
  - `isStartingCamera`
  - `isSwitchingCamera`
  - `isRequestingCamera`
  - `isCameraReady`
  - `currentStream`
- Trước khi mở camera mới có gọi:
  - `stopScanner()`
  - `stopQrCameraTracks()`
  - stop tất cả track của stream cũ.
- Constraints QR hiện tại:
  1. `facingMode: { ideal: 'environment' }`, `width: { ideal: 1280 }`, `height: { ideal: 720 }`
  2. `facingMode: 'environment'`
  3. `video: true`
- Sau khi `getUserMedia()` thành công, code gắn stream vào video preview tạm, `await video.play()`, rồi stop stream và chuyển sang `Html5Qrcode.start()`.
- Sau đó `Html5Qrcode.start()` lại tự mở camera riêng theo config:
  - `{ facingMode: { ideal: qrCameraMode } }`
  - `{ facingMode: qrCameraMode }`
  - `{ facingMode: { ideal: qrCameraMode } }`
  - `{ facingMode: 'environment' }`
  - fallback `Html5Qrcode.getCameras()` chọn deviceId.
- Rủi ro chính: QR đang có 2 tầng mở camera: mở thật bằng `getUserMedia()` rồi lại mở bằng `Html5Qrcode`. Việc stop stream tạm rồi mở lại ngay có thể gây `NotReadableError` trên mobile/PWA do camera chưa giải phóng kịp.

### Face verify

- Có state chống spam:
  - `isStartingFaceCamera`
  - `isSwitchingCamera`
  - `isRequestingPermission`
  - `faceCheckRunId`
  - `faceStream`
- Trước khi mở camera mới có `stopFaceCameraTracks()`.
- Constraints:
  1. `facingMode: { ideal: faceCameraMode }`, width/height 640x480.
  2. Fallback `video: true`.
- Camera preview dùng:
  - `video.srcObject = faceStream`
  - `playsinline`
  - `muted`
  - `await video.play()`
- Khi đổi camera:
  - toggle `user` <-> `environment`
  - tăng `faceCheckRunId` để hủy vòng detect cũ.

### Face register

- Có state chống spam:
  - `startingCameraPromise`
  - `isStartingCamera`
  - `isRequestingCamera`
  - `isSwitchingCamera`
  - `detectionRunId`
- Trước khi mở stream mới có `stopRegisterCameraTracks()`.
- Constraints:
  1. `facingMode: { ideal: registerCameraMode }`, width/height 640x480.
  2. Fallback `video: true`.
- Khác Face verify: có `waitForVideoReady()` để đợi metadata video, tốt hơn cho detect ổn định.
- Đang lưu camera mode trong `localStorage`, không dùng localStorage để kết luận permission đã cấp.

## 4. Thuật toán quyền Camera/GPS hiện tại

### Camera permission

- QR scanner:
  - Không tự gọi camera khi vào trang.
  - Mặc định hiện panel và chờ user bấm `Cho phép mở Camera`.
  - Chỉ hiện hướng dẫn thủ công sau `cameraPermissionFailures >= 2`.
- Face verify:
  - Dùng `navigator.permissions.query({ name: 'camera' })` nếu browser hỗ trợ.
  - Nếu state `denied`, hiện hướng dẫn lỗi.
  - Nếu không phải `granted`, hiện panel yêu cầu bấm nút.
  - Nếu `granted`, tự mở camera.
- Face register:
  - Dùng `navigator.permissions.query({ name: 'camera' })`.
  - Nếu không denied, sau khi load model thì tự gọi camera.
  - Nếu denied, hiện modal/hướng dẫn.

### GPS permission

- GPS chỉ nằm ở `resources/views/attendance/checkin.blade.php`.
- Có `cachedGpsPosition` để cache trong page hiện tại.
- Có `isRequestingGps` để chống gọi song song.
- Thuật toán lấy GPS:
  1. Nếu cached và không force thì dùng cached.
  2. Kiểm tra permission state nếu browser hỗ trợ.
  3. Lần 1 gọi nhanh:
     - `enableHighAccuracy: false`
     - `timeout: 5000`
     - `maximumAge: 300000`
  4. Nếu fail, fallback chính xác hơn:
     - `enableHighAccuracy: true`
     - `timeout: 10000`
     - `maximumAge: 0`
- Khi submit attendance, backend kiểm tra GPS bằng cách tìm branch gần vị trí user nhất, so với `gps_radius` của branch đó. Không bắt user thuộc đúng branch.

## 5. Lỗi/bug tiềm ẩn cần sửa

1. QR scanner hiện chưa tự gọi camera khi vào trang.
   - Nếu nghiệp vụ cuối cùng yêu cầu QR tự xin quyền ngay khi vào màn quét, hiện tại chưa đúng vì `initScanner()` chỉ gọi `showCameraStartCard()`.

2. QR scanner mở camera 2 lần.
   - `getUserMedia()` mở stream preview trước.
   - Sau đó stop stream rồi `Html5Qrcode.start()` mở stream mới.
   - Đây là nguyên nhân tiềm ẩn gây `NotReadableError` hoặc lỗi “camera đang được ứng dụng khác sử dụng” dù camera vẫn bình thường.

3. QR scanner fallback đang hơi dư.
   - Sau `requestRealCameraStream()` đã fallback nhiều tầng, `startQrCamera()` lại fallback nhiều tầng bằng `Html5Qrcode.start()`.
   - Có lợi về độ bền, nhưng làm flow khó kiểm soát và khó phân biệt lỗi thật.

4. Face verify đang xin camera trước GPS trong `startPermissionFlow()`.
   - UI text nói cần vị trí và camera, nhưng code hiện tại mở camera trước rồi lấy GPS.
   - Nếu nghiệp vụ muốn GPS trước, camera sau, cần sửa thứ tự.

5. Face verify nếu permission state là `prompt` thì không tự gọi `getUserMedia()`.
   - Trên một số browser, Permissions API không ổn định hoặc trả `prompt`; flow hiện chuyển sang chờ user bấm nút.
   - Nếu UX cuối cùng muốn tự mở khi vào trang, cần dựa vào `getUserMedia()` thực tế thay vì chỉ dựa Permissions API.

6. Face register/verify phụ thuộc CDN.
   - Nếu thiết bị ngoài quán mạng yếu hoặc CDN chậm, face-api model không load được.
   - PWA service worker hiện chỉ cache asset cùng origin, không cache CDN face-api/html5-qrcode.

7. Text trong file Blade có dấu hiệu mojibake khi đọc bằng terminal.
   - Có thể do encoding hiển thị terminal, nhưng nên kiểm tra trực tiếp trên browser/source để đảm bảo file lưu UTF-8.

8. QR token display và QR session sau scan là 2 khái niệm dễ nhầm.
   - `QrController@generate()` tạo token hết hạn rất nhanh.
   - `AttendanceController@store()` lại dùng `qr_scanned_at` 180 giây sau khi scan.
   - Tài liệu và UI nên giải thích rõ để debug đúng.

9. NotReadableError hiện vẫn có thể bị báo quá sớm ở một số nhánh.
   - QR có fallback tốt hơn, nhưng cuối flow vẫn throw `NotReadableError` khi mọi fallback fail.
   - Face verify/register fallback một lần sang `video: true`; nếu stream cũ chưa giải phóng kịp thì vẫn có thể báo camera bận.

10. PWA không cache HTML động là đúng, nhưng cần kiểm tra version deploy thực tế.
    - `public/sw.js` dùng cache version từ query `?v=appVersion`.
    - Nếu production không đổi `appVersion` hoặc build asset chưa chạy, user có thể vẫn thấy UI cũ.

## 6. Đề xuất kiến trúc fix chuẩn production

1. Tách helper camera dùng chung.
   - Tạo một module JS nhỏ hoặc inline helper thống nhất:
     - `stopStream(stream)`
     - `openCameraWithFallback(mode, profile)`
     - `classifyCameraError(error)`
     - `attachVideo(video, stream)`
   - QR, Face verify, Face register dùng chung logic bắt lỗi.

2. QR scanner chỉ nên có một owner mở camera.
   - Phương án an toàn nhất:
     - Dùng `Html5Qrcode.start()` trực tiếp với constraints fallback.
     - Không mở preview stream tạm bằng `getUserMedia()` trước.
   - Nếu vẫn cần `getUserMedia()` để trigger permission, phải đảm bảo delay giải phóng đủ và không gọi `Html5Qrcode.start()` ngay lập tức.

3. Face verify/register giữ stream tự quản lý.
   - Không cần Html5Qrcode.
   - Giữ current stream trong biến riêng từng trang.
   - Mỗi lần đổi camera phải:
     - tăng runId để hủy detect loop cũ
     - stop stream cũ
     - set `video.srcObject = null`
     - mở stream mới

4. Không dùng chung state lỗi QR và Face.
   - Hiện tại mỗi file đã có state riêng, nên tiếp tục giữ tách biệt.
   - Không đưa `cameraPermissionFailures` thành biến global dùng chung nhiều page.

5. Chuẩn hóa permission UX.
   - Không hiện hướng dẫn thủ công ngay từ đầu.
   - Chỉ hiện khi:
     - permission state là `denied`
     - hoặc user bấm thử lại nhiều lần mà vẫn `NotAllowedError`
     - hoặc browser không hiện popup nữa.

6. Chuẩn hóa GPS helper.
   - Giữ flow nhanh -> chính xác như hiện tại.
   - Không gọi GPS liên tục.
   - Cache trong page hiện tại.
   - Message rõ: đang lấy, thành công, bị chặn, không lấy được.

7. Đưa thư viện AI/QR về local asset nếu production cần ổn định cao.
   - CDN là rủi ro lớn khi PWA/mobile ở mạng yếu.
   - Có thể tải `face-api.js`, model và `html5-qrcode` vào public/build hoặc public/vendor rồi version theo Vite/service worker.

## 7. Đề xuất flow UX cuối cùng

### QR Scan

- User vào `/attendance/scanner`.
- Hệ thống tự gọi camera ngay bằng `getUserMedia()` hoặc `Html5Qrcode.start()` thực tế, ưu tiên camera sau:
  - `facingMode: { ideal: 'environment' }`
- Nếu browser chưa có quyền, browser tự hiện popup xin quyền.
- Nếu user đã cấp quyền trước đó, camera mở và quét QR ngay.
- Nếu bị từ chối quyền:
  - Hiện card ngắn: `Bạn chưa cấp quyền Camera.`
  - Nút chính: `Thử lại mở Camera`
  - Link phụ: `Xem hướng dẫn cấp quyền`
- Nút đổi camera chỉ là phụ:
  - Toggle `environment` <-> `user`
  - Không loop qua toàn bộ camera mỗi lần bấm.

### Face Verify

- Sau khi QR hợp lệ, vào `/attendance/checkin`.
- Nếu chưa đăng ký descriptor:
  - Chặn flow.
  - Hiện popup đăng ký khuôn mặt.
- Nếu đã có descriptor:
  - Mở face camera trước, ưu tiên:
    - `facingMode: { ideal: 'user' }`
  - Match descriptor của user đăng nhập.
  - Nếu không khớp, tiếp tục báo không khớp và không chuyển sang bước liveness.
  - Nếu khớp, chạy liveness:
    - nhìn thẳng -> quay trái -> quay phải -> nhìn thẳng.
  - Sau khi face pass:
    - POST `face.verify-pass`
    - lấy GPS
    - submit `attendance.store`
- GPS nên hiện trạng thái rõ và không treo UI quá timeout đã cấu hình.

### Face Register

- User vào `/face/register`.
- Hệ thống tự load model và tự mở camera trước nếu chưa bị denied.
- Nếu camera chưa cấp quyền, gọi `getUserMedia()` thực tế để browser tự xin quyền.
- Detect đúng 1 mặt, đủ sáng, mặt nằm giữa khung.
- Lấy nhiều mẫu descriptor và lưu trung bình.
- Hiện rõ:
  - `Không tìm thấy khuôn mặt`
  - `Chỉ được có 1 khuôn mặt trong khung hình`
  - `Đưa mặt vào giữa khung`
  - `Đã ghi nhận gương mặt. Đang lưu...`

## 8. Checklist code cần sửa

- `resources/views/attendance/scanner.blade.php`
  - Quyết định lại QR có tự mở camera ngay khi vào trang hay vẫn cần bấm nút.
  - Loại bỏ hoặc giảm cơ chế mở camera 2 tầng.
  - Chỉ để một owner điều khiển stream camera.
  - Giữ fallback `ideal environment -> simple environment -> video true`.
  - Giữ stop stream/scanner trước khi đổi camera.

- `resources/views/attendance/checkin.blade.php`
  - Chuẩn hóa thứ tự GPS/Camera theo nghiệp vụ cuối cùng.
  - Nếu muốn auto request, giảm phụ thuộc vào `permissions.query()` và ưu tiên gọi API thực tế.
  - Giữ rule descriptor match trước liveness.
  - Giữ hidden `face_verified=1` chỉ sau khi backend verify-pass thành công.

- `resources/views/face/create.blade.php`
  - Giữ logic lấy 3 samples và average descriptor.
  - Kiểm tra UI permission modal không hiện hướng dẫn thủ công quá sớm.
  - Kiểm tra localStorage chỉ lưu camera mode, không dùng để kết luận permission.

- `resources/views/components/zalo-camera-warning.blade.php`
  - Kiểm tra button mở trình duyệt trên Zalo có hoạt động thực tế trên Android/iOS hay không.
  - Nếu không, ưu tiên hướng dẫn copy link.

- `public/sw.js`
  - Giữ nguyên rule không cache HTML động.
  - Đảm bảo deploy đổi `APP_VERSION`/asset version để PWA nhận JS mới.

- `resources/css/face-camera.css`
  - Kiểm tra frame camera mobile không bị bottom nav che.
  - Kiểm tra video/canvas bám đúng khung nếu thêm overlay/canvas mới.

## 9. Test case bắt buộc

### QR camera

1. Chrome desktop lần đầu chưa cấp camera.
   - Browser hiện popup xin quyền.
   - Đồng ý thì mở camera và quét được QR.

2. Chrome desktop đã cấp camera trước đó.
   - Không hiện hướng dẫn thủ công.
   - Camera mở ngay theo UX đã chọn.

3. Android Chrome có nhiều camera.
   - QR ưu tiên camera sau.
   - Nút đổi camera toggle sang camera trước.
   - Bấm đổi nhanh không crash.

4. Android PWA.
   - Không dùng JS/CSS cũ sau deploy.
   - Không cache HTML có CSRF.
   - Camera không bị màn hình đen vô hạn.

5. iPhone Safari/PWA.
   - Video có `playsinline`.
   - Popup permission hoạt động.
   - Nếu deny, không spam popup.

6. Camera bị từ chối.
   - Hiện `Bạn chưa cấp quyền Camera.`
   - Nút thử lại gọi lại camera.
   - Hướng dẫn thủ công chỉ hiện sau deny thật hoặc nhiều lần fail.

7. QR ngoài hệ thống.
   - Không 404 trắng.
   - Chuyển sang trang `Không hỗ trợ mã QR này.`

### Face verify

1. User chưa có `face_descriptor`.
   - Không submit attendance.
   - Hiện popup đăng ký khuôn mặt.

2. User có descriptor đúng.
   - Camera ưu tiên camera trước.
   - Match đúng user.
   - Pass liveness.
   - POST `face.verify-pass` thành công.
   - Lấy GPS và submit attendance.

3. Sai mặt.
   - Luôn báo `Khuôn mặt không khớp với tài khoản`.
   - Không chuyển sang quay trái/quay phải.
   - Khi đổi lại đúng mặt, flow quét lại bình thường.

4. Nhiều mặt.
   - Báo chỉ được có 1 khuôn mặt.
   - Không pass.

5. GPS đã cấp quyền.
   - Lấy nhanh bằng maximumAge nếu có.
   - Không hỏi lại vô lý.

6. GPS denied.
   - Không treo UI.
   - Có nút cấp quyền vị trí/thử lại.

### Face register

1. Camera chưa cấp quyền.
   - Browser xin quyền khi gọi API thật.
   - Đồng ý thì camera mở.

2. Camera đã cấp quyền.
   - Tự mở camera sau khi load AI.

3. Không có mặt.
   - Báo không tìm thấy mặt, không submit.

4. Nhiều mặt.
   - Báo chỉ được có 1 khuôn mặt, không submit.

5. Mặt lệch/quá gần/quá xa.
   - Báo hướng dẫn đúng.

6. Đủ điều kiện.
   - Lấy đủ 3 samples.
   - Lưu descriptor vào `users.face_descriptor`.

### Session/backend

1. QR scan xong trong 180 giây.
   - Face lâu nhưng chưa quá 180 giây từ lúc scan QR thì vẫn hợp lệ.

2. QR scan quá 180 giây.
   - Backend chặn và yêu cầu scan lại.

3. Face session quá 120 giây.
   - Backend chặn và yêu cầu xác minh mặt lại.

4. Submit thiếu CSRF hoặc mất session.
   - Không pass attendance.

## 10. Không được làm

- Không dùng `facingMode: { exact: 'environment' }` làm mặc định cho QR.
- Không dùng `facingMode: { exact: 'user' }` làm mặc định cho Face.
- Không dùng localStorage/session/database để kết luận chắc chắn browser đã cấp quyền camera.
- Không để QR camera và Face camera dùng chung state lỗi/global stream.
- Không pass face verify chỉ vì detect có khuôn mặt.
- Không submit attendance nếu `face_verified` chưa pass và backend chưa lưu session verify.
- Không dùng `checkout_at` để quyết định ngày công.
- Không cache HTML động có CSRF trong service worker.
- Không hiện hướng dẫn cấp quyền thủ công ngay từ đầu nếu browser vẫn có thể hiện popup permission.
- Không mở nhiều stream camera song song.
- Không loop xin quyền camera/GPS liên tục sau khi user từ chối.

