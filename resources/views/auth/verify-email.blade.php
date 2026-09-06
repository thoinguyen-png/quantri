<x-guest-layout>
    <div class="mb-4 text-sm text-gray-600">
        Vui lòng xác minh email bằng liên kết hệ thống vừa gửi. Nếu chưa nhận được email, bạn có thể gửi lại.
    </div>

    @if (session('status') == 'verification-link-sent')
        <div class="mb-4 font-medium text-sm text-green-600">
            Liên kết xác minh mới đã được gửi đến email của bạn.
        </div>
    @endif

    <div class="mt-4 flex items-center justify-between">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf

            <div>
                <x-primary-button>
                    Gửi lại email xác minh
                </x-primary-button>
            </div>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf

            <button type="submit" class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                Đăng xuất
            </button>
        </form>
    </div>
</x-guest-layout>
