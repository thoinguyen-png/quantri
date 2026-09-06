<x-app-layout>
    <div class="ratings-feed-page">
        <header class="ratings-feed-hero">
            <div>
                <span>MAXSIM</span>
                <h1>Duyệt đánh giá nghi ngờ</h1>
                <p>Kiểm tra các đánh giá cần duyệt thủ công trước khi hợp thức hóa thưởng.</p>
            </div>
        </header>

        <form class="ratings-feed-filter" method="GET" action="{{ route('ratings.risk-review.index') }}">
            <label>
                <span>Ngày nghiệp vụ</span>
                <input type="date" name="business_date" value="{{ $filters['business_date'] ?? '' }}">
            </label>

            <label>
                <span>Nhân sự</span>
                <select name="employee_id">
                    <option value="">Tất cả</option>
                    @foreach ($employeeOptions as $employee)
                        <option value="{{ $employee->id }}" @selected((string) ($filters['employee_id'] ?? '') === (string) $employee->id)>
                            {{ $employee->name }}
                        </option>
                    @endforeach
                </select>
            </label>

            <label>
                <span>Mức đánh giá</span>
                <select name="rating">
                    <option value="">Tất cả</option>
                    <option value="good" @selected(($filters['rating'] ?? '') === 'good')>Tốt</option>
                    <option value="average" @selected(($filters['rating'] ?? '') === 'average')>Trung bình</option>
                    <option value="bad" @selected(($filters['rating'] ?? '') === 'bad')>Tệ</option>
                </select>
            </label>

            @if ($branchOptions->isNotEmpty())
                <label>
                    <span>Chi nhánh</span>
                    <select name="branch_id">
                        <option value="">Toàn hệ thống</option>
                        @foreach ($branchOptions as $branch)
                            <option value="{{ $branch->id }}" @selected((string) ($filters['branch_id'] ?? '') === (string) $branch->id)>
                                {{ $branch->name }}
                            </option>
                        @endforeach
                    </select>
                </label>
            @endif

            <label>
                <span>Lý do nghi ngờ</span>
                <select name="risk_reason">
                    <option value="">Tất cả</option>
                    @foreach ($reasonLabels as $code => $label)
                        <option value="{{ $code }}" @selected(($filters['risk_reason'] ?? '') === $code)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <label>
                <span>Trạng thái thưởng</span>
                <select name="reward_status">
                    <option value="">Tất cả</option>
                    @foreach ($rewardStatusOptions as $status)
                        <option value="{{ $status }}" @selected(($filters['reward_status'] ?? '') === $status)>{{ str_replace('_', ' ', $status) }}</option>
                    @endforeach
                </select>
            </label>

            <button type="submit">Lọc</button>
        </form>

        <section class="ratings-feed-list">
            @forelse ($ratings as $rating)
                @php
                    $reasons = is_array($rating->risk_reasons) ? $rating->risk_reasons : [];
                    $backUrl = request()->fullUrl();
                @endphp

                <article class="ratings-feed-item ratings-feed-item--{{ $rating->rating }}">
                    <div class="ratings-feed-item__avatar">{{ mb_strtoupper(mb_substr(trim($rating->employee?->name ?? 'N'), 0, 1)) }}</div>
                    <div class="ratings-feed-item__body">
                        <div class="ratings-feed-item__top">
                            <strong>{{ $rating->employee?->name ?? 'Nhân sự' }}</strong>
                            <span>{{ match ($rating->rating) { 'bad' => 'Tệ', 'average' => 'Trung bình', 'good' => 'Tốt', default => 'Đánh giá' } }}</span>
                        </div>

                        <p>{{ $rating->comment ?: 'Không có lời nhắn.' }}</p>

                        <div class="ratings-feed-item__meta">
                            <span>{{ $rating->branch?->name ?? 'Chưa có chi nhánh' }}</span>
                            <span>Ngày: {{ $rating->business_date?->format('d/m/Y') }}</span>
                            <time>{{ $rating->submitted_at?->format('H:i d/m/Y') }}</time>
                            <span>Thưởng: {{ $rating->reward?->status ? str_replace('_', ' ', $rating->reward->status) : 'Không có' }}</span>
                        </div>

                        <div class="risk-review-grid">
                            <div>
                                <strong>Lý do nghi ngờ</strong>
                                <ul>
                                    @forelse ($reasons as $reason)
                                        <li>{{ $reasonLabels[$reason] ?? $reason }}</li>
                                    @empty
                                        <li>Không có lý do.</li>
                                    @endforelse
                                </ul>
                            </div>
                            <div>
                                <strong>Dấu vết rút gọn</strong>
                                <p>IP: {{ $rating->ip_hash ? substr($rating->ip_hash, 0, 10) : 'Không có' }}</p>
                                <p>Browser: {{ substr($rating->guest_browser_hash, 0, 10) }}</p>
                            </div>
                            <div>
                                <strong>Duyệt</strong>
                                <p>{{ $rating->reviewer?->name ?? 'Chưa duyệt' }}</p>
                                <p>{{ $rating->reviewed_at?->format('H:i d/m/Y') ?? '' }}</p>
                            </div>
                        </div>

                        <div class="risk-review-actions">
                            <form method="POST" action="{{ route('ratings.risk-review.approve', $rating) }}">
                                @csrf
                                <input type="hidden" name="back" value="{{ $backUrl }}">
                                <input type="text" name="review_note" maxlength="500" placeholder="Ghi chú duyệt nếu cần">
                                <button type="submit">Duyệt hợp lệ</button>
                            </form>

                            <form method="POST" action="{{ route('ratings.risk-review.reject', $rating) }}">
                                @csrf
                                <input type="hidden" name="back" value="{{ $backUrl }}">
                                <input type="text" name="review_note" maxlength="500" placeholder="Ghi chú từ chối nếu cần">
                                <button type="submit" class="risk-review-actions__danger">Từ chối thưởng</button>
                            </form>
                        </div>
                    </div>
                </article>
            @empty
                <div class="ratings-feed-empty">Không có đánh giá nghi ngờ đang chờ duyệt.</div>
            @endforelse
        </section>

        <div class="ratings-feed-pagination">
            {{ $ratings->links() }}
        </div>
    </div>
</x-app-layout>
