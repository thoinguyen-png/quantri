import Cropper from 'cropperjs';

const CROPPER_TEMPLATE = `
    <cropper-canvas background>
        <cropper-image
            rotatable
            scalable
            translatable
        ></cropper-image>

        <cropper-shade></cropper-shade>

        <cropper-handle
            action="move"
            plain
        ></cropper-handle>

        <cropper-selection
            initial-aspect-ratio="1"
            aspect-ratio="1"
            initial-coverage="0.82"
            outlined
        >
            <cropper-grid
                role="grid"
                bordered
                covered
            ></cropper-grid>

            <cropper-crosshair centered></cropper-crosshair>
        </cropper-selection>
    </cropper-canvas>
`;

document
    .querySelectorAll('[data-avatar-cropper]')
    .forEach(initAvatarCropper);

function initAvatarCropper(root) {
    const inputs = Array.from(
        root.querySelectorAll('[data-avatar-input]'),
    );

    const modal = root.querySelector('[data-avatar-modal]');
    const stage = root.querySelector('[data-avatar-stage]');
    const errorBox = root.querySelector('[data-avatar-error]');
    const confirmButton = root.querySelector(
        '[data-crop-action="confirm"]',
    );

    if (
        inputs.length === 0 ||
        !modal ||
        !stage ||
        !confirmButton
    ) {
        return;
    }

    const confirmedFiles = new WeakMap();
    const previewObjectUrls = new WeakMap();

    let cropper = null;
    let activeInput = null;
    let activeItem = null;
    let pendingFile = null;
    let sourceObjectUrl = null;

    inputs.forEach((input) => {
        input.addEventListener('change', () => {
            /*
             * Đây là event được phát sau khi crop xong.
             * Không mở lại modal lần thứ hai.
             */
            if (input.dataset.avatarCropReady === '1') {
                return;
            }

            const file = input.files?.[0];

            if (!file) {
                return;
            }

            if (!file.type.startsWith('image/')) {
                showError('Vui lòng chọn đúng file ảnh.');
                input.value = '';

                return;
            }

            activeInput = input;
            activeItem =
                input.closest('[data-avatar-item]') ||
                root;

            pendingFile = file;

            openCropper(file);
        });
    });

    root
        .querySelectorAll('[data-crop-action]')
        .forEach((button) => {
            button.addEventListener('click', async () => {
                const action = button.dataset.cropAction;

                if (action === 'cancel') {
                    cancelCrop();

                    return;
                }

                if (action === 'reselect') {
                    reselectImage();

                    return;
                }

                if (action === 'confirm') {
                    await confirmCrop();

                    return;
                }

                if (!cropper) {
                    return;
                }

                const cropperImage = cropper.getCropperImage();
                const selection = cropper.getCropperSelection();

                if (action === 'zoom-in') {
                    cropperImage?.$zoom(0.1);
                }

                if (action === 'zoom-out') {
                    cropperImage?.$zoom(-0.1);
                }

                if (action === 'rotate-left') {
                    cropperImage?.$rotate('-90deg');
                }

                if (action === 'rotate-right') {
                    cropperImage?.$rotate('90deg');
                }

                if (action === 'reset') {
                    cropperImage
                        ?.$resetTransform()
                        ?.$center('contain');

                    selection
                        ?.$reset()
                        ?.$center();
                }
            });
        });

    modal
        .querySelectorAll('[data-avatar-modal-close]')
        .forEach((button) => {
            button.addEventListener('click', cancelCrop);
        });

    document.addEventListener('keydown', (event) => {
        if (
            event.key === 'Escape' &&
            !modal.classList.contains('hidden')
        ) {
            cancelCrop();
        }
    });

    function openCropper(file) {
        clearError();
        destroyCropper();

        modal.classList.remove('hidden');
        modal.setAttribute('aria-hidden', 'false');

        document.body.classList.add('overflow-hidden');

        sourceObjectUrl = URL.createObjectURL(file);

        const image = new Image();

        image.alt = 'Ảnh nhân sự cần cắt';
        image.decoding = 'async';

        image.onload = () => {
            stage.innerHTML = '';

            cropper = new Cropper(image, {
                container: stage,
                template: CROPPER_TEMPLATE,
            });
        };

        image.onerror = () => {
            showError('Không thể mở ảnh này.');
            cancelCrop();
        };

        image.src = sourceObjectUrl;
    }

    async function confirmCrop() {
        if (
            !cropper ||
            !pendingFile ||
            !activeInput ||
            !activeItem
        ) {
            return;
        }

        const selection = cropper.getCropperSelection();

        if (!selection) {
            showError('Không tìm thấy vùng cắt ảnh.');

            return;
        }

        const input = activeInput;
        const item = activeItem;
        const oldText = confirmButton.textContent;

        confirmButton.disabled = true;
        confirmButton.textContent = 'Đang xử lý...';

        try {
            const canvas = await selection.$toCanvas({
                width: 720,
                height: 720,

                beforeDraw(context) {
                    context.imageSmoothingEnabled = true;
                    context.imageSmoothingQuality = 'high';
                },
            });
            if (hasTooMuchEmptyMargin(canvas)) {
                showError(
                    'Ảnh đang bị hở lề trong khung. Vui lòng kéo hoặc phóng to ảnh để phủ kín vùng cắt, hoặc bấm "Chọn lại ảnh".'
                );

                return;
            }

            const blob = await canvasToBlob(canvas);

            const originalName = pendingFile.name
                .replace(/\.[^.]+$/, '')
                .trim();

            const croppedFile = new File(
                [blob],
                `${originalName || 'avatar'}-cropped.jpg`,
                {
                    type: 'image/jpeg',
                    lastModified: Date.now(),
                },
            );

            replaceInputFile(input, croppedFile);
            confirmedFiles.set(input, croppedFile);

            showPreview(item, croppedFile);
            closeModal();

            /*
             * Báo cho Quick Onboarding biết file đã crop xong
             * và có thể tải lên server.
             */
            input.dataset.avatarCropReady = '1';

            input.dispatchEvent(
                new Event('change', {
                    bubbles: true,
                }),
            );

            /*
             * Các form bình thường không có listener AJAX.
             * Xóa cờ sau khi event chạy xong để lần chọn tiếp theo
             * vẫn mở cropper.
             */
            queueMicrotask(() => {
                if (
                    input.dataset.avatarCropReady === '1'
                ) {
                    delete input.dataset.avatarCropReady;
                }
            });

            root.dispatchEvent(
                new CustomEvent('avatar:cropped', {
                    detail: {
                        file: croppedFile,
                        input,
                        item,
                    },
                }),
            );

            activeInput = null;
            activeItem = null;
            pendingFile = null;
        } catch (error) {
            console.error(error);

            showError(
                'Không thể cắt ảnh. Vui lòng thử lại.',
            );
        } finally {
            confirmButton.disabled = false;
            confirmButton.textContent = oldText;
        }
    }

    function reselectImage() {
        if (!activeInput) {
            return;
        }

        clearError();

        /*
         * Reset value trước để nếu user chọn lại đúng file cũ
         * thì input change vẫn chạy lại.
         */
        activeInput.value = '';
        activeInput.click();
    }

    function cancelCrop() {
        if (activeInput) {
            const confirmedFile =
                confirmedFiles.get(activeInput);

            if (confirmedFile) {
                replaceInputFile(
                    activeInput,
                    confirmedFile,
                );
            } else {
                activeInput.value = '';
            }
        }

        activeInput = null;
        activeItem = null;
        pendingFile = null;

        closeModal();
    }

    function replaceInputFile(input, file) {
        const transfer = new DataTransfer();

        transfer.items.add(file);

        input.files = transfer.files;
    }

    function showPreview(item, file) {
        const preview = item.querySelector(
            '[data-avatar-preview]',
        );

        if (!preview) {
            return;
        }

        const currentImage = item.querySelector(
            '[data-avatar-current]',
        );

        const fallback = item.querySelector(
            '[data-avatar-fallback], [data-avatar-placeholder]',
        );

        const oldPreviewUrl =
            previewObjectUrls.get(preview);

        if (oldPreviewUrl) {
            URL.revokeObjectURL(oldPreviewUrl);
        }

        const previewUrl = URL.createObjectURL(file);

        previewObjectUrls.set(preview, previewUrl);

        preview.src = previewUrl;
        preview.classList.remove('hidden');

        /*
         * Form Quick Onboarding public dùng CSS:
         * .avatar-preview.has-image img
         * nên phải thêm class này thì ảnh mới xuất hiện.
         */
        const previewContainer = preview.closest('.avatar-preview');

        previewContainer?.classList.add('has-image');

        currentImage?.classList.add('hidden');
        fallback?.classList.add('hidden');

        item
            .querySelector('[data-avatar-wrap]')
            ?.classList.remove('is-avatar-missing');
    }

    function closeModal() {
        modal.classList.add('hidden');
        modal.setAttribute('aria-hidden', 'true');

        document.body.classList.remove('overflow-hidden');

        destroyCropper();
    }

    function destroyCropper() {
        cropper?.destroy();
        cropper = null;

        stage.innerHTML = '';

        if (sourceObjectUrl) {
            URL.revokeObjectURL(sourceObjectUrl);
            sourceObjectUrl = null;
        }
    }

    function hasTooMuchEmptyMargin(canvas) {
        const context = canvas.getContext('2d', {
            willReadFrequently: true,
        });

        if (!context) {
            return false;
        }

        const width = canvas.width;
        const height = canvas.height;

        if (!width || !height) {
            return false;
        }

        const imageData = context.getImageData(
            0,
            0,
            width,
            height,
        ).data;

        const band = Math.max(
            10,
            Math.floor(Math.min(width, height) * 0.06),
        );

        const transparentRatio = getTransparentRatioInEdgeBand(
            imageData,
            width,
            height,
            band,
        );

        /*
         * Nếu vùng rìa bị trong suốt quá nhiều,
         * nghĩa là user đang để hở lề.
         */
        return transparentRatio > 0.12;
    }

    function getTransparentRatioInEdgeBand(
        data,
        width,
        height,
        band,
    ) {
        let transparent = 0;
        let total = 0;

        for (let y = 0; y < height; y++) {
            for (let x = 0; x < width; x++) {
                const isEdge =
                    x < band ||
                    x >= width - band ||
                    y < band ||
                    y >= height - band;

                if (!isEdge) {
                    continue;
                }

                total++;

                const alpha = data[(y * width + x) * 4 + 3];

                if (alpha < 10) {
                    transparent++;
                }
            }
        }

        if (!total) {
            return 0;
        }

        return transparent / total;
    }

    function canvasToBlob(canvas) {
        return new Promise((resolve, reject) => {
            canvas.toBlob(
                (blob) => {
                    if (blob) {
                        resolve(blob);

                        return;
                    }

                    reject(
                        new Error(
                            'Canvas không tạo được ảnh.',
                        ),
                    );
                },
                'image/jpeg',
                0.92,
            );
        });
    }

    function showError(message) {
        if (!errorBox) {
            return;
        }

        errorBox.textContent = message;
        errorBox.classList.remove('hidden');
    }

    function clearError() {
        if (!errorBox) {
            return;
        }

        errorBox.textContent = '';
        errorBox.classList.add('hidden');
    }
}