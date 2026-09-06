<x-guest-layout>
    <div class="space-y-5 text-center">
        <div class="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-red-50 text-red-600">
            <svg viewBox="0 0 24 24" class="h-7 w-7 fill-current" aria-hidden="true">
                <path d="M12 2 3.5 5.75v6.45c0 5.1 3.55 8.75 8.5 9.8 4.95-1.05 8.5-4.7 8.5-9.8V5.75L12 2Zm.85 5.5v6.25h-1.7V7.5h1.7Zm0 8v1.9h-1.7v-1.9h1.7Z"/>
            </svg>
        </div>

        <div>
            <h1 class="text-2xl font-black text-slate-950">Phiên đăng nhập đã kết thúc</h1>
            <p class="mt-2 text-sm leading-6 text-slate-500">
                Bạn đã đăng xuất hoặc phiên làm việc không còn hợp lệ. Vui lòng đăng nhập lại để tiếp tục.
            </p>
        </div>

        <a
            href="{{ route('login', absolute: false) }}"
            class="inline-flex min-h-12 w-full items-center justify-center rounded-2xl bg-slate-950 px-5 font-bold text-white transition hover:bg-slate-800 focus:outline-none focus:ring-4 focus:ring-slate-300"
        >
            Quay lại đăng nhập
        </a>
    </div>
</x-guest-layout>
