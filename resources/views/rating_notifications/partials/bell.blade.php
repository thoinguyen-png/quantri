@auth
    @if (in_array(auth()->user()->role, ['staff', 'cashier', 'manager', 'admin'], true))
        <div
            class="rating-bell"
            data-rating-feed
            data-rating-feed-url="{{ route('ratings.feed.recent', absolute: false) }}"
            data-rating-feed-all-url="{{ route('ratings.feed.index', absolute: false) }}"
        >
            <button class="rating-bell__button" type="button" data-rating-feed-toggle aria-label="Thông báo đánh giá" aria-expanded="false">
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none">
                    <path d="M15 17H9M18 10.5C18 7.19 15.31 4.5 12 4.5S6 7.19 6 10.5C6 13.43 5 15 4 16H20C19 15 18 13.43 18 10.5Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M13.5 19C13.1 19.6 12.55 20 12 20S10.9 19.6 10.5 19" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                </svg>
            </button>

            <div class="rating-bell__dropdown" data-rating-feed-dropdown hidden>
                <div class="rating-bell__head">
                    <strong>Đánh giá mới</strong>
                    <a href="{{ route('ratings.feed.index') }}">Xem tất cả</a>
                </div>
                <div class="rating-bell__list" data-rating-feed-list>
                    <div class="rating-bell__empty">Chưa có đánh giá mới.</div>
                </div>
            </div>
        </div>
    @endif
@endauth
