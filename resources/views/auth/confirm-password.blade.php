<x-guest-layout>
    <div class="mb-4 text-sm text-gray-600">
        Đây là khu vực bảo mật. Vui lòng xác nhận mật khẩu trước khi tiếp tục.
    </div>

    <form method="POST" action="{{ route('password.confirm') }}">
        @csrf

        <div>
            <x-input-label for="password" value="Mật khẩu" />

            <x-text-input id="password" class="block mt-1 w-full"
                            type="password"
                            name="password"
                            required autocomplete="current-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="flex justify-end mt-4">
            <x-primary-button>
                Xác nhận
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
