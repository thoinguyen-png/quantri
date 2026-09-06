<x-app-layout>
    <div class="home-page home-page--employee">
        <section class="home-brand-bar" aria-label="Maxsim">
            <img src="{{ asset('icons/logoMaxSim.png') }}" alt="Maxsim">
        </section>

        <section class="home-topline">
            <div>
                Đánh giá của <strong>{{ $user->name }}</strong>
            </div>
            <a href="{{ route('dashboard') }}" class="home-profile-button" aria-label="Về trang chủ">
                <span>←</span>
            </a>
        </section>

        @include('dashboard.partials.staff-rating-dashboard')

        <section class="my-rating-history" aria-label="Lịch sử đánh giá cá nhân">
            <div class="my-rating-history__head">
                <div>
                    <h2>Lịch sử đánh giá</h2>
                    <p>Toàn bộ đánh giá của bạn, mới nhất trước.</p>
                </div>
            </div>

            <div class="my-rating-history__list">
                @forelse ($ratingDashboard['rating_history'] as $rating)
                    <article class="my-rating-history__item my-rating-history__item--{{ $rating->rating }}">
                        <div>
                            <strong>{{ match ($rating->rating) { 'bad' => 'Tệ', 'average' => 'Trung bình', 'good' => 'Tốt', default => 'Đánh giá' } }}</strong>
                            <p>{{ $rating->comment ?: 'Không có lời nhắn.' }}</p>
                        </div>
                        <time>{{ $rating->submitted_at?->format('H:i d/m/Y') }}</time>
                    </article>
                @empty
                    <div class="my-rating-history__empty">Chưa có đánh giá nào trong tháng này.</div>
                @endforelse
            </div>

            <div class="my-rating-history__pagination">
                {{ $ratingDashboard['rating_history']->links() }}
            </div>
        </section>

        @include('dashboard.partials.staff-rating-modal')
    </div>

    <script>
        (function () {
            const modal = document.querySelector('[data-staff-rating-modal]');

            if (!modal) {
                return;
            }

            const icon = modal.querySelector('[data-staff-rating-icon]');
            const title = modal.querySelector('[data-staff-rating-title]');
            const time = modal.querySelector('[data-staff-rating-time]');
            const reward = modal.querySelector('[data-staff-rating-reward]');
            const label = modal.querySelector('[data-staff-rating-label]');
            const comment = modal.querySelector('[data-staff-rating-comment]');

            function openRatingModal(payload) {
                icon.textContent = payload.rating_label ? payload.rating_label.substring(0, 1) : '•';
                icon.dataset.tone = payload.rating_tone || 'neutral';
                title.textContent = 'Đánh giá ' + (payload.rating_label || '');
                time.textContent = payload.submitted_at || '--';
                reward.textContent = payload.reward_status_label || 'Không áp dụng';
                label.textContent = payload.rating_label || '--';
                comment.textContent = payload.comment || 'Không có lời nhắn.';
                modal.hidden = false;
            }

            function closeRatingModal() {
                modal.hidden = true;
            }

            document.querySelectorAll('[data-staff-rating-open]').forEach(function (button) {
                button.addEventListener('click', function () {
                    try {
                        openRatingModal(JSON.parse(button.dataset.rating || '{}'));
                    } catch (error) {
                        openRatingModal({});
                    }
                });
            });

            modal.querySelectorAll('[data-staff-rating-close]').forEach(function (button) {
                button.addEventListener('click', closeRatingModal);
            });
        })();
    </script>
</x-app-layout>
