<section class="manager-rating-dashboard" aria-label="Thống kê đánh giá chi nhánh">
    <div class="manager-rating-head">
        <div>
            <span>Quản lý chi nhánh · {{ $managerRatingDashboard['month_label'] }}</span>
            <h1>Phản hồi khách hàng trong chi nhánh</h1>
        </div>
        <div class="manager-rating-head__stats">
            <strong>{{ $managerRatingDashboard['counts']['good'] }}</strong><small>Tốt</small>
            <strong>{{ $managerRatingDashboard['counts']['average'] }}</strong><small>Trung bình</small>
            <strong>{{ $managerRatingDashboard['counts']['bad'] }}</strong><small>Tệ</small>
        </div>
    </div>

    <div class="manager-rating-priority">
        <section class="manager-rating-panel">
            <div class="manager-rating-title">
                <div>
                    <h2>Đánh giá mới</h2>
                    <p>10 phản hồi mới nhất trong chi nhánh</p>
                </div>
            </div>
            <div class="manager-rating-feed">
                @forelse ($managerRatingDashboard['latest'] as $rating)
                    <button type="button" class="manager-rating-feedback manager-rating-feedback--{{ $rating['rating_tone'] }}" data-manager-feedback='@json($rating)'>
                        <span>{{ mb_substr($rating['rating_label'], 0, 1) }}</span>
                        <div>
                            <b>{{ $rating['employee_name'] }} · {{ $rating['rating_label'] }}</b>
                            <p>{{ $rating['comment'] ?: 'Không có lời nhắn.' }}</p>
                        </div>
                        <time>{{ $rating['submitted_at'] }}</time>
                    </button>
                @empty
                    <div class="manager-rating-empty">Chưa có đánh giá mới trong tháng này.</div>
                @endforelse
            </div>
        </section>

        <section class="manager-rating-panel manager-rating-panel--alert">
            <div class="manager-rating-title">
                <div>
                    <h2>Phản hồi Tệ cần xử lý</h2>
                    <p>Ưu tiên xem trước các phản hồi rủi ro</p>
                </div>
            </div>
            <button type="button" class="manager-rating-alert" data-manager-list-open>
                <strong>{{ $managerRatingDashboard['bad_feedback']->count() }}</strong>
                <span>Mở danh sách phản hồi Tệ</span>
            </button>
            <div class="manager-rating-feed manager-rating-feed--compact">
                @forelse ($managerRatingDashboard['bad_feedback']->take(3) as $rating)
                    <button type="button" class="manager-rating-feedback manager-rating-feedback--bad" data-manager-feedback='@json($rating)'>
                        <span>{{ mb_substr($rating['rating_label'], 0, 1) }}</span>
                        <div>
                            <b>{{ $rating['employee_name'] }}</b>
                            <p>{{ $rating['comment'] ?: 'Không có lời nhắn.' }}</p>
                        </div>
                    </button>
                @empty
                    <div class="manager-rating-empty">Không có phản hồi Tệ trong tháng này.</div>
                @endforelse
            </div>
        </section>
    </div>

    <div class="manager-rating-grid">
        <section class="manager-rating-panel manager-rating-panel--wide">
            <div class="manager-rating-title">
                <div>
                    <h2>Hiệu suất nhân sự</h2>
                    <p>Tốt / Trung bình / Tệ theo từng người</p>
                </div>
            </div>
            <div class="manager-performance-list">
                @forelse ($managerRatingDashboard['performance'] as $employee)
                    <div class="manager-performance-row">
                        <div>
                            <b>{{ $employee['name'] }}</b>
                            <small>{{ $employee['role'] }} · {{ $employee['total'] }} đánh giá</small>
                        </div>
                        <div class="manager-rating-pills">
                            <span class="is-good">{{ $employee['good'] }} Tốt</span>
                            <span class="is-average">{{ $employee['average'] }} TB</span>
                            <span class="is-bad">{{ $employee['bad'] }} Tệ</span>
                        </div>
                    </div>
                @empty
                    <div class="manager-rating-empty">Chưa có nhân sự trong phạm vi chi nhánh.</div>
                @endforelse
            </div>
        </section>

        <section class="manager-rating-panel">
            <div class="manager-rating-title">
                <div>
                    <h2>Trạng thái thưởng</h2>
                    <p>Reward liên quan nhân sự trong chi nhánh</p>
                </div>
            </div>
            <div class="manager-reward-list">
                @forelse ($managerRatingDashboard['reward_history'] as $reward)
                    <div class="manager-reward-row">
                        <div>
                            <b>{{ $reward['employee_name'] }}</b>
                            <span>{{ $reward['business_date'] }} · {{ $reward['status_label'] }}</span>
                        </div>
                        <strong>{{ $reward['amount_label'] }}</strong>
                    </div>
                @empty
                    <div class="manager-rating-empty">Chưa có reward trong tháng này.</div>
                @endforelse
            </div>
        </section>

        <section class="manager-rating-panel">
            <div class="manager-rating-title">
                <div>
                    <h2>QR đánh giá nhân sự</h2>
                    <p>Chỉ nhân sự thuộc chi nhánh của bạn</p>
                </div>
            </div>
            <div class="manager-qr-list">
                @forelse ($managerRatingDashboard['qr_users'] as $qrUser)
                    <div class="manager-qr-row">
                        <div>
                            <b>{{ $qrUser['name'] }}</b>
                            <span>{{ $qrUser['role'] }}</span>
                        </div>
                        <div>
                            <a href="{{ $qrUser['show_url'] }}" data-manager-qr-preview>Xem</a>
                            <a href="{{ $qrUser['download_url'] }}">Tải</a>
                        </div>
                    </div>
                @empty
                    <div class="manager-rating-empty">Chưa có QR đánh giá đang bật trong chi nhánh.</div>
                @endforelse
            </div>
        </section>
    </div>

    <div class="manager-rating-modal" data-manager-rating-modal hidden>
        <button type="button" class="manager-rating-modal__backdrop" data-manager-modal-close aria-label="Đóng"></button>
        <section class="manager-rating-modal__panel" role="dialog" aria-modal="true">
            <div data-manager-list-view>
                <div class="manager-rating-modal__head">
                    <div>
                        <span>Danh sách phản hồi</span>
                        <h2>Phản hồi Tệ cần xử lý</h2>
                    </div>
                    <button type="button" data-manager-modal-close>Đóng</button>
                </div>
                <div class="manager-rating-feed">
                    @forelse ($managerRatingDashboard['bad_feedback'] as $rating)
                        <button type="button" class="manager-rating-feedback manager-rating-feedback--bad" data-manager-feedback='@json($rating)'>
                            <span>{{ mb_substr($rating['rating_label'], 0, 1) }}</span>
                            <div>
                                <b>{{ $rating['employee_name'] }}</b>
                                <p>{{ $rating['comment'] ?: 'Không có lời nhắn.' }}</p>
                            </div>
                            <time>{{ $rating['submitted_at'] }}</time>
                        </button>
                    @empty
                        <div class="manager-rating-empty">Không có phản hồi Tệ trong tháng này.</div>
                    @endforelse
                </div>
            </div>

            <div data-manager-detail-view hidden>
                <div class="manager-rating-modal__head">
                    <div>
                        <span>Chi tiết phản hồi</span>
                        <h2 data-manager-detail-title>--</h2>
                    </div>
                    <button type="button" data-manager-list-back>Danh sách</button>
                </div>
                <div class="manager-rating-detail">
                    <div><span>Nhân sự</span><strong data-manager-detail-employee>--</strong></div>
                    <div><span>Đánh giá</span><strong data-manager-detail-rating>--</strong></div>
                    <div><span>Thời gian</span><strong data-manager-detail-time>--</strong></div>
                    <div><span>Trạng thái thưởng</span><strong data-manager-detail-reward>--</strong></div>
                </div>
                <blockquote data-manager-detail-comment>--</blockquote>
                <button type="button" class="manager-rating-modal__primary" data-manager-modal-close>Đóng</button>
            </div>
        </section>
    </div>
</section>
