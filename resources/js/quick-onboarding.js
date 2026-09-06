const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
const statusClasses = ['quick-entry--draft', 'quick-entry--sent', 'quick-entry--completed'];
const autosaveTimers = new WeakMap();
const autosaveControllers = new WeakMap();
const shareState = {
    row: null,
    name: '',
    code: '',
    link: '',
    qrUrl: '',
    avatarUrl: '',
    branch: '',
    role: '',
    markSentUrl: '',
};
let shareCloseTimer = 0;

function toast(message) {
    const target = document.querySelector('[data-share-toast]');

    if (!target) {
        return;
    }

    target.textContent = message;
    target.classList.add('is-show');

    window.clearTimeout(Number(target.dataset.timer || 0));
    target.dataset.timer = String(window.setTimeout(function () {
        target.classList.remove('is-show');
    }, 1700));
}

function initialFromName(name) {
    const clean = (name || 'M').trim();
    return clean.charAt(0).toUpperCase() || 'M';
}

function setAvatar(row, url, label) {
    const wrap = row?.querySelector('[data-avatar-wrap]');
    const placeholder = row?.querySelector(
        '[data-avatar-placeholder]',
    );
    const croppedPreview = row?.querySelector(
        '[data-avatar-preview]',
    );

    if (!wrap || !placeholder) {
        return;
    }

    let image = wrap.querySelector('[data-avatar-img]');

    if (!url) {
        if (
            croppedPreview &&
            croppedPreview.src &&
            !croppedPreview.classList.contains('hidden')
        ) {
            wrap.classList.remove('is-avatar-missing');

            return;
        }

        if (image) {
            image.hidden = true;
            image.classList.add('hidden');
            image.removeAttribute('src');
        }

        wrap.classList.add('is-avatar-missing');

        placeholder.innerHTML = `
            <svg
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
            >
                <path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3Z"></path>
                <circle cx="12" cy="13" r="3"></circle>
            </svg>
        `;

        placeholder.setAttribute(
            'aria-label',
            initialFromName(label),
        );

        return;
    }

    if (!image) {
        image = document.createElement('img');

        image.alt = '';
        image.dataset.avatarImg = '1';
        image.dataset.avatarCurrent = '1';

        wrap.insertBefore(image, placeholder);
    }

    /*
     * Giữ ảnh crop tạm hiển thị cho đến khi
     * ảnh thật từ server tải thành công.
     */
    image.hidden = false;
    image.classList.add('hidden');

    image.onload = function () {
        image.hidden = false;
        image.classList.remove('hidden');

        croppedPreview?.classList.add('hidden');

        wrap.classList.remove('is-avatar-missing');
    };

    image.onerror = function () {
        image.hidden = true;
        image.classList.add('hidden');

        /*
         * Ảnh server lỗi thì vẫn giữ ảnh crop tạm,
         * tránh làm khung ảnh trở thành trống.
         */
        if (croppedPreview?.src) {
            croppedPreview.classList.remove('hidden');
            wrap.classList.remove('is-avatar-missing');

            return;
        }

        wrap.classList.add('is-avatar-missing');
    };

    const separator = url.includes('?') ? '&' : '?';

    /*
     * Thêm timestamp để trình duyệt không lấy ảnh cũ từ cache.
     */
    image.src = `${url}${separator}avatar_v=${Date.now()}`;
}

function replaceExpectedInputWithName(row, entry) {
    const input = row.querySelector('[data-expected-name-input]');

    if (!input) {
        return;
    }

    const label = document.createElement('div');
    label.className = 'kara-quick-completed-name';
    label.dataset.employeeName = '';
    label.textContent = entry.employee_name || entry.expected_name || input.dataset.defaultName || 'Nhan su moi';
    input.replaceWith(label);
}

function applyEntryState(entry) {
    const row = document.querySelector('[data-entry-row="' + entry.id + '"]');

    if (!row) {
        return;
    }

    statusClasses.forEach(function (className) {
        row.classList.remove(className);
    });
    row.classList.add(entry.color_class || 'quick-entry--draft');
    row.dataset.status = entry.status || 'draft';

    const branchName = row.querySelector('[data-branch-name]');
    if (branchName) {
        branchName.textContent = entry.branch_name || 'Chua co co so';
    }

    const expectedInput = row.querySelector('[data-expected-name-input]');
    if (expectedInput && expectedInput !== document.activeElement) {
        expectedInput.value = entry.expected_name || '';
        expectedInput.dataset.lastSavedValue = expectedInput.value.trim();
    }

    const nameTarget = row.querySelector('[data-employee-name]');
    if (nameTarget) {
        nameTarget.textContent = entry.employee_name || entry.expected_name || nameTarget.textContent;
    }

    if (entry.status === 'completed') {
        replaceExpectedInputWithName(row, entry);
    }

    const roleSelect = row.querySelector('[data-role-select]');
    if (roleSelect) {
        if (entry.intended_role && roleSelect !== document.activeElement) {
            roleSelect.value = entry.intended_role;
            roleSelect.dataset.originalRole = entry.intended_role;
        }

        roleSelect.disabled = entry.status === 'completed';
    }

    const shareButton = row.querySelector('[data-share-open]');
    if (shareButton) {
        shareButton.dataset.name = entry.employee_name || entry.expected_name || shareButton.dataset.name || 'Nhan su';
        shareButton.dataset.link = entry.invite_url || shareButton.dataset.link || '';
        shareButton.dataset.avatarUrl = entry.avatar_url || '';
        shareButton.dataset.branch = entry.branch_name || shareButton.dataset.branch || '';
        shareButton.dataset.role = entry.role_label || shareButton.dataset.role || '';
    }

    setAvatar(row, entry.avatar_url || '', entry.employee_name || entry.expected_name || shareButton?.dataset.name || '');
}

async function postJson(url, payload) {
    const response = await fetch(url, {
        method: 'POST',
        cache: 'no-store',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify(payload || {}),
    });

    const data = await response.json().catch(() => ({}));

    if (!response.ok) {
        throw new Error(data.message || 'Khong xu ly duoc yeu cau.');
    }

    return data;
}

async function markEntryAsSent(url) {
    if (!url) {
        return null;
    }

    const entry = await postJson(url, {});
    applyEntryState(entry);

    return entry;
}

async function copyText(text) {
    if (navigator.clipboard && window.isSecureContext) {
        await navigator.clipboard.writeText(text);
        return;
    }

    const textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.setAttribute('readonly', 'readonly');
    textarea.style.position = 'fixed';
    textarea.style.left = '-9999px';
    document.body.appendChild(textarea);
    textarea.select();
    document.execCommand('copy');
    textarea.remove();
}

function shareMessage() {
    const name = shareState.name || 'Nhan su';
    const code = shareState.code || '---';

    return [
        'Xin chao ' + name + ',',
        'Ban vui long cap nhat ho so nhan su tai link ben duoi:',
        shareState.link,
        'Ma loi moi: ' + code,
    ].join('\n');
}

function shareAvatarPlaceholder() {
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M2 21a8 8 0 0 1 13.3-6"></path><circle cx="10" cy="8" r="5"></circle><path d="m16 19 2 2 4-4"></path></svg>';
}

function setShareAvatar(target, url) {
    target.innerHTML = '';

    if (!url) {
        target.innerHTML = shareAvatarPlaceholder();
        return;
    }

    const image = document.createElement('img');
    image.alt = '';
    image.onerror = function () {
        image.remove();
        target.innerHTML = shareAvatarPlaceholder();
    };
    image.src = url;
    target.appendChild(image);
}

function openShareSheet(button) {
    const sheet = document.querySelector('[data-share-sheet]');

    if (!sheet) {
        return;
    }

    window.clearTimeout(shareCloseTimer);
    shareState.row = button.closest('[data-entry-row]');
    shareState.name = button.dataset.name || 'Nhan su';
    shareState.code = button.dataset.code || '---';
    shareState.link = button.dataset.link || '';
    shareState.qrUrl = button.dataset.qrUrl || '';
    shareState.avatarUrl = button.dataset.avatarUrl || '';
    shareState.branch = button.dataset.branch || '';
    shareState.role = button.dataset.role || '';
    shareState.markSentUrl = button.dataset.markSentUrl || '';

    sheet.querySelector('[data-sheet-name]').textContent = shareState.name;
    sheet.querySelector('[data-sheet-code]').textContent = 'Ma ' + shareState.code;
    sheet.querySelector('[data-sheet-meta]').textContent = [shareState.branch, shareState.role].filter(Boolean).join(' · ');
    sheet.querySelector('[data-sheet-qr-code]').textContent = shareState.code;

    const linkInput = sheet.querySelector('[data-sheet-link]');
    if (linkInput) {
        linkInput.value = shareState.link;
    }

    const qrImage = sheet.querySelector('[data-sheet-qr]');
    if (qrImage) {
        qrImage.src = shareState.qrUrl;
    }

    const avatar = sheet.querySelector('[data-share-avatar]');
    if (avatar) {
        setShareAvatar(avatar, shareState.avatarUrl);
    }

    sheet.hidden = false;
    sheet.classList.remove('is-closing');
    sheet.classList.remove('is-open');
    document.body.classList.add('kara-quick-modal-open');
    window.requestAnimationFrame(function () {
        sheet.classList.add('is-open');
    });
}

function updateEntryCount() {
    const count = document.querySelectorAll('[data-entry-row]').length;

    document.querySelectorAll('[data-quick-count-label]').forEach(function (label) {
        label.textContent = count + ' loi moi';
    });

    document.querySelectorAll('.demo-counter').forEach(function (label) {
        label.textContent = String(count);
    });
}

async function deleteEntry(button) {
    const url = button.dataset.deleteUrl || '';
    const row = button.closest('[data-entry-row]');

    if (!url || !row || button.disabled) {
        return;
    }

    const confirmed = window.confirm('Xoa loi moi nay?');
    if (!confirmed) {
        return;
    }

    button.disabled = true;

    try {
        const response = await fetch(url, {
            method: 'DELETE',
            cache: 'no-store',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        const data = await response.json().catch(() => ({}));

        if (!response.ok) {
            throw new Error(data.message || 'Khong xoa duoc loi moi.');
        }

        row.remove();
        updateEntryCount();
        toast('Da xoa loi moi.');
    } catch (error) {
        button.disabled = false;
        toast(error.message || 'Khong xoa duoc loi moi.');
    }
}

function closeShareSheet() {
    const sheet = document.querySelector('[data-share-sheet]');

    if (!sheet || sheet.hidden) {
        return;
    }

    window.clearTimeout(shareCloseTimer);
    sheet.classList.remove('is-open');
    sheet.classList.add('is-closing');

    shareCloseTimer = window.setTimeout(function () {
        sheet.hidden = true;
        sheet.classList.remove('is-closing');
        document.body.classList.remove('kara-quick-modal-open');
    }, 240);
}

async function quickShare() {
    if (!shareState.link) {
        toast('Khong co link de chia se.');
        return;
    }

    const message = shareMessage();

    if (navigator.share) {
        try {
            await navigator.share({
                title: 'Quick Onboarding',
                text: message,
                url: shareState.link,
            });
            await markEntryAsSent(shareState.markSentUrl);
            toast('Da mo bang chia se.');
            closeShareSheet();
            return;
        } catch (error) {
            if (error?.name === 'AbortError') {
                return;
            }
        }
    }

    await copyText(message);
    await markEntryAsSent(shareState.markSentUrl);
    toast('Da copy noi dung chia se.');
    closeShareSheet();
}

async function copyShareLink() {
    if (!shareState.link) {
        toast('Khong co link de copy.');
        return;
    }

    await copyText(shareState.link);
    await markEntryAsSent(shareState.markSentUrl);
    toast('Da copy link loi moi.');
}

async function downloadQr() {
    if (!shareState.qrUrl) {
        toast('Khong co QR de tai.');
        return;
    }

    try {
        const response = await fetch(shareState.qrUrl, { cache: 'no-store', credentials: 'same-origin' });

        if (!response.ok) {
            throw new Error('Download failed');
        }

        const blob = await response.blob();
        const objectUrl = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = objectUrl;
        link.download = 'qr-loi-moi-' + (shareState.code || 'invite') + '.svg';
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.setTimeout(function () {
            URL.revokeObjectURL(objectUrl);
        }, 1200);
        await markEntryAsSent(shareState.markSentUrl);
    } catch (error) {
        window.open(shareState.qrUrl, '_blank', 'noopener,noreferrer');
    }
}

async function copyShareMessage() {
    if (!shareState.link) {
        toast('Khong co link de copy.');
        return;
    }

    await copyText(shareMessage());
    await markEntryAsSent(shareState.markSentUrl);
    toast('Da copy noi dung chia se.');
    closeShareSheet();
}

async function saveExpectedName(input, force = false) {
    const url = input.dataset.expectedNameUrl;

    if (!url) {
        return;
    }

    const value = input.value.trim();
    const lastSavedValue = input.dataset.lastSavedValue ?? input.defaultValue.trim();

    if (!force && value === lastSavedValue) {
        return;
    }

    const oldController = autosaveControllers.get(input);
    if (oldController) {
        oldController.abort();
    }

    const controller = new AbortController();
    autosaveControllers.set(input, controller);

    try {
        const entry = await fetch(url, {
            method: 'POST',
            cache: 'no-store',
            credentials: 'same-origin',
            signal: controller.signal,
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ expected_name: value }),
        }).then(async function (response) {
            const data = await response.json().catch(() => ({}));
            if (!response.ok) {
                throw new Error(data.message || 'Khong luu duoc ten.');
            }
            return data;
        });

        input.dataset.lastSavedValue = value;
        applyEntryState(entry);
    } catch (error) {
        if (error.name === 'AbortError') {
            return;
        }

        toast(error.message || 'Khong luu duoc ten.');
    }
}

function setupExpectedName(input) {
    input.dataset.lastSavedValue = input.value.trim();

    input.addEventListener('input', function () {
        window.clearTimeout(autosaveTimers.get(input));
        autosaveTimers.set(input, window.setTimeout(function () {
            saveExpectedName(input);
        }, 700));
    });

    input.addEventListener('blur', function () {
        window.clearTimeout(autosaveTimers.get(input));
        saveExpectedName(input, true);
    });

    input.addEventListener('keydown', function (event) {
        if (event.key !== 'Enter') {
            return;
        }

        event.preventDefault();
        window.clearTimeout(autosaveTimers.get(input));
        saveExpectedName(input, true);
        input.blur();
    });
}

function setupRoleSelect(select) {
    select.addEventListener('change', async function () {
        const previous = select.dataset.originalRole || 'staff';

        try {
            const entry = await postJson(select.dataset.roleUrl || '', { role: select.value });
            select.dataset.originalRole = entry.intended_role || select.value;
            applyEntryState(entry);
            toast('Da cap nhat chuc vu.');
        } catch (error) {
            select.value = previous;
            toast(error.message || 'Khong cap nhat duoc chuc vu.');
        }
    });
}

function setupAvatarInput(input) {
    input.addEventListener('change', async function () {
        /*
         * Chỉ upload sau khi người dùng đã bấm
         * “Xác nhận ảnh” trong Cropper.
         */
        if (input.dataset.avatarCropReady !== '1') {
            return;
        }

        delete input.dataset.avatarCropReady;

        const file = input.files?.[0];

        if (!file || !input.dataset.avatarUrl) {
            return;
        }

        const row = input.closest('[data-entry-row]');
        const wrap = row?.querySelector(
            '[data-avatar-wrap]',
        );
        const croppedPreview = row?.querySelector(
            '[data-avatar-preview]',
        );

        if (!row) {
            return;
        }

        /*
         * Bảo đảm ảnh crop hiện ngay trên thẻ
         * trong lúc đang gửi lên server.
         */
        croppedPreview?.classList.remove('hidden');
        wrap?.classList.remove('is-avatar-missing');

        const formData = new FormData();

        formData.append('avatar', file);

        try {
            const response = await fetch(
                input.dataset.avatarUrl,
                {
                    method: 'POST',
                    cache: 'no-store',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: formData,
                },
            );

            const data = await response
                .json()
                .catch(() => ({}));

            if (!response.ok) {
                throw new Error(
                    data.message ||
                    'Không lưu được ảnh.',
                );
            }

            /*
             * applyEntryState gọi setAvatar().
             * setAvatar mới sẽ chỉ ẩn preview sau khi
             * ảnh server đã load thành công.
             */
            applyEntryState(data);

            toast('Đã cập nhật ảnh.');
        } catch (error) {
            /*
             * Upload lỗi thì giữ ảnh crop đang hiện,
             * đồng thời báo lỗi cho người dùng.
             */
            croppedPreview?.classList.remove('hidden');
            wrap?.classList.remove('is-avatar-missing');

            toast(
                error.message ||
                'Không lưu được ảnh.',
            );
        } finally {
            input.value = '';
        }
    });
}

document.querySelectorAll('[data-expected-name-input]').forEach(setupExpectedName);
document.querySelectorAll('[data-role-select]').forEach(setupRoleSelect);
document.querySelectorAll('[data-avatar-input]').forEach(setupAvatarInput);

document.querySelectorAll('[data-share-open]').forEach(function (button) {
    button.addEventListener('click', function () {
        const row = button.closest('[data-entry-row]');
        const input = row?.querySelector('[data-expected-name-input]');
        const name = row?.querySelector('[data-employee-name]')?.textContent?.trim()
            || input?.value?.trim()
            || button.dataset.name
            || 'Nhan su';

        button.dataset.name = name;
        openShareSheet(button);
    });
});

document.querySelectorAll('[data-entry-delete]').forEach(function (button) {
    button.addEventListener('click', function () {
        deleteEntry(button);
    });
});

document.querySelectorAll('[data-share-close]').forEach(function (button) {
    button.addEventListener('click', closeShareSheet);
});
document.querySelector('[data-share-quick]')?.addEventListener('click', function () {
    quickShare().catch(function (error) {
        toast(error.message || 'Khong chia se duoc.');
    });
});
document.querySelector('[data-share-copy-message]')?.addEventListener('click', function () {
    copyShareMessage().catch(function (error) {
        toast(error.message || 'Khong copy duoc.');
    });
});
document.querySelector('[data-share-copy-link]')?.addEventListener('click', function () {
    copyShareLink().catch(function (error) {
        toast(error.message || 'Khong copy duoc link.');
    });
});
document.querySelector('[data-share-download-qr]')?.addEventListener('click', function () {
    downloadQr().catch(function (error) {
        toast(error.message || 'Khong tai duoc QR.');
    });
});

const shareRoot = document.querySelector('[data-quick-onboarding-share-root]');
let syncTimer = null;
let syncLoading = false;

async function syncEntries() {
    if (!shareRoot || !shareRoot.dataset.syncUrl || syncLoading || document.hidden) {
        return;
    }

    syncLoading = true;

    try {
        const response = await fetch(shareRoot.dataset.syncUrl + '?t=' + Date.now(), {
            cache: 'no-store',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            return;
        }

        const data = await response.json();
        (data.entries || []).forEach(applyEntryState);
    } catch (error) {
        // Sync is best-effort and must not disturb current editing.
    } finally {
        syncLoading = false;
    }
}

function startSync() {
    if (syncTimer || !shareRoot) {
        return;
    }

    syncTimer = window.setInterval(syncEntries, 10000);
}

function stopSync() {
    if (!syncTimer) {
        return;
    }

    window.clearInterval(syncTimer);
    syncTimer = null;
}

if (shareRoot) {
    startSync();

    document.addEventListener('visibilitychange', function () {
        if (document.hidden) {
            stopSync();
            return;
        }

        syncEntries();
        startSync();
    });
}

document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
        closeShareSheet();
    }
});
