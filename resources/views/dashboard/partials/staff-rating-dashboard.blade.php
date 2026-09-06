<section class="staff-rating-dashboard" aria-label="Thống kê đánh giá cá nhân">
    <div class="staff-rating-hero">
        <div class="staff-rating-hero__copy">
            <span class="staff-rating-eyebrow">Dữ liệu cá nhân · {{ $ratingDashboard['month_label'] }}</span>
            <h1>Phân tích đánh giá phục vụ</h1>
            <p>Chỉ hiển thị dữ liệu của tài khoản đang đăng nhập, để bạn theo dõi phản hồi khách hàng một cách riêng tư.</p>
        </div>
        <div class="staff-rating-hero__pill">Tổng quan</div>
    </div>

    <div class="staff-rating-summary">
        <div class="staff-rating-stat staff-rating-stat--good">
            <span>Tốt</span>
            <strong>{{ $ratingDashboard['counts']['good'] }}</strong>
        </div>
        <div class="staff-rating-stat staff-rating-stat--average">
            <span>Trung bình</span>
            <strong>{{ $ratingDashboard['counts']['average'] }}</strong>
        </div>
        <div class="staff-rating-stat staff-rating-stat--bad">
            <span>Tệ</span>
            <strong>{{ $ratingDashboard['counts']['bad'] }}</strong>
        </div>
    </div>

    <div class="staff-rating-grid">
        <section class="staff-rating-card staff-rating-card--accent">
            <div class="staff-rating-card__head">
                <div>
                    <h2>Tiến độ thưởng hôm nay</h2>
                    <p>{{ $ratingDashboard['reward_progress']['eligible_count'] }}/{{ $ratingDashboard['reward_progress']['max_count'] }} lượt Tốt được thưởng</p>
                </div>
                <strong>{{ $ratingDashboard['reward_progress']['amount_label'] }}</strong>
            </div>
            <div class="staff-rating-progress">
                <i style="width: {{ $ratingDashboard['reward_progress']['percent'] }}%;"></i>
            </div>
            <div class="staff-rating-card__meta">
                <span>{{ $ratingDashboard['reward_progress']['percent'] }}% giới hạn thưởng trong ngày</span>
                <span>{{ $ratingDashboard['reward_progress']['eligible_count'] }} đủ điều kiện</span>
            </div>
        </section>

        <section class="staff-rating-card">
            <div class="staff-rating-card__head">
                <div>
                    <h2>Đánh giá mới</h2>
                    <p>10 phản hồi gần nhất trong tháng</p>
                </div>
            </div>

            <div class="staff-rating-list">
                @forelse ($ratingDashboard['latest'] as $rating)
                    <button
                        type="button"
                        class="staff-rating-item staff-rating-item--{{ $rating['rating_tone'] }}"
                        data-staff-rating-open
                        data-rating='@json($rating)'
                    >
                        <span class="staff-rating-item__icon">{{ mb_substr($rating['rating_label'], 0, 1) }}</span>
                        <div class="staff-rating-item__content">
                            <b>{{ $rating['rating_label'] }}</b>
                            <p>{{ $rating['comment'] ?: 'Không có lời nhắn.' }}</p>
                        </div>
                        <time>{{ $rating['submitted_at'] }}</time>
                    </button>
                @empty
                    <div class="staff-rating-empty">Chưa có đánh giá nào trong tháng này.</div>
                @endforelse
            </div>
        </section>

        <section class="staff-rating-card">
            <div class="staff-rating-card__head">
                <div>
                    <h2>Lịch sử thưởng</h2>
                    <p>Trạng thái reward từ đánh giá Tốt trong tháng</p>
                </div>
            </div>

            <div class="staff-reward-list">
                @forelse ($ratingDashboard['reward_history'] as $reward)
                    <div class="staff-reward-item">
                        <div>
                            <b>{{ $reward['business_date'] }}</b>
                            <span>{{ $reward['rating_label'] }} · {{ $reward['status_label'] }}</span>
                        </div>
                        <strong>{{ $reward['amount_label'] }}</strong>
                    </div>
                @empty
                    <div class="staff-rating-empty">Chưa có lịch sử thưởng trong tháng này.</div>
                @endforelse
            </div>
        </section>
    </div>
</section>
