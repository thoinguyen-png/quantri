<x-app-layout>
    <div class="camera-flow-page face-flow-page">
        <div class="camera-flow-card face-flow-card">
            <x-zalo-camera-warning />

            <div class="face-flow-header">
                <span>Bước 2/2</span>
                <h1>Xác minh gương mặt</h1>
                <p>Xác minh đúng tài khoản trước khi hệ thống lấy GPS và ghi nhận chấm công.</p>
            </div>

            <div>
                <h1 class="text-2xl font-bold">Xác minh khuôn mặt</h1>
                <p class="text-sm text-gray-500">
                    Nhìn thẳng → quay trái → quay phải → nhìn thẳng để tự chấm công
                </p>
            </div>

            @if (session('success'))
                <div class="attendance-alert attendance-alert--success">
                    {{ session('success') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="attendance-alert attendance-alert--error" role="alert">
                    <div>{{ $errors->first() }}</div>
                    <div class="attendance-alert__actions">
                        <a href="{{ route('attendance.checkin') }}" class="attendance-alert__button">
                            Thử lại
                        </a>
                    </div>
                </div>
            @endif

            <div class="face-camera-frame">
                <video id="video" autoplay muted playsinline class="face-camera-video"></video>
                <div class="face-camera-guide"></div>
                <div class="face-camera-corners" aria-hidden="true"></div>
            </div>

            <!-- <div class="face-guidance-grid" aria-label="Lưu ý khi xác minh gương mặt">
                <div>Đưa mặt vào khung</div>
                <div>Giữ mặt thẳng</div>
                <div>Đủ sáng</div>
                <div>Chỉ 1 người trong khung</div>
            </div> -->

            <div id="face-status" class="p-3 rounded-2xl bg-yellow-100 text-yellow-700 text-sm">
                Đang tải AI...
            </div>

            <div id="permission-panel" class="rounded-2xl border border-blue-100 bg-blue-50 p-4 text-sm text-blue-900">
                <div class="font-bold">Chuẩn bị chấm công</div>
                <p class="mt-1">Ứng dụng cần quyền Vị trí để xác nhận nơi chấm công và quyền Camera để xác minh gương mặt.</p>
                <button
                    type="button"
                    id="start-permission-button"
                    onclick="startPermissionFlow()"
                    class="mt-3 w-full rounded-2xl bg-blue-600 px-4 py-3 font-bold text-white"
                >
                    Cho phép mở Camera
                </button>
            </div>

            <button
                type="button"
                id="face-camera-permission-button"
                onclick="requestCameraPermission()"
                class="mobile-action-button hidden bg-blue-600 text-white"
            >
                Thử lại mở Camera
            </button>

            <button
                type="button"
                id="face-camera-manual-toggle"
                onclick="toggleCameraManualHelp()"
                class="hidden text-sm font-bold text-blue-700 underline underline-offset-4"
            >
                Xem hướng dẫn cấp quyền
            </button>

            <div id="face-camera-manual-help" class="hidden rounded-2xl border border-slate-200 bg-white p-3 text-xs leading-5 text-slate-700">
                Bấm biểu tượng ổ khóa trên thanh địa chỉ → Camera → Cho phép → tải lại trang.
            </div>

            <button
                type="button"
                id="gps-permission-button"
                onclick="requestGpsPermission()"
                class="mobile-action-button hidden bg-blue-600 text-white"
            >
                Cấp quyền vị trí
            </button>

            <!-- <button type="button" class="face-primary-action" disabled>
                Xác minh tự động
            </button> -->

            <button
                type="button"
                onclick="switchCamera()"
                id="face-camera-switch-button"
                class="mobile-action-button face-secondary-action hidden"
            >
                Đổi camera
            </button>

            <form method="POST" action="{{ route('attendance.store', absolute: false) }}" id="attendance-form">
                @csrf

                <input type="hidden" name="latitude" id="latitude">
                <input type="hidden" name="longitude" id="longitude">
                <input type="hidden" name="face_verified" id="face_verified" value="0">

                <input
                    type="hidden"
                    id="saved_descriptor"
                    value='@json(auth()->user()->face_descriptor)'
                >
            </form>

        </div>
    </div>

    <div
        id="face-required-modal"
        class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4"
        role="dialog"
        aria-modal="true"
        aria-labelledby="face-required-title"
    >
        <div class="w-full max-w-sm rounded-3xl bg-white p-5 shadow-xl">
            <h2 id="face-required-title" class="text-lg font-bold text-gray-900">
                Chưa đăng ký khuôn mặt
            </h2>
            <p class="mt-3 text-sm leading-6 text-gray-600">
                Bạn chưa đăng ký khuôn mặt. Vui lòng đăng ký trước khi chấm công.
            </p>
            <div class="mt-5 grid grid-cols-2 gap-3">
                <button
                    type="button"
                    id="face-required-later"
                    class="rounded-2xl border border-gray-300 px-4 py-3 text-sm font-semibold text-gray-700"
                >
                    Để sau
                </button>
                <a
                    href="{{ route('face.register') }}"
                    class="rounded-2xl bg-gray-900 px-4 py-3 text-center text-sm font-semibold text-white"
                >
                    Đăng ký ngay
                </a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/@vladmandic/face-api/dist/face-api.js"></script>

    <script>
        const video = document.getElementById('video');
        const statusBox = document.getElementById('face-status');
        const attendanceForm = document.getElementById('attendance-form');
        const faceRequiredModal = document.getElementById('face-required-modal');
        const faceRequiredLater = document.getElementById('face-required-later');
        const switchCameraButton = document.getElementById('face-camera-switch-button');
        const cameraPermissionButton = document.getElementById('face-camera-permission-button');
        const cameraManualToggle = document.getElementById('face-camera-manual-toggle');
        const cameraManualHelp = document.getElementById('face-camera-manual-help');
        const gpsPermissionButton = document.getElementById('gps-permission-button');
        const permissionPanel = document.getElementById('permission-panel');
        const startPermissionButton = document.getElementById('start-permission-button');
        const FACE_MATCH_THRESHOLD = 0.5;
        const FACE_CAMERA_DEBUG = @json(config('app.debug'));
        const FACE_TURN_RATIO = 0.24;

        const FACE_VERIFY_CAMERA_MODE_KEY = 'chamcongv2_face_verify_camera_mode';

        let step = 'center';
        let submitted = false;
        let faceCameraMode = localStorage.getItem(FACE_VERIFY_CAMERA_MODE_KEY) || 'user';
        let faceStream = null;
        let faceCheckRunId = 0;
        let isSwitchingCamera = false;
        let cachedGpsPosition = null;
        let isRequestingGps = false;
        let isRequestingPermission = false;
        let isStartingFaceCamera = false;
        let cameraPermissionFailures = 0;

        function faceCameraDebug(method, ...args) {
            if (FACE_CAMERA_DEBUG && console[method]) {
                console[method](...args);
            }
        }

        function faceModeLabel(mode) {
            return mode === 'user' ? 'camera trước' : 'camera sau';
        }

        function setStatus(message, type = 'yellow') {
            const classes = {
                yellow: 'p-3 rounded-2xl bg-yellow-100 text-yellow-700 text-sm',
                green: 'p-3 rounded-2xl bg-green-100 text-green-700 text-sm',
                red: 'p-3 rounded-2xl bg-red-100 text-red-700 text-sm',
            };

            statusBox.className = classes[type] || classes.yellow;
            statusBox.innerText = message;
        }

        async function browserPermissionState(name) {
            if (!navigator.permissions?.query) {
                return 'unknown';
            }

            try {
                const permission = await navigator.permissions.query({ name });
                return permission.state;
            } catch (error) {
                faceCameraDebug('warn', `[Permission] ${name} query failed`, error);
                return 'unknown';
            }
        }

        function cameraErrorMessage(error) {
            const errorName = error?.name || '';

            if (['NotAllowedError', 'PermissionDeniedError'].includes(errorName)) {
                return 'Bạn chưa cấp quyền Camera.';
            }

            if (['NotFoundError', 'DevicesNotFoundError'].includes(errorName)) {
                return 'Không tìm thấy Camera trên thiết bị.';
            }

            if (['NotReadableError', 'TrackStartError'].includes(errorName)) {
                return 'Không mở được Camera. Vui lòng thử lại. Nếu vẫn lỗi, hãy đóng ứng dụng có thể đang dùng Camera.';
            }

            if (errorName === 'OverconstrainedError') {
                return 'Không mở được Camera phù hợp. Vui lòng thử lại.';
            }

            if (errorName === 'SecurityError') {
                return 'Camera chỉ hoạt động trên HTTPS hoặc trình duyệt được hỗ trợ.';
            }

            return 'Không mở được Camera, vui lòng thử lại.';
        }

        function showCameraPermissionHelp(error = null) {
            setStatus(error ? cameraErrorMessage(error) : 'Bạn chưa cấp quyền Camera.', 'red');
            permissionPanel.classList.remove('hidden');
            startPermissionButton.classList.add('hidden');
            cameraPermissionButton.classList.remove('hidden');
            cameraPermissionButton.innerText = 'Thử lại mở Camera';
            cameraManualToggle.classList.toggle('hidden', cameraPermissionFailures < 2);
            cameraManualHelp.classList.add('hidden');
            switchCameraButton.classList.add('hidden');
        }

        function hideCameraPermissionButton() {
            cameraPermissionButton.classList.add('hidden');
            cameraManualToggle.classList.add('hidden');
            cameraManualHelp.classList.add('hidden');
        }

        function toggleCameraManualHelp() {
            cameraManualHelp.classList.toggle('hidden');
        }

        function showGpsPermissionHelp() {
            setStatus('Bạn chưa cấp quyền vị trí. Vui lòng bấm Cấp quyền vị trí để thử lại.\nNếu PWA không hiện popup nữa, vui lòng mở cài đặt quyền của ứng dụng/trình duyệt và bật Vị trí.', 'red');
            gpsPermissionButton.classList.remove('hidden');
        }

        function hideGpsPermissionButton() {
            gpsPermissionButton.classList.add('hidden');
        }

        function showPermissionPanel(message = null, showStartButton = false) {
            permissionPanel.classList.remove('hidden');
            startPermissionButton.classList.toggle('hidden', !showStartButton);

            if (message) {
                setStatus(message, 'yellow');
            }
        }

        function hidePermissionPanel() {
            permissionPanel.classList.add('hidden');
        }

        function setPermissionButtonsDisabled(disabled) {
            isRequestingPermission = disabled;
            startPermissionButton.disabled = disabled;
            cameraPermissionButton.disabled = disabled;
            gpsPermissionButton.disabled = disabled;

            [startPermissionButton, cameraPermissionButton, gpsPermissionButton].forEach((button) => {
                button.classList.toggle('opacity-60', disabled);
                button.classList.toggle('cursor-not-allowed', disabled);
            });
        }

        function resetFaceFlow() {
            step = 'center';
            submitted = false;
            document.getElementById('face_verified').value = '0';
        }

        function showFaceRequiredPopup() {
            submitted = false;
            resetFaceFlow();
            stopFaceCameraTracks();
            statusBox.className = 'p-3 rounded-2xl bg-red-100 text-red-700 text-sm';
            statusBox.innerText = 'Bạn chưa đăng ký khuôn mặt. Vui lòng đăng ký trước khi chấm công.';
            faceRequiredModal.classList.remove('hidden');
            faceRequiredModal.classList.add('flex');
        }

        function hideFaceRequiredPopup() {
            faceRequiredModal.classList.add('hidden');
            faceRequiredModal.classList.remove('flex');
        }

        function stopFaceCameraTracks() {
            if (faceStream && typeof faceStream.getTracks === 'function') {
                faceStream.getTracks().forEach(track => track.stop());
            }

            const currentStream = video.srcObject;
            if (currentStream && typeof currentStream.getTracks === 'function') {
                currentStream.getTracks().forEach(track => track.stop());
            }

            faceStream = null;
            video.srcObject = null;
        }

        function sleep(ms) {
            return new Promise(resolve => setTimeout(resolve, ms));
        }

        async function waitForVideoReady() {
            if (video.readyState >= 2 && video.videoWidth > 0 && video.videoHeight > 0) {
                return;
            }

            await new Promise((resolve, reject) => {
                const timeout = setTimeout(() => reject(new Error('VIDEO_NOT_READY')), 8000);

                video.onloadedmetadata = () => {
                    clearTimeout(timeout);
                    resolve();
                };
            });
        }

        async function openFaceStream(constraints) {
            faceCameraDebug('log', '[Face camera] getUserMedia constraints', constraints);
            faceStream = await navigator.mediaDevices.getUserMedia(constraints);
            video.srcObject = faceStream;
            video.setAttribute('playsinline', true);
            video.muted = true;
            await video.play();
            await waitForVideoReady();

            const [track] = faceStream.getVideoTracks();
            faceCameraDebug('log', '[Face camera] active settings', track?.getSettings?.());
        }

        async function startFaceCamera(mode = faceCameraMode) {
            if (isStartingFaceCamera) {
                return;
            }

            isStartingFaceCamera = true;
            faceCameraMode = mode;
            localStorage.setItem(FACE_VERIFY_CAMERA_MODE_KEY, faceCameraMode);

            try {
                stopFaceCameraTracks();
                await sleep(220);

                faceCameraDebug('log', '[Face camera] mode', faceCameraMode);
                faceCameraDebug('log', '[Face camera] facingMode', { ideal: faceCameraMode });
                setStatus('Đang mở Camera...', 'yellow');

                try {
                    await openFaceStream({
                        video: {
                            facingMode: {
                                ideal: faceCameraMode,
                            },
                            width: { ideal: 640 },
                            height: { ideal: 480 },
                        },
                        audio: false,
                    });

                    return;
                } catch (firstError) {
                    faceCameraDebug('warn', '[Face camera] primary constraints failed', firstError);

                    if (['NotAllowedError', 'PermissionDeniedError', 'SecurityError'].includes(firstError?.name || '')) {
                        throw firstError;
                    }

                    if (!['OverconstrainedError', 'NotFoundError', 'DevicesNotFoundError', 'NotReadableError', 'TrackStartError'].includes(firstError?.name || '')) {
                        throw firstError;
                    }

                    setStatus('Không mở được Camera. Hệ thống đang thử lại với cấu hình khác...', 'yellow');
                }

                await openFaceStream({
                    video: true,
                    audio: false,
                });
            } finally {
                isStartingFaceCamera = false;
            }
        }

        async function init() {
            if (!getSavedDescriptor()) {
                showFaceRequiredPopup();
                return;
            }

            try {
                await faceapi.nets.tinyFaceDetector.loadFromUri('https://cdn.jsdelivr.net/npm/@vladmandic/face-api/model/');
                await faceapi.nets.faceLandmark68Net.loadFromUri('https://cdn.jsdelivr.net/npm/@vladmandic/face-api/model/');
                await faceapi.nets.faceRecognitionNet.loadFromUri('https://cdn.jsdelivr.net/npm/@vladmandic/face-api/model/');

                const cameraPermissionState = await browserPermissionState('camera');

                if (cameraPermissionState === 'denied') {
                    cameraPermissionFailures += 2;
                    showCameraPermissionHelp();
                    return;
                }

                hidePermissionPanel();
                hideGpsPermissionButton();
                hideCameraPermissionButton();
                setStatus('Đang mở Camera...', 'yellow');
                await startFaceCamera(faceCameraMode || 'user');
                switchCameraButton.classList.remove('hidden');

                setStatus('Camera đã sẵn sàng', 'green');
                setTimeout(() => {
                    setStatus('Nhìn thẳng vào camera...', 'yellow');
                }, 700);

                faceCheckRunId += 1;
                setTimeout(() => checkLivenessAndFace(faceCheckRunId), 1000);
            } catch (error) {
                console.error(error);

                if (['NotAllowedError', 'PermissionDeniedError'].includes(error?.name || '')) {
                    cameraPermissionFailures += 1;
                }

                showCameraPermissionHelp(error);
            }
        }

        async function startPermissionFlow() {
            if (isRequestingPermission) return;

            if (!getSavedDescriptor()) {
                showFaceRequiredPopup();
                return;
            }

            setPermissionButtonsDisabled(true);
            hideGpsPermissionButton();
            hideCameraPermissionButton();

            try {
                showPermissionPanel('Đang mở Camera...');
                await startFaceCamera(faceCameraMode || 'user');
                switchCameraButton.classList.remove('hidden');

                hidePermissionPanel();
                setStatus('Camera đã sẵn sàng', 'green');

                faceCheckRunId += 1;
                setTimeout(() => checkLivenessAndFace(faceCheckRunId), 700);
            } catch (error) {
                console.error(error);

                const cameraPermissionState = await browserPermissionState('camera');

                if (['NotAllowedError', 'PermissionDeniedError'].includes(error?.name || '') || cameraPermissionState === 'denied') {
                    cameraPermissionFailures += 1;
                    showCameraPermissionHelp(error);
                    return;
                }

                showCameraPermissionHelp(error);
                setStatus(cameraErrorMessage(error), 'red');
            } finally {
                setPermissionButtonsDisabled(false);
            }
        }

        async function requestCameraPermission() {
            if (isRequestingPermission) return;

            if (!getSavedDescriptor()) {
                showFaceRequiredPopup();
                return;
            }

            try {
                setPermissionButtonsDisabled(true);
                hideCameraPermissionButton();
                setStatus('Đang xin quyền camera...', 'yellow');
                await startFaceCamera(faceCameraMode || 'user');
                switchCameraButton.classList.remove('hidden');
                hidePermissionPanel();
                setStatus('Camera đã sẵn sàng', 'green');

                faceCheckRunId += 1;
                setTimeout(() => checkLivenessAndFace(faceCheckRunId), 700);
            } catch (error) {
                console.error(error);
                if (['NotAllowedError', 'PermissionDeniedError'].includes(error?.name || '')) {
                    cameraPermissionFailures += 1;
                }
                showCameraPermissionHelp(error);
                setStatus(cameraErrorMessage(error), 'red');
            } finally {
                setPermissionButtonsDisabled(false);
            }
        }

        async function switchCamera() {
            if (submitted || isSwitchingCamera || isRequestingPermission) return;

            if (!getSavedDescriptor()) {
                showFaceRequiredPopup();
                return;
            }

            isSwitchingCamera = true;
            switchCameraButton.disabled = true;
            switchCameraButton.classList.add('opacity-60', 'cursor-not-allowed');

            try {
                faceCheckRunId += 1;
                resetFaceFlow();

                const nextMode = faceCameraMode === 'user' ? 'environment' : 'user';
                await startFaceCamera(nextMode);

                statusBox.className = 'p-3 rounded-2xl bg-yellow-100 text-yellow-700 text-sm';
                statusBox.innerText = `Đã đổi sang ${faceModeLabel(faceCameraMode)}. Nhìn thẳng vào camera...`;

                faceCheckRunId += 1;
                setTimeout(() => checkLivenessAndFace(faceCheckRunId), 700);
            } catch (error) {
                console.error(error);
                if (['NotAllowedError', 'PermissionDeniedError'].includes(error?.name || '')) {
                    cameraPermissionFailures += 1;
                    showCameraPermissionHelp(error);
                }
                statusBox.className = 'p-3 rounded-2xl bg-red-100 text-red-700 text-sm';
                statusBox.innerText = cameraErrorMessage(error);
            } finally {
                setTimeout(() => {
                    isSwitchingCamera = false;
                    switchCameraButton.disabled = false;
                    switchCameraButton.classList.remove('opacity-60', 'cursor-not-allowed');
                }, 300);
            }
        }

        function getNoseDirection(landmarks) {
            const nose = landmarks.getNose();
            const leftEye = landmarks.getLeftEye();
            const rightEye = landmarks.getRightEye();

            const noseX = nose[3].x;
            const leftEyeX = leftEye[0].x;
            const rightEyeX = rightEye[3].x;

            const eyeDistance = Math.max(Math.abs(rightEyeX - leftEyeX), 1);
            const faceCenterX = (leftEyeX + rightEyeX) / 2;
            const ratio = (noseX - faceCenterX) / eyeDistance;

            let direction = 'center';

            if (ratio > FACE_TURN_RATIO) {
                direction = 'right';
            }

            if (ratio < -FACE_TURN_RATIO) {
                direction = 'left';
            }

            // Preview camera trước đang mirror để người dùng thấy tự nhiên.
            // Face-api đọc ảnh gốc, nên cần đảo trái/phải cho đúng với hướng dẫn trên UI.
            if (faceCameraMode === 'user') {
                if (direction === 'left') {
                    return 'right';
                }

                if (direction === 'right') {
                    return 'left';
                }
            }

            return direction;
        }

        function getSavedDescriptor() {
            const savedRaw = document.getElementById('saved_descriptor').value;

            if (!savedRaw || savedRaw === 'null') {
                return null;
            }

            try {
                const parsed = JSON.parse(savedRaw);
                const descriptor = typeof parsed === 'string' ? JSON.parse(parsed) : parsed;

                if (!Array.isArray(descriptor) || descriptor.length < 128) {
                    return null;
                }

                return new Float32Array(descriptor);
            } catch (error) {
                faceCameraDebug('warn', '[Face verify] invalid saved descriptor', error);
                return null;
            }
        }

        async function markFaceVerified(distance) {
            const token = document.querySelector('#attendance-form input[name="_token"]').value;

            const response = await fetch('{{ route('face.verify-pass', absolute: false) }}', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': token,
                },
                body: JSON.stringify({
                    distance: distance,
                }),
            });

            if (!response.ok) {
                throw new Error('Không thể ghi nhận phiên xác minh khuôn mặt.');
            }
        }

        async function checkLivenessAndFace(runId) {
            if (submitted || runId !== faceCheckRunId) return;

            const savedDescriptor = getSavedDescriptor();

            if (!savedDescriptor) {
                showFaceRequiredPopup();
                return;
            }

            const detections = await faceapi
                .detectAllFaces(
                    video,
                    new faceapi.TinyFaceDetectorOptions({
                        inputSize: 224,
                        scoreThreshold: 0.4
                    })
                )
                .withFaceLandmarks()
                .withFaceDescriptors();

            if (runId !== faceCheckRunId) return;

            if (!detections.length) {
                statusBox.className = 'p-3 rounded-2xl bg-yellow-100 text-yellow-700 text-sm';
                statusBox.innerText = 'Không tìm thấy khuôn mặt';
                setTimeout(() => checkLivenessAndFace(runId), 700);
                return;
            }

            if (detections.length > 1) {
                step = 'center';
                document.getElementById('face_verified').value = '0';
                statusBox.className = 'p-3 rounded-2xl bg-red-100 text-red-700 text-sm';
                statusBox.innerText = 'Chỉ được có 1 khuôn mặt trong khung hình';
                setTimeout(() => checkLivenessAndFace(runId), 700);
                return;
            }

            const detection = detections[0];
            const distance = faceapi.euclideanDistance(
                savedDescriptor,
                detection.descriptor
            );

            if (distance > FACE_MATCH_THRESHOLD) {
                step = 'center';
                document.getElementById('face_verified').value = '0';
                statusBox.className = 'p-3 rounded-2xl bg-red-100 text-red-700 text-sm';
                statusBox.innerText = 'Khuôn mặt không khớp với tài khoản';
                setTimeout(() => checkLivenessAndFace(runId), 1000);
                return;
            }

            const direction = getNoseDirection(detection.landmarks);

            if (step === 'center') {
                if (direction === 'center') {
                    step = 'left';
                    statusBox.className = 'p-3 rounded-2xl bg-yellow-100 text-yellow-700 text-sm';
                    statusBox.innerText = 'Đúng người. Bây giờ quay mặt sang TRÁI.';
                } else {
                    statusBox.innerText = 'Hãy nhìn thẳng vào camera.';
                }
            } else if (step === 'left') {
                if (direction === 'left') {
                    step = 'right';
                    statusBox.innerText = 'Tốt. Bây giờ quay mặt sang PHẢI.';
                } else {
                    statusBox.innerText = 'Vui lòng quay mặt sang TRÁI.';
                }
            } else if (step === 'right') {
                if (direction === 'right') {
                    step = 'final';
                    statusBox.innerText = 'Tốt. Nhìn thẳng lại để chấm công.';
                } else {
                    statusBox.innerText = 'Vui lòng quay mặt sang PHẢI.';
                }
            } else if (step === 'final') {
                if (direction === 'center') {
                    submitted = true;

                    statusBox.className = 'p-3 rounded-2xl bg-green-100 text-green-700 text-sm';
                    statusBox.innerText = 'Xác minh thành công. Đang lấy GPS...';

                    try {
                        await markFaceVerified(distance);
                        document.getElementById('face_verified').value = '1';
                    } catch (error) {
                        console.error(error);
                        submitted = false;
                        statusBox.className = 'p-3 rounded-2xl bg-red-100 text-red-700 text-sm';
                        statusBox.innerText = 'Không thể ghi nhận phiên xác minh khuôn mặt.';
                        setTimeout(() => checkLivenessAndFace(runId), 1000);
                        return;
                    }

                    stopFaceCameraTracks();
                    getGpsAndSubmit();

                    return;
                } else {
                    statusBox.innerText = 'Vui lòng nhìn thẳng lại camera.';
                }
            }

            setTimeout(() => checkLivenessAndFace(runId), 700);
        }

        function applyGpsAndSubmit(position) {
            cachedGpsPosition = position;
            document.getElementById('latitude').value = position.coords.latitude;
            document.getElementById('longitude').value = position.coords.longitude;
            hideGpsPermissionButton();
            setStatus('Đã lấy vị trí thành công.', 'green');
            document.getElementById('attendance-form').submit();
        }

        function getCurrentPositionAsync(options) {
            return new Promise((resolve, reject) => {
                navigator.geolocation.getCurrentPosition(resolve, reject, options);
            });
        }

        async function acquireGpsPosition(forceRequest = false) {
            if (!navigator.geolocation) {
                throw new Error('Browser không hỗ trợ GPS');
            }

            if (isRequestingGps) {
                return cachedGpsPosition;
            }

            if (cachedGpsPosition && !forceRequest) {
                return cachedGpsPosition;
            }

            isRequestingGps = true;
            setStatus('Đang lấy vị trí...', 'yellow');

            try {
                const permissionState = await browserPermissionState('geolocation');

                if (permissionState === 'denied' && !forceRequest) {
                    showGpsPermissionHelp();
                    throw new Error('Bạn chưa cấp quyền vị trí.');
                }

                try {
                    const fastPosition = await getCurrentPositionAsync({
                        enableHighAccuracy: false,
                        timeout: 5000,
                        maximumAge: 300000,
                    });

                    cachedGpsPosition = fastPosition;
                    hideGpsPermissionButton();
                    setStatus('Đã lấy vị trí thành công.', 'green');
                    return fastPosition;
                } catch (fastError) {
                    faceCameraDebug('warn', '[GPS] fast position failed', fastError);
                }

                const accuratePosition = await getCurrentPositionAsync({
                    enableHighAccuracy: true,
                    timeout: 10000,
                    maximumAge: 0,
                });

                cachedGpsPosition = accuratePosition;
                hideGpsPermissionButton();
                setStatus('Đã lấy vị trí thành công.', 'green');
                return accuratePosition;
            } catch (error) {
                console.error(error);
                setStatus('Không lấy được vị trí.', 'red');
                gpsPermissionButton.classList.remove('hidden');
                throw error;
            } finally {
                isRequestingGps = false;
            }
        }

        async function getGpsAndSubmit(forceRequest = false) {
            if (!getSavedDescriptor()) {
                showFaceRequiredPopup();
                return;
            }

            try {
                const position = await acquireGpsPosition(forceRequest);

                if (!position) {
                    submitted = false;
                    return;
                }

                applyGpsAndSubmit(position);
            } catch (error) {
                submitted = false;
            }
        }

        async function requestGpsPermission() {
            if (isRequestingPermission) return;

            hideGpsPermissionButton();
            setPermissionButtonsDisabled(true);

            try {
                const position = await acquireGpsPosition(true);
                setStatus('Đã cấp quyền vị trí.', 'green');

                if (document.getElementById('face_verified').value === '1' && position) {
                    applyGpsAndSubmit(position);
                }
            } catch (error) {
                showGpsPermissionHelp();
                showPermissionPanel();
            } finally {
                setPermissionButtonsDisabled(false);
            }
        }

        faceRequiredLater.addEventListener('click', hideFaceRequiredPopup);

        attendanceForm.addEventListener('submit', function (event) {
            if (!getSavedDescriptor()) {
                event.preventDefault();
                showFaceRequiredPopup();
                return;
            }

            if (document.getElementById('face_verified').value !== '1') {
                event.preventDefault();
                statusBox.className = 'p-3 rounded-2xl bg-red-100 text-red-700 text-sm';
                statusBox.innerText = 'Vui lòng xác minh khuôn mặt trước khi chấm công.';
            }
        });

        init();
    </script>
</x-app-layout>
