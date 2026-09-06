<x-app-layout>
    <style>
        .ratings-feed-leaderboard {
            margin-bottom: 20px;
            padding: 20px;
            border: 1px solid #e5e7eb;
            border-radius: 18px;
            background: #fff;
            box-shadow: 0 8px 24px rgba(15, 23, 42, .06);
        }

        .ratings-feed-leaderboard__head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 16px;
        }

        .ratings-feed-leaderboard__eyebrow {
            display: block;
            margin-bottom: 4px;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: .08em;
            color: #64748b;
        }

        .ratings-feed-leaderboard__head h1 {
            margin: 0;
            font-size: 22px;
            font-weight: 800;
            color: #0f172a;
        }

        .ratings-feed-leaderboard__head p {
            margin: 5px 0 0;
            font-size: 13px;
            color: #64748b;
        }

        .ratings-feed-leaderboard__all {
            flex: 0 0 auto;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 38px;
            padding: 0 14px;
            border-radius: 10px;
            background: #0f172a;
            color: #fff;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
        }

        .ratings-feed-leaderboard__list {
            display: grid;
            gap: 10px;
        }

        .ratings-feed-leaderboard__item {
            display: grid;
            grid-template-columns: 42px 42px minmax(0, 1fr) auto;
            align-items: center;
            gap: 12px;
            padding: 12px 14px;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            background: #f8fafc;
        }

        .ratings-feed-leaderboard__rank {
            font-size: 24px;
            text-align: center;
        }

        .ratings-feed-leaderboard__avatar {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 42px;
            height: 42px;
            border-radius: 999px;
            background: #e2e8f0;
            color: #334155;
            font-weight: 800;
        }

        .ratings-feed-leaderboard__person {
            min-width: 0;
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .ratings-feed-leaderboard__person strong {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            color: #0f172a;
        }

        .ratings-feed-leaderboard__person span {
            font-size: 12px;
            color: #64748b;
        }

        .ratings-feed-leaderboard__score {
            min-width: 34px;
            text-align: right;
            font-size: 18px;
            color: #16a34a;
        }

        .ratings-feed-leaderboard__empty {
            padding: 18px;
            border: 1px dashed #cbd5e1;
            border-radius: 12px;
            color: #64748b;
            text-align: center;
        }

        @media (max-width: 640px) {
            .ratings-feed-leaderboard {
                padding: 16px;
            }

            .ratings-feed-leaderboard__head {
                align-items: stretch;
                flex-direction: column;
            }

            .ratings-feed-leaderboard__all {
                align-self: flex-start;
            }

            .ratings-feed-leaderboard__item {
                grid-template-columns: 34px 38px minmax(0, 1fr) auto;
                gap: 9px;
                padding: 10px;
            }

            .ratings-feed-leaderboard__rank {
                font-size: 21px;
            }

            .ratings-feed-leaderboard__avatar {
                width: 38px;
                height: 38px;
            }
        }
    </style>

    <div class="ratings-feed-page">
        <section class="ratings-feed-leaderboard">
            <div class="ratings-feed-leaderboard__head">
                <div>
                    <span class="ratings-feed-leaderboard__eyebrow">BẢNG XẾP HẠNG</span>
                    <h1>Top đánh giá tốt tuần này</h1>
                    <p>{{ $topPeriodLabel }}</p>
                </div>

                <a href="{{ route('ratings.feed.top', array_filter([
                    'branch_id' => $branchFilter,
                    'period' => 'week',
                ], fn ($value) => $value !== null && $value !== '')) }}"
                   class="ratings-feed-leaderboard__all">
                    Xem tất cả
                </a>
            </div>

            <div class="ratings-feed-leaderboard__list">
                @forelse ($topEmployees as $leader)
                    @php
                        $rank = $loop->iteration;
                        $employeeName = $leader->employee?->name ?? 'Nhân sự';
                        $rankLabel = match ($rank) {
                            1 => '🥇',
                            2 => '🥈',
                            3 => '🥉',
                            default => '#'.$rank,
                        };
                    @endphp

                    <article class="ratings-feed-leaderboard__item">
                        <div class="ratings-feed-leaderboard__rank">{{ $rankLabel }}</div>

                        <div class="ratings-feed-leaderboard__avatar">
                            {{ mb_strtoupper(mb_substr(trim($employeeName), 0, 1)) }}
                        </div>

                        <div class="ratings-feed-leaderboard__person">
                            <strong>{{ $employeeName }}</strong>
                            <span>{{ (int) $leader->good_count }} đánh giá tốt</span>
                        </div>

                        <strong class="ratings-feed-leaderboard__score">
                            {{ (int) $leader->good_count }}
                        </strong>
                    </article>
                @empty
                    <div class="ratings-feed-leaderboard__empty">
                        Chưa có đánh giá tốt trong tuần này.
                    </div>
                @endforelse
            </div>
        </section>

        <form class="ratings-feed-filter" method="GET" action="{{ route('ratings.feed.index') }}">
            <label>
                <span>Mức đánh giá</span>
                <select name="rating">
                    <option value="">Tất cả</option>
                    <option value="good" @selected($ratingFilter === 'good')>Tốt</option>
                    <option value="average" @selected($ratingFilter === 'average')>Trung bình</option>
                    <option value="bad" @selected($ratingFilter === 'bad')>Tệ</option>
                </select>
            </label>

            @if ($branchOptions->isNotEmpty())
                <label>
                    <span>Chi nhánh</span>
                    <select name="branch_id">
                        <option value="">Toàn hệ thống</option>
                        @foreach ($branchOptions as $branch)
                            <option value="{{ $branch->id }}" @selected((string) $branchFilter === (string) $branch->id)>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </label>
            @endif

            <button type="submit">Lọc</button>
        </form>

        <section class="ratings-feed-list">
            @forelse ($ratings as $rating)
                <article class="ratings-feed-item ratings-feed-item--{{ $rating->rating }}">
                    <div class="ratings-feed-item__avatar">{{ mb_strtoupper(mb_substr(trim($rating->employee?->name ?? 'N'), 0, 1)) }}</div>
                    <div class="ratings-feed-item__body">
                        <div class="ratings-feed-item__top">
                            <strong>{{ $rating->employee?->name ?? 'Nhân sự' }}</strong>
                            <span>{{ match ($rating->rating) { 'bad' => 'Tệ', 'average' => 'Trung bình', 'good' => 'Tốt', default => 'Đánh giá' } }}</span>
                        </div>
                        <p>{{ $rating->comment ?: 'Không có lời nhắn.' }}</p>
                        <div class="ratings-feed-item__meta">
                            <time>{{ $rating->submitted_at?->format('H:i d/m/Y') }}</time>
                            <span>{{ \App\Notifications\CustomerRatingReceivedNotification::rewardStatusLabel($rating->reward?->status, $rating->reward?->reason_code) }}</span>
                        </div>
                    </div>
                </article>
            @empty
                <div class="ratings-feed-empty">Chưa có đánh giá nào.</div>
            @endforelse
        </section>

        <div class="ratings-feed-pagination">
            {{ $ratings->links() }}
        </div>
    </div>
</x-app-layout>