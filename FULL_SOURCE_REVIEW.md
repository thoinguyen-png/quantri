# Full Source Review - ChamCongV2

Ngay lap: 2026-07-11  
Cap nhat sau sua route/runtime: 2026-07-11  
Pham vi: doc source hien tai trong workspace `D:\xampp\htdocs\chamcong\chamcongv2`, doi chieu voi `TomTat.md`, sau do khoi phuc route/runtime thieu va tat OCR frontend theo task ngay 2026-07-11.

## 1. Cach thuc kiem tra

Da doc va doi chieu cac nhom file sau:

- Tai lieu hien tai: `TomTat.md`.
- Route: `routes/web.php`, `routes/auth.php`.
- Bootstrap/middleware/provider: `bootstrap/app.php`, `app/Http/Middleware/*`, `app/Providers/AppServiceProvider.php`.
- Auth: `app/Http/Controllers/Auth/*`, `app/Http/Requests/Auth/LoginRequest.php`.
- Controllers chinh: `UserController`, `QuickOnboardingController`, `QuickOnboardingPublicController`, `AttendanceController`, `QrRatingController`, `SettingController`, `LeaveRequestController`, `AttendanceSupplementRequestController`.
- Services chinh: `QuickOnboardingService`, `QuickOnboardingShareService`, `EmployeeCodeService`, `CitizenIdOcrService`, `RatingEligibilityService`, `RatingSubmissionService`, `RewardCalculationService`, `RatingFraudRiskService`, `GuestBrowserTokenService`, `InternalDeviceMarkerService`, attendance/shift services.
- Models chinh: `User`, `Branch`, `Attendance`, `CustomerRating`, `CustomerRatingReward`, `RatingQrToken`, `QuickOnboardingBatch`, `QuickOnboardingEntry`, `Setting`.
- Views/JS/CSS chinh: `resources/views/layouts/*`, `resources/views/users/*`, `resources/views/quick_onboarding/*`, `resources/views/qr_ratings/*`, `resources/views/settings/edit.blade.php`, `resources/js/quick-onboarding*.js`, `resources/css/navigation.css`, `public/sw.js`, public QR rating assets.
- Database: migrations lien quan users, branches, attendance, shift, rating/reward/risk, quick onboarding, settings, employee code.
- Build/config: `composer.json`, `package.json`, `vite.config.js`, `config/app.php`, `config/services.php`, `public/manifest.json`.
- Scripts: `scripts/paddle_ocr_vneid.py`.

Loai tru khoi doc sau: `vendor`, `node_modules`, `.git`, log/cache/framework runtime, `public/build` chi tiet.

## 2. Tong quan cong nghe

- Backend: Laravel 12, PHP 8.2+, Breeze, guard `web`.
- Frontend: Blade, Tailwind, AlpineJS, vanilla JS, Vite.
- DB: MySQL/MariaDB qua migrations.
- QR: `bacon/bacon-qr-code`.
- OCR: backend co `CitizenIdOcrService` goi Python/PaddleOCR qua Symfony Process va script `scripts/paddle_ocr_vneid.py`.
- PWA: `public/sw.js`, `public/manifest.json`, icons trong `public/icons`.
- App name: source hien co `APP_NAME="Quản trị"` trong `.env.example`, `public/manifest.json` la `Quản trị`. Mot so output terminal hien mojibake do encoding, nhung file rg doc duoc gia tri co dau.

## 3. Route map hien tai

### Public / guest

- `/login`, `/register`, reset password, session expired/keepalive/status nam trong `routes/auth.php`.
- QR rating public:
  - `GET/POST /qr-rating/{token}`
  - `GET/POST /{publicRatingCode}` o cuoi `routes/web.php`, constrain 8 ky tu.
  - `GET /rating-thank-you`
- Quick Onboarding public hien tai:
  - Prefix thuc te: `/quick-onboarding/invite/{token}`
  - Names: `quick-onboarding.public.show`, `ocr`, `preview`, `confirm`
  - Co throttle OCR `10,1`.
  - Alias cu `GET /onboarding/{token}` redirect sang route canonical, name `quick-onboarding.public.legacy`.

### Authenticated

- Dashboard, attendance, QR cham cong, shifts, shift assignments, reports, payroll, settings, users, quick onboarding admin, rating feed/review/notifications.
- `/attendance/dashboard` -> `DashboardController::attendance`, name `attendance.dashboard`.
- `/me` co `GET /me` -> `UserController::me` va `PUT /me` -> `UserController::updateMe`.
- `attendance-supplements` route block da duoc khoi phuc, gom create/day-info/store va admin/manager approve/reject/bulk-review.
- `branch-shift-permissions` da duoc khoi phuc trong group admin.
- `leave-requests` route block dang active.

## 4. Module Auth va phan quyen

- `LoginRequest` cho phep login bang email hoac phone: neu input hop le email thi dung field `email`, nguoc lai dung `phone`.
- Login thanh cong attach internal device marker qua `InternalDeviceMarkerService` de chan QR rating public tren cung browser/PWA noi bo.
- `RoleMiddleware` don gian: yeu cau auth va role nam trong danh sach route.
- `PreventBackHistory` append vao web middleware, them no-store headers cho GET HTML.
- `AuthenticatedSessionController` co logic cashier desktop session keepalive.

Rui ro/ghi chu:

- `AppServiceProvider` dang `URL::forceScheme('https')` khi `app()->environment('local')`. Voi XAMPP/http local, viec nay co the lam URL generate sang HTTPS ngoai y muon neu local khong phuc vu HTTPS.

## 5. Module quan ly nhan su

Files chinh:

- `app/Http/Controllers/UserController.php`
- `app/Models/User.php`
- `app/Services/EmployeeCodeService.php`
- `resources/views/users/index.blade.php`
- `resources/views/users/create.blade.php`
- `resources/views/users/edit.blade.php`
- `resources/views/users/me.blade.php`

Trang thai source:

- Admin/manager vao `/users`.
- Manager bi scope theo branch va chi quan ly role `staff/cashier`.
- `UserController::store()` dung `EmployeeCodeService` de sinh `employee_code` theo branch neu de trong.
- `UserController::update()` co phan quyen backend:
  - Admin sua duoc field quan tri.
  - Manager sua nhan su cung branch role `staff/cashier`, co the sua role trong `staff/cashier`, branch bi co dinh branch cua manager.
  - Staff/cashier khong vao flow `/users`.
- `UserController::updateMe()` ton tai va chi cho update field ca nhan: name/email/phone/citizen/avatar.

Mau nhan su:

- `users.employee_code` la cot rieng, 4 chu so.
- Migration `2026_07_06_000002_add_employee_code_to_users_table.php` backfill theo tung `branch_id`, sort theo `id`, unique `(branch_id, employee_code)`.
- `User::getEmployeeCodeAttribute()` chi format cot thanh 4 chu so hoac `----`, khong con sinh tu `users.id`.

Rui ro/loi:

- `GET/PUT /me` da duoc khai bao. `resources/views/users/me.blade.php` post toi `route('users.me.update')` va route nay tro ve `UserController::updateMe()`.
- `User` fillable van co `zalo_phone`, nhung form user hien khong cap nhat Zalo. Day khop yeu cau khong them Zalo vao UI.
- DB migration goc co `email` unique/not null; cac migration sau co the da nullable email. Can xem DB thuc te truoc khi deploy vi onboarding cho email nullable.

## 6. Quick Onboarding admin

Files chinh:

- `routes/web.php`
- `app/Http/Controllers/QuickOnboardingController.php`
- `app/Services/QuickOnboardingService.php`
- `app/Services/QuickOnboardingShareService.php`
- `app/Models/QuickOnboardingBatch.php`
- `app/Models/QuickOnboardingEntry.php`
- `resources/views/quick_onboarding/index.blade.php`
- `resources/js/quick-onboarding.js`

Flow hien tai:

1. Admin/manager vao `/quick-onboarding`.
2. Chon branch va quantity, submit tao batch moi hoac add-more vao batch hien tai.
3. `QuickOnboardingService::addEntries()` tao N entries:
   - `token`: random 72 ky tu.
   - `demo_code`: tang trong batch, pad 3 so `001..999`.
   - `branch_id`: branch duoc chon.
   - `intended_role`: default `staff`.
   - `status`: `draft`.
4. Bulk update van con trong controller/service, nhung UI moi dang huong card-based.
5. Expected name autosave:
   - Route `POST /quick-onboarding/entries/{entry}/expected-name`.
   - Controller validate max 255 va can access entry.
   - `resources/js/quick-onboarding.js` co debounce 700ms, blur/Enter save.
6. Role per entry:
   - Route `POST /quick-onboarding/entries/{entry}/role`.
   - Admin allowed `manager/staff/cashier`; manager allowed `staff/cashier`.
7. Avatar per entry:
   - Route `POST /quick-onboarding/entries/{entry}/avatar`.
   - Luu disk public `quick-onboarding/avatars`.
8. Share:
   - Copy link/message, quick share Web Share API, QR modal/download, Zalo/Messenger.
   - `markSent` doi status sang `sent` neu chua completed.
9. Sync:
   - `GET /quick-onboarding/sync`.
   - Polling 10 giay khi document visible.
   - Payload co `id`, `status`, `color_class`, `employee_name`, `expected_name`, `branch_name`, `role`, `avatar_url`, `invite_url`, `share_message`.

Diem dung:

- Khong dung localStorage/sessionStorage lam data chinh.
- Neu session current batch mat, controller fallback latest accessible batch theo scope.
- `avatar_url` da duoc build an toan: entry avatar truoc, completed user avatar sau, null neu khong co file/URL hop le.

Rui ro/ghi chu:

- `resources/js/quick-onboarding.js` van co logic cu cho nut save thu cong neu selector ton tai, nhung view hien tai autosave that bang data attributes. Khong phai loi nghiem trong.
- `resources/views/quick_onboarding/index.blade.php` co nhieu CSS/JS inline lon; sua UI can can than vi de va cham share/sync/autosave.

## 7. Quick Onboarding public

Files chinh:

- `routes/web.php`
- `app/Http/Controllers/QuickOnboardingPublicController.php`
- `app/Services/QuickOnboardingService.php`
- `resources/views/quick_onboarding/public/form.blade.php`
- `resources/views/quick_onboarding/public/preview.blade.php`
- `resources/views/quick_onboarding/public/completed.blade.php`
- `resources/js/quick-onboarding-public.js`
- `resources/css/quick-onboarding-public.css`

Flow thuc te:

1. Link canonical hien tai la `/quick-onboarding/invite/{token}`; link cu `/onboarding/{token}` redirect sang canonical.
2. `show()` lookup entry by token, neu completed thi hien completed, nguoc lai hien form.
3. Form post preview toi `quick-onboarding.public.preview`.
4. Preview validate toi thieu name/phone/password va cac field optional.
5. Avatar luu `faces` tren disk public; CCCD luu `quick-onboarding/citizen-ids`.
6. Preview data luu session theo key `quick_onboarding_preview_{entry_id}`.
7. Confirm goi `QuickOnboardingService::createUserFromOnboarding()` trong DB transaction.
8. User tao moi:
   - branch/role tu entry.
   - employee_code sinh theo branch.
   - status `thu_viec`, employment `probation`.
   - `face_image_path` tu avatar.
   - `face_verification_mode = priority`.
   - password hash.
9. Entry set completed, `completed_user_id`, `submitted_payload` bo password.
10. User moi duoc login va attach internal-device marker.

Rui ro/loi:

- OCR frontend da duoc tat bang `const OCR_ENABLED = false;` trong `resources/js/quick-onboarding-public.js`. Khi chon anh CCCD/VNeID, JS chi hien thong bao nhap tay va khong fetch endpoint OCR.
- `preview()` van chap nhan fallback `electronic_image` ngoai `cccd_image`, du UI chinh chi dung `cccd_image`. Khong gay loi, nhung la dau vet cu.
- Public route canonical la `/quick-onboarding/invite/{token}`; alias `/onboarding/{token}` giu tuong thich link cu.

## 8. OCR CCCD/VNeID

Files chinh:

- `app/Services/CitizenIdOcrService.php`
- `scripts/paddle_ocr_vneid.py`
- `QuickOnboardingPublicController::ocr()`
- `resources/js/quick-onboarding-public.js`

Trang thai source:

- Backend OCR ton tai, cau hinh qua `config/services.php`/ENV:
  - `OCR_PROVIDER`
  - `OCR_PYTHON_BINARY`
  - `OCR_PADDLE_SCRIPT`
  - `OCR_TIMEOUT`
- Service normalize anh upload sang JPG trong storage, goi Python bang Symfony Process array, parse raw text thanh schema.
- Python script co xu ly PaddleOCR va output JSON.
- Controller OCR nhan `cccd_image`.

Lech voi TomTat:

- Frontend OCR da tam tat co chu dich bang `OCR_ENABLED=false`; source khong auto-call OCR khi chon anh CCCD.

Rui ro:

- Neu moi truong Python/PaddleOCR web chua on dinh, form public co the hien loi OCR.
- Shared hosting/cPanel thuong khong phu hop PaddleOCR local vi can Python process, venv, OpenCV/Numpy, quyen chay process va ghi storage.

## 9. Attendance / cham cong QR-GPS-face

Files chinh:

- `AttendanceController`
- `QrController`
- `AttendanceCalculationService`
- `AttendanceStatusService`
- `ShiftScheduleService`
- `AttendanceShiftResolver`
- views `resources/views/attendance/*`, `resources/views/qr/show.blade.php`

Flow chinh:

- QR cham cong:
  - `/qr` hien QR, `/qr/generate` tao token.
  - `/attendance/scan/{token}` validate token/expiry/branch, luu session `qr_token`, `qr_branch_id`, `qr_scanned_at`.
- Checkin:
  - Yeu cau face descriptor da dang ky.
  - Validate face pass session, QR session <=180s, GPS nearest branch.
  - Resolve shift, strict mode setting, attendance segments neu ca gay.
  - Transaction tao checkin/checkout/segment.

Rui ro/ghi chu:

- Day la module phuc tap, khong nen sua neu task khong lien quan.
- `attendance-supplements` controller ton tai va route da duoc khoi phuc. Xem muc 12.

## 10. QR rating / danh gia nhan vien

Files chinh:

- `QrRatingController`
- `RatingQrController`
- `RatingQrService`
- `RatingEligibilityService`
- `RatingSubmissionService`
- `RewardCalculationService`
- `RatingFraudRiskService`
- `GuestBrowserTokenService`
- `InternalDeviceMarkerService`
- `resources/views/qr_ratings/show.blade.php`
- `public/css/qr-rating.css`, `public/js/qr-rating.js`

Flow hien tai:

- GET public rating:
  - Check internal-device marker truoc.
  - Resolve token/public code.
  - Check QR token enabled/revoked, employee QR enabled, role hop le, branch.
  - Check duplicate daily theo guest browser hash.
  - Build view state allowed/blocked.
- POST:
  - Lop bao ve cuoi lai check internal device.
  - `RatingSubmissionService` transaction/lock token.
  - Re-resolve eligibility.
  - Duplicate daily, browser branch employee cap, fraud/risk/reward, notification.
- Setting `rating_require_active_attendance`:
  - Default true.
  - Khi false, `RatingEligibilityService` cho rating khong co attendance hien tai, branch lay tu employee branch, attendance/shift nullable.
  - Cac lop token/QR enabled/internal-device/duplicate/fraud/reward/validate van giu.

Rui ro/ghi chu:

- Composite unique cu tren `customer_ratings` co `attendance_id`; voi MySQL, NULL trong unique cho phep nhieu row co NULL. Duplicate daily hien duoc chan o service bang `employee_id + business_date + guest_browser_hash`, nhung DB unique khong phai lop chan cuoi khi attendance null.
- Public QR rating co CSS/override lon, nen sua UI can rat can than.

## 11. Settings

Files chinh:

- `SettingController`
- `Setting` model
- `resources/views/settings/edit.blade.php`

Settings hien co:

- Attendance strict mode, overnight cutoff, probation auto promote, show all shifts, leave/supplement limits, probation workdays.
- QR rating/reward:
  - `rating_require_active_attendance`
  - `good_reward_amount`
  - `max_rewarded_good_per_employee_per_business_date`
  - `max_distinct_employees_per_guest_browser_per_branch_per_business_date`

Ghi chu:

- `Setting::CUSTOMER_RATING_DEFAULTS` co them cac nguong fraud/risk, nhung form/controller update hien tai chi expose/validate mot phan settings rating. Neu can admin cau hinh fraud thresholds, can task rieng.

## 12. Attendance supplement / Leave request

Leave request:

- Routes active.
- Staff/cashier/manager tao request.
- Manager xem/duyet minh + staff/cashier cung branch.
- Admin duyet toan bo.
- Co auto reject pending qua 3 ngay trong controller.

Attendance supplement:

- `AttendanceSupplementRequestController` va views/models/migrations ton tai.
- `routes/web.php` da khoi phuc route block `attendance-supplements`.
- Cac route dang co: `index`, `create`, `day-info`, `store`, `bulk-review`, `approve`, `reject`.
- `AppNavigation`, `resources/views/layouts/navigation.blade.php`, `resources/views/components/mobile-nav.blade.php` co link toi cac route nay.

Rui ro/loi:

- Khong con route missing cho `attendance-supplements.*` sau khi khoi phuc. Van can test manual approve/reject/bulk review vi controller logic phuc tap va khong sua trong task nay.

## 13. PWA / cache / assets

Files chinh:

- `public/sw.js`
- `resources/views/layouts/partials/assets.blade.php`
- `public/manifest.json`

Trang thai:

- Static cache cho `/build/assets`, QR rating public static CSS/JS/logo, icons, manifest.
- Dynamic/private paths dung network/no-store cho nhieu route: dashboard, auth, attendance, users, shifts, leave/payroll/admin/api/ratings/qr-rating.
- HTML navigation requests mac dinh fetch network no-store.

Rui ro/ghi chu:

- `public/sw.js` khong thay explicit dynamic path cho `/quick-onboarding` hoac `/quick-onboarding/invite`, du HTML navigation fetch network no-store se giam rui ro cache. Neu Quick Onboarding/PWA co bug stale data, nen them explicit path sau.

## 14. Public/production hygiene

Phat hien file/phu luc can luu y:

- Root co file debug/test/tai lieu nhu `__laravel_runtime_debug.php`, `debug_registration.php`, `test_ocr_process.php`, `OCR_FIX_GUIDE.md`, cac zip/backup. Neu document root dung sai khong tro vao `/public`, cac file nay co the bi truy cap. Production nen document root vao `public` va don dep debug file neu khong can.
- Project folder hien tai khong co `.git` repository theo `git status` (`fatal: not a git repository`). Khong co version control visible trong workspace hien tai.

## 15. Doi chieu voi TomTat.md

### Dung voi source

- Laravel 12, PHP 8.2+, Breeze, Blade/Tailwind/Alpine/Vite.
- Login bang email hoac phone.
- Employee code hien la cot `users.employee_code`, 4 chu so theo branch, unique `(branch_id, employee_code)`.
- Quick Onboarding admin co batch/entry/token/demo_code/expected_name/share/sync/avatar/status.
- QR rating active-attendance setting ton tai va default true.
- Avatar user uu tien `face_image_path`, resolver data URI/asset/storage co trong `User::getAvatarUrlAttribute()`.
- Internal device marker dung de chan QR rating public tren browser noi bo.

### Da cap nhat/sua sau review

1. Quick Onboarding public canonical route la `/quick-onboarding/invite/{token}` va co alias cu `/onboarding/{token}` redirect sang canonical.
2. OCR frontend da co `OCR_ENABLED=false`, khong fetch OCR khi chon `cccd_image`.
3. `/me` da co GET va PUT; `users.me.update` tro ve `UserController::updateMe`.
4. `attendance-supplements/*` da duoc khoi phuc, gom ca `attendance-supplements.bulk-review`.
5. `attendance.dashboard` da duoc khoi phuc.
6. `branch-shift-permissions.*` da duoc khoi phuc trong group admin.

### Con lech/ghi chu voi source

1. `TomTat.md` noi settings gom rating reward/risk. Source settings UI/update chi expose mot phan reward/rating; risk threshold defaults ton tai nhung khong thay form update day du.

## 16. Priority issues

### P0 - Da xu ly trong luot sua route/runtime 2026-07-11

1. **Da them route `users.me.update`.**
2. **Da khoi phuc attendance supplement routes va bulk review.**
3. **Da tat OCR public frontend bang `OCR_ENABLED=false`.**
4. **Da them `attendance.dashboard`.**
5. **Da them `branch-shift-permissions.*` cho admin.**

### P1 - Lech flow/link hoac rui ro nghiep vu

4. **Quick Onboarding public URL canonical la `/quick-onboarding/invite/{token}`.**
   - Alias `/onboarding/{token}` da duoc them de redirect link cu, nen link cu khong con 404.

5. **AppServiceProvider force HTTPS trong local.**
   - File: `app/Providers/AppServiceProvider.php`.
   - Anh huong: XAMPP local HTTP co the bi generate HTTPS sai.

6. **Customer rating DB unique khong chan duplicate tot khi `attendance_id` null.**
   - File: migrations/customer_ratings, `RatingSubmissionService`.
   - Hien co service-level duplicate check, nhung DB-level unique khong con la lop chan cuoi cho mode khong require attendance.

### P2 - Hygiene / maintainability

7. Debug/test files o root nen duoc kiem tra truoc deploy.
8. Mojibake tieng Viet xuat hien trong nhieu file/output; can thong nhat encoding khi sua UI text.
9. Quick Onboarding admin CSS/JS inline rat lon; future UI fixes can de gay regression share/sync/autosave.
10. SW chua explicit no-store path cho Quick Onboarding; hien navigate fetch network no-store nhung co the them de ro hon.

## 17. De xuat file can doc/sua theo task tiep theo

### Neu sua `/me`

- `routes/web.php`
- `app/Http/Controllers/UserController.php`
- `resources/views/users/me.blade.php`

### Neu sua attendance supplements

- `routes/web.php`
- `app/Http/Controllers/AttendanceSupplementRequestController.php`
- `resources/views/attendance_supplements/*`
- `resources/views/layouts/navigation.blade.php`
- `app/Support/AppNavigation.php`

### Neu tam tat OCR dung nhu yeu cau manual fill

- `resources/js/quick-onboarding-public.js`
- `resources/views/quick_onboarding/public/form.blade.php`
- Khong can sua `CitizenIdOcrService` neu chi tat frontend.

### Neu sua Quick Onboarding UI/admin

- `resources/views/quick_onboarding/index.blade.php`
- `resources/js/quick-onboarding.js`
- `app/Http/Controllers/QuickOnboardingController.php`
- `app/Services/QuickOnboardingService.php`
- `public/UI/quick-onboarding-management-style-v3.html` chi la reference.

### Neu sua QR rating

- `QrRatingController`
- `RatingEligibilityService`
- `RatingSubmissionService`
- `RewardCalculationService`
- `RatingFraudRiskService`
- `resources/views/qr_ratings/show.blade.php`
- `public/css/qr-rating.css`
- `public/js/qr-rating.js`

### Neu sua employee code

- `EmployeeCodeService`
- `User::getEmployeeCodeAttribute()`
- migration `2026_07_06_000002_add_employee_code_to_users_table.php`
- `UserController`
- `QuickOnboardingService::createUserFromOnboarding()`

## 18. Trang thai sau sua route/runtime

Da sua trong luot 2026-07-11:

- `routes/web.php`: them `attendance.dashboard`, `users.me.update`, nhom `attendance-supplements`, `branch-shift-permissions.*`, alias `quick-onboarding.public.legacy`.
- `resources/js/quick-onboarding-public.js`: them `OCR_ENABLED=false`, khong goi `runOcr()` khi chon anh CCCD/VNeID.
- `TomTat.md`: cap nhat route canonical/alias, OCR disabled, route `/me`, attendance supplement, branch shift permissions.
- `FULL_SOURCE_REVIEW.md`: cap nhat cac loi da khoi phuc va sua ghi chu Quick Onboarding admin expected-name/role la POST.

## 19. Ket luan ngan

Source hien tai da khoi phuc cac route/runtime duoc neu trong task: bang cong `/attendance/dashboard`, ho so `/me` GET/PUT, attendance supplements day du bulk review, branch shift permissions cho admin, va alias Quick Onboarding cu. OCR frontend da duoc tat that su theo chien luoc manual fill; backend OCR van duoc giu de bat lai sau.

Van con cac ghi chu ngoai pham vi:

1. `AppServiceProvider` force HTTPS trong local co the gay bat tien neu XAMPP khong phuc vu HTTPS.
2. Source co nhieu chuoi mojibake, can can than khi sua UI text.
3. Public QR rating va Quick Onboarding admin co CSS/JS lon, can sua dung pham vi de tranh regression.
