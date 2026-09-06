<x-app-layout>
    <div class="min-h-[70vh] bg-gray-100 p-4">
        <div class="mx-auto max-w-md rounded-3xl bg-white p-6 text-center shadow">
            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-red-100 text-2xl font-black text-red-600">
                !
            </div>
            <h1 class="mt-4 text-2xl font-bold text-slate-900">Không hỗ trợ mã QR này.</h1>
            <p class="mt-2 text-sm text-slate-500">
                Vui lòng quét mã QR được tạo từ hệ thống ChamCongV2.
            </p>
            <a
                href="{{ route('dashboard') }}"
                class="mt-5 inline-flex rounded-2xl bg-gray-900 px-5 py-3 font-bold text-white"
            >
                Quay lại trang chủ
            </a>
        </div>
    </div>
</x-app-layout>
