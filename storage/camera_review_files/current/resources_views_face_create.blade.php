<x-app-layout>
    <div class="camera-flow-page face-flow-page bg-gray-100">
        <div class="camera-flow-card face-flow-card bg-white rounded-3xl shadow p-4 sm:p-5 space-y-4">
            <x-zalo-camera-warning />

            <div class="face-flow-header">
                <span>Đăng ký gương mặt</span>
                <h1>Cập nhật khuôn mặt</h1>
                <p>Hệ thống sẽ tự mở camera trước, nhận diện 1 khuôn mặt rõ ràng và lưu mẫu gương mặt cho tài khoản của bạn.</p>
            </div>

            <div class="face-camera-frame">
                <video id="video" autoplay muted playsinline class="face-camera-video"></video>
                <div class="face-camera-guide"></div>
                <div class="face-camera-corners" aria-hidden="true"></div>
            </div>

            <div id="register-status" class="p-3 rounded-2xl bg-yellow-100 text-yellow-700 text-sm">
                Đang tải AI...
            </div>

            <div id="permission-panel" class="hidden rounded-2xl border border-blue-100 bg-blue-50 p-4 text-sm text-blue-900">
                <div class="font-bold" id="permission-title">Ứng dụng cần quyền Camera</div>
                <p class="mt-1" id="permission-message">Ứng dụng sẽ tự xin quyền Camera để đăng ký gương mặt. Nếu trình duyệt chặn popup, hãy bấm nút bên dưới để thử lại.</p>
                <button
                    type="button"
                    id="start-permission-button"
                    onclick="requestCameraPermission()"
                    class="mt-3 w-full rounded-2xl bg-blue-600 px-4 py-3 font-bold text-white"
                >
                    Thử lại cấp quyền Camera
                </button>
                <button
                    type="button"
                    id="manual-permission-toggle"
                    onclick="toggleManualPermissionHelp()"
                    class="mt-3 hidden text-sm font-bold text-blue-700 underline underline-offset-4"
                >
                    Xem hướng dẫn cấp quyền
                </button>
                <div id="manual-permission-help" class="mt-3 hidden rounded-2xl bg-white/80 p-3 text-xs leading-5 text-slate-700">
                    Trình duyệt có thể đã chặn hỏi lại quyền Camera. Hãy bấm biểu tượng ổ khóa trên thanh địa chỉ → Camera → Cho phép, sau đó tải lại trang.
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <button
                    type="button"
                    id="register-camera-switch-button"
                    onclick="switchCamera()"
                    class="mobile-action-button hidden rounded-2xl bg-gray-900 px-4 py-3 font-bold text-white shadow"
                >
                    Đổi camera
                </button>

                <button
                    type="button"
                    id="retry-register-button"
                    onclick="restartRegistration()"
                    class="mobile-action-button hidden rounded-2xl bg-blue-600 px-4 py-3 font-bold text-white shadow"
                >
                    Chụp lại
                </button>
            </div>

            <a href="{{ route('dashboard', absolute: false) }}"
               class="mobile-action-button block text-center rounded-2xl bg-gray-200 py-3 font-bold">
                Quay lại
            </a>

            <form method="POST" action="{{ route('face.store', absolute: false) }}" id="face-register-form">
                @csrf
                <input type="hidden" name="face_descriptor" id="face_descriptor">
                <input type="hidden" name="descriptor" id="descriptor">
                <input type="hidden" name="face_image_data" id="face_image_data">
            </form>
        </div>
    </div>

    <div
        id="camera-required-modal"
        class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4"
        role="dialog"
        aria-modal="true"
        aria-labelledby="camera-required-title"
    >
        <div class="w-full max-w-sm rounded-3xl bg-white p-5 shadow-xl">
            <h2 id="camera-required-title" class="text-lg font-bold text-gray-900">
                Bạn chưa cấp quyền Camera
            </h2>
            <p id="camera-required-message" class="mt-3 text-sm leading-6 text-gray-600">
                Ứng dụng cần Camera để đăng ký gương mặt cho tài khoản của bạn.
            </p>
            <div id="camera-required-manual-help" class="mt-3 hidden rounded-2xl bg-red-50 p-3 text-xs leading-5 text-red-700">
                Trình duyệt có thể đã chặn hỏi lại quyền Camera. Hãy bấm biểu tượng ổ khóa trên thanh địa chỉ → Camera → Cho phép, sau đó tải lại trang.
            </div>
            <div class="mt-5 grid gap-3">
                <button
                    type="button"
                    id="camera-required-retry"
                    onclick="requestCameraPermission()"
                    class="rounded-2xl bg-blue-600 px-4 py-3 text-sm font-bold text-white"
                >
                    Thử lại cấp quyền Camera
                </button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/@vladmandic/face-api/dist/face-api.js"></script>

    <script>
        const video = document.getElementById('video');
        const statusBox = document.getElementById('register-status');
        const form = document.getElementById('face-register-form');
        const faceDescriptorInput = document.getElementById('face_descriptor');
        const descriptorInput = document.getElementById('descriptor');
        const faceImageDataInput = document.getElementById('face_image_data');
        const permissionPanel = document.getElementById('permission-panel');
        const permissionTitle = document.getElementById('permission-title');
        const permissionMessage = document.getElementById('permission-message');
        const startPermissionButton = document.getElementById('start-permission-button');
        const manualPermissionToggle = document.getElementById('manual-permission-toggle');
        const manualPermissionHelp = document.getElementById('manual-permission-help');
        const switchCameraButton = document.getElementById('register-camera-switch-button');
        const retryRegisterButton = document.getElementById('retry-register-button');
        const cameraRequiredModal = document.getElementById('camera-required-modal');
        const cameraRequiredMessage = document.getElementById('camera-required-message');
        const cameraRequiredManualHelp = document.getElementById('camera-required-manual-help');
        const cameraRequiredRetry = document.getElementById('camera-required-retry');

        const FACE_REGISTER_DEBUG = @json(config('app.debug'));
        const FACE_CAMERA_MODE_KEY = 'chamcongv2_register_face_camera_mode';
        const REQUIRED_SAMPLES = 3;
        const MIN_FACE_RATIO = 0.18;
        const MAX_FACE_RATIO = 0.78;

        let registerStream = null;
        let registerCameraMode = localStorage.getItem(FACE_CAMERA_MODE_KEY) || 'user';
        let isStartingCamera = false;
        let startingCameraPromise = null;
        let isRequestingCamera = false;
        let isSwitchingCamera = false;
        let cameraPermissionFailures = 0;
        let modelsLoaded = false;
        let detectionRunId = 0;
        let descriptorSamples = [];
        let submitted = false;

        function registerDebug(method, ...args) {
            if (FACE_REGISTER_DEBUG && console[method]) {
                console[method](...args);
            }
        }

        function sleep(ms) {
            return new Promise(resolve => setTimeout(resolve, ms));
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

        function cameraModeLabel(mode) {
            return mode === 'user' ? 'camera trước' : 'camera sau';
        }

        function isPermissionError(error) {
            return ['NotAllowedError', 'PermissionDeniedError'].includes(error?.name || '');
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
                return 'Không mở được Camera phù hợp. Vui lòng thử lại hoặc đổi camera.';
            }

            if (errorName === 'SecurityError') {
                return 'Camera chỉ hoạt động trên HTTPS hoặc trình duyệt được hỗ trợ.';
            }

            if (error?.message === 'MODEL_LOAD_FAILED') {
                return 'Không tải được AI nhận diện gương mặt. Vui lòng kiểm tra mạng rồi tải lại trang.';
            }

            return 'Không mở được Camera, vui lòng thử lại.';
        }

        function isSecureCameraContext() {
            return window.isSecureContext || ['localhost', '127.0.0.1'].includes(window.location.hostname);
        }

        async function browserPermissionState(name) {
            if (!navigator.permissions?.query) {
                return 'unknown';
            }

            try {
                const permission = await navigator.permissions.query({ name });
                return permission.state;
            } catch (error) {
                registerDebug('warn', `[Permission] ${name} query failed`, error);
                return 'unknown';
            }
        }

        function showPreparingPanel() {
            permissionPanel.classList.remove('hidden');
            permissionPanel.className = 'rounded-2xl border border-blue-100 bg-blue-50 p-4 text-sm text-blue-900';
            permissionTitle.innerText = 'Đang chuẩn bị Camera';
            permissionMessage.innerText = 'Ứng dụng sẽ tự mở Camera để đăng ký gương mặt.';
            startPermissionButton.classList.add('hidden');
            manualPermissionToggle.classList.add('hidden');
            manualPermissionHelp.classList.add('hidden');
        }

        function showCameraPermissionHelp(error = null) {
            const message = error ? cameraErrorMessage(error) : 'Bạn chưa cấp quyền Camera.';

            setStatus(message, 'red');
            permissionPanel.classList.remove('hidden');
            permissionPanel.className = 'rounded-2xl border border-red-100 bg-red-50 p-4 text-sm text-red-800';
            permissionTitle.innerText = 'Bạn chưa cấp quyền Camera';
            permissionMessage.innerText = 'Vui lòng cấp quyền Camera để đăng ký gương mặt.';
            startPermissionButton.classList.remove('hidden');
            startPermissionButton.innerText = 'Thử lại cấp quyền Camera';
            manualPermissionToggle.classList.toggle('hidden', cameraPermissionFailures < 2);
            manualPermissionHelp.classList.add('hidden');
            switchCameraButton.classList.add('hidden');
            retryRegisterButton.classList.add('hidden');

            cameraRequiredMessage.innerText = message;
            cameraRequiredManualHelp.classList.toggle('hidden', cameraPermissionFailures < 2);
            cameraRequiredModal.classList.remove('hidden');
            cameraRequiredModal.classList.add('flex');
        }

        function hidePermissionUi() {
            permissionPanel.classList.add('hidden');
            manualPermissionToggle.classList.add('hidden');
            manualPermissionHelp.classList.add('hidden');
            cameraRequiredModal.classList.add('hidden');
            cameraRequiredModal.classList.remove('flex');
        }

        function toggleManualPermissionHelp() {
            manualPermissionHelp.classList.toggle('hidden');
        }

        function setPermissionButtonsDisabled(disabled) {
            isRequestingCamera = disabled;
            startPermissionButton.disabled = disabled;
            cameraRequiredRetry.disabled = disabled;

            [startPermissionButton, cameraRequiredRetry].forEach(button => {
                button.classList.toggle('opacity-60', disabled);
                button.classList.toggle('cursor-not-allowed', disabled);
            });
        }

        function stopRegisterCameraTracks() {
            if (registerStream && typeof registerStream.getTracks === 'function') {
                registerStream.getTracks().forEach(track => track.stop());
            }

            const currentStream = video.srcObject;
            if (currentStream && typeof currentStream.getTracks === 'function') {
                currentStream.getTracks().forEach(track => track.stop());
            }

            registerStream = null;
            video.srcObject = null;
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

        async function openRegisterStream(constraints) {
            registerDebug('log', '[Face register] getUserMedia constraints', constraints);
            registerStream = await navigator.mediaDevices.getUserMedia(constraints);
            video.srcObject = registerStream;
            video.setAttribute('playsinline', true);
            video.muted = true;
            await video.play();
            await waitForVideoReady();

            const [track] = registerStream.getVideoTracks();
            registerDebug('log', '[Face register] active settings', track?.getSettings?.());
        }

        async function startRegisterCamera(mode = registerCameraMode) {
            if (startingCameraPromise) {
                return startingCameraPromise;
            }

            isStartingCamera = true;
            registerCameraMode = mode;
            localStorage.setItem(FACE_CAMERA_MODE_KEY, registerCameraMode);

            startingCameraPromise = (async () => {
                try {
                    stopRegisterCameraTracks();
                    await sleep(180);

                    setStatus('Đang mở Camera...', 'yellow');

                    try {
                        await openRegisterStream({
                            video: {
                                facingMode: { ideal: registerCameraMode },
                                width: { ideal: 640 },
                                height: { ideal: 480 },
                            },
                            audio: false,
                        });
                        return;
                    } catch (firstError) {
                        registerDebug('warn', '[Face register] primary camera failed', firstError);

                        if (isPermissionError(firstError) || firstError?.name === 'SecurityError') {
                            throw firstError;
                        }

                        setStatus('Không mở được Camera phù hợp. Hệ thống đang thử cấu hình khác...', 'yellow');
                        await sleep(220);
                    }

                    await openRegisterStream({
                        video: true,
                        audio: false,
                    });
                } finally {
                    isStartingCamera = false;
                    startingCameraPromise = null;
                }
            })();

            return startingCameraPromise;
        }

        async function loadFaceModels() {
            if (modelsLoaded) return;

            try {
                setStatus('Đang tải AI...', 'yellow');
                await faceapi.nets.tinyFaceDetector.loadFromUri('https://cdn.jsdelivr.net/npm/@vladmandic/face-api/model/');
                await faceapi.nets.faceLandmark68Net.loadFromUri('https://cdn.jsdelivr.net/npm/@vladmandic/face-api/model/');
                await faceapi.nets.faceRecognitionNet.loadFromUri('https://cdn.jsdelivr.net/npm/@vladmandic/face-api/model/');
                modelsLoaded = true;
            } catch (error) {
                registerDebug('error', '[Face register] model load failed', error);
                throw new Error('MODEL_LOAD_FAILED');
            }
        }

        function resetRegistrationState() {
            submitted = false;
            descriptorSamples = [];
            faceDescriptorInput.value = '';
            descriptorInput.value = '';
            faceImageDataInput.value = '';
        }

        async function requestCameraPermission(auto = false) {
            if (isRequestingCamera || isStartingCamera) return;

            try {
                setPermissionButtonsDisabled(true);
                hidePermissionUi();
                resetRegistrationState();

                await startRegisterCamera(registerCameraMode || 'user');
                switchCameraButton.classList.remove('hidden');
                retryRegisterButton.classList.add('hidden');
                setStatus(`Camera đã sẵn sàng. Đưa mặt vào khung và nhìn thẳng.`, 'green');

                detectionRunId += 1;
                setTimeout(() => detectAndRegisterFace(detectionRunId), auto ? 700 : 400);
            } catch (error) {
                console.error(error);

                if (isPermissionError(error)) {
                    cameraPermissionFailures += 1;
                }

                showCameraPermissionHelp(error);
            } finally {
                setPermissionButtonsDisabled(false);
            }
        }

        async function switchCamera() {
            if (submitted || isSwitchingCamera || isRequestingCamera) return;

            isSwitchingCamera = true;
            switchCameraButton.disabled = true;
            switchCameraButton.classList.add('opacity-60', 'cursor-not-allowed');

            try {
                detectionRunId += 1;
                resetRegistrationState();

                const nextMode = registerCameraMode === 'user' ? 'environment' : 'user';
                await startRegisterCamera(nextMode);

                setStatus(`Đã đổi sang ${cameraModeLabel(registerCameraMode)}. Đưa mặt vào khung và nhìn thẳng.`, 'yellow');

                detectionRunId += 1;
                setTimeout(() => detectAndRegisterFace(detectionRunId), 600);
            } catch (error) {
                console.error(error);

                if (isPermissionError(error)) {
                    cameraPermissionFailures += 1;
                    showCameraPermissionHelp(error);
                } else {
                    setStatus(cameraErrorMessage(error), 'red');
                }
            } finally {
                setTimeout(() => {
                    isSwitchingCamera = false;
                    switchCameraButton.disabled = false;
                    switchCameraButton.classList.remove('opacity-60', 'cursor-not-allowed');
                }, 350);
            }
        }

        async function restartRegistration() {
            if (isRequestingCamera || isSwitchingCamera) return;

            detectionRunId += 1;
            resetRegistrationState();
            retryRegisterButton.classList.add('hidden');

            if (!video.srcObject) {
                await requestCameraPermission();
                return;
            }

            setStatus('Đưa mặt vào khung và nhìn thẳng.', 'yellow');
            detectionRunId += 1;
            setTimeout(() => detectAndRegisterFace(detectionRunId), 500);
        }

        function getFaceQualityMessage(detection) {
            const box = detection.detection.box;
            const videoWidth = Math.max(video.videoWidth || 1, 1);
            const videoHeight = Math.max(video.videoHeight || 1, 1);
            const faceRatio = box.width / videoWidth;
            const centerX = box.x + box.width / 2;
            const centerY = box.y + box.height / 2;
            const offCenterX = Math.abs(centerX - videoWidth / 2) / videoWidth;
            const offCenterY = Math.abs(centerY - videoHeight / 2) / videoHeight;

            if (faceRatio < MIN_FACE_RATIO) {
                return 'Đưa mặt lại gần camera hơn.';
            }

            if (faceRatio > MAX_FACE_RATIO) {
                return 'Đưa mặt ra xa camera hơn một chút.';
            }

            if (offCenterX > 0.22 || offCenterY > 0.24) {
                return 'Đưa mặt vào giữa khung.';
            }

            return null;
        }

        function averageDescriptors(samples) {
            const length = samples[0].length;
            const average = new Array(length).fill(0);

            samples.forEach(sample => {
                sample.forEach((value, index) => {
                    average[index] += Number(value || 0);
                });
            });

            return average.map(value => Number((value / samples.length).toFixed(8)));
        }

        function captureFaceImage() {
            try {
                if (!video.videoWidth || !video.videoHeight) return '';

                const canvas = document.createElement('canvas');
                canvas.width = video.videoWidth;
                canvas.height = video.videoHeight;
                const context = canvas.getContext('2d');
                context.drawImage(video, 0, 0, canvas.width, canvas.height);
                return canvas.toDataURL('image/jpeg', 0.72);
            } catch (error) {
                registerDebug('warn', '[Face register] capture image failed', error);
                return '';
            }
        }

        async function submitFaceDescriptor(descriptor) {
            submitted = true;
            detectionRunId += 1;

            const serialized = JSON.stringify(descriptor);
            faceDescriptorInput.value = serialized;
            descriptorInput.value = serialized;
            faceImageDataInput.value = captureFaceImage();

            setStatus('Đã ghi nhận gương mặt. Đang lưu...', 'green');
            switchCameraButton.classList.add('hidden');
            retryRegisterButton.classList.add('hidden');
            switchCameraButton.disabled = true;
            retryRegisterButton.disabled = true;
            startPermissionButton.disabled = true;
            cameraRequiredRetry.disabled = true;

            await sleep(250);
            stopRegisterCameraTracks();
            form.submit();
        }

        async function detectAndRegisterFace(runId) {
            if (submitted || runId !== detectionRunId) return;

            if (!video.srcObject || video.readyState < 2) {
                setStatus('Camera chưa sẵn sàng. Đang thử lại...', 'yellow');
                setTimeout(() => detectAndRegisterFace(runId), 700);
                return;
            }

            let detections = [];

            try {
                detections = await faceapi
                    .detectAllFaces(
                        video,
                        new faceapi.TinyFaceDetectorOptions({
                            inputSize: 224,
                            scoreThreshold: 0.45,
                        })
                    )
                    .withFaceLandmarks()
                    .withFaceDescriptors();
            } catch (error) {
                console.error(error);
                setStatus('AI nhận diện đang lỗi. Vui lòng tải lại trang rồi thử lại.', 'red');
                retryRegisterButton.classList.remove('hidden');
                return;
            }

            if (runId !== detectionRunId) return;

            if (!detections.length) {
                descriptorSamples = [];
                setStatus('Không tìm thấy khuôn mặt. Đưa mặt vào khung và đủ sáng.', 'yellow');
                setTimeout(() => detectAndRegisterFace(runId), 700);
                return;
            }

            if (detections.length > 1) {
                descriptorSamples = [];
                setStatus('Chỉ được có 1 khuôn mặt trong khung hình.', 'red');
                setTimeout(() => detectAndRegisterFace(runId), 900);
                return;
            }

            const detection = detections[0];
            const qualityMessage = getFaceQualityMessage(detection);

            if (qualityMessage) {
                descriptorSamples = [];
                setStatus(qualityMessage, 'yellow');
                setTimeout(() => detectAndRegisterFace(runId), 700);
                return;
            }

            descriptorSamples.push(Array.from(detection.descriptor).map(Number));

            if (descriptorSamples.length < REQUIRED_SAMPLES) {
                setStatus(`Đã nhận diện đúng khung. Giữ yên thêm ${REQUIRED_SAMPLES - descriptorSamples.length} nhịp...`, 'yellow');
                setTimeout(() => detectAndRegisterFace(runId), 500);
                return;
            }

            const descriptor = averageDescriptors(descriptorSamples.slice(-REQUIRED_SAMPLES));
            await submitFaceDescriptor(descriptor);
        }

        async function initRegisterFace() {
            try {
                if (!window.faceapi) {
                    throw new Error('MODEL_LOAD_FAILED');
                }

                if (!navigator.mediaDevices?.getUserMedia || !isSecureCameraContext()) {
                    throw new DOMException('Camera requires HTTPS', 'SecurityError');
                }

                showPreparingPanel();
                await loadFaceModels();

                const cameraPermissionState = await browserPermissionState('camera');

                if (cameraPermissionState === 'denied') {
                    cameraPermissionFailures += 2;
                    showCameraPermissionHelp(new DOMException('Permission denied', 'NotAllowedError'));
                    return;
                }

                await sleep(300);
                await requestCameraPermission(true);
            } catch (error) {
                console.error(error);
                showCameraPermissionHelp(error);
            }
        }

        form.addEventListener('submit', function (event) {
            if (!faceDescriptorInput.value) {
                event.preventDefault();
                setStatus('Vui lòng đưa mặt vào khung để hệ thống đăng ký gương mặt.', 'red');
            }
        });

        window.addEventListener('pagehide', stopRegisterCameraTracks);
        window.addEventListener('beforeunload', stopRegisterCameraTracks);

        initRegisterFace();
    </script>
</x-app-layout>
