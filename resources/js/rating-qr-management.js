function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content || '';
}

async function fetchJson(url, options = {}) {
    const response = await fetch(url, {
        cache: 'no-store',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
            ...(options.headers || {}),
        },
        ...options,
    });

    if (!response.ok) {
        const message = response.status === 403
            ? 'Ban khong co quyen thao tac QR nay.'
            : 'Khong the tai du lieu QR. Vui long thu lai.';
        throw new Error(message);
    }

    return response.json();
}

function setText(root, selector, value) {
    const element = root.querySelector(selector);
    if (element) {
        element.textContent = value;
    }
}

function setQrState(root, payload) {
    root.dataset.ratingQrEnabled = payload.rating_qr_enabled ? '1' : '0';
    root.dataset.ratingQrShowUrl = root.dataset.ratingQrShowUrl || '';
    root.dataset.ratingQrPublicUrl = payload.public_url || '';

    const preview = root.querySelector('[data-rating-qr-preview]');
    const actions = root.querySelector('[data-rating-qr-actions]');
    const disabled = root.querySelector('[data-rating-qr-disabled]');
    const copyButton = root.querySelector('[data-rating-qr-copy]');
    const downloadLink = root.querySelector('[data-rating-qr-download]');
    const image = root.querySelector('[data-rating-qr-image]');

    if (payload.rating_qr_enabled) {
        preview?.classList.remove('hidden');
        actions?.classList.remove('hidden');
        disabled?.classList.add('hidden');

        if (image) {
            image.src = `${payload.svg_url}?t=${Date.now()}`;
        }

        if (downloadLink) {
            downloadLink.href = payload.download_url;
        }

        if (copyButton) {
            copyButton.disabled = false;
        }
    } else {
        preview?.classList.add('hidden');
        actions?.classList.add('hidden');
        disabled?.classList.remove('hidden');

        if (image) {
            image.removeAttribute('src');
        }

        if (copyButton) {
            copyButton.disabled = true;
        }
    }
}

async function loadQr(root) {
    if (root.dataset.ratingQrEnabled !== '1') {
        return;
    }

    try {
        const payload = await fetchJson(root.dataset.ratingQrShowUrl);
        setQrState(root, payload);
        setText(root, '[data-rating-qr-message]', '');
    } catch (error) {
        setText(root, '[data-rating-qr-message]', error.message);
    }
}

function modal() {
    return document.querySelector('[data-rating-qr-confirm-modal]');
}

function openConfirm(root) {
    const currentModal = modal();
    if (!currentModal) {
        return;
    }

    currentModal.hidden = false;
    currentModal.dataset.targetPanel = root.id;
}

function closeConfirm() {
    const currentModal = modal();
    if (currentModal) {
        currentModal.hidden = true;
        currentModal.dataset.targetPanel = '';
    }
}

async function postAction(root, url, method) {
    const payload = await fetchJson(url, { method });
    setQrState(root, payload);
    setText(root, '[data-rating-qr-message]', 'Da cap nhat QR danh gia.');
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-rating-qr-panel]').forEach((root) => {
        if (root.dataset.ratingQrAutoload !== '0') {
            loadQr(root);
        }

        root.querySelector('[data-rating-qr-view]')?.addEventListener('click', () => loadQr(root));

        root.querySelector('[data-rating-qr-copy]')?.addEventListener('click', async () => {
            if (!root.dataset.ratingQrPublicUrl) {
                await loadQr(root);
            }

            try {
                await navigator.clipboard.writeText(root.dataset.ratingQrPublicUrl);
                setText(root, '[data-rating-qr-message]', 'Da copy link QR danh gia.');
            } catch (error) {
                setText(root, '[data-rating-qr-message]', 'Trinh duyet khong cho copy tu dong.');
            }
        });

        root.querySelector('[data-rating-qr-enable]')?.addEventListener('click', async (event) => {
            event.currentTarget.disabled = true;
            try {
                await postAction(root, root.dataset.ratingQrEnableUrl, 'PATCH');
            } catch (error) {
                setText(root, '[data-rating-qr-message]', error.message);
            } finally {
                event.currentTarget.disabled = false;
            }
        });

        root.querySelector('[data-rating-qr-disable]')?.addEventListener('click', async (event) => {
            event.currentTarget.disabled = true;
            try {
                await postAction(root, root.dataset.ratingQrDisableUrl, 'PATCH');
            } catch (error) {
                setText(root, '[data-rating-qr-message]', error.message);
            } finally {
                event.currentTarget.disabled = false;
            }
        });

        root.querySelector('[data-rating-qr-regenerate]')?.addEventListener('click', () => openConfirm(root));
    });

    document.querySelector('[data-rating-qr-confirm-cancel]')?.addEventListener('click', closeConfirm);
    document.querySelector('[data-rating-qr-confirm-modal] [data-rating-qr-modal-backdrop]')?.addEventListener('click', closeConfirm);
    document.querySelector('[data-rating-qr-confirm-submit]')?.addEventListener('click', async (event) => {
        const currentModal = modal();
        const root = currentModal?.dataset.targetPanel ? document.getElementById(currentModal.dataset.targetPanel) : null;

        if (!root) {
            closeConfirm();
            return;
        }

        event.currentTarget.disabled = true;
        try {
            await postAction(root, root.dataset.ratingQrRegenerateUrl, 'POST');
            closeConfirm();
        } catch (error) {
            setText(root, '[data-rating-qr-message]', error.message);
        } finally {
            event.currentTarget.disabled = false;
        }
    });

    document.querySelectorAll('details').forEach((details) => {
        details.addEventListener('toggle', () => {
            if (!details.open) {
                return;
            }

            details.querySelectorAll('[data-rating-qr-panel]').forEach((root) => loadQr(root));
        });
    });
});
