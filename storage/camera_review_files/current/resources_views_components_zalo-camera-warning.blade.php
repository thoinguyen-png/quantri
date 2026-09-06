@php
    $userAgent = request()->userAgent() ?? '';
    $inAppPatterns = ['zalo', 'fbav', 'fb_iab', 'messenger', 'instagram', 'line'];
    $isInAppBrowser = collect($inAppPatterns)->contains(
        fn ($pattern) => stripos($userAgent, $pattern) !== false
    );
@endphp

@if ($isInAppBrowser)
    <div class="mb-4 rounded-2xl bg-amber-50 p-4 text-sm text-amber-800 ring-1 ring-amber-200">
        <div class="font-bold">Trình duyệt trong ứng dụng có thể chặn quyền camera.</div>
        <div class="mt-1">Nếu không mở được camera, hãy mở link bằng Chrome/Safari.</div>
        <div class="mt-3 flex flex-wrap gap-2">
            <a
                href="{{ url()->current() }}"
                target="_blank"
                rel="noopener"
                class="rounded-xl bg-amber-600 px-3 py-2 font-bold text-white"
            >
                Mở bằng trình duyệt
            </a>
            <button
                type="button"
                class="rounded-xl bg-white px-3 py-2 font-bold text-amber-700 ring-1 ring-amber-200"
                onclick="navigator.clipboard?.writeText(window.location.href); this.innerText = 'Đã copy link';"
            >
                Copy link
            </button>
        </div>
    </div>
@endif
