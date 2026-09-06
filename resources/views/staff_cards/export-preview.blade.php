<x-app-layout>
    @php
    $templateLabels = [
    'horizontal' => 'Mẫu ngang 80 × 24 mm',
    'vertical' => 'Bảng đánh giá A5',
    'both' => 'Cả hai mẫu',
    ];
    @endphp

    <div class="mx-auto max-w-6xl space-y-5 p-4 sm:p-6">
        <section class="rounded-[2rem] bg-white p-5 shadow-sm ring-1 ring-slate-200 sm:p-6">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="text-sm font-bold text-indigo-600">Kiểm tra trước khi xuất</p>
                    <h1 class="mt-1 text-2xl font-black text-slate-950">{{ $branchName }}</h1>
                    <p class="mt-2 text-sm text-slate-500">{{ $cards->count() }} nhân sự · {{ $templateLabels[$selection['template']] }}</p>
                </div>
                <a href="{{ route('staff-cards.export.index', ['branch_id' => $selection['branch_id']]) }}" class="rounded-xl bg-slate-100 px-4 py-2 text-sm font-bold text-slate-700">Chọn lại</a>
            </div>
        </section>
        @if ($invalidQrNames->isNotEmpty())
        <section
            class="rounded-2xl border border-rose-200 bg-rose-50 p-5">
            <div class="flex items-start gap-3">
                <div
                    class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-rose-100 text-lg font-black text-rose-700">
                    !
                </div>

                <div class="min-w-0">
                    <h2 class="font-black text-rose-800">
                        Chưa thể xuất PDF
                    </h2>

                    <p class="mt-1 text-sm font-semibold leading-6 text-rose-700">
                        Các nhân sự sau có mã QR đánh giá bị thiếu
                        hoặc lỗi và cần được cấp lại:
                    </p>

                    <ul
                        class="mt-3 list-disc space-y-1 pl-5 text-sm font-black text-rose-800">
                        @foreach ($invalidQrNames as $employeeName)
                        <li>{{ $employeeName }}</li>
                        @endforeach
                    </ul>

                    <p class="mt-3 text-sm font-semibold text-rose-700">
                        Hãy cấp lại mã QR cho các nhân sự trên,
                        sau đó mở lại trang xem trước.
                    </p>
                </div>
            </div>
        </section>
        @endif

        <section class="grid gap-3 md:grid-cols-2">
            @foreach ($cards as $card)
            <article class="flex items-center gap-4 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
                <div class="grid h-16 w-16 shrink-0 place-items-center overflow-hidden rounded-2xl bg-indigo-600 text-xl font-black text-white">
                    @if ($card['avatar_url'])
                    <img src="{{ $card['avatar_url'] }}" alt="" class="h-full w-full object-cover">
                    @else
                    {{ mb_strtoupper(mb_substr($card['name'], 0, 1)) }}
                    @endif
                </div>
                <div class="min-w-0 flex-1">
                    <h2 class="truncate font-black text-slate-900">{{ $card['name'] }}</h2>
                    <p class="text-sm font-semibold text-slate-500">{{ $card['position'] }}</p>
                    <div class="mt-2 flex flex-wrap gap-2 text-xs font-bold">
                        <span class="rounded-full bg-slate-100 px-2 py-1 text-slate-600">Mã {{ $card['citizen_last4'] }}</span>
                        <span class="rounded-full px-2 py-1 {{ $card['has_rating_qr'] ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">
                            {{ $card['has_rating_qr'] ? 'Có QR đánh giá' : 'Chưa có QR đánh giá' }}
                        </span>
                    </div>
                </div>
            </article>
            @endforeach
        </section>

        <form method="POST" action="{{ route('staff-cards.export.download') }}" class="sticky bottom-4 rounded-2xl bg-slate-950 p-4 shadow-2xl">
            @csrf
            <input type="hidden" name="branch_id" value="{{ $selection['branch_id'] }}">
            <input type="hidden" name="selection_mode" value="{{ $selection['selection_mode'] }}">
            <input type="hidden" name="template" value="{{ $selection['template'] }}">
            @foreach ($selection['user_ids'] ?? [] as $userId)
            <input type="hidden" name="user_ids[]" value="{{ $userId }}">
            @endforeach
            <button
                type="submit"
                @disabled($invalidQrNames->isNotEmpty())
                class="w-full rounded-xl bg-white px-5 py-3 text-sm font-black text-slate-950 disabled:cursor-not-allowed disabled:bg-slate-700 disabled:text-slate-400"
                >
                @if ($invalidQrNames->isNotEmpty())
                Không thể xuất — cần cấp lại QR
                @elseif ($selection['template'] === 'both')
                Tải ZIP hai mẫu
                @else
                Tải PDF
                @endif
            </button>
        </form>
    </div>
</x-app-layout>