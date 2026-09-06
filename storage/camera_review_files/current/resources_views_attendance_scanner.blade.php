<x-app-layout>
    <div class="camera-flow-page bg-gray-100">
        <div class="camera-flow-card bg-white rounded-3xl shadow p-4 sm:p-5 space-y-4">
            <x-zalo-camera-warning />

            <div>
                <div class="text-sm text-blue-600 font-bold">Bước 1/2</div>
                <h1 class="text-2xl font-bold">Quét mã QR</h1>
                <p class="text-sm text-gray-500">
                    Hệ thống sẽ tự mở camera sau để quét mã chấm công.
                </p>
            </div>

            <div id="reader" class="qr-reader-frame rounded-3xl overflow-hidden bg-black"></div>

            <div id="scan-result" class="hidden p-3 rounded-2xl text-sm"></div>

            <div id="qr-permission-panel" class="hidden rounded-2xl border border-blue-100 bg-blue-50 p-4 text-sm text-blue-900">
                <div class="font-bold" id="qr-permission-title">Ứng dụng cần quyền Camera để quét mã QR</div>
                <p class="mt-1" id="qr-permission-message">Vui lòng cấp quyền Camera để tiếp tục chấm công.</p>
                <button
                    type="button"
                    id="qr-start-permission-button"
                    onclick="requestCameraPermission()"
                    class="mt-3 w-full rounded-2xl bg-blue-600 px-4 py-3 font-bold text-white"
                >
                    Thử lại cấp quyền Camera
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
        let qrCameraMode = localStorage.getItem('chamcongv2_qr_camera_mode') || 'environment';
        let scanned = false;
        let isSwitchingCamera = false;
        let isStartingCamera = false;
        let isRequestingCamera = false;
        let isCameraReady = false;
        let cameraPermissionFailures = 0;

        const readerId = 'reader';
        const resultBox = document.getElementById('scan-result');
        const switchCameraButton = document.getElementById('qr-camera-switch-button');
        const permissionPanel = document.getElementById('qr-permission-panel');
        const startPermissionButton = document.getElementById('qr-start-permission-button');
        const permissionTitle = document.getElementById('qr-permission-title');
        const permissionMessage = document.getElementById('qr-permission-message');
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
            setTimeout(() => {
                resultBox.classList.add('hidden');
            }, delay);
        }

        function isPermissionError(error) {
            return ['NotAllowedError', 'PermissionDeniedError'].includes(error?.name || '');
        }

        function isFallbackCameraError(error) {
            return [
                'OverconstrainedError',
                'NotReadableError',
                'TrackStartError',
                'NotFoundError',
                'DevicesNotFoundError',
            ].includes(error?.name || '');
        }

        function cameraErrorMessage(error) {
            const errorName = error?.name || '';

            if (isPermissionError(error)) {
                return 'Bạn chưa cấp quyền Camera.';
            }

            if (['NotFoundError', 'DevicesNotFoundError'].includes(errorName)) {
                return 'Không tìm thấy Camera trên thiết bị.';
            }

            if (['NotReadableError', 'TrackStartError'].includes(errorName)) {
                return 'Không mở được Camera. Vui lòng thử lại. Nếu vẫn lỗi, hãy đóng ứng dụng có thể đang dùng Camera.';
            }

            if (errorName === 'OverconstrainedError') {
                return 'Không mở được Camera phù hợp. Hệ thống sẽ thử cấu hình khác.';
            }

            if (errorName === 'SecurityError') {
                return 'Camera chỉ hoạt động trên HTTPS hoặc trình duyệt được hỗ trợ.';
            }

            if (error?.message === 'QR_LIBRARY_MISSING') {
                return 'Không tải được bộ quét QR. Vui lòng kiểm tra mạng rồi tải lại trang.';
            }

            return 'Không mở được Camera, vui lòng thử lại.';
        }

        async function browserPermissionState(name) {
            if (!navigator.permissions?.query) {
                return 'unknown';
            }

            try {
                const permission = await navigator.permissions.query({ name });
                return permission.state;
            } catch (error) {
                qrCameraDebug('warn', `[Permission] ${name} query failed`, error);
                return 'unknown';
            }
        }

        function showCameraPermissionHelp(error = null) {
            showMessage(error ? cameraErrorMessage(error) : 'Bạn chưa cấp quyền Camera.', 'error');
            permissionPanel.classList.remove('hidden');
            permissionPanel.className = 'rounded-2xl border border-red-100 bg-red-50 p-4 text-sm text-red-800';
            permissionTitle.innerText = 'Bạn chưa cấp quyền Camera';
            permissionMessage.innerText = 'Vui lòng cấp quyền Camera để chấm công.';
            startPermissionButton.classList.remove('hidden');
            startPermissionButton.innerText = 'Thử lại cấp quyền Camera';
            manualPermissionToggle.classList.toggle('hidden', cameraPermissionFailures < 2);
            manualPermissionHelp.classList.add('hidden');
            switchCameraButton.classList.add('hidden');
            isCameraReady = false;
        }

        function hidePermissionUi() {
            permissionPanel.classList.add('hidden');
            manualPermissionToggle.classList.add('hidden');
            manualPermissionHelp.classList.add('hidden');
        }

        function toggleManualPermissionHelp() {
            manualPermissionHelp.classList.toggle('hidden');
        }

        function setPermissionButtonsDisabled(disabled) {
            isRequestingCamera = disabled;
            startPermissionButton.disabled = disabled;
            startPermissionButton.classList.toggle('opacity-60', disabled);
            startPermissionButton.classList.toggle('cursor-not-allowed', disabled);
        }

        function qrModeLabel(mode) {
            return mode === 'environment' ? 'camera sau' : 'camera trước';
        }

        function wait(ms) {
            return new Promise(resolve => setTimeout(resolve, ms));
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

        function stopRenderedVideoTracks() {
            const videos = document.querySelectorAll(`#${readerId} video`);
            videos.forEach((video) => {
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

        function markRenderedVideoInline() {
            document.querySelectorAll(`#${readerId} video`).forEach((video) => {
                video.setAttribute('playsinline', true);
                video.muted = true;
            });
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

        function cameraAttemptsFor(mode) {
            const preferredMode = mode === 'user' ? 'user' : 'environment';

            return [
                {
                    label: `${preferredMode} ideal`,
                    config: {
                        facingMode: {
                            ideal: preferredMode,
                        },
                    },
                },
                {
                    label: `${preferredMode} simple`,
                    config: {
                        facingMode: preferredMode,
                    },
                },
                {
                    label: 'any camera',
                    config: {},
                },
            ];
        }

        async function startScannerWithConfig(cameraConfig) {
            if (!html5QrCode) {
                html5QrCode = new Html5Qrcode(readerId);
            }

            qrCameraDebug('log', '[QR camera] start config', cameraConfig);

            await html5QrCode.start(
                cameraConfig,
                scannerConfig(),
                onScanSuccess,
                onScanFailure
            );

            markRenderedVideoInline();
            isCameraReady = true;
        }

        async function tryStartByDeviceId(mode) {
            if (!Html5Qrcode?.getCameras) {
                return false;
            }

            const cameras = await Html5Qrcode.getCameras();
            const preferredKeywords = mode === 'environment'
                ? ['back', 'rear', 'environment', 'sau']
                : ['front', 'user', 'face', 'trước'];
            const preferredCamera = cameras.find(camera => {
                const label = (camera.label || '').toLowerCase();
                return preferredKeywords.some(keyword => label.includes(keyword));
            }) || cameras[0];

            if (!preferredCamera) {
                throw new DOMException('No camera available', 'NotFoundError');
            }

            qrCameraDebug('log', '[QR camera] deviceId fallback', preferredCamera.id);
            await startScannerWithConfig(preferredCamera.id);
            return true;
        }

        async function startQrCamera(mode = qrCameraMode) {
            if (isStartingCamera) return;

            isStartingCamera = true;
            qrCameraMode = mode === 'user' ? 'user' : 'environment';
            localStorage.setItem('chamcongv2_qr_camera_mode', qrCameraMode);

            let lastError = null;

            try {
                hidePermissionUi();
                switchCameraButton.classList.add('hidden');
                showMessage('Đang mở camera...', 'loading');

                await stopScanner();
                await wait(120);

                for (const attempt of cameraAttemptsFor(qrCameraMode)) {
                    try {
                        await startScannerWithConfig(attempt.config);
                        switchCameraButton.classList.remove('hidden');
                        showMessage('Camera đã sẵn sàng', 'success');
                        hideMessageSoon();
                        return;
                    } catch (error) {
                        lastError = error;
                        qrCameraDebug('warn', '[QR camera] start failed', attempt.label, error);

                        await stopScanner();

                        if (isPermissionError(error) || error?.name === 'SecurityError') {
                            throw error;
                        }

                        if (!isFallbackCameraError(error) && attempt.label !== 'any camera') {
                            throw error;
                        }

                        if (attempt.label !== 'any camera') {
                            showMessage('Không mở được Camera. Hệ thống đang thử cấu hình khác...', 'loading');
                            await wait(180);
                        }
                    }
                }

                if (await tryStartByDeviceId(qrCameraMode)) {
                    switchCameraButton.classList.remove('hidden');
                    showMessage('Camera đã sẵn sàng', 'success');
                    hideMessageSoon();
                    return;
                }

                throw lastError || new Error('Cannot open camera');
            } finally {
                isStartingCamera = false;
            }
        }

        async function requestCameraPermission() {
            if (isRequestingCamera || isStartingCamera) return;

            try {
                setPermissionButtonsDisabled(true);
                await startQrCamera(qrCameraMode || 'environment');
            } catch (error) {
                console.error(error);

                if (isPermissionError(error)) {
                    cameraPermissionFailures += 1;
                    showCameraPermissionHelp(error);
                    return;
                }

                const permissionState = await browserPermissionState('camera');
                if (permissionState === 'denied') {
                    cameraPermissionFailures += 2;
                    showCameraPermissionHelp(new DOMException('Permission denied', 'NotAllowedError'));
                    return;
                }

                hidePermissionUi();
                showMessage(cameraErrorMessage(error), 'error');
            } finally {
                setPermissionButtonsDisabled(false);
            }
        }

        async function switchCamera() {
            if (!isCameraReady || isSwitchingCamera || isRequestingCamera || isStartingCamera) return;

            isSwitchingCamera = true;
            switchCameraButton.disabled = true;
            switchCameraButton.classList.add('opacity-60', 'cursor-not-allowed');

            try {
                const nextMode = qrCameraMode === 'environment' ? 'user' : 'environment';
                await startQrCamera(nextMode);
                showMessage(`Đã đổi sang ${qrModeLabel(qrCameraMode)}.`, 'success');
                hideMessageSoon(1000);
            } catch (error) {
                console.error(error);

                if (isPermissionError(error)) {
                    cameraPermissionFailures += 1;
                    showCameraPermissionHelp(error);
                } else {
                    showMessage(cameraErrorMessage(error), 'error');
                }
            } finally {
                setTimeout(() => {
                    isSwitchingCamera = false;
                    switchCameraButton.disabled = false;
                    switchCameraButton.classList.remove('opacity-60', 'cursor-not-allowed');
                }, 300);
            }
        }

        async function initScanner() {
            try {
                if (!window.Html5Qrcode) {
                    throw new Error('QR_LIBRARY_MISSING');
                }

                const permissionState = await browserPermissionState('camera');
                if (permissionState === 'denied') {
                    cameraPermissionFailures += 2;
                    showCameraPermissionHelp(new DOMException('Permission denied', 'NotAllowedError'));
                    return;
                }

                await requestCameraPermission();
            } catch (error) {
                console.error(error);
                showMessage(cameraErrorMessage(error), 'error');
            }
        }

        window.addEventListener('pagehide', stopScanner);
        window.addEventListener('beforeunload', stopRenderedVideoTracks);

        initScanner();
    </script>
</x-app-layout>
