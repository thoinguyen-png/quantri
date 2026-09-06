# Huong dan kiem tra va sua OCR CCCD

## 1. Cac loi da thay trong source cu

1. `resources/js/quick-onboarding-public.js` dat `OCR_ENABLED = false`, nen ham OCR khong chay.
2. Form public khong co `data-ocr-url`, nen frontend khong biet endpoint can goi.
3. `routes/web.php` khong co cac route Quick Onboarding va route OCR.
4. Python dung `lang="en"` thay vi model Latin/Viet Nam `lang="vi"`.
5. Parser chi nhan CCCD 12 so lien nhau, nen chuoi OCR nhu `08220401 6369` bi bo qua.
6. Parser de lay nham dong rac vao dia chi va khong lam sach dau `!` trong ho ten.
7. Log cu cho thay `import paddle` loi `NameError: base_events is not defined`. Day la loi moi truong Python/Paddle bi hong, khong phai loi regex.
8. Anh CCCD tam khong duoc xoa sau OCR. Thu muc `storage/app/quick-onboarding/ocr-temp` va `ocr-normalized` dang giu nhieu anh nhay cam.
9. File zip du an chua `.env`, `.venv`, `node_modules`, `vendor`, log va anh CCCD tam. Khong nen gui/goi du an theo cach nay ra ngoai.

## 2. Kiem tra sau khi chep patch

Chay trong CMD tai thu muc du an:

```bat
php artisan optimize:clear
php scripts\check_cccd_parser.php
scripts\check_paddle_runtime.bat
php artisan route:list | findstr quick-onboarding
npm install
npm run build
```

Ket qua parser dung phai co:

```text
[PASS] standard-vneid
[PASS] noisy-ocr
```

Route OCR phai xuat hien:

```text
POST quick-onboarding/invite/{token}/ocr quick-onboarding.public.ocr
```

## 3. Neu `check_paddle_runtime.bat` van bao `base_events is not defined`

Moi truong `.venv` hien tai da hong hoac bi tron goi khong tuong thich. Tao lai sach:

```bat
rmdir /s /q .venv
py -3.11 -m venv .venv
.venv\Scripts\python.exe -m pip install --upgrade pip setuptools wheel
.venv\Scripts\python.exe -m pip install paddlepaddle==2.6.2
.venv\Scripts\python.exe -m pip install paddleocr==2.7.3
.venv\Scripts\python.exe -m pip check
.venv\Scripts\python.exe -c "import paddle; print(paddle.__version__); paddle.utils.run_check()"
.venv\Scripts\python.exe -c "from paddleocr import PaddleOCR; print('PaddleOCR OK')"
```

Sau do chay lai:

```bat
scripts\check_paddle_runtime.bat
```

Kiem tra `.env` dung duong dan thuc te:

```env
OCR_PROVIDER=paddleocr
OCR_PYTHON_BINARY=D:/xampp/htdocs/chamcong/chamcongv2/.venv/Scripts/python.exe
OCR_PADDLE_SCRIPT=scripts/paddle_ocr_vneid.py
OCR_TIMEOUT=120
```

Neu thu muc du an khong phai `D:/xampp/htdocs/chamcong/chamcongv2`, phai sua `OCR_PYTHON_BINARY`.
Sau khi sua `.env`:

```bat
php artisan config:clear
```

## 4. Kiem tra tren trinh duyet

1. Mo trang loi moi Quick Onboarding.
2. Bam F12, chon tab **Network**.
3. Chon anh CCCD.
4. Phai co request:

```text
POST /quick-onboarding/invite/{token}/ocr
```

5. Mo response va kiem tra:

```json
{
  "ok": true,
  "message": "Da doc thong tin CCCD.",
  "data": {
    "name": "...",
    "citizen_id": "...",
    "date_of_birth": "YYYY-MM-DD",
    "gender": "Nam",
    "place_of_origin": "...",
    "address": "...",
    "issue_date": "YYYY-MM-DD",
    "raw_text": "..."
  }
}
```

### Cach doc ma loi

- `404`: chua co route OCR hoac route cache cu.
- `419`: CSRF/session het han; tai lai trang.
- `422`: file khong phai anh hoac lon hon 5 MB.
- `500`: xem `storage/logs/laravel.log`.
- HTTP 200, `raw_text` co chu nhung cac field rong: parser chua nhan duoc bo cuc OCR; luu `raw_text` de bo sung test case.
- Khong co request OCR: Vite chua build lai hoac dang dung asset cu.

## 5. Xoa anh CCCD tam cu

Sau khi da sao luu neu can debug, xoa du lieu tam cu:

```bat
del /q storage\app\quick-onboarding\ocr-temp\* 2>nul
del /q storage\app\quick-onboarding\ocr-normalized\* 2>nul
```

Ban sua moi tu dong xoa anh tam sau moi lan OCR.

## 6. File da thay doi

- `routes/web.php`
- `resources/views/quick_onboarding/public/form.blade.php`
- `resources/js/quick-onboarding-public.js`
- `scripts/paddle_ocr_vneid.py`
- `app/Services/CitizenIdOcrService.php`
- `scripts/check_cccd_parser.php`
- `scripts/check_paddle_runtime.bat`
- `tests/Unit/CitizenIdOcrServiceTest.php`
- `.gitignore`
