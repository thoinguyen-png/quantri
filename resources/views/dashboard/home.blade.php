<x-app-layout>
    <div class="home-page home-page--rating" data-home-rating-dashboard data-summary-url="{{ $summaryEndpoint }}">
        <section class="home-brand-bar" aria-label="Maxsim">
            <img src="{{ asset('icons/logoMaxSim.png') }}" alt="Maxsim">
        </section>

        <section class="home-rating-shell">
            <article class="home-rating-panel home-rating-panel--personal">
                <header class="home-rating-panel__head">
                    <div>
                        <h1><?php echo e($user->name); ?></h1>
                        <p>Các đánh giá gần đây từ khách hàng</p>
                    </div>
                    @if (in_array($user->role, ['staff', 'cashier', 'manager'], true))
                        <a href="{{ route('my-ratings.index') }}">XEM TẤT CẢ</a>
                    @endif
                </header>

                <div class="home-rating-stats" aria-label="Tổng đánh giá cá nhân">
                    <div class="home-rating-stat home-rating-stat--good">
                        <span>Tốt</span>
                        <strong data-home-personal-count="good">0</strong>
                    </div>
                    <div class="home-rating-stat home-rating-stat--average">
                        <span>Trung bình</span>
                        <strong data-home-personal-count="average">0</strong>
                    </div>
                    <div class="home-rating-stat home-rating-stat--bad">
                        <span>Tệ</span>
                        <strong data-home-personal-count="bad">0</strong>
                    </div>
                </div>

                <div class="home-rating-list" data-home-personal-list>
                    <div class="home-rating-empty">Đang tải đánh giá...</div>
                </div>
            </article>

            <article class="home-rating-panel home-rating-panel--branch">
                <header class="home-rating-panel__head">
                    <div>
                        <h2>Trang tin</h2>
                        <p>Các đánh giá mới nhất của nhân sự</p>
                    </div>
                    <a href="{{ route('ratings.feed.index') }}">XEM TẤT CẢ</a>
                </header>

                <div class="home-rating-list home-rating-list--branch" data-home-branch-list>
                    <div class="home-rating-empty">Đang tải đánh giá...</div>
                </div>
            </article>
        </section>
    </div>

    <script>
        (function () {
            const root = document.querySelector('[data-home-rating-dashboard]');

            if (!root || root.dataset.homeRatingStarted === '1') {
                return;
            }

            root.dataset.homeRatingStarted = '1';

            const endpoint = root.dataset.summaryUrl;
            const personalList = root.querySelector('[data-home-personal-list]');
            const branchList = root.querySelector('[data-home-branch-list]');
            const countNodes = {
                good: root.querySelector('[data-home-personal-count="good"]'),
                average: root.querySelector('[data-home-personal-count="average"]'),
                bad: root.querySelector('[data-home-personal-count="bad"]'),
            };
            let timer = null;
            let lastSignature = '';

            function escapeHtml(value) {
                return String(value || '').replace(/[&<>"']/g, function (char) {
                    return {
                        '&': '&amp;',
                        '<': '&lt;',
                        '>': '&gt;',
                        '"': '&quot;',
                        "'": '&#039;',
                    }[char];
                });
            }

            function truncate(value, maxLength) {
                const text = String(value || 'Không có lời nhắn.').trim();

                if (text.length <= maxLength) {
                    return text;
                }

                return text.slice(0, maxLength - 1).trimEnd() + '…';
            }

            function itemTemplate(item) {
                const tone = ['good', 'average', 'bad'].includes(item.rating_tone) ? item.rating_tone : 'neutral';

                return `
                    <article class="home-rating-row home-rating-row--${tone}">
                        <span class="home-rating-row__avatar">${escapeHtml(item.employee_initial || 'N')}</span>
                        <div class="home-rating-row__body">
                            <div class="home-rating-row__top">
                                <strong>${escapeHtml(item.employee_name || 'Nhân sự')}</strong>
                                <span class="home-rating-badge home-rating-badge--${tone}">${escapeHtml(item.rating_label || 'Đánh giá')}</span>
                            </div>
                            <p>${escapeHtml(truncate(item.comment, 92))}</p>
                            <small>${escapeHtml(item.submitted_at_label || '--')} · ${escapeHtml(item.reward_status_label || 'Không áp dụng')}</small>
                        </div>
                    </article>
                `;
            }

            function renderList(target, items, emptyText) {
                if (!target) {
                    return;
                }

                if (!Array.isArray(items) || items.length === 0) {
                    target.innerHTML = `<div class="home-rating-empty">${escapeHtml(emptyText)}</div>`;
                    return;
                }

                target.innerHTML = items.map(itemTemplate).join('');
            }

            function render(data) {
                const personal = data.personal || {};
                const counts = personal.counts || {};

                Object.keys(countNodes).forEach(function (key) {
                    if (countNodes[key]) {
                        countNodes[key].textContent = Number(counts[key] || 0).toLocaleString('vi-VN');
                    }
                });

                renderList(
                    personalList,
                    personal.items || [],
                    personal.can_receive_ratings === false
                        ? 'Tài khoản này chưa bật QR đánh giá cá nhân.'
                        : 'Chưa có đánh giá nào.'
                );
                renderList(branchList, data.branch ? data.branch.items : [], 'Chưa có đánh giá trong phạm vi của bạn.');
            }

            async function loadSummary() {
                if (!endpoint) {
                    return;
                }

                try {
                    const response = await fetch(endpoint + '?t=' + Date.now(), {
                        cache: 'no-store',
                        credentials: 'same-origin',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    });

                    if (!response.ok) {
                        return;
                    }

                    const data = await response.json();
                    const signature = JSON.stringify(data);

                    if (signature !== lastSignature) {
                        lastSignature = signature;
                        render(data);
                    }
                } catch (error) {
                    renderList(personalList, [], 'Chưa tải được dữ liệu đánh giá.');
                    renderList(branchList, [], 'Chưa tải được dữ liệu đánh giá.');
                }
            }

            function startPolling() {
                if (timer || document.hidden) {
                    return;
                }

                loadSummary();
                timer = window.setInterval(loadSummary, 10000);
            }

            function stopPolling() {
                if (timer) {
                    window.clearInterval(timer);
                    timer = null;
                }
            }

            document.addEventListener('visibilitychange', function () {
                if (document.hidden) {
                    stopPolling();
                    return;
                }

                loadSummary();
                startPolling();
            });

            startPolling();
        })();
    </script>
</x-app-layout>
