<x-app-layout>
    <div class="camera-flow-page bg-gray-100">
        <div class="camera-flow-card bg-white rounded-3xl shadow p-4 sm:p-5 space-y-4">
            <x-zalo-camera-warning />

            <div>
                <div class="text-sm text-blue-600 font-bold">Bước 1/2</div>
                <h1 class="text-2xl font-bold">Quét mã QR</h1>
                <p class="text-sm text-gray-500">
                    Hệ thống sẽ tự mở camera để quét mã chấm công.
                </p>
            </div>

            <div id="reader" class="qr-reader-frame rounded-3xl overflow-hidden bg-black"></div>

            <div id="scan-result" class="hidden p-3 rounded-2xl text-sm"></div>

            <div id="qr-permission-panel" class="hidden rounded-2xl border border-red-100 bg-red-50 p-4 text-sm text-red-800">
                <div class="font-bold" id="qr-permission-title">Không mở được Camera</div>
                <p class="mt-1" id="qr-permission-message">Vui lòng thử lại để cấp quyền Camera.</p>
                <button
                    type="button"
                    id="qr-start-permission-button"
                    onclick="requestCameraPermission()"
                    class="mt-3 w-full rounded-2xl bg-blue-600 px-4 py-3 font-bold text-white"
                >
                    Thử lại mở Camera
                </button>
                <button
                    type="button"
                    id="qr-manual-permission-toggle"
                    class="mt-3 hidden text-sm font-bold text-blue-700 underline underline-offset-4"
                    onclick="toggleManualPermissionHelp()"
                >
                    Xem hướng dẫn cấp quyền
                </button>
                <div id="qr-manual-permission-help" class="mt-3 hidden rounded-2xl bg-white/80 p-3 text-xs leading-5 text-slate-700">
                    Bấm biểu tượng ổ khóa trên thanh địa chỉ → Camera → Cho phép → tải lại trang.
                </div>
            </div>

            <button
                type="button"
                onclick="switchCamera()"
                id="qr-camera-switch-button"
                class="mobile-action-button hidden w-full bg-gray-900 text-white py-3 rounded-2xl font-bold shadow"
                 style="display: none;"
                >
                Đổi camera
            </button>

            <a href="{{ route('dashboard') }}"
               class="mobile-action-button block text-center bg-gray-200 py-3 rounded-2xl font-bold">
                Quay lại
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/html5-qrcode/html5-qrcode.min.js"></script>

    <script>
        let html5QrCode;
        let cameras = [];
        let currentCameraId = null;
        let preferredCameraId = null;
        let fallbackCameraId = null;
        let scanned = false;
        let isStartingCamera = false;
        let isSwitchingCamera = false;
        let isRequestingCamera = false;
        let isCameraReady = false;
        let cameraFailureCount = 0;

        const readerId = 'reader';
        const resultBox = document.getElementById('scan-result');
        const switchCameraButton = document.getElementById('qr-camera-switch-button');
        const permissionPanel = document.getElementById('qr-permission-panel');
        const permissionTitle = document.getElementById('qr-permission-title');
        const permissionMessage = document.getElementById('qr-permission-message');
        const retryButton = document.getElementById('qr-start-permission-button');
        const manualPermissionHelp = document.getElementById('qr-manual-permission-help');
        const manualPermissionToggle = document.getElementById('qr-manual-permission-toggle');
        const QR_CAMERA_DEBUG = @json(config('app.debug'));

        function qrCameraDebug(method, ...args) {
            if (QR_CAMERA_DEBUG && console[method]) {
                console[method](...args);
            }
        }

        function showMessage(message, type = 'success') {
            resultBox.classList.remove('hidden');
            const classes = {
                success: 'p-3 rounded-2xl text-sm bg-green-100 text-green-700',
                error: 'p-3 rounded-2xl text-sm bg-red-100 text-red-700',
                loading: 'p-3 rounded-2xl text-sm bg-yellow-100 text-yellow-700',
            };
            resultBox.className = classes[type] || classes.success;
            resultBox.innerText = message;
        }

        function hideMessageSoon(delay = 900) {
            setTimeout(() => resultBox.classList.add('hidden'), delay);
        }

        function isPermissionError(error) {
            return ['NotAllowedError', 'PermissionDeniedError'].includes(error?.name || '');
        }

        function cameraErrorMessage(error) {
            const name = error?.name || '';

            if (isPermissionError(error)) {
                return 'Bạn chưa cấp quyền Camera. Vui lòng bấm thử lại và chọn Cho phép.';
            }

            if (['NotFoundError', 'DevicesNotFoundError'].includes(name)) {
                return 'Không tìm thấy Camera.';
            }

            if (['NotReadableError', 'TrackStartError'].includes(name)) {
                return 'Không mở được Camera. Vui lòng thử lại. Nếu vẫn lỗi, hãy đóng ứng dụng khác có thể đang dùng Camera.';
            }

            if (name === 'SecurityError') {
                return 'Camera chỉ hoạt động trên HTTPS hoặc trình duyệt được hỗ trợ.';
            }

            if (error?.message === 'QR_LIBRARY_MISSING') {
                return 'Không tải được bộ quét QR. Vui lòng kiểm tra mạng rồi tải lại trang.';
            }

            if (error?.message === 'NO_CAMERA') {
                return 'Không tìm thấy Camera.';
            }

            return 'Không mở được Camera, vui lòng thử lại.';
        }

        function logCameraError(error) {
            qrCameraDebug('error', '[QR camera] error.name', error?.name || '');
            qrCameraDebug('error', '[QR camera] error.message', error?.message || '');
        }

        function showCameraError(error) {
            cameraFailureCount += 1;
            logCameraError(error);
            const message = cameraErrorMessage(error);
            showMessage(message, 'error');
            permissionPanel.classList.remove('hidden');
            permissionTitle.innerText = isPermissionError(error) ? 'Bạn chưa cấp quyền Camera' : 'Không mở được Camera';
            permissionMessage.innerText = message;
            retryButton.disabled = false;
            manualPermissionToggle.classList.toggle('hidden', cameraFailureCount < 2);
            manualPermissionHelp.classList.add('hidden');
            switchCameraButton.classList.add('hidden');
            isCameraReady = false;
        }

        function hideCameraError() {
            permissionPanel.classList.add('hidden');
            manualPermissionToggle.classList.add('hidden');
            manualPermissionHelp.classList.add('hidden');
        }

        function toggleManualPermissionHelp() {
            manualPermissionHelp.classList.toggle('hidden');
        }

        function setRetryDisabled(disabled) {
            isRequestingCamera = disabled;
            retryButton.disabled = disabled;
            retryButton.classList.toggle('opacity-60', disabled);
            retryButton.classList.toggle('cursor-not-allowed', disabled);
        }

        function normalizeQrTarget(decodedText) {
            let targetUrl;

            try {
                targetUrl = new URL(decodedText, window.location.origin);
            } catch (error) {
                return null;
            }

            if (!targetUrl.pathname.startsWith('/attendance/scan/')) {
                return null;
            }

            return targetUrl.pathname + targetUrl.search;
        }

        function markRenderedVideoInline() {
            document.querySelectorAll(`#${readerId} video`).forEach((video) => {
                video.setAttribute('playsinline', true);
                video.muted = true;
            });
        }

        function stopRenderedVideoTracks() {
            document.querySelectorAll(`#${readerId} video`).forEach((video) => {
                const stream = video.srcObject;

                if (stream && typeof stream.getTracks === 'function') {
                    stream.getTracks().forEach(track => track.stop());
                }

                video.srcObject = null;
            });

            isCameraReady = false;
        }

        async function stopScanner() {
            try {
                if (html5QrCode?.isScanning) {
                    await html5QrCode.stop();
                }
            } catch (error) {
                qrCameraDebug('warn', '[QR camera] stop failed', error);
            }

            try {
                if (html5QrCode) {
                    await html5QrCode.clear();
                }
            } catch (error) {
                qrCameraDebug('warn', '[QR camera] clear failed', error);
            }

            stopRenderedVideoTracks();
        }

        function scannerConfig() {
            return {
                fps: 10,
                qrbox: {
                    width: 250,
                    height: 250,
                },
                aspectRatio: 1.0,
            };
        }

        function onScanSuccess(decodedText) {
            if (scanned) return;

            const targetPath = normalizeQrTarget(decodedText);

            if (!targetPath) {
                window.location.href = @json(route('qr.unsupported', absolute: false));
                return;
            }

            scanned = true;
            showMessage('Đã quét QR. Đang chuyển sang xác minh mặt...');

            stopScanner().finally(() => {
                window.location.href = targetPath;
            });
        }

        function onScanFailure() {
            // Không hiện lỗi liên tục trong lúc camera đang quét.
        }

        function choosePreferredCamera(cameraList) {
            const backKeywords = ['back', 'rear', 'environment', 'sau'];
            const backCamera = cameraList.find((camera) => {
                const label = (camera.label || '').toLowerCase();
                return backKeywords.some((keyword) => label.includes(keyword));
            });

            return backCamera || cameraList[cameraList.length - 1] || cameraList[0] || null;
        }

        function firstCameraId() {
            return cameras[0]?.id || null;
        }

        async function loadCameras() {
            cameras = await Html5Qrcode.getCameras();
            qrCameraDebug('log', '[QR camera] cameras', cameras);

            if (!cameras.length) {
                throw new Error('NO_CAMERA');
            }

            const preferred = choosePreferredCamera(cameras);
            preferredCameraId = preferred?.id || null;
            fallbackCameraId = firstCameraId();

            if (!preferredCameraId) {
                throw new Error('NO_CAMERA');
            }
        }

        async function startScannerByDeviceId(cameraId) {
            if (!cameraId) {
                throw new Error('NO_CAMERA');
            }

            if (!html5QrCode) {
                html5QrCode = new Html5Qrcode(readerId);
            }

            qrCameraDebug('log', '[QR camera] starting cameraId', cameraId);
            await html5QrCode.start(cameraId, scannerConfig(), onScanSuccess, onScanFailure);
            currentCameraId = cameraId;
            markRenderedVideoInline();
            isCameraReady = true;
        }

        async function startQrCamera(cameraId = null) {
            if (isStartingCamera) return;

            isStartingCamera = true;

            try {
                hideCameraError();
                switchCameraButton.classList.add('hidden');
                showMessage('Đang mở camera, vui lòng chờ...', 'loading');

                await stopScanner();
                await loadCameras();

                const targetCameraId = cameraId || preferredCameraId || fallbackCameraId;

                try {
                    await startScannerByDeviceId(targetCameraId);
                } catch (firstError) {
                    logCameraError(firstError);

                    if (isPermissionError(firstError) || firstError?.name === 'SecurityError') {
                        throw firstError;
                    }

                    const firstId = fallbackCameraId;
                    if (!firstId || firstId === targetCameraId) {
                        throw firstError;
                    }

                    await stopScanner();
                    await startScannerByDeviceId(firstId);
                }

                switchCameraButton.classList.toggle('hidden', cameras.length < 2);
                showMessage('Camera đã sẵn sàng. Đưa mã QR vào khung để quét.', 'success');
                hideMessageSoon();
            } finally {
                isStartingCamera = false;
            }
        }

        async function requestCameraPermission() {
            if (isRequestingCamera || isStartingCamera) return;

            try {
                setRetryDisabled(true);
                await startQrCamera();
            } catch (error) {
                console.error(error);
                showCameraError(error);
            } finally {
                setRetryDisabled(false);
            }
        }

        async function switchCamera() {
            if (!isCameraReady || isSwitchingCamera || isRequestingCamera || isStartingCamera || cameras.length < 2) return;

            isSwitchingCamera = true;
            switchCameraButton.disabled = true;
            switchCameraButton.classList.add('opacity-60', 'cursor-not-allowed');

            try {
                const currentIndex = cameras.findIndex((camera) => camera.id === currentCameraId);
                const nextCamera = cameras[(currentIndex + 1 + cameras.length) % cameras.length] || cameras[0];
                await startQrCamera(nextCamera.id);
                showMessage('Đã đổi camera.', 'success');
                hideMessageSoon(1000);
            } catch (error) {
                console.error(error);
                showCameraError(error);
            } finally {
                setTimeout(() => {
                    isSwitchingCamera = false;
                    switchCameraButton.disabled = false;
                    switchCameraButton.classList.remove('opacity-60', 'cursor-not-allowed');
                }, 250);
            }
        }

        async function initScanner() {
            try {
                if (!window.Html5Qrcode) {
                    throw new Error('QR_LIBRARY_MISSING');
                }

                html5QrCode = new Html5Qrcode(readerId);
                await startQrCamera();
            } catch (error) {
                console.error(error);
                showCameraError(error);
            }
        }

        window.addEventListener('pagehide', stopScanner);
        window.addEventListener('beforeunload', stopRenderedVideoTracks);

        initScanner();
    </script>
</x-app-layout>
