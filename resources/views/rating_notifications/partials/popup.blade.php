@auth
    @if (in_array(auth()->user()->role, ['staff', 'cashier', 'manager', 'admin'], true))
        <div
            data-rating-notifications
            data-rating-notifications-url="{{ route('rating-notifications.recent', absolute: false) }}"
            data-rating-polling-ms="30000"
        ></div>

        <div class="rating-toast-region" data-rating-toast-region aria-live="polite" aria-atomic="false"></div>

        <div class="rating-notification-modal" data-rating-notification-modal hidden role="dialog" aria-modal="true" aria-labelledby="rating-notification-title">
            <div class="rating-notification-modal__backdrop" data-rating-modal-close></div>
            <section class="rating-notification-modal__panel">
                <div class="rating-notification-modal__head">
                    <div class="rating-notification-modal__icon" data-rating-modal-icon>•</div>
                    <div>
                        <h2 id="rating-notification-title" data-rating-modal-title>Đánh giá mới</h2>
                        <p data-rating-modal-subtitle>MAXSIM</p>
                    </div>
                </div>

                <div class="rating-notification-grid">
                    <div class="rating-notification-cell">
                        <span>Nhân sự</span>
                        <b data-rating-modal-employee>-</b>
                    </div>
                    <div class="rating-notification-cell">
                        <span>Đánh giá</span>
                        <b data-rating-modal-rating>-</b>
                    </div>
                    <div class="rating-notification-cell">
                        <span>Thời gian</span>
                        <b data-rating-modal-time>-</b>
                    </div>
                    <div class="rating-notification-cell">
                        <span>Trạng thái thưởng</span>
                        <b data-rating-modal-reward>-</b>
                    </div>
                </div>

                <div class="rating-notification-quote" data-rating-modal-comment>Không có lời nhắn.</div>

                <div class="rating-notification-actions">
                    <button type="button" data-rating-modal-close>Đã hiểu</button>
                </div>
            </section>
        </div>
    @endif
@endauth
