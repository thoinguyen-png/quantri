# Tom tat du an ChamCongV2

Tai lieu nay duoc tao de AI/Codex/ChatGPT khac doc truoc khi sua code. Noi dung duoc tong hop tu source hien tai, khong lay password, secret key, token that hoac gia tri nhay cam tu `.env`.

## 1. Cong nghe su dung

- Backend: Laravel 12, PHP 8.2+, Laravel Breeze, guard `web`.
- Frontend: Blade, Tailwind CSS, AlpineJS, JavaScript vanilla, Vite.
- Database: MySQL/MariaDB qua migrations Laravel.
- Build: `npm run build` voi `vite.config.js`, asset output trong `public/build`.
- App hien thi ten `Quan tri`/`Quản trị` trong UI/PWA/header tuy noi dung co dau/khong dau cua file; `.env.example` dat `APP_NAME="Quản trị"`, khong sua `.env` that trong repo.
- QR: `bacon/bacon-qr-code`.
- PWA: `public/sw.js`, `public/manifest.json`, app icons trong `public/icons`.
- OCR: `App\Services\CitizenIdOcrService` goi Python/PaddleOCR qua `scripts/paddle_ocr_vneid.py`. Frontend OCR Quick Onboarding dang tam tat co chu dich bang flag `OCR_ENABLED = false` trong `resources/js/quick-onboarding-public.js`.
- Queue/jobs: Laravel jobs tables co migration, nhung chua thay Job/Event/Listener tuy bien trong `app`.

## 2. Cau truc source quan trong

- `routes/web.php`: tat ca route web/public/auth/module chinh. Khong thay `routes/api.php` trong source hien tai.
- `routes/auth.php`: login/register/reset/logout Breeze, co session expired.
- `app/Http/Controllers`: controller cho attendance, dashboard, user, quick onboarding, QR rating, rating review/feed/notification, payroll, shifts, leave/supplement requests.
- `app/Services`: logic nghiep vu lon: cham cong, ca lam, QR rating, reward, fraud risk, Quick Onboarding, OCR, internal-device marker.
- `app/Models`: model Eloquent cho users, branches, shifts, attendances, customer ratings/rewards, quick onboarding, settings.
- `database/migrations`: schema chinh cho user, chi nhanh, ca, cham cong, request, rating/reward, notification, quick onboarding, OCR/onboarding fields.
- `resources/views`: Blade noi bo va public: dashboard, attendance, users, quick onboarding, QR rating, risk review, feed, auth.
- `resources/js`: JS cho app, QR rating, rating notifications, rating QR management, quick onboarding admin/public.
- `resources/css`: CSS module theo tung man hinh.
- `public/css/qr-rating.css`, `public/js/qr-rating.js`, `public/images/maxsim-logo.png`: static asset rieng cho public QR rating, khong phu thuoc Vite.
- `public/UI`: HTML mau tham khao, khong phai production view.
- `scripts/paddle_ocr_vneid.py`: script PaddleOCR local cho CCCD/VNeID.
- `config/services.php`: cau hinh OCR qua ENV names, khong hardcode secret.
- `public/sw.js`: service worker chi cache static asset an toan, route rieng tu/network dynamic dung network/no-store.
- `users.employee_code` va `App\Services\EmployeeCodeService`: ma nhan su 4 chu so theo tung chi nhanh (`0001`, `0002`...), unique theo `(branch_id, employee_code)`. `User::getEmployeeCodeAttribute()` chi format gia tri trong cot nay, khong con sinh tu `users.id`.

## 3. Danh sach chuc nang hien co

### Auth va phan quyen

- Route: `/login`, `/register`, `/logout`, reset password, `/auth/session-status`, `/auth/session-keepalive`.
- Controller/View: `Auth\AuthenticatedSessionController`, `LoginRequest`, `resources/views/auth/*`.
- Model/database: `users`, `sessions`, `password_reset_tokens`.
- Middleware: `RoleMiddleware`, roles that: `admin`, `manager`, `staff`, `cashier`.
- Trang thai: dang hoat dong.
- Ghi chu: login chap nhan email hoac phone trong `LoginRequest`; login noi bo set internal-device marker de chan QR rating public tren cung browser/PWA.

### Quan ly nhan su

- Route: `/users`, `/users/create`, `/users/{user}/edit`, `GET/PUT /me`.
- Controller/View: `UserController`, `resources/views/users/*`.
- Model/database: `User`, `Branch`, `WorkHistory`, fields status/employment/status milestones/face/rating QR.
- Trang thai: dang hoat dong.
- Ghi chu: admin/manager quan ly theo branch; manager khong duoc tu y tao/sua role admin/manager theo logic hien co. Ma nhan su luu trong `users.employee_code`, dang 4 chu so theo tung chi nhanh; admin/manager co the nhap tay khi tao/sua, de trong se tu sinh ma tiep theo trong chi nhanh. UI `/users` va form nhan su da dong bo style Quick Onboarding: nen nhe, card trang bo goc lon, shadow nhe, mobile khong keo ngang.
- Form tao/sua/ho so nhan su chia thanh nhieu box/card: anh dai dien, thong tin co ban, CCCD, cong viec, nhan dien/QR, doi mat khau neu co. Khong them Zalo. Chi hien field co cot DB that su; `users` hien chua co date_of_birth/gender/place_of_origin/address/issue_date nen cac field nay khong duoc them vao form nhan su noi bo.
- Anh nhan su uu tien `users.face_image_path` qua `User::getAvatarUrlAttribute()`, fallback chu cai dau neu khong co anh hop le.
- Phan quyen form: admin xem/sua toan bo nhan su va field quan tri; manager xem/sua nhan su thuoc chi nhanh minh nhung khong sua admin/manager va khong nang role len manager/admin; staff/cashier chi sua ho so ca nhan `/me`. Backend `UserController` co chan update field trai quyen, khong chi an UI.
- Route `/me` cho user dang nhap tu cap nhat thong tin ca nhan cua minh: ho ten, email, so dien thoai, CCCD, avatar. Staff/cashier/manager khong duoc sua field quan tri nhu role/branch/status/employment_status/employee_code/face_verification_mode/rating_qr_enabled/public_rating_code/face_descriptor tren form ca nhan.

### Chi nhanh/co so

- Database/model: `branches`, `Branch`.
- Route lien quan: user management, attendance, quick onboarding bulk update, branch-shift permissions.
- Controller/View: `BranchShiftPermissionController`, `resources/views/branch_shift_permissions/index.blade.php`.
- Trang thai: dang hoat dong.
- Ghi chu: manager bi scope theo `branch_id`; admin xem toan bo.

### Ca lam va phan ca

- Route: `/shifts`, `/shift-assignments`, `/branch-shift-permissions`.
- Controller/Service/View: `ShiftController`, `ShiftAssignmentController`, `ShiftScheduleService`, `AttendanceShiftResolver`, `resources/views/shifts/*`, `resources/views/shift_assignments/*`.
- Model/database: `shifts`, `shift_segments`, `shift_assignments`, pivot branch-shift.
- Trang thai: dang hoat dong.
- Ghi chu: co ho tro ca gay/segments, overtime/checkin windows, strict mode settings. `branch-shift-permissions` chi danh cho admin de cau hinh ca duoc phep theo tung co so.

### Cham cong QR/GPS/face

- Route: `/attendance/checkin`, `/attendance/scan/{token}`, `/attendance/scanner`, `/attendance/history`, `/qr`, `/qr/generate`.
- Controller/Service/View: `AttendanceController`, `QrController`, `AttendanceCalculationService`, `AttendanceStatusService`, `ShiftScheduleService`, views `attendance/*`, `qr/show.blade.php`.
- Model/database: `attendances`, `attendance_segments`, `qr_tokens`, `users.face_descriptor`, `users.face_image_path`.
- Trang thai: dang hoat dong.
- Ghi chu: khong sua thuat toan QR cham cong neu task khong yeu cau; co check GPS, ca, checkout, ca gay.

### Dang ky/nhan dien guong mat

- Route: `/face/register`, `/face/verify-pass`, `/face-verification-modes`.
- Controller/View: `FaceController`, `FaceVerificationModeController`, `resources/views/face/create.blade.php`, `resources/views/admin/face-verification-modes/index.blade.php`.
- Model/database: `users.face_descriptor`, `users.face_image_path`, `users.face_verification_mode`, `face_verification_mode_logs`.
- Trang thai: dang hoat dong.
- Ghi chu: Quick Onboarding khi tao user set `face_verification_mode = priority`; avatar onboarding luu vao `face_image_path`.
- Avatar nhan su tren `/users`, `/users/{user}/edit` va `/me` uu tien hien anh that tu `users.face_image_path`; neu khong co file/URL hop le moi fallback ve chu cai dau.

### Yeu cau bo sung cong

- Route: `/attendance-supplements/*`.
- Controller/View: `AttendanceSupplementRequestController`, `resources/views/attendance_supplements/*`.
- Model/database: `attendance_supplement_requests`, `attendance_adjustment_logs`.
- Trang thai: dang hoat dong.
- Ghi chu: co create/day-info/store cho staff/cashier/manager/admin; approve/reject/bulk review cho admin/manager; ap dung vao attendance/segment.

### Yeu cau nghi phep

- Route: `/leave-requests/*`.
- Controller/View: `LeaveRequestController`, `resources/views/leave_requests/*`.
- Model/database: `leave_requests`, settings max leave days.
- Trang thai: dang hoat dong.
- Ghi chu: staff/cashier/manager tao request; admin/manager duyet theo scope.

### Bao cao, thong ke, bang cong, payroll

- Route: `/attendance/dashboard`, `/attendance-reports`, `/attendance-statistics`, `/attendance-adjustment-logs`, `/payrolls`, `/payrolls/export`, `/admin/quick-attendance`.
- Controller/View: `DashboardController`, `AttendanceReportController`, `AttendanceStatisticController`, `AttendanceAdjustmentLogController`, `PayrollController`, `QuickAttendanceController`.
- Model/database: `attendances`, `attendance_segments`, `shift_assignments`, `attendance_adjustment_logs`.
- Trang thai: dang hoat dong.
- Ghi chu: dashboard moi da tach bang cong sang nav Cham cong; homepage co rating summary cards va AJAX rating-summary.

### Trang chu noi bo

- Route: `/dashboard`, `/dashboard/rating-summary`.
- Controller/View: `DashboardController`, `resources/views/dashboard/home.blade.php`, `dashboard/staff.blade.php`, manager/admin dashboard.
- Model/database: `CustomerRating`, `CustomerRatingReward`, `Attendance`.
- Trang thai: dang hoat dong.
- Ghi chu: homepage moi co 2 card rating; chuong notification da duoc an theo cac phase truoc, nhung popup ca nhan/rating notification file van con trong source.

### QR danh gia nhan vien public

- Route: `GET/POST /qr-rating/{token}`, `GET/POST /{publicRatingCode}`, `/rating-thank-you`.
- Controller/Service/View: `QrRatingController`, `StoreQrRatingRequest`, `RatingSubmissionService`, `RatingEligibilityService`, `RatingQrService`, `RewardCalculationService`, `RatingFraudRiskService`, `GuestBrowserTokenService`, `InternalDeviceMarkerService`, views `qr_ratings/show.blade.php`, `qr_ratings/thank-you.blade.php`.
- Model/database: `rating_qr_tokens`, `customer_ratings`, `customer_rating_rewards`, `customer_rating_reward_counters`, `internal_device_markers`, `users.public_rating_code`.
- Trang thai: dang hoat dong, nhung can canh giac do UI/public asset da sua nhieu lan.
- Ghi chu: public QR rating dung static asset `/css/qr-rating.css`, `/js/qr-rating.js`, `/images/maxsim-logo.png`; GET co block state `allowed`, `blocked_internal_device`, `blocked_duplicate_rating_today`; POST van la lop bao ve cuoi.
- Setting `rating_require_active_attendance` cho phep admin bat/tat dieu kien "nhan su phai dang checkin trong ca moi nhan danh gia". Default la bat (`true`) de giu hanh vi cu. Khi tat, QR rating van giu token hop le, QR enabled, internal-device block, duplicate daily browser/employee, browser cap, fraud/risk/reward va validate form; neu khong co attendance hien tai thi `attendance_id`/`shift_id` co the null va `branch_id` lay tu chi nhanh nhan su.

### QR rating ca nhan noi bo

- Route: `/rating-qrs/{user}`, `/svg`, `/download`, `/enable`, `/disable`, `/regenerate`.
- Controller/Service/View: `RatingQrController`, `RatingQrService`, `resources/views/rating_qrs/partials/*`.
- Model/database: `rating_qr_tokens`, `users.rating_qr_enabled`, `users.public_rating_code`.
- Trang thai: dang hoat dong.
- Ghi chu: dung Bacon QR; khong sua thuat toan QR neu task khong yeu cau.

### Rating notification/feed/risk review

- Route: `/rating-notifications/recent`, `/rating-notifications/{notification}/read`, `/ratings/feed`, `/ratings/feed/recent`, `/ratings/risk-review`.
- Controller/Service/View: `RatingNotificationController`, `RatingFeedController`, `RatingRiskReviewController`, `CustomerRatingNotificationService`, `RatingRiskReviewService`, `resources/views/ratings_feed/index.blade.php`, `ratings_risk_review/index.blade.php`.
- Model/database: `notifications`, `customer_ratings`, `customer_rating_rewards`.
- Trang thai: dang hoat dong.
- Ghi chu: pending review approve/reject idempotent theo service; route `/{publicRatingCode}` duoc khai bao cuoi file de tranh bat nham `/ratings/risk-review`.

### Quick Onboarding admin

- Route: `/quick-onboarding`, `/quick-onboarding/add-more`, `/quick-onboarding/bulk-update`, `/quick-onboarding/sync`, `/quick-onboarding/entries/{entry}/qr`, `/sent`, `/expected-name`.
- Controller/Service/View/JS: `QuickOnboardingController`, `QuickOnboardingService`, `QuickOnboardingShareService`, `resources/views/quick_onboarding/index.blade.php`, `resources/js/quick-onboarding.js`.
- Model/database: `quick_onboarding_batches`, `quick_onboarding_entries`.
- Trang thai: dang hoat dong; autosave ten du kien da co trong inline script cua `resources/views/quick_onboarding/index.blade.php` voi debounce 700ms va blur/Enter save.
- Ghi chu: tao batch/link moi, add-more khong xoa link cu, demo_code 001..999 theo batch, bulk update branch/role, share/copy/Zalo/Messenger/QR, mark sent, sync 10 giay. Trang admin khong lay du lieu chinh tu session/localStorage; neu mat session `quick_onboarding_current_batch_id` sau logout/browser khac thi controller fallback batch moi nhat trong DB theo scope quyen va set lai session tam. Luu y `resources/js/quick-onboarding.js` van con listener cu cho nut `[data-expected-name-save]`, nhung view hien tai khong con nut luu thu cong; autosave thuc te nam trong Blade.

### Quick Onboarding public

- Route canonical: `/quick-onboarding/invite/{token}`, `/quick-onboarding/invite/{token}/ocr`, `/preview`, `/confirm`. Alias cu `GET /onboarding/{token}` redirect sang route canonical de giu tuong thich link cu.
- Controller/Service/View/JS: `QuickOnboardingPublicController`, `QuickOnboardingService`, `CitizenIdOcrService`, `resources/views/quick_onboarding/public/form.blade.php`, `preview.blade.php`, `completed.blade.php`, `resources/js/quick-onboarding-public.js`.
- Model/database: `quick_onboarding_entries`, `users`.
- Trang thai: dang hoat dong cho manual fill/preview/confirm; OCR frontend dang tam tat co chu dich, khong xem la bug can sua ngay.
- Ghi chu: confirm tao user that, login user moi, attach internal-device marker, khong luu password plain text vao `submitted_payload`. Password public onboarding toi thieu 6 ky tu. Buoc preview luu draft theo entry trong session key `quick_onboarding_preview_{entry_id}` de bam "Quay lai sua" khong mat du lieu; avatar/anh CCCD da upload duoc giu bang path string va tai dung neu user khong chon file moi. Draft/session chi duoc xoa sau confirm thanh cong.

### Settings va employment status

- Route: `/settings`.
- Controller/Service/View: `SettingController`, `EmploymentStatusService`, `resources/views/settings/edit.blade.php`.
- Model/database: `settings`, `users.employment_status`.
- Trang thai: dang hoat dong.
- Ghi chu: settings gom attendance mode, request permission, auto promote, rating reward/risk, va toggle QR rating `rating_require_active_attendance` mac dinh bat.

### PWA/cache

- File: `public/sw.js`, `resources/views/layouts/partials/assets.blade.php`, `public/manifest.json`.
- Trang thai: dang hoat dong.
- Ghi chu: dynamic/private paths network/no-store; static assets cache version theo manifest/sw filemtime.

## 4. Luong Quick Onboarding hien tai

1. Admin/manager vao `/quick-onboarding`.
2. Nhap so luong va bam Tao de tao batch moi; bam `+ Tao them` de them entry vao batch hien tai.
3. `QuickOnboardingService::addEntries()` tao token random 72 ky tu, `demo_code` tang dan 001..999 trong batch, status `draft`.
4. Admin/manager tick checkbox va bulk update branch/role cho entry. Manager chi duoc chi nhanh cua minh va role `staff/cashier`; admin co `manager/staff/cashier`.
5. Cot Nhan su hien input `expected_name` neu entry chua completed; completed thi hien ten user that.
6. Endpoint `/quick-onboarding/entries/{entry}/expected-name` luu expected_name bang AJAX. Autosave hien tai nam trong inline script cua `resources/views/quick_onboarding/index.blade.php`: input debounce 700ms, blur save ngay, Enter save va blur; co status `Dang cho tu luu/Dang tu luu/Da tu luu`.
7. Share menu co copy link, xuat QR, Zalo, Messenger; khi share/copy/QR thi endpoint mark-sent doi row sang xanh duong.
8. Sync `/quick-onboarding/sync` moi 10 giay cap nhat ten user/status/color row, khong render lai bang.
9. Nhan su mo `/quick-onboarding/invite/{token}`. Link cu `/onboarding/{token}` van duoc redirect sang URL canonical.
10. Form public cho upload avatar va mot anh CCCD/VNeID, nhap thu cong name/citizen_id/date_of_birth/gender/place_of_origin/address/issue_date/phone/email/password.
11. OCR endpoint con ton tai, nhung frontend `OCR_ENABLED = false` trong `resources/js/quick-onboarding-public.js`, nen khi chon anh CCCD chi preview/trang thai upload, khong fetch `/quick-onboarding/invite/{token}/ocr`.
12. POST `/preview` validate toi thieu name/phone/password, password min 6 ky tu, email nullable, CCCD/ngay sinh/gender/dia chi/avatar/anh CCCD nullable; file upload duoc store vao disk `public`.
13. Preview doc session data, hien avatar/anh CCCD bang resolver co the tra data URI neu file ton tai local. Link "Quay lai sua" ve form se dien lai draft da nhap; input file khong the restore theo browser, nhung UI hien trang thai/preview anh da upload va controller tai dung path cu neu khong upload file moi.
14. Confirm doc session preview, tao user trong DB transaction bang `QuickOnboardingService::createUserFromOnboarding()`.
15. User moi duoc set branch/role tu entry, `employee_code` tu sinh theo chi nhanh cua entry, status `thu_viec`, employment `probation`, hired/status audit fields, `face_image_path` tu avatar, `face_verification_mode = priority`.
16. Entry completed luu `completed_user_id`, `completed_at`, `used_at`, `submitted_payload` da bo password.
17. Sau confirm, he thong login user moi bang guard web va attach internal-device marker.

## 5. Luong OCR CCCD/VNeID

- Hien source chi giu mot anh CCCD/VNeID (`cccd_image`) tren UI public onboarding; CCCD vat ly 2 mat khong con la luong chinh.
- `QuickOnboardingPublicController::ocr()` nhan `cccd_image`, validate image, goi `CitizenIdOcrService::extract()`, tra JSON keys: `name`, `citizen_id`, `date_of_birth`, `gender`, `place_of_origin`, `address`, `issue_date`, `raw_text`.
- Frontend hien khong goi endpoint OCR vi `const OCR_ENABLED = false;` o `resources/js/quick-onboarding-public.js`. Khi chon anh CCCD/VNeID, JS khong goi `runOcr()`, khong fetch, khong overwrite input, khong show loi OCR ky thuat.
- `CitizenIdOcrService` cau hinh qua `config/services.php`:
  - `OCR_PROVIDER`
  - `OCR_PYTHON_BINARY`
  - `OCR_PADDLE_SCRIPT`
  - `OCR_TIMEOUT`
- Service co normalize anh upload sang JPG trong `storage/app/quick-onboarding/ocr-normalized`, goi Python bang Symfony Process array, parse raw text bang PHP parser.
- `scripts/paddle_ocr_vneid.py` dung PaddleOCR, co xu ly moi truong Windows/PATH va output JSON.
- Trang thai hien tai: backend OCR co code, nhung frontend da tam disable co chu dich (`OCR_ENABLED = false`) vi con bug moi truong Python/PaddleOCR khi goi qua web. Khong can xem day la loi can sua ngay neu task hien tai khong yeu cau bat OCR lai.
- Ly do co the bao "PaddleOCR chua san sang hoac chua duoc cai dat": `OCR_PROVIDER` khong phai `paddleocr`, python binary/script sai path, Python/PaddleOCR/OpenCV/Numpy chua cai, process khong duoc phep chay tren hosting, thieu quyen ghi/read storage, hoac runtime Windows/PATH/Paddle bi loi.
- Production/shared hosting: neu muon dung OCR that can co Python, virtualenv, PaddleOCR, OpenCV/Numpy, binary path dung, quyen chay process, quyen ghi `storage/app/quick-onboarding/ocr-*`. Shared hosting co the khong phu hop PaddleOCR.
- Huong xu ly an toan hien tai: giu manual fill; chi bat OCR lai sau khi xu ly xong bug moi truong Python/PaddleOCR hoac chuyen OCR sang server/API phu hop.

## 6. Luong nhan dien va dang ky guong mat

- Face registration: `FaceController`, view `face/create.blade.php`, CSS `face-camera.css`, JS co the nam trong Blade/app asset.
- Attendance co check face descriptor/mode trong `AttendanceController`.
- Mode xac minh: `normal`, `priority`, logs bang `face_verification_mode_logs`.
- Admin quan ly mode tai `/face-verification-modes`.
- Quick Onboarding tao user moi voi `face_verification_mode = priority`.
- Anh dai dien onboarding va avatar upload tu form nhan su/ca nhan luu disk `public` trong folder `faces`, gan vao `users.face_image_path`.
- Nguoi dung da xac nhan anh/avatar hien da hien thi duoc. Khong ghi day la bug hien tai; chi can luu y khi deploy/sua tiep: kiem tra `storage:link`, path `storage/app/public`, `public/storage`, va resolver `User::getAvatarUrlAttribute()`/preview/QR rating de tranh loi tai xuat hien.

## 7. Cac man hinh giao dien chinh

- `layouts/app.blade.php`, `layouts/navigation.blade.php`, `layouts/partials/assets.blade.php`: layout noi bo, PWA meta, Vite assets. App name hien thi la `Quan tri`/`Quản trị`; bottom nav mobile duoc an khi focus input/textarea/select bang body class `keyboard-open` de khong che form khi ban phim mobile mo.
- `dashboard/home.blade.php`: trang chu moi voi card rating ca nhan/co so.
- `dashboard/staff.blade.php`, `manager.blade.php`, `admin.blade.php`: dashboard role.
- `attendance/*`: checkin, scanner, history.
- `attendance_reports/*`, `attendance_statistics/index.blade.php`, `payrolls/*`: bang cong/thong ke/luong.
- `users/*`: quan ly nhan su. `/users` la card/list responsive style Quick Onboarding; `create/edit/me` chia box nho gom avatar, thong tin co ban, CCCD, cong viec, nhan dien/QR, doi mat khau neu co.
- `quick_onboarding/index.blade.php`: admin quick onboarding.
- `quick_onboarding/public/form.blade.php`, `preview.blade.php`, `completed.blade.php`: public onboarding.
- `qr_ratings/show.blade.php`, `thank-you.blade.php`: public rating QR va trang cam on.
- `my_ratings/index.blade.php`, `ratings_feed/index.blade.php`, `ratings_risk_review/index.blade.php`: rating noi bo.
- `public/UI/*.html`: mau UI tham khao, khong sua truc tiep neu task khong yeu cau.

## 8. Database/migration/model

- `users`: name, email nullable, password, phone, zalo_phone, citizen_id, start_work_date, branch_id, employee_code, role, status, employment_status, milestone audit, face_image_path, face_descriptor, face_verification_mode, rating_qr_enabled, public_rating_code. `employee_code` nullable cho user khong co branch, unique theo `(branch_id, employee_code)`.
- `branches`: name, address, latitude, longitude, gps_radius.
- `shifts`: name, start/end, late, attendance offset fields; `shift_segments` cho ca gay; `branch_shift` scope chi nhanh.
- `shift_assignments`: user, shift, work_date nullable.
- `attendances`: user, shift, work_date, checkin/checkout, GPS, late/overtime/worked/work_day/work_units/penalty, lock/manual audit, face mode, note.
- `attendance_segments`: segment_order, checkin/checkout, worked/late/overtime data.
- `attendance_supplement_requests`, `leave_requests`: request/approve/reject, audit fields.
- `attendance_adjustment_logs`: audit thay doi cham cong.
- `qr_tokens`: QR cham cong.
- `rating_qr_tokens`: QR danh gia, token hash/ciphertext, enabled/revoked/regenerated.
- `customer_ratings`: employee/branch/shift/attendance, rating/comment, guest/ip/user_agent hashes, settings snapshot, risk fields, review audit. Sau setting `rating_require_active_attendance`, `attendance_id` co the nullable khi admin tat dieu kien dang checkin; `shift_id` da nullable.
- `customer_rating_rewards`, `customer_rating_reward_counters`: thuong rating good va cap theo business_date. `customer_rating_rewards.attendance_id` co the nullable de ho tro rating khong gan attendance khi setting tren bi tat.
- `internal_device_markers`: token_hash cua browser/PWA noi bo, expiry.
- `notifications`: Laravel database notifications.
- `settings`: key/value va updated_by.
- `quick_onboarding_batches`: creator, branch, entries_count.
- `quick_onboarding_entries`: batch, demo_code, expected_name, creator, branch, intended_role, token, status, sent/completed/user, submitted_payload, used_at.
- `work_histories`, `face_verification_mode_logs`: audit thay doi nhan su/face mode.
- Ma nhan su da co cot rieng `users.employee_code`. Migration `2026_07_06_000002_add_employee_code_to_users_table.php` backfill user cu theo tung `branch_id`, sort theo `id`, bat dau tu `0001`; user `branch_id = null` co the de null.

## 9. Cac thay doi gan day thay trong source

- QR rating public dung static asset rieng trong `public/css`, `public/js`, `public/images`, khong dung Vite cho form public.
- QR rating show them avatar nhan su lon; resolver trong `QrRatingController::employeeAvatarUrl()` co xu ly local path/data URI.
- QR rating co thank-you route rieng va no-store headers.
- QR rating co setting admin `rating_require_active_attendance` trong `/settings`: default bat, khi tat thi bo qua rieng dieu kien active attendance/ca lam trong `RatingEligibilityService`, con cac rule QR/risk/reward khac giu nguyen.
- Ma nhan su da doi tu global 3 so theo `users.id` sang 4 so theo tung chi nhanh. `EmployeeCodeService` dung cho `UserController` va `QuickOnboardingService::createUserFromOnboarding()`.
- Quick Onboarding da them batch/entries, bulk update, share menu, mark sent, AJAX sync, demo_code, expected_name.
- Quick Onboarding admin da co autosave expected_name bang inline debounce trong Blade; file Vite `resources/js/quick-onboarding.js` van con code cu cho nut save thu cong nhung view hien khong dung nut do.
- Quick Onboarding admin da fallback batch moi nhat tu DB theo scope quyen khi session current batch bi mat, tranh tinh trang logout/browser khac lam trang trong gia.
- Quick Onboarding public da tao user that, login user moi, avatar vao `face_image_path`, mode face priority.
- Quick Onboarding public preview da giu draft theo token/entry; password min 6 ky tu; password co the tam nam trong session draft de quay lai sua khong mat du lieu nhung service van xoa truoc khi luu `submitted_payload`.
- Quick Onboarding public da bo CCCD vat ly 2 mat tren UI chinh, chi giu mot anh CCCD/VNeID.
- Quick Onboarding public canonical URL la `/quick-onboarding/invite/{token}`; URL cu `/onboarding/{token}` la alias redirect de giu tuong thich link da phat.
- Route/runtime da khoi phuc: `GET /attendance/dashboard`, `GET/PUT /me`, nhom `/attendance-supplements` gom bulk review, va `/branch-shift-permissions` chi cho admin.
- App UI/PWA da doi ten hien thi sang `Quan tri`/`Quản trị` va bottom navigation mobile da an khi focus input/textarea/select de tranh che form luc ban phim mo.
- `/users` da lam lai UI theo style Quick Onboarding: card/grid responsive, avatar that tu `face_image_path`, fallback chu cai, thong tin chi nhanh/role/trang thai va action hien co.
- Form `resources/views/users/create.blade.php`, `edit.blade.php`, `me.blade.php` da chia thanh cac box/card nho: anh dai dien, thong tin co ban, CCCD, cong viec, nhan dien/QR, doi mat khau neu co. Khong them Zalo va khong them field khong co cot DB.
- Phan quyen update nhan su da duoc siem o backend: admin sua toan bo field quan tri; manager chi sua nhan su trong chi nhanh va role staff/cashier; staff/cashier chi update field ca nhan qua `/me`. Request co field trai quyen se bi bo qua/khong validate vao update data.
- User management edit va `/me` da bo Zalo khoi form; backend update khong cap nhat `zalo_phone` trong cac flow nay.
- `/users` list, form edit nhan su va form ca nhan uu tien hien avatar that tu `face_image_path`, fallback chu cai neu chua co anh. Avatar upload moi luu vao `storage/app/public/faces`.
- OCR frontend da tam disable co chu dich, backend OCR/PaddleOCR van con de bat lai sau.
- Anh/avatar truoc do tung co loi khong hien thi, nhung nguoi dung da xac nhan hien tai anh da hien thi duoc.
- PWA service worker da cau hinh network/no-store cho dashboard, QR rating, rating feed/notification, api, auth, attendance.

## 10. Van de con ton dong / bug can xu ly

- Nhieu chuoi tieng Viet trong source/terminal hien mojibake (`Ä`, `áº`, `Ã`...), can kiem tra encoding file va hien thi thuc te truoc khi sua UI text.
- Quick Onboarding autosave expected_name co trong Blade inline script, nhung asset Vite `resources/js/quick-onboarding.js` van con code cu cho nut luu thu cong; neu don dep sau nay can can than de khong pha share/sync.
- Quick Onboarding public draft preview hien dua vao session theo entry; neu user doi trinh duyet/thiet bi giua form va preview thi draft khong di theo. Admin batch/list khong phu thuoc session vi da fallback DB.
- OCR PaddleOCR dang tam tat frontend co chu dich; neu bat lai can kiem tra Python/Paddle/OpenCV/Process tren local va hosting.
- Public onboarding preview/QR rating anh co resolver data URI va nguoi dung da xac nhan anh hien duoc; khi deploy van can `php artisan storage:link`/public storage dung de tranh loi anh tai xuat hien.
- `routes/api.php` khong thay trong source; cac endpoint JSON hien nam trong `routes/web.php`.
- Public QR rating co inline override CSS lon trong Blade; can than neu sua logo/avatar/responsive.
- Service worker cache chi cache static asset, nhung khi sua `public/sw.js` can tang cache schema/version va test logout/user switch.
- Shared hosting co the khong cho chay Python Process/PaddleOCR; dung manual fill neu khong co OCR runtime.

## 11. Nhung thu KHONG duoc pha khi sua tiep

- Khong sua thuat toan QR cham cong (`QrController`, `qr_tokens`) neu task khong yeu cau.
- Khong sua thuat toan QR rating/token/reward/fraud neu task chi lien quan UI/onboarding.
- Neu sua QR rating, luu y setting `rating_require_active_attendance` chi duoc dieu khien dieu kien "dang checkin trong ca"; khong duoc tat cac rao chan khac nhu QR enabled/token hop le/internal-device/duplicate/fraud/reward.
- Khong thay doi `UserController@store` khi chi sua Quick Onboarding public.
- Khong xoa/doi route short QR `/{publicRatingCode}`; route nay phai o cuoi file.
- Khong dua secret/API key/password/token that vao code hoac tai lieu.
- Khong lam mat `submitted_payload` da bo password.
- Khong random ID nhan su. Ma nhan su hien dung `users.employee_code` 4 chu so theo tung chi nhanh; neu sua tiep phai giu unique `(branch_id, employee_code)` va khong tu doi ma khi chuyen chi nhanh neu khong co ly do nghiep vu ro.
- Khong sua `public/UI` neu chi la file tham khao.
- Khong refactor lon, khong format toan project.
- Khong xoa `public/build` neu workflow deploy dang can upload build output.

## 12. Deploy local va production

- Local Windows/XAMPP:
  - Cai composer/npm dependencies.
  - Cau hinh `.env` DB, app key, session/cache.
  - Chay migrations bang `php artisan migrate`.
  - Sau thay doi QR rating active attendance toggle, can chay migration de them setting default va cho phep `attendance_id` nullable trong `customer_ratings`/`customer_rating_rewards`.
  - Sau thay doi ma nhan su, can chay migration `2026_07_06_000002_add_employee_code_to_users_table.php` de them/backfill `users.employee_code` va unique index.
  - Build asset bang `npm run build`.
  - Tao public storage symlink bang `php artisan storage:link` neu dung file public.
  - Clear cache khi doi config/view: `php artisan optimize:clear`, `php artisan view:clear`.
- OCR local neu bat lai:
  - Can `OCR_PROVIDER=paddleocr`.
  - Can `OCR_PYTHON_BINARY`, `OCR_PADDLE_SCRIPT`, `OCR_TIMEOUT`.
  - Can Python venv co PaddleOCR, OpenCV, Numpy.
  - Can quyen ghi `storage/app/quick-onboarding/ocr-temp` va `ocr-normalized`.
- Production/shared hosting:
  - Upload source, `public/build`, static public assets, `public/sw.js`, `manifest.json`, icons/images.
  - Khong chay `migrate:fresh` tren production.
  - Chay migrations tang dan, backup DB truoc khi migrate.
  - Neu hosting tach public/source, dam bao document root tro vao `public`.
  - Dam bao `APP_URL`, `ASSET_URL` neu co, HTTPS, cookie/session domain.
  - PaddleOCR tren shared hosting co rui ro cao; neu khong co Python Process thi de OCR frontend disabled va manual fill.

## 13. Danh cho AI/Codex/ChatGPT doc truoc khi sua code

Du an la he thong ChamCongV2 cho quan ly nhan su, ca lam, cham cong QR/face/GPS, request bo sung cong/nghi phep, dashboard, payroll, QR rating khach hang, reward/fraud review, va Quick Onboarding nhan su moi.

File nen doc truoc khi sua:

- Route lien quan trong `routes/web.php`.
- Controller/service/view cua module can sua.
- Neu lien quan Quick Onboarding: `QuickOnboardingController`, `QuickOnboardingPublicController`, `QuickOnboardingService`, `CitizenIdOcrService`, `resources/views/quick_onboarding/*`, `resources/js/quick-onboarding*.js`.
- Neu lien quan rating: `QrRatingController`, `RatingSubmissionService`, `RatingEligibilityService`, `RewardCalculationService`, `RatingFraudRiskService`, `resources/views/qr_ratings/*`, `public/css/qr-rating.css`, `public/js/qr-rating.js`.
- Neu lien quan attendance: `AttendanceController`, `ShiftScheduleService`, `AttendanceShiftResolver`, `AttendanceCalculationService`, models attendance/shift.
- Neu lien quan storage/anh: model field path, disk `public`, `storage:link`, resolver URL, `User::getAvatarUrlAttribute()`.
- Neu lien quan ID nhan su: doc `EmployeeCodeService`, `User::getEmployeeCodeAttribute()`, migration `2026_07_06_000002_add_employee_code_to_users_table.php`, `UserController`, `QuickOnboardingService`, cac view dung `$user->employee_code`.
- Neu lien quan UI/phan quyen nhan su: doc `UserController`, `User` model, `resources/views/users/index.blade.php`, `create.blade.php`, `edit.blade.php`, `me.blade.php`, `resources/views/layouts/navigation.blade.php`, `resources/css/navigation.css`, `resources/js/app.js`.

Nguoi dung uu tien sua dung pham vi, it rui ro, khong refactor, khong sua module cu neu task khong yeu cau. Can bao cao ngan, ro file sua va lenh da chay.

## 14. Checklist truoc khi AI sua code lan sau

- Doc `tomtat.md`.
- Kiem tra route lien quan trong `routes/web.php`.
- Kiem tra controller/service/view/JS/CSS dung module.
- Kiem tra model/migration/cot DB truoc khi dung field.
- Neu lien quan anh, kiem tra storage path, public path, `Storage::url`, `asset`, symlink.
- Neu lien quan OCR/Python, kiem tra `.env` names va runtime, khong log secret.
- Neu lien quan PWA/cache, kiem tra `public/sw.js` va no-store headers.
- Neu lien quan ma nhan su, giu logic `users.employee_code` 4 chu so theo chi nhanh va unique `(branch_id, employee_code)`, khong sua bang cach random.
- Chi sua file can sua, khong format toan project.
- Khong sua `public/UI` tru khi user yeu cau ro.
- Chay dung lenh user cho phep; khong tu chay test suite neu user cam.

## 15. Cac yeu cau chac chan cua nguoi dung hien tai

- Quick Onboarding phai UI/UX dep, de dung, khong pha layout dang on.
- Ten du kien trong Quick Onboarding phai autosave, khong yeu cau bam nut luu thu cong. Source hien tai dang co inline autosave debounce trong Blade.
- User chua xac nhan dang ky van co the doi ten/thong tin du kien.
- OCR CCCD/VNeID dang tam tat co chu dich do con bug moi truong Python/PaddleOCR; khong tu y bat lai neu task khong yeu cau.
- OCR chi uu tien CCCD dien tu/VNeID mot anh; khong quay lai luong CCCD vat ly hai mat neu chua duoc yeu cau.
- User tao tu Quick Onboarding mac dinh dung xac nhan guong mat kieu uu tien (`face_verification_mode = priority`).
- Anh/avatar hien da hien thi duoc; khi sua tiep khong duoc lam hong lai.
- ID nhan su khong random; source hien dung ma 4 chu so tang dan theo tung chi nhanh dang `0001`, `0002`, `0003`...
