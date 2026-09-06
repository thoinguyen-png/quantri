<x-app-layout>
    <div class="p-4 space-y-4">
        <div>
            <h1 class="text-2xl font-bold">Gán giờ làm cho nhân viên</h1>
            <p class="text-sm text-gray-500">Tạm thời chỉ gán ca mặc định, không gán theo ngày.</p>
        </div>

        <form method="POST" action="{{ route('shift-assignments.store') }}" class="space-y-4">
            @csrf

            <div>
                <label class="font-bold">Nhân viên</label>
                <select name="user_id" class="w-full mt-1 rounded-xl border-gray-300" required>
                    @foreach ($users as $user)
                        <option value="{{ $user->id }}">
                            {{ $user->name }} - {{ $user->email }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="font-bold">Giờ làm</label>
                <select name="shift_id" class="w-full mt-1 rounded-xl border-gray-300" required>
                    @foreach ($shifts as $shift)
                        <option value="{{ $shift->id }}">
                            {{ $shift->name }}
                            |
                            {{ $shift->start_at->format('H:i') }}
                            -
                            {{ $shift->end_at->format('H:i') }}
                        </option>
                    @endforeach
                </select>
            </div>

            <button class="w-full bg-blue-600 text-white py-4 rounded-2xl font-bold shadow">
                Lưu giờ làm
            </button>
        </form>
    </div>
</x-app-layout>
