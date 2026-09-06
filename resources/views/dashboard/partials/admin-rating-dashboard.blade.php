<section class="admin-rating-dashboard" aria-label="Thống kê đánh giá toàn hệ thống">
    <div class="admin-rating-head">
        <div>
            <span>Admin · {{ $ratingDashboard['month_label'] }}</span>
            <h1>Thống kê QR đánh giá toàn hệ thống</h1>
            <p>Đánh giá mới, phản hồi Tệ, hiệu suất và trạng thái thưởng.</p>
        </div>
        <a href="{{ route('settings.edit') }}">Cấu hình thưởng</a>
    </div>

    <div class="admin-rating-priority">
        <section class="admin-rating-panel">
            <div class="admin-rating-title">
                <div>
                    <h2>Đánh giá mới</h2>
                    <p>10 phản hồi mới nhất toàn hệ thống</p>
                </div>
            </div>
            <div class="admin-rating-feed">
                @forelse ($ratingDashboard['latest'] as $rating)
                    <button type="button" class="admin-rating-feedback admin-rating-feedback--{{ $rating['rating_tone'] }}" data-admin-feedback='@json($rating)'>
                        <span>{{ mb_substr($rating['rating_label'], 0, 1) }}</span>
                        <div>
                            <b>{{ $rating['employee_name'] }} · {{ $rating['rating_label'] }}</b>
                            <p>{{ $rating['branch_name'] }} · {{ $rating['comment'] ?: 'Không có lời nhắn.' }}</p>
                        </div>
                        <time>{{ $rating['submitted_at'] }}</time>
                    </button>
                @empty
                    <div class="admin-rating-empty">Chưa có đánh giá mới trong tháng này.</div>
                @endforelse
            </div>
        </section>

        <section class="admin-rating-panel admin-rating-panel--alert">
            <div class="admin-rating-title">
                <div>
                    <h2>Phản hồi Tệ cần xử lý</h2>
                    <p>Ưu tiên cao trên toàn hệ thống</p>
                </div>
            </div>
            <button type="button" class="admin-rating-alert" data-admin-list-open>
                <strong>{{ $ratingDashboard['bad_feedback']->count() }}</strong>
                <span>Mở danh sách phản hồi Tệ</span>
            </button>
            <div class="admin-rating-feed admin-rating-feed--compact">
                @forelse ($ratingDashboard['bad_feedback']->take(3) as $rating)
                    <button type="button" class="admin-rating-feedback admin-rating-feedback--bad" data-admin-feedback='@json($rating)'>
                        <span>{{ mb_substr($rating['rating_label'], 0, 1) }}</span>
                        <div>
                            <b>{{ $rating['employee_name'] }}</b>
                            <p>{{ $rating['branch_name'] }} · {{ $rating['comment'] ?: 'Không có lời nhắn.' }}</p>
                        </div>
                    </button>
                @empty
                    <div class="admin-rating-empty">Không có phản hồi Tệ trong tháng này.</div>
                @endforelse
            </div>
        </section>
    </div>

    <div class="admin-rating-grid">
        <section class="admin-rating-panel">
            <div class="admin-rating-title">
                <div>
                    <h2>Hiệu suất chi nhánh</h2>
                    <p>Tốt / Trung bình / Tệ theo chi nhánh</p>
                </div>
            </div>
            <div class="admin-performance-list">
                @forelse ($ratingDashboard['branch_performance'] as $branch)
                    <div class="admin-performance-row">
                        <div>
                            <b>{{ $branch['name'] }}</b>
                            <small>{{ $branch['good'] + $branch['average'] + $branch['bad'] }} đánh giá</small>
                        </div>
                        <div class="admin-rating-pills">
                            <span class="is-good">{{ $branch['good'] }} Tốt</span>
                            <span class="is-average">{{ $branch['average'] }} TB</span>
                            <span class="is-bad">{{ $branch['bad'] }} Tệ</span>
                        </div>
                    </div>
                @empty
                    <div class="admin-rating-empty">Chưa có dữ liệu chi nhánh trong tháng này.</div>
                @endforelse
            </div>
        </section>

        <section class="admin-rating-panel">
            <div class="admin-rating-title">
                <div>
                    <h2>Hiệu suất nhân sự</h2>
                    <p>Tốt / Trung bình / Tệ theo nhân sự</p>
                </div>
            </div>
            <div class="admin-performance-list">
                @forelse ($ratingDashboard['employee_performance'] as $employee)
                    <div class="admin-performance-row">
                        <div>
                            <b>{{ $employee['name'] }}</b>
                            <small>{{ $employee['branch_name'] }} · {{ $employee['role'] }}</small>
                        </div>
                        <div class="admin-rating-pills">
                            <span class="is-good">{{ $employee['good'] }} Tốt</span>
                            <span class="is-average">{{ $employee['average'] }} TB</span>
                            <span class="is-bad">{{ $employee['bad'] }} Tệ</span>
                        </div>
                    </div>
                @empty
                    <div class="admin-rating-empty">Chưa có dữ liệu nhân sự trong tháng này.</div>
                @endforelse
            </div>
        </section>

        <section class="admin-rating-panel">
            <div class="admin-rating-title">
                <div>
                    <h2>Tổng thưởng</h2>
                    <p>Trạng thái reward toàn hệ thống</p>
                </div>
                <strong>{{ $ratingDashboard['reward_total_amount_label'] }}</strong>
            </div>
            <div class="admin-reward-list">
                @forelse ($ratingDashboard['reward_statuses'] as $status)
                    <div class="admin-reward-row">
                        <div>
                            <b>{{ $status['status_label'] }}</b>
                            <span>{{ $status['count'] }} lượt</span>
                        </div>
                        <strong>{{ $status['amount_label'] }}</strong>
                    </div>
                @empty
                    <div class="admin-rating-empty">Chưa có reward trong tháng này.</div>
                @endforelse
            </div>
        </section>

        <section class="admin-rating-panel admin-rating-panel--wide">
            <div class="admin-rating-title">
                <div>
                    <h2>Danh sách QR nhân sự</h2>
                    <p>Xem, tải, khóa, bật và cấp lại QR. Admin không có QR cá nhân.</p>
                </div>
            </div>
            <div class="admin-qr-grid">
                @forelse ($ratingDashboard['qr_users'] as $qrUser)
                    <details class="admin-qr-card">
                        <summary>
                            <span>
                                <b>{{ $qrUser->name }}</b>
                                <small>{{ $qrUser->branch?->name ?? 'Chưa có chi nhánh' }} · {{ $qrUser->role }}</small>
                            </span>
                            <strong>{{ $qrUser->rating_qr_enabled ? 'Đang bật' : 'Đang khóa' }}</strong>
                        </summary>
                        <div class="mt-3">
                            @include('rating_qrs.partials.panel', [
                                'user' => $qrUser,
                                'title' => 'Mã QR đánh giá',
                                'autoload' => false,
                            ])
                        </div>
                    </details>
                @empty
                    <div class="admin-rating-empty">Chưa có nhân sự đủ điều kiện QR đánh giá.</div>
                @endforelse
            </div>
        </section>
    </div>

    <div class="admin-rating-modal" data-admin-rating-modal hidden>
        <button type="button" class="admin-rating-modal__backdrop" data-admin-modal-close aria-label="Đóng"></button>
        <section class="admin-rating-modal__panel" role="dialog" aria-modal="true">
            <div data-admin-list-view>
                <div class="admin-rating-modal__head">
                    <div>
                        <span>Danh sách phản hồi</span>
                        <h2>Phản hồi Tệ cần xử lý</h2>
                    </div>
                    <button type="button" data-admin-modal-close>Đóng</button>
                </div>
                <div class="admin-rating-feed">
                    @forelse ($ratingDashboard['bad_feedback'] as $rating)
                        <button type="button" class="admin-rating-feedback admin-rating-feedback--bad" data-admin-feedback='@json($rating)'>
                            <span>{{ mb_substr($rating['rating_label'], 0, 1) }}</span>
                            <div>
                                <b>{{ $rating['employee_name'] }}</b>
                                <p>{{ $rating['branch_name'] }} · {{ $rating['comment'] ?: 'Không có lời nhắn.' }}</p>
                            </div>
                            <time>{{ $rating['submitted_at'] }}</time>
                        </button>
                    @empty
                        <div class="admin-rating-empty">Không có phản hồi Tệ trong tháng này.</div>
                    @endforelse
                </div>
            </div>

            <div data-admin-detail-view hidden>
                <div class="admin-rating-modal__head">
                    <div>
                        <span>Chi tiết phản hồi</span>
                        <h2 data-admin-detail-title>--</h2>
                    </div>
                    <button type="button" data-admin-list-back>Danh sách</button>
                </div>
                <div class="admin-rating-detail">
                    <div><span>Nhân sự</span><strong data-admin-detail-employee>--</strong></div>
                    <div><span>Chi nhánh</span><strong data-admin-detail-branch>--</strong></div>
                    <div><span>Đánh giá</span><strong data-admin-detail-rating>--</strong></div>
                    <div><span>Thời gian</span><strong data-admin-detail-time>--</strong></div>
                    <div><span>Trạng thái thưởng</span><strong data-admin-detail-reward>--</strong></div>
                </div>
                <blockquote data-admin-detail-comment>--</blockquote>
                <button type="button" class="admin-rating-modal__primary" data-admin-modal-close>Đóng</button>
            </div>
        </section>
    </div>
</section>
