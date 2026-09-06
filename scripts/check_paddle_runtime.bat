@echo off
setlocal
cd /d "%~dp0.."

set "PYTHON=.venv\Scripts\python.exe"
set "IMAGE=public\images\cccd.jpg"

if not exist "%PYTHON%" (
    echo [FAIL] Khong tim thay %PYTHON%
    exit /b 1
)

if not exist "%IMAGE%" (
    echo [FAIL] Khong tim thay anh test %IMAGE%
    exit /b 1
)

echo === Python ===
"%PYTHON%" --version || exit /b 1

echo.
echo === Import Paddle ===
"%PYTHON%" -c "import paddle; print('paddle=', paddle.__version__); paddle.utils.run_check()" || exit /b 1

echo.
echo === Import PaddleOCR ===
"%PYTHON%" -c "import paddleocr; print('paddleocr import OK')" || exit /b 1

echo.
echo === OCR sample ===
"%PYTHON%" scripts\paddle_ocr_vneid.py "%IMAGE%" > storage\logs\ocr-runtime-check.json
if errorlevel 1 (
    type storage\logs\ocr-runtime-check.json
    echo.
    echo [FAIL] OCR script tra ve loi.
    exit /b 1
)

type storage\logs\ocr-runtime-check.json
echo.
echo [PASS] PaddleOCR runtime va script dang hoat dong.
exit /b 0
