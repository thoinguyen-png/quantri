const root = document.querySelector('[data-rating-notifications]');
const toastRegion = document.querySelector('[data-rating-toast-region]');
const modal = document.querySelector('[data-rating-notification-modal]');
const feedRoot = document.querySelector('[data-rating-feed]');
const dismissed = new Set(JSON.parse(window.sessionStorage.getItem('ratingNotificationDismissed') || '[]'));
let currentNotifications = new Map();
let loading = false;
let pollingTimer = null;
const toastTimers = new Map();

function saveDismissed() {
    window.sessionStorage.setItem('ratingNotificationDismissed', JSON.stringify([...dismissed].slice(-50)));
}

function ratingIcon(rating) {
    if (rating === 'bad') {
        return '!';
    }

    if (rating === 'good') {
        return '✓';
    }

    return '•';
}

function text(value, fallback = '-') {
    return value && String(value).trim() !== '' ? value : fallback;
}

function openModal(notification) {
    if (!modal) {
        return;
    }

    modal.querySelector('[data-rating-modal-icon]').textContent = ratingIcon(notification.rating);
    modal.querySelector('[data-rating-modal-icon]').classList.toggle('is-high', notification.priority === 'high');
    modal.querySelector('[data-rating-modal-title]').textContent = `${notification.rating_label} · ${notification.employee_name}`;
    modal.querySelector('[data-rating-modal-subtitle]').textContent = 'MAXSIM';
    modal.querySelector('[data-rating-modal-employee]').textContent = notification.employee_name;
    modal.querySelector('[data-rating-modal-rating]').textContent = notification.rating_label;
    modal.querySelector('[data-rating-modal-time]').textContent = text(notification.submitted_at_label);
    modal.querySelector('[data-rating-modal-reward]').textContent = notification.reward_status_label;
    modal.querySelector('[data-rating-modal-comment]').textContent = text(notification.comment, 'Khong co loi nhan.');
    modal.hidden = false;

    markRead(notification);
}

function closeModal() {
    if (modal) {
        modal.hidden = true;
    }
}

function removeToast(notificationId) {
    const timer = toastTimers.get(notificationId);

    if (timer) {
        window.clearTimeout(timer);
        toastTimers.delete(notificationId);
    }

    document.querySelector(`[data-rating-toast-id="${notificationId}"]`)?.remove();
    dismissed.add(notificationId);
    saveDismissed();
}

function renderToast(notification) {
    if (!toastRegion || dismissed.has(notification.id) || document.querySelector(`[data-rating-toast-id="${notification.id}"]`)) {
        return;
    }

    const toast = document.createElement('div');
    toast.className = `rating-toast ${notification.priority === 'high' ? 'is-high' : ''}`;
    toast.dataset.ratingToastId = notification.id;
    toast.innerHTML = `
        <div class="rating-toast__icon">${ratingIcon(notification.rating)}</div>
        <button type="button" class="rating-toast__main">
            <b></b>
            <span></span>
        </button>
        <button type="button" class="rating-toast__close" aria-label="Close">×</button>
        <div class="rating-toast__progress" aria-hidden="true"><i></i></div>
    `;

    toast.querySelector('b').textContent = `${notification.rating_label} · ${notification.employee_name}`;
    toast.querySelector('span').textContent = text(notification.comment, notification.reward_status_label);
    toast.querySelector('.rating-toast__main').addEventListener('click', () => openModal(notification));
    toast.querySelector('.rating-toast__close').addEventListener('click', () => removeToast(notification.id));

    toastRegion.prepend(toast);
    dismissed.add(notification.id);
    saveDismissed();
    toastTimers.set(notification.id, window.setTimeout(() => removeToast(notification.id), 5000));
}

async function markRead(notification) {
    try {
        await fetch(notification.read_url, {
            method: 'PATCH',
            cache: 'no-store',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
    } catch (error) {
        return;
    }

    removeToast(notification.id);
}

async function pollNotifications() {
    if (!root || loading || document.hidden) {
        return;
    }

    loading = true;

    try {
        const response = await fetch(`${root.dataset.ratingNotificationsUrl}?t=${Date.now()}`, {
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
        currentNotifications = new Map((data.notifications || []).map((item) => [item.id, item]));
        currentNotifications.forEach((notification) => renderToast(notification));
    } finally {
        loading = false;
    }
}

function startNotificationPolling() {
    if (!root || pollingTimer || document.hidden) {
        return;
    }

    pollNotifications();
    pollingTimer = window.setInterval(pollNotifications, Number(root.dataset.ratingPollingMs || 30000));
}

function stopNotificationPolling() {
    if (!pollingTimer) {
        return;
    }

    window.clearInterval(pollingTimer);
    pollingTimer = null;
}

modal?.querySelectorAll('[data-rating-modal-close]').forEach((button) => {
    button.addEventListener('click', closeModal);
});

if (root) {
    startNotificationPolling();

    window.addEventListener('focus', () => {
        if (!document.hidden) {
            pollNotifications();
            startNotificationPolling();
        }
    });

    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            stopNotificationPolling();
            return;
        }

        pollNotifications();
        startNotificationPolling();
    });

    window.addEventListener('pagehide', () => {
        stopNotificationPolling();
        toastTimers.forEach((timer) => window.clearTimeout(timer));
        toastTimers.clear();
    });
}

if (feedRoot) {
    const toggle = feedRoot.querySelector('[data-rating-feed-toggle]');
    const dropdown = feedRoot.querySelector('[data-rating-feed-dropdown]');
    const list = feedRoot.querySelector('[data-rating-feed-list]');
    let feedLoaded = false;

    function closeFeed() {
        if (dropdown) {
            dropdown.hidden = true;
            toggle?.setAttribute('aria-expanded', 'false');
        }
    }

    function shortText(value) {
        const content = text(value, 'Khong co loi nhan.');
        return content.length > 78 ? `${content.substring(0, 75)}...` : content;
    }

    function renderFeed(ratings) {
        if (!list) {
            return;
        }

        if (!ratings.length) {
            list.innerHTML = '<div class="rating-bell__empty">Chua co danh gia moi.</div>';
            return;
        }

        list.innerHTML = ratings.map((rating) => `
            <a class="rating-bell__item rating-bell__item--${rating.rating}" href="${feedRoot.dataset.ratingFeedAllUrl}">
                <span class="rating-bell__avatar">${text(rating.employee_initial, 'N')}</span>
                <span class="rating-bell__body">
                    <b>${text(rating.employee_name, 'Nhan su')}</b>
                    <small>${text(rating.rating_label, 'Danh gia')} · ${shortText(rating.comment)}</small>
                    <time>${text(rating.submitted_at_label, '--')}</time>
                </span>
            </a>
        `).join('');
    }

    async function loadFeed() {
        const response = await fetch(`${feedRoot.dataset.ratingFeedUrl}?t=${Date.now()}`, {
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
        renderFeed(data.ratings || []);
        feedLoaded = true;
    }

    toggle?.addEventListener('click', async (event) => {
        event.stopPropagation();
        const willOpen = dropdown?.hidden;

        if (willOpen && !feedLoaded) {
            await loadFeed();
        }

        if (dropdown) {
            dropdown.hidden = !willOpen;
            toggle.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
        }
    });

    document.addEventListener('click', (event) => {
        if (!feedRoot.contains(event.target)) {
            closeFeed();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeFeed();
        }
    });
}
