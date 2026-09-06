@php
    $panelId = 'rating-qr-panel-' . $user->id . '-' . uniqid();
    $canViewRatingQr = \Illuminate\Support\Facades\Gate::allows('viewRatingQr', $user);
    $canManageRatingQr = \Illuminate\Support\Facades\Gate::allows('manageRatingQr', $user);
    $ratingQrEnabled = (bool) $user->rating_qr_enabled;
@endphp

@if ($canViewRatingQr)
    <section
        id="{{ $panelId }}"
        class="rating-qr-card p-4"
        data-rating-qr-panel
        data-rating-qr-autoload="{{ ($autoload ?? true) ? '1' : '0' }}"
        data-rating-qr-enabled="{{ $ratingQrEnabled ? '1' : '0' }}"
        data-rating-qr-show-url="{{ route('rating-qrs.show', $user, absolute: false) }}"
        data-rating-qr-enable-url="{{ route('rating-qrs.enable', $user, absolute: false) }}"
        data-rating-qr-disable-url="{{ route('rating-qrs.disable', $user, absolute: false) }}"
        data-rating-qr-regenerate-url="{{ route('rating-qrs.regenerate', $user, absolute: false) }}"
    >
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h2 class="text-lg font-black text-slate-950">{{ $title ?? 'Mã QR đánh giá' }}</h2>
                <p class="mt-1 text-sm text-slate-500">QR này chỉ dùng để khách mở trang web đánh giá.</p>
            </div>

            @if ($canManageRatingQr)
                <div class="flex shrink-0 flex-wrap gap-2">
                    @if ($ratingQrEnabled)
                        <button type="button" class="rating-qr-button rating-qr-button--danger" data-rating-qr-disable>Khóa QR</button>
                        <button type="button" class="rating-qr-button rating-qr-button--dark" data-rating-qr-regenerate>Cấp lại QR</button>
                    @else
                        <button type="button" class="rating-qr-button rating-qr-button--primary" data-rating-qr-enable>Bật QR</button>
                    @endif
                </div>
            @endif
        </div>

        <div class="mt-4 grid gap-4 md:grid-cols-[15rem_minmax(0,1fr)]">
            <div class="rating-qr-preview {{ $ratingQrEnabled ? '' : 'hidden' }}" data-rating-qr-preview>
                <img data-rating-qr-image alt="QR đánh giá của {{ $user->name }}">
            </div>

            <div class="{{ $ratingQrEnabled ? 'hidden' : '' }} rounded-2xl bg-slate-50 p-4 text-sm text-slate-600" data-rating-qr-disabled>
                QR đánh giá đang bị khóa hoặc chưa bật cho nhân sự này.
            </div>

            <div class="space-y-3">
                <div class="rounded-2xl bg-slate-50 p-4">
                    <div class="text-sm font-black text-slate-900">{{ $user->name }}</div>
                    <div class="mt-1 font-mono text-xs font-black text-slate-500">ID {{ $user->employee_code }}</div>
                    <div class="mt-1 text-sm text-slate-500">{{ $user->branch?->name ?? 'Chưa có chi nhánh' }}</div>
                    <div class="mt-2 text-xs text-slate-500">Link khách đánh giá chỉ được lấy sau khi gọi endpoint QR.</div>
                </div>

                <div class="rating-qr-actions {{ $ratingQrEnabled ? '' : 'hidden' }}" data-rating-qr-actions>
                    <button type="button" class="rating-qr-button rating-qr-button--muted" data-rating-qr-view>Xem lại QR</button>
                    <a href="{{ route('rating-qrs.download', $user, absolute: false) }}" class="rating-qr-button rating-qr-button--primary" data-rating-qr-download>Tải SVG</a>
                    <button type="button" class="rating-qr-button rating-qr-button--muted" data-rating-qr-copy>Copy link</button>
                </div>

                <p class="min-h-5 text-sm font-semibold text-blue-700" data-rating-qr-message></p>
            </div>
        </div>
    </section>
@endif
