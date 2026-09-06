const form = document.querySelector('[data-qr-rating-form]');
const submit = document.querySelector('[data-qr-rating-submit]');
const submitText = document.querySelector('[data-qr-rating-submit-text]');
const comment = document.querySelector('[data-qr-rating-comment]');
const counter = document.querySelector('[data-qr-rating-counter]');
const successModal = document.querySelector('[data-qr-success-modal]');
const errorModal = document.querySelector('[data-qr-error-modal]');
const errorTitle = document.querySelector('[data-qr-error-title]');
const errorMessage = document.querySelector('[data-qr-error-message]');
const privateModal = document.querySelector('[data-qr-private-modal]');
const internalModal = document.querySelector('[data-qr-internal-modal]');
const THANK_YOU_DELAY_MS = 2000;
let privateModeBlocked = false;
let keyboardScrollTimer = null;

function selectedRating() {
    return document.querySelector('input[name="rating"]:checked');
}

function syncRatingState() {
    document.querySelectorAll('[data-qr-rating-option]').forEach((option) => {
        const input = option.querySelector('input[name="rating"]');
        option.classList.toggle('is-selected', Boolean(input?.checked));
        option.classList.toggle('is-disabled', privateModeBlocked);
    });

    if (!submit) {
        return;
    }

    submit.disabled = privateModeBlocked || !selectedRating();
    if (submitText && privateModeBlocked) {
        submitText.textContent = 'MỞ TAB THƯỜNG ĐỂ TIẾP TỤC';
        return;
    }

    if (submitText && !selectedRating()) {
        submitText.textContent = 'CHỌN 1 MỨC ĐÁNH GIÁ';
    } else if (submitText) {
        submitText.textContent = 'GỬI ĐÁNH GIÁ';
    }
}

function syncCounter() {
    if (comment && counter) {
        counter.textContent = `${comment.value.length}/300`;
    }
}

async function privateBrowsingState() {
    try {
        const key = `qr-rating-${Date.now()}`;
        window.localStorage.setItem(key, '1');
        window.localStorage.removeItem(key);
    } catch (error) {
        return 'suspected';
    }

    if (navigator.storage?.persisted) {
        try {
            const persisted = await navigator.storage.persisted();
            if (persisted === false && navigator.storage?.estimate) {
                const estimate = await navigator.storage.estimate();
                if (estimate.quota && estimate.quota < 120000000) {
                    return 'suspected';
                }
            }
        } catch (error) {
            return 'unknown';
        }
    }

    if (navigator.storage?.estimate) {
        try {
            const estimate = await navigator.storage.estimate();
            if (estimate.quota && estimate.quota < 120000000) {
                return 'suspected';
            }
        } catch (error) {
            return 'unknown';
        }
    }

    return 'clear';
}

function openModal(modal) {
    if (modal) {
        modal.hidden = false;
    }
}

function closeModal(modal) {
    if (modal) {
        modal.hidden = true;
    }
}

function thankYouUrl() {
    return form?.dataset.thankYouUrl || successModal?.dataset.thankYouUrl || '/rating-thank-you';
}

function showErrorModal(message, title = 'CHƯA GỬI ĐƯỢC') {
    if (!errorModal) {
        return;
    }

    if (errorTitle) {
        errorTitle.textContent = title;
    }

    if (errorMessage) {
        errorMessage.textContent = message || 'Vui long thu lai sau.';
    }

    openModal(errorModal);
}

function scrollCommentIntoView() {
    if (!comment) {
        return;
    }

    window.clearTimeout(keyboardScrollTimer);

    keyboardScrollTimer = window.setTimeout(() => {
        const field = comment.closest('.qr-message-field') || comment;
        const viewport = window.visualViewport;
        const rect = field.getBoundingClientRect();
        const visibleTop = viewport ? viewport.offsetTop : 0;
        const visibleHeight = viewport ? viewport.height : window.innerHeight;
        const targetBottom = visibleTop + visibleHeight - 24;

        if (rect.bottom > targetBottom || rect.top < visibleTop + 12) {
            const currentTop = viewport ? viewport.pageTop : window.scrollY;
            const delta = rect.bottom - targetBottom;
            window.scrollTo({
                top: Math.max(0, currentTop + delta),
                behavior: 'smooth',
            });
            return;
        }

        field.scrollIntoView({
            behavior: 'smooth',
            block: 'center',
            inline: 'nearest',
        });
    }, 180);
}

function redirectToThankYou(delay = THANK_YOU_DELAY_MS) {
    window.setTimeout(() => {
        window.location.replace(thankYouUrl());
    }, delay);
}

document.querySelectorAll('[data-qr-rating-option]').forEach((option) => {
    option.addEventListener('click', () => {
        if (privateModeBlocked) {
            openModal(privateModal);
            return;
        }

        const input = option.querySelector('input[name="rating"]');
        if (input) {
            input.checked = true;
            syncRatingState();
        }
    });
});

document.querySelectorAll('[data-qr-modal-close]').forEach((button) => {
    button.addEventListener('click', () => {
        if (button.hasAttribute('data-qr-private-reload')) {
            window.location.reload();
            return;
        }

        closeModal(button.closest('[data-qr-modal]'));
    });
});

comment?.addEventListener('input', syncCounter);
comment?.addEventListener('focus', scrollCommentIntoView);
comment?.addEventListener('click', scrollCommentIntoView);

window.visualViewport?.addEventListener('resize', () => {
    if (document.activeElement === comment) {
        scrollCommentIntoView();
    }
});

form?.addEventListener('submit', (event) => {
    if (privateModeBlocked) {
        event.preventDefault();
        openModal(privateModal);
        syncRatingState();
        return;
    }

    if (!selectedRating()) {
        event.preventDefault();
        syncRatingState();
        return;
    }

    event.preventDefault();

    if (submit) {
        submit.disabled = true;
        submit.classList.add('is-loading');
    }

    if (submitText) {
        submitText.textContent = 'ĐANG GỬI...';
    }

    fetch(form.action, {
        method: 'POST',
        body: new FormData(form),
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
    }).then(async (response) => {
        if (response.ok) {
            openModal(successModal);
            form.reset();
            syncRatingState();
            syncCounter();
            redirectToThankYou();
            return;
        }

        const data = await response.json().catch(() => ({}));
        showErrorModal(data.message || 'Chưa gửi được lúc này.');
    }).catch(() => {
        showErrorModal('Kết nối không ổn định. Vui lòng thử lại.', 'LỖI MẠNG');
    }).finally(() => {
        if (submit) {
            submit.classList.remove('is-loading');
        }

        syncRatingState();
    });
});

syncRatingState();
syncCounter();

if (successModal?.dataset.open === 'true') {
    openModal(successModal);
    redirectToThankYou();
}

if (errorModal?.dataset.open === 'true') {
    openModal(errorModal);
}

if (internalModal?.dataset.open === 'true') {
    openModal(internalModal);
}

if (form && privateModal) {
    privateBrowsingState().then((state) => {
        if (state !== 'suspected') {
            return;
        }

        privateModeBlocked = true;
        form.setAttribute('data-private-blocked', 'true');
        form.querySelectorAll('input[name="rating"]').forEach((input) => {
            input.checked = false;
            input.disabled = true;
        });
        openModal(privateModal);
        syncRatingState();
    });
}
