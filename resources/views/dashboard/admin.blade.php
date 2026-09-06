<x-app-layout>
    @php
        $toneIcons = [
            'purple' => '👥',
            'green' => '✓',
            'red' => '✕',
            'yellow' => '◔',
            'indigo' => '☂',
            'orange' => '▤',
        ];
    @endphp

    <div class="home-page home-page--admin">
        <section class="home-brand-bar" aria-label="Maxsim">
            <img src="{{ asset('icons/logoMaxSim.png') }}" alt="Maxsim">
        </section>

        <section class="home-topline">
            <div>
                Xin chao, <strong>{{ auth()->user()->name }}</strong>
            </div>
            <a href="{{ route('users.me') }}" class="home-profile-button" aria-label="Tài khoản">
                <span>{{ strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span>
            </a>
        </section>

        <section class="admin-section">
            <h1>Tổng quan hôm nay</h1>

            <div class="admin-summary-grid">
                @foreach ($summary as $item)
                    <article class="admin-summary-card admin-summary-card--{{ $item['tone'] }}">
                        <div class="admin-summary-card__icon">{{ $toneIcons[$item['tone']] ?? '•' }}</div>
                        <div>
                            <span>{{ $item['label'] }}</span>
                            <strong>{{ $item['value'] }}</strong>
                        </div>
                        @if ($item['meta'] !== '')
                            <small>{{ $item['meta'] }}</small>
                        @endif
                    </article>
                @endforeach
            </div>
        </section>

        @include('dashboard.partials.admin-rating-dashboard', ['ratingDashboard' => $ratingDashboard])

        <section class="admin-panel">
            <div class="admin-panel__head">
                <h2>Đơn chờ duyệt</h2>
            </div>

            <div class="admin-list">
                @foreach ($pending as $item)
                    <a href="{{ route($item['route']) }}" class="admin-list-row">
                        <span>{{ $item['label'] }}</span>
                        <strong>{{ $item['value'] }}</strong>
                    </a>
                @endforeach
            </div>

            <a href="{{ route('attendance-supplements.index') }}" class="admin-panel__more">Xem tất cả</a>
        </section>

        <section class="admin-panel">
            <div class="admin-panel__head">
                <h2>Cảnh báo</h2>
            </div>

            <div class="admin-list">
                @foreach ($alerts as $alert)
                    <a href="{{ route($alert['route']) }}" class="admin-list-row admin-list-row--warning">
                        <span>{{ $alert['label'] }}</span>
                        <strong>{{ $alert['value'] }}</strong>
                    </a>
                @endforeach
            </div>

            <a href="{{ route('attendance-reports.index') }}" class="admin-panel__more">Xem tất cả cảnh báo</a>
        </section>

        <section class="admin-panel">
            <div class="admin-panel__head">
                <h2>Truy cập nhanh</h2>
            </div>

            <div class="admin-quick-grid">
                @foreach ($quickLinks as $link)
                    <a href="{{ route($link['route']) }}" class="admin-quick-link">
                        <span>{{ $link['label'] }}</span>
                    </a>
                @endforeach
            </div>
        </section>
    </div>
    @include('rating_qrs.partials.confirm-modal')

    <script>
        (function () {
            const modal = document.querySelector('[data-admin-rating-modal]');

            if (!modal) {
                return;
            }

            const listView = modal.querySelector('[data-admin-list-view]');
            const detailView = modal.querySelector('[data-admin-detail-view]');
            const fields = {
                title: modal.querySelector('[data-admin-detail-title]'),
                employee: modal.querySelector('[data-admin-detail-employee]'),
                branch: modal.querySelector('[data-admin-detail-branch]'),
                rating: modal.querySelector('[data-admin-detail-rating]'),
                time: modal.querySelector('[data-admin-detail-time]'),
                reward: modal.querySelector('[data-admin-detail-reward]'),
                comment: modal.querySelector('[data-admin-detail-comment]'),
            };

            function openList() {
                listView.hidden = false;
                detailView.hidden = true;
                modal.hidden = false;
            }

            function openDetail(payload) {
                listView.hidden = true;
                detailView.hidden = false;
                fields.title.textContent = payload.rating_label || 'Phản hồi';
                fields.employee.textContent = payload.employee_name || '--';
                fields.branch.textContent = payload.branch_name || '--';
                fields.rating.textContent = payload.rating_label || '--';
                fields.time.textContent = payload.submitted_at || '--';
                fields.reward.textContent = payload.reward_status_label || 'Không áp dụng';
                fields.comment.textContent = payload.comment || 'Không có lời nhắn.';
                modal.hidden = false;
            }

            function closeModal() {
                modal.hidden = true;
            }

            document.querySelectorAll('[data-admin-list-open]').forEach(function (button) {
                button.addEventListener('click', openList);
            });

            document.querySelectorAll('[data-admin-feedback]').forEach(function (button) {
                button.addEventListener('click', function () {
                    try {
                        openDetail(JSON.parse(button.dataset.adminFeedback || '{}'));
                    } catch (error) {
                        openDetail({});
                    }
                });
            });

            modal.querySelectorAll('[data-admin-modal-close]').forEach(function (button) {
                button.addEventListener('click', closeModal);
            });

            modal.querySelectorAll('[data-admin-list-back]').forEach(function (button) {
                button.addEventListener('click', openList);
            });
        })();
    </script>
</x-app-layout>
