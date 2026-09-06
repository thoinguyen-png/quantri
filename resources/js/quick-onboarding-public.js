import './avatar-cropper';
import {
    BrowserQRCodeReader,
    BrowserQRCodeSvgWriter,
} from '@zxing/browser'

const form = document.querySelector('[data-onboarding-form]');

if (form) {
    const OCR_ENABLED = form.dataset.ocrEnabled === '1';
    const ocrUrl = form.dataset.ocrUrl || '';
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    const avatarInput = form.querySelector('#avatarInput');
    const avatarPreview = form.querySelector('#avatarPreview');
    const avatarImage = form.querySelector('#avatarImage');
    const cccdInput = form.querySelector('#cccdInput');
    const cccdUploadBox = form.querySelector('#cccdUploadBox');
    const cccdUploadText = form.querySelector('#cccdUploadText');
    const genderInput = form.querySelector('#genderInput');
    const formMessage = form.querySelector('#formError');
    const cameraOpenButton = form.querySelector('[data-qr-camera-open]');
    const imagePickButtons = document.querySelectorAll('[data-qr-image-pick]');
    const cameraModal = document.querySelector('[data-qr-modal]');
    const cameraCloseButton = cameraModal?.querySelector('[data-qr-modal-close]');
    const cameraVideo = cameraModal?.querySelector('[data-qr-video]');
    const cameraStatus = cameraModal?.querySelector('[data-qr-camera-status]');
    const torchButton = cameraModal?.querySelector('[data-qr-torch]');

    let avatarObjectUrl = null;
    let ocrRequestController = null;
    let activeImageScanId = 0;
    let activeCameraControls = null;
    let activeCameraReader = null;
    let activeTorchOn = false;

    function initializeExistingMedia() {
        if (avatarPreview && avatarImage && avatarImage.getAttribute('src')) {
            avatarPreview.classList.add('has-image');
        }

        if (cccdUploadBox?.dataset.hasExisting === '1') {
            cccdUploadBox.classList.add('is-uploaded');

            if (cccdUploadText && !cccdUploadText.textContent.trim()) {
                cccdUploadText.textContent = 'Da tai CCCD truoc do';
            }
        }
    }

    function setAvatarPreview(file) {
        if (!file || !avatarPreview || !avatarImage) {
            return;
        }

        if (avatarObjectUrl) {
            URL.revokeObjectURL(avatarObjectUrl);
        }

        avatarObjectUrl = URL.createObjectURL(file);
        avatarImage.src = avatarObjectUrl;
        avatarPreview.classList.add('has-image');
    }

    function setMessage(text, isError = false) {
        if (!formMessage) {
            return;
        }

        formMessage.textContent = text || '';
        formMessage.classList.toggle('is-error', Boolean(isError));
    }

    function setScanStatus(text, isSuccess = false, isError = false) {
        if (cccdUploadText) {
            cccdUploadText.textContent = text || '';
            cccdUploadText.classList.toggle('is-success', Boolean(isSuccess));
            cccdUploadText.classList.toggle('is-error', Boolean(isError));
        }

        setMessage(text, isError);
    }

    function fillInput(name, value, source = 'ocr') {
        if (value === null || value === undefined || String(value).trim() === '') {
            return;
        }

        const input = form.querySelector('[name="' + name + '"]');

        if (!input) {
            return;
        }

        const currentValue = String(input.value || '').trim();
        const sourceKey = source === 'qr' ? 'qrFilled' : 'ocrFilled';
        const wasFilledBySource = input.dataset[sourceKey] === '1';

        if (source !== 'qr' && currentValue !== '' && !wasFilledBySource) {
            return;
        }

        let cleanValue = String(value).trim();

        if ((name === 'date_of_birth' || name === 'issue_date') && /^\d{4}-\d{2}-\d{2}$/.test(cleanValue)) {
            input.type = 'date';
        }

        input.value = cleanValue;
        input.dataset[sourceKey] = '1';
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function normalizeText(value) {
        return String(value || '')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .trim()
            .toLowerCase();
    }

    function normalizeGenderValue(value) {
        const normalized = normalizeText(value);

        if (normalized.startsWith('nam') || normalized.includes('male')) {
            return 'Nam';
        }

        if (normalized.startsWith('nu') || normalized.includes('female')) {
            return 'Nu';
        }

        return '';
    }

    function selectGender(value, source = 'ocr') {
        const gender = normalizeGenderValue(value);

        if (!gender || !genderInput) {
            return;
        }

        const sourceKey = source === 'qr' ? 'qrFilled' : 'ocrFilled';
        const currentValue = String(genderInput.value || '').trim();
        const wasFilledBySource = genderInput.dataset[sourceKey] === '1';

        if (source !== 'qr' && currentValue !== '' && !wasFilledBySource) {
            return;
        }

        genderInput.value = gender;
        genderInput.dataset[sourceKey] = '1';

        form.querySelectorAll('[data-gender]').forEach(function (item) {
            const selected = item.dataset.gender === gender;
            item.classList.toggle('is-selected', selected);
            item.setAttribute('aria-pressed', selected ? 'true' : 'false');
        });

        genderInput.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function parseDateValue(value) {
        const text = String(value || '').trim();
        if (!text) {
            return '';
        }

        const normalized = text.replace(/[^\d/\-.]/g, '');
        const match1 = normalized.match(/^(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{4})$/);
        if (match1) {
            const day = match1[1].padStart(2, '0');
            const month = match1[2].padStart(2, '0');
            const year = match1[3];
            return `${year}-${month}-${day}`;
        }

        const match2 = normalized.match(/^(\d{4})[\/\-.](\d{1,2})[\/\-.](\d{1,2})$/);
        if (match2) {
            const year = match2[1];
            const month = match2[2].padStart(2, '0');
            const day = match2[3].padStart(2, '0');
            return `${year}-${month}-${day}`;
        }

        const match3 = text.match(/^(\d{2})(\d{2})(\d{4})$/);
        if (match3) {
            return `${match3[3]}-${match3[2]}-${match3[1]}`;
        }

        return text;
    }

    function cleanText(value) {
        return String(value || '')
            .replace(/[\u0000-\u001f]+/g, ' ')
            .replace(/\s+/g, ' ')
            .replace(/\s*,\s*/g, ', ')
            .replace(/(?:,\s*){2,}/g, ', ')
            .trim();
    }

    function parseCccdQr(rawText) {
        const source = cleanText(rawText).replace(/\uFEFF/g, '');
        if (!source) {
            return null;
        }

        const parts = source.split('|').map((part) => cleanText(part));
        const data = {};

        if (parts.length >= 7) {
            data.citizen_id = parts[0] || '';
            data.name = parts[2] || '';
            data.date_of_birth = parseDateValue(parts[3] || '');
            data.gender = normalizeGenderValue(parts[4] || '');
            data.address = parts[5] || '';
            data.issue_date = parseDateValue(parts[6] || '');
        } else {
            const lines = source.split(/\r?\n/).map((line) => cleanText(line)).filter(Boolean);

            data.citizen_id = (source.match(/(?<!\d)\d{12}(?!\d)/) || [])[0] || '';
            data.name = lines.find((line) => /full\s*name|ho\s*va\s*ten/i.test(normalizeText(line))) || '';
            data.date_of_birth = parseDateValue((lines.find((line) => /date\s*of\s*birth|ngay\s*sinh/i.test(normalizeText(line))) || '').split(':').pop() || '');
            data.gender = normalizeGenderValue((lines.find((line) => /sex|gioi\s*tinh/i.test(normalizeText(line))) || '').split(':').pop() || '');
            data.address = lines.find((line) => /place\s*of\s*residence|noi\s*thuong\s*tru/i.test(normalizeText(line))) || '';
            data.issue_date = parseDateValue((lines.find((line) => /date\s*of\s*issue|ngay\s*cap/i.test(normalizeText(line))) || '').split(':').pop() || '');
        }

        data.citizen_id = cleanText(data.citizen_id || '').replace(/\D/g, '').slice(0, 12);
        data.name = cleanText(data.name || '');
        data.address = cleanText(data.address || '');

        if (data.citizen_id || data.name || data.date_of_birth || data.gender || data.address || data.issue_date) {
            return data;
        }

        return null;
    }

    function parseJsonResponse(text) {
        try {
            return JSON.parse(text);
        } catch (error) {
            throw new Error('invalid_json');
        }
    }

    function setOcrButtonLoading(isLoading) {
        const cccdOcrButton = form.querySelector('[data-ocr-button]');

        if (!cccdOcrButton) {
            return;
        }

        cccdOcrButton.disabled = isLoading || !cccdInput?.files?.[0] || !OCR_ENABLED;
        const label = cccdOcrButton.querySelector('span');

        if (label) {
            label.textContent = isLoading ? 'Dang doc thong tin...' : 'Doc thong tin tu anh';
        }
    }

    function stopCameraScan() {
        if (activeCameraControls && typeof activeCameraControls.stop === 'function') {
            activeCameraControls.stop();
        }

        activeCameraControls = null;
        activeTorchOn = false;

        if (torchButton) {
            torchButton.hidden = true;
            torchButton.setAttribute('aria-pressed', 'false');
        }

        if (cameraVideo) {
            cameraVideo.srcObject = null;
        }
    }

    function closeCameraModal() {
        stopCameraScan();

        if (cameraModal) {
            cameraModal.hidden = true;
            cameraModal.setAttribute('aria-hidden', 'true');
        }
    }

    function openCameraModal() {
        if (!cameraModal || !cameraVideo) {
            return;
        }

        cameraModal.hidden = false;
        cameraModal.setAttribute('aria-hidden', 'false');
        if (cameraStatus) {
            cameraStatus.textContent = 'Dua ma QR tren CCCD vao giua khung hinh.';
        }
    }

    function fillFromQrData(data, source = 'qr') {
        fillInput('name', data.name, source);
        fillInput('citizen_id', data.citizen_id, source);
        fillInput('date_of_birth', data.date_of_birth, source);
        fillInput('address', data.address, source);
        fillInput('place_of_origin', data.place_of_origin, source);
        fillInput('issue_date', data.issue_date, source);
        selectGender(data.gender, source);
    }

    async function runExistingOcrFlow(file) {
        if (!OCR_ENABLED || !ocrUrl || !file) {
            return false;
        }

        if (ocrRequestController) {
            ocrRequestController.abort();
        }

        ocrRequestController = new AbortController();
        setOcrButtonLoading(true);
        setScanStatus('Khong tim thay ma QR, dang thu doc thong tin tu anh...');

        const payload = new FormData();
        payload.append('cccd_image', file, file.name);

        try {
            const response = await fetch(ocrUrl, {
                method: 'POST',
                cache: 'no-store',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: payload,
                signal: ocrRequestController.signal,
            });

            const responseText = await response.text();
            const result = parseJsonResponse(responseText);

            if (!response.ok || !result.ok) {
                throw new Error(result?.message || 'ocr_failed');
            }

            fillFromQrData(result.data || {}, 'ocr');
            setScanStatus('Da doc thong tin CCCD. Vui long kiem tra va chinh sua neu can.', true);

            return true;
        } catch (error) {
            if (error?.name === 'AbortError') {
                return false;
            }

            setScanStatus('Khong doc duoc anh. Vui long nhap thong tin thu cong hoac thu anh ro hon.', false, true);
            return false;
        } finally {
            setOcrButtonLoading(false);
            ocrRequestController = null;
        }
    }

    async function scanQrFromImage(file) {
        const url = URL.createObjectURL(file);
        const reader = new BrowserQRCodeReader();

        try {
            const result = await reader.decodeFromImageUrl(url);
            const text = result?.getText?.() || result?.text || '';
            return cleanText(text);
        } catch (error) {
            return '';
        } finally {
            URL.revokeObjectURL(url);
        }
    }

    function scanQrFromImageFile(file) {
        return scanQrFromImage(file);
    }

    function applyQrText(rawText) {
        const data = parseCccdQr(rawText);

        if (!data) {
            return false;
        }

        fillFromQrData(data, 'qr');
        setScanStatus('Da doc thong tin CCCD', true);
        return true;
    }

    async function startCameraScan() {
        if (!cameraModal || !cameraVideo) {
            return;
        }

        if (!navigator.mediaDevices?.getUserMedia) {
            setScanStatus('Trinh duyet khong ho tro camera. Vui long chon anh CCCD co san.', false, true);
            return;
        }

        openCameraModal();

        const reader = new BrowserQRCodeReader();
        activeCameraReader = reader;

        if (cameraStatus) {
            cameraStatus.textContent = 'Dua ma QR tren CCCD vao giua khung hinh.';
        }

        try {
            activeCameraControls = await reader.decodeFromConstraints(
                {
                    audio: false,
                    video: {
                        facingMode: { ideal: 'environment' },
                    },
                },
                cameraVideo,
                (result, error, controls) => {
                    if (error) {
                        return;
                    }

                    const text = cleanText(result?.getText?.() || result?.text || '');

                    if (!text) {
                        return;
                    }

                    if (controls) {
                        controls.stop();
                    }

                    if (applyQrText(text)) {
                        closeCameraModal();
                    }
                }
            );

            if (activeCameraControls?.switchTorch && torchButton) {
                torchButton.hidden = false;
                torchButton.onclick = async () => {
                    activeTorchOn = !activeTorchOn;

                    try {
                        await activeCameraControls.switchTorch(activeTorchOn);
                        torchButton.setAttribute('aria-pressed', activeTorchOn ? 'true' : 'false');
                    } catch (error) {
                        activeTorchOn = false;
                    }
                };
            }
        } catch (error) {
            if (cameraStatus) {
                cameraStatus.textContent = 'Khong the mo camera. Vui long chon anh co san.';
            }
        }
    }

    

    cccdInput?.addEventListener('change', async function () {
        const file = cccdInput.files?.[0];

        cccdUploadBox?.classList.toggle('is-uploaded', Boolean(file));

        if (cccdUploadText) {
            cccdUploadText.textContent = file ? 'Dang doc ma QR...' : 'He thong se tu dong doc thong tin tu ma QR CCCD/VNeID.';
        }

        if (!file) {
            return;
        }

        const requestId = ++activeImageScanId;
        const qrText = await scanQrFromImageFile(file);

        if (requestId !== activeImageScanId) {
            return;
        }

        if (qrText && applyQrText(qrText)) {
            return;
        }

        await runExistingOcrFlow(file);
    });

    cameraOpenButton?.addEventListener('click', function () {
        startCameraScan();
    });

    imagePickButtons.forEach((button) => {
        button.addEventListener('click', function () {
            closeCameraModal();
            cccdInput?.click();
        });
    });

    cameraCloseButton?.addEventListener('click', function () {
        closeCameraModal();
    });

    cameraModal?.addEventListener('click', function (event) {
        if (event.target === cameraModal) {
            closeCameraModal();
        }
    });

    form.querySelectorAll('[data-gender]').forEach(function (button) {
        button.addEventListener('click', function () {
            const value = button.dataset.gender || '';

            form.querySelectorAll('[data-gender]').forEach(function (item) {
                const selected = item === button;
                item.classList.toggle('is-selected', selected);
                item.setAttribute('aria-pressed', selected ? 'true' : 'false');
            });

            if (genderInput) {
                genderInput.value = value;
            }
        });
    });

    initializeExistingMedia();

    if (!navigator.mediaDevices?.getUserMedia && cameraOpenButton) {
        cameraOpenButton.disabled = true;
        cameraOpenButton.setAttribute('aria-disabled', 'true');
    }

    window.addEventListener('pagehide', function () {
        if (ocrRequestController) {
            ocrRequestController.abort();
        }

        stopCameraScan();

        if (avatarObjectUrl) {
            URL.revokeObjectURL(avatarObjectUrl);
            avatarObjectUrl = null;
        }
    });
}
